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
        $filterStatus = match ($status) {
            FeStatus::Contingency => 'contingency',
            FeStatus::Rejected => 'rejected',
            default => 'pending',
        };

        if ($status === FeStatus::Contingency) {
            $hoursPending = (int) $sale->created_at->diffInHours(now());
            $bucket = $this->contingencyBucket($hoursPending);

            $maxHours = (int) config('sync.fe_contingency_max_hours', 48);
            $severity = $bucket === 'critical' ? 'critical' : 'warning';
            $title = $bucket === 'critical'
                ? "Contingencia urgente: cerca del límite DIAN de {$maxHours}h"
                : 'Factura en contingencia';
            $body = "{$sale->document_number}: {$status->label()} · {$hoursPending}h sin transmitir (límite DIAN: {$maxHours}h)"
                .($sale->fe_error_message ? " · {$sale->fe_error_message}" : '');
            $alertKey = "fe:contingency:{$tenant->id}:{$sale->id}:{$bucket}";
        } else {
            $severity = $status === FeStatus::Rejected ? 'critical' : 'warning';
            $title = match ($status) {
                FeStatus::Rejected => 'Factura rechazada',
                default => 'Factura pendiente',
            };
            $body = "{$sale->document_number}: {$status?->label()}".($sale->fe_error_message ? " · {$sale->fe_error_message}" : '');
            $alertKey = "fe:{$status?->value}:{$tenant->id}:{$sale->id}";
        }

        return [
            'alert_key' => $alertKey,
            'category' => 'billing',
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'href' => "/fe/submissions?status={$filterStatus}",
            'meta' => [
                'sale_id' => $sale->id,
                'sale_uuid' => $sale->uuid,
                'document_number' => $sale->document_number,
                'fe_status' => $status?->value,
            ],
        ];
    }

    /**
     * Buckets contingency age against the DIAN regulatory deadline
     * (Resolución 0165 de 2023 — config('sync.fe_contingency_max_hours'),
     * 48h by default) for transmitting contingency documents. Warns at the
     * halfway point and turns critical 8h before the deadline. Each bucket
     * is a distinct alert key so escalation isn't swallowed by an earlier
     * unread notification at a lower severity.
     */
    private function contingencyBucket(int $hoursPending): string
    {
        $maxHours = (int) config('sync.fe_contingency_max_hours', 48);
        $criticalAt = max($maxHours - 8, 1);
        $warningAt = max((int) ($maxHours / 2), 1);

        return match (true) {
            $hoursPending >= $criticalAt => 'critical',
            $hoursPending >= $warningAt => 'warning',
            default => 'initial',
        };
    }

    private function hasUnreadAlert(User $user, string $alertKey): bool
    {
        return $user->unreadNotifications()
            ->where('type', FeTransmissionAlertNotification::class)
            ->get(['id', 'data'])
            ->contains(fn ($notification): bool => ($notification->data['alert_key'] ?? null) === $alertKey);
    }
}
