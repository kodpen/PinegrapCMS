---
name: workspace-mail-screen-steps-2
description: Steps WM10-WM14 of plans/workspace-mail-screen.md (owner 2026-11-11, #8765): a row opens the mail, the HTML kept and drawn in a guarded frame, a mailbox kept in no channel, a plain box
---

## Which steps are there?
- Owner (2026-11-11, #8765): "ben html vs. içerikleri ile çizen bir önizlemeyi açsın istemiştim, tıklayınca bir kanala gitsin değil. ayrıca bu eposta kutusu bir kanala bağlı olmayadan bağlanmış bir eposta kutusu da olabilsin. bu ekranı da güçlendirelim."
- WM9 (close-out) comes after the last step of this file and deletes this file too.

- WM10 (S) Done #PRNUM. A row opens the mail, never a channel. Architect 2026-11-11.
  - Today a row's `href` is the card in the channel, and the pane is opened by the script, which starts only once `ws_bootstrap` has answered (`workspace.js`): a click before that, or when it fails, lands in the channel. The owner met it.
  - The row's `href` becomes the E-mails screen with `conversation=<id>`, the filters kept. For that address the server draws the pane itself (`ws_mail_pane_html()`), so the mail opens with or without the script. "Open in channel" stays the pane's button.
  - A check (in `tools/lint.php` or a test) that no script source declares one function name twice in a scope: #8719 did so in `workspace_mail.js`, "Reply by e-mail" broke and `node --check` saw nothing (#8748 renames it).
  - Tests: the row's address, the pane drawn for the address, the check on a fixture. `workspace/mail-list.md` and `mail-screen-pane.md` corrected.
- WM11 (S, schema) A mail's HTML is kept. `free`. Owner 2026-11-11, #8765 (`workflow/decisions-made-schema-16.md`).
  - The step: `ws_mail_items.body_html` and the kind value `mailbox`, rerun twice on a dev DB; the PR body names the `[SCHEME]` item.
  - `ws_mail_ingest()` keeps the HTML part, cleaned by an allowlist of its own (`includes/fn/mail_html.php`: text, tables, inline styles; no script, form, frame, event attribute or style sheet from outside; an outside address in `src` set aside so nothing loads by itself), 1 MB at most, else none. A picture the HTML names by `cid:` is kept as the mail's file even without a name.
  - Mail whose `.eml` is still kept is filled in by the recurring job, 200 a run. Tests: what the cleaner keeps and drops, the `cid:` pictures, the fill. Topic `workspace/mail-html.md`.
- WM12 (M) The preview draws the HTML. After WM11 and the guarded frame (LP1 of `agent-live-page-steps.md`; if this step comes first it builds the module with the mode without script and that mode's proofs).
  - The pane shows a mail's HTML in the frame, its height following the mail; plain text when there is none, and a switch to it. Links open in a new tab. Pictures from outside wait for "Show pictures", remembered for the sender per reader in the browser (`workflow/decisions-made-workspace-17.md`).
  - With the pane open the list's rows narrow to who, subject, first words and time. Looked at in the sandbox, wide and phone, light and dark, with a newsletter, a plain mail and a mail with pictures; the PR links the pictures.
- WM13 (M) A mailbox kept in no channel. After WM11.
  - The box form's "Kept in": a channel, or the mailbox itself. The second makes a room of kind `mailbox` named after the box; the people and departments chosen on the box are its members. No channel list, picker, search or API channel list shows it: every reader of `ws_channels` by kind is checked, with a test.
  - The E-mails screen is its face: read, answer, New e-mail, files; its notices open the screen. Routes by address or +tag into channels stay.
- WM14 (S) A plain box. After WM13.
  - A box says whether it is a support box (in `settings`, no column). Status, holder, the first-answer time, the overdue filter and the automatic answer show only for one. A box kept in no channel starts plain; today's boxes stay support boxes.
