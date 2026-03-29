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
 * Version:     0.1.260329
 * Requires PHP: 7.4
 */


namespace AIConnectorOpenRouter;

use WordPress\AiClient\AiClient;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {
	}

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void {
		require_once plugin_dir_path( __FILE__ ) . 'includes/autoload.php';
		add_action( 'init', array( $this, 'register_provider' ), 1 );
		add_action( 'wp_connectors_init', array( $this, 'register_connector' ) );
	}

	public function register_provider(): void {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return;
		}

		if ( ! class_exists( AiClient::class ) || ! class_exists( OpenRouterProvider::class ) ) {
			return;
		}

		try {
			$registry = AiClient::defaultRegistry();

			if ( ! $registry->hasProvider( 'openrouter' ) ) {
				$registry->registerProvider( OpenRouterProvider::class );
			}
		} catch ( \Throwable $e ) {
			wp_trigger_error(
				__METHOD__,
				sprintf( 'Failed to register OpenRouter provider: %s', $e->getMessage() )
			);
		}
	}

	public function register_connector( \WP_Connector_Registry $registry ): void {
		$connector = array(
			'name'        => 'OpenRouter',
			'description' => 'Access 200+ AI models through a single unified API.',
			'logo_url'    => plugin_dir_url( __FILE__ ) . 'assets/openrouter-icon.svg',
			'type'        => 'ai_provider',
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://openrouter.ai/keys',
				'setting_name'    => 'connectors_ai_openrouter_api_key',
			),
			'plugin' => array(
				'slug' => 'ai-connector-openrouter-wordpress',
			),
		);

		if ( $registry->is_registered( 'openrouter' ) ) {
			$existing = $registry->unregister( 'openrouter' );

			if ( is_array( $existing ) ) {
				$connector = array_replace_recursive( $existing, $connector );
			}
		}

		$registry->register( 'openrouter', $connector );
	}
}

function ai_connector_openrouter(): Plugin {
	return Plugin::get_instance();
}

ai_connector_openrouter()->run();

