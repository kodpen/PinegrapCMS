<?php
/**
 * Pinegrap - Enterprise Website Platform — System-widget cart POST handler.
 *
 * Forms rendered by `_render_system_widget_shopping_cart` (the Visual
 * Pinegrap Editor's shopping_cart widget) post here so a `header('Location: …')`
 * redirect is guaranteed to fire — handling the POST from inside the widget
 * render function fights with the page-rendering pipeline (output buffering,
 * intermediate rendering steps, etc.) and the resulting "form yeniden
 * gönderme" prompt was the visible symptom.
 *
 * Supported actions (mutually exclusive per POST):
 *   submit_update_cart           — quantities[item_id] map → UPDATE order_items;
 *                                  also product-form data, gift-card recipient
 *                                  rows and the recurring schedule of every
 *                                  recurring row
 *   submit_special_offer_code    — special_offer_code → orders.special_offer_code
 *
 * Required fields on every POST:
 *   send_to                      — same-host path the user returns to (the
 *                                  cart page URL the form was rendered on)
 *
 * No CSRF token validation here — the cart actions are visitor-side
 * operations on the visitor's own session order, same trust level as the
 * legacy add_order_item / remove_item_from_cart endpoints.
 */

include('init.php');

// Nothing to process on a direct GET: send the visitor back to the page
// they came from. go() only honours same-host targets, so a foreign or
// missing referrer lands on the home page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    go(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
}

// Resolve the active session order. initialize_order creates one when
// missing, so $oid is always > 0 after this call.
initialize_order();
$oid = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

// liveform handle — used to surface "Cart updated" / coupon errors via the
// session messages pipeline so the cart widget's Messages content node can
// pick them up on the redirect-target page.
$lf = new liveform('shopping_cart');
$lf->clear_notices();

$did_mutate = false;

