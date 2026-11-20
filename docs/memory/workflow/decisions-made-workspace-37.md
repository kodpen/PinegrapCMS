---
name: decisions-made-workspace-37
description: Decisions made, workspace part 37 (2026-11-16 on): channel commands grown in five packages, flows in the / list, an agent's command, a mention typed by hand becoming a tag, a question's picture look
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Are channel commands grown, and how far? (owner, 2026-11-16, the R&D agent's polls 93, 95 and 96)
- His request (agent channel message 6671): "kanallardaki komutları genişletme ilgili araştırma ve plan hazırla. en son mimara sor planı o hazırlasın."
- Poll 93 (message 6777), the five packages (the engine, commands for what has a button, records, application and agent commands, by-hand flows in the list): "Evet, hepsi; sırayla".
- Poll 95 (message 6790), a channel's by-hand flows in the "/" list by name: "Evet; listeden seçilir, Enter "Bir akış başlat" düğmesinin işini yapar". Not chosen: a short command name of a flow's own; the menu alone. The keyword start left out on 2026-11-05 (#7110) stays out: picking from a list is no keyword.
- Poll 96 (message 6792), a command typed for an agent that waits on its request queue: "Evet; satır yazanın adıyla kanalda görünür, anmayla aynı frenler". Not chosen: agents woken by a mention alone.
- The column of poll 97: `decisions-made-schema-25.md`. What was built: `workspace/workspace-command-overview.md`.

## What did the architect fix for channel commands? (architect, 2026-11-16, agent channel message 6770)
- The registry is a PHP array in code, one entry per command; no table, no hook. A new built-in name wins over an application's earlier one; the application's row stays and is listed as shadowed.
- Names: /hatırlat, /özet, /onay, /akış, /güncelleme, /checkin, /bildirim, /davet, /ayrıl (with a confirmation card), /konu, /ara, /bellek, /kargo. No /sessiz.
- A textless /task typed as a reply makes the task from that message; a textless /decision or /note marks it.
- The need #2999 waited for is this request: /create learns a contact and a workflow table row, then an ERP account and a calendar event; no sales quote. /kargo is a change with the old-and-new card, so #3801 (no command that only shows a record) does not refuse it.
- Out: an order's status by a command, a general "set a field" command, counting the use of commands. Menu only: archive, clear, make private, remove a person and the like.

## Does a mention typed by hand become a tag? (owner, 2026-11-16, in the architect's window)
- Yes. His words, with a picture of the agent channel where a picked mention was a tag and a typed one plain text: "bazı sohbetlerde anmalar etikete dönüyor bazılarında dönmüyor. bunu tab basmadan da sonradan döüştürecek bir sisteme döndürelim olur mu, sohbette tab basmadan devam edersem benim mesajlarımda da dönmüyor".
- The architect's shape, not his words (issue #9985): when a channel or discussion message is written or changed, a typed `@name` becomes the tag a pick would have written if it is exactly one match: the handle of an agent allowed in the channel, or one team member's username whom the channel's `@` list offers. The rule of a typed assignee in /task (#1731).
- So a colleague's typed username now rings them as a picked mention does. Left as typed: `@herkes`, `@ai`, `@claude`, a shared username, a prefix, a display name, anything in code or an e-mail address. A changed message asks nobody twice. Old messages are not rewritten; no schema.

## Does a question's own picture stand in its box as built? (owner, 2026-11-16, agent channel poll 92, message 6751)
- Yes: "Evet, böyle çıksın", on the sheet of message 6750 (QP2, #9964): the question's picture inside the box at the left of the options, each option's own picture small on its row.
