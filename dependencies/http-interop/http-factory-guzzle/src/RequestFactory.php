<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\Request;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\RequestFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\RequestInterface;
class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
}
