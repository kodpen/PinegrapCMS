---
name: workspace-flows-panel-fields
description: How a Flows box panel's fields are written (FE5): one line of help and the "?" window, the e-mail's Recipient words as chips, the folded Sender row showing who sends
---

## How is a field's help shown? (field(), helpSplit(), helpAsk())
- At most one line under a field. A help of `HELP_LINE` (44) characters or fewer stands as that line; about 50 of the small text fit the 320 px card, and 51 ran onto two.
- A longer help opens whole from a "?" (`.wsf-help-ask`, `bi-question-circle`) at the right of the field's name, in a small window over the panel (`.wsf-help-pop`, the floating surface). Its first sentence stays under the field when it fits the line (cut with an ellipsis if wider), else nothing does.
- The "?" again, a press outside or Esc closes it; Esc gives the focus back to the "?" and stops there, so the panel's own Esc does not take the box's focus. The control is described by the line and the window (`aria-describedby`).
- The split is made on the translated text at run time, so a Turkish help may land otherwise than the English. Every `field()` caller follows it: no help key was shortened.

## Which words does an e-mail fill per recipient? (mailWordNames(), valueParts())
- `ws_flow_mail_words()` in `flow_mail_campaign.php`: the names `pg_mail_merge_contact_fields()` fills (`name`, `first_name`, `last_name`), labelled, sent as `steps.mail_words` of `ws_flow_js_config()`. A word the send path adds is offered with it.
- In an e-mail box's texts (subject and body) they are the "Recipient" group of Add value, "filled for each recipient", and drawn as chips "Recipient › Name" with `data-token` `^^name^^`; saved as typed, so the server reads nothing new.
- A word typed by hand becomes its chip as its last `^^` is typed; one the send path does not fill (`^^city^^`), or a word in another box, stays text. A word chip takes no "When empty, write".
- Before FE5 the subject took value chips while the text showed `^^name^^` typed.

## What does a folded part show? (emailSenderFields(), senderShown())
- The e-mail's Sender is a row with a chevron, "Sender" and, while closed, who sends at the right: the box's From name, else its From address, else the site's name (`steps.mail_sender`, `ORGANIZATION_NAME`). It changes as the fields are typed; open, the value goes.
- It opens on its own when one of its fields is set, as before.

## Tests
- `test_workspace_flows_canvas_panel_fields` (Node): `helpSplit()`, `valueParts()`, `mailWordNames()`, `alwaysWords()`, `senderShown()`. `test_workspace_flow_values_mail_words`: the words are the send path's.
- Harness (`workspace-flows-canvas-harness.md`), fixture `mail`: the "?" opens and Esc shuts it, no help over one line, the chip, the list's order, a word kept as typed, the Sender row.

Related: docs/memory/workspace/workspace-flows-value-picker.md, docs/memory/workspace/workspace-flows-side-panel.md
