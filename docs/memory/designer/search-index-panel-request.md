---
name: search-index-panel-request
description: How update_search_index.php guards a panel request (POST with the form token, #5819) and closes the session before drawing pages (#5817)
---

## Which panel request may start an index run?
- A web request (not `PG_CRON_DISPATCH`) must be a POST carrying the session's form token (`pg_search_index_web_request_valid()`, pure); a GET or a missing token gets the session-expired error (#5819: a cross-site link started a crawl in a manager's session). The general job opened in a browser (`job.php`) dispatches it without either.

## Why does a run not hold the manager's session?
- After reading the user id and the token it calls `session_write_close()` and empties `$_SESSION` before the role check and any render (#5817: `unset($_SESSION)` kept the session lock, so every other panel request of that session waited the whole 20 s step). Closing writes the session unchanged; the manager stays signed in.


Test: `tests/search_index_web_request_test.php`.
