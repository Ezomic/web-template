<?php

declare(strict_types=1);

namespace App\Actions\Workflow;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

/**
 * Password confirmation for users who have no password. In workflow mode ID owns the
 * identity and id-client provisions users without one, so Fortify's confirm form could
 * never pass and every page behind RequirePassword, the API tokens among them, was out
 * of reach. A sign-in through ID stands in for the password: a recent one counts as the
 * confirmation, an older one sends the user back through ID. A stolen session or
 * remember cookie does not carry ID's own session, so that round trip is a real check.
 * See WEB-28.
 */
final class ConfirmIdentityThroughId
{
    public function handle(Request $request): Response
    {
        if ($this->signedInRecently($request)) {
            $request->session()->passwordConfirmed();

            return redirect()->intended(Fortify::redirects('password-confirmation'));
        }

        // Inertia::location, not a redirect: the first visit is usually an Inertia request,
        // and a plain redirect to ID's origin would be followed by XHR and blocked by CORS.
        return Inertia::location(route('sso.redirect'));
    }

    private function signedInRecently(Request $request): bool
    {
        $signedInAt = $request->session()->get(EnsureSsoSessionIsActive::AUTHENTICATED_AT);

        return is_int($signedInAt)
            && Date::now()->getTimestamp() - $signedInAt < Config::integer('auth.password_timeout', 10800);
    }
}
