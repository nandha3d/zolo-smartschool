<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabBatchStudent extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'lab_batch_id',
        'student_id',
        'workstation_number',
    ];

    public function batch()
    {
        return $this->belongsTo(LabBatch::class, 'lab_batch_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }
}
