---
name: application-review-overview
description: Start file of the application review desk (Forms › Application programmes): which application-review-* file holds the store, set-up, reviewers, scoring, jurors, ranking, decisions, report and API
---

## What is the desk?
- A form run as an application programme: a period, eligibility, weighted criteria in rounds, reviewers and outside jurors, blind review, combined scores, a shortlist, decisions and their e-mails. A form with no programme works as before. Binding rules, the features and the PR of each: `application-review.md`.

## Which file holds what?
- Pure rules (eligibility, weighted sums, leniency correction, assignment, shortlist cut): `application-review-rules.md`.
- The four tables, erase and export: `application-review-store.md`.
- Set-up tab, the period check, eligibility and "Let it in": `application-review-programme.md`.
- Reviewers tab and "Give out entries": `application-review-reviewers.md`.
- "My reviews" and the scoring screen: `application-review-my-reviews.md`; the outside juror's page: `application-review-jurors.md`; the correction link: `application-review-correction-link.md`.
- Entries tab, combining and the cut: `application-review-ranking.md`; Decisions tab and e-mails: `application-review-decisions.md`.
- Report tab and Excel, the API routes, MCP tools and the two events: `application-review-report.md`.

## How is a part added?
- A new tab: a view in `edit_application_program.php` (`$ap_view`), its own `includes/fn/application_<part>.php` loaded with `require_once`, pure parts in `tests/application_<part>_test.php`, and its memory file listed here.
