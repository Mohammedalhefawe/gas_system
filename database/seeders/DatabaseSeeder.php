<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Customer;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // أولاً نضيف الأدوار
        $this->call(RoleSeeder::class);

        // مثال لإضافة مستخدم Admin
        $adminRole = Role::where('name', 'admin')->first();

        User::updateOrCreate(
            ['phone_number' => '0956012469'],
            [
                'password' => bcrypt('123456'),
                'role_id' => $adminRole->role_id,
                'is_verified' => true,
            ]
        );
    }
}
