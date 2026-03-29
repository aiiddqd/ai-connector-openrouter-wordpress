<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Fetches and parses the list of models available via OpenRouter's /models endpoint.
 *
 * OpenRouter returns models in a format similar to the OpenAI /models endpoint,
 * so we extend the built-in OpenAI-compatible base class and only override:
 *   - How to build the HTTP request (point to OpenRouter URL)
 *   - How to parse the response into ModelMetadata objects
 *
 * OpenRouter model IDs use the format: provider/model-name
 * e.g. "openai/gpt-4o", "anthropic/claude-haiku-4", "meta-llama/llama-3.3-8b-instruct"
 *
 * @since 0.1.0
 *
 * @phpstan-type ModelsResponseData array{data: list<array{id: string, context_length?: int}>}
 */
class OpenRouterModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * Build an HTTP request pointed at the OpenRouter base URL.
     *
     * @since 0.1.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request(
            $method,
            OpenRouterProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * Parse the OpenRouter /models response into ModelMetadata objects.
     *
     * All OpenRouter models are treated as text generation capable.
     * This is a reasonable default — OpenRouter primarily routes LLM requests.
     *
     * @since 0.1.0
     *
     * @return list<ModelMetadata>
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();

        if (empty($responseData['data'])) {
            throw ResponseException::fromMissingData('OpenRouter', 'data');
        }

        $textGenCapabilities = [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];

        $textGenOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        return array_values(
            array_map(
                static function (array $modelData) use ($textGenCapabilities, $textGenOptions): ModelMetadata {
                    $modelName = '';
                    if (!empty($modelData['name']) && is_string($modelData['name'])) {
                        $modelName = $modelData['name'];
                    } else {
                        $modelName = (string) $modelData['id'];
                    }

                    return new ModelMetadata(
                        $modelData['id'],
                        $modelName,
                        $textGenCapabilities,
                        $textGenOptions
                    );
                },
                (array) $responseData['data']
            )
        );
    }
}
