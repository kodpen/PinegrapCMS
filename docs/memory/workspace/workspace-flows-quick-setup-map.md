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
- Message: E-posta gönder `email`, Ekibe bildir `notify`, Kanala yaz `post`; also `report`. WhatsApp mesajı gönder: none until WA7 (`plans/whatsapp-channel-steps.md`), then its kind joins the message group. SMS gönder: none.
- People: Görev aç `task_open` (also `task`, which waits), Onay iste `approval`, Müşteriyi kanala al `lead`; also `checkin`, `task_digest`.
- Records: Sipariş durumunu değiştir: `change`, but its record is a typed id, never the run's order; Kişiyi gruba ekle: none; Tabloya satır ekle `row_add`; Teklif kodu ver: none; also `record_get`, `row_set`, `row_get`, `rows_get`.
- Logic: Bekle `delay`, Yalnızca şu durumda `condition`, Her biri için `for_each` (its End box placed by the setup).
- System: Pinegrap AI'ya sor `ai`, Webhook çağır `webhook`, Başka bir akış başlat `start_flow`; also `agent`, `web_check`, `mcp`.

## Which drawn recipes are there?
- "Teşekkür et, sonra görüş iste": `thank_then_review` with e-mail boxes (the review mail as marketing, consent-gated).
- "Depoya görev aç, ekibe bildir": `task_and_tell`.
- "Büyük siparişte onay iste" (approval, change the order, e-mail): no box changes the run's order, so `approve_first` (approval, then a task) until the change box below.
- Added beside them: `form_reply`, `booking_tell`, `welcome_contact`, `restock`, `win_back`.

## Which general boxes are proposed for what has none?
- Each by `workspace-flows-adding.md`, never for the quick setup alone; none is built yet and the drawn step stays hidden until one lands.
- Send an SMS (new step kind): to the record's person's mobile (as the e-mail box's `record_person`) or a typed number, text with values, info or marketing with the same consent rule; sent through `pg_sms_send()` (`includes/fn/sms.php`); outputs `sent`.
- The change box on the run's record (a setting, no new kind): `change.id` takes one value such as `{{trigger.record_id}}`, as Read a record's `id` does; then "Change the order's status" is a change box on the order that started the run.
- Add to or take out of a contact group (new step kind): the contact of the run or of a value, the group chosen; no change kind writes groups today.
- Give an offer code (new step kind): a single-use code of an existing offer for the run's person, the code an output for the next e-mail; offers can only be changed today.
