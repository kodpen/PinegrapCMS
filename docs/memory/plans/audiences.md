---
name: audiences
description: Plan (owner 2026-11-02): rule-driven contact groups whose members are computed from orders, visits, consent, membership and fields, usable wherever a group is today; steps AU1-AU6
---

## What is wrong now?
- A contact group is a hand-kept list (`contact_groups`, `users_contact_groups_xref`): a form adds to it, a product sale adds to it, staff tick contacts. "Customers who ordered in the last 90 days", "members whose membership ends this month", "subscribers in Germany who never bought", "visitors who left a cart" exist only as a saved contact-list filter (`pg_contact_list_filters()` in `includes/fn/contact_list.php`, a dozen fixed ones) that no campaign, SMS send, flow or page can use.
- Campaigns and SMS campaigns send to groups (`contact_groups_email_campaigns_xref`, `platform/sms-campaigns.md`); Flows start on `contact_group.members_changed` (`pg_announce()` reaches the Workspace and the webhooks); the audience conditions of `page-experiments.md` read group membership. All of them become useful the moment a group can fill itself.

## What is proposed?
- A contact group may carry rules: `contact_groups.rules` (JSON) and `kind` (`static` as today, `dynamic`). Rules are a list of conditions joined by all/any: contact fields (country, city, lead source, a custom field), consent (`opt_in`, the consent log purposes, the SMS consent), membership (member, expired, expiring in N days), orders (count, total, last order age, bought a product or group, never bought), subscriptions (active, past due), returns, form records (sent a form, a field's value), visits (last visit age, landing page, UTM, the experiment bucket is not kept per contact and stays out), other groups (in, not in). Each condition compiles to one SQL fragment through a pure builder with placeholders; no free SQL.
- The general job recomputes dynamic groups (each at most once an hour, a cursor for big sites), writes the xref difference and announces `contact_group.members_changed` with the added and removed ids, so Flows, webhooks, campaign profiles with a "joined a group" trigger and the audience conditions react as they do for a hand edit. A dynamic group shows a count preview while its rules are edited and a "Refresh now" button.
- Everything that reads a group keeps working unchanged: campaigns, SMS campaigns, products' contact groups, the account's groups, the API and MCP resources, exports, erasure (a dynamic membership is derived data and is simply recomputed).

## Which decisions bind it?
- Marketing sends still obey consent and İYS rules per contact (`platform/sms-and-iys-rules.md`); a rule can select, never override.
- A dynamic group cannot be edited by hand (no tick boxes, no form or product writes into it; those doors are refused for `kind = dynamic`); turning a static group dynamic keeps its members until the first recompute.
- Counting and recomputing follow `visitors/high-volume-counter-rules.md`: visit conditions read the summary tables and the visit's contact link only, never scan raw rows per contact.

## Which questions are open? (architect, before AU1)
1. The two columns on `contact_groups` (`kind` ENUM static, dynamic; `rules` TEXT NULL) in the open version's migration, and `users_contact_groups_xref.source` (hand, form, product, rule) so a recompute removes only rule-made rows; recommended as written.
2. The recompute budget: at most one hour between runs per group, N seconds a tick, and the rule that a group over 100,000 members is recomputed in pages; recommended as written.

## Where does it start?
- AU1 alone, then AU2 and AU3 together. Read `core/README.md` key rules, `core/contact-holders.md`, `core/consent-log-writers.md`, `platform/sms-campaigns.md`, `includes/fn/contact_list.php`, `includes/fn/events.php` (`pg_announce_contact_group_members()`). Steps: `audiences-steps.md`.
