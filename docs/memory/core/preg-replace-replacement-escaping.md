---
name: preg-replace-replacement-escaping
description: Text put into a page through preg_replace() goes through pg_regex_replacement(): $NN and \N in it are back references, "$400'dan" printed as "0'dan"; where it applies and the guard test
---

## What went wrong?
- PCRE reads `$n`, `${n}` and `\n` (up to two digits) in a preg_replace() replacement as back references. A reference to a group the pattern does not have prints nothing.
- kodpen.com's meta descriptions held prices in dollars: "Kurumsal web tasarım $400'dan." became "Kurumsal web tasarım 0'dan." (`$40` dropped, "0" left), "$50/yıl'dan başlar" became "/yıl'dan başlar". The English copy showed "from 0.". The body text was fine because it is not passed as a replacement.
- It looked like a data binding printing "0" for an empty price. It was not: no binding was involved. The meta block was escaped in 60740e72 (2026-10-19). That commit was not on main yet, so the live site still showed the bug.
- CSS has the same problem: `content:"\201C"` reads `\20` as a reference.

## What is the rule?
- Any text that is not a fixed literal (page content, settings, product or record text, JSON-LD, CSS, paths) goes into a replacement as `pg_regex_replacement($text)` (includes/fn/core.php, `addcslashes($text, '\\$')`). A reference the caller writes itself stays outside: `'$1' . pg_regex_replacement($html)`.
- Do not wrap text given to `str_replace()`. It reads nothing, and the added backslashes would print.
- `preg_replace_callback()` or `str_replace()` avoid the question when no pattern is needed.

## Where is it applied?
- Every variable replacement in `get_page_content.php` and `get_page.php`: regions, menus, title, meta tags, robots and verification tags, JSON-LD (the system widget catalog and product blocks were unescaped), messages, RSS link tags, stylesheet links, analytics and editor code, path placeholders, PDF addresses, the toolbar, theme preview CSS.
- The catalog item view's variant picker (`widgets_catalog.php`, attribute option labels) and the cart's applied-offers block (`widgets_cart.php`, which escaped `$` but not the backslash).
- `seo.php`, `meta_tags.php` and the designer's head code put no variable text into a replacement. Their replacements are literals or language codes.

## How is it kept?
- `tests/regex_replacement_test.php`: "$400'dan", "$50/yıl", `\1`, `${1}` and CSS escapes arrive as written. A token scan also fails any `preg_replace()` in `get_page_content.php` or `get_page.php` whose replacement is neither a plain literal nor a `pg_regex_replacement()` call.
- PR #1203.
