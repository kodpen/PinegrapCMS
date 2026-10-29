---
name: kodpen-vendor-api-client-design-update
description: Update contract of plans/kodpen-vendor-api-client.md: release check, pin, checks before extraction, download failures and security events, beta builds, keys, P2's workflow
---

## How is an update found (GET /releases/latest?channel=&from=)?
- A 200 failing `ReleaseEnvelope` is unusable. `release.blocked`: stepping-stone message. Another channel: refused. An update when `version_compare(v, VERSION, '>')`, or on beta the same v with another build (Q1). Repair: older refused, equal repairs, newer updates.
- No update: a 200 that is not one, 404 `release_not_found`, 403 `access_blocked` (own message). Unusable: anything else; the screens keep "No access to" / "No response from the update server" with `pg_curl_tls_hint()`.
- Scheduled check: an update or blocked sets the flag and notification; no update clears them; unusable keeps today's error path.
- Parsers map the answer's `release.artifacts.update` and the manifest's one `artifacts` entry of kind `update` (schema 1; none or two: `manifest_mismatch`) to the vectors' flat `update`; tests feed real-shaped bodies too.

## What does the pin hold?
- Session `software.update`: mode, version, channel, build, package URL, size, sha256, `manifest_sha256`, `sig_key_id`, manifest and signature URLs, package file name. Later steps read only it; replace clears it.
- Manifest, signature and package URLs by `parse_url()`: https, the base's host (lower case) and effective port (443 when absent); user info, a backslash, a blank or a control byte: refused.

## What runs before pg_extract_archive()?
- The vector steps in order, then backup, then replace. `pg_vendor_update_steps()` judges "newer" only at zip_version; the key map is a parameter.
- Caps in a write callback, whatever libcurl or Content-Length says: 64 KB answers and manifest, 4 KB signature, the pinned size for the package.
- Signature: blanks stripped, strict base64, only `openssl_verify() === 1` passes. In-ZIP version: `pg_zip_entry_contents()`, `pg_upgrade_versions_from_source()`; repair accepts an equal one.
- Package: `data/temp/vendor_update_<16 hex>.zip`, opened with `x`; a run deletes only its own file, after replace or a refusal. Replace checks size, sha256 (`hash_equals`) and in-ZIP version again right before extraction.
- A cURL error, a short body or a failed write is a download failure: file deleted, today's "cut short" or "disk may be full" text, no security log. A complete transfer with another sha256, a bad signature or a manifest mismatch is a security event, logged. No automatic retry, no other source; the operator may start again from a new check.

## What changes for beta builds?
- `data/update_build.json`: `{version, build, git_ref}` of the verified manifest; it counts only when `version` is `pg_code_version()`, else build '' and no `git_ref`.
- After replace: for another build of the same version `config.version` goes first to the previous `versions.php` entry, the write checked (failure: an error naming Repair Software); then the build file is written atomically; the upgrade runs. On beta the integrity check tries `git_ref` first.
- Backup `manifest.json` carries `build`; rollback restores the file, or deletes it when the backup has none.

## Which keys verify?
- `pg_vendor_release_keys()`: key id => public PEM, two, in the code, never from config; changed only by a release (a key is retired by a release signed with the other). Test pairs are made at test time, never committed.

## What does P2's workflow do?
- On `release: published` it stops before building when the key-id repository variable is not `^[a-z0-9][a-z0-9._-]{0,15}$`, or tag form (`v<version>`, `v<version>-beta.<stamp>`), pre-release flag and built version disagree. It runs the vendor tool (copied byte for byte with its header under `.github/release/`, PHP 8.3) and `gh release upload`s both ZIPs and `manifest.json`, never `--clobber`; `contents: write` for that job only.
