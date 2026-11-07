<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => DB::getDriverName() === 'pgsql'
                ? DB::selectOne("select nextval('audit_logs_id_seq') as v")->v
                : null,
            'organization_id' => null,
            'actor_user_id' => User::factory(),
            'action' => fake()->word(),
            'entity_type' => fake()->word(),
            'entity_id' => null,
            'before_json' => null,
            'after_json' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
