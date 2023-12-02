<?php

namespace App\Console\Commands\Rerunnable;

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
        $requestData = $this->crawler->sync();
        file_put_contents(public_path('request.json'), json_encode($requestData, JSON_PRETTY_PRINT));
    }
}
