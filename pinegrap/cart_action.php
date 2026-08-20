<?php
/**
 * PineGrap - Enterprise Website Platform — System-widget cart POST handler.
 *
 * Forms rendered by `_render_system_widget_shopping_cart` (the Visual
 * Pinegrap Editor's shopping_cart widget) post here so a `header('Location: …')`
 * redirect is guaranteed to fire — handling the POST from inside the widget
 * render function fights with the page-rendering pipeline (output buffering,
 * intermediate rendering steps, etc.) and the resulting "form yeniden
 * gönderme" prompt was the visible symptom.
 *
 * Supported actions (mutually exclusive per POST):
 *   submit_update_cart           — quantities[item_id] map → UPDATE order_items
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

// ── GET self-test ───────────────────────────────────────────────────────
// Hitting this URL directly with GET prints a tiny status page instead of
// silently redirecting. Lets the operator verify that the URL is reachable
// and that init.php loaded cleanly. POSTs continue normally below.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_POST)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Pinegrap cart_action.php — POST handler (GET self-test).\n\n";
    echo "init.php loaded:        OK\n";
    echo "ECOMMERCE constant:     " . (defined('ECOMMERCE') ? (ECOMMERCE ? 'true' : 'false') : 'UNDEFINED') . "\n";
    echo "OUTPUT_PATH:            " . (defined('OUTPUT_PATH') ? OUTPUT_PATH : 'UNDEFINED') . "\n";
    echo "OUTPUT_SOFTWARE_DIR:    " . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'UNDEFINED') . "\n";
    echo "URL_SCHEME + HOSTNAME:  " . (defined('URL_SCHEME') ? URL_SCHEME : '?') . (defined('HOSTNAME') ? HOSTNAME : '?') . "\n";
    echo "session_id:             " . session_id() . "\n";
    echo "session order_id:       " . (isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : '0') . "\n";
    echo "data/cart_action.log:   " . (file_exists(dirname(__FILE__) . '/data/cart_action.log') ? 'exists' : 'missing') . "\n";
    echo "log file size:          " . (file_exists(dirname(__FILE__) . '/data/cart_action.log') ? filesize(dirname(__FILE__) . '/data/cart_action.log') . ' bytes' : '-') . "\n";
    echo "\nLast 30 log lines:\n";
    echo str_repeat('-', 60) . "\n";
    if (file_exists(dirname(__FILE__) . '/data/cart_action.log')) {
        $_lines = @file(dirname(__FILE__) . '/data/cart_action.log');
        if (is_array($_lines)) {
            $_tail = array_slice($_lines, -30);
            foreach ($_tail as $_l) echo $_l;
        }
    }
    exit;
}

// ── Debug log (one rotating file) ───────────────────────────────────────
// Writes every invocation to data/cart_action.log so we can diagnose why
// updates / coupons aren't taking. Lines: ISO timestamp · POST keys · $oid
// · resulting mutations. Trim if file grows past 1MB.
$_pg_log_path = dirname(__FILE__) . '/data/cart_action.log';
$_pg_log = function ($msg) use ($_pg_log_path) {
    @file_put_contents(
        $_pg_log_path,
        '[' . date('c') . '] ' . $msg . "\n",
        FILE_APPEND | LOCK_EX
    );
    if (@filesize($_pg_log_path) > 1048576) {
        @unlink($_pg_log_path);
    }
};
$_pg_log('ENTER cart_action.php · POST=' . json_encode(array_keys($_POST))
       . ' · qty_keys=' . (isset($_POST['quantities']) && is_array($_POST['quantities'])
                              ? json_encode(array_keys($_POST['quantities']))
                              : 'NONE'));

// Resolve the active session order. initialize_order creates one when
// missing, so $oid is always > 0 after this call.
initialize_order();
$oid = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
$_pg_log('order_id=' . $oid);

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
    $_pg_log('UPDATE branch entered · qty_arr=' . json_encode($qty_arr));
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
    if ($don_applied > 0) $_pg_log('UPDATE donations applied · count=' . $don_applied);

    $_pg_log('UPDATE applied · count=' . $applied);

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
    if ($_pg_form_saved > 0) $_pg_log('UPDATE form_data saved · count=' . $_pg_form_saved);

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
    if ($_pg_gc_saved > 0) $_pg_log('UPDATE gift_card saved · count=' . $_pg_gc_saved);

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
            $_pg_log('UPDATE offline_payment_allowed=' . $_off_val);
        }
    }

    $lf->add_notice(lang('Cart updated.'));
    $did_mutate = true;
} else {
    $_pg_log('UPDATE branch SKIPPED · submit_key=' . (array_key_exists('submit_update_cart', $_POST) ? 'YES' : 'NO')
           . ' · oid=' . $oid);
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
    $_pg_log('PENDING OFFERS branch entered · keys='
           . json_encode(array_values(array_filter(array_keys($_POST), function ($k) {
                 return strncmp($k, 'add_pending_offer_', 18) === 0;
             }))));
    add_pending_offers($lf);
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

$_pg_log('did_mutate=' . ($did_mutate ? '1' : '0') . ' · redirect to=' . $send_to);

// Flush session writes so the next request sees the notice + updated order.
session_write_close();

header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);
exit;
