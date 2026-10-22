---
name: designer-collab-locking
description: Two people in the visual designer: designer_presence / designer_page_lock tables, atomic lock claim, staleness, same-user takeover, and why the lock is enforced on the save path.
---

## What backs concurrent editing?
Core `includes/designer_collab.php`; endpoints `api.php` -> `designer/presence|leave|lock_release|comment_*`; client `_collab*` block in `style_designer.js`.
| Table | Key | Holds |
|---|---|---|
| `designer_presence` | `uniq_session(session_key)` | one row per open editor **tab**, 20 s heartbeat |
| `designer_page_lock` | `PRIMARY KEY(page_id)` | who is editing the page |

## What are the locking rules?
- **Identity is the tab.** Key lives in `sessionStorage` (`pg_sd_tab`); one person's two tabs must not fight over one lock.
- **Lock is taken with one `INSERT ... ON DUPLICATE KEY UPDATE`.** SELECT-then-INSERT races. Takeover condition: `(session_key = <mine> OR last_seen < <stale>)`.
- **`session_key` goes LAST in the SET list.** MySQL evaluates assignments left to right; if the key updates first, every later condition says "mine" and the lock goes to whoever asks.
- **The lock is an authority.** `pg_collab_may_edit_page()` is called on the **save path** (`includes/designer_screen.php`). Disabling the Save button is not an authorization check; the POST need not come from that screen. The external API (`includes/api/resources/design.php`) and the AI assistant (`includes/designer_ai.php`) call it with an empty key: they write a page only while no editor tab holds it.
- **A locked page is skipped, not a save failure**, and reported as a warning. The editor saves all tabs at once.
- **Staleness (`PG_COLLAB_STALE`, 65 s) is the real retirement mechanism**; `leave` / `lock_release` only speed up handover. A killed browser cannot say goodbye.
- `pg_collab_ready()` probes both tables; on a non-upgraded install the endpoint returns `ready:false` + `may_edit:true` and the editor behaves as before.

## Who may take over a lock?
- Takeover is only from **the same user's other tab**. Because the lock belongs to a tab, the same person opening a second tab falls into view mode; if `holder.user_id === sdDesign.userId`, the banner and modal offer "Tasarımı devral" (`_collabTakeOver` -> `_collabBeatNow(true)` -> `presence` + `takeover: 1`).
- Server `pg_collab_claim_page(..., $take_over_own)` adds `OR user_id = me` to the handover condition: **another user's lock never drops this way**, whoever sends the flag.
- The warning is unconditional: the other tab's unsaved work cannot be known (a closed/frozen tab cannot answer), so the operator is asked. The displaced tab drops to view mode at its next heartbeat, its work stays in memory, Save closes.

Related: docs/memory/designer/designer-collab-view-mode.md, docs/memory/designer/designer-collab-notes-sync.md
