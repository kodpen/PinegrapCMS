---
name: design-template-magazine-a11y
description: Magazine accessibility: ink focus rings on links, buttons, fields and the shelf, headings that never repeat, e-mail frames light in the dark theme, the core items left open.
---

## How does focus stay visible?
- Bootstrap's focus is a half-clear shadow (`base.css` `.btn:where(:not(.btn-link)):focus-visible`, fields 25%): 1.1 to 1.5:1 on paper and in the dark theme. Own rules draw a 2px `--bs-emphasis-color` outline: `body .btn:focus-visible` (0,2,1 beats the theme's 0,2,0), `body .form-control:focus`, `body .form-select:focus`, `body .form-check-input:focus-visible`, and `body .page-link:focus-visible,body .btn-close:focus-visible` (not `.btn`: Bootstrap's `.page-link:focus` and `.btn-close:focus` set `outline:0`; Every story's page links, the drawer's Close). Tokens only, no `!important` (test 15).
- `.pm-navlink` an inset ink outline (the words keep their ink), `.nav-underline .nav-link` an ink ring.
- Shelf: `scroller-cards` with `role=region`, a name, `tabindex=0`. Chromium scrolls a link into view only when wholly hidden, so `.pm-shelf:has(a:focus-visible){scroll-padding-inline:25%}`. Its ring is inset: the row's negative margins put an outer one off a phone screen.
- The News template has the same button, field, page link and drawer close gap (its rule covers only its top bar and masthead): a follow-up there.
- The AI summary links stay filled `btn-secondary` pills, the news block unchanged: text buttons are `btn-primary` or `btn-secondary`, never outline (3.29:1 in dark under the plain palette).

## Why does no heading repeat?
- Home's sections band and the search aside say "Browse the sections"; "Sections" is the footer column and the masthead nav's name. The canvas audit warns on equal heading texts, and a screen reader user would hear two "Bölümler" leading to different lists. Test 28 checks home, search, Every story and the story page.
- Most read opens with `div.pm-sec-head` like every band head, so beside or under "More from the magazine" it has its rule.

## Why is the e-mail frame `data-bs-theme="light"`?
- Its text colours are written inline for a light card. Under a dark page theme `bg-body` and `bg-body-tertiary` (`!important`) turned the card dark: 1.02:1 when an editor opens the page in a browser. Sent e-mails carry no theme. The News `$mail_page` has the same frame: a follow-up there.

## Which items are the platform's, not the template's?
- The list search box: an icon button without a name, an input without a label, and an icon-only clear link without a name (`widgets.php`). Under the plain palette the `account_security` starter tree's per-device sign-out buttons are `btn-outline-secondary`, 3.28:1 in dark. No h1 on the sign-in pages (the widget prints h4). The account security card's heading order and `btn-outline-danger` at 3.4:1 in dark. CKEditor's oEmbed loads jQuery 1 and throws `noConflict` on Submit a Story. The canvas' comments placeholder at 2.0:1 (editor only).
- The rich text editor's editing iframe on Submit a Story, once without a title, now carries the field's caption (`ui/rich-text-editor-names.md`).
