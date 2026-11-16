---
name: content-review-features
description: Plan content-review, features V1 to V17: which items, what an item keeps, the states, the Content review screen, its actions, who sees what, the task, the flow event, the API
---

## Which items, and what does one keep?
- (V1) An item is a page outside the Recycle Bin (a draft is marked as one) or a record of a form used as a collection. Nothing else is listed.
- (V2) An item can keep a person (a panel user), a review date, how often it comes round (3, 6 or 12 months, or not at all), and when and by whom it was last marked reviewed. An item that keeps nothing is "not set".
- (V3) The state of a date (pure, in the site's time): overdue (before today), due soon (within 30 days), later, not set.
- (V4) "Mark reviewed" writes when and who. With an interval the date moves to today plus the interval; without one the date is cleared.
- (V5) The person must be a panel user who may edit the item. A person who is removed or loses that right leaves the item nobody's at the next sweep.
- (V6) The general job's sweep removes the rows of items that are gone. No delete path of pages or records changes.

## What does the screen show?
- (V7) A new screen "Content review" under the Pages tools, in look A on the list table driver: name (linked to where the item is edited), kind (Page, or the collection's name), person, review by with its state, last published or changed, SEO score, accessibility, views of 30 days.
- (V8) SEO score, accessibility and views are read from what the site keeps and run no engine: "Not scored" for a page never checked, "Not checked yet" before a pass has finished, a dash for a record.
- (V9) "Last published" is the newest revision of a designer page; for a classic page or a record it is the stored change time, labelled "changed".
- (V10) Filters: All, Overdue, Due soon, Nobody's, Not set, Mine; a search by name; sorted overdue first, or by name, date or views.
- (V11) Speed is not a column. A row's menu opens the page's line in Visitors, Page Speed, and the page in Search and audits.

## What can be done there, and by whom?
- (V12) On a row or a selection, in one request: "Set person", "Set review date" (with the interval), "Mark reviewed", "Clear".
- (V13) Roles under 3 see every item and do everything. A user (role 3) sees the items they are the person of and may only mark them reviewed.

## What reaches the Workspace and flows?
- (V14) "Make a task", only with the Workspace on and the person a member of it: a task for the person, opened as whoever pressed, the item tagged, due on the review date; pressed again while that task is open it hands the open one back.
- (V15) The general job announces `content.review_due` once per item on the day its date passes: the kind, id, name, person and date. The name is listed in `api_webhook_events()`, so a flow can start from it and a webhook can carry it.
- (V16) A gallery flow "A page is due for review" opens a task for the item's person. The screen itself schedules nothing.

## What does the API give?
- (V17) `GET /content-review` with V10's filters and a cursor; `POST /content-review/{kind}/{id}` takes the person, the date, the interval and "reviewed", with a dry run. A user-role caller reads and marks only their own items.
