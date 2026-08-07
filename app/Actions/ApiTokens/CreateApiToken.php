<?php

declare(strict_types=1);

namespace App\Actions\ApiTokens;

use App\Models\User;

final class CreateApiToken
{
    /**
     * Returns the plaintext token. It is the only time it exists in readable
     * form: only a hash is stored, so a caller that does not show it to the
     * user here has lost it.
     */
    public function handle(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }
}
