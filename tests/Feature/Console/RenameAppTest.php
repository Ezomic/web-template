<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

/**
 * Everything here runs against a throwaway fixture directory. The command's
 * whole job is rewriting composer.json and .env, so pointing it at the real
 * repo would rewrite the template itself.
 */
beforeEach(function (): void {
    $this->fixture = sys_get_temp_dir().'/web-template-rename-'.bin2hex(random_bytes(6));

    File::makeDirectory($this->fixture);

    File::put($this->fixture.'/composer.json', <<<'JSON'
        {
            "name": "thijssensoftware/web-template",
            "type": "project",
            "description": "House Laravel + Inertia/Vue web application template."
        }
        JSON);

    File::put($this->fixture.'/.env.example', <<<'ENV'
        APP_NAME="Web Template"
        APP_ENV=local
        APP_URL=http://web-template.test
        ENV);
});

afterEach(function (): void {
    File::deleteDirectory($this->fixture);
});

function renameApp(string $fixture, string $slug = 'my-app', string $name = 'My App'): PendingCommand
{
    return test()->artisan('app:rename', ['slug' => $slug, 'name' => $name, '--path' => $fixture]);
}

it('rewrites the composer package name and description', function (): void {
    renameApp($this->fixture)->assertSuccessful();

    $composer = File::get($this->fixture.'/composer.json');

    expect($composer)->toContain('"name": "thijssensoftware/my-app"')
        ->and($composer)->toContain('"description": "My App web application."')
        ->and($composer)->toContain('"type": "project"');
});

it('rewrites APP_NAME and APP_URL in the env example', function (): void {
    renameApp($this->fixture)->assertSuccessful();

    $env = File::get($this->fixture.'/.env.example');

    expect($env)->toContain('APP_NAME="My App"')
        ->and($env)->toContain('APP_URL=http://my-app.test')
        ->and($env)->toContain('APP_ENV=local');
});

it('rewrites .env too when it exists', function (): void {
    File::put($this->fixture.'/.env', "APP_NAME=\"Web Template\"\nAPP_URL=http://web-template.test");

    renameApp($this->fixture)->assertSuccessful();

    expect(File::get($this->fixture.'/.env'))->toContain('APP_NAME="My App"');
});

it('skips .env when the clone has not copied one yet', function (): void {
    renameApp($this->fixture)->assertSuccessful();

    expect(File::exists($this->fixture.'/.env'))->toBeFalse();
});

it('is safe to run twice', function (): void {
    renameApp($this->fixture)->assertSuccessful();
    $afterFirst = File::get($this->fixture.'/composer.json');

    renameApp($this->fixture)
        ->expectsOutputToContain('Already named "My App". Nothing to change.')
        ->assertSuccessful();

    expect(File::get($this->fixture.'/composer.json'))->toBe($afterFirst);
});

it('refuses to rename an app that is no longer the template', function (): void {
    File::put($this->fixture.'/composer.json', '{"name": "thijssensoftware/flare", "description": "x"}');

    renameApp($this->fixture)->assertFailed();

    expect(File::get($this->fixture.'/composer.json'))->toContain('thijssensoftware/flare');
});

it('refuses when the env file belongs to another app', function (): void {
    File::put($this->fixture.'/.env.example', "APP_NAME=\"Flare\"\nAPP_URL=http://flare.test");

    renameApp($this->fixture)->assertFailed();

    expect(File::get($this->fixture.'/.env.example'))->toContain('APP_NAME="Flare"');
});

it('rejects a slug that is not url safe', function (string $slug): void {
    renameApp($this->fixture, $slug)->assertFailed();

    expect(File::get($this->fixture.'/composer.json'))->toContain('thijssensoftware/web-template');
})->with(['My App', 'my_app', 'My-App', '-my-app', 'my--app', 'my-app-', '']);

it('fails when there is no composer.json to rewrite', function (): void {
    File::delete($this->fixture.'/composer.json');

    renameApp($this->fixture)->assertFailed();
});

it('fails when composer.json has no name key', function (): void {
    File::put($this->fixture.'/composer.json', '{"type": "project"}');

    renameApp($this->fixture)->assertFailed();
});

it('fails when the env file has no APP_NAME', function (): void {
    File::put($this->fixture.'/.env.example', 'APP_ENV=local');

    renameApp($this->fixture)->assertFailed();
});

it('leaves the env file alone when it already matches', function (): void {
    File::put($this->fixture.'/.env.example', "APP_NAME=\"My App\"\nAPP_URL=http://my-app.test");

    renameApp($this->fixture)->assertSuccessful();

    expect(File::get($this->fixture.'/.env.example'))->toContain('APP_NAME="My App"');
});

it('defaults to this application when no path is given', function (): void {
    // No --path, so this targets the real repo. Renaming the template to its
    // own identity is a no-op, which is the one safe way to reach the
    // base_path() branch. Snapshotted and restored regardless, so a drifted
    // local .env cannot be quietly rewritten by running the suite.
    $tracked = ['composer.json', '.env.example', '.env'];
    $before = [];

    foreach ($tracked as $file) {
        if (File::exists(base_path($file))) {
            $before[$file] = File::get(base_path($file));
        }
    }

    try {
        $this->artisan('app:rename', ['slug' => 'web-template', 'name' => 'Web Template'])
            ->expectsOutputToContain('Already named "Web Template". Nothing to change.')
            ->assertSuccessful();

        foreach ($before as $file => $contents) {
            expect(File::get(base_path($file)))->toBe($contents);
        }
    } finally {
        foreach ($before as $file => $contents) {
            File::put(base_path($file), $contents);
        }
    }
});
