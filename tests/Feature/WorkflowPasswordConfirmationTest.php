<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\travel;

/**
 * id-client provisions workflow-mode users with a null password, so the confirmation in
 * front of the token pages is a round trip through ID instead. Before WEB-28 nothing
 * handled it and no SSO user could ever create an API token.
 */
function ssoUser(int $signedInSecondsAgo = 60): User
{
    config(['workflow.enabled' => true]);

    $user = User::create(['name' => 'SSO User', 'email' => 'sso@example.test']);
    $user->forceFill(['email_verified_at' => now()])->save();

    actingAs($user);
    session()->put(EnsureSsoSessionIsActive::AUTHENTICATED_AT, now()->getTimestamp() - $signedInSecondsAgo);

    return $user;
}

function idSignsInAgain(): void
{
    $idUser = (new SocialiteUser)->map(['id' => 'idp-1', 'email' => 'sso@example.test', 'name' => 'SSO User']);
    $idUser->setToken('id-access-token');

    mock(Socialite::class)->shouldReceive('driver->user')->andReturn($idUser);

    get(route('sso.callback'))->assertRedirect();
}

it('asks a user without a password to confirm before the token pages', function () {
    ssoUser();

    get(route('api-tokens.index'))->assertRedirect(route('password.confirm'));
});

it('sends the user through ID even right after signing in', function () {
    ssoUser(signedInSecondsAgo: 5);

    get(route('password.confirm'))
        ->assertRedirect(route('sso.redirect'))
        ->assertSessionMissing('auth.password_confirmed_at');
});

it('answers an Inertia visit with a full-page location, since ID is another origin', function () {
    ssoUser();
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    get(route('password.confirm'), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('sso.redirect'));
});

it('reaches the token page once ID has signed the user in again', function () {
    ssoUser();

    get(route('api-tokens.index'))->assertRedirect(route('password.confirm'));
    get(route('password.confirm'))->assertRedirect(route('sso.redirect'));

    travel(5)->seconds();
    idSignsInAgain();

    get(route('api-tokens.index'))->assertRedirect(route('password.confirm'));
    get(route('password.confirm'))
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('auth.password_confirmed_at');
    get(route('api-tokens.index'))->assertOk();
});

it('does not count a sign-in from before the confirmation was asked for', function () {
    ssoUser();

    get(route('password.confirm'))->assertRedirect(route('sso.redirect'));
    travel(5)->seconds();

    get(route('password.confirm'))
        ->assertRedirect(route('sso.redirect'))
        ->assertSessionMissing('auth.password_confirmed_at');
});
