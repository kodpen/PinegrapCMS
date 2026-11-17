---
name: automation-platforms-features
description: Plan automation-platforms, features A1 to A13: the contract the apps rely on, newest-first lists, the subscription limit, event fields and samples, the panel's subscription form, the guide
---

## What do the apps rely on?
- (A1) One list in code of the first triggers, actions and searches. A trigger: its events, the route of its record, the list that gives its newest records. An action or a search: its route. Every app is written from it.
- (A2) Nothing the list names is renamed or removed once an app is public: a route, an event, a field, a scope. A test fails first: every event named is in the catalogue, every route in the route table under the scope the list says, every list takes the order it names.
- (A3) A trigger enters when its event carries its record's id and the record has a route. Candidates: orders (placed, status changed, shipped, cancelled, return requested), customers, form submissions, products and low stock, subscriptions; with their module on, Workspace messages, tasks and table rows, ERP invoices and quotes. AU1 fixes the list and says what each candidate left out lacks.

## What changes in the API?
- (A4) The lists the triggers read take a newest-first order as `/orders` does (`sort=placed_desc`), the cursor walking the same way. The default order stays.
- (A5) An application may hold more than ten subscriptions. The number is asked first; proposed 50.
- (A6) Every event has its payload as data beside its catalogue line: the fields of `data` with type and meaning, and one sample. A test compares each sample's keys with the keys the announcing code writes.
- (A7) `GET /webhooks/events`: every event with its description, its module, its fields and its sample, for an app's own event chooser. `GET /webhooks` keeps its list of names.
- (A8) `POST /webhooks/{id}/test` takes `event`, one of the subscription's own: that event's sample is queued and sent like a real one, with the header `X-Pinegrap-Test: 1`. Without `event` it is `webhook.test` as today.
- (A9) The API document names the OAuth server: an `oauth2` scheme with the site's own authorization and token addresses and the scopes of the ceiling, only while the site's OAuth application exists. The two schemes of today keep their names.

## What changes in the panel? (drawn first, the owner's yes)
- (A10) Application Access › New application: a starting point "Automation platform" that ticks the scopes the contract names (`webhooks:manage` and `meta:read` among them). The sheet that shows the new secret names the three values an app asks for: the site's address, the key, the secret.
- (A11) An application's Events tab: "New subscription": the address, the events (a list to search, each with its description, up to 20), the secret shown once. The same checks and the same limit as `POST /webhooks`, through one function. An address a platform gives for incoming webhooks then works with no published app.
- (A12) "Send a sample" on a subscription: one of its events is chosen (A8).
- (A13) The guides of Application Access get "Automation platforms": the two ways (an app, a pasted address); an app is named there once it is public.

## What do the documentation pages show?
- Under each event its fields and its sample (A6), on the panel's page and the public one. Only the events section: the pages' opening and headings are plan `api-clean-modern`'s.

## What is a trigger, seen from an app?
- It subscribes with `POST /webhooks`, keeps the id and the secret, and removes the subscription with `POST /webhooks/{id}`, which every server takes.
- On a delivery it checks `X-Pinegrap-Signature` where the platform runs code, reads the record by the id in `data` and gives the record. Its samples are the list's newest records. So a live run and a sample have the record's shape, which the API document describes.
- One more trigger, "Any event", lists the catalogue (A7) and gives `data` as it comes, with the catalogue's sample.
