---
name: cloud-file-picker
description: Upload File's cloud picker (#3238): Google Picker and Dropbox Chooser hand back only an id or link, the server downloads within the upload limit and stores through pg_file_store_upload()
module: cloud_file_picker
---

## What does it do?
- On `add_file.php` (Upload File), next to Select File(s): "Pick from Google Drive" / "Pick from Dropbox". Decided in #3180 (owner chose it; architect: download on the server, the normal upload rules, no schema).
- The browser (`assets/js/cloud_file_picker.js`, bundle `cloud_file_picker.min.js`) opens the provider's picker and posts only `cloud_provider` + `cloud_choice` (Google: Drive file id; Dropbox: the Chooser's direct link, name, bytes) with the upload form's folder, description and design fields to `add_file.php?jsonreturn=true`. The answer is the usual JSON (`files`, `failed`).

## When is a provider shown?
- Google: the Google app on Connected accounts is ready, has the option `picker_api_key` (a browser API key with the Picker API) and a client ID carrying the project number (`pg_cloud_picker_google_app_id()`), and the person has an active connection for purpose `file_picker`. App ready but not connected: a "Connect your Google Drive account" button (submit with `form=`, the hidden form after the upload form, return `add_file.php?picker_connect=1`).
- Dropbox: the Dropbox app is ready (its app key is the Chooser's `data-app-key`); no account needed. The site's domain must be in the app's Chooser/Saver domains.
- Nothing set up: nothing shown (`pg_cloud_picker_choices()`).

## Why per person?
- Purpose `includes/connect/purposes/file_picker.php` is `owner => person`, Google `drive.file` only: add_file.php is open to role 3 (folder rights), and the site's account would show a role-3 user the site owner's whole Drive in the Picker.
- The Picker needs the person's token in the browser: `cloud_action=token` (POST, form token, JSON) answers it on each open.

## How is the file taken?
- `pg_cloud_picker_source()` maps the choice: Google id `^[A-Za-z0-9_-]{10,200}$` → `drive/v3/files/<id>` metadata (name, mimeType, size) then `?alt=media`, with the token (401 renews once). Google Docs/Sheets/Slides (`application/vnd.google-apps.*`) are refused: no bytes. Dropbox: https on `dl.dropboxusercontent.com` (or a subdomain of it) only, no user or port, no token.
- Size known up front over `pg_upload_limits()['file_max']` → refused before any fetch. The download streams into `tempnam(sys_get_temp_dir())` through `api_http_request()`'s `write` option (`pg_cloud_picker_write()` stops at the limit), so the private-address guard, pinning and TLS rules apply.
- The temp file goes to `pg_file_store_upload()` (`includes/fn/files.php`), the function add_file.php's browser files use too: blocked names, the single-file limit, `prepare_file_name()`, `get_unique_name()`, the product image profile, the row, audit, `file.created`. It returns refusals; the caller logs them (so the rules run in tests without a database).
- The cloud branch checks the form token and the folder right (`check_edit_access()`) in JSON before downloading.

## What is not there?
- OneDrive: the next step (plan `reverse-oauth-leftovers.md`): the v8 picker needs a token for the OneDrive/SharePoint resource, not Graph, and differs for personal and work accounts.
- A site enforcing its own CSP must allow `apis.google.com`, `docs.google.com` (frame) and `www.dropbox.com`; the default policy is report-only.
- Tests: `tests/cloud_file_picker_test.php`.
