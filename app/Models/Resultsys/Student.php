<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'students';

    public $timestamps = true;

    protected $fillable = [
        'national_id',
        'full_name',
    ];
}
