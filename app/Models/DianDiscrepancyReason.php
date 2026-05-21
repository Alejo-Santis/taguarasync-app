<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'applies_to', 'name'])]
class DianDiscrepancyReason extends Model
{
    public $timestamps = false;
}
