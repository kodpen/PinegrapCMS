---
name: bug-hunt-areas-3
description: The areas given to a bug hunt from 2026-11-20 on (the own sheet reader and its importers; Incoming e-mail and Mailboxes at phone width), what each fixed, the issues it opened and what it read clean
---

## Which areas were hunted from 2026-11-20 on, and what did each find?
- The hunts before: `bug-hunt-areas.md`, `bug-hunt-areas-2.md`; beside this file: `bug-hunt-areas-4.md`. The next area is none of the four files.
- Given on 2026-11-20 (channel message 9983): the own sheet reader (`platform/sheet-reader.md`, XL1 #10466) and the importers on it (product import XL2 #10501, bank statement import XL3 #10503), before XL5 removes PHPExcel. Every difference from PHPExcel on the same file counts as a fault.
- It ended with #10537, three faults fixed: an .xls whose shared strings a CONTINUE record splits in formatting runs refused, or a string cut; an .xls read at 3 to 5 times PHPExcel's memory; a .xlsx whose shared strings (and, in the product import, styles) are not at the usual name read empty. Issue #10544: the sheet reader's .xlsx styles part (held `office.php`).
- Read and found clean, cell by cell against PHPExcel: LibreOffice .xls/.xlsx/.ods of a 3,000-row Turkish bank statement (merged title, empty cells, dates, date-times, times, amounts as text) and of 20,000 rows (8 MB, extension FAT blocks); a sheet with only a head; the second and an empty third sheet; numbers stored as text and large numbers; a hand-built Excel 95 file in code page 1254 (LABEL, RK, NUMBER, custom date formats).
- Left as PHPExcel had them: an .xls number is PHP's float text (a 16-digit code reads in E notation); an encrypted .xls is refused (PHPExcel read only the legacy RC4 kind); dates before 1 March 1900 a day off.
- Given on 2026-11-20 (agent channel message 9983), after the owner met a fault there (message 9832; the long subject in the open mail's head was #10496): Communication › Incoming e-mail and Site Settings › Mailboxes at 390 and 320 px. A headless browser run was allowed for it, as layout reaches no unit test.
- Fixed in #10543, each pinned in `tests/mail_phone_width_test.php`: the list's views ran past the screen's edge (Blocked e-mail and Drafts unseen); a mailbox row's Edit ran off the card at 320 px; the box form's sent-copy check box stood apart from its label; the open conversation's status and holder were 23 px high.
- Opened: #10545, the channel pill in the open conversation's head is a 22 px target (`backend.src.css`, held by #10522 at the time).
- Read and found clean there: the list rows (subject and customer cut with an ellipsis by design), the open conversation's head, mails and long links, Reply by e-mail and New e-mail drawers, Remind me later's menu and its time drawer, Block sender, Add to my calendar, My e-mail settings, the Mailboxes card's text and the box form's fields at both widths.
- Not verified: a drawer under a phone's keyboard (a headless browser has none). Seen and left: the Communication tab strip and the panel header pass 320 px by a few pixels (shared shell, not the area); fields at 14 px let iOS zoom in on focus panel-wide.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md, docs/memory/platform/sheet-reader.md, docs/memory/workspace/mail-screen-phone.md
