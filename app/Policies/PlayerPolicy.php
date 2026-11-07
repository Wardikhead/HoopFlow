<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlayerPolicy extends BasePolicy
{
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
