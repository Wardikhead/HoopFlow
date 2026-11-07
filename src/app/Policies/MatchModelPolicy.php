<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MatchModelPolicy extends BasePolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    public function correctScore(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function enterStats(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
