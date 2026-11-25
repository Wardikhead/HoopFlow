<?php

/** Authorization policy for Match model policy. */

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MatchModelPolicy extends BasePolicy
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
        return $user->hasRole('organizer');
    }

    /**
     * Correct score.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function correctScore(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Enter stats.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function enterStats(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
