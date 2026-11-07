# Laravel Repo handbook

Laravel Repo fetches one configured GitHub owner's repository metadata and raw README content, persists rows and caches the returned array. This handbook follows the released 1.0 service, migrations, configuration and fixture tests.

It is a repository ingestion primitive. The host application owns scheduling, retries, credentials, publication policy, Markdown sanitization, stale-row reconciliation and authorization.

## Install

The package supports Laravel 12 with PHP 8.2+ and Laravel 13 with its framework PHP floor. It requires Guzzle and Illuminate Support/Database/Cache.

```sh
composer config repositories.repo vcs https://github.com/MrNewport/laravel-repo
composer require mrnewport/laravel-repo:^1.0
php artisan vendor:publish --provider="MrNewport\LaravelRepo\Providers\RepoServiceProvider"
php artisan migrate
```

The VCS declaration is useful when the release is not indexed in your registry. Package discovery registers the singleton service, loads migrations and registers `repo:fetch` in console contexts. The publish tag is the generic `config` tag; selecting the provider avoids publishing unrelated packages' configuration.

The migrations create `repositories`, then add `is_private`. Existing rows receive `is_private = true` until refreshed. Back up and review existing production data under your application's normal migration process.

## Configuration

```dotenv
GITHUB_USERNAME=example-owner
GITHUB_TOKEN=
REPO_CACHE_DURATION=3600
REPO_MINIMUM_VISIBILITY=public
```

| Key | Environment variable | Default |
| --- | --- | --- |
| `repo.github_username` | `GITHUB_USERNAME` | `your-github-username` |
| `repo.github_token` | `GITHUB_TOKEN` | Empty |
| `repo.cache_duration` | `REPO_CACHE_DURATION` | 3600 seconds |
| `repo.minimum_visibility` | `REPO_MINIMUM_VISIBILITY` | `public` |

Use `REPO_MINIMUM_VISIBILITY`, including the `REPO_` prefix. The accepted values are exactly `public` and `private`.

Construction rejects invalid owner syntax, unsupported visibility and private mode without a token. Owner syntax permits an initial alphanumeric character followed by alphanumeric characters or hyphens. These are client admission checks, not account existence checks.

The service captures configuration in its constructor. If you change owner, credential or visibility in a long-running process, resolve a newly configured service rather than assuming an existing singleton updates itself.

## Fetch from Laravel

```sh
php artisan repo:fetch
```

```php
use MrNewport\LaravelRepo\Services\RepoService;

$rows = app(RepoService::class)->fetchRepositories();
```

The command prints progress, calls the service and prints success only when it returns. Provider failures propagate; the command does not implement a retry schedule.

`fetchRepositories(): array` returns the accepted API repository arrays with an added `readme` string. It is not an Eloquent collection. The corresponding database records can be queried separately.

## Exact request behavior

| Mode | Repository endpoint | Query |
| --- | --- | --- |
| Public | `GET https://api.github.com/users/{owner}/repos` | `type=owner`, `per_page=100`, incrementing `page` |
| Private | `GET https://api.github.com/user/repos` | `visibility=all`, `affiliation=owner`, `per_page=100`, incrementing `page` |

Private mode means all repositories belonging to the configured owner that the credential can see. It does not mean “only private repositories.” Both modes recheck each row's `owner.login` case-insensitively against the configured owner. Public mode also excludes rows whose `private` flag is true.

The service continues while a page has exactly 100 rows and stops when a page has fewer than 100. A page must decode as a JSON list. There is no configurable package page-count budget; a host supplying an untrusted transport should impose its own bounds.

Request headers include:

- GitHub API version `2022-11-28`.
- `application/vnd.github+json` for repository lists.
- `application/vnd.github.raw+json` for README content.
- The package User-Agent.
- A bearer header only when a nonempty token was configured, including in public mode.

Redirect following is disabled for list and README requests. The default Guzzle client uses a 30-second request timeout and a 10-second connection timeout.

## README retrieval

```php
$markdown = $service->fetchReadme('example-owner/example-repository');
```

`fetchReadme()` accepts a validated `owner/repository` name and requests the fixed GitHub README endpoint. It returns raw response bytes as a string.

A README HTTP 404 becomes the empty string. The repository still persists and remains in the result. Other client errors, including 403 and rate-limit responses, propagate. Missing repository lists, authentication failures and server errors are not silently converted into an empty catalog.

The package stores raw Markdown. Escape it as text, or render it through a Markdown implementation with an explicit HTML/link sanitization policy. A README must never become a trusted Blade template or executable PHP.

## Persisted model

