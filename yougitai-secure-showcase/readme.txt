=== YougitAI Secure Showcase ===
Contributors: yougitai
Tags: github, portfolio, code, elementor, security
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.0.26
License: GPLv2 or later

Secure GitHub repository showcases with strict source/public separation, deterministic secret scanning, optional AI-assisted review, standalone WordPress rendering and Elementor compatibility.

== Description ==

YougitAI Secure Showcase imports a GitHub repository into a strictly separated public snapshot. Original source is processed in memory, scanned and redacted before any showcase content is persisted. The public frontend never reads directly from GitHub.

The plugin works standalone in WordPress, via shortcode and through a native Elementor widget. Elementor Pro is supported but not required. Private GitHub repositories are supported through an encrypted fine-grained access token or a configured GitHub OAuth App. Large repository trees use a safe recursive fallback when GitHub truncates its recursive tree response.

External AI processing is optional and disabled by default. When enabled, selected source files can be reviewed before they are discarded. A final deterministic leak scan runs before publication.

== Security workflow ==

1. Import source into transient process memory.
2. Block known-sensitive paths.
3. Scan for secrets.
4. Optionally run AI exposure review.
5. Apply repository protection rules.
6. Persist only sanitized showcase content.
7. Review findings.
8. Run final leak scan.
9. Publish verified snapshot.

GitHub push webhooks can queue asynchronous imports. A webhook never publishes automatically; it only creates a new draft snapshot for review.

== Shortcode ==

[yougitai_showcase id="123"]
[yougitai_showcase slug="my-project"]
[yougitai_showcase_grid columns="3" theme="auto"]

Multi-provider AI review supports ChatGPT/OpenAI, Claude/Anthropic, and Gemini/Google with encrypted API-key storage.

After GitHub OAuth authorization, the Repositories screen can load all accessible public and private repositories directly from the connected GitHub account. Multiple repositories can be selected and imported at once; manual repository URLs remain available only as a fallback.

ChatGPT (DIRECT) uses native OAuth discovery and PKCE by default, so users normally paste only the MCP server URL into ChatGPT and approve repository access in WordPress. No model API key or copied bearer token is required. A temporary bearer-token mode remains available only as an advanced fallback for compatible MCP clients.


== Portfolio showcase ==

Published repositories include a secure overview, repository browser, README preview, detected technology stack, language distribution and high-level architecture modules derived exclusively from the verified public snapshot. A native Elementor repository-grid widget is included in addition to the single-showcase widget.

== GitHub connection ==

For private repositories, use either an encrypted fine-grained token or configure a GitHub OAuth App in YougitAI Settings and use the displayed callback URL. Webhook, hourly and daily sync modes create draft snapshots only.

== Data retention ==

Repository data is preserved on uninstall by default. Administrators can explicitly enable secure deletion of plugin tables, stored credentials and settings from the settings screen before uninstalling.


= 1.0.29 =
* Added configurable showcase colors in the plugin settings.
* Kept Elementor responsible only for the page canvas while YougitAI retains its own UI palette.


= 1.0.30 =
* Isolated repository browser buttons from global Elementor/theme button styles.
