<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return Student::visibleTo($user)->whereKey($student->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === 'administrator';
    }

    public function update(User $user, Student $student): bool
    {
        return $user->role === 'administrator';
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->role === 'administrator';
    }
}
