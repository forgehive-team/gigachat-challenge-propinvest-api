<?php

namespace App\Services\Crawlers;

use App\Models\Parameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PropertyCrawler
{
    const ROOT_URL = 'https://erzrf.ru/';
    const REGIONS_API_URL = 'https://erzrf.ru/erz-rest/api/v1/filtered/dictionary?dictionaryType=buildings_regions';
    const LISTING_PROPERTY_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/table';

    const SHOW_PROJECT_LOCATION_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/list-map';
    const SHOW_PROJECT_OBJECTS_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/full_cost_statistics/{id}'; // id = XXXXX
    const SHOW_PROJECT_DESCRIPTION_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/complexparams/{id}'; // id = XXXXX
    const SHOW_GK_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/index/{id}'; // id = 2170284001
    const SHOW_GK_IMAGES_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/media/{id}'; // id = 2170284001
    const SHOW_GK_ADVANTAGES_API_URL = 'https://erzrf.ru/erz-rest/api/v1/gk/advantages/{id}'; // id = 2170284001

    private static array $PARAMETERS = [];

    private function getHeaders(): array
    {
        // Browser-like headers
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Referer' => 'https://erzrf.ru/novostroyki',
        ];
    }

    private function getResponseResult($response)
    {
        if ($response->successful()) {
            return $response->json();
        } else {
            Log::critical(__METHOD__ . " Failed to fetch data from the API.", [
                'reason' => $response->reason(),
                'code' => $response->status(),
            ]);
        }
        return null;
    }

    public function sync(): array
    {
        $parameters = Parameter::all();
        foreach ($parameters as $parameter) {
            self::$PARAMETERS[$parameter->name] = $parameter->id;
        }
        // ...
        $output = [];
        $regions = $this->getRegions();
        if (!$regions) {
            return []; // die
        }
        // @todo
        $max = 4; // temp
        foreach ($regions as $regionItem) {
            if ($max == 0) {
                break;
            }
            $max--;
            $region = $regionItem->region;
            $regionKey = $regionItem->regionKey;
            if ($regionKey == 0) {
                continue;
            }
            echo "Handling region {$regionItem->name}\n";
            // @todo: endless loop
            $projects = $this->getProjects($region, $regionKey);
            if (!$projects) {
                echo "No projects for {$regionItem->name}\n";
                return []; // die
            }
            echo "Handling projects for {$regionItem->name}\n";
            foreach ($projects as $projectId) {
                $images             = $this->getProjectImages($projectId);
                $coordinates        = $this->getProjectLocation($projectId, $region, $regionKey);
                $basicAttributes    = $this->getProjectBasicInfo($projectId);
                $parameters         = $this->getProjectParameters($projectId);
                $output[] = array_merge([
                    'external_id'   => $projectId,
                    'coordinates'   => json_encode($coordinates),
                    'images'        => json_encode($images),
                    'parameters'    => $parameters,
                ], $basicAttributes);
                // $descriptionData    = $this->getProjectDescription($projectId, $region, $regionKey);
            }
        }
        return $output;
    }

    public function getRegions(): ?Collection
    {
        $response = Http::withHeaders($this->getHeaders())->get(self::REGIONS_API_URL);
        $result = $this->getResponseResult($response);

        return $result ? collect($result)->map(fn($item) => (object)[
            'region'    => $item['additional'],
            'regionKey' => $item['id'],
            'name'      => $item['text'],
        ]) : null;
    }

    public function getProjects($region, $regionKey, $min = 1, $max = 10, $page = 1): ?Collection
    {
        $query = [
            'region' => $region,
            'regionKey' => $regionKey,
            'costType' => 1,
            'sortType' => 'cmxrating',
            'min' => $min,
            'max' => $max,
            'page' => $page,
        ];

        $apiUrl = self::LISTING_PROPERTY_API_URL . '?' . http_build_query($query);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        return $result ? collect($result['list'])->map(fn($item) => $item['gkId']) : null;
    }

    // optional for AI
    private function getProjectLocation($id, $region, $regionKey): array
    {
        $query = [
            'gkId' => $id,
            'region' => $region,
            'regionKey' => $regionKey,
            'costType' => 1,
            'sortType' => 'qrooms'
        ];

        $apiUrl = self::SHOW_PROJECT_LOCATION_API_URL . '?' . http_build_query($query);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return $result['list'][0]['geometry']['coordinates'] ?? []; // [X, Y]
        }

        return [];
    }

    private function getProjectObjects($id, $region, $regionKey)
    {
        $query = [
            'gkId' => $id,
            'region' => $region,
            'regionKey' => $regionKey,
            'costType' => 1,
            'sortType' => 'qrooms',
        ];

        $apiUrl = self::SHOW_PROJECT_OBJECTS_API_URL . '?' . http_build_query($query);
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            // optional for AI functions
            return $result;
        }

        return null;
    }

    private function getProjectDescription($id, $region, $regionKey)
    {
        $query = [
            'gkId' => $id,
            'region' => $region,
            'regionKey' => $regionKey,
            'costType' => 1,
            'sortType' => 'qrooms',
        ];

        $apiUrl = self::SHOW_PROJECT_DESCRIPTION_API_URL . '?' . http_build_query($query);
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return $result; // <KEY>:<VALUE>
        }

        return null;
    }

    private function getProjectBasicInfo($id): ?array
    {
        $apiUrl = self::SHOW_GK_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return [
                // 'cert-mark'     => $result['erzMark'],
                // 'rating-mark'   => $result['topPlace'] ?? null,
                'name'          => $result['name'],
                'metro'         => isset($result['metro']) ? implode(', ', $result['metro']) : null, // [{...}, {...}]
                'city'          => $result['nasel_punkt'] ?? null,
                'area'          => $result['raion'] ?? null,
                'region'        => $result['region'] ?? null,
                'street'        => $result['street'] ?? null,
                'description'   => $result['placeDesc'] ?? null,
                // 'website'       => $result['site'] ?? null,
            ];
        }

        return null;
    }

    private function getProjectImages($id): array
    {
        $apiUrl = self::SHOW_GK_IMAGES_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return collect($result['photoUrls'])->map(fn($url) => self::ROOT_URL.$url)->toArray();
        }

        return [];
    }

    private function getProjectParameters($id): array
    {
        $apiUrl = self::SHOW_GK_ADVANTAGES_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            $parameters = [];
            foreach ($result as $data) {
                $parameters[] = [
                    'parameter_id' => self::$PARAMETERS[$data['groupName']],
                    'weight' => collect($data['values'])->map(fn($value) => $value['mark'])->sum(),
                ];
                foreach ($data['values'] as $value) {
                    $parameters[] = [
                        'parameter_id' => self::$PARAMETERS[$value['name']],
                        'weight' => $value['mark'],
                    ];
                }
            }
            return $parameters;
        }

        return [];
    }
}
