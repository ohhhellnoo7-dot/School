<?php
namespace App\Models;
use App\Models\Concerns\ExcludesSchoolHolidayAttendance;
use Illuminate\Database\Eloquent\Model;
class TeacherAttendance extends Model {
    use ExcludesSchoolHolidayAttendance;

    protected $guarded = [];
    protected function casts(): array { return ['date' => 'date']; }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
}
