<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'academic_years';

    public $timestamps = false;

    protected $fillable = [
        'year_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getActiveId(): ?int
    {
        return once(fn () => self::where('is_active', true)->value('id'));
    }
}
