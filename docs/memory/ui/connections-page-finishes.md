---
name: connections-page-finishes
description: A Google or Microsoft account service's Connections page asks for the missing application itself; the drives give one answer; Microsoft's page has the sign-in part (#11894)
module: connections_screen
---

## What is it?
- Built in #11894, the rule's "The page finishes it" part (`settings-crossings-rule.md`; owner poll 232). Before it every account service sent the person on to Google's or Microsoft's page for the application.

## When does a service's page show the application?
- `pg_connections_screen_app_here($id, $service, $ready, $manager)` (pure, tested): a registry service whose state is `kind => account` of Google or Microsoft, on a page that is not the provider's own (not `google`, `microsoft`, `github`), while `pg_connect_app_ready()` is false, for a manager. Google Drive, OneDrive, Google Analytics, Merchant Center, Gmail, Microsoft 365 mail, the calendars, OneNote.
- Then `pg_connections_screen_page_cards()` gets `'app' => true` and puts the card `app` after Status's inside parts, before `details`.
- `connections_app_card()` draws "Application" (`#connections-app`) with `connections_app_form()`, the provider page's own form: `save_app`, the same storage, `connections_return` the service, so Save comes back here with `from=` kept. Once ready the card is gone and Status connects.
- Status says "Set up the application below first." with a link to it; Google Analytics' and Merchant Center's "set up the Google application first" link there too, not to Google's page.

## What is the drives' one answer?
- Google Drive's, OneDrive's and Dropbox's pages connect their account for backups in Status (`connections_backup_part()`); the sentence "Accounts are connected on the Google page…" (or Dropbox's panel) is no longer printed beside it. It stays on pages that connect nothing themselves (calendars, mail routes, OneNote) once the application is ready.
- The Backups card's "Set up the {var:1} application" (`pg_backup_cloud_app_url()`) opens the drive's own page at `#connections-app`; Dropbox still opens its drawer on the list.

## What does Sign in with Microsoft have now?
- The card's "Set up in Connections" opens `?service=microsoft&from=security%2Fpgset-signin#connections-signin`.
- Microsoft's page: `connections_signin_microsoft_part()` at the head of Connection details, `#connections-signin`, Ready or Not set up, a line that the application below serves it, and "Accounts and sign-in ›". It shows for who may open Accounts and sign-in (`signin` card, now Google's and Microsoft's pages). There is one Microsoft application; its form is headed "Application" under the part.

Tests: `tests/connections_page_finishes_test.php`.
