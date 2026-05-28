<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'table_name',
    'record_uuid',
    'strategy_applied',
    'local_data',
    'cloud_data',
    'resolved_data',
    'resolved_at',
])]
class SyncConflictLog extends Model
{
    protected function casts(): array
    {
        return [
            'local_data' => 'array',
            'cloud_data' => 'array',
            'resolved_data' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
