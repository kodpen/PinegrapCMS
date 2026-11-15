---
name: canvas-closed-blocks-emails
description: CB0 list, the designed e-mail pages and what in them is closed: the comment e-mail's System region with its fixed markup, the other e-mails' widgets, and what opening takes
---

## Which e-mails are pages the canvas draws?
- Welcome and password reset (Account E-mail widget, `account-email-pages.md`), form submitter and administrator e-mails, comment submitter and watcher e-mails, order receipt (Order View widget), gift card, recurring profile disabled, reward program, campaign profiles. Each is drawn by `get_page_content(..., $email = true)`.
- Only the comment e-mails pass fixed markup (`$extra_system_content`, `includes/fn/mail.php`); the others draw only their page tree.

## What is closed in a comment e-mail?
- The System region after the comment card: on the site it prints the fixed comment markup with its View or Reply link, unless the Comments widget card on the page drew the comment and claimed it (`comments-widget-email.md`).
- Canvas: the System region's label box; the card above draws in the E-mail state (`comments-widget-email-design.md`).
- Change today: nothing in the fixed markup; delete the region (the card must then always draw).
- Use: 57 templates, most after the `email_comment` card.
- Opening: when the card always claims (a comment it can draw), the region is dead weight; the step is a save warning or a template update removing it, not a tree. Whether the fixed markup may go on sites without a card is a question for CB1.

## What else is closed in an e-mail?
- The closed slots of the widget it carries (`canvas-closed-blocks-widgets.md`), such as the order receipt's return actions.
- The e-mail width, its mail-safe styles and a mail check are not closed blocks but missing editor parts (`campaign-email-body-way.md`).
