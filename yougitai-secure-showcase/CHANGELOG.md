# Changelog

## 1.0.26
- Refresh GitHub repository metadata before every real GitHub snapshot import/sync.
- GitHub About/description changes now update the local repository description and therefore the public repository overview after the next GitHub sync.
- Refresh default branch, repository URL and private/public metadata together with the description.
- Applying approved protection rules to an existing safe draft remains local and does not contact GitHub.

# 1.0.25
- Protected Structure: fully protected files remain visible in the public repository tree with a lock badge, while their contents are never exposed.
- Derived folders remain visible and show protected/partially protected indicators.
- Clicking a protected file now renders a localized protection notice instead of source content.
- Public REST responses expose protection state but return an empty content field for protected files.
- Repository overview heading and description can be customized separately for German and English; the /repositories/ slug stays unchanged.
- Public showcase statistics/profile now count protected structural entries so visitors can understand the project shape without receiving hidden source.

# 1.0.24

- Improved dark-theme contrast on the public repository index: headings, card titles, descriptions, metadata and CTA links now keep readable colors even when the active WordPress theme overrides link/heading states.
- Added explicit hover, active, visited and keyboard-focus colors for repository links.
- Made rewrite upgrades reliable: plugin updates now defer rewrite flushing until after the current index/detail routes are registered on `init`.
- Activation now registers both `/repositories/` and `/repositories/{slug}/` before flushing.
- Showcase slug changes now defer the flush to the next request so WordPress cannot persist stale routes from the previous slug.

# 1.0.23

- Added a guided four-step repository workflow with a clear next action.
- Added "Apply protection now" to rebuild a protected review draft from an existing safe snapshot without contacting GitHub.
- Clarified that GitHub sync is only needed when upstream source changes.
- Added a standalone public repository overview at the configured `/repositories/` slug.

## 1.0.22

- Fixed the published `get_repository_tree` MCP input schema to expose optional `cursor`, `prefix`, and `limit` parameters while keeping only `repository_id` required.
- Added a live `connected-ai/tool-schema` diagnostic endpoint and admin checks showing the exact parameter names exported by the server.
- Kept backend pagination behavior (`next_cursor`, `has_more`) unchanged.

# YougitAI Secure Showcase changelog

## 1.0.21
- Added cache-independent `get_repository_tree_paginated` MCP tool with optional `cursor`, `prefix`, and `limit` inputs and explicit repository-wide pagination guidance.
- Added protection proposal filters by portfolio decision and proposal source.
- Bulk selection and “Approve all visible” now operate only on currently visible filtered proposals.

## 1.0.20
- Added bulk approval for connected-AI protection proposals with per-row checkboxes, Select all, Approve selected, and Approve all visible.
- Marked repository-scoped MCP read tools as closed-world/read-only to avoid overly conservative client safety classification.
- Added bounded `get_file_excerpt_for_review` as a safer source-review fallback (max 200 lines, secrets masked).
- Kept full source review scoped, read-only, audited, and subject to the existing source-byte budget.

## 1.0.19
- Added a cache-busting MCP tool name `get_repository_tree_v2` with explicit `repository_id`, `cursor`, `prefix`, and optional `limit` arguments in `tools/list`.
- Kept the existing tree tools for backward compatibility while mapping v2 to the same paginated handler.
- This avoids clients reusing a stale schema for the original `get_repository_tree` tool name.

## 1.0.18
- Fixed the exposed MCP `get_repository_tree` input schema so `cursor`, `prefix`, and `limit` are first-class arguments in `tools/list`.
- `cursor` and `prefix` are now required in the exposed schema (use `0` and an empty string on the first call) to prevent clients from collapsing the signature to `repository_id` only.
- Updated `get_repository_tree_page` the same way for compatibility.
- MCP initialize now advertises `tools.listChanged=true` so clients know tool definitions can change and should be rescanned.
- No Connected AI publish or restore capability was added.

## 1.0.17
- Added explicit MCP tool `get_repository_tree_page` with first-class `cursor`, `prefix`, and `limit` inputs.
- Pagination metadata is now returned at the top level (`items`, `next_cursor`, `has_more`, `total_matching`) so ChatGPT can iterate the complete repository tree reliably.
- Kept `get_repository_tree` backward compatible.
- No publish or restore capability was added to Connected AI.

# 1.0.16

- Fixed MCP `list_repositories` structured output: it now always returns an object with `repositories` and `count`.
- Reworked `get_repository_tree` into a paginated, prefix-filterable metadata-only response (default 250, max 500 paths) instead of returning up to 5000 entries at once.
- Added path-only risk hints for AI prompts, security logic, private endpoints, business logic, internal schemas, and core IP without reading source.
- Added `get_portfolio_policy` so connected AI clients use the same SHOW / ABSTRACT / REDACT / HIDE decision model.
- Added MCP output schemas and standard read/write annotations to make tool intent clearer to ChatGPT and other MCP clients.
- Redaction proposals can now carry portfolio classification and decision metadata while remaining disabled until human approval.
- No connected-AI publish or restore tool is exposed.

