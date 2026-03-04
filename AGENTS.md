# Agent Guidelines

Guidelines for AI coding assistants working in this repository.

---

## Git & GitHub

- **Never push to GitHub.** All git operations stay local. Commit, stage, and prepare as needed — always stop short of `git push`. The developer reviews and pushes manually.
- Do not amend existing commits. Create new commits for follow-up changes.
- Do not force-push, hard reset, or run any destructive git operation unless explicitly instructed.
- Stage specific files by name; avoid `git add -A` or `git add .`.
- Do not create or publish GitHub Releases directly.

### Branch workflow

- **`develop`** — active development branch. Push freely here; nothing is triggered.
- **`main`** — release branch. Every push to `main` triggers a release automatically.

To release:
```bash
git push origin develop:main
```

That's the only release command. No keywords, no version numbers, no manual steps.

### How versioning works

The CI workflow generates a **CalVer** version from the UTC build timestamp (e.g. `2026.03.04.1423`) and stamps it into the plugin files and `readme.txt` **during the build only** — it is never committed back to the repo. This means:

- Your local files always stay clean — no bot commits, no rebasing required.
- The released ZIPs contain the correct version.
- `readme.txt` in the source will show a slightly older `Stable tag`; that's expected.

---

## WordPress Coding Standards

Follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) at all times.

- **Indentation:** Tabs, not spaces.
- **Naming:** `snake_case` for functions, variables, and hooks. `PascalCase` for classes.
- **Yoda conditions:** `if ( true === $value )`, not `if ( $value === true )`.
- **Arrays:** Use `array()` syntax, not short `[]` notation.
- **Unique prefixes:** All functions, hooks, option keys, and global variables must use the plugin's unique prefix to avoid conflicts with other plugins.
- **No closing `?>`** at the end of PHP files.
- **No trailing whitespace.**
- All user-facing strings must be wrapped in `__()`, `esc_html__()`, or equivalent i18n functions with the correct text domain.

---

## Security (WordPress.org Requirements)

These rules are mandatory for WordPress.org submission and must never be skipped.

### Sanitize Input
- Sanitize all data received from users or external sources before using it.
- Use WordPress built-in functions: `sanitize_text_field()`, `sanitize_email()`, `absint()`, `wp_kses_post()`, etc.
- Always call `wp_unslash()` before sanitizing `$_POST` / `$_GET` data.

```php
// Correct
$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
```

### Escape Output
- Escape all output, even data that came from the database.
- Use the most specific escaping function for the context:
  - `esc_html()` — inside HTML tags
  - `esc_attr()` — inside HTML attributes
  - `esc_url()` — for `href` and `src` values
  - `esc_js()` — for inline JavaScript
  - `wp_kses_post()` — for HTML with allowed markup
- Escape as late as possible — at the point of output, not earlier.

```php
// Correct
echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
```

### Validate Nonces
- All forms and AJAX handlers that change state must include a nonce.
- Verify the nonce before processing anything.

```php
check_admin_referer( 'my_action_nonce' );
// or for AJAX:
check_ajax_referer( 'my_action_nonce', 'nonce' );
```

### Check Capabilities
- Always verify the current user has permission before performing sensitive operations.

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to do this.', 'text-domain' ) );
}
```

### Database Queries
- All custom database queries must use `$wpdb->prepare()` with proper placeholders (`%d`, `%s`, `%f`).
- Never interpolate variables directly into SQL strings.

```php
$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}my_table WHERE id = %d", $id ) );
```

---

## WordPress.org Plugin Requirements

The following rules apply directly to WordPress.org submission compliance.

- **GPL-compatible license only.** All code, libraries, and assets must be GPL 2.0+ or a compatible license. Verify before adding any third-party dependency.
- **No obfuscated or minified-without-source code.** Source must be human-readable or linked to a public development repository.
- **No remote code execution.** Do not load or evaluate code from external URLs at runtime.
- **No unauthorized data collection.** Any external service calls require explicit user opt-in. All external services must be documented in `readme.txt`.
- **No forced attribution.** Any "Powered by" or credit links must be opt-in and off by default.
- **Admin notices must be dismissible.** Do not display persistent, non-dismissible admin notices.
- **Scripts and styles must be enqueued properly** via `wp_enqueue_scripts()` / `wp_enqueue_style()`. Never inline or hardcode them into templates.
- **readme.txt must follow the WordPress.org format** with a valid `Stable tag`, `Requires at least`, `Tested up to`, and a `== Changelog ==` section.

---

## File Conventions

- `acta-content.php` — production plugin entry point.
- `lib/` — vendored libraries. Do not modify files inside `lib/` directly.
- `.github/workflows/` — CI release automation. Do not modify without explicit instruction; changes here affect versioning and asset publishing.
- `CHANGELOG.md` — manually maintained release notes. Update when making notable changes.
- Release notes on GitHub are auto-generated from commit messages by the CI workflow.

---

## Testing

There is no automated test suite. Verify changes manually against a local WordPress install before committing. When modifying the update-checker integration, confirm behavior against a real GitHub release.

---

## What Not to Do

- Do not push to GitHub or publish releases.
- Do not create accounts, change file-sharing permissions, or submit forms on behalf of the developer.
- Do not add dependencies without verifying their license is GPL-compatible.
- Do not store sensitive data (API keys, secrets) in plugin files or committed code.
