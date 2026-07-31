<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\RateLimiter;

function requestWithSession(array $session = [], array $query = []): Request
{
    $request = Request::create('/', 'POST', $query);
    $store = new Store('testing', new ArraySessionHandler(120));
    $store->put($session);
    $request->setLaravelSession($store);

    return $request;
}

it('throttles two factor attempts by the login id in session', function () {
    $limiter = RateLimiter::limiter('two-factor');

    expect($limiter(requestWithSession(['login.id' => 42])))
        ->toBeInstanceOf(Limit::class);
});

it('throttles login attempts by username and ip', function () {
    $limiter = RateLimiter::limiter('login');

    expect($limiter(requestWithSession([], ['email' => 'User@Example.com'])))
        ->toBeInstanceOf(Limit::class);
});

it('throttles passkey attempts by credential id', function () {
    $limiter = RateLimiter::limiter('passkeys');

    expect($limiter(requestWithSession([], ['credential' => ['id' => 'cred_1']])))
        ->toBeInstanceOf(Limit::class);
});

it('falls back to the session id when no passkey credential is present', function () {
    $limiter = RateLimiter::limiter('passkeys');

    expect($limiter(requestWithSession()))
        ->toBeInstanceOf(Limit::class);
});
