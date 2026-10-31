---
name: search-index-run-failures
description: How update_search_index.php reports the record lists and records it left out: Site Log line, warning in the finish window
---

## What did it fix?
A run that left designer records out (a list's query or a record's draw threw) still said "The Search Index has been updated successfully!"; only one `error_log` line knew.

## What does a run write now?
- Each failure is counted (`$designer_failures`, both catch blocks of the record branch, [[designer-records-site-search]]); the first five are named with `pg_search_index_failure_note()` (`includes/fn/site_search.php`, pure): `<page> (<reference code>): <message>`, a list's host page without a code, the message on one line and cut to 200 characters (UTF-8 safe).
- After the record loop: one Site Log line, `log_activity()` with `lang()` (`{var:1}` the count, `{var:2}` the notes joined by `; `, `; ...` when more failed than were named), as the run's user from the panel button, no user from the job. The `error_log` line is gone.
- The finish window: a warning icon and "updated, but N record list(s) or record(s) failed to load and were left out. The Site Log names them." instead of the success sentence. `#finish_message` keeps its id: the status region copies its text.

## Which tests cover it?
`tests/designer_search_index_test.php`: the note's labels and cut, source checks of both catch blocks, the log line and the finish window.
