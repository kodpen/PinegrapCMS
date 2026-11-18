---
name: release-packages
description: Release packages (P2, PR #PRNUM): release-packages.yml adds both ZIPs and manifest.json to a published release with the vendor's tool; checks before the build, RELEASE_KEY_ID, replacing the tool copy
---

## What does a published release get?
- `.github/workflows/release-packages.yml` adds three assets: `pinegrap_software_update.zip` (the panel's update), `pinegrap_software.zip` (a new install), `manifest.json` (schema 1: version, channel, a beta's build, `git_ref` = the tag, commit, size and SHA-256 of both, `key_id`). The owner signs the manifest offline later (`manifest.json.sig`); an unsigned release is offered to no site.
- Stable: `v<version>` on the release merge commit (`release-status.md`). Beta: a pre-release `v<version>-beta.<YYYYMMDDHHMM>` (UTC) on a `development` commit, build id `<sha7>.<stamp>`, so every pre-release is a build of its own.

## What stops the run before the build?
- Tag form and pre-release flag disagree, or the stamp is no real date; the tag's tree ends on another version (`versions.php`, read with the tool's own function); a beta of a version that has a stable release (open the next version first); a stable tag whose tree holds `CLAUDE.md`, `.claude/`, `docs/`, `tools/` or `tests/`; `RELEASE_KEY_ID` not `^[a-z0-9][a-z0-9._-]{0,15}$`; a tag that no longer names the checked-out commit.
- A release with all three assets ends green without changes; one with some fails and names them (delete them, re-run). Uploads never `--clobber`; the manifest goes last.

## Where does the recipe come from?
- `.github/release/build_packages.php` is the vendor's package tool, byte for byte, its Kodpen header included. The workflow checks its SHA-256 first (`TOOL_SHA256`, today `3ba6a1bf…c507`); `.github/release/.gitattributes` (`-text`) keeps its bytes on every checkout.
- Never edit the copy: a recipe change comes from the vendor as a new file, copied with its new SHA-256 in one PR, before the next release.
- It parses on PHP 7.1 and answers 403 to a web request (`.github/` is in the full package); it runs on PHP 8.0+ with git and the zip extension (the workflow: 8.3).
- `php .github/release/build_packages.php --repo=. --ref=v2026.4.10 --channel=stable --out=<folder>` gives 3,787 files (30,445,419 bytes) in the update and 4,408 (77,800,043) in the full package; other counts mean the recipe or the checkout changed.

## What does it need on GitHub?
- The repository variable `RELEASE_KEY_ID` = `kp-2026a` (Settings › Secrets and variables › Actions › Variables) before the first release; without it every run stops.
- Immutable releases stay off: the assets are added after publishing.
- `contents: write` only for the upload job; the build runs read-only, in `kodpen`'s repository only.
- A release runs the workflow of its tagged commit: the first beta needs a `development` commit that holds it. A pull request changing the workflow or the tool builds its own commit (beta, no key id) and uploads nothing.

## Tests?
- The 2026.4.10 build above, byte-equal to a build with the vendor's own file; a beta build of `development` with none of the five paths and `docker/` only in the full package; actionlint in CI. The first live run is the first release after P2.
