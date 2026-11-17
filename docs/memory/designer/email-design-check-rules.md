---
name: email-design-check-rules
description: The editor's check list (Audit panel) on an e-mail design or a page sent as an e-mail: which web page rules it leaves out (main, landmarks, skip link, SEO score) and why (#10069)
---

## Which pages are checked as an e-mail?
- `_sdPageIsEmail(page, root, pages, configs)` in `style_designer.src.js`: an e-mail design (root `props.pgEmail`), a comment e-mail page (`_sdCommentsEmailPageOf()`), or a page a widget setting ending in `_email_page_id` names (order receipt, welcome, password reset, a form's replies), by id or `tab:<key>`. `_sdActivePageIsEmail(root)` feeds it the open page, `_pages` and the `system_region_config` of the loaded shared components.

## What is left out on an e-mail?
- `_sdAuditDropsOnEmail(code)`: the editor rules `main_missing`, `main_multiple`, `landmark_missing` (in `validateTree()`) and the accessibility code `a11y_main_missing` ("No main content area to skip to", filtered in `_renderAuditConcepts()`).
- The SEO section shows "An e-mail is not indexed by search engines: it gets no SEO score." instead of the score and its Title/Description lines. `seo_check` is still asked: its answer carries the accessibility findings.
- Why: a mail program draws no `<main>` or landmarks, and one dropped into an e-mail design raises "Not an e-mail block", so the count of an untouched New e-mail design never reached zero (#10069, PR #10162).

## What stays?
- Every other rule (headings, alt text, links, contrast) and the e-mail's own `mail_block` line (`_sdMailCheckIssues()`). Web pages are checked as before.
- A page whose naming widget sits in a shared component not loaded yet is checked as a web page until it loads.

Test: `tests/email_design_check_rules_test.php` (the Node-run functions and the wiring).

Related: docs/memory/designer/email-designs-editor.md, docs/memory/designer/seo-check-draft-render.md
