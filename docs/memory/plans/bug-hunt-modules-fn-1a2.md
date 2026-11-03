---
name: bug-hunt-modules-fn-1a2
description: Module list of plans/bug-hunt.md, part 1a2: includes/fn/ modules email_campaign_schedule to legal_documents, one line each with its state
---

## Which includes/fn/ modules (email_campaign_schedule to legal_documents) are hunted?
- `email_campaign_schedule`: clean
- `email_campaign_send`: done (#5934)
- `errors`: #6017
- `events`: clean
- `explorer_side`: clean (gates match view_folders.php, view_product_groups.php and the menu rows; ids filtered; markup escaped; script builds rows with textContent)
- `files`: done #4613
- `form_subscription`: clean (read twice: link signing and parsing, link states, hold and daily resend limit, confirmation writes)
- `forms`: done #6044 (validate_date() took a date inside longer text)
- `geo`: done #5975
- `google_analytics`: clean
- `google_reviews`: done #6019
- `image`: #6055
- `image_editor`: open
- `imap_client`: done (#6020)
- `innodb`: #6085
- `lang`: done #6045 (placeholder values re-scanned; rest reviewed: language pick, source, cache, log/staff language)
- `lang_files`: done #6071 (non-UTF-8 value emptied a shard; rest reviewed: shards, split, add, merge3, checks, cache)
- `language_redirect`: open
- `layout_convert`: #6083
- `layouts`: #6043
- `legal_documents`: #6112
