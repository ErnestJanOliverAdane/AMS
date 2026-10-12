<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'portal:super-admin {email? : Login email} {--name= : Display name} {--password= : Password (prompted if omitted)}';

    protected $description = 'Create a Super Admin account, or promote and reset an existing one';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email');
        $name = $this->option('name') ?? $this->ask('Name', 'Super Admin');
        $password = $this->option('password') ?? $this->secret('Password (min. 8 characters)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => Role::SuperAdmin, 'is_active' => true],
        );

        $this->info("Super Admin ready: {$email}");

        return self::SUCCESS;
    }
}
