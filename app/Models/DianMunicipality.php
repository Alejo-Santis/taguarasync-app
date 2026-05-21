<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'department_code', 'department_name'])]
class DianMunicipality extends Model
{
    public $timestamps = false;
}
