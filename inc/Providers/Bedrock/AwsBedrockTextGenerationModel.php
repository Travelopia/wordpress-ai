<?php

declare( strict_types = 1 );

namespace Aysnc\WordPress\PhpAiClientBedrock;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Class for an AWS Bedrock text generation model.
 *
 * @phpstan-type UsageData array{
 *     inputTokens?: int,
 *     outputTokens?: int,
 *     totalTokens?: int
 * }
 * @phpstan-type ContentBlock array{
 *     text?: string,
 *     toolUse?: array{toolUseId: string, name: string, input: array<string, mixed>}
 * }
 * @phpstan-type MessageOutput array{
 *     role: string,
 *     content: list<ContentBlock>
 * }
 * @phpstan-type ResponseData array{
 *     output?: array{message?: MessageOutput},
 *     stopReason?: string,
 *     usage?: UsageData,
 *     metrics?: array{latencyMs?: int}
 * }
 */
class AwsBedrockTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {
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
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();

		// Prepare request parameters.
		$params = $this->prepareConverseParams( $prompt );

		// Get region from config.
		$region   = $this->getRegion();
		$model_id = $this->metadata()->getId();

		// Build request.
		$request = new Request(
			HttpMethodEnum::POST(),
			AwsBedrockProvider::runtimeUrl( "model/{$model_id}/converse", $region ),
			[ 'Content-Type' => 'application/json' ],
			$params,
			$this->getRequestOptions(),
		);

		// Add authentication credentials to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest( $request );

		// Send and process the request.
		$response = $http_transporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * Gets the AWS region from the model configuration.
	 *
	 * @return string The AWS region, defaults to 'us-east-1' when not provided or set via environment.
	 */
	protected function getRegion(): string {
		$custom_options = $this->getConfig()->getCustomOptions();
		$region         = $custom_options['region'] ?? null;
		if ( ! is_string( $region ) || '' === $region ) {
			$region = null;
		}

		return AwsBedrockProvider::resolveRegion( $region );
	}

	/**
	 * Prepares the given prompt and the model configuration into parameters for the Converse API.
	 *
	 * @param list<Message> $prompt The prompt to generate text for.
	 *
	 * @return array<string, mixed> The parameters for the Converse API request.
	 */
	protected function prepareConverseParams( array $prompt ): array {
		$config = $this->getConfig();

		$params = [
			'messages' => $this->prepareMessagesParam( $prompt ),
		];

		// System instruction.
		$system_instruction = $config->getSystemInstruction();
		if ( $system_instruction ) {
			$params['system'] = [
				[ 'text' => $system_instruction ],
			];
		}

		// Inference configuration.
		$inference_config = [];

		$max_tokens = $config->getMaxTokens();
		if ( null !== $max_tokens ) {
			$inference_config['maxTokens'] = $max_tokens;
		}

		$temperature = $config->getTemperature();
		if ( null !== $temperature ) {
			$inference_config['temperature'] = $temperature;
		}

		$top_p = $config->getTopP();
		if ( null !== $top_p ) {
			$inference_config['topP'] = $top_p;
		}

		$stop_sequences = $config->getStopSequences();
		if ( null !== $stop_sequences && ! empty( $stop_sequences ) ) {
			$inference_config['stopSequences'] = $stop_sequences;
		}

		if ( ! empty( $inference_config ) ) {
			$params['inferenceConfig'] = $inference_config;
		}

		// Tool configuration.
		$function_declarations = $config->getFunctionDeclarations();
		if ( null !== $function_declarations && ! empty( $function_declarations ) ) {
			$params['toolConfig'] = [
				'tools' => $this->prepareFunctionDeclarations( $function_declarations ),
			];
		}

		return $params;
	}

	/**
	 * Prepares the messages parameter from the prompt messages.
	 *
	 * @param list<Message> $prompt The prompt messages.
	 *
	 * @return list<array<string, mixed>> The formatted messages for the API.
	 */
	protected function prepareMessagesParam( array $prompt ): array {
		$messages = [];

		foreach ( $prompt as $message ) {
			$role    = $message->getRole()->value;
			$content = [];

			foreach ( $message->getParts() as $part ) {
				if ( null !== $part->getText() ) {
					$content[] = [ 'text' => $part->getText() ];
				} elseif ( null !== $part->getFile() ) {
					$file      = $part->getFile();
					$content[] = $this->prepareFileContent( $file );
				} elseif ( null !== $part->getFunctionResponse() ) {
					$function_response = $part->getFunctionResponse();
					$content[]         = [
						'toolResult' => [
							'toolUseId' => $function_response->getId(),
							'content'   => [
								[ 'json' => $function_response->getResponse() ],
							],
						],
					];
				} elseif ( null !== $part->getFunctionCall() ) {
					$function_call = $part->getFunctionCall();
					$content[]     = [
						'toolUse' => [
							'toolUseId' => $function_call->getId(),
							'name'      => $function_call->getName(),
							'input'     => $function_call->getArgs(),
						],
					];
				}
			}

			$messages[] = [
				'role'    => $role,
				'content' => $content,
			];
		}

		return $messages;
	}

