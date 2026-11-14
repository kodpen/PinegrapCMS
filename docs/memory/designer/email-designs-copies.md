---
name: email-designs-copies
description: Pages offered as copies of a new e-mail design (MA5d2 #9542): the installed template's e-mail pages and earlier campaigns' pages, which are left out, what a copy keeps
module: email_design_copies
---

## Which pages are offered?
- `pg_email_design_copy_list()` (`includes/fn/email_design_copies.php`), at most eight of each kind, on the Templates tab's E-mail designs card for role <= 1 (`ui/messages-area-templates-tab.md`).
- The template's: for each design with `style.style_template` (the ten newest), the template's e-mail pages (`pg_design_template_email_pages()`) found among the design's pages by the name the template gives, or that name numbered when it was taken (`pg_email_design_template_matches()`, pure). The name is the template's in the current language: a site installed in another language finds none.
- The campaigns': the pages of manual campaigns (`email_campaigns.page_id > 0`), the latest used first, each once under its latest campaign's subject.

## Which are left out?
- `pg_email_design_copy_source()` refuses a page in the e-mail designs folder or an e-mail design already (those are "Your e-mail designs"), and a page that places a system widget (a shared component with `system_region_config`): the order, form or comment it draws is given by the mail that sends it, and a campaign has none. Most template e-mail pages are such pages; the newsletter welcome is not.

## What does a copy keep?
- "Make a copy" opens `add_system_style.php?start=email&copy=<page_id>`: a new e-mail design whose tab starts with the page's tree, `props.pgEmail` set on its root (`pg_email_design_copy_tree()`, pure). The source page is never changed.
- Shared components stay placed (`shared_ref`): they are rows of their own, so the copy draws the template's header and footer, and an edit of one reaches every page that places it.
- Its elements are no mail blocks, so the check line marks the outermost one (`email-designs-parts.md`); the mail pass keeps their inline styles and takes the page's CSS out.

Test: `tests/email_designs_test.php`, `tests/messages_templates_test.php`.

Related: docs/memory/designer/email-designs-ready.md, docs/memory/designer/email-designs.md
