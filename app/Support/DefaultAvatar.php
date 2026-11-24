<?php

namespace App\Support;

class DefaultAvatar
{
    public static function url(?string $role, ?string $gender): string
    {
        $gender = strtolower(trim($gender ?? ''));
        $portrait = in_array($role, ['parent', 'student', 'teacher'], true)
            && in_array($gender, ['male', 'female'], true)
            ? "$role-$gender"
            : 'neutral';

        return asset("images/default-avatars/$portrait.jpg");
    }
}
