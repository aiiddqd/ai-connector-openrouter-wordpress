# OpenRouter Connector for WordPress

OpenRouter Connector for WordPress adds an AI provider connector via the WordPress 7.0 Connectors API, so site owners can configure OpenRouter as an AI backend from a native admin screen. The plugin registers an ai_provider connector with api_key authentication, exposes a credentials link, and integrates with the WP AI Client for automatic provider discovery.

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

