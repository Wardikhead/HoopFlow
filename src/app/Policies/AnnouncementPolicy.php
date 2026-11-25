<?php

/** Authorization policy for Announcement policy. */

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnnouncementPolicy extends BasePolicy
{
    /**
     * Update.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer')
            || ($model instanceof Announcement && $model->created_by === $user->id);
    }

    /**
     * Publish.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function publish(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
