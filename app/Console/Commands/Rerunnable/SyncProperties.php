<?php

namespace App\Console\Commands\Rerunnable;

use App\Models\ParameterProject;
use App\Models\Project;
use App\Services\Crawlers\PropertyCrawler;
use Illuminate\Console\Command;

class SyncProperties extends Command
{
    protected $signature = 'app:sync-properties';

    protected $description = 'Sync properties';

    private PropertyCrawler $crawler;

    public function __construct()
    {
        $this->crawler = new PropertyCrawler();
        parent::__construct();
    }

    public function handle()
    {
        $projects = $this->crawler->sync();
        foreach ($projects as $project) {
            $parameters = $project['parameters'];
            unset($project['parameters']);
            $project = Project::create($project);
            foreach ($parameters as $parameter) {
                ParameterProject::create(array_merge($parameter, ['project_id' => $project->id]));
            }
        }
    }
}
