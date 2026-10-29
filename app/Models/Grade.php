<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = [];

    public function getPercentageAttribute(): float
    {
        return (float) $this->marks * 100 / max(1, $this->examination->total_marks);
    }

    public function getLetterGradeAttribute(): string
    {
        return match (true) {
            $this->percentage >= 90 => 'A', $this->percentage >= 80 => 'B',
            $this->percentage >= 70 => 'C', $this->percentage >= 60 => 'D', default => 'F',
        };
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }
}
