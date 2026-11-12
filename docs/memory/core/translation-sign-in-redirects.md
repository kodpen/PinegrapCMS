---
name: translation-sign-in-redirects
description: Sign-in redirects and Log Out keep the language of a page in /en/: pg_tr_script_url() adds pg_lang, _pg_sw_page_base() gives LANGUAGE_PATH (#9052)
module: translate, widgets_account
---

## Why can a redirect leave the language?
- The translation adds the language's directory to the links and form actions inside a page it draws (`pg_tr_rewrite_links()`), never to a `Location` header, and never to a link into the software directory (`pg_tr_path_is_file()`).
- Symptom before (#9052): signed out on `/en/hesabim` (private folder) the visitor landed on `/giris?send_to=%2Fen%2Fhesabim`, the Turkish sign-in page; the account menu's Log Out ended on `/cikis?logged_out=true` in Turkish.

## How does a redirect keep it now?
- A designed page named by a redirect starts at `_pg_sw_page_base()` (`includes/fn/widgets_account.php`): `LANGUAGE_PATH` when defined (the router's prefix, or `pg_lang` read by `init.php`), else `PATH`. Used by `pg_sw_sign_in_url()` and `pg_sw_screen_page_url()`.
- A software script the page sends its visitor to gets `pg_lang=<code>` from `pg_tr_script_url($url)` (`includes/fn/translate.php`; source language: unchanged). `init.php` then defines the language for the script, and `pg_tr_redirect_hook()` gives the script's own redirect to a page the language's directory.

## Which addresses carry it?
- `get_page.php`: every redirect to `index.php`, `registration_entrance.php` (guest, registration, custom form, form item view and other gates), `membership_entrance.php` and `logout.php` (logout page type).
- Log Out: the login region's and the account widget's `__logout_url`, and the link a signed-in visitor sees on a sign-in page (`_pg_member_render_signed_in()`). `logout.php`'s own "Do you want to log out?" form posts `pg_lang` on. The logout widget's form is a posted form, which carries `pg_lang` already.
- Not changed: in-page links built with `PATH` (the translation rewrites them) and `pg_sw_page_type_widget_url()`, which e-mails use too.

## Why not register the redirect hook in the router?
- `get_page.php` also answers with redirects that choose a language on purpose (`pg_lang_redirect_page()`, automatic language redirect); a hook rewriting every page-level `Location` would send them back to the page's language.

Tests: `tests/sign_in_page_language_test.php`.

Related: docs/memory/designer/member-widgets-handlers-and-forms.md, docs/memory/commerce/express-order-script-language.md
