---
name: appointment-booking-steps-2
description: Steps BK6-BK10 of plans/appointment-booking.md: Workspace calendar rows, My bookings and manage page, API, deposits, templates and close-out; claim as [plan:appointment-booking] BK<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `appointment-booking-steps.md`; BK6, BK7 and BK8 run at once after BK3 and BK4.

- BK6 (S) Workspace calendar. Done in #6305 (decided in #6302, option B: read from `bookings`, no `ws_events` row; `workspace/workspace-bookings.md`). The agenda's status changes call `pg_booking_changed()`.
- BK6b (S) The four `booking.*` events in `ws_events_catalog()` (record kind `booking`, its right, `workspace_flows_parity_table()`). After BK6.

- BK7 (S) My bookings and the manage page. Done in #6607 (`commerce/booking-manage.md`).
  - The account widgets (`designer/account-widget-forms.md`) gain "My bookings" (upcoming and past, reschedule and cancel within the policy); `booking_manage.php` (root) opens from the mail's signed link for a visitor without an account: the same two actions and the `.ics` download; both call the slot engine again for a reschedule. Memory: `designer/booking-widget.md` grows.

- BK8 (S) API, MCP and webhooks. After BK3. Done in #6377 (`api/api-bookings.md`).
  - Resource `bookings` in `includes/api/resources/bookings.php` with the schema rows (`api/api-endpoint-rules.md`): list, get, create (staff-made), update status, cancel, plus `GET /bookings/slots?service=&staff=&day=`; scope `bookings:*` with its owner ceiling; `dry_run`; MCP tools follow the routes; the `booking.*` events of BK5 in the webhook catalogue. Memory: `api/api-bookings.md`.

- BK9 (S) Deposits. After BK4 and the forms' payment step (FL5, merged: `designer/custom-form-payment-step.md`).
  - Decided in #6632 (`workflow/decisions-made-schema-10.md`): `booking_services.deposit` (minor units) and `deposit_product_id`, `bookings.order_id` (indexed) and `paid_at`, the form-fee shape; the booking is marked paid once from `pg_order_complete()` / `pg_order_mark_paid()` as `pg_form_payment_mark_paid()` does, and confirms then; the service form in Settings › Bookings gets the deposit and the product picker.
  - A service with a price may ask for a deposit at the details step: the widget hands the amount to the form payment step (the fee product named in the service), the booking confirms on `pg_order_complete()`, else stays requested and the hold expires; "paid on arrival" marks nothing. Memory: `commerce/bookings-model.md` grows.

- BK10 (S) Templates and close-out. After BK1-BK9.
  - The Salon, Clinic and Law templates get a booking page on the widget beside their form (new installs only; `designer/design-template-*.md` updated); PR body names `[NEW] Added appointment booking: services, staff hours, free time slots on designed pages, confirmations and reminders`; decisions to `commerce/`, `designer/`, `workspace/`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
