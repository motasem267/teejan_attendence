<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassModel extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'classes';

    public $timestamps = false;

    protected $fillable = [
        'grade_id',
        'section_id',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(grade::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
