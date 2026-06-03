<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\Stream;
use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\Utils;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\StreamFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\StreamInterface;
class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return Utils::streamFor($content);
    }
    public function createStreamFromFile(string $file, string $mode = 'r'): StreamInterface
    {
        return $this->createStreamFromResource(Utils::tryFopen($file, $mode));
    }
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