# 1.0.15

- Fixed OAuth authorization-server discovery returning 404 on some WordPress/front-end routing stacks by serving /.well-known metadata during parse_request, independent of rewrite rules/theme 404 handling.
- Added /.well-known/openid-configuration as a compatibility discovery alias.
- Added explicit HTTP 200 and CORS headers to public OAuth discovery metadata.

# Changelog

## 1.0.14
- Replaced the flat public file list with a collapsible repository tree.
- Added visible marker-style blackout overlays for protected passages in public code.
- Added per-file redaction counts and protected-line summaries without persisting original secret values.
- Added a redaction manifest that stores only location/length/replacement metadata for new snapshots.
- Added legacy placeholder detection so existing redacted snapshots still show visible protection markers.
- Fixed dark-theme link, hover, active, visited, and keyboard-focus contrast.

## 1.0.13
- Forces the MCP OAuth `WWW-Authenticate` challenge at the final WordPress REST serving layer so reverse-proxy or REST response handling cannot silently drop the plugin-level challenge.
- Adds `Vary: Authorization` and no-store headers to protected MCP responses.
- Adds an admin OAuth diagnostics panel that checks the public MCP 401 response, `WWW-Authenticate`, Protected Resource Metadata, and Authorization Server Metadata from the site itself.

## 1.0.12
- Added MCP 2026 Client ID Metadata Document (CIMD) support for ChatGPT OAuth clients.
- Advertises `client_id_metadata_document_supported` and RFC 9207 issuer responses.
- Uses a standards-compliant OAuth `401 invalid_token` response at the MCP HTTP boundary.
- Keeps Dynamic Client Registration as a backwards-compatible fallback.

## 1.0.11
- Fixed ChatGPT OAuth discovery by allowing unauthenticated GET requests to the MCP endpoint to return a proper 401 OAuth challenge instead of a WordPress REST 404.
- Added `scope` guidance to the `WWW-Authenticate` challenge.
- Authenticated GET requests now return MCP-compliant `405 Method Not Allowed` with `Allow: POST` because this server does not expose an SSE stream.

## 1.0.10
- Fixed ChatGPT OAuth discovery for path-based MCP endpoints by serving RFC 9728 protected-resource metadata at both the root and resource-specific well-known URI and advertising the resource-specific URL in 401 challenges.

# Changelog

## 1.0.12
- Added MCP 2026 Client ID Metadata Document (CIMD) support for ChatGPT OAuth clients.
- Advertises `client_id_metadata_document_supported` and RFC 9207 issuer responses.
- Uses a standards-compliant OAuth `401 invalid_token` response at the MCP HTTP boundary.
- Keeps Dynamic Client Registration as a backwards-compatible fallback.

## 1.0.9

- Replaced the primary ChatGPT DIRECT bearer-token setup with native OAuth 2.1-style authorization for custom MCP apps.
- Added Protected Resource Metadata and Authorization Server Metadata discovery endpoints.
- Added dynamic OAuth client registration for public PKCE clients, Authorization Code + PKCE S256, short-lived access tokens, rotating refresh tokens, and `offline_access` discovery.
- Added a WordPress administrator consent screen that lets the site owner choose exactly which repositories ChatGPT may review.
- Added OAuth connection revocation and audit logging while preserving the hard rule that DIRECT exposes no publish or restore tool.
- Kept temporary bearer tokens only as an advanced fallback for MCP clients that explicitly support them.
- Reworked the ChatGPT setup instructions so the normal flow is now: paste MCP URL → choose OAuth → approve repositories in WordPress.

## 1.0.8

- Fixed the combined “Review and publish” action so it can approve a pending review, run final verification, and activate the verified public snapshot in one workflow.
- Added repository publication/access states: draft/offline, public, password protected, and private/admin-only.
- Added reversible publication state changes without deleting the verified snapshot.
- Added password-protected frontend access with invalidation when the password changes.

## 1.0.7

- Rebuilt ChatGPT (DIRECT) setup as a three-step guided wizard.
- Added clear repository access selection and an advanced all-repositories warning.
- Added one-click copy controls for MCP endpoint, temporary token, and starter prompt.
- Added explicit guidance that the bearer token belongs in the ChatGPT custom-app authentication field and must never be pasted into a conversation.
- Added a human-readable permission summary and clearer connection management.
- Improved responsive layout and accessibility of the direct-connection setup.

