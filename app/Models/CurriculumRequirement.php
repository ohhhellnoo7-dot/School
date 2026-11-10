<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CurriculumRequirement extends Model {
    protected $guarded = [];
    public function profile() { return $this->belongsTo(CurriculumProfile::class, 'curriculum_profile_id'); }
    public function subjects() { return $this->hasMany(Subject::class); }
}
