<?php

declare( strict_types = 1 );

namespace Aysnc\WordPress\PhpAiClientBedrock;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the AWS Bedrock model metadata directory.
 *
 * @phpstan-type ModelSummary array{
 *     modelArn?: string,
 *     modelId: string,
 *     modelName?: string,
 *     providerName?: string,
 *     inputModalities?: list<string>,
 *     outputModalities?: list<string>,
 *     responseStreamingSupported?: bool,
 *     customizationsSupported?: list<string>,
 *     inferenceTypesSupported?: list<string>,
 *     modelLifecycle?: array{status?: string}
 * }
 * @phpstan-type ModelsResponseData array{
 *     modelSummaries?: list<ModelSummary>
 * }
 */
class AwsBedrockModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {
	/**
	 * {@inheritDoc}
	 */
	public function getRequestAuthentication(): RequestAuthenticationInterface {
		/*
		 * Since we're calling the AWS Bedrock API here, we need to use the Bedrock specific
		 * API key authentication class.
		 */
		$request_authentication = parent::getRequestAuthentication();
		if ( ! $request_authentication instanceof ApiKeyRequestAuthentication ) {
			return $request_authentication;
		}
		return new AwsBedrockApiKeyRequestAuthentication( $request_authentication->getApiKey() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 */
	protected function sendListModelsRequest(): array {
		$request = new Request(
			HttpMethodEnum::GET(),
			AwsBedrockProvider::controlPlaneUrl( 'foundation-models' ),
			[ 'Content-Type' => 'application/json' ],
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToModelMetadataList( $response );
	}

	/**
	 * Parses the API response to a list of model metadata.
	 *
	 * @param Response $response The HTTP response from the list models API.
	 *
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 *
	 * @throws ResponseException If the response data is invalid or missing required fields.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['modelSummaries'] ) ) {
			throw ResponseException::fromMissingData( 'AWS Bedrock', 'modelSummaries' );
		}

		$models = [];

		foreach ( $response_data['modelSummaries'] as $model_data ) {
			$model_id   = $model_data['modelId'];
			$model_name = $model_data['modelName'] ?? $model_id;

			// Infer capabilities from model data.
			$capabilities = $this->inferCapabilities( $model_data );

			// Define supported options based on model capabilities.
			$options = $this->getSupportedOptions( $model_data );

			$models[ $model_id ] = new ModelMetadata(
				$model_id,
				$model_name,
				$capabilities,
				$options,
			);
		}

		return $models;
	}

	/**
	 * Infers model capabilities from the model data.
	 *
	 * @param ModelSummary $model_data The model data from the API.
	 *
	 * @return list<CapabilityEnum> The inferred capabilities.
	 */
	protected function inferCapabilities( array $model_data ): array {
		$capabilities = [];

		// Check if model supports ON_DEMAND inference (text generation).
		$inference_types = $model_data['inferenceTypesSupported'] ?? [];
		if ( in_array( 'ON_DEMAND', $inference_types, true ) ) {
			$capabilities[] = CapabilityEnum::textGeneration();
			$capabilities[] = CapabilityEnum::chatHistory();
		}

		// Check for image generation models (e.g., Stability AI).
		$output_modalities = $model_data['outputModalities'] ?? [];
		if ( in_array( 'IMAGE', $output_modalities, true ) ) {
			$capabilities[] = CapabilityEnum::imageGeneration();
		}

		// Default to text generation if no capabilities detected.
		if ( empty( $capabilities ) ) {
			$capabilities[] = CapabilityEnum::textGeneration();
		}

		return $capabilities;
	}

	/**
	 * Gets the supported options for a model based on its capabilities.
	 *
	 * @param ModelSummary $model_data The model data from the API.
	 *
	 * @return list<SupportedOption> The supported options.
	 */
	protected function getSupportedOptions( array $model_data ): array {
		// Base options supported by most Bedrock models via Converse API.
		$options = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::customOptions() ),
		];

		// Check if model supports multimodal input.
		$input_modalities = $model_data['inputModalities'] ?? [];
		if ( in_array( 'TEXT', $input_modalities, true ) && in_array( 'IMAGE', $input_modalities, true ) ) {
			$options[] = new SupportedOption(
				OptionEnum::inputModalities(),
				[
					[ ModalityEnum::text() ],
					[ ModalityEnum::text(), ModalityEnum::image() ],
				],
			);
		} elseif ( in_array( 'TEXT', $input_modalities, true ) ) {
			$options[] = new SupportedOption(
				OptionEnum::inputModalities(),
				[ [ ModalityEnum::text() ] ],
			);
		}

		// Check output modalities.
		$output_modalities = $model_data['outputModalities'] ?? [];
		if ( in_array( 'TEXT', $output_modalities, true ) ) {
			$options[] = new SupportedOption(
				OptionEnum::outputModalities(),
				[ [ ModalityEnum::text() ] ],
			);
		}

		// Tool support for models that support it (most Converse API models).
		if ( in_array( 'ON_DEMAND', $model_data['inferenceTypesSupported'] ?? [], true ) ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

		return $options;
	}
}
