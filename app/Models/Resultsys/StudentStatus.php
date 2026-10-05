<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class StudentStatus extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'student_status';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
