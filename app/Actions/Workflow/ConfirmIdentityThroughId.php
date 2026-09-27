<?php

declare(strict_types=1);

namespace App\Actions\Workflow;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

/**
 * Password confirmation for users who have no password. In workflow mode ID owns the
 * identity and id-client provisions users without one, so Fortify's confirm form could
 * never pass and every page behind RequirePassword, the API tokens among them, was out
 * of reach. A round trip through ID stands in for the password: the confirmation counts
 * once ID has signed the user in again after it was asked for. That needs ID's own
 * session, which a stolen app session or remember cookie does not carry. An earlier
 * sign-in never counts, however recent. See WEB-28.
 */
final class ConfirmIdentityThroughId
{
    private const REQUESTED_AT = 'workflow.identity_confirmation_requested_at';

    public function handle(Request $request): Response
    {
        $session = $request->session();
        $requestedAt = $session->pull(self::REQUESTED_AT);
        $signedInAt = $session->get(EnsureSsoSessionIsActive::AUTHENTICATED_AT);

        if (is_int($requestedAt) && is_int($signedInAt) && $signedInAt >= $requestedAt) {
            $session->passwordConfirmed();

            return redirect()->intended(Fortify::redirects('password-confirmation'));
        }

        $session->put(self::REQUESTED_AT, Date::now()->getTimestamp());

        // Inertia::location, not a redirect: the first visit is usually an Inertia request,
        // and a plain redirect to ID's origin would be followed by XHR and blocked by CORS.
        return Inertia::location(route('sso.redirect'));
    }
}
