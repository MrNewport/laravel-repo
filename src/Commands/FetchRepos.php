<?php
namespace MrNewport\LaravelRepo\Commands;

use Illuminate\Console\Command;
use MrNewport\LaravelRepo\Services\RepoService;

class FetchRepos extends Command
{
    protected $signature = 'repo:fetch';
    protected $description = 'Fetch repositories and their README files from GitHub';

    protected RepoService $repoService;

    public function __construct(RepoService $repoService)
    {
        parent::__construct();
        $this->repoService = $repoService;
    }

    public function handle()
    {
        $this->info('Fetching repositories...');
        $this->repoService->fetchRepositories();
        $this->info('Repositories have been fetched and updated.');
    }
}
