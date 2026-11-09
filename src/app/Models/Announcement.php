<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int, int>|null $target_user_ids
 * @property Carbon|null $published_at
 */
#[Fillable(['organization_id', 'tournament_id', 'title', 'body', 'category', 'audience', 'target_user_ids', 'pinned', 'status', 'published_at', 'created_by'])]
class Announcement extends Model
{
    /** @use HasFactory<Factory> */
    use BelongsToOrganization, HasFactory;

    /** @var array<string, string> */
    public const CATEGORIES = [
        'schedule' => 'Schedule',
        'venue' => 'Venue',
        'eligibility' => 'Eligibility',
        'general' => 'General',
    ];

    /** @var array<string, string> */
    public const AUDIENCES = [
        'all' => 'Everyone',
        'coaches' => 'Coaches',
        'organizers' => 'Organizers',
        'fans' => 'Fans',
        'specific' => 'Specific users',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'target_user_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::CATEGORIES[$category ?? 'general'] ?? ucfirst((string) $category);
    }

    public static function audienceLabel(?string $audience): string
    {
        return self::AUDIENCES[$audience ?? 'all'] ?? ucfirst((string) $audience);
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