## 1.0.6
- Reworked snapshot review UX with protection summary, grouped findings, clearer status language, and visible explanations.
- Added a prominent AI protection panel with provider-aware actions and a clear ChatGPT DIRECT path.
- Added bulk actions for finding groups and multi-file hiding on the next import.
- Clarified that scanner findings marked as protected are already redacted in the stored draft.
- Improved file risk labels and manual-redaction discoverability.

## 1.0.5

- Added per-repository **Original source** display mode for repositories that may be shown without redaction.
- Original mode is explicit and requires a warning acknowledgement; it never changes the currently published snapshot automatically.
- Secure mode remains the default and recommended mode; existing protection rules are retained when switching modes.
- Clarified sync behavior: every sync creates a draft and never overwrites the live showcase automatically.
- Reworked repository admin UX with visible Code & Redaction navigation, live-vs-draft status cards, clearer manual protection actions, and improved button contrast/disabled states.

## 1.0.4
- Reworked repository admin UX with clear section navigation and live-vs-draft workflow status.
- Added prominent manual Code & Redaction entry point and one-click whole-file protection.
- Clarified that sync always creates a draft, reapplies protection rules, and never overwrites the live showcase automatically.
- Fixed admin button contrast across primary, secondary, hover, focus, and disabled states.
- Improved empty states and file selection visibility for manual redaction.

## 1.0.3
- Added an OAuth-powered GitHub repository picker with multi-select import for accessible public and private repositories.
- Added quick actions to select all repositories, private repositories only, or clear the selection.
- Already imported repositories are detected and disabled in the picker.
- GitHub OAuth now returns directly to the repository picker after successful authorization.
- Manual repository URL import is retained as a collapsed fallback only.
- Added a refresh action for the connected GitHub repository list and complete DE/EN localization for the new workflow.

## 1.0.2

- Added a complete in-plugin GitHub OAuth setup guide directly beside the OAuth credentials.
- Shows the current site's Homepage URL and exact Authorization callback URL as selectable fields.
- Added explicit guidance that private GitHub repositories remain private and only reviewed public snapshots are exposed.
- Added direct navigation to GitHub Developer Settings and step-by-step DE/EN instructions.

## 1.0.1
- Fix standalone showcase rewrite registration during activation so `/repositories/{slug}/` works immediately after plugin activation.
- Live-verified Elementor and Elementor Pro widget registration, DIRECT authentication gate, database schema creation, and scheduled cleanup on WordPress 6.7 / PHP 8.1.

## 1.0.0
- Release hardening for production use.
- Added per-connection rate limiting and no-store security headers for ChatGPT (DIRECT) MCP responses.
- Added daily cleanup of expired/revoked DIRECT connection records.
- Corrected standalone showcase routing to return real 404 responses for unavailable projects.
- Final compatibility baseline validated against WordPress 7.1 / PHP 8.4 environments with Elementor and Elementor Pro present, while keeping Elementor optional.
- Completed release lint, translation, package-integrity, security-boundary and upgrade checks.

## 0.9.0

- Added GitHub OAuth App connection flow with state validation, encrypted OAuth client secret, encrypted access token storage, and exact callback URL guidance.
- Added recursive large-repository tree fallback when GitHub truncates recursive tree responses, with a configurable hard node limit.
- Added hourly and daily secure synchronization modes in addition to manual and signed-webhook sync; scheduled syncs only create draft snapshots.
- Added a professional portfolio showcase with Overview, README preview, Tech Stack, language distribution, high-level architecture modules, and secure code browsing derived only from the verified public snapshot.
- Added a multi-project portfolio grid shortcode and native Elementor grid widget; Elementor Pro remains optional.
- Added a system-status panel covering PHP, HTTPS, encrypted secret storage, background queue, Elementor availability, and active AI mode.
- Added explicit data-retention controls and opt-in secure plugin-data deletion on uninstall.
- Expanded German and English localization for all new release surfaces.
- Preserved the hard publication boundary: original GitHub source is never served publicly, automated sync never publishes, and DIRECT AI exposes no publish or restore tool.

## 0.7.0

- Hardened ChatGPT (DIRECT) with per-connection repository allowlists for least-privilege access.
- Added an explicit all-repositories opt-in; new connections otherwise require at least one selected repository.
- Added connected-AI source-review budgets to limit data exposure if a temporary token is compromised.
- Added connection diagnostics exposing scopes, repository restrictions, expiry, and source-budget usage without exposing secrets.
- Added review-context metadata so connected AI can understand protection profiles, recent snapshots, and existing rules before requesting source.
- Added batch source review for up to ten paths and batch redaction proposals for up to fifty suggestions per call.
- Added a tool for reading connected-AI proposal approval state.
- Preserved the hard publication boundary: DIRECT still has no publish or restore capability and all AI-created redactions remain disabled until human approval.
- Expanded German and English localization for all new DIRECT controls and security messages.

