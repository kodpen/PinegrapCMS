---
name: site-from-brief-census-doors
description: Site from a brief census: where a new site's owner lands after the installer, the New design window's starts, and where a brief flow could start for a new or an existing site
---

## Where does a new site's owner land after the installer?
- The installer's last screen has one button, "Control Panel", to the software folder (`install/index.php:6840`); signed in there the administrator opens the panel's start page: the template's `staff` page when a template was installed (`includes/install_design_template.php:323`, `user_home` moved to it), else the dashboard.
- Step 3 "Site Design" offered the starter site or a design template (`platform/install-design-step.md`); a template is installed live there, by a second request (`platform/design-template-install-at-setup.md`).
- The new dashboard shows "Set up your site" first and asks "What is this site for?" (eight purposes: shop, company, association, school, clinic, agency, blog, other, `includes/fn/launch_journey.php:68`); an installed template's purpose is offered (`pg_launch_journey_template_purpose()`, `launch_journey.php:752`; `pg_launch_journey_template_noted()`, `includes/fn/launch_journey_site.php:111`). Stored in the `config_text` row `launch_journey` (`ui/launch-journey-setup-card.md`).

## Where could the flow start?
- New design window, a start "Describe it" in "Start new" (`pg_designer_start_kinds()`, `includes/designer_screen.php:1843`). Its icon is already chosen (`bi-stars`, `designer_screen.php:1823`) and its place drawn (`designer/designer-home-tidy-drawing.md`); no plan built it (`designer/designer-start-import.md`). This works for every site, new or old, and always makes a new design.
- Set-up card: a step or line "Make your site from a description" that opens the New design window with `?start=describe` (`view_system_styles.php?start=<key>`, `designer/new-design-window.md`). The purpose already chosen is the brief's first answer.
- Installer step 3: a third choice beside the starter site and the templates is possible but runs before the panel exists, needs Pinegrap AI set up (Workspace module, application, licence), which a fresh install does not have yet. Not proposed.

## How does a start hand pages to the editor today?
- The Canva start is the closest: its part (`assets/js/designer_canva.js`) stores the pictures, writes sketch pages into the browser's `pg_proto_sketch_new` and opens the new design; the editor takes them as that design's sketch pages (`assets/js/style_designer.src.js:81178`, `:81213`). The colours go into the five questions.
- A sketch page keeps its boxes and "Page details" (address, type, menu, title, description, folder; `designer/sketch-page-details.md`) in the browser only; no table or column.
- "Build the page" makes one draft tab a page, not saved; Save writes the design (`designer/sketch-build.md`). There is no "build every page" today: each sketch page is built by its own button.
- An abandoned start deletes what it stored and nothing uses (`designer/start-abandoned-cleanup.md`).

## What is missing for a whole site?
- A brief: no form asks for the business's name, kind, offer, pages wanted, tone or colours.
- A plan of pages: Describe draws one page from words; nothing proposes the list of pages, their names and addresses, or the menu.
- Building all pages at once and writing all texts in one run: both are per page today.
- A server call that holds many requests in one run: each AI call is one panel request from the browser.
