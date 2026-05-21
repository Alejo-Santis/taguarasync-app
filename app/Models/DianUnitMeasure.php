<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'description'])]
class DianUnitMeasure extends Model
{
    public $timestamps = false;
}
