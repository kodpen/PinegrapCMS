---
name: agent-guide-panel-door
description: The guide for a browser agent through the panel session: agent_guide.php, llms.txt in the software folder, the rel="help" head link, "AI agent guide" in the account menu, the designer link
module: output
---

## What answers in the software folder?
- `agent_guide.php` (plan ai-guide AG6): the guide as `text/markdown` for a signed-in panel session. No `topic`: the topic list plus one line saying where a topic is read; `?topic=<id>` a topic; `&section=` one section. A bad name or a section without topic: 400; a topic not listed: 404, plain text.
- No session: `validate_user()` sends the browser to the sign-in page, as any panel screen.
- `<software>/llms.txt` has no file: the web server hands it to `router.php`, which defines `PG_AGENT_GUIDE_LIST` and requires `agent_guide.php`; it always answers the list. The site root's `/llms.txt` (Site Settings, SEO, AI Crawlers) is another file and is not touched.

## Which topics does the panel door list?
- Those a connection owned by the signed-in user could reach: route ids whose scope `api_owner_scopes($user)` holds (scope `''` always), through `pg_agent_guide_listed()`. The panel topic reads the same user's screens (`agent-guide-panel.md`).
- Headers: `Cache-Control: private, no-cache` (the text differs per user), `ETag` of the digest with a 304, `X-Robots-Tag: noindex`.

## Where does the panel point at it?
- `output_header()` prints `<link rel="help" type="text/markdown">` on every panel page, to `pg_agent_guide_url()` (`includes/fn/output.php`); the property `agent_guide` names a topic. The Visual Page Editor (`includes/designer_screen.php`) passes `designer`.
- `pg_account_menu()`: "AI agent guide" (`bi-robot`, new tab) under Help & Support after Interface tour, also under a private label: it is the site's own text, not a Kodpen link.
- `output_header_secure()` (the sign-in pages) has no link: the door needs a session.
- Test: `tests/agent_guide_door_test.php`.
