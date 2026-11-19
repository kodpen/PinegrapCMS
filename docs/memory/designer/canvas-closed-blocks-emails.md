---
name: canvas-closed-blocks-emails
description: Designed e-mail pages and their closed parts today: the comment e-mail's System region drawn with a note (CB3), the fixed markup kept for a page with no card, the other e-mails' widget slots
---

## Which e-mails are pages the canvas draws?
- Welcome and password reset (Account E-mail widget, `account-email-pages.md`), form submitter and administrator e-mails, comment submitter and watcher e-mails, order receipt (Order View widget), gift card, recurring profile disabled, reward program, campaign profiles. Each is drawn by `get_page_content(..., $email = true)`.
- Only the comment e-mails pass fixed markup (`$extra_system_content`, `includes/fn/mail.php`); the others draw only their page tree.

## What does the canvas show of a comment e-mail's System region?
- The region's label box with a note saying what it prints on a comment e-mail page (CB3, #9685, [[comment-email-pages-system-region]]); the Comments widget card above draws in the E-mail state (`comments-widget-email-design.md`).
- On the site it prints the fixed comment markup with its View or Reply link, unless the card on the page drew the comment and claimed it (`comments-widget-email.md`).

## What stays closed, and why?
- The fixed markup itself: it stays for a page with no card, so nothing changes on a running site. Owner (2026-11-01): a comment e-mail page gets the card and keeps its System region (`workflow/decisions-made-product-16.md` (a)); the 57 templates keep it.
- A user can only delete the region (the card must then always draw).

## What else is closed in an e-mail?
- Only the slots the widget it carries keeps closed (`canvas-closed-blocks-widgets.md`); the order receipt's return actions open as the return moves part (W16).
- The e-mail width, its mail-safe styles and a mail check are missing editor parts, not closed blocks (`campaign-email-body-way.md`).
