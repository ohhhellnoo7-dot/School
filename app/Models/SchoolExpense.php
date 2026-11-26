<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SchoolExpense extends Model {
 protected $guarded = [];
 protected function casts(): array { return ['amount'=>'decimal:2', 'spent_on'=>'date', 'recover_from_month'=>'date']; }
 public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
 public function employee() { return $this->belongsTo(User::class, 'employee_id')->withoutGlobalScope('archived'); }
}
