<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class grade extends Model
{
    protected $connection = 'resultsys';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
