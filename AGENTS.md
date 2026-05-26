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

- `acta-content.php` — **production plugin entry point** and the single source of truth for plugin behavior. Feeds BOTH the direct-distribution build and the WordPress.org build (see Distribution below). Any feature change must land here.
- `acta-content-dev.php` — **dev/test plugin** (slug `acta-content-dev`, points at `api.develop.readwithacta.com`, Stripe test keys). Runs side-by-side with production for manual testing via `./build-dev.sh`. Mirror feature changes here so they can be tested, but this file never ships to publishers.
- `acta-content-wporg.php`, `readme-wporg.txt` — **build artifacts, do not hand-edit.** Regenerated at release time by `release.yml` (the `@wporg-strip` awk pass on `acta-content.php` / the readme). The committed copies are stale and only vestigial.
- `lib/` — vendored libraries. Do not modify files inside `lib/` directly.
- `.github/workflows/` — CI release automation. Do not modify without explicit instruction; changes here affect versioning and asset publishing.
- `CHANGELOG.md` — manually maintained release notes. Update when making notable changes.
- Release notes on GitHub are auto-generated from commit messages by the CI workflow.

---

## Distribution channels

This plugin ships through three separate channels — a feature is only "everywhere" when `acta-content.php` (and, for testing, `acta-content-dev.php`) is updated:

1. **WordPress.org directory** — slug **`acta-pay-per-article`** (APPROVED; permalink is locked and cannot be changed). This is what most publishers install from their WP admin / WordPress.com. Built from `acta-content.php` via the `@wporg-strip` pass, distributed via **SVN** (a release system — only push ready versions).
2. **Direct distribution / Acta portal** — `acta-content.zip` from a GitHub release; installed plugins auto-update via the Plugin Update Checker hitting `api.readwithacta.com`. Publishers can also request this zip via the Acta portal.
3. **Dev/test** — `acta-content-dev.zip`, uploaded manually to a test site.

---

## WordPress.org submission requirements (learned during review)

The directory review enforces these — get them right or the SVN push / resubmission is rejected:

- **Main file name must equal the slug:** `acta-pay-per-article.php` (NOT `acta-content.php`). WP.org flags `nonstandard_main_filename` otherwise. The release build must rename the entry file accordingly.
- **Zip name must be `acta-pay-per-article.zip`** (NOT `acta-content-wporg.zip`).
- **Do NOT include directory assets** (banners, icons, `screenshot-*.png`) in the plugin zip — they are uploaded separately to the SVN `/assets/` folder after approval.
- **`readme.txt` drives the public WP.org page** (Stable tag, Requires at least, Tested up to, Changelog).
- **Test on a clean WP install with `WP_DEBUG` true** before submitting — no notices/warnings.
- Updates after approval are pushed via **SVN tags**, not the "Add your plugin" form.

> NOTE: the current `release.yml` still outputs `acta-content`-named artifacts for the WP.org build. Until that's aligned, the WP.org SVN release requires a manual rename to `acta-pay-per-article.php` / `acta-pay-per-article.zip` (as was done for the initial submission).

### How to release an update to WordPress.org (step by step)

The plugin is **approved** (slug `acta-pay-per-article`). Updates go out via **SVN**, not the submission form. To ship a change:

1. Make the change in **`acta-content.php`** (the single source). Mirror it into `acta-content-dev.php` for testing.
2. Bump the version in `acta-content.php` (the `Version:` header + `ACTA_PLUGIN_VERSION`) and the `Stable tag` in `readme.txt`.
3. Build the WP.org package = a folder `acta-pay-per-article/` containing:
   - `acta-pay-per-article.php` — `acta-content.php` with the `@wporg-strip-start … @wporg-strip-end` blocks removed, renamed to match the slug.
   - `readme.txt` — with the update-check line removed.
   - `uninstall.php`, and the functional `assets/` (`acta-logo.*`, `js/`).
   - **Exclude:** `acta-content-dev.php`, build scripts, `AGENTS.md`/`CHANGELOG.md`/`README.md`, and any screenshot/banner/icon images (those live only in the SVN `assets/` dir).
   Zip it to `acta-pay-per-article.zip`.
4. Test the zip on a clean WP install with `WP_DEBUG` on — no notices/warnings.
5. SVN: `cp` the package into `trunk/`, then `cp` `trunk` → `tags/<version>/`, then `svn ci`. Plugin banners/icons/screenshots go in the SVN `assets/` dir, never in `trunk`.

> The backend (`api.readwithacta.com`) must already have the matching API (model fields, endpoints) deployed, or the plugin's runtime calls fail.

### WordPress 7.0 compliance checklist
- `Requires PHP: 7.4` and `Tested up to: 7.0` in `readme.txt`.
- `wp_remote_post` / `wp_remote_get` calls work under the updated Requests library — smoke-test on a 7.0 install.

---

## Testing

There is no automated test suite. Verify changes manually against a local WordPress install before committing. When modifying the update-checker integration, confirm behavior against a real GitHub release.

---

## What Not to Do

- Do not push to GitHub or publish releases.
- Do not create accounts, change file-sharing permissions, or submit forms on behalf of the developer.
- Do not add dependencies without verifying their license is GPL-compatible.
- Do not store sensitive data (API keys, secrets) in plugin files or committed code.
