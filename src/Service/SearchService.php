<?php

namespace App\Service;

use Algolia\AlgoliaSearch\SearchClient;

class SearchService
{
    private $client;

    public function __construct($algoliaAppId, $algoliaApiKey)
    {
        $this->client = SearchClient::create($algoliaAppId, $algoliaApiKey);
    }

    public function index(array $objects, string $indexName)
    {
        $index = $this->client->initIndex($indexName);
        $index->saveObjects($objects, ['autoGenerateObjectIDIfNotExist' => true]);
    }

    public function search(string $query)
    {
        $index = $this->client->initIndex('movie');
        return $index->search($query);
    }
}