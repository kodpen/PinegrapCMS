---
name: x-rewrite-url-iis-only
description: The request's address is read by pg_request_uri_from_server(): X-Rewrite-URL counts on IIS only, else a client header could replace the routed address and slip past the firewall
---

## Where is the request's address read?
- `pg_request_uri_from_server($server)` in `includes/fn/request_scheme.php`, pure (test `tests/request_uri_test.php`). Every reader goes through it: `get_request_uri()` (`includes/fn/forms.php` and its intended copy in `get_file.php`, both one line), `router_get_request_url()` (`router.php`, which builds `REQUEST_URL` from it) and `pg_proto_link_request_uri()` (`includes/fn/prototype_links.php`, the prototype's Secure Mode redirect).
- `router.php` and `prototype.php` load `request_scheme.php` themselves, defining `PG_FUNCTIONS_DIR` first if needed, as the router's other module loaders do; `get_file.php` and `functions.php` already loaded it.

## What does it choose?
- `HTTP_X_REWRITE_URL` when it is not empty and `SERVER_SOFTWARE` contains `IIS` (ISAPI_Rewrite puts the original pretty address there and `REQUEST_URI` holds the rewritten one); else `REQUEST_URI`; else `PHP_SELF` plus `?QUERY_STRING` when there is one.

## Why only on IIS?
- On Apache, nginx, LiteSpeed and the built-in server nothing sets it but the client: any `X-Rewrite-URL:` request header arrives as `HTTP_X_REWRITE_URL`. Read first everywhere, `curl -H 'X-Rewrite-URL: /other-page' https://site/` made the router serve `other-page` at `/` (a CDN would cache it under `/`), and the firewall's rules on `REQUEST_URI` checked an address the router did not serve.

## What is left reading it directly?
- `includes/oauth/server.php` `pg_oauth_request_resource()` still tries `HTTP_X_ORIGINAL_URL` and `HTTP_X_REWRITE_URL` before `REQUEST_URI` for the resource address it names; only the path is used and it is not a routing decision.
- `pg_proto_link_redact_server()` still redacts the header's value: a token in it must not reach a log whatever the server.

Related: docs/memory/platform/proxy-https-trust.md, docs/memory/core/get-file-shared-code.md
