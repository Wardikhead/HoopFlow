<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BracketPolicy extends BasePolicy
{
    public function generate(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
