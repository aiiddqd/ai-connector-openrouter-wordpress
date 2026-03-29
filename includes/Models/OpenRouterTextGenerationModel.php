<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Models;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
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
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Text generation model using OpenRouter's OpenAI-compatible Chat Completions API.
 *
 * Endpoint: POST https://openrouter.ai/api/v1/chat/completions
 *
 * OpenRouter uses the standard OpenAI chat/completions format, so the request
 * and response structure is identical to the OpenAI provider.
 *
 * @since 0.1.0
 *
 * @phpstan-type ChoiceData array{
 *     message: array{role: string, content: string},
 *     finish_reason: string
 * }
 * @phpstan-type UsageData array{
 *     prompt_tokens?: int,
 *     completion_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type CompletionResponseData array{
 *     choices: list<ChoiceData>,
 *     usage?: UsageData
 * }
 */
class OpenRouterTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface
{
    /**
     * Send a prompt to OpenRouter and return a GenerativeAiResult.
     *
     * @since 0.1.0
     *
     * @param list<Message> $prompt
     */
    final public function generateTextResult(array $prompt): GenerativeAiResult
    {
        $params = $this->buildRequestParams($prompt);

        $request = new Request(
            HttpMethodEnum::POST(),
            OpenRouterProvider::url('chat/completions'),
            [
                'Content-Type'    => 'application/json',
                // OpenRouter-specific headers for analytics / rate limit tracking.
                'HTTP-Referer'    => get_site_url(),
                'X-Title'         => get_bloginfo('name'),
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
     * Build the chat/completions request body.
     *
     * @since 0.1.0
     *
     * @param list<Message> $prompt
     * @return array<string, mixed>
     */
    private function buildRequestParams(array $prompt): array
    {
        $config = $this->getConfig();

        $params = [
            'model'    => $this->metadata()->getId(),
            'messages' => $this->buildMessages($prompt, $config->getSystemInstruction()),
        ];

        if ($config->getTemperature() !== null) {
            $params['temperature'] = $config->getTemperature();
        }

        if ($config->getMaxTokens() !== null) {
            $params['max_tokens'] = $config->getMaxTokens();
        }

        if ($config->getTopP() !== null) {
            $params['top_p'] = $config->getTopP();
        }

        if ($config->getStopSequences()) {
            $params['stop'] = $config->getStopSequences();
        }

        // JSON schema / structured output.
        $outputMimeType = $config->getOutputMimeType();
        $outputSchema   = $config->getOutputSchema();
        if ($outputMimeType === 'application/json' && $outputSchema) {
            $params['response_format'] = [
                'type'        => 'json_schema',
                'json_schema' => [
                    'name'   => 'response_schema',
                    'schema' => $outputSchema,
                    'strict' => true,
                ],
            ];
        }

        // Pass-through any custom options.
        foreach ($config->getCustomOptions() as $key => $value) {
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Convert prompt messages (and optional system instruction) to the OpenAI messages format.
     *
     * @since 0.1.0
     *
     * @param list<Message> $messages
     * @return list<array<string, string>>
     */
    private function buildMessages(array $messages, ?string $systemInstruction): array
    {
        $result = [];

        if ($systemInstruction) {
            $result[] = ['role' => 'system', 'content' => $systemInstruction];
        }

        foreach ($messages as $message) {
            $role = $message->getRole() === MessageRoleEnum::model() ? 'assistant' : 'user';
            $text = '';

            foreach ($message->getParts() as $part) {
                if ($part->isText()) {
                    $text .= $part->getText();
                }
            }

            if ($text !== '') {
                $result[] = ['role' => $role, 'content' => $text];
            }
        }

        return $result;
    }

    /**
     * Parse the chat/completions response into a GenerativeAiResult.
     *
     * @since 0.1.0
     */
    private function parseResponse(Response $response): GenerativeAiResult
    {
        /** @var CompletionResponseData $data */
        $data = $response->getData();

        if (empty($data['choices'])) {
            throw ResponseException::fromMissingData('OpenRouter', 'choices');
        }

        $candidates = [];
        foreach ($data['choices'] as $choice) {
            $content      = $choice['message']['content'] ?? '';
            $finishReason = FinishReasonEnum::fromString($choice['finish_reason'] ?? 'stop');

            $candidates[] = new Candidate($content, $finishReason);
        }

        $tokenUsage = null;
        if (!empty($data['usage'])) {
            $usage      = $data['usage'];
            $tokenUsage = new TokenUsage(
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0
            );
        }

        return new GenerativeAiResult(
            $candidates,
            $this->metadata()->getProviderMetadata(),
            $this->metadata(),
            $tokenUsage
        );
    }
}
