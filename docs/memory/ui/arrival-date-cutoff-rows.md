---
name: arrival-date-cutoff-rows
description: Shipping Cut-offs table on the arrival date screen: how rows are numbered, which posted rows the save reads, when the left-out notice shows, a deleted row
---

## How are the cut-off rows numbered?
- The Add Shipping Cut-off button (`create_shipping_cutoff()`, `backend.src.js`; also run for each stored row when the edit screen opens) gives each new row the next number and posts it as `shipping_cutoff_<n>_shipping_method_id` and `shipping_cutoff_<n>_date_and_time`; the hidden `last_shipping_cutoff_number` holds the highest number given.
- The row's Delete button (`delete_shipping_cutoff()`) takes the row away without renumbering the others, so a deleted row leaves a gap.

## Which posted rows does the save read?
- `pg_arrival_date_form_cutoff_numbers($post)` (`includes/forms/arrival_date_form.php`): the numbers of the row fields the form posted, in number order. The posted `last_shipping_cutoff_number` is never read.
- Each row goes through `pg_arrival_date_form_cutoff()` (`api/api-store-lookups.md`); `pg_arrival_date_form_cutoffs($post)` returns the kept rows and whether a posted row was left out, and `pg_arrival_date_form_save_cutoffs()` replaces the stored ones and returns that flag.

## When does the left-out notice show?
- After a save with a posted row left out: a row with no method or time, a method an earlier row names, a time outside the start and end dates. Create and edit then show the edit screen with the notice.
- A row deleted on the screen posts nothing and is not a left-out row: the save lands on the list.
- Why (PR #11076): the save counted from 1 to the posted last number, so every deleted row was reported as left out and the screen stayed on the edit screen; `last_shipping_cutoff_number[]=x` looped until the 30 s limit (500), since a number compared with a list is always smaller in PHP 8.

## Tested?
- `tests/arrival_date_form_test.php`: a deleted row, a row added and deleted again, a blank row and a method used twice still left out, the number order, names that are not rows, a last number posted as a list, a huge number or text. Tried on the sandbox: Add then delete saves to the list; two filled rows are both stored.
