<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('organizer');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer') || $this->isOwner($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    protected function isOwner(User $user, Model $model): bool
    {
        foreach (['coach_user_id', 'created_by', 'actor_user_id'] as $attribute) {
            if ($model->getAttribute($attribute) === $user->id) {
                return true;
            }
        }

        return false;
    }

    public function approve(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function reject(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function verify(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    public function refund(User $user, ?Model $model = null): bool
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

    public function resetDemo(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
