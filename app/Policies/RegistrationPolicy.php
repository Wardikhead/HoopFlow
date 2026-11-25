<?php

/** Authorization policy for Registration policy. */

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegistrationPolicy extends BasePolicy
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
}
