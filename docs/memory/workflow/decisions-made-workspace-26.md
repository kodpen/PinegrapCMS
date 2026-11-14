---
name: decisions-made-workspace-26
description: Decisions made, workspace part 26: a template is chosen with a preview; new parts inside a channel use look A's components; a person's connected chat may send Workspace e-mail
---

## Does a place where a template is chosen show a preview? (owner, 2026-11-14, channel message 2709)
- Looking at the Reply by WhatsApp drawer's pictures (WA14b, #9446), where the approved template was a plain select and its text showed the raw `{{1}}` marks, the owner wrote: "Adnan gorsellerini onayla ancak şablon seçim ekranlarında önizleme ler olursa iyi olur."
- So: wherever a template or a design is chosen, the choice shows a preview of what the receiver will see. In the drawer the chosen WhatsApp template is drawn as the customer reads it, a message bubble with the typed values in place, updated as they are typed.
- The same holds for the ready e-mail designs (`plans/messages-area-steps.md`, MA5d) and for every later chooser of a template or a design.
- The architect's reading, told to him in the channel (the answer to 2709): it is a rule for every such screen, not for this drawer only. The drawer's pictures were approved by the same message; the preview was added to that PR while it was a draft.

## Which components does a new part inside a channel use? (owner, 2026-11-14, channel message 2709)
- The owner, in the same message: "Kanal içinde de moder arayüz öğeleri kullanalim yavaştan geçiş yapariz."
- So: every new part built inside a channel (a drawer, a card, a bar, a field) uses look A's components and recipes (`ui/look-design-examples.md`), never the browser's own controls. The first case: the drawer's File field was the browser's file input and became the panel's file field.
- The old parts of the channel are not redrawn in one piece. A step that touches one converts it, and DL5f (`plans/panel-design-language-steps.md`) takes what is left.
- Refused: one large redraw of the channel screen before other work goes on.

## May a connected chat send a Workspace e-mail? (owner, 2026-11-14, channel message 2714)
- The owner asked for a plan: "API mcp ile e-posta okuyacak, gönderecek, taslak olarak kaydedecek bir plan hazırlar misin. Hem html hem düz metin. Claude sohbetinden direkt e-posta okuyup yanıtlama işleri için".
- So: over the API and MCP a mail is read, kept as a draft on the server and sent, in plain text and in HTML. Plan: `plans/mail-chat-tools.md`.
- The architect's reading, told to him in the channel (the answer to 2714): sending is for a person's own connection, in that person's name, under a scope of its own that no connection of today holds. His answer 7 of #2444 ("assistants never e-mail customers, drafts only", `workspace/mail-decisions.md`) stays for an application key, a channel agent and Pinegrap AI.
