## v4.2.0 -- 2026-08-06

### Changes
- Add a **Disconnect Acta** button to the settings page. Turns off the Acta button on the site without deleting the plugin, and tells Acta so the paywall stops being served and no further purchases are taken. The publisher account, Stripe details and any balance owed are kept, so reconnecting is a single click.
- Notify Acta on uninstall. Deleting the plugin now tells the backend before the local settings are removed, so a publisher who leaves is no longer silently treated as active. Best-effort and non-blocking — uninstall is never held up by a network problem.
- Stop enqueuing the frontend script while disconnected.

## v4.1.0 -- 2026-05-26

### Changes
- Add "Paywall mode" setting: choose between adding pay-per-article alongside an existing subscription paywall, or "pay-per-article only" which replaces the subscribe gate with just the Acta buy button (for publishers who don't sell subscriptions). The setting is saved to the Acta backend on the publisher record and fetched by the embed script at runtime from a no-store config endpoint — so it is never stored in cacheable page HTML and a toggle takes effect on the next page load without any page-cache / CDN purge.
- Add a "How Acta works" explainer to the settings page clarifying that Acta mounts onto an existing paywall (Jetpack recommended) rather than being a paywall itself, with guidance for publishers who don't have one yet (including Elementor).
- Bump "Tested up to" to WordPress 7.0.

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
