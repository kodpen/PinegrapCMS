---
name: bound-href-scheme
description: A form field bound to a link's address never prints a javascript:, vbscript: or data: address
module: widgets
---

## What happens to a field value that starts a link's address?

`pg_sw_apply_tokens()` checks a form field filled at the start of an
`href`, `action` or `formaction` value (`pg_sw_token_starts_address()`).
When the value opens with `javascript:`, `vbscript:`, `livescript:` or
`data:` (`pg_sw_address_runs_script()`: entities decoded once, control
characters and spaces dropped, any case) the address becomes `#`; the
link's text still shows the value. Web, mail, phone and relative addresses
print as before.

## Why there and not in the template?

Any designer link bound to a text field (`_bindings.href`) printed what a
visitor typed: the Marketplace seller application's website
(`<input type=url>`, stored as a text box, no server check) was a link the
staff clicked, so `javascript:` ran in the site's origin with their
session (#7516). One check in the fill covers every widget and template.

## What is left alone?

`$extra` values (ids, detail URLs, widget state) are the renderer's own and
are not checked; a token after a fixed start (`href="/x/^^id^^"`) cannot
pick the scheme.
