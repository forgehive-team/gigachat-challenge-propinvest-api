<?php

namespace App\Services\Crawlers;

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
        $regions = $this->getRegions();
        if (!$regions) {
            return []; // die
        }
        foreach ($regions as $regionItem) {
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
                $basicAttributes    = $this->getProjectBasicInfo($projectId);
                $advantages         = $this->getProjectAdvantages($projectId);
                $descriptionData    = $this->getProjectDescription($projectId, $region, $regionKey);
                return [[
                    'id'            => $projectId,
                    'meta'          => $basicAttributes,
                    'description'   => $descriptionData,
                    'parameters'    => $advantages,
                ]]; // 1 element as temp solution
            }
        }
        return [];
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
    private function getProjectLocation($id, $region, $regionKey): ?array
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
            return $result['list'][0]['geometry']['coordinates'] ?? null; // [X, Y]
        }

        return null;
    }


    /*
     * optional for AI service
     * {
        "apartmentsCount": 388,
        "filteredApartmentsCount": 388,
        "maxApartmentCost": 14.7,
        "maxApartmentCostM2": 213560.0,
        "maxSquare": 64.3,
        "minApartmentCost": 5.3,
        "minApartmentCostM2": 152500.0,
        "minSquare": 26.6,
        "objectId": "7836342001",
        "objectName": "Дом 5, Корпус 1",
        "objectStatistics": [
            {
                "apartmentsCount": "19",
                "filteredApartmentsCount": "19",
                "maxApartmentCost": "5.7",
                "maxApartmentCostM2": "213560",
                "maxSquare": "26.9",
                "minApartmentCost": "5.3",
                "minApartmentCostM2": "198000",
                "minSquare": "26.6",
                "objectId": "7836342001",
                "objectName": "Дом 5, Корпус 1",
                "rooms": "0",
                "state": "Сдан"
            },
            {
                "apartmentsCount": "39",
                "filteredApartmentsCount": "39",
                "maxApartmentCost": "14.7",
                "maxApartmentCostM2": "184600",
                "maxSquare": "82.6",
                "minApartmentCost": "8.9",
                "minApartmentCostM2": "160500",
                "minSquare": "52.3",
                "objectId": "7836342001",
                "objectName": "Дом 5, Корпус 1",
                "rooms": "4",
                "state": "Сдан"
            }
        ],
        "state": "Сдан"
    },
     */
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

    /*
     {
        "ecology": "Новый жилой район расположен недалеко от Финского залива — любимого места отдыха горожан — и окружен уникальным природным массивом — заповедником «Юнтоловский заказник Приоритетом для компании является сохранение и приумножение зеленых зон. В одном из кварталов комплекса сохранен участок натурального леса, между деревьями проложена экотропа. В «Юнтолово» запроектировано более 70 га парковых и прогулочных мест, включая доминанту —центральный парк и рекреационные зоны между кварталами (гринфингеры).",
        "energyeff": "Внешние стены изготовлены из газобетонных блоков толщиной 400 мм с минералватным утеплителем. Газобетон является современным, качественным и долговечным материалом. Его теплоизоляционные свойства в 2 раза выше, чем у кирпича.",
        "school": "Юнтолово» — район счастливых родителей. Здесь есть все необходимое для развития ребенка. В комплексе открыты два государственных дошкольных учреждения с бассейнами и один частный детский сад. В общеобразовательной школе компания «Главстрой Санкт-Петербург» в 2019 году запустила центр цифровых дисциплин «Техноспейс», в котором дети и подростки района бесплатно могут освоить востребованные специальности будущего: робототехника, инженерный дизайн CAD, интернет вещей. На базе школы открыт образовательный кластер Санкт-Петербургского государственного архитектурно-строительного университета, который готовит ребят к поступлению в вуз.",
        "sport": "Комплекс окружен лесным массивом и Юнтоловским заказником, что позволяет жителям круглогодично заниматься спортом на свежем воздухе. Летом жители ездят на велосипедах, бегают, занимаются скандинавской ходьбой. Зимой – катаются на лыжах, санках и ватрушках. Осень – время сбора грибов и ягод. В 2020 году на реке Юнтоловка открылся современный гребной клуб «Причал’Ю».",
        "trade": "Сейчас «Юнтолово»– это тридцать семь корпусов и всё необходимое в шаговой доступности: продуктовые магазины, кафе и пекарни, магазины формата «у дома», аптеки и медицинские центры, почта России и службы быта, дом творчества и много другое. Более 2 тыс. кв. м коммерческих объектов, подобранных по предпочтениям жителей.",
        "transport": "Компания самостоятельно строит транспортные магистрали. К настоящему моменту для движения открыто более 2 км дорог с остановками общественного транспорта, освещением и светофорами.Рядом с комплексом проходят магистрали городского значения – КАД, ЗСД и Приморское шоссе. Внутри комплекса налажено городское и коммерческое транспортное сообщение. В 20 минутах езды находится станция метро «Беговая».",
        "yardarea": "На территории комплекса расположены игровые площадки для детей разного возраста. Для подростков и взрослых предусмотрены спортивные зоны с тренажерами и площадки для игры в футбол и баскетбол. В июле 2022 года рядом со школой открылось новое мультиформатное пространство PlayHub. Зона занимает площадь более 13 тыс. м? и уже стала излюбленным местом для игр, развития и социализации маленьких жителей района. Проект был создан в сотрудничестве с детскими психологами. Площадка выполнена в экостиле, который подчеркивает концепцию «Юнтолово»."
    }
     */
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

    /*
     {
        "brandAndDevelopers": [
            {
                "brand": {
                    "ratingErz": "4.00",
                    "avgMarkErz": "3",
                    "countComplexesInTop": "6 из 0 ЖК",
                    "organizationName": "ГК Главстрой",
                    "place": "24",
                    "regionComplexesInTop": "г.Москва",
                    "name": "Группа компаний Главстрой",
                    "id": "1961151001",
                    "ratingOff": false,
                    "urlId": "gruppa-kompanij-glavstroj-1961151001"
                },
                "developers": [
                    {
                        "brandId": "1961151001",
                        "developerId": "362493001",
                        "developerName": "ООО Главстрой-СПб Специализированный застройщик",
                        "developerUrlId": "ooo-glavstroj-spb-specializirovannyj-zastrojshhik-362493001"
                    }
                ]
            }
        ],
        "buildGroupType": "жилой комплекс",
        "dateBuildEnd": "II кв. 2026",
        "dateBuildStart": "II кв. 2015",
        "erzMark": 49.0,
        "htmlPage": {
            "description": "ЖК Юнтолово - оценка по 120 параметрамсравнение с другими жилыми комплексами в г.Санкт-Петербург, фото хода строительства. Квартиры от 2,14 млн руб. по ценам от застройщика, акции, планировки, ипотека",
            "index": "index_follow",
            "title": "ЖК Юнтолово - цены, акции, планировки продающихся квартир на сайте от официального застройщика ГК Главстрой, ипотека. г. Санкт-Петербург, ул. 3-я Конная Лахта, метро Старая Деревня - ЕРЗ.",
            "url": "zhk-juntolovo-2170284001"
        },
        "id": 2170284001,
        "isPartner": false,
        "latitude": 60.031452,
        "liveSquare": "384639",
        "longtitude": 30.144178,
        "medal": {
            "gold": 1,
            "silver": 0,
            "bronze": 1,
            "descs": []
        },
        "metro": [
            "Старая Деревня (124 мин. пешком, 58 мин. на транспорте)"
        ],
        "metroList": [
            {
                "name": "Старая Деревня",
                "isBuild": false,
                "steps": 124,
                "drive": 58
            }
        ],
        "nallSquare": "83307",
        "name": "ЖК Юнтолово",
        "nasel_punkt": "г. Санкт-Петербург",
        "phoneSales": "(812) 6040404, (812) 2452961, (812) 6221852",
        "placeDesc": "В перспективе «Юнтолово» станет молодым динамичным районом Санкт-Петербурга с отличной транспортной доступностью и собственной развитой социальной инфраструктурой. В районе появятся собственные детские сады, школы и магазины.",
        "raion": "Приморский",
        "region": "г.Санкт-Петербург",
        "regionKey": 144781001,
        "revised": true,
        "salesApartmentsCount": 388,
        "showBanner": false,
        "showInShortCard": false,
        "site": "yuntolovo-spb.ru/",
        "smartBuildClass": "нет",
        "street": "улица 3-я Конная Лахта",
        "topPlace": 10,
        "urlId": "zhk-juntolovo-2170284001"
    }
     */
    private function getProjectBasicInfo($id): ?array
    {
        $apiUrl = self::SHOW_GK_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return [
                'external_id'   => $result['id'],
                'cert-mark'     => $result['erzMark'],
                'rating-mark'   => $result['topPlace'] ?? null,
                'metro'         => $result['metroList'] ?? null, // [{...}, {...}]
                'name'          => $result['name'],
                'location'      => [
                    'city'      => $result['nasel_punkt'],
                    'area'      => $result['raion'],
                    'region'    => $result['region'],
                    'street'    => $result['street'],
                ],
                'website'       => $result['site'] ?? null,
            ];
        }

        return null;
    }

    /*
    {
        "photoUrls": [
            "images/doctypes/22547470001DOCTYPES.jpg",
            "images/doctypes/22547483001DOCTYPES.jpg",
            "images/doctypes/22547485001DOCTYPES.jpg",
            "images/doctypes/22547487001DOCTYPES.jpg",
            "images/doctypes/22547489001DOCTYPES.jpg",
            "images/doctypes/22547491001DOCTYPES.jpg",
            "images/doctypes/22547493001DOCTYPES.jpg",
            "images/doctypes/22547495001DOCTYPES.jpg",
            "images/doctypes/22547497001DOCTYPES.jpg"
        ]
    }
     */
    private function getProjectImages($id): ?Collection
    {
        $apiUrl = self::SHOW_GK_IMAGES_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            // do smth...
            return collect($result['photoUrls'])->map(fn($url) => self::ROOT_URL.$url);
        }

        return null;
    }

    /*
    [
    {
        "groupId": 18716504001,
        "groupName": "Транспортная доступность",
        "groupShortName": "Транспортная доступность",
        "values": [
            {
                "id": 18938632001,
                "mark": 3.0,
                "name": "Уровень транспортной доступности",
                "ntypeuser": "0",
                "pinValue": "средний",
                "shortName": "Уровень транспортной доступности"
            },
            {
                "id": 18938678001,
                "mark": 2.0,
                "name": "Перспективы изменения уровня транспортной доступности",
                "ntypeuser": "0",
                "pinValue": "есть перспективы улучшения",
                "shortName": "Перспективы изменения уровня транспортной доступности"
            },
            {
                "id": 18952520001,
                "mark": 0.0,
                "name": "Улучшение комфорта транспортного обслуживания",
                "ntypeuser": "1",
                "pinValue": "нет",
                "shortName": "Улучшение комфорта транспортного обслуживания"
            }
        ]
    },
    ...
     */
    private function getProjectAdvantages($id): ?Collection
    {
        $apiUrl = self::SHOW_GK_ADVANTAGES_API_URL;
        $apiUrl = str_replace('{id}', $id, $apiUrl);
        $response = Http::withHeaders($this->getHeaders())->get($apiUrl);
        $result = $this->getResponseResult($response);

        if ($result) {
            return collect($result)->map(fn($item) => (object)[
                'group' => $item['groupName'],
                'values' => collect($item['values'])->map(fn($value) => (object)[
                    'name'          => $value['name'],
                    'rating-string' => $value['pinValue'],
                    'rating'        => $value['mark'], // float 3.0 or 0.0
                ])
            ]);
        }

        return null;
    }
}
