---
name: reverse-oauth-leftovers
description: Remaining outside-account connections (calendar, notes, cloud picker, Meta, Merchant, GA4, Business Profile, social, AI), the connect.pinegrap.com broker and open decisions
---

## What is done and what is left?
- Done: connection layer (model A, #282), Gmail / Microsoft 365 mail (#294), Search Console (#310), Microsoft, Apple and Google sign-in (#306, #318), backups to Drive / OneDrive / Dropbox (#312), UPS / FedEx / USPS REST with `client_credentials` (#302, #308, #314), Workspace MCP client and remote agent (#347–#361). Rules: `api/api-connected-accounts.md`, `api/api-connected-accounts-usage.md`.
- Owner (2026-10-29, #3180): all seven items start in 2026.11.0, each its own step and PR, in this order: Merchant Center, Notion (MCP alias), file picker, Google reviews, GA4 card, OneNote, personal iCal layer. Architect: Notion only via the alias; the picker downloads on the server; iCal: `ws_holiday_feeds.user_id INT UNSIGNED NOT NULL DEFAULT 0` + KEY, address sealed; Notion and OneNote after #3135 (`workflow/decisions-made-product-5.md`).

| Item | What | Effort |
|---|---|---|
| Calendar sync | Step 0: personal secret iCal address as a read-only layer: done (#3254, `workspace/workspace-personal-ical.md`). OAuth step: write tasks to the person's calendar and read changes back (Google `syncToken`, Graph delta). Files: `includes/workspace/calendar_feed.php`, `workdays.php`, `workspace_calendar.php` | S / M–L |
| Notion, OneNote | Notion via its MCP alias: done (#3212). OneNote via Graph `Notes.Read`: done (#3291, `workspace/workspace-notes-import-onenote.md`). An imported note is an ordinary `ws_notes` row. Keep, Evernote, Apple Notes: no usable API | M (S via MCP) |
| Cloud file picker | Drive, Dropbox (#3238), OneDrive (#3261): done (`platform/cloud-file-picker.md`) | S |
| Meta | Product feed (step 0, shared with Merchant), Meta Business Extension for Pixel + catalog, Instagram Login feed, WhatsApp Cloud API. Model B only | L |
| Merchant Center | Merchant API (Content API for Shopping shut 2026-08-18). Slice 1 done (#3213, `commerce/merchant-center.md`). Left: per-item push + sync table (schema question) | M |
| GA4, Business Profile | Admin/Data API; Business Profile needs Google's access form (5 read-only reviews: Places API key is enough). On demand | M |
| Social posting | Facebook Page, Instagram, LinkedIn; needs app review; X is paid, not recommended | M + process |
| AI accounts | Not recommended (subscription OAuth is limited to Anthropic's own products); only OpenRouter PKCE as "bring your own model" (`includes/workspace/ai.php`) | S |

## What are models A and B?
- A (built): each site registers its own app at Google / Entra / Meta; the consent screen shows the site; Google sensitive scopes show the unverified warning and a 100-user cap unless the Workspace org marks it Internal.
- B (not built): Kodpen registers one verified app per provider; sites connect through `connect.pinegrap.com`: a start request signed with the licence key (site URL, provider, scopes, PKCE challenge) → consent → the broker swaps the code with Kodpen's secret → the browser returns to the site's `connect.php` with a one-time code → the site fetches the token server to server with its PKCE verifier. The broker stores no tokens (refresh: the site sends its refresh token, the broker adds the secret); revocation goes to the provider. A licence feature; once ready the default for operator connections, A stays for customer sign-in.

## Which owner decisions are open?
- Google verification for Kodpen and the broker: yes / no.
- Meta business verification and App Review for Kodpen (Meta, social posting): yes / no.
- OAuth server phase E, "Sign in with Pinegrap" (OpenID Connect, `id_token`): waits for a decision.

## What must be rechecked before starting?
- Provider terms were read on 2026-10-13 from public pages; recheck in the console when applying (Google scope classes, Microsoft MC786329 dates, FedEx migration dates). Live tests on dev with the owner's accounts.
