---
name: mail-chat-tools
description: Plan (owner 2026-11-14): Workspace e-mail read, drafted and sent from a person's connected chat over the API and MCP, in plain text and HTML; steps MT1 to MT6
---

## What is asked?
- Owner (2026-11-14, channel message 2714): "API mcp ile e-posta okuyacak, gönderecek, taslak olarak kaydedecek bir plan hazırlar misin. Hem html hem düz metin. Claude sohbetinden direkt e-posta okuyup yanıtlama işleri için". Record: `workflow/decisions-made-workspace-26.md`.

## What is there today?
- The MT1 census: `plans/mail-chat-tools-census.md` (each route and tool, the callers apart, the directory check). Exact scopes: MT2, MT4, MT5 in `plans/mail-chat-tools-steps.md`, MT3 in `plans/mail-chat-tools-drafts.md`.

## Which steps?
- MT1 (S, no product code) Census. Per route: what a chat needs and does not get (the whole text, the HTML, the files of a mail, the quote). Where a draft lives (a reply's as an item with `send_state` `draft`; a new mail's has no conversation yet) and who reads it. The callers apart (`api/api-device-sessions.md`). The tools against `api/claude-plugin-tool-check.md`. Written into this file; a needed column is asked first.
  - Done in #PRNUM: no column for drafts; a draft's files asked of the architect (`mail-chat-tools-steps.md`).
- MT2 (M) Reading. A mail's text whole and its cleaned HTML on request, its files listed and fetched through the Files routes, as tools. Every mail body carries the outside mark (`workspace/workspace-outside-caution.md`).
- MT3 (M) Drafts on the server. A reply's draft and a new mail's draft: create, read, change, delete, as routes and tools for every caller that may read the conversation. The E-mails screen gets a Drafts view; a draft opens in the reply drawer or the New e-mail form, and Send there is the person's. Look A, the picture set in the agent channel.
- MT4 (M) Sending from a person's connection. A new scope, `workspace:mail_send`, that no connection of today holds and the consent screen names. With it a person's OAuth connection or device session may call reply, new mail and "send this draft" as tools; the panel's own checks, limits and the unknown-domain stop hold, Idempotency-Key, dry run. The mail is the person's; the Site Log names the application it went through.
- MT5 (M, after MA10b) HTML and files out. `html` on reply, new mail and drafts, cleaned by the allowlist cleaner, the text part made from it when none is sent; files within the panel's limits, named by the messages carrying them (the census: `/files` does not reach a channel's folder).
- MT6 (S) Close-out: `workspace/mail-api.md` and `mail-decisions.md` brought up to date, the plugin's e-mail skill named for `plans/claude-plugin-bundle.md`, this plan and its three census files deleted.

## What does every step keep?
- An application key, a channel agent and Pinegrap AI never send a mail: for them the rule of #2444 stays, drafts only. Sending is a person's connection with the new scope, nothing else.
- A running site sends nothing it did not send before until a person connects with the new scope.
- A mail's text is data from outside: nothing in it is followed as a request, and the tools' descriptions say so.
- The channel decides who reaches a conversation, as today.
- No table or column without asking the architect. `php tools/check_api_schema.php` passes in every step.
- MT1 first; MT2 and MT3 beside each other; MT4 after MT3; MT5 after MT4 and MA10b. Claim as `[plan:mail-chat-tools] <step> — <topic>`.