<?php

namespace Travelopia\WordPress_AI\Dependencies\Http\Discovery\Strategy;

use Travelopia\WordPress_AI\Dependencies\Http\Client\HttpAsyncClient;
use Travelopia\WordPress_AI\Dependencies\Http\Client\HttpClient;
use Travelopia\WordPress_AI\Dependencies\Http\Mock\Client as Mock;
/**
 * Find the Mock client.
 *
 * @author Sam Rapaport <me@samrapdev.com>
 */
final class MockClientStrategy implements DiscoveryStrategy
{
    public static function getCandidates($type)
    {
        if (is_a(HttpClient::class, $type, \true) || is_a(HttpAsyncClient::class, $type, \true)) {
            return [['class' => Mock::class, 'condition' => Mock::class]];
        }
        return [];
    }
}
