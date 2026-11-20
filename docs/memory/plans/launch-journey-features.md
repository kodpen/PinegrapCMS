---
name: launch-journey-features
description: Plan launch-journey, features L1 to L8: purpose, the step catalogue and its sources, one click, the go-live check, the first week, a team member's path and hints, the agency summary, API and MCP
---

## Which features are there?
- L1 Purpose: "What is this site for?" on an administrator's first dashboard while none is stored: Shop, Company, Association or charity, School or courses, Clinic or bookings, Agency, Blog or magazine, Other. It picks and orders the steps; changed later in Site Settings › General.
- L2 Steps and their sources: Domain and SSL (`check_ssl_status()`, `pg_ops_certificate_state()`); E-mail sending (`pg_mail_settings_ready()`, `pg_mail_spf_check()`, `pg_mail_dkim_check()`, `pg_mail_dmarc_check()`); Legal texts (`pg_legal_kvkk_settings()`, `pg_consent_enabled()`, a shop's `pg_legal_documents_enabled()`).
- L2 (more): Search engines open (`pg_robots_catch_all_closed()`, the home page's noindex); Search Console and Analytics (`pg_connections_screen_state()` for `search_console`, `google_analytics`); Payment live, a shop only (`ECOMMERCE_PAYMENT_GATEWAY_MODE`, each driver's `pg_payment_setting()` mode, Stripe's key prefix); Backups (`pg_backup_age_state()`); Scheduled tasks (`pg_cron_last_runs()`); Team (another user signed in once); Bookings hours for a clinic.
- L3 One click: each step's "Do it" opens its screen at the right section (Site Settings anchors `pgset-*`); the card reads the states again when the tab is shown again.
- L4 Go-live check: one screen, blockers then warnings. Blockers: closed to search engines, maintenance mode on, payment in test mode on a shop, no personal-data text where a form or the shop collects data, mail not sending, scheduled tasks stopped. Warnings: internal broken links (`seo_issue` `link_broken_internal`), no backup, no Search Console.
- L5 First week: from the go-live time, seven days of a "First week" card: the first order or form entry, visitors, the first mail delivered, any ops alert; it ends itself after the week.
- L6 A team member's path: on a user's first dashboard, three to five rows for their role (Designer: the designer and pages; Manager: orders and contents; User: their own areas). A screen's first visit shows one hint line with "Don't show again".
- L7 Agency summary: a signed read-only page for the customer: the steps done, and "What we need from you" lines the agency writes (logo, texts, domain access), each answered by the customer in text; a bell line to the agency on an answer.
- L8 API and MCP: a read route with every step's state, blockers and warnings, one MCP tool ("what is missing?"); the agent guide's `start` topic names it. The same pure catalogue as the card.

Related: docs/memory/plans/launch-journey.md, docs/memory/plans/launch-journey-steps.md
