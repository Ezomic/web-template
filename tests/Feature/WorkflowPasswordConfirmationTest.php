<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * id-client provisions workflow-mode users with a null password, so the confirmation in
 * front of the token pages is satisfied by a sign-in through ID instead. Before WEB-28
 * nothing handled it and no SSO user could ever create an API token.
 */
function ssoUser(?int $signedInSecondsAgo = null): User
{
    config(['workflow.enabled' => true]);

    $user = User::create(['name' => 'SSO User', 'email' => 'sso@example.test']);
    $user->forceFill(['email_verified_at' => now()])->save();

    actingAs($user);

    if ($signedInSecondsAgo !== null) {
        session()->put(EnsureSsoSessionIsActive::AUTHENTICATED_AT, now()->getTimestamp() - $signedInSecondsAgo);
    }

    return $user;
}

it('asks a user without a password to confirm before the token pages', function () {
    ssoUser();

    get(route('api-tokens.index'))->assertRedirect(route('password.confirm'));
});

it('sends a user with no sign-in stamp back through ID', function () {
    ssoUser();

    get(route('password.confirm'))->assertRedirect(route('sso.redirect'));
});

it('answers an Inertia visit with a full-page location, since ID is another origin', function () {
    ssoUser();
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    get(route('password.confirm'), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('sso.redirect'));
});

it('accepts a recent sign-in through ID as the confirmation', function () {
    ssoUser(signedInSecondsAgo: 60);
    session()->put('url.intended', route('api-tokens.index'));

    get(route('password.confirm'))
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('auth.password_confirmed_at');

    get(route('api-tokens.index'))->assertOk();
});

it('sends a sign-in older than the password timeout back through ID', function () {
    ssoUser(signedInSecondsAgo: config()->integer('auth.password_timeout') + 1);

    get(route('password.confirm'))
        ->assertRedirect(route('sso.redirect'))
        ->assertSessionMissing('auth.password_confirmed_at');
});
