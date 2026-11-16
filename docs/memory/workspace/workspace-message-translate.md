---
name: workspace-message-translate
description: Translate in a message's menu (MT1): the browser's own Translator and LanguageDetector, the reader's panel language, the line under the message, what is kept untranslated, nothing sent or saved
---

## What is it?
- "Translate" in the menu of a message the conversation's renderer draws (`messageActions()` in `assets/js/workspace.js`: More and the context menu, not the hover bar): channels, direct messages, discussions, a guest's message. Owner (2026-11-15, poll 54, `workflow/decisions-made-workspace-35.md`).
- Only when the browser has both `window.Translator` and `window.LanguageDetector` (`PGWsTranslate.supported()`); most phones have neither and show no item. Only a message with text (`message.html`), not a deleted one.
- Code: `assets/js/workspace_translate.js` (`window.PGWsTranslate`), in the `workspace.min.js` bundle before `workspace_replies.js`.

## What goes where?
- Nothing to the server: no API route, no schema, nothing saved, no storage. The text never leaves the device.
- Target: the reader's panel language (`CFG.locale`, `lang(array('info' => ''))`), its language part. Source: the detector's first answer.

## What does the reader see?
- The translation in the message's place, with "Translated from <language>" and "Show original" in a line under it (`.ws-msg-tr`); "Show original" swaps back and the button then reads "Show translation". Only this reader; gone on a reload.
- A redraw of the message (sync, `replaceMessage()`) shows the translation again while its html is the same (`PGWsTranslate.apply()` in `messageNode()`); an edited message loses it.
- One line instead: already in the reader's language, language not found, a source the browser cannot translate, nothing to translate, a failure. While the browser fetches the language pack: "preparing", with its percentage.
- Strings `tr_*` in `includes/workspace/screen.php`.

## What is kept untranslated?
- Elements: links and record chips (`a`, `.ws-chip`, `[data-ws-ref]`), tag cards, code (`code`, `pre`, `.ws-code`), the message's own controls, `[translate="no"]`.
- Inside text: `<@…>` / `<#…>` tags, inline code, web addresses (without the sign closing the sentence), e-mail addresses, pieces with no letter, the spaces around a piece. Pure `pieces()` / `join()`, the rewrite's rule (`workspace-ai-rewrite.md`).
- Each text node is translated on its own, so a sentence split by bold or a link is translated in parts.

## What is left out?
- Pinegrap AI where the browser has no translator, "always translate this conversation", sending an answer translated, an e-mail conversation's items: each a later yes of the owner.

## How is it checked?
- `tests/workspace_message_translate_test.php`: `pieces()` / `join()` under Node, the wiring (only with the APIs, no server call, nothing stored, in the bundle). The pictures for the owner's look used a stand-in Translator in headless Chromium; a real browser translator was not run.
