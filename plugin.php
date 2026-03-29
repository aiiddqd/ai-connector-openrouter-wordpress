<?php
/**
 * Plugin Name: AI Connector for OpenRouter
 * Plugin URI:  https://github.com/aiiddqd/ai-connector-openrouter-wordpress
 * Description: Connects WordPress to the OpenRouter AI API.
 * Author:      aa
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-connector-openrouter
 * Version:     0.1.260329
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
	}
}

function ai_connector_openrouter(): Plugin {
	return Plugin::get_instance();
}

ai_connector_openrouter()->run();