`MrNewport\LaravelRepo\Models\Repository` uses `repositories`. `updateOrCreate()` identifies rows by `full_name` and writes:

| Column | Purpose |
| --- | --- |
| `name` | Repository name |
| `full_name` | Unique owner/name identity |
| `html_url` | API-supplied repository link |
| `description` | Nullable description |
| `readme` | Raw README string |
| `is_private` | Visibility from the current accepted API row |
| Timestamps | Eloquent create/update times |

Reads for public presentation should at least filter visibility:

```php
use MrNewport\LaravelRepo\Models\Repository;

$public = Repository::query()
    ->where('is_private', false)
    ->orderBy('name')
    ->get();
```

For a curated portfolio, also use a host-owned allowlist and review the exported fields and destinations. Visibility alone does not establish that every public repository belongs in a publication.

The service does not purge a row when a repository disappears, is renamed or becomes invisible to the current credential. A previously public row can therefore remain after a later public-only fetch stops returning it. A public publisher must reconcile against a newly verified complete public inventory, not merely publish every historical database row marked public.

## Cache semantics

A SHA-256 cache key incorporates the configured owner, visibility and token identity. The raw token does not appear in that key. This prevents a public service instance from returning another credential's cached private array through the same package key.

The cache TTL is measured in seconds. On a hit, the service returns the cached array without making requests or repeating database writes. On a miss, the fetch callback requests and persists accepted repositories, then stores the completed result.

The package does not wrap the entire sync in a database transaction. If a later README or page fails, earlier rows may already have been updated while the cache remains unpopulated. A failed call is not evidence of a complete rollback.

Cache storage policy remains the host's responsibility. A shared cache containing private README data must have appropriate access controls and retention. Rotating credentials changes the package key; deleting old credential-specific cache entries requires the application's own cache lifecycle process.

## Inject a fully local transport

`RepoService` accepts any `GuzzleHttp\ClientInterface`. This is useful in integration tests and offline examples.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrNewport\LaravelRepo\Services\RepoService;

$repo = [
    'name' => 'supplies',
    'full_name' => 'Northwind/supplies',
    'owner' => ['login' => 'Northwind'],
    'private' => false,
    'html_url' => 'https://github.com/Northwind/supplies',
];

config([
    'repo.github_username' => 'Northwind',
    'repo.github_token' => '',
    'repo.minimum_visibility' => 'public',
]);

$mock = new MockHandler([
    new Response(200, [], json_encode([$repo], JSON_THROW_ON_ERROR)),
    new Response(200, [], '# Fictional supplies'),
]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
$service = new RepoService($client);
$rows = $service->fetchRepositories();
```

This example still uses the configured Eloquent database and cache. Run it in an explicitly disposable test environment. An exhausted `MockHandler` fails instead of making an external request. Add history middleware to inspect request method, endpoint and page number without logging bearer headers or private README bodies.

## Application recipes

**Offline portfolio refresh:** create an isolated local database/cache, set public mode, run the service and independently reconcile the complete accepted inventory with a repository allowlist. Export reviewed public fields to a version-controlled catalog. The deployed publication can serve that catalog without a GitHub token or writable database.

**Private team catalog:** authorize the viewer before loading records, scope accepted repositories to a host-owned workspace policy and keep the private cache/database inaccessible to public views. The package model has no workspace column or built-in tenant policy.

**Scheduled refresh:** run a host job with a stored owner/visibility policy, bounded retries and provider-aware backoff. Prevent overlapping refreshes when partial updates would be confusing. The package registers no scheduler or queue worker.

## Failures and recovery

- Invalid constructor configuration throws `InvalidArgumentException` before fetching.
- Invalid README identity throws `InvalidArgumentException` before its request.
- Malformed/non-list repository JSON fails rather than claiming a valid empty catalog.
- README 404 is the one explicitly accepted missing-content case.
- Provider authorization, rate-limit and server failures propagate through Guzzle.
- Earlier writes can remain after a partial failure; rerun after reviewing the provider state and publication boundary.
- Deletions and visibility reconciliation require an explicit host process.

## Tests

```sh
composer install
composer test
```

`tests/Feature/RepositoriesTest.php` exercises real service pagination, a missing README, credential-isolated cache behavior, persisted visibility and provider-error propagation through local Guzzle fixtures. Migration tests cover the supported database setup. CI configuration records the current Laravel/PHP matrix. These are transport/consumer contracts, not a live GitHub production acceptance claim.

See the [feature inventory](FEATURES.md), [service](../src/Services/RepoService.php) and [configuration](../config/repo.php).
