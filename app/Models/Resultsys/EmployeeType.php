<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class EmployeeType extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'employee_types';

    public $timestamps = true;

    protected $fillable = [
        'type_name',
    ];
}
