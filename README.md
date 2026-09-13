# Laravel Repo

Current release: **1.0.0**. Supported installations: Laravel 12 (PHP 8.2+) and Laravel 13 (PHP 8.3+). CI verifies supported PHP/Laravel combinations. Earlier Laravel versions should remain on the previous major release.

First tagged release: Laravel 12/13, stable dependencies, isolated Testbench coverage. Repository sync paginates, keeps repositories without README files, propagates real API failures, scopes cache by owner/visibility/credential identity, uses owner-specific endpoints, and persists visibility. Existing rows are private until refreshed by the new sync.

```sh
composer require mrnewport/laravel-repo:^1.0
composer test # from the package checkout; tests use isolated fixtures
```

GitHub source and tags are published first. Until the release is indexed on Packagist, add this repository as a Composer VCS repository. Never install test dependencies in your production application's require section.

Run `php artisan migrate` after upgrading to apply package migrations. Back up application data before normal production migrations.

## Public and private data

`repo.minimum_visibility=public` fetches the configured owner's public repositories. `private` requires a token and includes only that owner's repositories visible to the token. The new `is_private` field defaults existing rows to true until refreshed. Public pages must filter `Repository::where('is_private', false)` and sanitize rendered Markdown. Applications remain responsible for authorization. Cache TTL is in seconds. No repository is removed merely because its README is missing; 403/rate-limit errors propagate for retry by the caller.

## Existing API reference


# MrNewport Repo Package

## Introduction
The MrNewport Repo package allows you to set your GitHub username, fetch all of your repositories, and store their README files in your db for display on your website.

## Installation

1. Require the package via Composer:

   ```bash
   composer require mrnewport/laravel-repo
   ```

2. Publish the configuration file:

   ```bash
   php artisan vendor:publish --provider="MrNewport\LaravelRepo\Providers\RepoServiceProvider"
   ```

3. Set your GitHub username and token (for private repos) in the `.env` file:

   ```dotenv
   GITHUB_USERNAME=your-github-username
   GITHUB_TOKEN=your-github-token
   REPO_CACHE_DURATION=3600
   MINIMUM_VISIBILITY=private
   ```

4. Run the migration:

   ```bash
   php artisan migrate
   ```

## Usage

- Run the following command to fetch repositories and their README files:

  ```bash
  php artisan repo:fetch
  ```

- You can also use the service in your controller, which will populate (when necessary) and store the repos in cache for the amount of time determined in your env file.

  ```php
  $service = app(\MrNewport\LaravelRepo\Services\RepoService::class);
  $repos = $service->fetchRepositories();
  return view('repo.index', compact('repos'));
  ```



