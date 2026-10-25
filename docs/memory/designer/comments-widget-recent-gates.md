---
name: comments-widget-recent-gates
description: Which source pages a list of recent comments reads and why one adds nothing (sign-in pages included), who sees which comment, the record gate in SQL, 'mine' from the account's creation
---

## Which pages add comments?
- `pg_comments_recent_source_decision()` (pure, `includes/fn/widgets_comments_recent.php`): a top-level Form Item View with a form adds its records' threads, any other page its own (`''` / 0).
- Refused silently, in order: missing, binned, archived (unless editable), a folder the visitor may not open (live `check_view_access()` without the registration/guest grant; background: public only), `sign_in`, comments off (Preview's unsaved settings count), classic (`pg_comments_widget_classic_thread()`), nested record view on a page source, no form, a view filtered on the viewer (`pg_sw_filters_compare_viewer()`, shared with the search index) unless signed in, pending without edit access, mine signed out.

## Which pages are refused as sign-in pages?
- get_page.php sends a visitor who is not signed in to `registration_entrance.php` before it asks the folder for 'my account', 'my account profile', 'view order', 'affiliate sign up form', and a 'custom form' with auto_registration 0 that grants a trial membership (membership 1, membership_days > 0) or private access (private with a private_folder_id). `pg_comments_recent_sign_in_page()` (pure) is that rule; such a source adds nothing for a visitor who is not signed in and in every background render (Preview, SEO), so the anonymous page cache never keeps it.
- The custom form's row (`get_page_type_properties()`) is read only for such a visitor.

## Who sees which comment?
- Published: `published = 1`, staff too. Pending: `published = 0`, scheduled too, on editable pages.
- Mine: the account's `created_user_id`, any state (also earlier sessions' waiting ones; session ids are never read, a session survives a sign-in), written since the account was made: `comments.created_timestamp >= user.user_created` (`pg_comments_recent_account_since()`, probed with `pg_schema_has()`). Deleting an account keeps its comments' `created_user_id`, and MySQL 5.7 hands the highest user id out again after a restart; the bound keeps a new account from listing a deleted member's waiting comments. `user_created` 0 (accounts made before 2026.4.4) adds no bound.
- Record gate in the statement, before the LIMIT: the view's form, `forms.complete = 1`, the access setting (`pg_comments_recent_access_sql()`; submitter_only follows user and form editor only), the view's filters strictly (`pg_sw_list_record_filter_clause()`). Closed: no statement.
- Staff values only for staff of the comment's page. No parent, quote, file, seller reply.

## How is it checked?
- `tests/comments_recent_test.php`: the decision table (sign-in rows), `pg_comments_recent_sign_in_page()` table, audience SQL with and without the creation bound, the read passing it to every statement.
