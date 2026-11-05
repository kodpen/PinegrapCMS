---
name: object-storage-active-types
description: A stored HTML, XHTML, SVG or XML file is redirected to the object store as an attachment, never inline: the store cannot send the sandbox policy get_file.php adds to a local answer
module: file_storage
---

## How does the store answer a stored .html, .svg or .xml?
- As an attachment. `pg_file_serve_disposition()` (`includes/file_store_serve.php`) turns `inline` into `attachment` for names ending in html, htm, xhtml, xht, svg, svgz, xml, xsl, whatever the caller passed.

## Why not inline like a PDF or a picture?
- A local answer of these types carries `Content-Security-Policy: sandbox` and nosniff (get_file.php), so a script inside runs in an opaque origin. A presigned address can only override type, disposition and caching headers, not CSP: inline, an uploaded page or SVG ran its script on the store's host (a sibling subdomain when the store is self-hosted under the site's domain). Before the fix the redirect asked for `inline` with the real type.
- An `<img>` or CSS reference ignores Content-Disposition, so SVG logos keep showing; only opening the file's own address downloads it.

Tests: `tests/object_storage_serve_active_types_test.php`.
