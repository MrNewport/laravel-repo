<?php
namespace MrNewport\LaravelRepo\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use MrNewport\LaravelRepo\Models\Repository;

class RepoService
{
    protected Client $client;
    protected string $username;
    protected string $token;
    protected string $visibility;

    public function __construct()
    {
        $this->client = new Client();
        $this->username = config('repo.github_username');
        $this->token = config('repo.github_token');
        $this->visibility = config('repo.minimum_visibility');
    }

    public function fetchRepositories(): array
    {
        if (Cache::has('github_repos')) {
            return Cache::get('github_repos');
        }

        // If it's 'private', we want to fetch 'all' repos (private + public)
        // If it's 'public', we only want 'public'
        $visibility = $this->visibility === 'private' ? 'all' : 'public';

        // When fetching private repos, you must use the /user/repos endpoint (not /users/{username}/repos).
        // This also requires you to provide a valid auth token.
        $url = $this->token
            ? "https://api.github.com/user/repos?visibility={$visibility}"
            : "https://api.github.com/users/{$this->username}/repos?visibility={$visibility}";

        $options = [
            'headers' => array_filter([
                // If you have a personal access token, set it here
                'Authorization' => $this->token ? 'token ' . $this->token : null,
                'Accept'        => 'application/vnd.github.v3+json'
            ])
        ];

        $response = $this->client->get($url, $options);
        $repos = json_decode($response->getBody()->getContents(), true);

        foreach ($repos as $key => &$repo) {
            try {
                $readme = $this->fetchReadme($repo['full_name']);
            } catch (\Exception $e) {
                // If we fail to fetch the README, remove this repo from the list
                unset($repos[$key]);
                continue;
            }

            $repo['readme'] = $readme;

            // Save or update repository in your DB
            Repository::updateOrCreate(
                ['full_name' => $repo['full_name']],
                [
                    'name'        => $repo['name'],
                    'html_url'    => $repo['html_url'],
                    'description' => $repo['description'],
                    'readme'      => $readme
                ]
            );
        }

        // Re-index array (in case of unsets)
        $repos = array_values($repos);

        // Cache the results
        Cache::put('github_repos', $repos, config('repo.cache_duration'));

        return $repos;
    }

    public function fetchReadme(string $repoFullName): string
    {
        $url = "https://api.github.com/repos/{$repoFullName}/readme";
        $options = [
            'headers' => array_filter([
                'Authorization' => $this->token ? 'token ' . $this->token : null,
                'Accept' => 'application/vnd.github.v3.raw'
            ])
        ];

        $response = $this->client->get($url, $options);

        return $response->getBody()->getContents();
    }
}
