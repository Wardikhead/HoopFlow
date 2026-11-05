<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DefaultOrganizerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'organizer@hoopflow.test'],
            [
                'first_name' => 'Default',
                'last_name' => 'Organizer',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $roleId = DB::table('roles')->where('key', 'organizer')->value('id');
        $orgId = DB::table('organizations')->where('slug', 'default')->value('id');

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $roleId, 'scope' => null],
            ['organization_id' => $orgId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
