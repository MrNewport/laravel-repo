<?php

use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use MrNewport\LaravelRepo\{Models\Repository, Services\RepoService};

function repositoryRow(int $id, bool $private = false): array {
    return ['name' => 'repo'.$id, 'full_name' => 'example/repo'.$id, 'owner' => ['login' => 'example'], 'html_url' => 'https://github.com/example/repo'.$id, 'private' => $private];
}

it('paginates and retains repositories without readmes', function () {
    $first = array_map(fn ($i) => repositoryRow($i), range(1, 100));
    $responses = [new Response(200, [], json_encode($first))];
    for ($i = 1; $i <= 100; $i++) $responses[] = new Response(200, [], '# README');
    $responses[] = new Response(200, [], json_encode([repositoryRow(101)]));
    $responses[] = new Response(404);
    $history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $service = new RepoService(new Client(['handler' => $stack]));
    expect($service->fetchRepositories())->toHaveCount(101);
    expect(Repository::count())->toBe(101)->and(Repository::where('name', 'repo101')->first()->readme)->toBe('');
    expect((string) $history[101]['request']->getUri())->toContain('page=2');
    expect($service->fetchRepositories())->toHaveCount(101)->and(count($history))->toBe(103);
});

it('isolates private caches and flags private persisted rows', function () {
    config(['repo.github_token' => 'private-token', 'repo.minimum_visibility' => 'private']);
    $service = new RepoService(new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode([repositoryRow(1, true)])), new Response(200, [], 'private'),
    ]))]));
    expect($service->fetchRepositories())->toHaveCount(1);
    expect(Repository::first()->is_private)->toBeTrue();
    config(['repo.github_token' => '', 'repo.minimum_visibility' => 'public']);
    $public = new RepoService(new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], '[]')]))]));
    expect($public->fetchRepositories())->toBe([]);
});

it('does not swallow API errors as missing repositories', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode([repositoryRow(1)])), new Response(403),
    ]))]);
    expect(fn () => (new RepoService($client))->fetchRepositories())->toThrow(\GuzzleHttp\Exception\ClientException::class);
});
