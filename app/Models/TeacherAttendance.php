<?php
namespace App\Models;
use App\Models\Concerns\ExcludesSchoolHolidayAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
class TeacherAttendance extends Model {
    use ExcludesSchoolHolidayAttendance;

    protected $guarded = [];
    protected function casts(): array { return ['date' => 'date']; }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }

    public function scopeExcusedOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date)->where('status', 'excused');
    }
}
