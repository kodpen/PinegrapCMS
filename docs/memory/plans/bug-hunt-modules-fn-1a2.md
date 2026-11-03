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
- `form_subscription`: open
- `forms`: done #6044 (validate_date() took a date inside longer text)
- `geo`: done #5975
- `google_analytics`: clean
- `google_reviews`: done #6019
- `image`: open
- `image_editor`: open
- `imap_client`: done (#6020)
- `innodb`: #6085
- `lang`: done #6045 (placeholder values re-scanned; rest reviewed: language pick, source, cache, log/staff language)
- `lang_files`: open
- `language_redirect`: open
- `layout_convert`: open
- `layouts`: #6043
- `legal_documents`: open
