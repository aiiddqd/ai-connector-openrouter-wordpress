<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\OpenRouterAiProvider\Metadata\OpenRouterModelMetadataDirectory;
use WordPress\OpenRouterAiProvider\Models\OpenRouterTextGenerationModel;

/**
 * AI Provider for OpenRouter.
 *
 * OpenRouter exposes an OpenAI-compatible API at https://openrouter.ai/api/v1,
 * so we only need to override the base URL and provider metadata.
 *
 * API key priority (highest → lowest):
 *   1. OPENROUTER_API_KEY environment variable
 *   2. define( 'OPENROUTER_API_KEY', 'sk-or-...' ) in wp-config.php
 *   3. Settings → Connectors → OpenRouter (stored in DB)
 *
 * @since 0.1.0
 */
class OpenRouterProvider extends AbstractApiProvider
{
    /**
     * OpenRouter API base URL (OpenAI-compatible).
     *
     * @since 0.1.0
     */
    protected static function baseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    /**
     * Create a model instance for the given metadata.
     *
     * Currently supports text generation only.
     * Image generation via OpenRouter can be added later.
     *
     * @since 0.1.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();

        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new OpenRouterTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities for OpenRouter: ' . implode(', ', $capabilities)
        );
    }

    /**
     * Provider metadata — ID must match the connector ID registered via wp_connectors_init.
     *
     * @since 0.1.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $args = [
            'openrouter',                        // ID — matches connector & env var prefix
            'OpenRouter',                        // Display name
            ProviderTypeEnum::cloud(),
            'https://openrouter.ai/keys',        // Credentials URL shown in Settings → Connectors
            RequestAuthenticationMethod::apiKey(),
        ];

        // Description support added in php-ai-client 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            $args[] = function_exists('__')
                ? __('Access 200+ AI models via a single OpenAI-compatible API.', 'openrouter-connector')
                : 'Access 200+ AI models via a single OpenAI-compatible API.';
        }

        return new ProviderMetadata(...$args);
    }

    /**
     * Validate the API key by attempting to list available models.
     *
     * This is what runs when an admin saves the key in Settings → Connectors.
     *
     * @since 0.1.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * @since 0.1.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenRouterModelMetadataDirectory();
    }
}
