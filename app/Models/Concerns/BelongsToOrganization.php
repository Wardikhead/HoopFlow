<?php

/** Eloquent model for Belongs to organization. */

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  mixed  $organizationId
     * @return Builder<Model>
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }
}
