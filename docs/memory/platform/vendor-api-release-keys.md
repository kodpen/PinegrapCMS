---
name: vendor-api-release-keys
description: The two release keys a signed update is verified with (PR #10348, plan step P5c): ids kp-2026a and kp-2026b, where they live, their fingerprints, how one is retired, the test
---

## Which keys verify a release?
- `pg_vendor_release_keys()` (`includes/fn/update.php`): key id => ECDSA P-256 public key PEM. `kp-2026a` signs releases; `kp-2026b` is the spare, kept offline.
- The release answer names the signing key in `sig_key_id`, which the check step pins (`vendor-api-update.md`); the manifest's `key_id` (`release/release-packages.md`) is not read. The signature step refuses an id not in the list as `unknown_key_id`, a security event (`vendor-api-update-download.md`).

## Where do they live and how do they change?
- In the code only, never from config or the database; a key changes only with a release. A key is retired by a release signed with the other one that drops it.
- Before #10348 the list was empty, so every offered release was refused at `unknown_key_id`.

## What are their fingerprints?
- SHA-256 of the PEM text with its final newline: `kp-2026a` `9bf2cc54b9f9082e31b53473ad7a83c70828b12d22990b48a8170ff07973f260`, `kp-2026b` `045efb2b879b7d7937e03cabbaee9677299b6f2924d8bf20a1e88da40c0f46ea`.
- Production's keys must have the same fingerprints before the release (`plans/kodpen-vendor-api-client.md`).

## Tests?
- `tests/vendor_update_steps_test.php` `test_vendor_update_release_keys`: the two ids, each an id the release check reads; EC on prime256v1; the fingerprints above, so an edit to a key fails. Test signing pairs are made at test time, never committed.
