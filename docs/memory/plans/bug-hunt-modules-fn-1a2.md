---
name: bug-hunt-modules-fn-1a2
description: Module list of plans/bug-hunt.md, part 1a2: includes/fn/ modules email_campaign_schedule to legal_documents, one line each with its state
---

## Which includes/fn/ modules (email_campaign_schedule to legal_documents) are hunted?
- Done: email_campaign_schedule, email_campaign_send #5934, errors #6017, events, explorer_side, files #4613, form_subscription, forms #6044, geo #5975, google_analytics, google_reviews #6019, image #6055, imap_client #6020, innodb #6085, lang #6045, lang_files #6071, language_redirect #6110, layout_convert #6083, layouts #6043, legal_documents #6112
- `image_editor`: hunted (#6837, VA #PRNO); one fault left, to fix once #6840, #6841 and #6853 (which change `includes/fn/image_editor.php`) have landed: `pg_image_editor_content_allowed()` returns true for every role below 3, so action `content` lets a Manager (role 2) rewrite the content of a designer common region (`cregion_designer_type = 'yes'`), and a role-3 user one they were given; `save_region_content.php` refuses both (role > 1). Fix: the same gate for `cregion`, with a test.
