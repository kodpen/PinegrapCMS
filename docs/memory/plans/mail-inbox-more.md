---
name: mail-inbox-more
description: Plan (owner 2026-11-14, poll 3363): the E-mails screen gains remind later, a reminder when no answer comes, incoming mail rules, "I am away" and undo send; steps IB9 to IB14
---

## What is asked?
- The owner ticked four additions to the mailbox plan (`workflow/decisions-made-workspace-29.md`). They widen what `plans/mail-inbox-features.md` builds and start when its IB6 has landed or beside it, as each line says.

## Which steps?
- IB9 (S, no product code) Census, done #9554: findings `plans/mail-inbox-more-census.md`; exact scopes `plans/mail-inbox-more-scopes-wait.md` (IB10, IB10b, IB13) and `plans/mail-inbox-more-scopes-rules.md` (IB11, IB12). Schema (asked in 3448; owner's yes, decision 3500, shape 3505): table `ws_mail_reminders` added by IB10 and read by IB10b, `ws_profiles.mail_away` added by IB12; IB11 and IB13 need none of their own.
- IB10 (M, after IB6) Remind later: "Remind me later" among the reading pane's tools (`ws_mail_pane_tools()`; the list rows have no menu) takes a time (in an hour, tomorrow morning, next week, a chosen time): the conversation leaves that person's list and unread count, the others' do not change, and it comes back unread with inbox kind `mail_remind` at the time or at once when a new mail arrives.
- IB10b (S, after IB10) Remind when no answer comes: on Reply by e-mail and New e-mail, "Remind me if no answer comes" in 1, 3 or 7 days: with no incoming mail by then the writer gets inbox kind `mail_no_answer` and their row says "No answer yet"; an incoming mail cancels it.
- IB11 (M) Incoming mail rules, on the Mailboxes card beside "Blocked senders", kept in the box's `settings` JSON as `rules` like the routes (no schema). A rule is one condition (the sender's address, its domain, words in the subject) and one action ("Give it to" a person, "Close it"; conversations carry no labels).
  Rules run in `ws_mail_ingest()` for a mail that opens a conversation, after the block check and the conversation's write, before the notices and the automatic answer; the first that fits wins; a closed one gets no automatic answer; a blocked or held mail runs none. Behind the Workspace settings right, as the block list; each change is a Site Log line.
- IB12 (M, in progress, draft PR) "I am away" in "My e-mail settings": two days, a text, an optional stand-in. In that range a mail arriving in an ongoing conversation held by the person gets the text once per sender, as an automatic answer (never to an automatic mail, a bounce or a `fail` sender); a conversation given to the person by hand, the API or a rule goes to the stand-in. The range is a whole-team plan on My calendar (`ws_personal_plans`, visibility team).
- IB13 (S, after IB6) Undo send: the panel's Send keeps the mail as IB6's waiting mail for 10 seconds with "Undo", which turns it back into its draft; after the wait the browser asks for it to leave, the job is the fallback. The API sends at once. No setting, no schema beyond IB6's.
- IB14 (S) Close-out: the memory files of the steps, `workspace/mail-overview.md`, this plan deleted.

## What does every step keep?
- What the mailbox plan keeps: team accounts only, the channel decides who reaches a conversation, a running site reads and sends as before until a button is pressed or a setting written.
- An automatic answer and a rule never answer a mail the loop gate or the hold would stop (`workspace/mail-ingest.md`).
- Times are the person's time zone; the general job does the waking, no new job.
- Look A (`ui/look-design-examples.md`), the whole picture set in the agent channel at once. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- No table or column without asking the architect.
- IB9 first; IB11 and IB12 after IB9; IB10 and IB13 after IB9 and IB6, IB10b after IB10. Claim as `[plan:mail-inbox-more] <step> — <topic>`.
