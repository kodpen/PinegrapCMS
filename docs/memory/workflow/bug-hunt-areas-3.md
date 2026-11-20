---
name: bug-hunt-areas-3
description: The areas given to a bug hunt from 2026-11-20 on (the own sheet reader and its importers before PHPExcel's removal), the faults each fixed, the issues it opened and what it read clean
---

## Which areas were hunted from 2026-11-20 on, and what did each find?
- The hunts before: `bug-hunt-areas.md`, `bug-hunt-areas-2.md`. The next area is none of the three files.
- Given on 2026-11-20 (channel message 9983): the own sheet reader (`platform/sheet-reader.md`, XL1 #10466) and the importers on it (product import XL2 #10501, bank statement import XL3 #10503), before XL5 removes PHPExcel. Every difference from PHPExcel on the same file counts as a fault.
- It ended with #10537, three faults fixed: an .xls whose shared strings a CONTINUE record splits in formatting runs refused, or a string cut; an .xls read at 3 to 5 times PHPExcel's memory; a .xlsx whose shared strings (and, in the product import, styles) are not at the usual name read empty. Issue #10544: the sheet reader's .xlsx styles part (held `office.php`).
- Read and found clean, cell by cell against PHPExcel: LibreOffice .xls/.xlsx/.ods of a 3,000-row Turkish bank statement (merged title, empty cells, dates, date-times, times, amounts as text) and of 20,000 rows (8 MB, extension FAT blocks); a sheet with only a head; the second and an empty third sheet; numbers stored as text and large numbers; a hand-built Excel 95 file in code page 1254 (LABEL, RK, NUMBER, custom date formats).
- Left as PHPExcel had them: an .xls number is PHP's float text (a 16-digit code reads in E notation); an encrypted .xls is refused (PHPExcel read only the legacy RC4 kind); dates before 1 March 1900 a day off.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md, docs/memory/platform/sheet-reader.md
