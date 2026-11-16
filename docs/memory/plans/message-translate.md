---
name: message-translate
description: Plan (owner 2026-11-15, agent channel poll 54): Translate in a message's menu with the browser's own translator, the text never leaving the device; step MT1
priority: 6
---

## What is asked?
- The R&D agent's proposal is agent channel message 5355. The owner chose it in poll 54 (message 5366) as it was put to him: Translate in the message's menu, with the browser's own translator, the text never leaves the device; a browser with no translator (most phones) shows no button; no schema (`workflow/decisions-made-workspace-35.md`).
- Today only writing has help: "Rewrite with AI" (`workspace/workspace-ai-rewrite.md`), whose browser bridge already uses the browser's Translator API (`pg_ai_write.js`). Nothing translates a message that came in.

## Which steps are there?
- MT1 (M) Translate on a message.
  - "Translate" in the menu of every message the conversation's message renderer draws (channel, direct message, discussion, a guest's message), offered only when the browser has the translator. No server call, no API route, no schema, nothing saved.
  - The target is the reader's panel language; the source is found by the browser's language detector, and a message already in the reader's language says so in a short line and changes nothing.
  - The translation stands under the message with a "Translated" mark and "Show original"; only this reader sees it and it is gone on a reload. While the browser fetches a language pack the line says it is preparing; a refusal or failure says so in one line.
  - Tags, links and code are kept as they are, by the rule of the rewrite (`workspace/workspace-ai-rewrite.md`). The pure part (cutting a text into pieces to translate and pieces to keep, and putting them back) has a test.
  - Close-out in the same PR: `workspace/workspace-message-translate.md`, this plan deleted.

## What is left out?
- Pinegrap AI as the translator where the browser has none (a daily limit, the text goes to the model); "always translate this conversation"; an answer translated into the customer's language and sent; the items of an e-mail conversation. Each is a later yes of the owner.

## Whose look?
- The owner's: Workspace is a kept screen and the translated text is new on it. MT1 posts close-up pairs in the agent channel (the menu, a translated message, the original shown again; light and dark; a phone only where the button shows), the architect brings them to him, and the step is not marked ready before his look.
- The architect's readings, not his words: the target language, the conversations of the first step, what is kept untranslated.
