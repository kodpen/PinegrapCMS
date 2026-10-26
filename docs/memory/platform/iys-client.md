---
name: iys-client
description: İYS client (PR #1895): account in connect_apps iys, dry run, daily push of e-mail consent answers and pull of refusals, contacts.iys_email_status, campaign filter
---

## Where is the account?
- `connect_apps` row `iys` (no schema, as the SMS providers): API user = `client_id`, password sealed; `options`: `iys_code`, `brand_code` (digits), `env` (`test` = `api.sandbox.iys.org.tr`), `live`, `email` (the e-mail switch), and the job's cursors `push_id` (last `consent_log.id` handled) and `pull_after` (İYS's changes cursor). A settings card that saves the row must keep the cursor keys; `pg_iys_state_save()` merges them in.
- No card exists yet (plan step 5b): until then the row is written by hand, so the client is inert on every site.

## What does a run do?
- `iys_job.php`, `pg_cron_jobs()` entry `iys_job`, daily, light lane; not switched on by itself (scheduled jobs settings, or the card later). `pg_iys_run()` needs a complete account with `email` on, then push before pull, so a refusal given here is in İYS before İYS is read.
- Dry run (`live` missing or false, the default): no call at all; logs "would push N" and leaves the cursor, so the rows go once the account is live. Chosen because no İYS test credentials existed.
- Push: `consent_log` rows after `push_id`, `email`/`marketing`, granted or refused, source not `iys`, address from the contact, else the user; 500 per run, one `POST …/consents` each (`HS_WEB`, `BIREYSEL`, `EPOSTA`, ONAY/RET, Turkish local `consentDate`). Contacts opted in by DEFAULT 1, an import or the API have no row and are never pushed (plan open question).
- Answers (`pg_iys_answer()`): 2xx ok → contacts with the address get `iys_email_status` approved/refused; another 4xx = this row rejected, logged in the run's summary and passed over; 401/403/408/429, 5xx, 3xx, no answer = stop, the cursor stays before that row.
- Pull: `GET …/consents/changes?after=&limit=999`, up to 10 pages, stopping on an empty page (`read`), never on a page without e-mail changes: a page of SMS changes alone held an e-mail refusal behind it back for a day. EPOSTA only (no SMS/call consent is kept here: those changes are passed over for good). RET → `opt_in = 0` and a `consent_log` row (source `iys`, never pushed back) when a contact was opted in; ONAY only sets the status, never `opt_in`, so a late page cannot resubscribe someone who said no here.

## What does the column do?
- `contacts.iys_email_status` VARCHAR(16) '' / approved / refused (11.0.39, owner approved 2026-10-26, `workflow/decisions-made-data.md`). With İYS on for e-mail (`pg_iys_email_on()`), `pg_iys_email_refused()` drops a commercial recipient in `email_campaign_job_prepare()` and `send_email_campaign.php`, manual addresses included. An address with no İYS status still gets mail if opted in: requiring `approved` is an owner question in PR #1895.

## What is not verified?
- No İYS account was reached: endpoints, field names, the `errors[].code/message` shape, whether a duplicate consent comes back as an error, and the `changes` paging come from İYS's published API documents and client libraries. The 3-business-day limit is not checked here; İYS refuses older rows and the summary logs them.

Related: docs/memory/plans/sms-and-iys.md, docs/memory/core/consent-log-writers.md, docs/memory/platform/sms-provider-layer.md
