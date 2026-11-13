---
name: workspace-flows-quick-setup-map
description: Flows quick setup drawing against today's boxes and events: drawn starts, steps and recipes by kind; general boxes proposed for what has none (SMS, contact group, offer code)
---

## Which drawing?
- `plans/flows-quick-setup/quick-setup.html` (owner approved, 2026-11-12, #8950). Its names are examples; the dialog shows the boxes' and events' own labels. Server: `workspace-flows-quick-setup.md`.

## Which drawn starts are there?
- Orders cards: "Sipariş geldiğinde" `order.created`, "kargolandığında" `order.shipped`, "teslim edildiğinde" `order.delivered`, "iptal edildiğinde" `order.cancelled`, "İade talebi" `order.return.requested`, "sipariş vermeyi bıraktığında" the start box `no_order`. All there.
- Every event of `ws_events_catalog()` and every start box has an area: `web` under By hand (a start from outside), `join`, `mention`, `row` under Workspace, ERP quotes under Cart and quotes, store credit and user accounts under Customers, bounces under Content and campaigns.
- "Only when" (drawn: order total over 1.000,00 ₺): order total is no value of `order.created`, so a record field puts a Read a record box before the condition boxes.

## Which drawn steps are there? (drawn label: kind)
- Message: E-posta gönder `email`, Ekibe bildir `notify`, Kanala yaz `post`; also `report`. WhatsApp mesajı gönder: none until WA7 (`plans/whatsapp-channel-steps.md`), then its kind joins the message group. SMS gönder: `sms` (QS6, offered while SMS is set up).
- People: Görev aç `task_open` (also `task`, which waits), Onay iste `approval`, Müşteriyi kanala al `lead`; also `checkin`, `task_digest`.
- Records: Sipariş durumunu değiştir: `change` with id `{{trigger.record_id}}` (the start's `read` id), the order of the run (QS1b); Kişiyi gruba ekle `contact_group` (QS7, `workspace-flows-contact-group-box.md`); Tabloya satır ekle `row_add`; Teklif kodu ver: none; also `record_get`, `row_set`, `row_get`, `rows_get`.
- Logic: Bekle `delay`, Yalnızca şu durumda `condition`, Her biri için `for_each` (its End box placed by the setup).
- System: Pinegrap AI'ya sor `ai`, Webhook çağır `webhook`, Başka bir akış başlat `start_flow`; also `agent`, `web_check`, `mcp`.

## Which drawn recipes are there?
- "Teşekkür et, sonra görüş iste": `thank_then_review` with e-mail boxes (the review mail as marketing, consent-gated).
- "Depoya görev aç, ekibe bildir": `task_and_tell`.
- "Büyük siparişte onay iste": `approve_order` on `order.created`: approval, a change box on the run's order with its status left '' for the step's own setting (as an approval's channel 0), an e-mail to the customer (QS1b; `approve_first`, approval then a task, is gone).
- Added beside them: `form_reply`, `booking_tell`, `welcome_contact`, `restock`, `win_back`.

## Which general boxes are proposed for what has none?
- Each by `workspace-flows-adding.md`, never for the quick setup alone; the drawn step stays hidden until its box lands. Decided (architect, 2026-11-13, `plans/flows-quick-setup-steps-2.md`): the change box's value id is done (QS1b), the SMS box done (QS6, `workspace-flows-sms-box.md`), the contact group box done (QS7, #9291); the offer code waits for a question to the owner.
- Send an SMS (new step kind): to the record's person's mobile (as the e-mail box's `record_person`) or a typed number, text with values, info or marketing with the same consent rule; sent through `pg_sms_send()` (`includes/fn/sms.php`); outputs `sent`.
- Give an offer code (new step kind): a single-use code of an existing offer for the run's person, the code an output for the next e-mail; offers can only be changed today.
