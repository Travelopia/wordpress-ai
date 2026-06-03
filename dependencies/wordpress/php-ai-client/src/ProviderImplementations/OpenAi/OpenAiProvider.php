<?php

declare (strict_types=1);
namespace Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\ProviderImplementations\OpenAi;

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
 * Class for the OpenAI provider.
 *
 * @since 0.1.0
 */
class OpenAiProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 0.2.0
     */
    protected static function baseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModel(ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata): ModelInterface
    {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new OpenAiTextGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isImageGeneration()) {
                return new OpenAiImageGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isTextToSpeechConversion()) {
                // TODO: Implement OpenAiTextToSpeechConversionModel.
                throw new RuntimeException('OpenAI text to speech conversion model class is not yet implemented.');
            }
        }
        throw new RuntimeException('Unsupported model capabilities: ' . implode(', ', $capabilities));
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata('openai', 'OpenAI', ProviderTypeEnum::cloud(), 'https://platform.openai.com/api-keys', RequestAuthenticationMethod::apiKey());
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenAiModelMetadataDirectory();
    }
}
