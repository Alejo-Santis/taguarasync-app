<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BillingAlertNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:check')]
#[Description('Checks subscription status for all tenants and notifies the super admin.')]
class CheckBillingCommand extends Command
{
    public function handle(): int
    {
        $superAdmins = User::where('is_super_admin', true)->get();

        if ($superAdmins->isEmpty()) {
            $this->warn('No super admin users found.');

            return self::SUCCESS;
        }

        $tenants = Tenant::whereNot('status', TenantStatus::Archived)->get();

        $expiringSoon = collect();
        $expired = collect();

        foreach ($tenants as $tenant) {
            if (is_null($tenant->subscribed_until)) {
                continue;
            }

            $daysLeft = now()->diffInDays($tenant->subscribed_until, false);

            if ($daysLeft <= 7 && $daysLeft >= 0) {
                $expiringSoon->push(['tenant' => $tenant, 'days_left' => (int) $daysLeft]);
            }

            // Auto-suspend tenants that expired more than 5 days ago and are still active
            if ($daysLeft < -5 && $tenant->status === TenantStatus::Active) {
                $tenant->update(['status' => TenantStatus::Suspended]);
                $expired->push($tenant);
                $this->line("  Suspended: {$tenant->name}");
            }
        }

        if ($expiringSoon->isNotEmpty()) {
            $names = $expiringSoon->map(fn ($item) => "{$item['tenant']->name} ({$item['days_left']}d)")->implode(', ');
            $count = $expiringSoon->count();

            $alert = [
                'alert_key' => 'billing_expiring_'.now()->format('Ymd'),
                'severity' => 'warning',
                'title' => "{$count} farmacia(s) con suscripción por vencer",
                'body' => "Las siguientes farmacias vencen en los próximos 7 días: {$names}.",
                'meta' => ['tenant_ids' => $expiringSoon->pluck('tenant.id')->toArray()],
            ];

            $superAdmins->each->notify(new BillingAlertNotification($alert));
            $this->info("Expiring soon alert sent for {$count} tenant(s).");
        }

        if ($expired->isNotEmpty()) {
            $names = $expired->pluck('name')->implode(', ');
            $count = $expired->count();

            $alert = [
                'alert_key' => 'billing_auto_suspended_'.now()->format('Ymd'),
                'severity' => 'error',
                'title' => "{$count} farmacia(s) suspendida(s) por falta de pago",
                'body' => "Las siguientes farmacias fueron suspendidas automáticamente por vencimiento: {$names}.",
                'meta' => ['tenant_ids' => $expired->pluck('id')->toArray()],
            ];

            $superAdmins->each->notify(new BillingAlertNotification($alert));
            $this->info("Auto-suspended {$count} tenant(s).");
        }

        if ($expiringSoon->isEmpty() && $expired->isEmpty()) {
            $this->info('All subscriptions are up to date.');
        }

        return self::SUCCESS;
    }
}
