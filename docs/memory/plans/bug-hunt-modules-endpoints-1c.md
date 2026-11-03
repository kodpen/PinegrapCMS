---
name: bug-hunt-modules-endpoints-1c
description: Module list of plans/bug-hunt.md, part 3c: root endpoint groups mail, manifest and marketplace, and contact's add and edit screens, one line each with its state
---

## Which root endpoint groups (mail to marketplace, contact add/edit) are hunted?
- Endpoints `contact`, add_contact and edit_contact: done #PR (edit_contact checked a role 3 user's access on `$_REQUEST['id']` and deleted or saved `$_POST['id']`: core/record-gate-request-id.md); add_contact clean (CSRF, group check, e-mail, affiliate code, send_to by pg_safe_redirect_path())
- Endpoints `mail` (3): clean (mail_job web gate, mail_queue manager gate, CSRF, retry/delete and its list actions' role and token checks, escaping; mail_webhook key by hash_equals, size limit, signature, SNS confirm host)
- Endpoints `manifest` (2): done #6100 (manifest: start_url, scope, id and shortcuts on the setting's host were cross-origin on an alias host); manifest_icon clean (size whitelist, stored names are ASCII)
- Endpoints `marketplace` (3): clean (marketplace_settings: manager and e-commerce gates, CSRF, posted lists refused, account-scoped map rows, claim and question ids taken from the read, escaping; marketplace_products: JSON token check, account-scoped rows, DOM text in the script; marketplace_webhook)
