<?php

/** Authorization policy for Team policy. */

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TeamPolicy extends BasePolicy
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
            || ($model instanceof Team && $model->coach_user_id === $user->id);
    }

    /**
     * Approve.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function approve(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
