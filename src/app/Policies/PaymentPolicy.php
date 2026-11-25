<?php

/** Authorization policy for Payment policy. */

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentPolicy extends BasePolicy
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
            || ($model instanceof Payment && $model->recorded_by === $user->id);
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
}
