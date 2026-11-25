<?php

/** Authorization policy for Player policy. */

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlayerPolicy extends BasePolicy
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
        if ($user->hasRole('organizer')) {
            return true;
        }

        if ($model instanceof Player) {
            return $model->team !== null && $model->team->coach_user_id === $user->id;
        }

        return false;
    }
}