	/**
	 * Prepares file content for the API request.
	 *
	 * @param File $file The file to prepare.
	 *
	 * @return array<string, mixed> The formatted file content.
	 *
	 * @throws InvalidArgumentException If the file type is not supported.
	 */
	protected function prepareFileContent( File $file ): array {
		$mime_type   = $file->getMimeType();
		$base64_data = $file->getBase64Data();

		if ( null === $base64_data ) {
			throw new InvalidArgumentException( 'File must have base64 data for Bedrock API.' );
		}

		// Handle image files.
		if ( str_starts_with( $mime_type, 'image/' ) ) {
			return [
				'image' => [
					'format' => $this->getImageFormat( $mime_type ),
					'source' => [
						'bytes' => $base64_data,
					],
				],
			];
		}

		// Handle document files.
		if ( str_starts_with( $mime_type, 'application/pdf' ) || str_starts_with( $mime_type, 'text/' ) ) {
			return [
				'document' => [
					'format' => $this->getDocumentFormat( $mime_type ),
					'name'   => 'document',
					'source' => [
						'bytes' => $base64_data,
					],
				],
			];
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not rendered output.
		throw new InvalidArgumentException(
			sprintf( 'Unsupported file MIME type: %s', $mime_type ),
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped.
	}

	/**
	 * Gets the image format from MIME type.
	 *
	 * @param string $mime_type The MIME type.
	 *
	 * @return string The image format for Bedrock API.
	 */
	protected function getImageFormat( string $mime_type ): string {
		$formats = [
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		];

		return $formats[ $mime_type ] ?? 'png';
	}

	/**
	 * Gets the document format from MIME type.
	 *
	 * @param string $mime_type The MIME type.
	 *
	 * @return string The document format for Bedrock API.
	 */
	protected function getDocumentFormat( string $mime_type ): string {
		$formats = [
			'application/pdf' => 'pdf',
			'text/plain'      => 'txt',
			'text/html'       => 'html',
			'text/csv'        => 'csv',
			'text/markdown'   => 'md',
		];

		return $formats[ $mime_type ] ?? 'txt';
	}

	/**
	 * Prepares function declarations for the tool config.
	 *
	 * @param list<FunctionDeclaration> $function_declarations The function declarations.
	 *
	 * @return list<array<string, mixed>> The formatted tools for the API.
	 */
	protected function prepareFunctionDeclarations( array $function_declarations ): array {
		$tools = [];

		foreach ( $function_declarations as $declaration ) {
			$tools[] = [
				'toolSpec' => [
					'name'        => $declaration->getName(),
					'description' => $declaration->getDescription(),
					'inputSchema' => [
						'json' => $declaration->getParameters(),
					],
				],
			];
		}

		return $tools;
	}

	/**
	 * Parses the API response to a GenerativeAiResult.
	 *
	 * @param Response $response The HTTP response from the API.
	 *
	 * @return GenerativeAiResult The parsed result.
	 *
	 * @throws ResponseException If the response data is invalid or missing required fields.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['output']['message'] ) ) {
			throw ResponseException::fromMissingData( 'AWS Bedrock', 'output.message' );
		}

		$message_data = $response_data['output']['message'];
		$role_string  = $message_data['role'];
		// Bedrock uses 'assistant', map to 'model' for SDK.
		$role = 'assistant' === $role_string ? MessageRoleEnum::model() : MessageRoleEnum::from( $role_string );

		// Parse content parts.
		$parts = [];
		foreach ( $message_data['content'] as $content_item ) {
			if ( isset( $content_item['text'] ) ) {
				$parts[] = new MessagePart( $content_item['text'] );
			} elseif ( isset( $content_item['toolUse'] ) ) {
				$tool_use = $content_item['toolUse'];
				$parts[]  = new MessagePart(
					new FunctionCall(
						$tool_use['toolUseId'],
						$tool_use['name'],
						$tool_use['input'],
					),
				);
			}
		}

		$response_message = new Message( $role, $parts );

		// Parse stop reason.
		$stop_reason   = $response_data['stopReason'] ?? 'end_turn';
		$finish_reason = $this->mapStopReason( $stop_reason );

		// Parse usage.
		$usage       = $response_data['usage'] ?? [];
		$token_usage = new TokenUsage(
			$usage['inputTokens'] ?? 0,
			$usage['outputTokens'] ?? 0,
			$usage['totalTokens'] ?? 0,
		);

		$candidate = new Candidate( $response_message, $finish_reason );

		// Additional data.
		$additional_data = $response_data;
		unset( $additional_data['output'], $additional_data['stopReason'], $additional_data['usage'] );

		// Generate a unique ID (Bedrock doesn't provide one in Converse API).
		$id = uniqid( 'bedrock_', true );

		return new GenerativeAiResult(
			$id,
			[ $candidate ],
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data,
		);
	}

	/**
	 * Maps the Bedrock stop reason to the SDK's FinishReasonEnum.
	 *
	 * @param string $stop_reason The stop reason from Bedrock API.
	 *
	 * @return FinishReasonEnum The mapped finish reason.
	 */
	protected function mapStopReason( string $stop_reason ): FinishReasonEnum {
		$mapping = [
			'end_turn'         => FinishReasonEnum::stop(),
			'max_tokens'       => FinishReasonEnum::length(),
			'stop_sequence'    => FinishReasonEnum::stop(),
			'tool_use'         => FinishReasonEnum::toolCalls(),
			'content_filtered' => FinishReasonEnum::contentFilter(),
		];

		return $mapping[ $stop_reason ] ?? FinishReasonEnum::error();
	}
}
