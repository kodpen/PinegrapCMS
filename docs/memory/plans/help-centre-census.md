---
name: help-centre-census
description: Help centre HC1 census from the code: what collections lack for a knowledge base, what a Workspace mail conversation stores, where requests come in, the proposal, the owner's questions
---

## What is this census?
- Step HC1 of `help-centre.md`: what the code has today for a knowledge base and support requests, with a proposal for the architect. Paths are relative to `pinegrap/`. No product code or schema changed.
- Parts: this file (findings in short, the owner's questions), `help-centre-census-articles.md`, `help-centre-census-conversation.md`, `help-centre-census-doors.md`, `help-centre-census-proposal.md`, `help-centre-census-privacy.md`.

## What did it find?
- Articles: a collection holds them with pages, lists ordered by a number field, related records by "Shown record" and the site search. It lacks a search of the articles alone, "Was this helpful?", a category tree in one widget and an article offered while a request is typed (`help-centre-census-articles.md`).
- A support-box mail conversation has a state (Open, Waiting for the customer, Closed), a holder, a first-answer target and its times, and a `[#T<id>]` number in every answer's subject. It lacks a priority, a resolve-by time, a source and any page the customer sees (`help-centre-census-conversation.md`).
- Doors: e-mail, WhatsApp and SMS land as Workspace conversations; a form lands as a `forms` record with a reference code; Live Chat lands in the panel's chat window, apart from the Workspace; My Account has no requests section (`help-centre-census-doors.md`).
- The mail plan left "a customer portal, a knowledge base" out (`workspace/mail-decisions.md`); the owner's yes of 2026-11-27 (`workflow/decisions-made-product-117.md`) brings both in.

## What does it propose?
- A request is a support-box mail conversation with a priority and a source, not a thing of its own; a form, a chat and the account open one in a chosen box (`help-centre-census-proposal.md`).
- Articles stay a collection; the gaps are widget settings, one read endpoint and one small votes table.
- A request is the customer's personal data: today mail conversations are neither exported nor erased (`help-centre-census-privacy.md`).

## Which questions are the owner's?
- Is a support request a support-box mail conversation (proposed), or a table of its own apart from the mail?
- May `ws_mail_conversations` get the column `priority` (Low, Normal, High, Urgent)?
- May `ws_mail_conversations` get the columns `source` and `source_id` (mail, form, chat, account and the row it came from)?
- Does a request need states beyond Open, Waiting for the customer, Closed (proposed: Solved, closed by itself after some days)?
- Does a request get a resolve-by time beside the first-answer target, and does its clock stop while waiting for the customer?
- May the table `kb_votes` be added for "Was this helpful?" (article, yes or no, time; no address or IP)?
- Does the customer see their requests on My Account, and may they answer there?
- Does a guest get a signed link to their request's page?
- May a Live Chat be turned into a request by the team?
- Are a person's requests in the personal data export?
- On erasure, does a request made from a form or a chat stay like mail (2026-11-14 decision), or go?
- Is there a keep period for closed requests?
