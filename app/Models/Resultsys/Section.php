<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'sections';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'grade_id',
    ];
}
