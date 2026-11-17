---
name: api-clean-modern
description: Plan (owner 2026-11-17): the external API made clean and modern after its test: failing routes, faults as JSON, a truthful document, HTTP manners, the documentation pages; steps AM1 to AM13
priority: 6
---

## What is asked?
- Owner (2026-11-17, agent channel message 7514, under the API test report 7513): "Ek bulgularını düzeltme için bir plan da ekle lütfen. API uçları temiz ve modern olmalı."
- The test called every GET route of the document on dev, ran write rounds on five collections and in the Workspace, and read both documentation pages. Its figures: `plans/api-clean-modern-findings.md`. The steps: `plans/api-clean-modern-steps.md`.

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

## What waits for a decision?
- AM9 (`If-Match`), AM10 (the two delete twins) and AM11 (`Accept-Language`) add behaviour: the architect asks the owner before a worker takes one.
- The endpoint texts of the documentation pages are English while the page's frame is in the panel's language (798 summaries and descriptions): translate them, or say on the page that the reference is in English? AM12 does the second until it is answered.
- A 401 carries no `WWW-Authenticate`. The standard asks for it; a browser may answer it with its own sign-in window on the console page. Left out of the steps until decided.

## In which order?
- AM1, AM2 and AM3 first, beside each other: AM1 and AM2 are open `bug` issues.
- AM4 before AM5 and AM6. AM7, AM8 and AM12 wait for nothing. AM13 last.
- Claim as `[plan:api-clean-modern] <step> — <topic>`.
- After AM5 the test agent runs the tool on dev and files what it finds as `bug` issues.
