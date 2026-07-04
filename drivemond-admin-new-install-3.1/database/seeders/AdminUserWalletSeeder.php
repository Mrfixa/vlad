<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\UserManagement\Entities\UserAccount;

class AdminUserWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // The admin email is env-configurable (see AdminUserSeeder), and in production
        // AdminUserSeeder may have refused to seed at all — so look the account up by
        // user_type and skip gracefully instead of crashing on a missing row.
        $admin = User::query()->where('user_type', 'super-admin')->orderBy('created_at')->first();
        if (!$admin || UserAccount::query()->where('user_id', $admin->id)->exists()) {
            return;
        }

        UserAccount::query()->create([
            'user_id' => $admin->id
        ]);
    }
}
