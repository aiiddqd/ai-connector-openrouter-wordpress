<?php
/**
 * Plugin Name: AI Connector for OpenRouter
 * Plugin URI:  https://github.com/aiiddqd/ai-connector-openrouter-wordpress
 * Description: AI Provider for OpenRouter for the WordPress AI Client.
 * Author:      aiiddqd
 * Author URI:  https://github.com/aiiddqd
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-connector-openrouter-wordpress
 * GitHub Plugin URI: aiiddqd/ai-connector-openrouter-wordpress
 * Primary Branch: main
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version:     0.2.260529
 */


namespace AIConnectorOpenRouter;

use WordPress\AiClient\AiClient;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

if (! defined('ABSPATH')) {
	exit;
}

final class Plugin
{

	private static ?Plugin $instance = null;

	private function __construct()
	{
	}

	public static function get_instance(): Plugin
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void
	{
		require_once plugin_dir_path(__FILE__).'includes/autoload.php';
		add_action('init', array($this, 'register_provider'), 1);
		add_action('wp_connectors_init', array($this, 'register_connector'));
		add_filter('wpai_preferred_image_models', array($this, 'register_image_model_preferences'));
		add_action('wp_loaded', array($this, 'handle_ai_test'));
	}

	public function register_provider(): void
	{
		if (! function_exists('wp_supports_ai') || ! wp_supports_ai()) {
			return;
		}

		if (! class_exists(AiClient::class) || ! class_exists(OpenRouterProvider::class)) {
			return;
		}

		try {
			$registry = AiClient::defaultRegistry();

			if (! $registry->hasProvider('openrouter')) {
				$registry->registerProvider(OpenRouterProvider::class);
			}
		} catch (\Throwable $e) {
			wp_trigger_error(
				__METHOD__,
				sprintf('Failed to register OpenRouter provider: %s', $e->getMessage())
			);
		}
	}

	/**
	 * Appends OpenRouter image models to the AI plugin preferred image model list.
	 *
	 * @since 0.1.260329
	 *
	 * @param array<int, array{string, string}> $models Preferred image models from the AI plugin.
	 * @return array<int, array{string, string}> Updated preferred image models.
	 */
	public function register_image_model_preferences(array $models): array
	{
		$openrouter_models = array(
			array(
				'openrouter',
				'google/gemini-3.1-flash-image-preview',
			),
			array(
				'openrouter',
				'google/gemini-3-pro-image-preview',
			),
			array(
				'openrouter',
				'openai/gpt-5-image-mini',
			),
			array(
				'openrouter',
				'openai/gpt-5-image',
			),
			array(
				'openrouter',
				'google/gemini-2.5-flash-image',
			),
			array(
				'openrouter',
				'openrouter/auto',
			),
		);

		return array_merge($models, $openrouter_models);
	}

	/**
	 * Debug endpoint: ?ai_test=1 — show AI status; ?ai_test=prompt&q=Hello — generate text.
	 *
	 * @since 0.2.260529
	 */
	public function handle_ai_test(): void
	{

		if (!is_admin()) {
			return;
		}

		if (! isset($_GET['ai_test'])) {
			return;
		}

		// Only allow admins.
		if (! current_user_can('manage_options')) {
			wp_die('Unauthorized.');
		}

		header('Content-Type: text/plain; charset=utf-8');

		// If a prompt is requested, run text generation.
		if ('prompt' === $_GET['ai_test'] && ! empty($_GET['q'])) {
			if (! wp_supports_ai()) {
				echo "ERROR: wp_supports_ai() returned false.\n";
				exit;
			}

			$prompt = sanitize_text_field(wp_unslash($_GET['q']));

			// Build with model preference — DeepSeek V4 Pro first, then fallbacks.
			$builder = wp_ai_client_prompt($prompt)
				->using_model_preference(
					'deepseek/deepseek-v4-pro',
					'anthropic/claude-haiku-4-5',
					'google/gemini-2.5-flash',
					'openai/gpt-4o-mini'
				);

			echo "=== Generating text ===\n";
			echo "Prompt: " . $prompt . "\n\n";

			try {
				$result = $builder->generate_text_result();
				echo "Result: " . $result->toText() . "\n\n";

				// Show which model was actually used.
				$modelMeta = $result->getModelMetadata();
				$providerMeta = $result->getProviderMetadata();
				echo "=== Model used ===\n";
				printf("  Provider: %s (%s)\n", $providerMeta->getName(), $providerMeta->getId());
				printf("  Model:    %s (%s)\n", $modelMeta->getName(), $modelMeta->getId());

				$usage = $result->getTokenUsage();
				if ($usage) {
					printf("  Tokens:   %d in / %d out / %d total\n",
						$usage->getPromptTokens(),
						$usage->getCompletionTokens(),
						$usage->getTotalTokens()
					);
				}
			} catch (\Throwable $e) {
				echo "ERROR: " . $e->getMessage() . "\n";
			}
			echo "\n=== Done ===\n";
			exit;
		}

		// Default: dump AI status.
		echo "=== AI Status ===\n";
		echo "wp_supports_ai(): " . (wp_supports_ai() ? 'true' : 'false') . "\n";
		echo "AiClient class: " . (class_exists(AiClient::class) ? 'exists' : 'missing') . "\n\n";

		echo "=== Providers ===\n";
		$registry = AiClient::defaultRegistry();
		foreach (['openrouter', 'anthropic', 'google', 'openai'] as $id) {
			printf("  %s: %s\n", $id, $registry->hasProvider($id) ? 'registered' : 'not registered');
		}
		echo "\n";

		// Preferred models from filter.
		$preferred_text = apply_filters('wpai_preferred_text_models', []);
		echo "=== wpai_preferred_text_models ===\n";
		if (empty($preferred_text)) {
			echo "  (filter returned empty — using hardcoded defaults in AI plugin)\n";
		} else {
			foreach ($preferred_text as $m) {
				printf("  %s/%s\n", $m[0], $m[1]);
			}
		}

		// List models from OpenRouter provider using static methods.
		echo "\n=== OpenRouter models (first 10) ===\n";
		try {
			$className = $registry->getProviderClassName('openrouter');
			$all = $className::modelMetadataDirectory()->listModelMetadata();
			foreach (array_slice($all, 0, 10) as $model) {
				printf("  %s (%s)\n", $model->getId(), $model->getName());
			}
			echo "  ... total: " . count($all) . " models\n";
		} catch (\Throwable $e) {
			echo "  Error listing models: " . $e->getMessage() . "\n";
		}
		echo "\nUse ?ai_test=prompt&q=Hello to test text generation.\n";
		exit;
	}

	public function register_connector(\WP_Connector_Registry $registry): void
	{
		$connector = array(
			'name' => 'OpenRouter',
			'description' => 'Access 200+ AI models through a single unified API.',
			'logo_url' => plugin_dir_url(__FILE__).'assets/openrouter-icon.svg',
			'type' => 'ai_provider',
			'authentication' => array(
				'method' => 'api_key',
				'credentials_url' => 'https://openrouter.ai/keys',
				'setting_name' => 'connectors_ai_openrouter_api_key',
			),
			'plugin' => array(
				'slug' => 'ai-connector-openrouter-wordpress',
			),
		);

		if ($registry->is_registered('openrouter')) {
			$existing = $registry->unregister('openrouter');

			if (is_array($existing)) {
				$connector = array_replace_recursive($existing, $connector);
			}
		}

		$registry->register('openrouter', $connector);
	}
}

function ai_connector_openrouter(): Plugin
{
	return Plugin::get_instance();
}

ai_connector_openrouter()->run();

