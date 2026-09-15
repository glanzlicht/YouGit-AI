# Changelog

## 1.0.25
- Protected files and folders remain visible as structural context while protected contents stay unavailable.
- Added localized protected-content views.
- Public REST responses expose protection state but never protected file contents.
- Repository overview heading and description can be customized separately for German and English.
- Public showcase statistics include protected structural entries.

## 1.0.24
- Improved dark-theme contrast for headings, metadata and CTA links.
- Added explicit hover, active, visited and keyboard-focus states.
- Hardened rewrite activation/update handling so routes are flushed only after current rules are registered.

## 1.0.23
- Added guided import → review → apply protection → publish workflow.
- Added protection rebuild from an existing safe draft without contacting GitHub.
- Added the standalone /repositories/ overview.

## Earlier milestones
Previous 1.0.x releases added OAuth-based ChatGPT/MCP integration, repository-scoped DIRECT access, paginated repository review, bulk protection proposals, visual redaction markers, protected snapshots, publication visibility modes, GitHub OAuth repository selection and multilingual WordPress/Elementor integration.

The 0.x series established strict source/public separation, deterministic secret scanning, encrypted credential storage, AI-assisted review, signed GitHub webhooks, versioned snapshots, fail-closed redaction rules, audit logging and human-controlled publication.
