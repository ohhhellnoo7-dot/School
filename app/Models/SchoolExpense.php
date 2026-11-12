<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SchoolExpense extends Model {
 protected $guarded = [];
 protected function casts(): array { return ['amount'=>'decimal:2', 'spent_on'=>'date']; }
 public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
