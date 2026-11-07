<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegistrationPolicy extends BasePolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer');
    }
}
