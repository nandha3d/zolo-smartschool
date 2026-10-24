<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGroupMember extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'student_group_id',
        'student_id',
        'bench_number',
        'role_in_group',
        'joined_at',
    ];

    protected $casts = [
        'bench_number' => 'integer',
        'joined_at' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }
}
