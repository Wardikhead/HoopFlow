<?php

/** Authorization policy for Bracket policy. */

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BracketPolicy extends BasePolicy
{
    /**
     * Generate.
     *
     * @param  User  $user  The user.
     * @param  ?Model  $model  The model.
     * @return bool The result.
     */
    public function generate(User $user, ?Model $model = null): bool
    {
        return $user->hasRole('organizer');
    }
}
