<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'server_id',
    'last_sync_at',
    'last_successful_sync_at',
    'status',
    'error_detail',
])]
class SyncCheckpoint extends Model
{
    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
        ];
    }
}
