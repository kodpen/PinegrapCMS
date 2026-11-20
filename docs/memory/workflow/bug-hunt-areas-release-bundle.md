---
name: bug-hunt-areas-release-bundle
description: Bug hunt of publishing and taking back a release bundle (agent channel message 10241, area a, #10614): five faults fixed, what was read clean
module: page_bundles
---

## Which area was read?
- Architect (2026-11-20, agent channel message 10241, area a): publishing a release bundle and taking it back (plan release-bundle RB1-RB8, closed by #10559): `includes/fn/page_bundles.php`, `includes/fn/page_bundles_screen.php`, `page_bundles.php`, `includes/api/resources/design_bundles.php`. Not touched: `prototype.php`, `includes/fn/prototype_links.php`, `style_designer.src.js`.

## What did it fix? (#10614)
- A due bundle whose lock was held when the job came stayed scheduled with no time, never published (`release-bundle-time.md`).
- "Publish the rest" left the drafts of the replacements the partial run wrote in Drafts (`release-bundle-publish.md`).
- A failed query during Take back ended the request with the other pages untried (`release-bundle-publish.md`).
- A page added during a publish stayed waiting in a published bundle: adding now takes the lock.
- A bundle deleted while an action waited was answered "being published right now", 409 `bundle_busy` over the API; now "not found", 404 (`api-design-bundles.md`).

## What was read and found clean?
- The checks before any write (plan, locks, dry run, home page), the claim of an item (`waiting` to `written` in the WHERE), the failure taken back newest first, the dead publish's finish, the "changed since" choice (a change after the dialog asks again, nothing written), `kept[]` on take-back, the hold of a bundled draft (`release_bundle_held`), CSRF (`validate_token_field()` and the dialogs' token) and rights (designer area, roles 0-1 in the API and the job).

## What was left as it is?
- Rename and the publish time are set without the bundle's lock; a time set while the job's publish fails is cleared by the job's reopen. Rare, not changed.
