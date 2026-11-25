<?php

/** Authorization policy for Base policy. */

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BasePolicy
{
    /**
     * View any.
     *
     * @param  User  $user  The user.
     * @return bool The result.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function view(User $user, Model $model): bool
    {
        return true;
    }

    /**
     * Create.
     *
     * @param  User  $user  The user.
     * @return bool The result.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Update.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer') || $this->isOwner($user, $model);
    }

    /**
     * Delete.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Restore.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function restore(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Force delete.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Is owner.
     *
     * @param  User  $user  The user.
     * @param  Model  $model  The model.
     * @return bool The result.
     */
    protected function isOwner(User $user, Model $model): bool
    {
        foreach (['coach_user_id', 'created_by', 'actor_user_id'] as $attribute) {
            if ($model->getAttribute($attribute) === $user->id) {
                return true;
            }
        }

        return false;
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

    /**
     * Reject.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function reject(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Verify.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function verify(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }

    /**
     * Refund.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function refund(User $user, ?Model $model = null): bool
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

    /**
     * Reset demo.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function resetDemo(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
