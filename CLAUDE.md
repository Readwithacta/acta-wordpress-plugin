# CLAUDE.md — Release & Upload Runbook

Internal notes for shipping this plugin. **Never ships to publishers** — excluded
from every build in `.github/workflows/release.yml`. Keep it that way.

For coding standards, security rules and WordPress.org compliance, see `AGENTS.md`.
This file covers one thing: how to get a change out the door.

---

## 1. Which file do I edit?

This trips people up, so: **there is only one codebase.** The WordPress.org file is
a build artifact of the production file, not a separate plugin.

| File | What it is |
|---|---|
| `acta-content.php` | **Source of truth. Edit this.** Feeds every channel. |
| `acta-content-dev.php` | Dev twin — slug `acta-content-dev`, points at `api.develop.readwithacta.com`. Mirror changes here to test. Never ships. |
| `acta-pay-per-article.php` (SVN `trunk/`) | **Build output — never hand-edit.** It is `acta-content.php` with the `@wporg-strip` blocks removed and the file renamed to match the approved slug. |

Verify the relationship any time you doubt it — this should print zero differences:

```bash
awk '/@wporg-strip-start/{skip=1} !skip{print} /@wporg-strip-end/{skip=0}' acta-content.php | cat -s > /tmp/stripped.php && diff /tmp/stripped.php ../../acta-pay-per-article/trunk/acta-pay-per-article.php
```

The `@wporg-strip-start … @wporg-strip-end` blocks wrap the things WordPress.org
forbids: the self-update checker and debug mode. They exist in the source and are
stripped from the directory build.

Editing the SVN copy directly is the classic mistake — the next build regenerates
it from `acta-content.php` and your change vanishes silently.

---

## 2. Distribution channels

| Channel | Slug / artifact | How it updates |
|---|---|---|
| **WordPress.org directory** | `acta-pay-per-article` (approved, permalink locked) | Manual SVN push. Most publishers install from here. |
| **Direct distribution** | `acta-content.zip` | Automated. Plugin Update Checker polls `api.readwithacta.com`; publishers auto-update within ~12h. |
| **Dev/test** | `acta-content-dev.zip` | Manual upload to a test site via `./build-dev.sh`. |

A feature is only "everywhere" once `acta-content.php` is updated *and* both
channels have been released.

---

## 3. Test locally first

```bash
./build-dev.sh
```

Produces `acta-content-dev.zip`. Upload via **Plugins → Add New → Upload Plugin**.
It runs side-by-side with the production plugin and talks to the develop backend,
so it is safe to onboard, reset and re-onboard as often as you like.

The dev plugin has a red **Reset Installation** button the production plugin does
not. It clears the local options only — the same end state as a publisher deleting
the plugin — which makes it the fastest way to reproduce reinstall bugs.

Test on a clean WordPress install with `WP_DEBUG` on. No notices, no warnings.

---

## 4. Release to direct distribution (automated)

```bash
git push origin develop:main
```

That is the whole release. Every push to `main` triggers `release.yml`, which:

1. Generates a CalVer version from the UTC timestamp (e.g. `2026.08.06.1423`)
2. Stamps it into `acta-content.php`, `acta-content-dev.php` and `readme.txt`
   — **at build time only, never committed back**, so your working tree stays clean
3. Builds `acta-content.zip`, `acta-content-dev.zip`, `acta-content-wporg.zip`
4. Creates a GitHub release with auto-generated notes
5. POSTs to `api.readwithacta.com/api/v1/plugin-updates/acta-content` so installed
   plugins see the update

Because the version is stamped only at build time, `readme.txt` in the repo will
always show an older `Stable tag` than the latest release. That is expected.

> **Do not use `./release.sh`.** It is superseded. It does semver bumping, commits
> the version back and pushes tags — all of which CI overwrites with CalVer anyway.

---

## 5. Release to WordPress.org

Wait for the CI build from step 4 to finish, then:

```bash
./release-wporg.sh
```

That is the whole step. It reads the version from `acta-content.php`, downloads
the release zip, renames the main file to match the slug, undoes CI's CalVer
stamp, copies everything into the SVN working copy, commits trunk, and only then
creates the tag. It prints a summary and asks before touching WordPress.org.

The SVN checkout is expected at `../../acta-pay-per-article`; override with
`ACTA_SVN_DIR` if it lives elsewhere.

### Why the script exists

Doing this by hand broke the 4.2.0 release in three ways at once:

- `svn cp trunk tags/4.2.0` ran **before** trunk was updated, so the tag captured
  4.1.0's code
- the destination already existed, and `svn cp` nests instead of replacing —
  producing `tags/4.2.0/trunk/`
- `Stable tag` still said `4.1.0`, so WordPress.org kept serving the old version
  and no update ever appeared in publishers' dashboards

### What it checks before publishing

- the release zip matches your local `acta-content.php` (ignoring version stamps),
  so a stale or still-building release cannot be shipped
- no `screenshot-*` / `banner-*` / `icon-*` files in the package — including them
  got the original submission rejected
- `tags/<version>` does not already exist
- trunk actually changed; if nothing did it stops rather than tagging a no-op

### Manual fallback

If the script cannot run, the order is what matters: **update trunk → commit
trunk → copy the tag → commit the tag.** Never create the tag first, and never
copy onto a tag that already exists.

`readme.txt` drives the public WordPress.org page — `Stable tag`, `Requires at
least`, `Tested up to` and the `== Changelog ==` section all render from it.
Banners, icons and screenshots belong in the SVN `assets/` directory only, never
in `trunk`.
---

## 6. Versioning

Two schemes on purpose:

- **GitHub / direct distribution — CalVer**, generated by CI. Nothing to decide.
- **WordPress.org — semver**, set by hand in the SVN copy (`4.1.0`, `4.2.0`, …).

Keep bumping semver by hand for the directory. WordPress compares versions
numerically part by part, so a CalVer number would read as newer than `4.x` and
permanently switch the public listing to CalVer. Avoid that unless deliberate.

---

## 7. Before you release

- The backend (`api.readwithacta.com`) must already have any matching API — new
  model fields, new endpoints — deployed, or the plugin's runtime calls fail.
- Backend-only changes need **no plugin release at all**. Additive fields in API
  responses are ignored by older plugin builds.
- Update `CHANGELOG.md` — it is maintained by hand.
- Do not push to GitHub or publish releases on the developer's behalf.

---

## 8. Full release, start to finish

```bash
# 1. commit the change (versions already bumped in acta-content.php + readme.txt)
git add acta-content.php acta-content-dev.php readme.txt CHANGELOG.md && git commit -m "Release X.Y.Z: ..."

# 2. direct distribution — CI builds and publishes
git push origin develop:main

# 3. wait ~2 min for CI, then WordPress.org
./release-wporg.sh
```

## 9. State as of 2026-08-10

- `4.2.0` shipped to both channels. It added a "Disconnect Acta" button that was
  removed again in `4.2.1` — it could not edit any settings, which is what
  publishers reach for, so it was a confusing option that solved nothing. The
  uninstall notification from the same release was kept.
- The 4.2.0 WordPress.org release was published incorrectly and had to be redone;
  `release-wporg.sh` was written in response. See section 5.
- SVN tags: `4.0.0`, `4.1.0`, `4.2.0`.
