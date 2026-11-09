---
name: file-name-comma
description: A comma in a file name: stored as _ since 2026.4.1; a file stored before keeps it and loads only with the quoted Content-Disposition of pg_http_content_disposition(), else Chrome refuses the answer
---

## What broke?
- A file with a comma in its stored name did not load from its own address: an `<img>` stayed empty and opening the address showed Chrome's `ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION`. Reported on a 2026.4.9 site (2026-11-09).
- Why: every release up to 2026.4.10 sends `Content-disposition: filename=<name>` from get_file.php without quotes. A header value may be a comma-separated list, so Chromium (Chrome, Edge) reads `filename=a,b.jpg` as two different values and refuses the answer. Percent-encoding the comma in the address does not help: the header carries the decoded name. Reproduced in Chromium 152 with that header; the same name in quotes loads.

## Where does a stored comma come from?
- Not from an upload since 2026.4.1: `prepare_file_name()` runs `pg_ascii_file_name()`, which turns every character outside `A-Z a-z 0-9 . _ - [ ]` into `_` (`a,b.jpg` is stored as `a_b.jpg`). Checked on dev on 2026-11-09 through the File Manager's `explorer_upload` and the product screen's `add_file.php` (`image_profile=product`): both stored `_` and served 200.
- Before 2026.4.1 only a space, `&`, `+` and `#` were replaced, so a file uploaded then keeps its comma after an upgrade, and the router still finds it by that name.
- Such files are not renamed: their address works once the name is quoted, and a rename would break every link to them.
- On a site before 2026.11.0 the file loads again after a rename in the File Manager (stored with `_`) and picking it again wherever it is used: a rename rewrites only its files row, not the products or pages that name it.

## What sends the header now?
- `pg_http_content_disposition($name, $inline)` in `includes/http_range.php`, which functions.php and get_file.php both load: `inline` or `attachment`, the name in quotes with `"`, `\`, CR and LF removed, and `filename*=UTF-8''…` (RFC 8187) beside it when the name is not plain ASCII (old names such as `Özet.pdf`).
- get_file.php's answer from the disk uses it for both headers (#8379). The quotes first came on 2026-10-19, written inline and untested, so only 2026.11.0 carries them. The object store's `pg_file_serve_disposition()` calls it after its active-type rule (`object-storage-active-types.md`).

## Which other paths handle the comma?
- `pg_img_local_file_name()` refuses a `src` with a comma, so such an image gets no `srcset` and no WebP copies, only its plain address.
- The product feeds write a comma inside a URL as `%2C` when they join a list cell.
- The API's `api_send_file()` and Workspace downloads quote the name and add `filename*` themselves.

## How is it tested?
- `tests/file_name_comma_test.php`: the upload rule, one header value however the name is cut at commas (as Chrome cuts it), the UTF-8 form, and a guard that get_file.php builds no Content-Disposition by hand.

Related: docs/memory/platform/stored-file-name-upload-rule.md, docs/memory/core/get-file-shared-code.md
