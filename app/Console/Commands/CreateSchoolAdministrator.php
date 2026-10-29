<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSchoolAdministrator extends Command
{
    protected $signature = 'school:admin {email} {--name=School Administrator}';

    protected $description = 'Create a school administrator using an interactively entered password';

    public function handle(): int
    {
        $password = $this->secret('Password (10+ characters, mixed case, and a number)');
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name'), 'password' => $password, 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:users', 'name' => 'required|string|max:255', 'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User($data);
        $user->role = 'administrator';
        $user->save();
        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
