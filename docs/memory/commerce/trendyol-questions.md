---
name: trendyol-questions
description: Trendyol buyer questions: read on a button for the last 14 days, kept in the session only, answer a waiting one from the panel (10-2000 chars, reviewed before publishing); calls, unverified points
---

## Where are questions shown?
- Account screen (`marketplace_settings.php`), card "Questions from Trendyol" (`#mp-questions`), drawn when the connector can `questions`. "Read questions" (action `questions_read`) reads; each waiting question has a textarea and "Answer" (action `question_answer`) where the connector can `answer_question`.
- The read is kept in `$_SESSION['software']['marketplace_read'][<account id>]['questions']` = `{read, questions, more}` (`mp_settings_marketplace_read()`, shared with returns), never in a table. A failed read keeps the last good one.

## Which call reads them? (`mp_trendyol_questions()`)
- `GET /integration/qna/sellers/{sellerId}/questions/filter?startDate=&endDate=&orderByField=CreatedDate&orderByDirection=DESC&page=&size=50`, every status. Their window is at most 2 weeks (a longer one is cut from its start), so the order feed's window: now − 14 days + 1 hour. Their reference says size max 200, their guide 50: 50 is asked. At most 4 pages; stops at `totalPages` or a short page; `more` → the card says only the newest 200 are shown. A failed page fails the read.
- Docs: v2.0/reference/getquestionfilter and answerquestion (modified 2026-02-16), v2.0/docs/getting-customer-questions (2026-09-24), read 2026-10-26.

## What is kept of a question? (`mp_trendyol_question_rows()`)
- `{id, text, status, waiting, asked, user, product, product_url, answer, reason}`. `id` is a JSON number, checked with `mp_trendyol_id_fits()`; no id or no text → left out.
- `waiting` = `WAITING_FOR_ANSWER`. Other statuses: `WAITING_FOR_APPROVE` (answer in review), `ANSWERED`, `REJECTED` (answer rejected, `reason`), `REPORTED`, `UNANSWERED` (time ran out).
- `answer` is the active answer, else the last rejected one. `user` (masked by Trendyol) only when `showUserName` is not false. `product_url` (`webUrl`) only when https, since it is drawn as a link.

## How is a question answered? (`mp_trendyol_answer_question()`)
- `POST /integration/qna/sellers/{sellerId}/questions/{id}/answers`, body `{"text"}`, 10–2000 characters after trimming (`mp_trendyol_answer_problem()`, counted in characters). 200 answers a bare JSON string: success with no data (`$allow_empty`). Trendyol reviews the answer before publishing.
- The question must be in the session read and waiting; on success the read notes `sent` and the text ("Answer sent" pill, no form) and an activity-log line is written. A refusal is a screen warning. The next read shows their status.

## What is not built?
- Reporting a question, changing an answer, reading one question by id.

## What is unverified?
- No live call. The fields beyond the docs' table (no example values given); a successful answer's exact body; the status right after answering (`WAITING_FOR_APPROVE` assumed); their error body for a refused answer (private information, wrong status).

Related: docs/memory/commerce/trendyol-returns.md, docs/memory/commerce/trendyol-api-facts.md
