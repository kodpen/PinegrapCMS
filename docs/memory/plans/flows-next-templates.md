---
name: flows-next-templates
description: Template steps T1-T14 of plans/flows-next.md: ready flows people pick instead of building box by box, what each needs and how it is tested before it is added
---

## How is a template made and tested?
- Added to `ws_flow_templates()` (flow_templates.php) in the form `workspace-flows-template-format.md` gives, with the settings asked when it is used (`workspace-flows-template-setup.md`) and its texts through `tr_add.php`. An event template is offered only when its event is in the catalogue, as `stock_event` is.
- Tested twice: `tests/workspace_flow_templates_test.php` checks every template's filled graph without a database (`ws_flow_graph_check()`, settings, texts), plus a test for what the new one must hold; then a run on dev from the template with sample data, its runs read back and the dev flow binned.
- Up to three templates in one PR (S). `now` means every box and event it uses is on development; the others wait for the step named.
- Already there and not repeated: form to the channel, task when stock runs low, new order to the channel, new customer, quote approval, text review, next task when approved, overdue tasks, morning summaries, overdue invoices every Monday.

## Which templates?
- T1 (done, #7113): ask for a review: an order is delivered → wait 7 days → a task to ask the customer how it went and for a review.

- T2 (done, #7113): restock after approval: a product runs low → an approval in the channel "Order more?" → approved: a task to order it; rejected: a line in the channel.

- T3 (done, #7113): a return to handle: a return is requested → a task for the person who handles returns, due in 2 days → a line in the channel; the task not done in time → tell the manager.

- T4 (now): a cancelled booking: a booking is cancelled → a line in the channel and a task to offer the guest another time.

- T5 (now): an e-mail to answer: an e-mail is received → a task for the person on duty, due today → not done within a working day → tell the manager.

- T6 (after A1): goods received: by hand or each morning → an approval "Did the delivery arrive, how many?" with a number answer → add it to a stock table row (B5 when it lands).

- T7 (after A1, A2): daily cash count: each evening → an approval asking the counted amount → Calculate the difference from the expected amount → tell the manager when it is not zero.

- T8 (after C1): a low review: a product review is written → rating 2 or less → a task to answer it and a line in the channel.

- T9 (after C1): a failed subscription payment: tell the owner and open a task to contact the customer.

- T10 (after B4): booking reminder: a booking is confirmed → wait until a day before it → tell the person who hosts it (an e-mail to the guest after B8).

- T11 (after B6): failed runs to the team: a run fails → a line in the channel with the flow, the box and its sentence.

- T12 (after C3): overdue invoice follow-up: an invoice becomes overdue → a task for the person who collects it → still unpaid after 7 days → tell the manager.

- T13 (after C1, B1): quote follow-up: a cart quote is made → wait 2 days → read the quote → not ordered: a task to call the customer (an e-mail after B8).

- T14 (after B2): sort incoming forms: a form is submitted → Pinegrap AI picks sales, support or spam → a task for the right person, spam ends.
