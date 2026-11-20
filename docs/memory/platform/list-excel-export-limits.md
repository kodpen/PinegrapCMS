---
name: list-excel-export-limits
description: When Export to Excel sends the CSV instead (memory, 1,048,575 rows) and the text it cleans (UTF-8, 32,767 characters); the bug hunt of #10619
module: list_excel
---

## When is the CSV sent instead of the workbook?
- A CSV too big to build: `pg_list_excel_size()` counts the held CSV's bytes, rows and cells, `pg_list_excel_need()` estimates the build (200 bytes a cell + 16 a byte; measured up to 140 and 12 on PHP 8.3), and `pg_list_excel_fits()` sends the CSV when that passes `memory_limit` less what is in use or the rows pass 1,048,575. Before PR #10619 some 20,000 orders passed 128 MB and the download ended in a fatal error.

## What text is changed for Excel?
- Text and headings go through `pg_list_excel_text()`: bytes that are no UTF-8 replaced and at most 32,767 characters; either made Excel call the file damaged (PR #10619).

## What did the bug hunt find?
- Hunt asked by the architect (agent channel message 10243), PR #10619: the size fallback and the text above. Read clean: formula cells (text is always an inline string), the file name, the permission (the capture only holds what the CSV branch prints, so a refusal page passes through).
- Tests: `tests/list_excel_size_test.php`.

Related: docs/memory/platform/list-excel-export.md
