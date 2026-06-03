<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Discovery\Strategy;

use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\RequestFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\ResponseFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\ServerRequestFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\StreamFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UploadedFileFactoryInterface;
use Travelopia\WordPress_AI\Dependencies\Psr\Http\Message\UriFactoryInterface;
/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
 */
final class CommonPsr17ClassesStrategy implements DiscoveryStrategy
{
    /**
     * @var array
     */
    private static $classes = [RequestFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\RequestFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\RequestFactory'], ResponseFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\ResponseFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\ResponseFactory'], ServerRequestFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\ServerRequestFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\ServerRequestFactory'], StreamFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\StreamFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\StreamFactory'], UploadedFileFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\UploadedFileFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\UploadedFileFactory'], UriFactoryInterface::class => ['Travelopia\WordPress_AI\Dependencies\Phalcon\Http\Message\UriFactory', 'Travelopia\WordPress_AI\Dependencies\Nyholm\Psr7\Factory\Psr17Factory', 'Travelopia\WordPress_AI\Dependencies\GuzzleHttp\Psr7\HttpFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Diactoros\UriFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Guzzle\UriFactory', 'Travelopia\WordPress_AI\Dependencies\Http\Factory\Slim\UriFactory', 'Travelopia\WordPress_AI\Dependencies\Laminas\Diactoros\UriFactory', 'Travelopia\WordPress_AI\Dependencies\Slim\Psr7\Factory\UriFactory', 'Travelopia\WordPress_AI\Dependencies\HttpSoft\Message\UriFactory']];
    public static function getCandidates($type)
    {
        $candidates = [];
        if (isset(self::$classes[$type])) {
            foreach (self::$classes[$type] as $class) {
                $candidates[] = ['class' => $class, 'condition' => [$class]];
            }
        }
        return $candidates;
    }
}
