<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Models;

use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Image generation model using OpenRouter's Chat Completions API with modalities.
 *
 * OpenRouter routes image generation through the same chat/completions endpoint,
 * using a `modalities` parameter to request image output. Responses carry generated
 * images in `choices[].message.images[].image_url.url` as Base64 data URIs.
 *
 * Endpoint: POST https://openrouter.ai/api/v1/chat/completions
 *
 * @since 0.1.0
 *
 * @phpstan-type ImageChoiceData array{
 *     message: array{
 *         role: string,
 *         content?: string,
 *         images?: list<array{image_url: array{url: string}}>
 *     },
 *     finish_reason?: string
 * }
 * @phpstan-type UsageData array{
 *     prompt_tokens?: int,
 *     completion_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type ImageCompletionResponseData array{
 *     id?: string,
 *     choices: list<ImageChoiceData>,
 *     usage?: UsageData
 * }
 */
class OpenRouterImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface
{
    /**
     * Send a prompt to OpenRouter and return a GenerativeAiResult containing image candidates.
     *
     * @since 0.1.0
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
     */
    final public function generateImageResult(array $prompt): GenerativeAiResult
    {
        $params = $this->buildRequestParams($prompt);

        $request = new Request(
            HttpMethodEnum::POST(),
            OpenRouterProvider::url('chat/completions'),
            [
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title'      => get_bloginfo('name'),
            ],
            $params,
            $this->getRequestOptions()
        );

        $request  = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $this->getHttpTransporter()->send($request);

        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponse($response);
    }

    /**
     * Build the chat/completions request body for image generation.
     *
     * The `modalities` array is derived from the model config's output modalities so
     * that image-only models (e.g. Flux, `output_modalities: ["image"]`) send only
     * `["image"]`, while combined models (e.g. Gemini Image) send `["image", "text"]`.
     *
     * @since 0.1.0
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
     * @return array<string, mixed>
     */
    private function buildRequestParams(array $prompt): array
    {
        $config = $this->getConfig();

        // Convert ModalityEnum objects to plain strings for the OpenRouter API.
        $outputModalities = $config->getOutputModalities();
        if ($outputModalities !== null && count($outputModalities) > 0) {
            $modalities = array_map(static fn ($m) => $m->value, $outputModalities);
        } else {
            // Safe fallback: image+text covers Gemini-style models.
            $modalities = ['image', 'text'];
        }

        $params = [
            'model'      => $this->metadata()->getId(),
            'messages'   => $this->buildMessages($prompt),
            'modalities' => $modalities,
        ];

        $aspectRatio = $config->getOutputMediaAspectRatio();
        if ($aspectRatio !== null) {
            $params['image_config'] = ['aspect_ratio' => $aspectRatio];
        }

        foreach ($config->getCustomOptions() as $key => $value) {
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Convert prompt messages to the OpenAI messages format.
     *
     * Only text parts are forwarded; image parts in the prompt are not sent because
     * OpenRouter's image generation endpoint does not accept image inputs.
     *
     * @since 0.1.0
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $messages
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            $text = '';
            foreach ($message->getParts() as $part) {
                if ($part->getType()->isText()) {
                    $text .= $part->getText();
                }
            }
            if ($text !== '') {
                $result[] = ['role' => 'user', 'content' => $text];
            }
        }

        return $result;
    }

    /**
     * Parse the chat/completions response into a GenerativeAiResult.
     *
     * Iterates over `choices[].message.images[].image_url.url` to collect
     * Base64 data-URI images. Each image becomes a Candidate wrapping a File DTO.
     *
     * @since 0.1.0
     */
    private function parseResponse(Response $response): GenerativeAiResult
    {
        /** @var ImageCompletionResponseData $data */
        $data = $response->getData();

        if (empty($data['choices'])) {
            throw ResponseException::fromMissingData('OpenRouter', 'choices');
        }

        $candidates = [];

        foreach ($data['choices'] as $choice) {
            $images       = $choice['message']['images'] ?? [];
            $finishReason = FinishReasonEnum::tryFrom((string) ($choice['finish_reason'] ?? 'stop'))
                ?? FinishReasonEnum::stop();

            foreach ($images as $image) {
                $dataUri = $image['image_url']['url'] ?? '';
                if ($dataUri === '') {
                    continue;
                }

                $file         = new File($dataUri);
                $message      = new Message(MessageRoleEnum::model(), [new MessagePart($file)]);
                $candidates[] = new Candidate($message, $finishReason);
            }
        }

        if (empty($candidates)) {
            throw ResponseException::fromMissingData('OpenRouter', 'images');
        }

        $usage      = $data['usage'] ?? [];
        $prompt     = $usage['prompt_tokens'] ?? 0;
        $completion = $usage['completion_tokens'] ?? 0;
        $tokenUsage = new TokenUsage(
            $prompt,
            $completion,
            $usage['total_tokens'] ?? ($prompt + $completion)
        );

        $resultId = is_string($data['id'] ?? null) && $data['id'] !== ''
            ? $data['id']
            : wp_generate_uuid4();

        return new GenerativeAiResult(
            $resultId,
            $candidates,
            $tokenUsage,
            $this->providerMetadata(),
            $this->metadata()
        );
    }
}
