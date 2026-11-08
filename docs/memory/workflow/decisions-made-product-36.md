---
name: decisions-made-product-36
description: Decisions list, product part 36 (2026-11-06 on): one card per submitter in a form list (the Social template's People list)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## How do a member's repeated profile submissions collapse to one card? (#7530, bug #7508)
- Owner (2026-11-06): a form list option "newest record per submitter" (`one_per_submitter`, default off, kept in the widget's config like `max_results`: no column, no migration). On, the list keeps for each `forms.user_id` > 0 only that member's newest record; the count, the paging and the visitor's search run after the collapse, so `__result_count` is the number of cards. Running sites change nothing until a list turns it on; the Social template turns it on in `people_list` and `staff_members_list` (new installs; an existing Social site switches it on in the widget panel). Reason: small, opt-in, no schema, and it fixes both lists. Refused: no product change (My Profile reworded, moderators deleting old records by hand: old pictures stay public until then). A member editing their own record on the site (a new site-side edit path for designed forms with its access rules) is not refused: it may come later as its own plan, with the option kept as the safety net for a form sent twice.
- Architect: newest = the highest `forms.id`; anonymous rows (`user_id` 0) are never collapsed; the record view, the search index and the panel are untouched (old records stay in the database).

## What does Publish do in the visual designer? (2026-11-06)
- Owner (2026-11-06): Publish opens a sheet listing the design's pages with each page's state (draft, on the site, scheduled, a variant held by an experiment) and whether it changed since the last save; the drafts and the changed pages come checked, "Publish selected" writes them in one save, and a draft tab keeps its "Save" (off the site). Reason: the main button saved the whole design but left the drafts as drafts, the draft tab's second button put only that page on the site and "Publish every page" sat in the overflow menu, so a design with several drafts was published page by page. Plan `plans/designer-performance-and-publish.md` (D6).

## Who is the author and what is the status of a record sent through the API? (2026-11-06)
- Owner (2026-11-06): the caller may name the author with an optional `author_user_id` among the staff (the key's owner by default), and a collection form's Status field is written as sent, its allowed values read from `GET /forms/{id}/fields`; a record sent through the API is not forced to start as a draft. Reason: Claude Desktop and other integrations must be able to publish a post in one call; the field's own choices keep the control. Built in PR #8043: `api/api-form-rich-text-in.md`.
