<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'students';

    public $timestamps = true;

    protected $fillable = [
        'national_id',
        'full_name',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(StudentStatus::class, 'status_id');
    }
}