// ── Update quantities ───────────────────────────────────────────────────
// Use isset() not !empty() — bindable cart_update buttons may submit
// `submit_update_cart=` (empty string) when their value attr was stripped
// by an older btn renderer. The mere PRESENCE of the key marks intent.
if (isset($_POST['submit_update_cart']) && $oid > 0) {
    $qty_arr = (isset($_POST['quantities']) && is_array($_POST['quantities'])) ? $_POST['quantities'] : array();
    $applied = 0;
    foreach ($qty_arr as $iid => $q) {
        $iid = (int)$iid;
        $q   = (int)$q;
        if ($iid <= 0) continue;
        if ($q <= 0) {
            // 0 = remove
            db("DELETE FROM order_items WHERE id = '$iid' AND order_id = '$oid'");
            db("DELETE FROM form_data   WHERE order_item_id = '$iid' AND order_id = '$oid'");
            $applied++;
        } else {
            db("UPDATE order_items SET quantity = '$q' WHERE id = '$iid' AND order_id = '$oid'");
            $applied++;
        }
    }
    // ── Donation rows ──────────────────────────────────────────────────
    // Products with selection_type='donation' have no meaningful quantity —
    // the visitor edits the AMOUNT, posted as donations[<order_item_id>].
    // Legacy shopping_cart.php:228-247 does the same three steps: strip
    // thousand separators, convert the visitor's currency back to the base
    // currency, store as cents. A non-positive amount removes the row, which
    // is how a visitor cancels a donation (there is no qty box to zero out).
    $don_arr = (isset($_POST['donations']) && is_array($_POST['donations'])) ? $_POST['donations'] : array();
    $don_applied = 0;
    foreach ($don_arr as $iid => $raw) {
        $iid = (int)$iid;
        if ($iid <= 0) continue;
        // Anti-tamper: the row must belong to THIS session's order.
        $owns = (int)db_value("SELECT id FROM order_items
                               WHERE id = '$iid' AND order_id = '$oid' LIMIT 1");
        if ($owns !== $iid) continue;

        // "1.250,00" / "1,250.00" both arrive depending on locale — drop the
        // grouping separator, keep the last dot/comma as the decimal point.
        $amount = trim((string)$raw);
        $amount = preg_replace('/[^\d.,-]/', '', $amount);
        if (substr_count($amount, ',') && substr_count($amount, '.')) {
            // Both present: whichever comes last is the decimal separator.
            $amount = (strrpos($amount, ',') > strrpos($amount, '.'))
                ? str_replace(array('.', ','), array('', '.'), $amount)
                : str_replace(',', '', $amount);
        } else {
            $amount = str_replace(',', '.', $amount);
        }
        $amount = (float)$amount;

        $rate = (defined('VISITOR_CURRENCY_EXCHANGE_RATE') && (float)VISITOR_CURRENCY_EXCHANGE_RATE > 0)
            ? (float)VISITOR_CURRENCY_EXCHANGE_RATE : 1.0;
        // round(), never (int): a percentage-converted amount lands on a
        // fractional cent and truncating drifts a kuruş off the displayed value.
        $cents = (int)round(($amount / $rate) * 100);

        if ($cents <= 0) {
            if (function_exists('remove_order_item')) {
                remove_order_item($iid);
            } else {
                db("DELETE FROM order_items WHERE id = '$iid' AND order_id = '$oid'");
            }
        } else {
            db("UPDATE order_items SET price = '" . (int)$cents . "'
                WHERE id = '$iid' AND order_id = '$oid'");
        }
        $don_applied++;
    }

    // ── Per-item product-form data save ────────────────────────────────
    // The cart widget renders order-form fields for products carrying
    // products.form=1. Field name format (legacy convention):
    //
    //   order_item_<item_id>_quantity_number_<q>_form_field_<field_id>
    //
    // We scan $_POST + $_FILES for that pattern, validate the
    // order_item belongs to THIS order (anti-tamper), then upsert into
    // form_data (DELETE existing for that item+qty+field, INSERT new).
    $_pg_form_saved = 0;
    $_pg_form_re = '/^order_item_(\d+)_quantity_number_(\d+)_form_field_(\d+)$/';
    $_pg_form_seen = array();   // track item_id, qty, field_id we touched
    // Combine POST + FILES so file-upload fields are detected too. POST
    // keys take precedence on overlap (file fields would be in $_FILES).
    $_pg_form_keys = array();
    foreach (array_keys($_POST) as $_pk) $_pg_form_keys[] = $_pk;
    foreach (array_keys($_FILES) as $_pk) if (!in_array($_pk, $_pg_form_keys, true)) $_pg_form_keys[] = $_pk;

    foreach ($_pg_form_keys as $key) {
        if (!preg_match($_pg_form_re, $key, $m)) continue;
        $iid = (int)$m[1];
        $qn  = (int)$m[2];
        $fid = (int)$m[3];
        if ($iid <= 0 || $qn <= 0 || $fid <= 0) continue;

        // Anti-tamper: verify the order_item belongs to this session's order
        // AND the field is owned by the order_item's product. One JOIN does both.
        $owns = (int)db_value(
            "SELECT 1 FROM order_items oi
             INNER JOIN form_fields ff
                ON ff.product_id = oi.product_id
               AND ff.id = '" . (int)$fid . "'
             WHERE oi.id = '" . (int)$iid . "'
               AND oi.order_id = '" . (int)$oid . "'
             LIMIT 1"
        );
        if (!$owns) continue;

        // Resolve field type for proper storage of date / datetime / time.
        $f_meta = db_item("SELECT type FROM form_fields WHERE id = '" . (int)$fid . "' LIMIT 1");
        $f_type = is_array($f_meta) ? (string)$f_meta['type'] : 'text box';

        // Collect raw value(s) from POST. Multi-select / checkbox arrays
        // collapse to a comma-joined string for storage.
        $raw = isset($_POST[$key]) ? $_POST[$key] : '';
        if (is_array($raw)) {
            $raw = implode(', ', array_map('strval', $raw));
        }
        $raw = trim((string)$raw);

        // Store-time conversion for date/time types — INPUT[type=date]
        // returns "YYYY-MM-DD"; we keep as unix-ts in form_data for
        // round-trip with the legacy display logic.
        $store_val = $raw;
        $store_type = 'standard';
        if ($raw !== '') {
            if ($f_type === 'date') {
                $ts = strtotime($raw . ' 00:00:00');
                if ($ts !== false) { $store_val = (string)$ts; $store_type = 'date'; }
            } elseif ($f_type === 'date and time') {
                $ts = strtotime(str_replace('T', ' ', $raw));
                if ($ts !== false) { $store_val = (string)$ts; $store_type = 'date and time'; }
            } elseif ($f_type === 'time') {
                $ts = strtotime(date('Y-m-d') . ' ' . $raw);
                if ($ts !== false) { $store_val = (string)$ts; $store_type = 'time'; }
            }
        }

        // Upsert: DELETE then INSERT so we never end up with multiple rows
        // for the same (item, qty, field) — keeps form_data clean across
        // repeated cart updates by the same visitor.
        db("DELETE FROM form_data
            WHERE order_item_id = '" . (int)$iid . "'
              AND quantity_number = '" . (int)$qn . "'
              AND form_field_id = '" . (int)$fid . "'");
        db("INSERT INTO form_data
                (form_id, form_field_id, data, file_id, order_id, order_item_id,
                 quantity_number, name, type, ship_to_id)
            VALUES
                ('0', '" . (int)$fid . "', '" . e($store_val) . "', '0',
                 '" . (int)$oid . "', '" . (int)$iid . "',
                 '" . (int)$qn . "', '', '" . e($store_type) . "', '0')");
        $_pg_form_saved++;
    }

    // ── Per-item gift-card data save ───────────────────────────────────
    // Gift cards do NOT go through form_fields/form_data — they have their
    // own products.gift_card flag and a fixed four-column row in
    // order_item_gift_cards. Field name format matches the legacy cart
    // (shopping_cart.php) so both front ends stay interchangeable:
    //
    //   order_item_<item_id>_quantity_number_<q>_gift_card_<column>
    //
    // Without this branch the cart rendered the inputs but "Update" threw
    // the visitor's typed values away, and checkout then failed on the
    // required recipient e-mail with nothing on screen to explain it.
    $_pg_gc_re    = '/^order_item_(\d+)_quantity_number_(\d+)_gift_card_(recipient_email_address|from_name|message|delivery_date)$/';
    $_pg_gc_input = array();   // [item_id][qty][column] = raw value
    foreach ($_POST as $key => $val) {
        if (is_array($val)) continue;
        if (!preg_match($_pg_gc_re, $key, $m)) continue;
        $_pg_gc_input[(int)$m[1]][(int)$m[2]][$m[3]] = trim((string)$val);
    }
    $_pg_gc_saved = 0;
    foreach ($_pg_gc_input as $iid => $by_qty) {
        // Anti-tamper: the order_item must belong to THIS order and its
        // product must actually be a gift card.
        $owns = (int)db_value(
            "SELECT 1 FROM order_items oi
             INNER JOIN products p ON p.id = oi.product_id AND p.gift_card = 1
             WHERE oi.id = '" . (int)$iid . "'
               AND oi.order_id = '" . (int)$oid . "'
             LIMIT 1"
        );
        if (!$owns) continue;

        foreach ($by_qty as $qn => $cols) {
            if ($qn <= 0) continue;

            // INPUT[type=date] posts "YYYY-MM-DD"; the column is a DATE.
            // A date of today or earlier means "send immediately", stored
            // as blank — same rule the legacy cart applies.
            $delivery = isset($cols['delivery_date']) ? $cols['delivery_date'] : '';
            if ($delivery !== '') {
                $ts = strtotime($delivery);
                $delivery = ($ts === false) ? '' : date('Y-m-d', $ts);
                if ($delivery !== '' && $delivery <= date('Y-m-d')) $delivery = '';
            }

            // Upsert — DELETE then INSERT keeps one row per (item, qty)
            // however many times the visitor presses Update.
            db("DELETE FROM order_item_gift_cards
                WHERE order_item_id = '" . (int)$iid . "'
                  AND quantity_number = '" . (int)$qn . "'");
            db("INSERT INTO order_item_gift_cards
                    (order_id, order_item_id, quantity_number,
                     from_name, recipient_email_address, message, delivery_date)
                VALUES
                    ('" . (int)$oid . "', '" . (int)$iid . "', '" . (int)$qn . "',
                     '" . e(isset($cols['from_name']) ? $cols['from_name'] : '') . "',
                     '" . e(isset($cols['recipient_email_address']) ? $cols['recipient_email_address'] : '') . "',
                     '" . e(isset($cols['message']) ? $cols['message'] : '') . "',
                     '" . e($delivery) . "')");
            $_pg_gc_saved++;
        }
    }

    // ── Recurring schedule ─────────────────────────────────────────────
    // Every recurring row gets its schedule written on every update, the
    // way legacy shopping_cart.php does: the customer's own choice when the
    // product lets the customer set it (recurring_payment_period_<id>,
    // recurring_number_of_payments_<id>, recurring_start_date_<id> — the
    // legacy field names), the product's defaults otherwise. The gateway
    // rules are the legacy ones too: ClearCommerce needs 2-999 payments and
    // always starts today, First Data needs 1-99, everyone else takes any
    // count (blank = no limit) and a start date that is not in the past.
    // A row that fails validation keeps its stored schedule and the message
    // lands on $lf for the widget's Messages node.
    $_rec_rows = db_items(
        "SELECT oi.id AS item_id, oi.quantity, oi.recurring_payment_period,
                p.payment_period, p.recurring_schedule_editable_by_customer,
                p.number_of_payments AS product_number_of_payments, p.start AS recurring_start_days
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.product_id AND p.recurring = 1
         WHERE oi.order_id = '" . (int)$oid . "'"
    );
    $_rec_cc      = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == true;
    $_rec_gateway = defined('ECOMMERCE_PAYMENT_GATEWAY') ? (string)ECOMMERCE_PAYMENT_GATEWAY : '';
    $_rec_periods = function_exists('get_payment_period_options') ? array_values(get_payment_period_options()) : array();
    $_rec_saved   = 0;
    foreach ((array)$_rec_rows as $_rr) {
        $_riid = (int)$_rr['item_id'];
        if ($_riid <= 0 || (int)$_rr['quantity'] <= 0) continue;
        $_def = function_exists('_pg_cart_recurring_defaults') ? _pg_cart_recurring_defaults($_rr) : array(
            'period'     => ((string)$_rr['payment_period'] !== '') ? (string)$_rr['payment_period'] : 'Monthly',
            'payments'   => (int)$_rr['product_number_of_payments'],
            'start_date' => date('Y-m-d', time() + (86400 * max(0, (int)$_rr['recurring_start_days']))),
        );
        $_period   = $_def['period'];
        $_payments = (int)$_def['payments'];
        $_start    = $_def['start_date'];

        if ((int)$_rr['recurring_schedule_editable_by_customer'] === 1) {
            $_n_period   = 'recurring_payment_period_' . $_riid;
            $_n_payments = 'recurring_number_of_payments_' . $_riid;
            $_n_start    = 'recurring_start_date_' . $_riid;
            $_row_ok = true;
            $_p = $_c = $_d = '';

            // Frequency: one of the known periods; blank falls back to the
            // product's, which is what the control was showing anyway.
            $_p = trim((string)($_POST[$_n_period] ?? ''));
            if ($_p !== '') {
                if (in_array($_p, $_rec_periods, true)) {
                    $_period = $_p;
                } else {
                    $lf->mark_error($_n_period, lang('Frequency is required.'));
                    $_row_ok = false;
                }
            }

            // Number of payments: digits only; the gateway sets the range.
            $_c = trim((string)($_POST[$_n_payments] ?? ''));
            if ($_c === '') {
                if ($_rec_cc && ($_rec_gateway === 'ClearCommerce' || $_rec_gateway === 'First Data Global Gateway')) {
                    $lf->mark_error($_n_payments, lang('Number of Payments is required.'));
                    $_row_ok = false;
                } else {
                    $_payments = 0;
                }
            } elseif (!ctype_digit($_c)) {
                $lf->mark_error($_n_payments, lang('Number of Payments must be a whole number.'));
                $_row_ok = false;
            } else {
                $_payments = (int)$_c;
                if ($_rec_cc && $_rec_gateway === 'ClearCommerce' && ($_payments < 2 || $_payments > 999)) {
                    $lf->mark_error($_n_payments, lang('Number of Payments requires a value from 2-999.'));
                    $_row_ok = false;
                } elseif ($_rec_cc && $_rec_gateway === 'First Data Global Gateway' && ($_payments < 1 || $_payments > 99)) {
                    $lf->mark_error($_n_payments, lang('Number of Payments requires a value from 1-99.'));
                    $_row_ok = false;
                }
            }

            // Start date: not on ClearCommerce (bills from today). The widget
            // posts Y-m-d; the legacy screen's d/m/Y is accepted as well.
            if ($_rec_cc && $_rec_gateway === 'ClearCommerce') {
                $_start = date('Y-m-d');
            } else {
                $_d = trim((string)($_POST[$_n_start] ?? ''));
                if ($_d !== '') {
                    $_iso = '';
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $_d, $_dm) && checkdate((int)$_dm[2], (int)$_dm[3], (int)$_dm[1])) {
                        $_iso = $_d;
                    } elseif (function_exists('validate_date') && validate_date($_d)) {
                        $_iso = prepare_form_data_for_input($_d, 'date');
                    }
                    if ($_iso === '') {
                        $lf->mark_error($_n_start, lang('Start Date must contain a valid date.'));
                        $_row_ok = false;
                    } elseif ($_iso < date('Y-m-d')) {
                        $lf->mark_error($_n_start, lang('Start Date may not contain a date in the past.'));
                        $_row_ok = false;
                    } else {
                        $_start = $_iso;
                    }
                }
            }

            if (!$_row_ok) {
                // Keep the typed values for the re-render; the row's stored
                // schedule stays as it was.
                foreach (array($_n_period => $_p, $_n_payments => $_c, $_n_start => $_d) as $_k => $_v) {
                    if (isset($_POST[$_k])) $lf->assign_field_value($_k, $_v);
                }
                continue;
            }
        }

        db("UPDATE order_items
            SET recurring_payment_period = '" . e($_period) . "',
                recurring_number_of_payments = '" . (int)$_payments . "',
                recurring_start_date = '" . e($_start) . "'
            WHERE id = '" . $_riid . "' AND order_id = '" . (int)$oid . "'");
        $_rec_saved++;
    }

    // ── Offline-payment flag (STAFF ONLY) ──────────────────────────────
    // Mirrors the render-side gate in _pg_cart_offline_payment_checkbox():
    // the feature must be on, someone must be logged in, and that user must
    // be staff (role < 3) or carry set_offline_payment. Re-checked HERE and
    // not merely at render time — the checkbox is just HTML, and a visitor
    // could otherwise POST offline_payment_allowed=1 and check out without
    // paying. An unchecked box submits nothing, so absence means "clear it".
    if (defined('ECOMMERCE_OFFLINE_PAYMENT') && ECOMMERCE_OFFLINE_PAYMENT == true
        && defined('USER_LOGGED_IN') && USER_LOGGED_IN && $oid > 0) {
        $_off_role = defined('USER_ROLE') ? (int)USER_ROLE : 99;
        $_off_can  = ($_off_role < 3);
        if (!$_off_can && defined('USER_ID') && (int)USER_ID > 0) {
            $_off_probe = db_value("SHOW COLUMNS FROM user LIKE 'set_offline_payment'");
            if ($_off_probe !== '' && $_off_probe !== null) {
                $_off_can = (int)db_value(
                    "SELECT set_offline_payment FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1"
                ) === 1;
            }
        }
        if ($_off_can) {
            $_off_val = !empty($_POST['offline_payment_allowed']) ? 1 : 0;
            db("UPDATE orders SET offline_payment_allowed = '$_off_val' WHERE id = '$oid'");
        }
    }

    // A rejected schedule field already speaks for itself; "Cart updated."
    // next to it would say the opposite of what happened to that row.
    if (!$lf->check_form_errors()) {
        $lf->add_notice(lang('Cart updated.'));
    }
    $did_mutate = true;
}

// ── Apply / clear coupon ────────────────────────────────────────────────
// Same isset() reasoning — bindable apply_coupon buttons could submit an
// empty value if the btn renderer ever stripped their `value` attr.
if (isset($_POST['submit_special_offer_code']) && $oid > 0) {
    $code = isset($_POST['special_offer_code']) ? trim((string)$_POST['special_offer_code']) : '';
    if ($code === '') {
        // Empty submit → clear current code.
        db("UPDATE orders SET special_offer_code = '' WHERE id = '$oid'");
        $did_mutate = true;
    } else {
        $offer_code = function_exists('get_offer_code_for_special_offer_code')
                          ? get_offer_code_for_special_offer_code($code) : '';
        if ($offer_code) {
            db("UPDATE orders SET special_offer_code = '" . e($code) . "' WHERE id = '$oid'");
            $lf->add_notice(lang(array(
                'string' => 'Coupon "{var:1}" applied.',
                'vars'   => array(h($code))
            )));
            $did_mutate = true;
        } else {
            $lf->mark_error('special_offer_code', lang(array(
                'string' => 'The coupon "{var:1}" is invalid or expired.',
                'vars'   => array(h($code))
            )));
            // Even invalid attempts mutate session (the error message
            // belongs in the redirect target's notice block).
            $did_mutate = true;
        }
    }
}

// ── Claim a pending offer ("gift with purchase") ────────────────────────
// The cart widget's `pending_offers` section renders its own <form> whose
// submit buttons are named add_pending_offer_<offer_id>_<action_id>, plus a
// hidden `pending_offers=true` marker — the exact field shape the legacy
// /sepet template used, because add_pending_offers() parses those names with
// substr/explode rather than reading a structured payload.
//
// All the real work (validating the offer is still live, resolving or
// creating the ship_to, inserting the order_item at the discounted price)
// already lives in add_pending_offers(); we only need to route to it. Errors
// land on $lf via mark_error and surface in the widget's message area after
// the redirect below.
if (!empty($_POST['pending_offers']) && $oid > 0 && function_exists('add_pending_offers')) {
    add_pending_offers($lf);
    $did_mutate = true;
}

// ── Quick add ───────────────────────────────────────────────────────────
// The widget's quick-add box (own <form>): pick a product from the group the
// operator configured and drop it in the cart. Same rules the legacy branch
// in shopping_cart.php:42-185 applies — availability, per-selection-type
// field, recipient requirement — but the item goes in through
// add_order_item(), so pricing, offers and shipping behave exactly as they
// do everywhere else.
//
// Offers are refreshed afterwards: a quick-added item can be the one that
// qualifies the cart for a discount, and the new offer engine adds the gift
// itself (apply_offer_gift_to_cart) instead of leaving a pending claim, so
// the visitor sees the result on the very next render.
if (!empty($_POST['quick_add']) && $oid > 0 && function_exists('add_order_item')) {
    $qa_pid = isset($_POST['quick_add_product_id']) ? (int)$_POST['quick_add_product_id'] : 0;
    $qa_ship_to = isset($_POST['quick_add_ship_to']) ? (string)$_POST['quick_add_ship_to'] : '';
    $qa_add_name = isset($_POST['quick_add_add_name']) ? (string)$_POST['quick_add_add_name'] : '';

    $qa_product = $qa_pid > 0 ? db_item(
        "SELECT name, enabled, short_description, price, shippable, selection_type,
                default_quantity, inventory, inventory_quantity, backorder
         FROM products WHERE id = '" . e($qa_pid) . "' LIMIT 1") : null;

    if (!$qa_product) {
        $lf->mark_error('quick_add_product_id', lang('The item that you selected could not be found. Please select a different item to add.'));
    } elseif (($qa_product['enabled'] != 1)
              || (($qa_product['inventory'] == 1) && ($qa_product['inventory_quantity'] <= 0) && ($qa_product['backorder'] != 1))) {
        $qa_desc = trim(((string)$qa_product['name'] !== '' ? (string)$qa_product['name'] : '')
                 . (((string)$qa_product['name'] !== '' && (string)$qa_product['short_description'] !== '') ? ' - ' : '')
                 . (string)$qa_product['short_description']);
        $lf->mark_error('quick_add_product_id', lang(array('string' => 'Sorry, {var:1} is not currently available.', 'vars' => $qa_desc)));
    } else {
        // A shippable product on a multi-recipient site has to say who it is
        // for; everywhere else the question does not exist.
        $qa_needs_recipient = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == true)
                           && (defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE == 'multi-recipient')
                           && ($qa_product['shippable'] == 1);

        if ($qa_needs_recipient && ($qa_ship_to === '') && (trim($qa_add_name) === '')) {
            $lf->mark_error('quick_add_ship_to', lang('The item that you attempted to add requires a recipient.'));
        } else {
            $qa_added = false;
            switch ((string)$qa_product['selection_type']) {
                case 'checkbox':
                case 'autoselect':
                    add_order_item($qa_pid, (int)$qa_product['default_quantity'], 0, $qa_ship_to, $qa_add_name);
                    $qa_added = true;
                    break;

                case 'quantity':
                    $qa_qty = (int)preg_replace('/[^\d]/', '', (string)(isset($_POST['quick_add_quantity']) ? $_POST['quick_add_quantity'] : ''));
                    if ($qa_qty > 0) {
                        add_order_item($qa_pid, $qa_qty, 0, $qa_ship_to, $qa_add_name);
                        $qa_added = true;
                    } else {
                        $lf->mark_error('quick_add_quantity', lang('Please enter a valid quantity.'));
                    }
                    break;

                case 'donation':
                    // Same locale-tolerant parse the donation rows use.
                    $qa_amount = trim((string)(isset($_POST['quick_add_amount']) ? $_POST['quick_add_amount'] : ''));
                    $qa_amount = preg_replace('/[^\d.,-]/', '', $qa_amount);
                    if (substr_count($qa_amount, ',') && substr_count($qa_amount, '.')) {
                        $qa_amount = (strrpos($qa_amount, ',') > strrpos($qa_amount, '.'))
                            ? str_replace(array('.', ','), array('', '.'), $qa_amount)
                            : str_replace(',', '', $qa_amount);
                    } else {
                        $qa_amount = str_replace(',', '.', $qa_amount);
                    }
                    $qa_rate = (defined('VISITOR_CURRENCY_EXCHANGE_RATE') && (float)VISITOR_CURRENCY_EXCHANGE_RATE > 0)
                        ? (float)VISITOR_CURRENCY_EXCHANGE_RATE : 1.0;
                    $qa_cents = (int)round(((float)$qa_amount / $qa_rate) * 100);
                    if ($qa_cents > 0) {
                        add_order_item($qa_pid, 1, $qa_cents, $qa_ship_to, $qa_add_name);
                        $qa_added = true;
                    } else {
                        $lf->mark_error('quick_add_amount', lang('Please enter an amount.'));
                    }
                    break;

                default:
                    add_order_item($qa_pid, max(1, (int)$qa_product['default_quantity']), 0, $qa_ship_to, $qa_add_name);
                    $qa_added = true;
            }

            if ($qa_added) {
                if (function_exists('update_order_item_prices')) update_order_item_prices();
                if (function_exists('apply_offers_to_cart'))     apply_offers_to_cart();
                $lf->add_notice(lang('The item has been added to your cart.'));
            }
        }
    }
    $did_mutate = true;
}

// ── Save for later / restore-from-saved ─────────────────────────────────
// Opt-in via ECOMMERCE_SAVE_FOR_LATER config define. Operates on the
// order_items.saved_for_later flag (added in upgrade_to_2026_1_28).
//
//   submit_save_for_later=1 + save_item_id=<id>     → flip saved_for_later=1
//   submit_restore_saved=1   + save_item_id=<id>    → flip saved_for_later=0
//
// We probe the column once per request so the endpoint stays stable on
// installs that haven't run the migration yet (the endpoint just no-ops
// instead of producing a query error).
if ((isset($_POST['submit_save_for_later']) || isset($_POST['submit_restore_saved']))
    && $oid > 0
    && defined('ECOMMERCE_SAVE_FOR_LATER') && ECOMMERCE_SAVE_FOR_LATER === true) {
    $_save_iid = isset($_POST['save_item_id']) ? (int)$_POST['save_item_id'] : 0;
    if ($_save_iid > 0) {
        static $_sfl_has_col = null;
        if ($_sfl_has_col === null) {
            $_p = db_value("SHOW COLUMNS FROM order_items LIKE 'saved_for_later'");
            $_sfl_has_col = ($_p !== '' && $_p !== null);
        }
        if ($_sfl_has_col) {
            $_owns = (int)db_value("SELECT id FROM order_items
                                    WHERE id = '$_save_iid' AND order_id = '$oid' LIMIT 1");
            if ($_owns === $_save_iid) {
                if (isset($_POST['submit_save_for_later'])) {
                    db("UPDATE order_items
                        SET saved_for_later = 1, saved_at = UNIX_TIMESTAMP()
                        WHERE id = '$_save_iid'");
                    $lf->add_notice(lang('Item saved for later.'));
                } else {
                    db("UPDATE order_items
                        SET saved_for_later = 0, saved_at = NULL
                        WHERE id = '$_save_iid'");
                    $lf->add_notice(lang('Item restored to cart.'));
                }
                $did_mutate = true;
            }
        }
    }
}

// ── Refresh prices + offers BEFORE the redirect ─────────────────────────
// Without this the post-redirect GET would still see the pre-mutation
// discount/total until apply_offers_to_cart runs again on the cart render.
// Doing it here means the new totals are correct on the very first GET
// after this redirect.
if ($did_mutate) {
    update_order_item_prices();
    apply_offers_to_cart();
}

// ── Resolve redirect target ─────────────────────────────────────────────
// `send_to` is a same-host path written into the form by the widget render
// (typically the cart page URL the visitor was on). Reject absolute URLs
// (open-redirect protection) and fall back to '/' if anything looks off.
$send_to = isset($_POST['send_to']) ? (string)$_POST['send_to'] : '/';
if (!preg_match('#^(?:/|\?)#', $send_to) || stripos($send_to, '://') !== false) {
    $send_to = '/';
}

// Flush session writes so the next request sees the notice + updated order.
session_write_close();

header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($send_to));
exit;
