<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {name} {email}';

    protected $description = 'Crée le premier administrateur (mot de passe saisi de façon interactive)';

    public function handle(): int
    {
        $password = password('Mot de passe (12 caractères minimum)', required: true);

        $validator = Validator::make(
            ['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());
        $user->forceFill(['is_active' => true, 'email_verified_at' => now()])->save();
        $user->assignRole(Role::Admin);

        $this->info("Administrateur {$user->email} créé.");

        return self::SUCCESS;
    }
}
