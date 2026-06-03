<?php

declare (strict_types=1);
namespace Travelopia\WordPress_AI\Dependencies\Http\Adapter\Guzzle7;

use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Client as GuzzleClient;
use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\ClientInterface;
use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\HandlerStack;
use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Middleware;
use Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Utils;
use Travelopia\WordPress_AI\Dependencies\Http\Client\HttpAsyncClient;
use Travelopia\WordPress_AI\Dependencies\Http\Client\HttpClient;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\RequestInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\ResponseInterface;
/**
 * HTTP Adapter for Guzzle 7.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Client implements HttpClient, HttpAsyncClient
{
    /**
     * @var ClientInterface
     */
    private $guzzle;
    public function __construct(?ClientInterface $guzzle = null)
    {
        if (!$guzzle) {
            $guzzle = self::buildClient();
        }
        $this->guzzle = $guzzle;
    }
    /**
     * Factory method to create the Guzzle 7 adapter with custom Guzzle configuration.
     */
    public static function createWithConfig(array $config): Client
    {
        return new self(self::buildClient($config));
    }
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->sendAsyncRequest($request)->wait();
    }
    public function sendAsyncRequest(RequestInterface $request)
    {
        $promise = $this->guzzle->sendAsync($request);
        return new Promise($promise, $request);
    }
    /**
     * Build the Guzzle client instance.
     */
    private static function buildClient(array $config = []): GuzzleClient
    {
        $handlerStack = new HandlerStack(Utils::chooseHandler());
        $handlerStack->push(Middleware::prepareBody(), 'prepare_body');
        $config = array_merge(['handler' => $handlerStack], $config);
        return new GuzzleClient($config);
    }
}
