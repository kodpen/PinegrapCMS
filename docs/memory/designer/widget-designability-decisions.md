---
name: widget-designability-decisions
description: The owner's 2026-10-26 answers for widget designability: W16 old blocks equal in look and behaviour (not bytes), W5 gallery sources, W8 entity fields (who sees what, secrets, yes/no, words).
---

## W16: must old blocks stay byte-identical?
- No (owner, 2026-10-26, option B). The visitor sees the same blocks in the same place with the same words in the site's language, and forms keep working: field names, ids and the markers page scripts look for are kept. Hidden source details may change (a block's inline `<style>` may move to a stylesheet).
- Why: running sites keep working through equal look and behaviour, not equal bytes. Byte equality would keep a second renderer beside the parts and keep the words untranslatable, against the owner's direction of the same day (translatable words, design elements over placeholder tokens).
- Proof W16 owes: a test comparing old and new output by visible text, classes and form fields; the e-mail versions stay e-mail safe.

## W5: where does the layout gallery take layouts from?
- Owner, 2026-10-26, option 1: Default, From templates, On this site, filtered to layouts that look and work right in this design: a template's styled layouts only in a design made from that template; a form layout only when the widget's form has the same fields.
- A layout taken from another widget on this site is copied, never linked. Nothing changes until Keep.
- Why: a layout offered must look and work right where it lands, and a copy leaves the other widget's page as it was.
- Not in W5: template layouts with their styles from every template, an online library.

## W8: which entity values may a widget show?
- Owner, 2026-10-26, option A: the allow-list in [[widget-entity-fields]].
- Account, order and address data show only on the person's own pages and e-mails.
- Never: password and its hint, session and secret keys, device PIN, card number, CVV, expiry, card holder, approval code, IP, referrer, UTM codes, internal notes, staff remarks, roles and rights, accounting and marketplace ids.
- Yes/no values come as conditions (`show_if`), not as text. Choice values (Monthly, Refunded, Business address) come as translated words. New shop-written text (keywords) enters the site translation.
- A field once on the list is never removed: sites that bound it would go empty.
- Why: a widget shows what the classic layouts already showed the same person, and nothing a visitor could use against the shop or another customer.
