<?php

namespace App\Actions\Operations;

use App\Enums\PurchaseRadianStatus;
use App\Models\BankAccountMovement;
use App\Models\PurchaseReceipt;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class GenerateOperationalAlerts
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

                    foreach ($this->alertsForTenant($tenant) as $alert) {
                        $pendingRecipients = $recipients
                            ->reject(fn (User $user): bool => $this->hasUnreadAlert($user, $alert['alert_key']))
                            ->values();

                        if ($pendingRecipients->isEmpty()) {
                            continue;
                        }

                        Notification::send($pendingRecipients, new OperationalAlertNotification($alert, $sendEmail));

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
                    ->orWhereHas('permissions', fn (Builder $query) => $query->whereIn('name', ['reports.view', 'purchases.view', 'settings.manage']));
            })
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alertsForTenant(Tenant $tenant): array
    {
        return [
            ...$this->radianAlerts($tenant),
            ...$this->bankDifferenceAlerts($tenant),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function radianAlerts(Tenant $tenant): array
    {
        return PurchaseReceipt::query()
            ->withoutGlobalScopes()
            ->with('supplier:id,name')
            ->where('tenant_id', $tenant->id)
            ->where('radian_status', PurchaseRadianStatus::Pending)
            ->latest('received_at')
            ->limit(15)
            ->get(['id', 'uuid', 'supplier_id', 'document_number', 'total', 'received_at', 'radian_status'])
            ->map(fn (PurchaseReceipt $receipt): array => [
                'alert_key' => "operations:radian-pending:{$tenant->id}:{$receipt->id}",
                'category' => 'purchases',
                'severity' => 'warning',
                'title' => 'Compra pendiente RADIAN',
                'body' => "{$receipt->document_number} de {$receipt->supplier?->name}: pendiente por validar.",
                'href' => '/purchases?radian_status=pending',
                'meta' => [
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_receipt_uuid' => $receipt->uuid,
                    'document_number' => $receipt->document_number,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankDifferenceAlerts(Tenant $tenant): array
    {
        return BankAccountMovement::query()
            ->withoutGlobalScopes()
            ->with('bankAccount:id,bank_name,account_name')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'difference')
            ->latest('occurred_at')
            ->limit(15)
            ->get(['id', 'bank_account_id', 'amount', 'reference', 'occurred_at', 'status'])
            ->map(fn (BankAccountMovement $movement): array => [
                'alert_key' => "operations:bank-difference:{$tenant->id}:{$movement->id}",
                'category' => 'treasury',
                'severity' => 'critical',
                'title' => 'Movimiento bancario con diferencia',
                'body' => "{$movement->bankAccount?->bank_name} {$movement->reference}: diferencia por ".number_format($movement->amount, 0, ',', '.').'.',
                'href' => '/settings/banks?movement_status=difference',
                'meta' => [
                    'bank_account_movement_id' => $movement->id,
                    'reference' => $movement->reference,
                    'amount' => $movement->amount,
                ],
            ])
            ->all();
    }

    private function hasUnreadAlert(User $user, string $alertKey): bool
    {
        return $user->unreadNotifications()
            ->where('type', OperationalAlertNotification::class)
            ->get(['id', 'data'])
            ->contains(fn ($notification): bool => ($notification->data['alert_key'] ?? null) === $alertKey);
    }
}
