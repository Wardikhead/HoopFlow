<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $before_json
 * @property array<string, mixed>|null $after_json
 */
#[Fillable(['id', 'organization_id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'before_json', 'after_json', 'ip', 'user_agent', 'created_at'])]
class AuditLog extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /** @use HasFactory<Factory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->actor();
    }
}
