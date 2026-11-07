<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentPolicy extends BasePolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer')
            || ($model instanceof Payment && $model->recorded_by === $user->id);
    }
}
