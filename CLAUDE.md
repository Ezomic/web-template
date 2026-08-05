# Web Template

The house Laravel + Inertia/Vue starting point. New projects are cloned from this repo (its
`web/` directory is the git root), so it is kept working and dogfooded rather than hand-assembled
each time. Global conventions live in `~/.claude/CLAUDE.md`; this file is the short list of things
that are specific to the template.

## Two modes

The template runs in one of two modes, switched at runtime by `WORKFLOW_MODE` (`config/workflow.php`):

- **Base mode (`WORKFLOW_MODE=false`, default):** a standalone app with the official Laravel
  Inertia+Vue starter auth (local email + password, registration, reset, verification, passkeys,
  2FA, settings). This is the starting point for standalone apps.
- **Workflow mode (`WORKFLOW_MODE=true`):** login is delegated to Thijssensoftware ID (SSO) and the
  portal switcher is shown. Added under ticket WEB-2; base mode is complete first.

## Quality gates are strict (do not lower them)

- **PHPStan (Larastan) level 10.** Analyses `app/`, `bootstrap/app.php`, `database/`, `routes/`.
  `config/` is intentionally excluded (declarative framework scaffolding). Fix the cause, never add
  baselines, `@phpstan-ignore`, or casts-to-silence.
- **100% test coverage.** `composer test:coverage` runs `pest --coverage --min=100` (scoped to
  `app/` via `phpunit.xml`). New code ships with the tests that cover it. Coverage runs in CI
  (xdebug); Herd's local PHP has no coverage driver.
- **Architecture tests** (`tests/Unit/ArchTest.php`) enforce the house conventions mechanically:
  strict types everywhere, no `dd`/`dump`/`ray`, no Livewire/Filament, controllers final and
  extending the base controller, `App\Actions` expose a `handle()` method, models extend Eloquent,
  form requests extend the framework request.
- `composer ci:check` runs the JS gate (ESLint, Prettier, `vue-tsc`), Pint, PHPStan and Pest.
  CI additionally runs `composer test:coverage`.

## Must know before editing

- **Wayfinder route helpers are generated at build.** `resources/js/routes` and
  `resources/js/actions` are gitignored and produced by `npm run build`. If an `@/routes/...` import
  won't resolve, run the build.
- **Strict types are enforced by Pint** (`declare_strict_types` rule), so `vendor/bin/pint` adds the
  declaration; don't hand-write it inconsistently.
- **`$request->user()` is nullable at level 10.** On `auth`-protected routes, narrow it with
  `abort_unless($user instanceof User, 403)` before use (see the settings controllers).
- **Health check:** `GET /health` returns `{ status, app, version, database }` for the `status`
  monitor (200 healthy, 503 when the DB is unreachable). Laravel's built-in `/up` is also present.

## Local dev

- `composer dev` runs the server, Vite and logs together.
- Tests: Pest with `RefreshDatabase` on in-memory SQLite (`php artisan test`).

## Deploying

`.github/workflows/deploy.yml` is `workflow_dispatch` only; production is deployed on purpose,
never on push. It targets `/home/deploy/<repository name>`, so a clone needs no edit as long as
the repo name matches the droplet directory. It needs three repo secrets: `DEPLOY_SSH_HOST`,
`DEPLOY_SSH_USER`, `DEPLOY_SSH_KEY`.

Two things in that script are load-bearing and commented as such: `optimize:clear` runs *before*
`npm run build` (Wayfinder codegen reads the route list, and a stale route cache silently drops
new routes), and there is deliberately **no** php-fpm reload (every site on the droplet shares one
opcache, so reloading for one app evicts everyone else's bytecode). An app with a queue worker or
Reverb adds its restart where the comment says.
