<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EveningStudyPeriod extends Model {
    protected $guarded = [];
    public static function options(?string $program): array {
        return static::where('program', $program)->orderBy('starts_at')->get()->mapWithKeys(fn ($p) => [$p->id => [
            'label' => $p->label, 'starts_at' => substr($p->starts_at, 0, 5), 'ends_at' => substr($p->ends_at, 0, 5),
        ]])->all();
    }
}
