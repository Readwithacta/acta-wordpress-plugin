## v4.0.0 -- 2026-03-04

### Changes
- Enhance acta-content.php with Base64 SVG menu icon definition, update sanitization to use wp_unslash() for POST data, and improve admin notice strings with i18n wrappers. Add agent guidelines in AGENTS.md and update readme.txt for external services disclosure and versioning.
- Update release assets configuration in acta-content.php to specify ZIP file pattern for auto-updates
- Delete acta-content.v1.1.0-checkpoint.php

## v3.0.1 -- 2026-03-04

### Changes
- Refactor custom price snippet display in acta-content and acta-content-dev files for improved layout and styling consistency

## v3.0.0 -- 2026-03-04

### Changes
- Automate releases via commit message keywords/[minor]/[major]
- Enhance copy button functionality in acta-content and acta-content-dev files with improved styling and feedback on copy action
- Remove emojis from scripts; add unpushed-commits guard to release.sh

## v2.0.2 — 2026-03-03

### Changes
-

# Changelog

All notable changes to the Acta WordPress Plugin.

## v2.0.1 — 2026-03-02

### Added
- Auto-update mechanism via GitHub Releases
- Forced silent background updates (no publisher action required)
- Plugin Update Checker library bundled

## v2.0.0 — 2026-02-01

### Added
- Self-service onboarding — publishers connect without manual API setup
- Auto-detection of existing paywall heading and button styles
- Custom per-article pricing via `<script id="acta-price" data-price="X.XX">`
- Default Jetpack paywall container class auto-set
- JS snippet auto-injected via `wp_head` hook

### Changed
- Plugin moved to top-level WordPress admin sidebar menu
- Replaced technical endpoint/backend details with actionable pricing instructions

## v1.1.0 — 2025-12-01

### Added
- Initial public release
- Ghost and WordPress CMS support
- Stripe Connect integration
