# Repo feature inventory

| Implemented capability | Entry point | Host responsibility |
| --- | --- | --- |
| Owner-specific public repository fetch | `RepoService::fetchRepositories()` | Choose and validate publication owner |
| Credential-scoped private/all visibility fetch | `repo.minimum_visibility = private` | Credential issuance, storage and viewer authorization |
| Pagination in 100-row pages | `RepoService` list loop | Job bounds, rate-limit backoff and overlap policy |
| Foreign-owner and public/private filtering | Service row admission | Curated allowlist and stale-row reconciliation |
| Raw README retrieval | `fetchReadme(owner/name)` | Safe Markdown/HTML rendering |
| Missing README acceptance | README 404 → empty string | Decide display fallback |
| Failure propagation | Guzzle exceptions and strict JSON decode | Retries, monitoring and recovery |
| Eloquent metadata persistence | `Repository::updateOrCreate()` | Ownership, transactions spanning a whole refresh, deletion policy |
| Visibility migration | `is_private` defaults to true | Refresh/review historical rows before public use |
| Owner/visibility/token-isolated cache | Hashed `Cache::remember()` key | Private cache access, TTL and credential-rotation cleanup |
| Injected Guzzle transport | Optional `ClientInterface` constructor argument | Fixture isolation and response bounds |
| Disabled redirects | Every service request | Provider origin and network policy |
| Console command | `repo:fetch` | Scheduler, job retry and access policy |
| Automatic configuration/migration discovery | `RepoServiceProvider` | Run reviewed migrations in the intended database |

The package does not purge disappeared rows, add tenant ownership, sanitize Markdown, send webhooks or generate an offline catalog file itself. The [handbook](HANDBOOK.md) includes the complete ingestion/cache behavior and safe application recipes.
