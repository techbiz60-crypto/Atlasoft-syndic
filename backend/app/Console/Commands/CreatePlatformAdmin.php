<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePlatformAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:create-admin
        {email : Login email for the platform admin account}
        {name : Display name}
        {--password= : Password — a random one is generated and printed if omitted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée un compte super-admin plateforme (équipe Atlasoft Syndic), séparé de toute résidence, pour gérer les abonnements clients.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("Un compte existe déjà avec l'email {$email}.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? Str::password(16);

        User::forceCreate([
            'residence_id' => null,
            'role' => Role::Admin,
            'is_platform_admin' => true,
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Compte super-admin créé : {$email}");

        if (! $this->option('password')) {
            $this->warn("Mot de passe généré : {$password}");
        }

        return self::SUCCESS;
    }
}
