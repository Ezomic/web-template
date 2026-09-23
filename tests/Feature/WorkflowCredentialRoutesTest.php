<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * The reason this matters: id-client provisions a local user with a null
 * password on first SSO sign-in. A reset link would let that user mint one and
 * sign in locally from then on, in a session established without ID, which
 * outlives revoking their grant and ignores back-channel logout. See WEB-23.
 */
it('blocks the local credential routes in workflow mode', function (string $method, string $route) {
    config(['workflow.enabled' => true]);

    $response = $method === 'get' ? get(route($route, ['token' => 'x'])) : post(route($route));

    $response->assertNotFound();
})->with([
    ['get', 'password.request'],
    ['post', 'password.email'],
    ['get', 'password.reset'],
    ['post', 'password.update'],
    ['post', 'login.store'],
]);

it('leaves the local credential routes alone for a standalone app', function () {
    config(['workflow.enabled' => false]);

    get(route('password.request'))->assertOk();
});

/**
 * The login page itself has to stay reachable in workflow mode: it is what
 * renders the "Sign in with Thijssensoftware" button.
 */
it('keeps the login page reachable in workflow mode', function () {
    config(['workflow.enabled' => true]);

    get(route('login'))->assertOk();
});
