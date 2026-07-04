<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // In production the well-known demo credentials (admin@admin.com / 12345678)
        // must never be seeded. Require explicit credentials via env instead, and
        // abort loudly if they are missing so a `migrate --seed` can't silently
        // create a guessable super-admin.
        $isProduction = app()->environment('production');
        $email = $isProduction ? env('ADMIN_SEED_EMAIL') : env('ADMIN_SEED_EMAIL', 'admin@admin.com');
        $password = $isProduction ? env('ADMIN_SEED_PASSWORD') : env('ADMIN_SEED_PASSWORD', '12345678');

        if ($isProduction && (empty($email) || empty($password))) {
            $this->command?->error(
                'AdminUserSeeder: refusing to seed the default super-admin in production. '
                . 'Set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD in .env to seed a super-admin.'
            );
            return;
        }

        if ($isProduction && strlen((string) $password) < 12) {
            $this->command?->error('AdminUserSeeder: ADMIN_SEED_PASSWORD must be at least 12 characters in production.');
            return;
        }

        // Idempotent: only create the super-admin if it does not already exist,
        // so re-seeding never duplicates the row or mutates its id (FK-safe).
        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'id' => Uuid::uuid4(),
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => bcrypt($password),
            'user_type' => 'super-admin',
            'is_active' => true
        ]);
    }
}
