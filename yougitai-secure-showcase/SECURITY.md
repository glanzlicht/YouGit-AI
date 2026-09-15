# Security architecture

YougitAI Secure Showcase is designed around a publication boundary, not around runtime masking.

## Core invariant

Original repository content must never be served to public visitors. During imports, source files are fetched into memory, scanned, optionally reviewed by an explicitly enabled AI provider, redacted, and then discarded. Only sanitized public-snapshot content may be persisted in the showcase file table.

Known-sensitive paths such as environment files, credential stores, private keys and certificate files are fail-closed before source content is fetched whenever possible. Hidden files persist only metadata and an empty content field.

## Publication gate

A snapshot moves through import, findings review, approval, deterministic final verification and publication. The final verifier scans the stored public snapshot again and blocks publication if it detects a remaining secret or an unresolved critical-risk public file. A verification hash is stored for successfully published snapshots.

## AI privacy

External AI processing is disabled by default. Enabling it means selected source files can be sent to the configured provider before the source is discarded. Repository content is treated as untrusted data and the AI system instruction explicitly rejects instructions embedded in source code, comments, README text, strings and filenames.

## WordPress boundary

Public rendering reads only the active published snapshot. Elementor, Elementor Pro, shortcodes and standalone routes all use the same public renderer and therefore the same sanitized data source.


## Credential storage

GitHub access tokens, webhook secrets and newly saved AI API keys are encrypted at rest with authenticated encryption derived from WordPress installation salts. Existing plaintext AI keys from earlier plugin builds are migrated during the 0.3 upgrade when encryption is available. Secrets are never returned by public REST endpoints.

## GitHub webhooks

Repository webhooks require an HMAC SHA-256 signature (`X-Hub-Signature-256`). Only push events for the configured repository and default branch are accepted for synchronization. Valid pushes queue a secure import job; they never approve or publish a snapshot.

## Persistent line redaction

Visual line-range rules store a fingerprint of the selected sanitized segment. On a later import, a missing, shifted or changed segment is treated as a protection failure. The file is hidden completely and a critical finding is created rather than risking exposure.

## Connected AI boundary

Version 0.6.0 adds a direct Connected AI interface intended for compatible MCP/JSON-RPC clients. It is designed around a strict capability boundary:

- Connection tokens are random, time-limited, revocable, and only their SHA-256 hashes are stored.
- No connected-AI tool can publish or restore a public snapshot.
- Source files are fetched from GitHub just in time and are not persisted by the connection layer.
- Known credential paths are denied before retrieval.
- The deterministic secret scanner masks detected credentials before source content is returned to the client.
- AI-created redaction rules are disabled proposals until a WordPress administrator explicitly approves them.
- Every source read and write-like proposal action is audit logged.

This layer does not weaken the existing publication boundary: only a verified public snapshot can become visitor-visible.


## Repository-scoped DIRECT connections

Version 0.7.0 narrows Connected AI access further. Each temporary token can be limited to an explicit repository allowlist. Allowing every current and future repository is a separate opt-in choice. Repository checks are enforced server-side before tree, source, findings, proposal, or snapshot operations run.

Each connected-AI token also has a cumulative source-review budget (5 MiB by default, filterable with `yougitai_ss_connected_ai_source_budget`). When the budget is exhausted, additional source reads fail closed. Metadata tools remain available so a session can report its state without receiving more source. This reduces the blast radius of a leaked temporary token and encourages short, purpose-specific review sessions.

## OAuth and scheduled synchronization

GitHub OAuth uses a short-lived state value tied to the current WordPress administrator and validates it before exchanging an authorization code. OAuth client secrets and resulting GitHub access tokens are encrypted with the same site-bound secret vault as other credentials. Hourly, daily, webhook, and manual synchronization paths all terminate at a draft snapshot; none can cross the human publication gate automatically.

## Showcase derivation

Portfolio metadata, detected technology stack, README preview, language distribution and architecture hints are derived only from already-sanitized public snapshot rows. The showcase-analysis layer never reads the original repository directly and therefore cannot bypass redaction rules or the final verification boundary.

## Uninstall behavior

Plugin data is preserved by default to avoid accidental destructive removal. Full deletion must be explicitly enabled in settings before uninstall. When enabled, uninstall removes YougitAI tables, stored credentials/settings and scheduled synchronization hooks.


## ChatGPT OAuth authorization

Version 1.0.9 makes OAuth the primary ChatGPT DIRECT authentication path. The MCP endpoint returns HTTP 401 with a Protected Resource Metadata pointer when no valid access token is present. The site publishes OAuth discovery metadata, supports dynamic registration of public clients, requires Authorization Code flow with PKCE S256, and issues short-lived access tokens plus rotating refresh tokens.

Authorization is performed in the browser by a logged-in WordPress administrator. The administrator must explicitly select one or more repositories; the resulting OAuth token is restricted to those repository IDs and the fixed DIRECT scopes. OAuth never grants a publish or restore capability. Authorization codes are single-use and short-lived, access/refresh tokens are stored only as SHA-256 hashes, token responses are no-store, and connections can be revoked from WordPress at any time.
