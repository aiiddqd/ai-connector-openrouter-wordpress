# OpenRouter Connector — WP AI Client SDK Reference

## Key sources

- WP 7.0 AI Client announcement: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
- PHP AI Client SDK: https://github.com/WordPress/php-ai-client
- WP AI Client package: https://github.com/WordPress/wp-ai-client

## Provider registry API (`ProviderRegistry`)

From `WordPress\AiClient\Providers\ProviderRegistry`:

| Method | Signature |
|--------|-----------|
| `registerProvider` | `(string $className): void` |
| `hasProvider` | `(string $idOrClassName): bool` |
| `getProviderClassName` | `(string $id): string` |
| `getProviderId` | `(string $idOrClassName): string` |
| `isProviderConfigured` | `(string $idOrClassName): bool` |
| `getProviderModel` | `(string $idOrClassName, string $modelId, ?ModelConfig $modelConfig): ModelInterface` |
| `findProviderModelsMetadataForSupport` | `(string $idOrClassName, ModelRequirements $requirements): list<ModelMetadata>` |
| `findModelsMetadataForSupport` | `(ModelRequirements $requirements): list<ProviderModelsMetadata>` |
| `getRegisteredProviderIds` | `(): string[]` |
| `bindModelDependencies` | `(ModelInterface $modelInstance): void` |

**There is NO `getProvider()` method.** Use:
- `getProviderClassName('openrouter')` → returns `class-string<ProviderInterface>`
- Then `$className::modelMetadataDirectory()->listModelMetadata()` to list models

## Model preference with `using_model_preference()`

From WP 7.0 docs and SDK:

```php
// Preferred models in priority order — first available wins.
$builder = wp_ai_client_prompt('...')
    ->using_model_preference(
        'deepseek/deepseek-v4-pro',
        'anthropic/claude-haiku-4-5',
        'google/gemini-2.5-flash'
    );
```

Formats accepted:
- `'provider/model-id'` string (provider + model, first matching across all providers)
- `['provider-id', 'model-id']` tuple (specific provider)
- Model instance (use the given model object)

If no preference matches, falls back to first compatible model found.

## `ProviderInterface` (static methods)

```php
interface ProviderInterface {
    public static function metadata(): ProviderMetadata;
    public static function model(string $modelId, ?ModelConfig $modelConfig = null): ModelInterface;
    public static function availability(): ProviderAvailabilityInterface;
    public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface;
}
```


```
use WordPress\AiClient\AiClient;

$text = AiClient::prompt('Write a 2-verse poem about PHP.')
    ->usingProvider('openai')
    ->generateText();
```
## Model discovery

```php
$requirements = new ModelRequirements([CapabilityEnum::textGeneration()]);
$results = $registry->findModelsMetadataForSupport($requirements);
// Returns list<ProviderModelsMetadata>
// Each: ->getProvider() (ProviderMetadata), ->getModels() (list<ModelMetadata>)
```

## Entry point

```php
// Basic
$text = wp_ai_client_prompt('Hello')->generate_text();

// With full result (includes provider/model metadata)
$result = wp_ai_client_prompt('Hello')->generate_text_result();
// $result->getProviderMetadata(), $result->getModelMetadata(), $result->getTokenUsage()
```

## Feature detection

```php
$builder = wp_ai_client_prompt('test')->using_temperature(0.7);
if ($builder->is_supported_for_text_generation()) {
    // Show UI
}
```

## Version

- WP AI Client SDK bundled in Core: `AiClient::VERSION` = `'1.3.1'` (as of php-ai-client 1.3.1)
- This plugin: `0.2.260529`
