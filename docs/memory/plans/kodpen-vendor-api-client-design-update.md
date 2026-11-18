---
name: kodpen-vendor-api-client-design-update
description: Update contract of plans/kodpen-vendor-api-client.md still to build: P2's release workflow; the built check, pin, keys, download and beta builds are in platform/
---

## Where is the built part?
- Release check and pin: `platform/vendor-api-update.md`; the keys: `platform/vendor-api-release-keys.md`; checks before extraction, failures and security events: `platform/vendor-api-update-download.md`; beta builds: `platform/update-beta-builds.md`; calls, headers, licence: `platform/vendor-api-client.md`, `platform/license-check.md`.

## What does P2's workflow do?
- On `release: published` it stops before building when the key-id repository variable is not `^[a-z0-9][a-z0-9._-]{0,15}$`, or tag form (`v<version>`, `v<version>-beta.<stamp>`), pre-release flag and built version disagree. It runs the vendor tool (copied byte for byte with its header under `.github/release/`, PHP 8.3) and `gh release upload`s both ZIPs and `manifest.json`, never `--clobber`; `contents: write` for that job only.
