
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



