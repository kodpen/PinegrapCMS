---
name: mail-inbox-more
description: Plan (owner 2026-11-14, poll 3363): the E-mails screen gains remind later, a reminder when no answer comes, incoming mail rules, "I am away" and undo send; steps IB9 to IB14
---

## What is asked?
- The owner ticked four additions to the mailbox plan (`workflow/decisions-made-workspace-29.md`). They widen what the closed plan IB1-IB8 built (`workspace/mail-overview.md`) and start after its IB6 (#9547) or beside it, as each line says.

## Which steps?
- IB9 (S, no product code) Census: where a conversation's state per person and "My e-mail settings" are kept, what Send later (IB6) keeps for a mail that waits, what routing and the block list do at ingest, whether a conversation carries labels. It names the column or table each step needs; the architect asks the owner before any is built.
- IB10 (M, after IB6) Remind later: "Remind me later" in a conversation's menu takes a time (in an hour, tomorrow morning, next week, a date): the conversation leaves that person's list, the others' lists do not change, and it comes back unread with a notice at the time or at once when a new mail arrives.
- IB10b (S, after IB10) Remind when no answer comes: on the reply drawer and New e-mail, "Remind me if no answer comes" in 1, 3 or 7 days: with no incoming mail by then the writer gets a notice and the row says "No answer yet"; an answer cancels it.
- IB11 (M) Incoming mail rules, on the Mailboxes card beside the block list. A rule is one condition (the sender's address, its domain, words in the subject) and one action (assign to a person, close, a label where IB9 finds labels). Rules run at ingest after the block check, the first that fits wins, a blocked or automatic mail runs none. Who may change a box's blocks changes its rules; each change is a Site Log line.
- IB12 (M) "I am away" in "My e-mail settings": two days, a text, an optional stand-in. In that range a mail arriving in a conversation assigned to the person gets the text once per sender, never an automatic mail or a bounce, and a conversation that would be assigned to the person goes to the stand-in. The range shows on My calendar as the person's plan, shared with the whole team.
- IB13 (S, after IB6) Undo send: a sent mail waits 10 seconds under "Not sent" with "Undo", which turns it back into its draft; nothing leaves the site before the wait ends. No setting.
- IB14 (S) Close-out: the memory files of the steps, `workspace/mail-overview.md`, this plan deleted.

## What does every step keep?
- What the closed mailbox plan keeps (`workspace/mail-decisions.md`): team accounts only, the channel decides who reaches a conversation, a running site reads and sends as before until a button is pressed or a setting written.
- An automatic answer and a rule never answer a mail the loop gate or the hold would stop (`workspace/mail-ingest.md`).
- Times are the person's time zone; the general job does the waking, no new job.
- Look A (`ui/look-design-examples.md`), the whole picture set in the agent channel at once. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- No table or column without asking the architect.
- IB9 first; IB11 and IB12 after IB9; IB10 and IB13 after IB9 and IB6, IB10b after IB10. Claim as `[plan:mail-inbox-more] <step> — <topic>`.
