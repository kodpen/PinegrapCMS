---
name: design-template-text-contrast
description: Text colours and names in the hello-pinegrap and pinegrap-playground templates: no faded text, no primary text on grey sections, one owner name, Parola wording.
---

## Which colours does a template's text use on a band or a grey section?
- Full colour, never `opacity-*`: on a `text-bg-primary` / `text-bg-secondary` band `opacity-75` measured 3.1–3.4:1 in the default look (Modern Soft) and palette (Bootstrap) (#4485).
- On `bg-body-tertiary` the palette's primary is not readable (it is made readable against white only): eyebrows are `text-body-emphasis`, a text link `link-body-emphasis`, a second button a solid `btn-secondary` / `btn-primary`. The hello-pinegrap account bar is `bg-body` (its Log In outline button). Staff bars keep their tint and write `nav-link link-body-emphasis`.
- `tests/design_template_text_contrast_test.php` checks both templates (opacity, primary text and outline buttons under `bg-body-tertiary` outside cards). The opacity check also covers pinegrap-events since its register band's `opacity-75` measured 4.2:1 on Modern Soft + Aurora (1.0.2, #4824); its grey-section check does not (the events template keeps primary eyebrows and figures on `bg-body-tertiary`, which the designer audit does not flag).

## How does the playground keep one owner name?
- The owner is `lang('Jane Cooper')` (Turkish 'Ayşe Yılmaz', a key all templates share); the playground's own sentences that name her ("Hi, I'm Jane.", the footer, alt texts, meta descriptions) are its keys alone and say 'Ayşe' in Turkish. The avatar's initials come from `lang('Jane Cooper')` (`$avatar_url()`), so they follow the language (#4484).
- The about page's menu item is `lang('About me')` like its title; the font project's tag is `lang('Font')`.

## Which word is used for a password in template texts?
- 'Parola' (`core/panel-turkish-terms.md`): the FAQ question and answer and 'Password Reset E-mail' were re-translated with `tr_add.php --replace`, so every template using those keys says 'Parola'.

## Which class does a link inside an alert box take?
- `alert-link`: Bootstrap 5.3.8 gives an alert's own dark link colour only to `.alert-link`; a plain link measured 3.50:1 on `alert-secondary` and 4.06:1 on `alert-warning` (#4898). The comment widget's sign-in and reply Cancel links (PHP default tree, JS starter, every template tree) and the Set Password token messages (`pg_set_password_token_message()` adds the class; the classic `set_password.php` screens keep the bare keys) use it.
- `tests/design_template_alert_link_test.php` walks every template; courses, events and salon are listed as pending in it until their sign-in links change.

## Which colour does a quote attribution take on a dark band?
- `blockquote-footer text-body-secondary`: Bootstrap 5.3.8 gives `.blockquote-footer` a fixed `#6c757d`, 3.29:1 under `data-bs-theme="dark"`; the theme class follows the band (Boutique story band, 1.2.3, #4899). The same test checks it.
