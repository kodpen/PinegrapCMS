---
name: build-url-host-parts
description: build_url() keeps the port and user information of the URL it is given unless a hostname property replaces the host.
module: core
---

## What does build_url() keep from the given URL?
- Scheme, host, port, user information, path, query and fragment, each unless a property replaces it. A `hostname` property replaces host, port and user information together (`HOSTNAME` already carries the port).

## Why?
- It rebuilt the host from `parse_url()`'s `host` alone, so a form's Send To address such as `https://example.com:8443/thanks` redirected to the same host on the default port after submitting (`custom_form.php`, the save confirmation). Fixed in the core bug hunt (PR #PRNUM); tests in `tests/build_url_test.php`.
