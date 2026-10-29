<?php

namespace App\Policies;

use App\Models\User;
use App\Support\SchoolModules;
use Illuminate\Database\Eloquent\Model;

class SchoolRecordPolicy
{
    public function manage(User $user, Model $record, string $module): bool
    {
        return SchoolModules::get($module)['model'] === $record::class
            && SchoolModules::canWrite($user, $module)
            && (! $record->exists || SchoolModules::query($module, $user)->whereKey($record->getKey())->exists());
    }
}
