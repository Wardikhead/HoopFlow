<?php

/** Database seeder Database seeder. */

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run.
     *
     * @return void Nothing.
     */
    public function run(): void
    {
        $this->call([OrganizationSeeder::class, RoleSeeder::class, DefaultOrganizerSeeder::class]);

        if (filter_var(getenv('SEED_PROTECT') ?: false, FILTER_VALIDATE_BOOLEAN)) {
            $this->call(PrototypeSeeder::class);
        }
    }
}
