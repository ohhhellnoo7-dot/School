<?php

namespace App\Support;

class DefaultAvatar
{
    public static function url(?string $role, ?string $gender): string
    {
        $gender = strtolower(trim($gender ?? ''));
        $portrait = 'neutral';
        if (in_array($role, ['parent', 'student', 'teacher'], true)) {
            $variant = $gender === 'female' ? 'female' : 'male';
            $portrait = "$role-$variant";
        }

        return asset("images/default-avatars/$portrait.jpg");
    }
}
