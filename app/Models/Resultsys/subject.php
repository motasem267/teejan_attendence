<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class subject extends Model
{
    protected $connection = 'resultsys';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
