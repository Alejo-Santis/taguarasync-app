<?php

namespace App\Actions\Inventory;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InventoryAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class GenerateInventoryAlerts
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

                        Notification::send($pendingRecipients, new InventoryAlertNotification($alert, $sendEmail));

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
                    ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['owner', 'admin', 'warehouse']))
                    ->orWhereHas('permissions', fn (Builder $query) => $query->whereIn('name', ['inventory.view', 'inventory.adjust']));
            })
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alertsForTenant(Tenant $tenant): array
    {
        return [
            ...$this->lowStockAlerts($tenant),
            ...$this->expiryAlerts($tenant),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStockAlerts(Tenant $tenant): array
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('minimum_stock', '>', 0)
            ->withSum('inventoryLots as stock_on_hand', 'current_quantity')
            ->orderBy('commercial_name')
            ->limit(250)
            ->get(['id', 'uuid', 'commercial_name', 'minimum_stock'])
            ->filter(fn (Product $product): bool => (int) $product->stock_on_hand <= (int) $product->minimum_stock)
            ->take(25)
            ->map(fn (Product $product): array => [
                'alert_key' => "inventory:low-stock:{$tenant->id}:{$product->id}",
                'category' => 'inventory',
                'severity' => ((int) $product->stock_on_hand) === 0 ? 'critical' : 'warning',
                'title' => ((int) $product->stock_on_hand) === 0 ? 'Producto agotado' : 'Stock bajo',
                'body' => "{$product->commercial_name}: stock {$product->stock_on_hand} / mínimo {$product->minimum_stock}.",
                'href' => '/inventory?q='.urlencode($product->commercial_name),
                'meta' => [
                    'product_id' => $product->id,
                    'product_uuid' => $product->uuid,
                    'stock_on_hand' => (int) $product->stock_on_hand,
                    'minimum_stock' => (int) $product->minimum_stock,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiryAlerts(Tenant $tenant): array
    {
        $days = (int) config('taguara.notifications.inventory_expiry_days', 90);

        return InventoryLot::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', today()->addDays($days))
            ->with('product:id,commercial_name')
            ->orderBy('expires_on')
            ->limit(25)
            ->get(['id', 'uuid', 'product_id', 'lot_number', 'expires_on', 'current_quantity'])
            ->map(function (InventoryLot $lot) use ($tenant): array {
                $expired = $lot->expires_on->isBefore(today());
                $daysToExpiry = today()->diffInDays($lot->expires_on, false);
                $productName = $lot->product?->commercial_name ?? 'Producto sin nombre';

                return [
                    'alert_key' => "inventory:expiry:{$tenant->id}:{$lot->id}",
                    'category' => 'inventory',
                    'severity' => $expired ? 'critical' : 'warning',
                    'title' => $expired ? 'Lote vencido' : 'Lote por vencer',
                    'body' => "{$productName} lote {$lot->lot_number}: {$lot->current_quantity} unidades, vence {$lot->expires_on->format('d/m/Y')}.",
                    'href' => '/inventory?expiry='.($expired ? 'expired' : 'soon'),
                    'meta' => [
                        'inventory_lot_id' => $lot->id,
                        'inventory_lot_uuid' => $lot->uuid,
                        'lot_number' => $lot->lot_number,
                        'expires_on' => $lot->expires_on->toDateString(),
                        'days_to_expiry' => $daysToExpiry,
                        'current_quantity' => $lot->current_quantity,
                    ],
                ];
            })
            ->all();
    }

    private function hasUnreadAlert(User $user, string $alertKey): bool
    {
        return $user->unreadNotifications()
            ->where('type', InventoryAlertNotification::class)
            ->get(['id', 'data'])
            ->contains(fn ($notification): bool => ($notification->data['alert_key'] ?? null) === $alertKey);
    }
}
