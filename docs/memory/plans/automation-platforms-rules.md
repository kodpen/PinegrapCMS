---
name: automation-platforms-rules
description: Plan automation-platforms: what the API and Application Access do today that no step may lose (K1 to K9), the rules every step keeps, what a step's PR body carries
---

## What may no step lose?
- (K1) Nothing a running client relies on changes: a step adds a route, a parameter, a field or a header. A step that would change what an answer means is asked first (Working mode 10).
- (K2) `webhooks:manage` stays out of every OAuth connection (`pg_oauth_ceiling()`; architect, agent channel message 7524): an event stream is the whole site's, a connection one person's.
- (K3) A subscription is made in one place with its checks: the address tried when it is stored (`api_http_check_url()`), names of the catalogue only, the limit, the secret readable once.
- (K4) Deliveries are queued, never sent in the request; signed; six attempts; held rows go out only on Retry (`api/api-webhook-queue.md`).
- (K5) The Site Log names a subscription's address as scheme and host only (`api/api-webhook-log-address.md`): an address a platform gives carries its secret in the path. The new form, its messages and its list keep to that.
- (K6) Application Access: a row is made only by `api_app_create()`; a row of a higher-ranked owner is locked, its webhook buttons with it (`api/application-access-keys.md`).
- (K7) One route row, one presenter; a new error code is explained in `api_error_catalogue()` (`api/api-endpoint-rules.md`).
- (K8) Every call an app makes has a POST form: IIS answers PATCH and DELETE before PHP (`api/external-api-iis-environment.md`).
- (K9) No event from a bulk path, and a catalogue line says what is not announced (`api/api-webhook-events.md`). A sample goes to its one subscription only: it is never announced, so no flow, channel or other subscriber hears it.

## Which rules does every step keep?
- A pure function gets its test first; `php tools/check_api_schema.php` green; PHP 7.1 to 8.5 (`bash tools/static_analysis.sh`).
- Texts through `lang()`, the Turkish with `php tools/tr_add.php`.
- No schema change; a need for one comes back as a question to the architect.
- About 1,000 product lines a step. A step that finds its feature cannot be built as written stops and asks; it does not leave the feature out.
- Application Access is an old screen (`panel-old-screens-census-site-system.md`): a new part is drawn with look A's recipes (`ui/README.md`) and moves nothing else of the screen.

## What does a step's PR body carry?
- A table of the A and K numbers its step names, each with the test or the picture that shows it.
- For a screen: pictures at 1440, 1024 and 390, light and dark, beside AU8's sheet, with the longest Turkish labels and an address of 500 characters.
- For a route: the request and the answer of one real call on a throwaway install, and the same call refused.
- In words: what could not be verified.
