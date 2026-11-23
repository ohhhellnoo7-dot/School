<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class StudentTuitionRateException extends ValidationException
{
    public array $details;

    public static function forMonth(string $message, array $details): self
    {
        $exception = self::withMessages(['finance' => $message]);
        $exception->details = $details;

        return $exception;
    }
}
