<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'employees';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id',
        'name',
        'emp_type_id',
        'status_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function teacherClasses(): HasMany
    {
        return $this->hasMany(TeacherClass::class, 'teacher_id');
    }
}
