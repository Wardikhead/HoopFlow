<?php

/** Authorization policy for Tournament policy. */

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TournamentPolicy extends BasePolicy
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
            || ($model instanceof Tournament && $model->created_by === $user->id);
    }

    /**
     * Archive.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function archive(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Unarchive.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function unarchive(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Generate bracket.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function generateBracket(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Generate schedule.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function generateSchedule(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
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
