---
name: workspace-mail-screen-steps-2
description: Steps of plans/workspace-mail-screen.md after the owner's words of 2026-11-11 (#8765): WM10 a row opens the mail, never a channel; the HTML view and a mailbox kept in no channel follow
---

## Which steps are there?
- Owner (2026-11-11, #8765): "ben html vs. içerikleri ile çizen bir önizlemeyi açsın istemiştim, tıklayınca bir kanala gitsin değil. ayrıca bu eposta kutusu bir kanala bağlı olmayadan bağlanmış bir eposta kutusu da olabilsin. bu ekranı da güçlendirelim."
- WM9 (close-out) comes after the last step of this file and deletes this file too.

- WM10 (S) A row opens the mail, never a channel. `free`. Architect 2026-11-11.
  - Today a row's `href` is the card in the channel, and the pane is opened by the script, which starts only once `ws_bootstrap` has answered (`workspace.js`): a click before that, or when it fails, lands in the channel. The owner met it.
  - The row's `href` becomes the E-mails screen with `conversation=<id>`, the filters kept. For that address the server draws the pane itself (`ws_mail_pane_html()`), so the mail opens with or without the script. "Open in channel" stays the pane's button.
  - A check (in `tools/lint.php` or a test) that no script source declares one function name twice in a scope: #8719 did so in `workspace_mail.js`, "Reply by e-mail" broke and `node --check` saw nothing (#8748 renames it).
  - Tests: the row's address, the pane drawn for the address, the check on a fixture. `workspace/mail-list.md` and `mail-screen-pane.md` corrected.

## What follows?
- After the owner's answers on #8765 (the schema, pictures from outside): a mail's HTML kept and shown in the guarded frame without script (`agent-live-page.md`, LP1), a mailbox kept in no channel, a plain box without the support desk's marks. Written here once he has answered.
