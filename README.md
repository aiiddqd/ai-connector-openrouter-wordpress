# OpenRouter Connector for WordPress

OpenRouter Connector for WordPress adds an AI provider connector via the WordPress 7.0 Connectors API, so site owners can configure OpenRouter as an AI backend from a native admin screen. The plugin registers an ai_provider connector with api_key authentication, exposes a credentials link, and integrates with the WP AI Client for automatic provider discovery.

---

## WordPress AI API — Documentation

### Core Announcements (WordPress 7.0)

- [Introducing the AI Client in WordPress 7.0](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/) — `wp_ai_client_prompt()`, builder methods, feature detection, REST integration
- [Introducing the Connectors API in WordPress 7.0](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/) — connector registration, API key management, admin UI, `wp_connectors_init`
- [WordPress 7.0 Beta 1 announcement](https://wordpress.org/news/2026/02/wordpress-7-0-beta-1/) — overview of all new features
- [WordPress 7.0 Developer Notes (all)](https://make.wordpress.org/core/tag/dev-notes+7-0/) — full list of dev notes for the release

### PHP AI Client SDK

- [wordpress/php-ai-client on GitHub](https://github.com/WordPress/php-ai-client) — provider-agnostic PHP SDK bundled in WP 7.0 core; `AiClient`, `PromptBuilder`, provider registry
- [wp-ai-client WordPress plugin (deprecated in 7.0+)](https://github.com/WordPress/wp-ai-client) — the predecessor plugin; REST API endpoints and JS API still active

### Official Provider Plugin Examples

- [AI Provider for OpenAI](https://github.com/WordPress/ai-provider-for-openai) — reference implementation; `AbstractApiProvider`, `AbstractOpenAiCompatibleModelMetadataDirectory`
- [Call for testing: Community AI connector plugins](https://make.wordpress.org/ai/2026/03/25/call-for-testing-community-ai-connector-plugins/) — guidance for third-party provider plugins

### OpenRouter

- [OpenRouter API docs](https://openrouter.ai/docs) — OpenAI-compatible API, model IDs, authentication
- [OpenRouter models list](https://openrouter.ai/models) — all available models with IDs (format: `provider/model`)
- [OpenRouter API keys](https://openrouter.ai/keys) — create and manage API keys

### wp-env (Local Development)

- [Get started with wp-env](https://developer.wordpress.org/block-editor/getting-started/devenv/get-started-with-wp-env/) — official quick-start guide
- [@wordpress/env package reference](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) — full command reference, `.wp-env.json` schema
- [Quick and easy local WordPress development with wp-env](https://developer.wordpress.org/news/2023/03/quick-and-easy-local-wordpress-development-with-wp-env/) — WordPress Developer Blog overview

---

## Local Development

### Prerequisites

- [Node.js](https://nodejs.org/) LTS + npm
- [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) installed globally:
  ```bash
  npm -g i @wordpress/env
  ```

The local environment uses **WordPress Playground** (no Docker required) with **WordPress 7.0 RC2** and **PHP 8.3**.

### Start

```bash
wp-env start --runtime=playground
```

- Development site: http://localhost:8888
- Admin: `admin` / `password`

The plugin is automatically mounted and activated.

### Stop

```bash
wp-env stop
```

### Reset database

```bash
wp-env reset
wp-env start --runtime=playground
```

### Update WordPress / re-apply config

```bash
wp-env start --runtime=playground --update
```

### Notes

- Debug log is enabled — errors are written to `wp-content/debug.log` inside the environment.
- If you need WP-CLI or Composer inside the container, switch to the Docker runtime (remove `--runtime=playground`). Docker Desktop must be running.

