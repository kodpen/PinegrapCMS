---
name: automation-platforms-steps-2
description: Plan automation-platforms, steps AU8 to AU10 and the order of all ten: the panel's sheet for the owner, the panel build (starting point, subscription form, sample, guide), the pages and close-out
---

## Which steps are there?
- AU8 (S, the sheet) The panel's new parts, drawn before they are built.
  - One sheet at 1440, 1024 and 390, light and dark, the longest Turkish labels: New application with the starting point, the sheet with the three values, the Events tab with "New subscription" open and its event list searched, "Send a sample".
  - Posted in the agent channel; the architect brings it to the owner as one question.
  - Features: A10, A11, A12, A13 as drawn.
  - Drawn (PR #10126): agent channel message 7736, the legend, first pictures 7737 to 7746. Architect (2026-11-17, message 7749): the starting point is a second choice under "Bir entegrasyon", not a fourth kind; nothing about it is stored; the three values are on every integration's secret sheet, the automation note only after that starting point. Corrected pictures 7754 to 7763, the owner's overview 7753.
  - Owner (2026-11-17, agent channel poll 121, answer relayed in message 8068): "Evet; uygulamalar çıkana kadar ad yazmasın".
  - The four parts are built as drawn, but the line under "Otomasyon platformu" names no platform (architect, message 8071): "Bir otomasyon platformu siteye bu uygulamayla bağlanır. Platformun kullandığı izinler işaretlenir." / lang('An automation platform connects to the site with this application. The permissions the platform uses are ticked.')
  - No other product text of AU9 names a platform either (the note, "Yeni abonelik", "Örnek gönder"); an application name the administrator types may. The names come with AX1 to AX4.

- AU9 (M) after AU8's yes, AU3 and AU4: the panel.
  - Existing files: `api_settings.php` (New application, the Events tab, the webhook card, the guides), `includes/api/resources/webhooks.php`: the checks of `api_webhooks_create()` move into one function both callers use.
  - Tests: the starting point ticks the contract's scopes; the form's refusals are the route's; a locked row shows neither the form nor the button.
  - Features: A10, A11, A12, A13.
  - Done: PR #10225 (`api/application-access-automation.md`).

- AU10 (S) after AU5, AU6 and AU9: the events section of both documentation pages shows fields and sample (`includes/api/console_view.php`); the lasting rules into `api/`. The plan's files stay for AX1 to AX4.
  - Done: PR #10282 (`api/api-docs-webhook-events.md`).

- Done: AU8 #10126, AU9 #10225, AU10 #10282

## What waits for what?
- Waiting for nothing: AU1, AU3, AU4, AU7, AU8. AU2 waits for AU1; AU5 and AU6 for AU4; AU9 for AU8's yes, AU3 and AU4; AU10 for AU5, AU6 and AU9.
- Two steps that edit the same file are not open together: AU4 to AU6 and AU9 on `includes/api/resources/webhooks.php`. AU10 also edits `includes/api/console_view.php`, where the Conventions section lives (`api/api-docs-conventions.md`).
- Suggested beginning: AU1, AU4 and AU8 side by side.
