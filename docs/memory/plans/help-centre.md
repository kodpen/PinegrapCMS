---
name: help-centre
description: Plan (owner 2026-11-27, poll 246): a knowledge base on the site and support requests followed by the team; starts with the census step HC1, the build follows the owner's answers
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): a knowledge base and support requests. The list it belongs to and its rules: `plans/global-features.md`.

## What is known today? (read from memory's lists on 2026-11-27, not from the code)
- Content types hold articles of any kind with their own pages and search; satisfaction surveys ask after an answer (`platform/feedback-loop.md`).
- A customer's mail is a conversation in the Workspace with its customer card and follow-ups (`workspace/workspace-customer-card.md`, `workspace-followups.md`); Live Chat talks to a visitor.
- Nothing is called a help centre, and no request has a number, a state and a time it must be answered by.

## Which steps? (S under 400 lines; each waits for the one before it)
- HC1 (S) Census, memory only, written as `plans/help-centre-census.md` (more files if 4 KB is passed). It answers from the code, with file and line:
  - What a help centre needs of content types that they lack: categories in order, a search of the articles alone, "Was this helpful?", related articles, an article offered while a request is typed.
  - What a mail conversation stores (state, who holds it, times) and what a support request needs beyond it: a number the customer sees, states, a priority, an answer time, the customer's own page of their requests.
  - Which doors a request comes in by today (mail, form, Live Chat, the customer's account) and where each lands.
  - A proposal: whether a request is a conversation with more states or a thing of its own, the tables or columns either way with their reasons, the screens in words, and the questions that are the owner's.
- The build's steps are written by the architect after the census and the owner's answers; nothing else of this plan is given out before.

## What does the census keep?
- No product code and no schema. Tables are asked of the owner one by one.
- A request is a customer's personal data: the proposal says who reads it and how it leaves with the customer's data.
