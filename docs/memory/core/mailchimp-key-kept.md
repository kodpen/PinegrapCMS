---
name: mailchimp-key-kept
description: Mailchimp API key on the Communication settings card: never printed, empty box keeps it, remove tick, data center checked as a plain host label before any request
module: mailchimp_key
---

## Is the stored Mailchimp key shown on the settings card?
- No. `includes/settings/contact.php` draws the box empty; `prep.php` only sets `$mailchimp_key_stored`. With a key stored the box says "Leave blank to keep the stored key" and a "Remove the stored key" tick is drawn.
- `contact.save.php` takes the key from `pg_mailchimp_key_for_save()` (`includes/fn/mailchimp_key.php`): a typed key replaces, an empty box keeps `config.mailchimp_key`, the tick clears it. With sync on and no key left, "API Key is required".
- Before: the key (full access to the Mailchimp account) was printed into `value=` for every manager who opened the card.

## Why is the data center checked?
- The request host is `https://<dc>.api.mailchimp.com`, `<dc>` being what follows the dash in the key. It was `substr()` after the first dash, so a key like `abc-evil.example/x?` sent the key with Basic auth (and, through the sync job, the store's customers and orders) to another host.
- `pg_mailchimp_dc()` returns the lower-case letters + digits after the last dash, or `''`. The save refuses a key without one ("The API key must end with its data center, like -us6."); `mailchimp_request()` answers an error without sending when `$dc` is `''` (also for a stored key from before). Tests: `tests/mailchimp_key_test.php`.

Related: docs/memory/core/security-helpers-and-secret-storage.md
