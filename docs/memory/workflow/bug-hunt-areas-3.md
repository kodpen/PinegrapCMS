---
name: bug-hunt-areas-3
description: The areas given to a bug hunt from 2026-11-20 on (the own sheet reader and its importers before PHPExcel's removal), the faults each fixed, the issues it opened and what it read clean
---

## Which areas were hunted from 2026-11-20 on, and what did each find?
- The hunts before: `bug-hunt-areas.md`, `bug-hunt-areas-2.md`. The next area is none of the three files.
- Given on 2026-11-20 (channel message 9983): the own sheet reader (`platform/sheet-reader.md`, XL1 #10466) and the importers on it (product import XL2 #10501, bank statement import XL3 #10503), before XL5 removes PHPExcel. Every difference from PHPExcel on the same file counts as a fault.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md, docs/memory/platform/sheet-reader.md
