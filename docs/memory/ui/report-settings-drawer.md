---
name: report-settings-drawer
description: The order and visitor reports' Report settings drawer (#8709, #8764): the form inside it, when it opens by itself, Close, no Edit / Cancel Edit pair
---

## How does the settings drawer behave?
- `<dialog class="drawer drawer-end" id="report_settings">` sits inside the `<form>` (its fields submit, `panel-layout-pitfalls.md`); the toolbar's "Report settings" button opens it (`data-bs-toggle="drawer"`). Field names, the filter builder (`create_filter()`) and Save & Run are as before; Close keeps what was typed; Delete stays for a saved report.
- It opens at once on a plain new report (no `id`, no `ready`) and after a save that came back with an error (`data-pg-open-at-load`, opened through `pgUI.drawer.show()`); a saved or ready report shows its results with the drawer shut.
- The Edit / Cancel Edit pair that showed and hid the form is gone from both reports.
- Its look (sections, 430 px, the foot's buttons, the cross in its head that closes like Close): `look-report-screens.md` (#8981).
- The visitor report's the same: open on a plain new report (no `id`, no `ready`) and after a save error, shut on a saved or ready report (`visitors/visitor-report-ready-reports.md`).

Tests: `tests/order_report_screen_layout_test.php`, `tests/visitor_report_screen_layout_test.php`.