## 0.6.1
- Added `ChatGPT (DIRECT)` as a first-class AI mode alongside OpenAI, Anthropic Claude, and Google Gemini API modes.
- Added contextual setup guidance for connecting a ChatGPT custom MCP app directly to the plugin without storing a model API key in WordPress.
- Direct mode now shows the MCP endpoint, temporary connection-token workflow, permissions, and explicit no-publish security boundary in the AI settings area.
- Reworked the settings markup to avoid nested forms between general settings and connection-token management.
- Direct mode disables server-side external-AI processing; connected AI actions continue through the scoped MCP connection layer only.

## 0.6.0
- Added a first-party Connected AI / MCP-style JSON-RPC endpoint with no WPVibe dependency.
- Added short-lived, revocable bearer connection tokens stored as SHA-256 hashes only.
- Added scoped connected-AI permissions for repository metadata, just-in-time source review, findings, redaction proposals, and draft snapshot creation.
- Deliberately exposes no publish tool or publish permission to connected AI clients.
- Added just-in-time GitHub source retrieval for review; source is not persisted by the connection layer.
- Added pre-egress secret masking and hard blocks for sensitive credential paths before source can be returned to a connected AI client.
- Added audit events for connected-AI source reads, redaction proposals, approvals, and draft snapshot creation.
- Connected-AI redaction rules are stored disabled and require explicit WordPress administrator approval before they can affect future snapshots.
- Added connection management UI with one-time token display, expiry, revocation, status, and MCP endpoint discovery.
- Added rule-source/status UI for reviewing Connected AI proposals.
- Added dedicated notes storage for AI proposal rationale so rationale can never become public replacement text.

## 0.5.0

- Added provider-neutral AI review support for ChatGPT/OpenAI, Claude/Anthropic, and Gemini/Google.
- Added encrypted, independent API-key storage and model settings for all three providers.
- Added a shared normalized security-review schema so downstream findings and fail-closed publication logic stay provider-independent.
- Added Gemini structured JSON output integration and Claude Messages API integration with strict JSON validation.
- Added active-provider selection without deleting credentials for inactive providers.
- Kept external AI processing opt-in and source/public separation unchanged.
- Expanded German and English localization for all multi-provider AI settings.

## 0.4.0

- Added symbol-based protection rules for named classes, functions, and methods.
- Symbol rules survive line-number movement and fail closed when a symbol is missing, ambiguous, or its signature changes unexpectedly.
- Added secure restore/rollback to previously published snapshots only.
- Restores rerun the complete final leak scan and require the stored verification hash to match before activation.
- Made snapshot verification hashes deterministic by sorting files before hashing.
- Added repository audit-history UI for security-relevant events.
- Added translated admin controls for symbol protection, snapshot restoration, and audit history.
- Preserved strict source/public separation and draft-only sync behavior.

## 0.3.0

- Added encrypted GitHub fine-grained token storage for private repositories.
- Added encrypted-at-rest storage and automatic migration for existing OpenAI API keys.
- Added signed GitHub webhook endpoint with SHA-256 HMAC verification.
- Added asynchronous secure sync queue using Action Scheduler when available and WP-Cron fallback otherwise.
- Webhook syncs create draft snapshots only and never auto-publish.
- Added per-repository webhook secret generation/rotation and manual/webhook sync modes.
- Added interactive visual line-range redaction editor in wp-admin.
- Added fingerprinted line protection rules that fail closed when upstream code changes.
- Added critical stale-rule findings when line protection can no longer be applied safely.
- Expanded German and English translation catalogs for all new UI and security states.
- Preserved standalone WordPress and Elementor/Elementor Pro compatibility through the shared sanitized renderer.

## 0.2.0

- Added strict review workflow between import and publish.
- Added security finding storage with severity, source, recommendation and review state.
- Added final deterministic leak scan and verification hash before publication.
- Added manual protection rules for path hiding, exact-string redaction and regex redaction.
- Added audit logging for repository, snapshot, finding, rule and publish events.
- Added optional external AI review provider architecture; external AI remains disabled by default.
- Added OpenAI Responses API provider with structured security-review output and prompt-injection isolation.
- Expanded secret detection for private keys, GitHub, AWS, Google, Stripe, Slack, JWT, DB URLs and bearer credentials.
- Added protection profiles and repository review screens.
- Added plugin upgrade migrations for the expanded schema.
- Preserved the hard boundary: original GitHub source is processed in memory only; only sanitized snapshot content is persisted.

## 0.1.0

- Initial secure showcase foundation.
- Standalone WordPress rendering, shortcode and Elementor widget.
- Public GitHub repository import.
- Separate public snapshot storage.
- Basic secret scanner and built-in path blocking.
- German and English localization foundation.
