<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeacherAttendance extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['date' => 'date']; }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
}
