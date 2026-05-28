<?php

namespace App\Enums;

enum TenantPlan: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Básico',
            self::Professional => 'Profesional',
            self::Enterprise => 'Enterprise',
        };
    }

    public function monthlyPrice(): int
    {
        return match ($this) {
            self::Basic => 189000,
            self::Professional => 389000,
            self::Enterprise => 589000,
        };
    }

    /**
     * Returns the default limits applied to a tenant when this plan is assigned.
     * null means unlimited.
     *
     * @return array{max_users: int|null, max_cash_registers: int|null, offline_sync_enabled: bool}
     */
    public function defaultLimits(): array
    {
        return match ($this) {
            self::Basic => [
                'max_users' => 3,
                'max_cash_registers' => 1,
                'offline_sync_enabled' => false,
            ],
            self::Professional => [
                'max_users' => 10,
                'max_cash_registers' => 3,
                'offline_sync_enabled' => true,
            ],
            self::Enterprise => [
                'max_users' => null,
                'max_cash_registers' => null,
                'offline_sync_enabled' => true,
            ],
        };
    }
}
