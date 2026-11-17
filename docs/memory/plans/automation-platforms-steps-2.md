---
name: automation-platforms-steps-2
description: Plan automation-platforms, steps AU8 to AU10 and the order of all ten: the panel's sheet for the owner, the panel build (starting point, subscription form, sample, guide), the pages and close-out
---

## Which steps are there?
- AU8 (S, the sheet) The panel's new parts, drawn before they are built.
  - One sheet at 1440, 1024 and 390, light and dark, the longest Turkish labels: New application with the starting point, the sheet with the three values, the Events tab with "New subscription" open and its event list searched, "Send a sample".
  - Posted in the agent channel; the architect brings it to the owner as one question.
  - Features: A10, A11, A12, A13 as drawn.
  - Being drawn; the pictures go to the agent channel for the architect.

- AU9 (M) after AU8's yes, AU3 and AU4: the panel.
  - Existing files: `api_settings.php` (New application, the Events tab, the webhook card, the guides), `includes/api/resources/webhooks.php`: the checks of `api_webhooks_create()` move into one function both callers use.
  - Tests: the starting point ticks the contract's scopes; the form's refusals are the route's; a locked row shows neither the form nor the button.
  - Features: A10, A11, A12, A13.

- AU10 (S) after AU5, AU6 and AU9: the events section of both documentation pages shows fields and sample (`includes/api/console_view.php`); the lasting rules into `api/`. The plan's files stay for AX1 to AX4.

## What waits for what?
- Waiting for nothing: AU1, AU3, AU4, AU7, AU8. AU2 waits for AU1; AU5 and AU6 for AU4; AU9 for AU8's yes, AU3 and AU4; AU10 for AU5, AU6 and AU9.
- Two steps that edit the same file are not open together: AU4 to AU6 and AU9 on `includes/api/resources/webhooks.php`; AU10 and plan `api-clean-modern`'s AM12 on `includes/api/console_view.php`.
- Suggested beginning: AU1, AU4 and AU8 side by side.
