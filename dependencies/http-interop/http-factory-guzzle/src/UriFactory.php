<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\Uri;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UriFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UriInterface;
class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
