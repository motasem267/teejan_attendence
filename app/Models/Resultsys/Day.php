<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'days';

    public $timestamps = false;

    protected $fillable = [
        'day_name_ar',
        'day_order',
    ];
}
