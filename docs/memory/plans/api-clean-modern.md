---
name: api-clean-modern
description: Plan (owner 2026-11-17): the external API made clean and modern after its test: failing routes, faults as JSON, a truthful document, HTTP manners, the documentation pages; steps AM1 to AM13
priority: 6
---

## What is asked?
- Owner (2026-11-17, agent channel message 7514, under the API test report 7513): "Ek bulgularını düzeltme için bir plan da ekle lütfen. API uçları temiz ve modern olmalı."
- The test's figures: `plans/api-clean-modern-findings.md`. The steps: `plans/api-clean-modern-steps.md` (AM1 to AM6) and `plans/api-clean-modern-steps-manners.md` (AM7 to AM13).

## What does "clean and modern" mean here?
- An answer is always the JSON envelope, a fault too.
- The document says what the answers hold, and a tool proves it against a running site.
- The HTTP manners a client library expects are there: HEAD, `Location`, every wrong field named, a write that can refuse a stale copy.
- One resource, one shape: the same twin for every delete.
- The documentation pages open with the conventions and can be walked by their headings.

## What stays as it is?
- Nothing a running client relies on changes: a step adds (a header, a field, a route) or corrects the document. A step that would change what an answer means is asked first (Working mode 10).
- PATCH and DELETE stay behind their POST twins: IIS answers both 405 before PHP (`api/external-api-iis-environment.md`).
- The query string stays unchecked and `fields=` keeps ignoring an unknown name (`api/api-endpoint-rules.md`).
- `POST /webhooks/{id}` and `POST /workspace/scheduled-messages/{id}` keep deleting.
- No version in the address: the API grows by adding.
- The 64 lists that answer whole (lookups, a record's own rows) keep doing so.
- Field descriptions get no sweep (2,989 fields have none): AM6 adds only what a builder can generate.

## What did the architect settle?
- Architect (2026-11-17, channel message 7552): AM9 `If-Match` is built: opt-in by a header, nothing changes without it.
- Architect (2026-11-17, channel message 7552): AM10's delete twins are built: the `api/` key rule already asks for a `POST …/delete` twin whose id ends in `_post`.
- Architect (2026-11-17, channel message 7552): the endpoint texts stay English: the MCP tool a model reads prints the same text, and user documentation is English only (`api/api-openapi-declarations.md`). AM12 writes one translated line saying so.
- Architect (2026-11-17, channel message 7552): `WWW-Authenticate` is no question: `api_fail_unauthorized()` sends it unless `X-Requested-With` marks a script-made call. Only `credentials_missing` lacks it while OAuth is off: AM7.

## What waits for a decision?
- AM11 (`Accept-Language`) alone, held by the architect: browsers and phones send that header by themselves, and a call-wide language also reaches what the call stores or sends. The step opens with a census; the owner is asked only if the choice can be kept to the answer.

## In which order?
- AM1 and AM2 are open `bug` issues and go ahead of any step; AM3 beside them.
- AM4 before AM5 and AM6. AM7, AM8, AM9, AM10 and AM12 wait for nothing. AM13 last, with or without AM11.
- Claim as `[plan:api-clean-modern] <step> — <topic>`.
- After AM5 the test agent runs the tool on dev and files what it finds as `bug` issues.
