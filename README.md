# YouGitAI Secure Showcase

**AI-assisted secure code showcases for WordPress — scan, redact and publish GitHub repositories without exposing sensitive IP.**

YouGitAI Secure Showcase is a WordPress plugin that turns public or private GitHub repositories into employer-friendly portfolio showcases. It combines deterministic secret detection, AI-assisted IP review, manual protection rules, versioned public snapshots and a constrained ChatGPT/MCP review workflow.

The goal is simple: **show what you built without turning your portfolio into a blueprint for cloning it.**

## Highlights

- GitHub OAuth support for public and private repositories
- deterministic secret scanning
- AI-assisted portfolio/IP review
- manual path, string, regex and symbol-oriented protection rules
- protected files and folders remain visible as architectural context without exposing their contents
- visible inline redaction markers in public code views
- versioned draft/public snapshots
- human-controlled publication workflow
- ChatGPT/MCP integration with OAuth
- OpenAI, Anthropic Claude and Google Gemini support
- standalone WordPress frontend
- native Elementor integration
- German and English localization

## Portfolio-aware protection

YouGitAI is designed to distinguish between useful technical evidence and material that should not be public.

Typical review classes include:

| Class | Typical handling |
| --- | --- |
| Safe portfolio signal | Show |
| Internal schema | Abstract / redact |
| Private endpoint | Redact |
| AI prompt | Redact / protect |
| Security implementation | Abstract / protect |
| Proprietary business logic | Abstract / protect |
| Core/reconstructable IP | Protect |
| Secret / credential | Remove |

## Safe publication model

1. Import repository content for private review
2. Detect potential risks
3. Review scanner/AI suggestions
4. Approve protection rules
5. Apply protection to a draft showcase
6. Verify the resulting snapshot
7. Publish only after explicit human approval

**Sync is not publish. AI review is not publish.**

AI/MCP clients do not receive direct publish or restore capabilities.

## Protected structure

Files or folders that must not be public can remain visible in the repository tree as **protected** entries. Visitors can understand that a component exists and where it belongs while the protected source itself is never served publicly.

## ChatGPT / MCP

YouGitAI exposes a constrained MCP interface for authorized repository review. It supports repository discovery, paginated tree inspection, selective source review, security findings and protection proposals. Publication remains under WordPress administrator control.

## WordPress / Elementor

The plugin works standalone and also provides Elementor widgets.

Typical public routes:

```text
/repositories/
/repositories/{repository-slug}/
```

## Security

The plugin is designed around a strict separation between original repository material and public showcase snapshots. Repository contents are treated as untrusted input, publication requires explicit human approval, and protected content is not returned through the public showcase API.

See [`SECURITY.md`](yougitai-secure-showcase/SECURITY.md).

## Installation

1. Download or build the plugin directory `yougitai-secure-showcase`.
2. Upload it to `/wp-content/plugins/` or install the release ZIP in WordPress.
3. Activate **YouGitAI Secure Showcase**.
4. Configure GitHub access and optional AI providers in the WordPress admin.
5. Import a repository, review protection suggestions and publish an approved showcase snapshot.

## Development status

YouGitAI is under active development. This repository contains the actual plugin source. Credentials, API keys and site-specific configuration are intentionally not included and are configured at runtime in WordPress.

## Tech

WordPress · PHP · JavaScript · GitHub OAuth · REST · MCP · OAuth 2.0 / PKCE · Elementor · OpenAI · Claude · Gemini

## Author

Built by **Sascha Haselhuhn / glanzlicht**.

Public showcase: https://sascha-haselhuhn.com/repositories/
