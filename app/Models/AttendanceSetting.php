<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $table = 'attendance_settings';

    protected $fillable = [
        'teacher_matching_mode',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['teacher_matching_mode' => 'schedule']);
    }

    public function isDurationMode(): bool
    {
        return $this->teacher_matching_mode === 'duration';
    }

    public function toggle(): void
    {
        $this->update([
            'teacher_matching_mode' => $this->isDurationMode() ? 'schedule' : 'duration',
        ]);
    }
}
