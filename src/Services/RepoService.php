<?php

namespace MrNewport\LaravelRepo\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use MrNewport\LaravelRepo\Models\Repository;

class RepoService
{
    protected ClientInterface $client;
    protected string $username;
    protected string $token;
    protected string $visibility;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client(['timeout' => 30, 'connect_timeout' => 10]);
        $this->username = (string) config('repo.github_username', '');
        $this->token = (string) config('repo.github_token', '');
        $this->visibility = (string) config('repo.minimum_visibility', 'public');
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/D', $this->username)) {
            throw new \InvalidArgumentException('Configure a valid GitHub username.');
        }
        if (!in_array($this->visibility, ['public', 'private'], true)) {
            throw new \InvalidArgumentException('Visibility must be public or private.');
        }
        if ($this->visibility === 'private' && $this->token === '') {
            throw new \InvalidArgumentException('Private repository access requires a token.');
        }
    }

    public function fetchRepositories(): array
    {
        $key = 'github_repos:'.hash('sha256', json_encode([$this->username, $this->visibility, $this->token]));

        return Cache::remember($key, (int) config('repo.cache_duration', 3600), function () {
            $repos = [];
            $private = $this->visibility === 'private';
            $endpoint = $private ? '/user/repos' : '/users/'.$this->username.'/repos';
            for ($page = 1; ; $page++) {
                $query = ['per_page' => 100, 'page' => $page];
                if ($private) {
                    $query += ['visibility' => 'all', 'affiliation' => 'owner'];
                } else {
                    $query += ['type' => 'owner'];
                }
                $response = $this->client->request('GET', 'https://api.github.com'.$endpoint, [
                    'headers' => $this->headers(), 'query' => $query, 'allow_redirects' => false,
                ]);
                $batch = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($batch) || !array_is_list($batch)) {
                    throw new \UnexpectedValueException('GitHub returned an invalid repository list.');
                }
                foreach ($batch as $repo) {
                    if (strcasecmp($repo['owner']['login'] ?? '', $this->username) !== 0 || (!$private && ($repo['private'] ?? false))) {
                        continue;
                    }
                    $repo['readme'] = $this->fetchReadme($repo['full_name']);
                    Repository::updateOrCreate(['full_name' => $repo['full_name']], [
                        'name' => $repo['name'], 'html_url' => $repo['html_url'],
                        'description' => $repo['description'] ?? null, 'readme' => $repo['readme'],
                        'is_private' => (bool) ($repo['private'] ?? false),
                    ]);
                    $repos[] = $repo;
                }
                if (count($batch) < 100) {
                    return $repos;
                }
            }
        });
    }

    public function fetchReadme(string $repoFullName): string
    {
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9-]*/[A-Za-z0-9_.-]+$~D', $repoFullName)) {
            throw new \InvalidArgumentException('Expected an owner/repository name.');
        }
        try {
            $response = $this->client->request('GET', 'https://api.github.com/repos/'.$repoFullName.'/readme', [
                'headers' => $this->headers('application/vnd.github.raw+json'), 'allow_redirects' => false,
            ]);
            return (string) $response->getBody();
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() === 404) {
                return '';
            }
            throw $exception;
        }
    }

    private function headers(string $accept = 'application/vnd.github+json'): array
    {
        return array_filter([
            'Authorization' => $this->token !== '' ? 'Bearer '.$this->token : null,
            'Accept' => $accept, 'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'MrNewport-LaravelRepo',
        ]);
    }
}
