<?php

declare (strict_types=1);
namespace Travelopia\WordPress_AI\Dependencies\Aysnc\WordPress\PhpAiClientBedrock;

use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Http\DTO\Request;
/**
 * Class for HTTP request authentication using an API key in an AWS Bedrock compliant way.
 */
class AwsBedrockApiKeyRequestAuthentication extends ApiKeyRequestAuthentication
{
    /**
     * {@inheritDoc}
     */
    public function authenticateRequest(Request $request): Request
    {
        // Add the API key to the request headers using Bearer token authentication.
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Inherited from SDK parent class.
        return $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
    }
}
