<?php

/** Database seeder Organization seeder. */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    /**
     * Run.
     *
     * @return void Nothing.
     */
    public function run(): void
    {
        DB::table('organizations')->updateOrInsert(
            ['slug' => 'default'],
            ['name' => 'Default Organization', 'settings' => null, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
