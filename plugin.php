<?php
/**
 * Plugin Name: AI Connector for OpenRouter
 * Plugin URI:  https://github.com/aiiddqd/ai-connector-openrouter-wordpress
 * Description: Connects WordPress to the OpenRouter AI API.
 * Author:      aiiddqd
 * Author URI:  https://github.com/aiiddqd
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-connector-openrouter-wordpress
 * Version:     0.1.260329
 * Requires PHP: 7.4
 */


namespace AIConnectorOpenRouter;

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
        //add autoload.php
        require_once plugin_dir_path( __FILE__ ) . 'includes/autoload.php';
		add_action( 'wp_connectors_init', array( $this, 'register_connector' ) );
	}

	public function register_connector( \WP_Connector_Registry $registry ): void {
		$registry->register( 'openrouter', array(
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
		) );
	}
}

function ai_connector_openrouter(): Plugin {
	return Plugin::get_instance();
}

ai_connector_openrouter()->run();

