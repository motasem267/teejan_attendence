<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'employee_statuses';

    public $timestamps = true;

    protected $fillable = [
        'status_name',
    ];
}
