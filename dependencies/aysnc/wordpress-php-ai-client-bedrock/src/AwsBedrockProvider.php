<?php

declare (strict_types=1);
namespace Travelopia\WordPress_AI\Dependencies\Aysnc\WordPress\PhpAiClientBedrock;

use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Common\Exception\RuntimeException;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\DTO\ProviderMetadata;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
/**
 * Class for the AWS Bedrock provider.
 */
class AwsBedrockProvider extends AbstractApiProvider
{
    /**
     * The default AWS region to use.
     *
     * @var string Default AWS region.
     */
    public const DEFAULT_REGION = 'us-east-1';
    /**
     * The environment variable name for the AWS region.
     *
     * @var string The environment variable name.
     */
    public const ENV_REGION = 'AWS_BEDROCK_REGION';
    /**
     * {@inheritDoc}
     */
    protected static function baseUrl(): string
    {
        return static::controlPlaneUrl();
    }
    /**
     * Constructs a control plane URL for the given path and optional region.
     *
     * @param string      $path   The path to append to the base URL. Default empty string.
     * @param string|null $region The AWS region to use, or null for default.
     *
     * @return string The constructed URL.
     */
    public static function controlPlaneUrl(string $path = '', ?string $region = null): string
    {
        $region = static::resolveRegion($region);
        $base_url = "https://bedrock.{$region}.amazonaws.com";
        if ('' === $path) {
            return $base_url;
        }
        return $base_url . '/' . ltrim($path, '/');
    }
    /**
     * Constructs a runtime URL for the given path and optional region.
     *
     * @param string      $path   The path to append to the base URL. Default empty string.
     * @param string|null $region The AWS region to use, or null for default.
     *
     * @return string The constructed URL.
     */
    public static function runtimeUrl(string $path = '', ?string $region = null): string
    {
        $region = static::resolveRegion($region);
        $base_url = "https://bedrock-runtime.{$region}.amazonaws.com";
        if ('' === $path) {
            return $base_url;
        }
        return $base_url . '/' . ltrim($path, '/');
    }
    /**
     * Constructs a control plane URL for the given path and optional region.
     *
     * @param string      $path   The path to append to the base URL. Default empty string.
     * @param string|null $region The AWS region to use, or null for default.
     *
     * @return string The constructed URL.
     */
    public static function url(string $path = '', ?string $region = null): string
    {
        return static::controlPlaneUrl($path, $region);
    }
    /**
     * Resolves the AWS region from an explicit value, environment variable, or default.
     *
     * Priority: explicit param > AWS_BEDROCK_REGION > AWS_DEFAULT_REGION > 'us-east-1'.
     *
     * @param string|null $region The explicit region to use, if provided.
     *
     * @return string The resolved region.
     */
    public static function resolveRegion(?string $region = null): string
    {
        if (is_string($region) && '' !== $region) {
            return $region;
        }
        $env_region = getenv(self::ENV_REGION);
        if (\false === $env_region && defined(self::ENV_REGION)) {
            $env_region = constant(self::ENV_REGION);
        }
        if (is_string($env_region) && '' !== $env_region) {
            return $env_region;
        }
        $default_region = getenv('AWS_DEFAULT_REGION');
        if (\false === $default_region && defined('Travelopia\WordPress_AI\Dependencies\AWS_DEFAULT_REGION')) {
            $default_region = constant('AWS_DEFAULT_REGION');
        }
        if (is_string($default_region) && '' !== $default_region) {
            return $default_region;
        }
        return self::DEFAULT_REGION;
    }
    /**
     * {@inheritDoc}
     */
    protected static function createModel(ModelMetadata $model_metadata, ProviderMetadata $provider_metadata): ModelInterface
    {
        $capabilities = $model_metadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new AwsBedrockTextGenerationModel($model_metadata, $provider_metadata);
            }
        }
        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not rendered output.
        throw new RuntimeException('Unsupported model capabilities: ' . implode(', ', $capabilities));
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped.
    }
    /**
     * {@inheritDoc}
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata('aws-bedrock', 'AWS Bedrock', ProviderTypeEnum::cloud(), 'https://console.aws.amazon.com/bedrock/', RequestAuthenticationMethod::apiKey());
    }
    /**
     * {@inheritDoc}
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }
    /**
     * {@inheritDoc}
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new AwsBedrockModelMetadataDirectory();
    }
}
