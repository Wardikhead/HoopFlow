<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TournamentPolicy extends BasePolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer')
            || ($model instanceof Tournament && $model->created_by === $user->id);
    }
}
