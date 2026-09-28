<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\mock;
use function Pest\Laravel\travel;

/**
 * Deleting the account asked for the current password, and a user id-client provisions
 * has none, so nobody could delete their account in workflow mode. There it goes through
 * RequirePassword instead, which is a fresh trip through ID (WEB-28). Base mode keeps the
 * password in the dialog. WEB-31.
 */
function signedInWithoutAPassword(): User
{
    config(['workflow.enabled' => true]);

    $user = User::create(['name' => 'SSO User', 'email' => 'sso@example.test']);

    actingAs($user);
    session()->put(EnsureSsoSessionIsActive::AUTHENTICATED_AT, now()->getTimestamp() - 60);

    return $user;
}

function idSignsTheUserInAgain(): void
{
    $idUser = (new SocialiteUser)->map(['id' => 'idp-1', 'email' => 'sso@example.test', 'name' => 'SSO User']);
    $idUser->setToken('id-access-token');

    mock(Socialite::class)->shouldReceive('driver->user')->andReturn($idUser);

    get(route('sso.callback'))->assertRedirect();
}

it('sends a user without a password through ID before deleting the account', function () {
    $user = signedInWithoutAPassword();

    $this->from(route('profile.edit'))->delete(route('profile.destroy'))->assertRedirect(route('password.confirm'));
    get(route('password.confirm'))->assertRedirect(route('sso.redirect'));

    expect($user->fresh())->not->toBeNull();
});

it('deletes the account once ID has signed the user in again', function () {
    $user = signedInWithoutAPassword();

    $this->from(route('profile.edit'))->delete(route('profile.destroy'))->assertRedirect(route('password.confirm'));
    get(route('password.confirm'))->assertRedirect(route('sso.redirect'));

    travel(5)->seconds();
    idSignsTheUserInAgain();

    $this->from(route('profile.edit'))->delete(route('profile.destroy'))->assertRedirect(route('password.confirm'));
    get(route('password.confirm'))->assertRedirect(route('profile.edit'));

    $this->from(route('profile.edit'))->delete(route('profile.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    assertGuest();
    expect($user->fresh())->toBeNull();
});

it('asks a confirmed workflow user for no password', function () {
    $user = signedInWithoutAPassword();
    session()->put('auth.password_confirmed_at', now()->getTimestamp());

    $this->delete(route('profile.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull();
});

it('keeps the current password as the confirmation in base mode, with no trip anywhere', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->from(route('profile.edit'))->delete(route('profile.destroy'))
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    $this->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull();
});
