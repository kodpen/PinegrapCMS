---
name: appointment-booking-steps-2
description: Steps BK6-BK10 of plans/appointment-booking.md: Workspace calendar rows, My bookings and manage page, API, deposits, templates and close-out; claim as [plan:appointment-booking] BK<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `appointment-booking-steps.md`; BK6, BK7 and BK8 run at once after BK3 and BK4.

- BK6 (S) Workspace calendar. Done in #6305 (decided in #6302, option B: read from `bookings`, no `ws_events` row; `workspace/workspace-bookings.md`). The agenda's status changes call `pg_booking_changed()`.
- BK6b (S) The four `booking.*` events in `ws_events_catalog()`. Done in #6629 (`workspace/workspace-booking-record.md`; record kind `booking`, no new right: the agenda's row rule).

- BK7 (S) My bookings and the manage page. Done in #6607 (`commerce/booking-manage.md`).
  - The account widgets (`designer/account-widget-forms.md`) gain "My bookings" (upcoming and past, reschedule and cancel within the policy); `booking_manage.php` (root) opens from the mail's signed link for a visitor without an account: the same two actions and the `.ics` download; both call the slot engine again for a reschedule. Memory: `designer/booking-widget.md` grows.

- BK8 (S) API, MCP and webhooks. After BK3. Done in #6377 (`api/api-bookings.md`).
  - Resource `bookings` in `includes/api/resources/bookings.php` with the schema rows (`api/api-endpoint-rules.md`): list, get, create (staff-made), update status, cancel, plus `GET /bookings/slots?service=&staff=&day=`; scope `bookings:*` with its owner ceiling; `dry_run`; MCP tools follow the routes; the `booking.*` events of BK5 in the webhook catalogue. Memory: `api/api-bookings.md`.

- BK9 (S) Deposits, cut in two. Decided in #6632 (`workflow/decisions-made-schema-10.md`).
  - BK9a: the 11.0.169 columns, the service form's deposit and product picker, the deposit order, marked paid once from `pg_order_complete()` / `pg_order_mark_paid()` (confirms then). In #6654 (`commerce/booking-deposits.md`).
  - BK9b (after BK9a): at the details step of a service with a deposit (`pg_booking_deposit_due()`) the widget writes the details on the held row, keeps the hold for the payment, calls `pg_booking_deposit_order_write()` and takes the payment as the form payment step does (`designer/custom-form-payment-step.md`: choices, `pg_payment_box()`, redirect return to `booking_slots.php`, retry of the same order); unpaid, the hold expires and `pg_booking_deposit_mark_paid()` turns a late payment into requested; "paid on arrival" (offline) completes the order unpaid and leaves the booking requested. The agenda, the manage page, "My bookings" and the API show paid (`paid_at`, the order number). Done in #PRNUM (`commerce/booking-deposit-payment.md`).

- BK10 (S) Templates and close-out. After BK1-BK9.
  - The Salon, Clinic and Law templates get a booking page on the widget beside their form (new installs only; `designer/design-template-*.md` updated); PR body names `[NEW] Added appointment booking: services, staff hours, free time slots on designed pages, confirmations and reminders`; decisions to `commerce/`, `designer/`, `workspace/`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
