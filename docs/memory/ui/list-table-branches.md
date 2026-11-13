---
name: list-table-branches
description: A list table driver source read as branches (MA2b, #9275): the branches and branch_id keys, the wrapper each batch puts round a branch, the count per branch, the measured times
module: list_table
---

## How is a source of several tables read?
- A source may give `branches` (each `sql` = one SELECT with the same columns, `params`) and `branch_id` instead of `from`. A batch wraps each branch as `(SELECT * FROM (branch) AS pg_lt_b WHERE condition AND keyset ORDER BY levels, branch_id LIMIT 101)`, joins them with UNION ALL, sorts once more and cuts the batch (`pg_list_table_branch_page()`); the count is the sum of `COUNT(*)` per branch, so a branch holds each row once.
- The server merges each wrapper into its branch, so the bound and the order reach the branch's own index. `branch_id` is a plain column of the branch ordered like `id` (`sid = rid * 8 + n`): ordering on a computed id loses the index.
- Read whole (the selection resolver) the branches are one union in `from`, their values in `from_params` ahead of `params`, which then bind `where` only. Used by Communication › Sends (`platform/messages-area-sends.md`).

## Why not one UNION in `from`?
- Read whole, a union of 500,000 + 500,000 rows sorted for 101 took 2.8 s and its COUNT 3.2 s (MA2, #9252); bounded per branch the rows take 12 ms and the summed count about 120 ms (`platform/messages-area-sends.md`).

Related: docs/memory/ui/list-table-driver.md
