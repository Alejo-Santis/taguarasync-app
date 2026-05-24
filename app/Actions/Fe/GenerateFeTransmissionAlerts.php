<?php

namespace App\Actions\Fe;

use App\Enums\FeStatus;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\FeTransmissionAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class GenerateFeTransmissionAlerts
{
    /**
     * @return array{tenants: int, alerts: int, recipients: int}
     */
    public function execute(bool $sendEmail = false): array
    {
        $summary = ['tenants' => 0, 'alerts' => 0, 'recipients' => 0];

        Tenant::query()
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(50, function (Collection $tenants) use (&$summary, $sendEmail): void {
                foreach ($tenants as $tenant) {
                    $summary['tenants']++;
                    $recipients = $this->recipients($tenant);

                    if ($recipients->isEmpty()) {
                        continue;
                    }

                    foreach ($this->alertsForTenant($tenant) as $alert) {
                        $pendingRecipients = $recipients
                            ->reject(fn (User $user): bool => $this->hasUnreadAlert($user, $alert['alert_key']))
                            ->values();

                        if ($pendingRecipients->isEmpty()) {
                            continue;
                        }

                        Notification::send($pendingRecipients, new FeTransmissionAlertNotification($alert, $sendEmail));

                        $summary['alerts']++;
                        $summary['recipients'] += $pendingRecipients->count();
                    }
                }
            });

        return $summary;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Tenant $tenant): Collection
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['owner', 'admin', 'accountant']))
                    ->orWhereHas('permissions', fn (Builder $query) => $query->whereIn('name', ['billing.view', 'billing.resend']));
            })
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alertsForTenant(Tenant $tenant): array
    {
        return Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('fe_status', [FeStatus::Pending, FeStatus::Rejected, FeStatus::Contingency])
            ->latest()
            ->limit(50)
            ->get(['id', 'uuid', 'document_number', 'fe_status', 'fe_error_message', 'created_at'])
            ->map(fn (Sale $sale): array => $this->alertForSale($tenant, $sale))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function alertForSale(Tenant $tenant, Sale $sale): array
    {
        $status = $sale->fe_status;
        $severity = $status === FeStatus::Rejected ? 'critical' : 'warning';
        $title = match ($status) {
            FeStatus::Contingency => 'Factura en contingencia',
            FeStatus::Rejected => 'Factura rechazada',
            default => 'Factura pendiente',
        };
        $filterStatus = match ($status) {
            FeStatus::Contingency => 'contingency',
            FeStatus::Rejected => 'rejected',
            default => 'pending',
        };

        return [
            'alert_key' => "fe:{$status?->value}:{$tenant->id}:{$sale->id}",
            'category' => 'billing',
            'severity' => $severity,
            'title' => $title,
            'body' => "{$sale->document_number}: {$status?->label()}".($sale->fe_error_message ? " · {$sale->fe_error_message}" : ''),
            'href' => "/fe/submissions?status={$filterStatus}",
            'meta' => [
                'sale_id' => $sale->id,
                'sale_uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'fe_status' => $status?->value,
            ],
        ];
    }

    private function hasUnreadAlert(User $user, string $alertKey): bool
    {
        return $user->unreadNotifications()
            ->where('type', FeTransmissionAlertNotification::class)
            ->get(['id', 'data'])
            ->contains(fn ($notification): bool => ($notification->data['alert_key'] ?? null) === $alertKey);
    }
}
