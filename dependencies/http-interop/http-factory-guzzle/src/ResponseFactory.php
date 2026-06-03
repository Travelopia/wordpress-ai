<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\Response;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\ResponseFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\ResponseInterface;
class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, '1.1', $reasonPhrase);
    }
}
