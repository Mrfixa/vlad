<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * In production (APP_ENV=production) this seeder requires explicit env vars
     * ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD to be set. If either is missing it
     * aborts with a clear warning rather than seeding a hard-coded credential.
     * In local/testing environments the defaults (admin@admin.com / 12345678) are
     * used so `db:seed` continues to work out of the box.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $email    = env('ADMIN_SEED_EMAIL');
            $password = env('ADMIN_SEED_PASSWORD');

            if (empty($email) || empty($password)) {
                $this->command?->warn(
                    'AdminUserSeeder: skipping super-admin seed in production. '
                    .'Set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD env vars to create the account.'
                );

                return;
            }
        } else {
            // Local / testing defaults — never used in production.
            $email    = 'admin@admin.com';
            $password = '12345678';
        }

        // Idempotent: skip if the account already exists.
        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'id'         => Uuid::uuid4(),
            'first_name' => 'Super',
            'last_name'  => 'Admin',
            'email'      => $email,
            'password'   => bcrypt($password),
            'user_type'  => 'super-admin',
            'is_active'  => true,
        ]);
    }
}
