---
name: settings-card-own-button
description: A settings card that runs a job beside saving: named type=submit button, pressed handling, why serializers drop it, no save-less button contract.
---

## How does a card perform a job in addition to saving?
- Same way in dialog and page: a NAMED `type="submit"` button. The save module asks `post_value('<name>')`: settings are written first, then the job runs. The only card button, "Bot IP Listelerini Güncelle", works this way.
- No serializer sends a submit button: a submit button is a successful control only when it submits the form, while `serialize()` looks at the form, not the click. In the dialog this was silent: the button saved the category, skipped the job and said "kaydedildi". The name is remembered at click time (`pressed`, `backend.src.js`) and put back into the body by the submit that follows: the submit handler hands it to `save(button)`.
- A click whose default was cancelled (an inline `confirm()` answered Cancel) is not remembered. Before #748 it was, and the next Save or Ctrl+S carried it: a cancelled "New address" renewed the product feed address on the next save and the old `feed.php` address went 404.

## What else applies?
- The dialog's own Save is `type="button"` and Ctrl+S calls `save()` with no button, so neither carries a card button; a plain save stays plain.
- Return in a text field presses the form's first submit button. Both forms (dialog `modal.php`, page `screen.php`) start with a nameless, visually hidden submit button, so Return is a plain save and never presses a card button. Before #748 Return in the seller name field pressed "New address" (the first submit button of the SEO pane) and opened its question.
- A notice is TEXT, not markup (`notice()` uses `.text()`). If the job produces a DNS record or a key, put it in the sentence as plain text; writing a `<textarea>` shows it on screen with its tags.
- A button that must NOT save ("don't save, just do this") has NO contract, deliberately: one round was written, then SMTP was decided to stay in settings and its only user was removed. Two mechanisms doing the same job side by side give silent bugs because which one is chosen varies per card. Write it for the real need when it arrives, not on a guess.

Related: docs/memory/ui/settings-invalid-vs-error.md
