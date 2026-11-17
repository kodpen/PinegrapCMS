---
name: decisions-made-schema-21
description: Decisions list, schema part 21 (2026-11-15 on): ws_mail_reminders, ws_profiles.mail_away, ws_approval_agents; the schema of polls 49 to 51 (agent duty, marks, cart touches, pop-ups)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which schema do remind later, remind when no answer and "I am away" get? (owner, 2026-11-15, in the architect's window)
- Asked as agent channel poll 3450 after the census IB9 (#9554), with "Evet, ikisi de eklensin" recommended. He did not vote; in the architect's window he said: "Oylama 31 ve 32 önerdiğin gibi ilerle". The poll was closed with the decision under it (channel message 3500).
- Table `ws_mail_reminders`: `id`, `conversation_id`, `user_id`, `kind` VARCHAR(10) (`later` or `no_answer`), `item_id`, `due_at`, `woken_at`, `created_at`; UNIQUE (`conversation_id`, `user_id`, `kind`); KEY `idx_due` (`woken_at`, `due_at`). Added by IB10 (#9592, `workspace/mail-remind-later.md`); IB10b reads the same table (#9625, `workspace/mail-no-answer.md`).
- Why a table: the only per-person state was `ws_mail_reads`, whose mark never moves back, so it cannot take a conversation out of one person's list and bring it back at a time.
- Column `ws_profiles.mail_away` TEXT NULL, a JSON of the range, the text and the stand-in, beside the person's own signature. Added by IB12.
- Incoming rules (IB11) and undo send (IB13) add no schema; the rules stay in the box's settings JSON.
- Not chosen: only the table, only the column, neither. Running sites: no backfill.

## Where are an approval request's agent approvers kept? (owner, 2026-11-15, in the architect's window)
- Asked as agent channel poll 3455 after the census AE1 (#9555), with "Evet, ayrı tablo eklensin" recommended; answered by the same sentence of his. Decision under the poll: channel message 3501.
- Table `ws_approval_agents`, beside `ws_approval_guests`: `approval_id`, `agent` VARCHAR(32) (the `ws_ai_requests.agent` value), `request_id`, `decision`, `note`, `decided_at`; key (`approval_id`, `agent`). Added by AE2 (#9567, 11.0.210): `workspace/approval-agents.md`.
- `ws_approval_people`, its primary key and every reader of it (the card, Today, the reminders, the calendar, the dashboard) stay as they are.
- Not chosen: a column `agent_id` on `ws_approval_people` with its primary key widened, a MODIFY after which every reader must leave agent rows out.
- The architect's, not the owner's words: he had been told "a column"; the census showed the table is safer and the poll said so. The outcome comes from the one pure function, fed both lists; a removed or paused agent counts as not answering.

## Which schema do the features chosen on 2026-11-15 get? (owner, agent channel polls 49, 50, 51 and message 5139)
- Poll 49's text (channel message 5089) named both: the duty text "ajan tablosuna bir sütun ister", Right or Wrong "küçük bir tablo ister"; he chose both. Column `ws_agents.duty` TEXT NULL, added by AF1; a small table of marks, one row per marked reply, added by AF5a (`workspace/agent-duty.md`, `workspace/agent-feedback.md`).
- Poll 50: "Evet, tablo ve 7 gün": one small table of a left cart's touches, added by #9778 (`commerce/cart-recovery-touches.md`).
- Poll 51: "Evet, ikisi de": a rollup table for a pop-up's counts. Message 5139: "gerekiyorsa ona yeni tablo sütunları eklenir": new columns on `auto_dialogs` for when it opens, how often, who sees it and what it shows.
- Each step states its exact columns in its PR and the architect confirms them; they are recorded here when the step lands. Running sites: no backfill; an empty duty and an auto dialog's old row behave as before.
- No schema: the progress note (inside `ai_state`), the task hand-over (`ws_tasks.delegate` takes `agent:<id>` and `model:<id>`), the open items line.
