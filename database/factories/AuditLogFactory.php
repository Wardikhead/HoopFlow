<?php

/** Database support for Audit log factory. */

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
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        $id = DB::getDriverName() === 'pgsql'
            ? DB::selectOne("select nextval('audit_logs_id_seq') as v")->v
            : ((AuditLog::max('id') ?? 0) + 1);

        return [
            'id' => $id,
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
