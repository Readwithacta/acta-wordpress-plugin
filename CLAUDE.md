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

## 5. Release to WordPress.org (manual SVN)

Still manual, because CI produces the WP.org zip with the **wrong filenames** —
`acta-content.php` inside `acta-content-wporg/`. WordPress.org rejects that
(`nonstandard_main_filename`); the main file must equal the slug.

Working copy: `../../acta-pay-per-article` (sibling of `acta-project`).

```bash
cd /Users/guillermoolaizola/Documents/Acta/Development/acta-pay-per-article && svn up
```

Then:

1. Download `acta-content-wporg.zip` from the GitHub release and unzip it.
2. Rename the main file `acta-content.php` → `acta-pay-per-article.php`.
3. Copy the contents into `trunk/`, replacing what is there.
4. Set `Version:` + `ACTA_PLUGIN_VERSION` in the PHP file and `Stable tag:` in
   `trunk/readme.txt` to the **semver** number (see versioning below) — they must
   match the tag you are about to create.
5. Tag and commit:

```bash
svn cp trunk tags/<version> && svn ci -m "Release <version>"
```

**Never put banners, icons or `screenshot-*.png` in `trunk`.** They live in the SVN
`assets/` directory only, and including them in the plugin gets the release flagged.

`readme.txt` drives the public WordPress.org page — `Stable tag`, `Requires at
least`, `Tested up to` and the `== Changelog ==` section all render from it.

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

## 8. State as of 2026-08-06

- Production `acta-content.php` and SVN `trunk/` are both **4.1.0** and in sync
  (verified: zero diff after the strip pass).
- SVN tags: `4.0.0`, `4.1.0`. Working copy clean.
- Next WordPress.org release would be `4.2.0`.
