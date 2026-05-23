<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'document_type',
    'document_id',
    'xml_document_key',
    'attempts',
    'request_payload',
    'response_payload',
    'response_status',
    'is_non_recoverable',
    'submitted_at',
    'responded_at',
])]
class FeSubmission extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'is_non_recoverable' => 'boolean',
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }
}
