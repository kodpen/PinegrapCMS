---
name: decisions-made-workspace-17
description: Decisions list, workspace part 17 (2026-11-11): a mail opens as a preview drawn with its HTML and never in a channel, pictures from outside wait for the reader, a mailbox may be kept in no channel
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. The owner's words of this part stand on #8765.

## Where does a mail open from the E-mails list, and how is it drawn? (owner, 2026-11-11, #8765)
- In a preview on the E-mails screen, drawn with the mail's HTML; a click never leads into a channel.
- Owner (2026-11-11), in the architect's window with a picture of the list: "epostalar bir listede çıksın istemiştim yapılmış ancak ben html vs. içerikleri ile çizen bir önizlemeyi açsın istemiştim, tıklayınca bir kanala gitsin değil."
- It replaces the scope line of `workspace/mail-decisions.md` that left HTML rendering out, and the plan's "HTML later" for reading.
- The architect's, not the owner's words: the preview is the reading pane beside the list; the HTML is cleaned when the mail comes in and drawn in a frame that runs no script and sends no form (`plans/agent-live-page.md`, the guard's mode without script).

## How are pictures from outside shown in a mail? (owner, 2026-11-11, #8765)
- Not loaded until the reader presses "Show pictures"; the choice is remembered for the sender.
- Owner (2026-11-11), his vote on the one-choice poll in the agents channel: "Tıklayınca göster; gönderen için hatırla (Önerilen)". Not chosen: "Her zaman göster"; "Hiç gösterme".
- Why it was asked: a picture loaded from the sender's server tells the sender that and where the mail was opened.

## May a mailbox be kept in no channel? (owner, 2026-11-11, #8765)
- Yes. A box may be added without a channel; its mail is read and answered on the E-mails screen.
- Owner (2026-11-11): "ayrıca bu eposta kutusu bir kanala bağlı olmayadan bağlanmış bir eposta kutusu da olabilsin. bu ekranı da güçlendirelim."
- It widens answers 3 and 4 of `workspace/mail-decisions.md` (each box with a channel); a box with a channel stays as it is.
- The architect's, not the owner's words: such a box has a room of its own that no channel list shows (kind `mailbox`, `decisions-made-schema-16.md`), read by the people chosen on the box. A box says whether it is a support box; status, holder and the first-answer time show only for one (in his picture a DMARC report carried "First answer overdue").
