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

    public function archive(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function unarchive(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function generateBracket(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function generateSchedule(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function publish(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
