<?php

namespace App\Library;

require_once __DIR__ . '/../../vendor/autoload.php';

use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
class ApiRequest
{
    const API_VERSION = 2;

    const STATUS_OK = 200;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    use \App\Traits\Singleton;

    /** @var Client */
    private $client;

    /** @var array */
    private $defaultHeaders;

    private function __construct($baseUri, array $defaultHeaders = [], $timeout = 60)
    {
        $this->defaultHeaders = $defaultHeaders;

        $this->client = new Client([ 'base_uri' => $baseUri, 'timeout' => 240, 'exceptions' => false ]);
    }

    public function defaultHeaders(array $headers)
    {
        $this->defaultHeaders = $headers;
    }

    /**
     *
     * @param string $endpoint
     * @param array $data
     * @param array $query
     * @return void
     */
    public function makePostRequest($endpoint, array $data = [], array $query = [], array $headers = [], $apiVersion = 2)
    {
        return $this->makeRequest($endpoint, ApiRequest::METHOD_POST, $data, $query, $headers, $apiVersion);
    }

    public function makePostRequestIndacoin($endpoint, array $data = [], array $query = [], array $headers = [], $apiVersion = 2)
    {
        return $this->makeRequestIndacoin($endpoint, ApiRequest::METHOD_POST, $data, $query, $headers, $apiVersion);
    }

    /**
     *
     * @param string $endpoint
     * @param array $query
     * @param array $headers
     * @param int $apiVersion
     * @return void
     */
    public function makeGetRequest($endpoint, array $query = [], array $headers = [], $apiVersion = 2)
    {
        return $this->makeRequest($endpoint, ApiRequest::METHOD_GET, [], $query, $headers, $apiVersion);
    }

    public function makeGetRequestIndacoin($endpoint, array $query = [], array $headers = [], $apiVersion = 2)
    {
        return $this->makeRequestIndacoin($endpoint, ApiRequest::METHOD_GET, [], $query, $headers, $apiVersion);
    }

    /**
     *
     * Create request to API server, and return its response
     *
     * @param string $endpoint Route that has to be called
     * @param string $method Request method that should be applied
     * @param array $formData Data that are send to API as form
     * @param array $queryData Data that are send as query parameter to API
     * @param array $headers Headers that are send to API
     * @param int $apiVersion version of api, used to generate proper url
     *
     * @return array
     */
    public function makeRequest(
        $endpoint,
        $method = ApiRequest::METHOD_POST,
        array $formData = [],
        array $queryData = [],
        array $headers = [],
        $apiVersion = 2
    ) {
        if (empty($endpoint) || false === is_string($endpoint)) {
            throw new \RuntimeException("Invalid endpoint '{$endpoint}'");
        }
        
        $headers = $headers + $this->defaultHeaders;

        $endpoint = trim($endpoint, '/');
        /*print_r($formData);
        echo "<pre>";
        print_r($queryData);
        echo "<pre>";
        print_r($headers);
        exit;*/
        $response = $this->client->request($method, "/api/v1/{$endpoint}", [ 'form_params' => $formData, 'query' => $queryData, 'headers' => $headers,'debug' => false ]);
        //print_r($response->getBody()->getContents());exit;
        //print_r($formData);exit;
        return self::handleResponse($response, $apiVersion);
    }

    public function makeRequestIndacoin(
        $endpoint,
        $method = ApiRequest::METHOD_POST,
        array $formData = [],
        array $queryData = [],
        array $headers = [],
        $apiVersion = 2
    ) {
        
        if (empty($endpoint) || false === is_string($endpoint)) {
            throw new \RuntimeException("Invalid endpoint '{$endpoint}'");
        }
        
        $headers = $headers + $this->defaultHeaders;

        $endpoint = trim($endpoint, '/');
        /*print_r($formData);
        echo "<pre>";
        print_r($queryData);
        echo "<pre>";
        print_r($headers);
        exit;*/
        $response = $this->client->request($method, "/api/{$endpoint}", [ 'form_params' => $formData, 'query' => $queryData, 'headers' => $headers,'debug' => false ]);
        //print_r($response->getBody()->getContents());exit;
        //print_r($formData);exit;
        return self::handleResponse($response, $apiVersion);
    }
    
    /**
     * Handle API response, validate response and convert to proper associative array
     *
     * TODO: REMOVE THIS CRAP! AND DO REFACTOR ON HIGHER LEVEL SO THIS CAN BE CLEAN -.- - Aleksandar Zivanovic
     *
     * @param Response $response
     * @return void
     */
    private function handleResponse(Response $response = null, $apiVersion)
    {

        $body = json_decode($response->getBody(), true);
        Log::info('api response '.json_encode($body));
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException("API Returned invalid data.");
        }

        $message = $body['message'] ?? null;

        if ($message) {
            unset($body['message']);
        }

        /*return [
            'Success' => $response->getStatusCode() === 200 || $response->getStatusCode() === 201,
            'Status' => $response->getStatusCode(),
            'AppVersion' => "{$apiVersion}.0.0",
            'Message' => $message,
            'Result' => [ $body ]
        ];*/
        return $body;
    }
}
