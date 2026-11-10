<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CurriculumProfile extends Model {
    protected $guarded = [];
    public function requirements() { return $this->hasMany(CurriculumRequirement::class)->orderBy('position'); }
    public function getLabelAttribute(): string { return 'Grade '.$this->grade.($this->stream ? ' - '.(ClassGroup::STREAMS[$this->stream] ?? $this->stream) : ''); }
}
