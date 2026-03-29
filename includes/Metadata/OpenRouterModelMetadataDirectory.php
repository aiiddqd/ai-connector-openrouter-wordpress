<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Metadata;

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
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
 * @phpstan-type OpenRouterModelData array{
 *     id: string,
 *     name?: string,
 *     output_modalities?: list<string>,
 *     architecture?: array{
 *         output_modalities?: list<string>,
 *         modality?: string
 *     }
 * }
 * @phpstan-type ModelsResponseData array{data: list<OpenRouterModelData>}
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
     * Models with `output_modalities` containing "image" are registered as image
     * generation capable. All other models are treated as text generation capable.
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

        $imageOptions = [
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [
                [ModalityEnum::image()],
                [ModalityEnum::text(), ModalityEnum::image()],
            ]),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]),
            new SupportedOption(OptionEnum::outputMediaOrientation(), [
                MediaOrientationEnum::square(),
                MediaOrientationEnum::landscape(),
                MediaOrientationEnum::portrait(),
            ]),
            new SupportedOption(OptionEnum::outputMediaAspectRatio(), [
                '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9',
            ]),
        ];

        return array_values(
            array_map(
                static function (array $modelData) use ($textGenCapabilities, $textGenOptions, $imageOptions): ModelMetadata {
                    $modelName = '';
                    if (!empty($modelData['name']) && is_string($modelData['name'])) {
                        $modelName = $modelData['name'];
                    } else {
                        $modelName = (string) $modelData['id'];
                    }

                    $isImageGeneration = self::supportsImageGeneration($modelData);
                    if ($isImageGeneration) {
                        $capabilities = [CapabilityEnum::imageGeneration()];
                        $options      = $imageOptions;
                    } else {
                        $capabilities = $textGenCapabilities;
                        $options      = $textGenOptions;
                    }

                    return new ModelMetadata(
                        $modelData['id'],
                        $modelName,
                        $capabilities,
                        $options
                    );
                },
                (array) $responseData['data']
            )
        );
    }

    /**
     * Detect whether an OpenRouter model supports image generation.
     *
     * OpenRouter can expose modalities in multiple shapes depending on the model:
     * - output_modalities (top-level)
     * - architecture.output_modalities
     * - architecture.modality (e.g. "text->image", "text+image->image")
     *
     * @since 0.1.0
     *
     * @param OpenRouterModelData $modelData Raw model data from OpenRouter /models.
     */
    private static function supportsImageGeneration(array $modelData): bool
    {
        $modalities = [];

        if (isset($modelData['output_modalities']) && is_array($modelData['output_modalities'])) {
            foreach ($modelData['output_modalities'] as $modality) {
                if (is_string($modality)) {
                    $modalities[] = strtolower($modality);
                }
            }
        }

        $architecture = $modelData['architecture'] ?? null;
        if (is_array($architecture)) {
            if (isset($architecture['output_modalities']) && is_array($architecture['output_modalities'])) {
                foreach ($architecture['output_modalities'] as $modality) {
                    if (is_string($modality)) {
                        $modalities[] = strtolower($modality);
                    }
                }
            }

            if (!empty($architecture['modality']) && is_string($architecture['modality'])) {
                $modalityString = strtolower($architecture['modality']);

                // OpenRouter uses formats like "text+image->text" or "text->text+image".
                $parts = explode('->', $modalityString, 2);
                if (count($parts) === 2) {
                    $outputSide = $parts[1];
                    if (strpos($outputSide, 'image') !== false) {
                        return true;
                    }
                } elseif (strpos($modalityString, 'image') !== false) {
                    // Fallback for any legacy/non-standard modality string.
                    return true;
                }
            }
        }

        return in_array('image', $modalities, true);
    }
}
