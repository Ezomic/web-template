<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/**
 * Fortify builds its route list from config at boot, so a value flipped inside
 * a test cannot move a route that is already registered. These assert the
 * array those routes are built from, which is where the gate lives.
 */
$featuresWith = function (bool $workflowMode): array {
    $had = array_key_exists('WORKFLOW_MODE', $_ENV);
    $previous = $_ENV['WORKFLOW_MODE'] ?? null;

    $_ENV['WORKFLOW_MODE'] = $_SERVER['WORKFLOW_MODE'] = $workflowMode ? 'true' : 'false';

    try {
        $config = require config_path('fortify.php');
    } finally {
        if ($had) {
            $_ENV['WORKFLOW_MODE'] = $_SERVER['WORKFLOW_MODE'] = $previous;
        } else {
            unset($_ENV['WORKFLOW_MODE'], $_SERVER['WORKFLOW_MODE']);
        }
    }

    return is_array($config['features'] ?? null) ? $config['features'] : [];
};

/**
 * The reason this matters: id-client provisions a local user with a null
 * password on first SSO sign-in. A reset link would let that user mint one and
 * sign in locally from then on, in a session established without ID, which
 * outlives revoking their grant and ignores back-channel logout.
 */
it('drops password reset and registration in workflow mode', function () use ($featuresWith) {
    expect($featuresWith(true))
        ->not->toContain(Features::resetPasswords())
        ->not->toContain(Features::registration());
});

it('keeps password reset and registration for a standalone app', function () use ($featuresWith) {
    expect($featuresWith(false))
        ->toContain(Features::resetPasswords())
        ->toContain(Features::registration());
});

it('leaves the features workflow mode does not own alone', function () use ($featuresWith) {
    expect($featuresWith(true))->toContain(Features::emailVerification());
});
