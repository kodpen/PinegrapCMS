<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The express order widget (_eo_*) and the order view widget helpers.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Designer bindings: walk tree for express-order section markers ──────────
// Uses the SAME convention as the catalog widget: `props._bindings.section`
// (not a top-level `_bindings`). Values are prefixed `eo_` so they don't
// collide with catalog's binding namespace (breadcrumb, filter_chips, …).
// Returns true on first match so the dispatcher knows to take the bindings
// render path instead of the monolithic Phase-1 fallback.
function _eo_tree_has_section_bindings($tree)
{
    if (!is_array($tree)) return false;
    $sec = isset($tree['props']['_bindings']['section']) ? (string)$tree['props']['_bindings']['section'] : '';
    if ($sec !== '' && strpos($sec, 'eo_') === 0) return true;
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) {
            if (_eo_tree_has_section_bindings($child)) return true;
        }
    }
    return false;
}

// True when the tree carries a `messages` content node ("PHP Mesajları").
// That node renders the liveform's errors + notices AND consumes them from
// the session, so any other element fed from the same liveform would print a
// second copy. Used to suppress the `errors_notices` section — see the call
// site in _render_system_widget_express_order for the reasoning.
function _eo_tree_has_messages_node($tree)
{
    if (!is_array($tree)) return false;
    if (isset($tree['type']) && $tree['type'] === 'content'
        && isset($tree['props']['contentType']) && $tree['props']['contentType'] === 'messages') {
        return true;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) {
            if (_eo_tree_has_messages_node($child)) return true;
        }
    }
    return false;
}

// ── Designer bindings: substitute section HTML into the tree in place ───────
// For every node carrying `props._bindings.section='eo_X'`, replace its
// children with a single custom_html content node whose body IS the section's
// HTML. The eo_ prefix lets us share this walker with the catalog binding
// namespace without crosstalk. Unknown markers emit a harmless HTML comment.
function _eo_apply_section_bindings(&$tree, $sections_html)
{
    if (!is_array($tree)) return;

    $sec_raw = isset($tree['props']['_bindings']['section']) ? (string)$tree['props']['_bindings']['section'] : '';
    if ($sec_raw !== '' && strpos($sec_raw, 'eo_') === 0) {
        $marker = substr($sec_raw, 3); // strip leading "eo_"
        $html = isset($sections_html[$marker]) ? (string)$sections_html[$marker]
                                                : '<!-- pg-eo: unknown section binding ' . h($sec_raw) . ' -->';
        $tree['children'] = array(array(
            'type'     => 'content',
            'props'    => array('contentType' => 'custom_html', 'html' => $html),
            'children' => array(),
        ));
        // Don't recurse into children we just overwrote.
        return;
    }

    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $_child) {
            _eo_apply_section_bindings($tree['children'][$i], $sections_html);
        }
    }
}

// ── Saved-cart restore — visitor clicks the "?r=REFERENCE_CODE" link emailed
// or saved from a prior session. Find the matching incomplete order and
// swap session order_id to it. Called BEFORE initialize_order() so the
// restored order_id flows through the rest of the cart pipeline.
//
// Safety rules:
//   • reference_code must be 10-character alphanumeric (legacy format)
//   • order must exist AND be incomplete (order_date IS NULL = not yet placed)
//   • restoring overwrites whatever was in session (visitor explicitly asked
//     for THIS cart by clicking the link — their current session items, if
//     any, are discarded; UI should warn but legacy never did)
function _eo_restore_cart_from_reference_code()
{
    if (empty($_GET['r'])) return;
    $ref = trim((string)$_GET['r']);
    // Legacy reference codes are 10-char alphanumeric (see generate_order_reference_code).
    // Cap at 50 defensively in case of format drift.
    if ($ref === '' || strlen($ref) > 50 || !preg_match('/^[A-Za-z0-9]+$/', $ref)) return;
    // Order is "restorable" iff it hasn't been finalized — `status='incomplete'`
    // AND `order_number IS NULL` (set only when payment confirms). `order_date`
    // gets stamped at cart-creation, NOT at finalization, so it can\'t be
    // used as the gating signal.
    $row = db_item(
        "SELECT id FROM orders
         WHERE reference_code = '" . e($ref) . "'
           AND status = 'incomplete'
           AND (order_number IS NULL OR order_number = 0)
         LIMIT 1"
    );
    if (!is_array($row) || empty($row['id'])) return;

    if (!isset($_SESSION['ecommerce']) || !is_array($_SESSION['ecommerce'])) {
        $_SESSION['ecommerce'] = array();
    }
    $prev_oid = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
    $new_oid  = (int)$row['id'];
    $_SESSION['ecommerce']['order_id'] = $new_oid;

    // Ownership transfer: initialize_order() ALREADY updates orders.user_id
    // and contact_id to the current visitor when they\'re logged in (see
    // functions.php:6335-6351). So once we swap order_id here, the next
    // initialize_order() call rebinds the cart to the link clicker — the
    // cart is assigned to whoever uses the link, which is the intended
    // behavior.
    //
    // For GUEST visitors we leave user_id as-is (the original owner). A
    // guest can\'t take ownership because they don\'t have an account; if
    // they later log in, initialize_order() picks up the transfer.

    // Surface a one-shot notice so the visitor knows the cart was restored.
    // liveform notice is the channel cart/express-order widgets render via
    // their messages content node — keeps styling consistent and the
    // message disappears after one render via clear_notices.
    if (class_exists('liveform') && $prev_oid !== $new_oid) {
        // Notify on BOTH form names — cart widget uses 'shopping_cart',
        // express order uses 'express_order'. Posting to whichever liveform
        // the current page widget reads is harmless on the other.
        foreach (array('shopping_cart', 'express_order') as $_pg_lfname) {
            $_pg_lf = new liveform($_pg_lfname);
            if (method_exists($_pg_lf, 'add_notice')) {
                $_pg_lf->add_notice(lang('Your saved cart has been restored.'));
            }
        }
    }
}

// ── Designer bindings: token computation + replacement ──────────────────────
// The designer's tree contains literal element markup with `^^token^^` style
// placeholders (the same `_apply_bindings()` mechanism the catalog widget
// uses). We compute the variable map once per render, then str_replace it
// into the final HTML. Static tokens replace once; per-item tokens replace
// per loop iteration.
//
// Both carry the `__` prefix and the cart renderer's names, because they are
// the cart renderer's values: this widget checks out the same cart. They used
// to be bare (`^^subtotal_formatted^^`, `^^form_id^^`) — the only widget that
// namespaced nothing, which is how `^^form_id^^` came to compete with a form
// field a visitor could name. Bare names are reserved for the submitted
// form's standard fields (`^^reference_code^^`, `^^email_address^^`), which
// the classic screens resolve by those exact names.
//
// Tokens — STATIC (replaced once after final tree render):
//   ^^__cart_subtotal^^                     "₺17,64"
//   ^^__cart_tax^^                          "₺2,69"
//   ^^__cart_shipping^^                     "₺0,00"
//   ^^__cart_discount^^                     "-₺0,00"
//   ^^__cart_gift_card_discount^^           "-₺0,00"
//   ^^__cart_surcharge^^                    "₺0,75"
//   ^^__cart_total^^                        "₺17,64" (without surcharge)
//   ^^__cart_total_with_surcharge^^        "₺18,39" (with surcharge)
//   ^^__cart_count^^                        "3"
//   ^^__cart_count_label^^                  "3 items"
//   ^^__cart_subtotal_cents^^               "1764"  (raw int, used in JS hooks)
//   ^^__cart_total_cents^^                  "1764"
//   ^^__cart_total_with_surcharge_cents^^   "1839"
//   ^^__currency_symbol^^                   "₺"
//   ^^__form_id^^                          "pg-eo-form-w79" (parent form's id attr)
//   ^^__cart_total_charged^^                "₺18,39 TRY" — what the card is
//                                           charged, in the base currency
//   ^^__currency_disclaimer^^               the exchange-rate note; both for
//                                           the has_foreign_currency flag
//
// Tokens — PER ITEM (replaced inside loop template for each cart item):
//   ^^__item_id^^                    "612"            (order_items.id)
//   ^^__item_name^^                  "Office Chair"   (short_description or name)
//   ^^__item_qty^^                   "1"
//   ^^__item_price^^               "₺14,95"
//   ^^__item_total^^               "₺14,95"
//   ^^__item_image^^                 "/files/photo.jpg" (or empty)
//   ^^__item_remove_url^^            full /remove_item_from_cart.php?…&token=…
//   ^^__item_form_html^^             optional extra <tr>(s) for gift card / form
function _eo_compute_static_tokens($state)
{
    $fmt = isset($state['fmt']) && is_callable($state['fmt'])
        ? $state['fmt']
        : function ($c) { return '' . ((int)$c / 100); };
    $sub  = (int)($state['subtotal_cents']             ?? 0);
    $tax  = (int)($state['tax_cents']                  ?? 0);
    $ship = (int)($state['shipping_cents']             ?? 0);
    $disc = (int)($state['discount_cents']             ?? 0);
    $gdsc = (int)($state['gift_card_discount_cents']   ?? 0);
    $surc = (int)($state['surcharge_cents']            ?? 0);
    $tot  = (int)($state['total_cents']                ?? 0);
    $tws  = (int)($state['total_with_surcharge_cents'] ?? $tot);
    $cnt  = (int)($state['cart_count']                 ?? 0);
    $sym  = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : (defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '');

    // Shipping label: when the cart has a shippable item but cost resolves
    // to 0 (free-shipping promo, no method picked yet, ECOMMERCE_FREE_*),
    // show "Ücretsiz" / "Free" instead of "₺0,00". The currency-formatted
    // amount reads as missing data to most visitors; "Free" is the clear
    // signal. Caller passes `needs_shipping` so we know the cart actually
    // expects a shipping line (digital orders skip this branch entirely
    // via the has_shipping_cost visibility flag).
    $needs_shipping = !empty($state['needs_shipping']);
    $shipping_label = ($ship === 0 && $needs_shipping)
        ? (string)lang('Free')
        : $fmt($ship);

    return array(
        '^^__cart_subtotal^^'           => $fmt($sub),
        '^^__cart_tax^^'                => $fmt($tax),
        '^^__cart_shipping^^'           => $shipping_label,
        '^^__cart_discount^^'           => '-' . $fmt($disc),
        '^^__cart_gift_card_discount^^' => '-' . $fmt($gdsc),
        '^^__cart_surcharge^^'          => $fmt($surc),
        '^^__cart_total^^'              => $fmt($tot),
        '^^__cart_total_with_surcharge^^' => $fmt($tws),
        '^^__cart_count^^'                   => (string)$cnt,
        '^^__cart_count_label^^'             => ($cnt === 1
            ? lang(array('string' => '{var:1} item',  'vars' => array($cnt)))
            : lang(array('string' => '{var:1} items', 'vars' => array($cnt)))),
        '^^__cart_subtotal_cents^^'               => (string)$sub,
        '^^__cart_total_cents^^'                  => (string)$tot,
        '^^__cart_total_with_surcharge_cents^^'   => (string)$tws,
        '^^__currency_symbol^^'              => $sym,
        '^^__form_id^^'                      => (string)($state['form_id'] ?? ''),
        // The gateway takes the base currency only, so a visitor reading the
        // page in another currency is shown what the card is charged and why
        // the figures above may differ (the classic screens' note).
        '^^__cart_total_charged^^'           => pg_format_money($tws / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '')
                                                . (defined('BASE_CURRENCY_CODE') ? ' ' . h(BASE_CURRENCY_CODE) : ''),
        '^^__currency_disclaimer^^'          => _eo_currency_disclaimer(),
    );
}

// The instalment scripts' syncTotals(block): puts the chosen plan into the
// totals. The plan's amounts are in the base currency the gateway charges
// (Iyzipay takes Turkish lira only); the total and fee rows are shown in the
// visitor's currency like every other figure on the page, the "amount
// charged" row keeps the base amount, and the hidden total fields - what the
// server checks - stay in the base currency.
function _eo_installment_sync_js()
{
    return 'function syncTotals(block){'
        .   'var data=block.__pgEoInstData;if(!data||!data.installments)return;'
        .   'var hidden=block.querySelector(".pg-eo-installment-input");var n=parseInt(hidden?hidden.value:"1",10)||1;'
        .   'var plan=null;data.installments.forEach(function(o){if(o.number===n)plan=o;});if(!plan)return;'
        .   'var form=block.closest("form");if(!form)return;'
        .   'var wrap=form.parentNode;'
        .   'var totSpan=wrap.querySelector(".pg-eo-total-formatted");'
        .   'var chSpan=wrap.querySelector(".pg-eo-charged-formatted");'
        .   'var feeRow=wrap.querySelector(".pg-eo-installment-fee-row");'
        .   'var feeVal=wrap.querySelector(".pg-eo-installment-fee-value");'
        .   'var feeCents=Math.round(parseFloat(plan.increase||"0")*100);'
        .   'var totalCents=Math.round(parseFloat(plan.total||"0")*100);'
        .   'var sym=data.currency_symbol||"";'
        .   'var mf=window.software_money_format||null;'
        .   'var rate=(mf&&mf.rate>0)?mf.rate:1;'
        .   'var vsym=mf?mf.symbol:sym,vsuf=mf?(mf.suffix||""):"";'
        .   'var money=function(a,s,x){return window.software_format_money?software_format_money(a,s,x):(s||"")+(+a).toFixed(2)+(x||"");};'
        .   'if(totSpan&&totalCents>0){'
        .     'totSpan.textContent=money(totalCents/100*rate,vsym,vsuf);'
        .     'totSpan.setAttribute("data-pg-eo-total-with-surcharge-cents",totalCents);'
        .   '}'
        .   'if(chSpan&&totalCents>0){chSpan.textContent=money(totalCents/100,sym,data.currency_code?" "+data.currency_code:"");}'
        .   'if(feeRow){'
        .     'if(feeCents>0){feeRow.style.display="";if(feeVal)feeVal.textContent=money(feeCents/100*rate,vsym,vsuf);}'
        .     'else{feeRow.style.display="none";}'
        .   '}'
        .   'var hTotal=form.querySelector(\'input[name="total"]\');'
        .   'var hTws=form.querySelector(\'input[name="total_with_surcharge"]\');'
        .   'if(hTws&&totalCents>0)hTws.value=(totalCents/100).toFixed(2);'
        .   'if(hTotal&&totalCents>0)hTotal.value=(totalCents/100).toFixed(2);'
        . '}';
}

// The note under the totals when the visitor reads the page in another
// currency than the one charged; '' otherwise.
function _eo_currency_disclaimer()
{
    if (!defined('VISITOR_CURRENCY_CODE') || !defined('BASE_CURRENCY_CODE') || VISITOR_CURRENCY_CODE == BASE_CURRENCY_CODE) {
        return '';
    }
    $name = defined('BASE_CURRENCY_ID') ? (string)db_value("SELECT name FROM currencies WHERE id = '" . (int)BASE_CURRENCY_ID . "'") : '';
    if ($name === '') $name = (string)BASE_CURRENCY_CODE;
    return h(lang(array(
        'string' => '*This amount is based on our current currency exchange rate to {var:1} and may differ from the exact charges (displayed above in {var:1}).',
        'vars'   => $name,
    )));
}

function _eo_compute_item_tokens($item, $widget_id, $form_id, $fmt, $lf, $gc_data, $base, $sw_dir, $csrf_token, $back_url)
{
    $iid     = (int)$item['item_id'];
    $qty     = (int)$item['quantity'];
    $price   = (int)$item['price'];      // cents
    $line    = $price * $qty;
    $title   = (string)(!empty($item['short_description']) ? $item['short_description'] : $item['product_name']);
    $img_url = '';
    if (!empty($item['image_name'])) {
        $img_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)$item['image_name']);
    }
    $remove_url = $base . $sw_dir . '/remove_item_from_cart.php'
                . '?order_item_id=' . $iid
                . '&screen=express_order'
                . ($back_url !== '' ? '&send_to=' . rawurlencode($back_url) : '')
                . ($csrf_token !== '' ? '&token='   . rawurlencode($csrf_token)  : '');

    // Per-item custom form (gift card recipient block OR per-product form_fields)
    // — same THREE cases the legacy _eo_render_cart_summary handles. Returned
    // as ADDITIONAL <tr> rows the loop concatenates after the main row.
    $extra_html = '';
    $is_gc    = !empty($item['gift_card']);
    $has_form = !empty($item['form']);
    $form_qt  = isset($item['form_quantity_type']) ? (string)$item['form_quantity_type'] : 'One Form per Product';
    if ($has_form && function_exists('_pg_render_cart_item_form_data') && $qty > 0) {
        // form_name becomes the fieldset legend inside the renderer — no
        // separate heading row, no icon (see the cart widget's twin call).
        $form_html = _pg_render_cart_item_form_data(
            $iid, (int)$item['product_id'], $qty,
            (string)(!empty($item['short_description']) ? $item['short_description'] : $item['product_name']),
            $form_qt, $form_id,
            !empty($item['form_name']) ? (string)lang(trim((string)$item['form_name'])) : ''
        );
        if ($form_html !== '') {
            $extra_html .= '<tr><td colspan="5" class="bg-body-tertiary border-top-0">'
                         . $form_html . '</td></tr>';
        }
    }
    if ($is_gc && !$has_form && $qty > 0) {
        // Replicate the hardcoded recipient block from _eo_render_cart_summary.
        $gc_count = min($qty, 100);
        $rows_html = '';
        for ($qn = 1; $qn <= $gc_count; $qn++) {
            $fp = 'order_item_' . $iid . '_quantity_number_' . $qn . '_gift_card_';
            $get = function ($col) use ($fp, $lf, $gc_data, $iid, $qn) {
                $key = $fp . $col;
                if ($lf) {
                    $v = $lf->get_field_value($key);
                    if ($v !== '' && $v !== null) return (string)$v;
                }
                if (isset($gc_data[$iid][$qn][_eo_gc_col_map($col)])) return (string)$gc_data[$iid][$qn][_eo_gc_col_map($col)];
                return '';
            };
            // Same rule as the cart widget: Bootstrap CONTROL classes, no
            // LAYOUT opinion. Every field is its own full-width row. The old
            // col-md-6 / col-md-8 / col-md-4 pairing only looked right because
            // it was hand-tuned for these four known fields — the identical
            // block shape is reused for operator-defined product forms where
            // the next field could be a select, a rich-text editor or a file
            // picker, and any fixed pairing falls apart there.
            // Legend is just "Gift card" (+ "(n / m)" when the quantity
            // repeats) — the same wording the legacy cart's <legend> uses.
            $rows_html .=
                '<fieldset class="pg-eo-gc-set border rounded p-3 mb-3">'
              . '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h(lang('Gift card'))
              .   ($gc_count > 1 ? ' (' . $qn . ' / ' . $gc_count . ')' : '') . '</legend>'
              . '<div class="mb-3"><label class="form-label" for="' . h($fp) . 'recipient_email_address">' . h(lang('Recipient Email')) . ' <span class="text-danger">*</span></label>'
              .   '<input type="email" required class="form-control" id="' . h($fp) . 'recipient_email_address" name="' . h($fp) . 'recipient_email_address" value="' . h($get('recipient_email_address')) . '"></div>'
              . '<div class="mb-3"><label class="form-label" for="' . h($fp) . 'from_name">' . h(lang('From Name')) . '</label>'
              .   '<input type="text" class="form-control" id="' . h($fp) . 'from_name" name="' . h($fp) . 'from_name" value="' . h($get('from_name')) . '" maxlength="100"></div>'
              . '<div class="mb-3"><label class="form-label" for="' . h($fp) . 'message">' . h(lang('Message')) . '</label>'
              .   '<textarea class="form-control" rows="3" id="' . h($fp) . 'message" name="' . h($fp) . 'message" maxlength="500">' . h($get('message')) . '</textarea></div>'
              . '<div class="mb-3"><label class="form-label" for="' . h($fp) . 'delivery_date">' . h(lang('Delivery Date')) . '</label>'
              .   '<input type="date" class="form-control" id="' . h($fp) . 'delivery_date" name="' . h($fp) . 'delivery_date" value="' . h($get('delivery_date')) . '">'
              .   '<div class="form-text">' . h(lang('Leave blank to send immediately.')) . '</div></div>'
              . '</fieldset>';
        }
        $extra_html .= '<tr><td colspan="5" class="bg-body-tertiary border-top-0"><div class="pg-eo-gift-cards" data-pg-eo-gc-item="' . $iid . '">' . $rows_html . '</div></td></tr>';
    }
    // Recurring schedule the customer may set: the same controls as the
    // cart row (field names are the legacy ones express_order.php checks —
    // the handler requires them, so without these a recurring donation
    // could never be ordered from this page).
    if ((int)($item['recurring'] ?? 0) === 1 && (int)($item['recurring_schedule_editable_by_customer'] ?? 0) === 1
        && function_exists('_pg_render_cart_item_recurring_schedule')) {
        $rec_row = $item;
        if ((string)($rec_row['recurring_payment_period'] ?? '') === '' && function_exists('_pg_cart_recurring_defaults')) {
            $def = _pg_cart_recurring_defaults($rec_row);
            $rec_row['recurring_payment_period']     = $def['period'];
            $rec_row['recurring_number_of_payments'] = $def['payments'];
            $rec_row['recurring_start_date']         = $def['start_date'];
        }
        $rec_html = _pg_render_cart_item_recurring_schedule($rec_row, $lf, ' form="' . h($form_id) . '"');
        if ($rec_html !== '') {
            $extra_html .= '<tr><td colspan="5" class="bg-body-tertiary border-top-0">' . $rec_html . '</td></tr>';
        }
    }
    if ($is_gc && $has_form && $qty > 0) {
        // Hidden recipient_email so legacy express_order.php:1074 validation passes.
        $billing_email = $lf ? (string)$lf->get_field_value('billing_email_address') : '';
        $gc_count = min($qty, 100);
        for ($qn = 1; $qn <= $gc_count; $qn++) {
            $hk = 'order_item_' . $iid . '_quantity_number_' . $qn . '_gift_card_recipient_email_address';
            $extra_html .= '<tr style="display:none"><td colspan="5"><input type="hidden" name="' . h($hk) . '" value="' . h($billing_email) . '"></td></tr>';
        }
    }

    // Detail URL — the product under the catalog detail page. Designer can
    // bind an `<a>` href to __item_url to make item names link out.
    $detail_url = '';
    if (!empty($item['address_name'])) {
        // Under the catalog detail page: a bare /<address_name> is a 404.
        $detail_page = function_exists('pg_sw_catalog_detail_page_name') ? pg_sw_catalog_detail_page_name(0) : '';
        $detail_url  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
                     . ($detail_page !== '' ? encode_url_path($detail_page) . '/' : '')
                     . encode_url_path((string)$item['address_name']);
    }

    // Short vs full description — separate so designer can pick which one
    // (or both) to show. Falls back to product_name when missing so a
    // designer-placed `^^__item_short_description^^` slot always renders
    // SOMETHING meaningful.
    $short_desc = (string)(!empty($item['short_description']) ? $item['short_description'] : $item['product_name']);
    $full_desc  = (string)(!empty($item['full_description'])  ? $item['full_description']  : '');

    return array(
        '^^__item_id^^'                   => (string)$iid,
        '^^__item_name^^'                 => h($title),
        '^^__item_short_description^^'    => h($short_desc),
        '^^__item_description^^'     => $full_desc,    // RAW HTML — full_description is rich text
        '^^__item_qty^^'                  => (string)$qty,
        '^^__item_price^^'      => $fmt($price),
        '^^__item_total^^' => $fmt($line),
        '^^__item_image^^'                => h($img_url),
        '^^__item_url^^'           => h($detail_url),
        '^^__item_remove_url^^'           => h($remove_url),
        '^^__item_form_html^^'            => $extra_html,
    );
}

function _eo_replace_tokens($html, $vars)
{
    if (!is_array($vars) || empty($vars)) return $html;
    return strtr($html, $vars);
}

// ── Designer bindings: <button> action wiring ───────────────────────────────
// Walk tree; for any semantic node with `props._bindings.action='eo_X'`, OVERRIDE
// its `name`/`value`/`type`/`formnovalidate` attrs so submit_order.php's POST
// dispatcher matches what the legacy submit handlers expect. Designer can
// re-style the button freely (text, class, icon, size) — the WIRING attrs
// are always re-emitted from the action binding.
function _eo_apply_action_bindings(&$tree)
{
    if (!is_array($tree)) return;
    $action = isset($tree['props']['_bindings']['action']) ? (string)$tree['props']['_bindings']['action'] : '';
    // remove_from_cart works on BOTH semantic + content[contentType=link]
    // (no other action does — special-cased here so the common path stays
    // semantic-only).
    if ($action === 'remove_from_cart' && isset($tree['type']) && $tree['type'] === 'content'
        && isset($tree['props']['contentType']) && $tree['props']['contentType'] === 'link') {
        $tree['props']['href'] = '^^__item_remove_url^^';
    }
    if ($action !== '' && isset($tree['type']) && $tree['type'] === 'semantic') {
        // Override / inject these attrs unconditionally on action-bound nodes.
        $force_attrs = array();
        if ($action === 'eo_submit_purchase') {
            $force_attrs = array(
                'type'  => 'submit',
                'name'  => 'submit_purchase_now',
                'value' => '1',
            );
        } elseif ($action === 'eo_submit_update') {
            $force_attrs = array(
                'type'           => 'submit',
                'name'           => 'submit_update',
                'value'          => '1',
                'formnovalidate' => '',
            );
        } elseif ($action === 'eo_qty_inc' || $action === 'eo_qty_dec') {
            // Qty stepper — same convention as cart_qty_inc/dec. Force
            // type="button" (no form submit) and stamp data-pg-qty-action so
            // the global qty-stepper JS handler wires the click → input bump.
            // Works on semantic <button> / <a> / <span> / <i>. Designer can
            // optionally add data-pg-qty-target="<input-id>" via the Attrs
            // panel to target a specific input far from the button.
            $tag_lc = isset($tree['props']['tag']) ? strtolower((string)$tree['props']['tag']) : '';
            if (in_array($tag_lc, array('button', 'a', 'span', 'i', 'div'), true)) {
                $force_attrs = array(
                    'data-pg-qty-action' => ($action === 'eo_qty_inc' ? 'inc' : 'dec'),
                );
                // Only force type=button for actual <button> elements.
                if ($tag_lc === 'button') {
                    $force_attrs['type'] = 'button';
                }
            }
        } elseif ($action === 'remove_from_cart') {
            // Per-item remove action. Wires:
            //   • <a>      → href="^^__item_remove_url^^" (per-row substitution)
            //   • <button> → formaction + a tiny inline form is too complex;
            //                emit data-pg-remove-href="^^__item_remove_url^^"
            //                so a small click-handler JS can window.location.href.
            // Result: designer picks the action from the menu, doesn\'t care
            // what HTML element type they\'re styling.
            $tag_lc = isset($tree['props']['tag']) ? strtolower((string)$tree['props']['tag']) : '';
            if ($tag_lc === 'a') {
                $force_attrs = array(
                    'href' => '^^__item_remove_url^^',
                );
            } elseif (in_array($tag_lc, array('button', 'span', 'i', 'div'), true)) {
                $force_attrs = array(
                    'data-pg-remove-href' => '^^__item_remove_url^^',
                    'role'                => 'button',
                    'style'               => 'cursor:pointer',
                );
                if ($tag_lc === 'button') {
                    $force_attrs['type'] = 'button';
                }
            }
        }
        if ($force_attrs) {
            if (!isset($tree['props']['_attrs']) || !is_array($tree['props']['_attrs'])) $tree['props']['_attrs'] = array();
            // Drop existing entries we're about to set so we don't double-emit.
            $tree['props']['_attrs'] = array_values(array_filter($tree['props']['_attrs'], function ($a) use ($force_attrs) {
                return !(is_array($a) && isset($a['name']) && isset($force_attrs[$a['name']]));
            }));
            foreach ($force_attrs as $k => $v) {
                $tree['props']['_attrs'][] = array('name' => $k, 'value' => $v);
            }
        }
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $_child) {
            _eo_apply_action_bindings($tree['children'][$i]);
        }
    }
}

// NOTE: The previous `_eo_extract_terms_modal_body()` walker was removed.
// Earlier design used a system-binding `section='eo_terms_modal_body'` to
// extract designer-authored content out of the form and inject it into a
// server-rendered modal sitting next to </form>. That added a magic
// "section" the designer couldn't edit like a normal modal — the trigger,
// title, footer buttons, dialog size were all hardcoded in PHP. The new
// approach uses a REAL Bootstrap modal element in the tree (designer edits
// it like any other component) and a tiny JS shim moves any .modal node
// inside [data-pg-eo-form] out to <body> at DOM ready, so Bootstrap's
// backdrop manager works as expected. See _pg_eo_modal_relocate_inline_js
// for the relocation script and the default tree for the modal markup.

// ── Designer bindings: `_bindings.eo_visible_if='X'` on any container
// ─────────────────────────────────────────────────────────────────────────
// Walk tree; for each node with this binding, evaluate the condition X
// against the runtime $context (cart state, payment settings, etc.) and
// REMOVE the node from the tree if the condition is false. Used to
// conditionally hide entire cards — e.g. the Teslimat card disappears
// when the cart has no shippable items.
//
// Supported conditions:
//   has_shipping  → cart contains at least one shippable order item
//   has_offers    → at least one offer is applied
//   has_upsell    → at least one upsell offer is available
//   has_terms     → terms modal is configured to render
function _eo_apply_visibility_bindings(&$tree, $context)
{
    if (!is_array($tree) || empty($tree['children'])) return;
    $kept = array();
    foreach ($tree['children'] as $child) {
        if (is_array($child) && !empty($child['props']['_bindings']['eo_visible_if'])) {
            $cond = (string)$child['props']['_bindings']['eo_visible_if'];
            if (empty($context[$cond])) {
                // Condition false → drop this node entirely.
                continue;
            }
        }
        if (is_array($child)) _eo_apply_visibility_bindings($child, $context);
        $kept[] = $child;
    }
    $tree['children'] = $kept;
}

// ── Designer bindings: `_bindings.eo_field='X'` on input/select/textarea
// ─────────────────────────────────────────────────────────────────────────
// THE primary binding for express order form fields. Designer drops any
// element type (input, select, textarea) and sets `_bindings.eo_field` to
// the EO field name (billing_first_name, card_number, special_offer_code,
// tax_exempt, …). Server walks the tree, finds bound elements, and:
//   1. Injects/overrides `name=<field>` and `id=<field>` attrs
//   2. Pre-fills `value=""` from liveform session
//   3. For checkbox/radio: sets `checked` if session value matches
//   4. For textarea: writes session value as inner text
//
// This means designer can SWAP an `<input type=text>` for a `<select>` —
// the binding stays, server handles it. Designer doesn\'t have to remember
// raw `name` attribute values; they pick from a dropdown of EO fields.
// $ship_rid lets the walker rewrite `shipping_<field>` bindings to
// `shipping_<rid>_<field>` — the legacy submit handler reads ship_to
// fields with the row id embedded in the name. Pass 0 when there\'s no
// active ship_to row (e.g. cart has no shippable items — the visibility
// binding would have dropped the whole card anyway).
function _eo_apply_field_bindings(&$tree, $lf, $ship_rid = 0)
{
    if (!is_array($tree)) return;
    $field = isset($tree['props']['_bindings']['eo_field'])
        ? (string)$tree['props']['_bindings']['eo_field'] : '';

    // Shipping field rewrite: `shipping_first_name` → `shipping_<rid>_first_name`.
    // Tree carries the rid-less name (stable for designer), walker resolves
    // at render time. When ship_rid=0 we keep the unprefixed name — visibility
    // binding will have removed the field\'s container before render anyway.
    if ($field !== '' && $ship_rid > 0 && strpos($field, 'shipping_') === 0
        && strpos($field, 'shipping_' . $ship_rid . '_') !== 0) {
        $suffix = substr($field, strlen('shipping_'));
        $field = 'shipping_' . $ship_rid . '_' . $suffix;
    }

    if ($field !== '' && isset($tree['type']) && $tree['type'] === 'semantic'
        && isset($tree['props']['tag'])
        && in_array(strtolower((string)$tree['props']['tag']), array('input','select','textarea','button'), true)) {

        $tag_lc = strtolower((string)$tree['props']['tag']);
        if (!isset($tree['props']['_attrs']) || !is_array($tree['props']['_attrs'])) $tree['props']['_attrs'] = array();

        // Drop pre-existing name/id from _attrs so we always control them
        // from the binding. NB: `id` is special — _render_extra_attrs()
        // emits id from props.id (top-level), NOT from props._attrs. Setting
        // it inside _attrs gets silently dropped. So we set props.id
        // directly while name still goes via _attrs (the standard path).
        $tree['props']['_attrs'] = array_values(array_filter($tree['props']['_attrs'], function ($a) {
            return !(is_array($a) && isset($a['name']) && ($a['name'] === 'name' || $a['name'] === 'id'));
        }));
        $tree['props']['_attrs'][] = array('name' => 'name', 'value' => $field);
        $tree['props']['id']       = $field;

        // Pre-fill value from liveform session. Skip for type=submit/button
        // (those are action triggers, not data fields).
        if ($lf && $tag_lc !== 'button') {
            $input_type = '';
            foreach ($tree['props']['_attrs'] as $a) {
                if (is_array($a) && isset($a['name']) && $a['name'] === 'type' && isset($a['value'])) {
                    $input_type = strtolower((string)$a['value']); break;
                }
            }
            $session_val = (string)$lf->get_field_value($field);

            if ($tag_lc === 'textarea') {
                $tree['props']['text'] = $session_val;
            } elseif ($tag_lc === 'select' && ($field === 'billing_country'
                                              || preg_match('/^shipping_\d+_country$/', $field))) {
                // <select> bound to billing_country gets its <option> list
                // INJECTED by the server — 240+ countries are too many to put
                // in the tree at design time. Designer sees an empty select
                // in canvas; server replaces children with full option list
                // on render, marking the visitor\'s saved country as selected.
                if (!function_exists('db_value')) {
                    // Defensive — db helpers should always exist in widget render context.
                    return;
                }
                $current = $session_val;
                if ($current === '') {
                    $current = (string)db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1");
                }
                $rows = db_items("SELECT code, name FROM countries ORDER BY name ASC");
                if (!is_array($rows)) $rows = array();
                // Build child nodes: first an empty "Select…" option, then one per country.
                $opts = array(array(
                    'type'  => 'semantic',
                    'props' => array('tag' => 'option', 'text' => lang('Select...'),
                                     '_attrs' => array(array('name' => 'value', 'value' => ''))),
                    'children' => array(),
                ));
                foreach ($rows as $r) {
                    $attrs = array(array('name' => 'value', 'value' => (string)$r['code']));
                    if ((string)$r['code'] === $current) $attrs[] = array('name' => 'selected', 'value' => '');
                    $opts[] = array(
                        'type'  => 'semantic',
                        'props' => array('tag' => 'option', 'text' => (string)$r['name'], '_attrs' => $attrs),
                        'children' => array(),
                    );
                }
                $tree['children'] = $opts;
                // Mark required attr so HTML5 validation blocks empty submit.
                $has_req = false;
                foreach ($tree['props']['_attrs'] as $a) {
                    if (is_array($a) && isset($a['name']) && $a['name'] === 'required') { $has_req = true; break; }
                }
                if (!$has_req) $tree['props']['_attrs'][] = array('name' => 'required', 'value' => '');
            } elseif ($tag_lc === 'input' && ($input_type === 'checkbox' || $input_type === 'radio')) {
                $val_attr = '1';
                foreach ($tree['props']['_attrs'] as $a) {
                    if (is_array($a) && isset($a['name']) && $a['name'] === 'value' && isset($a['value'])) {
                        $val_attr = (string)$a['value']; break;
                    }
                }
                $tree['props']['_attrs'] = array_values(array_filter($tree['props']['_attrs'], function ($a) {
                    return !(is_array($a) && isset($a['name']) && $a['name'] === 'checked');
                }));
                if ($session_val !== '' && $session_val === $val_attr) {
                    $tree['props']['_attrs'][] = array('name' => 'checked', 'value' => '');
                }
            } else {
                // text/email/tel/number/etc. + select
                $found_val = false;
                foreach ($tree['props']['_attrs'] as $i => $a) {
                    if (is_array($a) && isset($a['name']) && $a['name'] === 'value') {
                        $tree['props']['_attrs'][$i]['value'] = $session_val;
                        $found_val = true; break;
                    }
                }
                if (!$found_val) {
                    $tree['props']['_attrs'][] = array('name' => 'value', 'value' => $session_val);
                }
            }
        }

        // Add data-pg-bind for designer canvas visibility (purely visual hint).
        $tree['props']['_attrs'] = array_values(array_filter($tree['props']['_attrs'], function ($a) {
            return !(is_array($a) && isset($a['name']) && $a['name'] === 'data-pg-bind');
        }));
        $tree['props']['_attrs'][] = array('name' => 'data-pg-bind', 'value' => 'eo:' . $field);
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $_child) {
            _eo_apply_field_bindings($tree['children'][$i], $lf, $ship_rid);
        }
    }
}

// ── Designer bindings: pre-fill `value=""` on real <input>/<select>/<textarea>
// Walks the tree. For every semantic node whose tag is one of the form
// inputs AND whose `name` attribute matches a known express-order field,
// override the `value` attribute with the current session/liveform value.
// This is what makes the designer's real form elements actually behave like
// a working form: prior submissions round-trip, defaults appear, the
// visitor's typed input isn't wiped on validation failure.
//
// Whitelist of known names — same set used by submit_order.php / express_order.php.
// Anything outside this list is left untouched so designers can add their
// own custom fields freely.
function _eo_known_input_names()
{
    static $names = null;
    if ($names !== null) return $names;
    $names = array_flip(array(
        'billing_salutation','billing_first_name','billing_last_name','billing_company',
        'billing_address_1','billing_address_2','billing_city','billing_state',
        'billing_zip_code','billing_phone_number','billing_email_address',
        'billing_fax_number','custom_field_1','custom_field_2','po_number',
        'special_offer_code','gift_card_code','referral_source','identitynumber',
        'notes','tax_exempt','opt_in','agree_terms','update_contact',
        'card_number','expiration','card_verification_number',
        // payment_method/installment are NOT pre-filled here — they're set by
        // their own dedicated section renderers (radio/installment selector).
    ));
    return $names;
}

// Match shipping_<rid>_<field> dynamically — the rid is unknown until
// runtime (ship_to row id), so we can\'t hard-code the full name in the
// static list. Returns true for any name matching that pattern.
function _eo_is_known_input_name($name)
{
    $names = _eo_known_input_names();
    if (isset($names[$name])) return true;
    // Shipping fields after rid rewrite: shipping_<int>_<field>
    if (preg_match('/^shipping_\d+_([a-z_0-9]+)$/', $name, $m)) {
        $valid_suffixes = array(
            'salutation','first_name','last_name','company',
            'address_1','address_2','city','state','zip_code','country',
            'phone_number','email_address','address_type','arrival_date','shipping_method',
        );
        return in_array($m[1], $valid_suffixes, true);
    }
    return false;
}

function _eo_prefill_input_values_in_tree(&$tree, $lf)
{
    if (!is_array($tree)) return;
    if (isset($tree['type']) && $tree['type'] === 'semantic'
        && isset($tree['props']['tag'])
        && in_array(strtolower((string)$tree['props']['tag']), array('input','select','textarea'), true)
        && !empty($tree['props']['_attrs']) && is_array($tree['props']['_attrs'])) {

        // Pull `name` AND `type` attributes from the _attrs list.
        $name = '';
        $input_type = '';
        foreach ($tree['props']['_attrs'] as $a) {
            if (!is_array($a) || !isset($a['name'])) continue;
            if ($a['name'] === 'name' && isset($a['value'])) $name = (string)$a['value'];
            if ($a['name'] === 'type' && isset($a['value'])) $input_type = strtolower((string)$a['value']);
        }
        if ($name !== '' && _eo_is_known_input_name($name)) {
            $session_val = (string)$lf->get_field_value($name);
            $tag_lc = strtolower((string)$tree['props']['tag']);

            if ($tag_lc === 'textarea') {
                // <textarea>'s value lives as inner TEXT, not a value="" attr.
                // The `semantic` renderer reads props.text for this.
                $tree['props']['text'] = $session_val;
            } elseif ($tag_lc === 'input' && ($input_type === 'checkbox' || $input_type === 'radio')) {
                // Checkbox / radio: session "1" (or matching value) → checked.
                // Find the input's `value` attr to compare against.
                $input_value = '1';
                foreach ($tree['props']['_attrs'] as $a) {
                    if (is_array($a) && isset($a['name']) && $a['name'] === 'value' && isset($a['value'])) {
                        $input_value = (string)$a['value']; break;
                    }
                }
                // Drop any pre-existing `checked` attr, re-add only if session matches.
                $tree['props']['_attrs'] = array_values(array_filter($tree['props']['_attrs'], function ($a) {
                    return !(is_array($a) && isset($a['name']) && $a['name'] === 'checked');
                }));
                if ($session_val !== '' && $session_val === $input_value) {
                    $tree['props']['_attrs'][] = array('name' => 'checked', 'value' => '');
                }
            } else {
                // <input> (text/email/tel/etc.) or <select> — override `value` attr.
                $found = false;
                foreach ($tree['props']['_attrs'] as $i => $a) {
                    if (is_array($a) && isset($a['name']) && $a['name'] === 'value') {
                        $tree['props']['_attrs'][$i]['value'] = $session_val;
                        $found = true; break;
                    }
                }
                if (!$found) {
                    $tree['props']['_attrs'][] = array('name' => 'value', 'value' => $session_val);
                }
            }
        }
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $_child) {
            _eo_prefill_input_values_in_tree($tree['children'][$i], $lf);
        }
    }
}

// ── Designer bindings: default tree for "Varsayılan Düzeni Yükle" button ────
// Mirrors the monolithic layout so designers who load defaults see exactly
// what they saw before — they can then reorder, restyle, wrap. The tree
// uses `block` + `content` types only (no proprietary widgets) so it's
// readable in the designer's tree view and editable with the standard prop
// panel. Each section is a `<section class="pg-eo-section ...">` wrapper
// with a heading + a child `<div>` carrying `_bindings.eo_section='X'`.
// Designers can delete the heading, change the wrapper, etc.
// Internal helper used by both `_eo_default_designer_tree()` and other
// designer-related code paths to build semantic / input / label / field
// nodes without retyping the full array shape every time. Kept at file
// scope so multiple builders can reuse.
function _eo_sem($tag, $cssClass = '', $childrenOrText = null, $extra = array())
{
    $props = array('tag' => $tag);
    if ($cssClass !== '') $props['cssClass'] = $cssClass;
    $attrs = isset($extra['attrs']) ? (array)$extra['attrs'] : array();
    if (!empty($extra['style'])) $attrs[] = array('name' => 'style', 'value' => $extra['style']);
    if (!empty($extra['href']))  $attrs[] = array('name' => 'href',  'value' => $extra['href']);
    if (!empty($attrs)) $props['_attrs'] = $attrs;
    if (!empty($extra['bindings'])) $props['_bindings'] = $extra['bindings'];
    $children = array();
    if (is_string($childrenOrText)) {
        $props['text'] = $childrenOrText;
    } elseif (is_array($childrenOrText)) {
        $children = $childrenOrText;
    }
    return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

function _eo_default_designer_tree()
{
    // ── Local builder helpers ───────────────────────────────────────────────
    $sem = function () { return call_user_func_array('_eo_sem', func_get_args()); };
    // Section anchor — empty div carrying a backend section binding. Replaces
    // its children with the live HTML from PHP at render time.
    $bind = function ($section, $cssClass = '') use ($sem) {
        return $sem('div', $cssClass, null, array('bindings' => array('section' => 'eo_' . $section)));
    };
    // Real form input — bound via `_bindings.eo_field` so designer can swap
    // <input> for <select> while the binding survives (server reads the
    // binding and injects name/id/value attrs). Designer also sees the
    // binding label in the property panel\'s "Bind Data" dropdown.
    // $required:    real HTML5 `required` attribute (browser enforces on submit).
    // $cc_required: emits `data-pg-cc-required` instead — the widget JS
    //               (pg-eo-cc-fields toggler) flips this to real `required`
    //               only when the CC fields are visible. Prevents "An invalid
    //               form control with name=X is not focusable" silent-block.
    $input = function ($eo_field, $type, $placeholder = '', $cssClass = 'form-control', $required = false, $cc_required = false) use ($sem) {
        $attrs = array(
            array('name' => 'type',  'value' => $type),
            array('name' => 'value', 'value' => ''),
        );
        if ($placeholder !== '') $attrs[] = array('name' => 'placeholder',        'value' => $placeholder);
        if ($required)           $attrs[] = array('name' => 'required',           'value' => '');
        if ($cc_required)        $attrs[] = array('name' => 'data-pg-cc-required','value' => '1');
        return $sem('input', $cssClass, null, array('attrs' => $attrs, 'bindings' => array('eo_field' => $eo_field)));
    };
    $label = function ($for, $text, $required = false) use ($sem) {
        return $sem('label', 'form-label small fw-semibold', $required ? $text . ' *' : $text,
            array('attrs' => array(array('name' => 'for', 'value' => $for))));
    };
    $field = function ($colClass, $labelText, $eo_field, $type = 'text', $placeholder = '', $required = false, $cc_required = false) use ($sem, $input, $label) {
        // Label still gets the * for CC fields (visual signal to user) even
        // though the underlying `required` is delayed until CC is selected.
        $show_star = $required || $cc_required;
        return $sem('div', $colClass, array($label($eo_field, $labelText, $show_star), $input($eo_field, $type, $placeholder, 'form-control', $required, $cc_required)));
    };

    // ── Totals row helper (one <tr> with label + token-bound value cell).
    // The value cell uses _bindings.text='X' so _apply_bindings overrides
    // ── Granular totals row ──────────────────────────────────────────────
    // Each row is a separate <tr> with THREE bindable parts:
    //   1. CONTAINER <tr> — `_bindings.eo_visible_if='<flag>'`. When the
    //      backend flag is FALSE the whole row is removed from the tree
    //      before render. Example: Discount row hides on orders with no
    //      discount; Tax row hides when ECOMMERCE_TAX is off.
    //   2. LABEL <span> — plain designer-editable text. Default localised
    //      via lang(); designer overrides by editing inline. NOT data-bound
    //      (the label IS the designer\'s content — no backend value to
    //      surface). Wrapping in a span instead of putting text directly
    //      on the <th> means designer can style label + cell padding
    //      independently.
    //   3. VALUE <span> — `_bindings.text='<token>'`. Backend replaces
    //      with the live formatted value (e.g. "₺123,45"). Designer sees
    //      a placeholder preview ("₺0,00") on canvas.
    //
    // Designer can still DELETE entire rows from the tree. Visibility
    // bindings are an ADDITIONAL layer for backend-driven hiding without
    // losing the row from the tree.
    //
    // $visibleIf: empty = always visible. Otherwise must be one of:
    //   has_discount, has_tax, has_shipping_cost, has_gift_card,
    //   has_surcharge, has_installment_fee — see _eo_vis_ctx for the
    //   full registry.
    $tot_row = function ($label, $token, $visibleIf = '', $trCss = '', $tdCss = 'text-end pe-0', $valueSpanCss = '', $preview = null)
                use ($sem) {
        // Written the way the site writes money ("₺0,00" / "$0.00").
        if ($preview === null) $preview = pg_money_text(0);
        $tr_extra = array();
        if ($visibleIf !== '') $tr_extra['bindings'] = array('eo_visible_if' => $visibleIf);
        return $sem('tr', $trCss, array(
            $sem('th', 'ps-0 fw-normal text-muted', array(
                $sem('span', 'pg-eo-tot-label', $label),
            )),
            $sem('td', $tdCss, array(
                $sem('span', 'pg-eo-tot-value ' . $valueSpanCss, $preview,
                     array('bindings' => array('text' => $token))),
            )),
        ), $tr_extra);
    };

    // Installment fee row — hidden initially via inline style, JS toggles
    // display:none on the live element when the visitor picks a plan with
    // a non-zero fee. We do NOT use eo_visible_if here: that walker
    // REMOVES the node from the tree before render, so the JS would have
    // nothing to surface. Keeping the row always-present-but-hidden lets
    // the BIN-lookup → installment-fetcher script reveal it on demand.
    $installment_fee_row = $sem('tr', 'pg-eo-installment-fee-row', array(
        $sem('th', 'ps-0 fw-normal text-warning', array(
            $sem('span', 'pg-eo-tot-label', lang('Instalment Fee')),
        )),
        $sem('td', 'text-end pe-0 text-warning', array(
            $sem('span', 'pg-eo-tot-value pg-eo-installment-fee-value', pg_money_text(0)),
        )),
    ), array('style' => 'display:none'));

    // ── Card wrapper helper — Bootstrap card with header + body for each
    // semantic section. Designer gets consistent visual containers.
    $card = function ($title, $bodyChildren, $cardCss = 'mb-3') use ($sem) {
        return $sem('div', 'card ' . $cardCss, array(
            $sem('div', 'card-header', array(
                $sem('h2', 'h6 mb-0 fw-semibold', $title),
            )),
            $sem('div', 'card-body', $bodyChildren),
        ));
    };

    // ── Checkbox + label helper (Bootstrap form-check pattern) ──
    // Bound via _bindings.eo_field so the form-check stays wired after a
    // designer swap (e.g. checkbox → switch). Server reads the binding,
    // injects name/id/value attrs, sets `checked` if session matches.
    //
    // $visibleIf: optional eo_visible_if condition (evaluated by
    // _eo_apply_visibility_bindings). When the condition is false the
    // container is REMOVED from the tree before render — designer sees the
    // element on canvas, but it never reaches the visitor when the gate is
    // closed. Used for tax_exempt which only shows when the site has both
    // ECOMMERCE_TAX and ECOMMERCE_TAX_EXEMPT enabled.
    $check_row = function ($eo_field, $label_text, $extraCss = 'mb-2', $visibleIf = '') use ($sem) {
        $extra = array();
        if ($visibleIf !== '') $extra['bindings'] = array('eo_visible_if' => $visibleIf);
        return $sem('div', 'form-check ' . $extraCss, array(
            $sem('input', 'form-check-input', null, array(
                'attrs' => array(
                    array('name' => 'type',  'value' => 'checkbox'),
                    array('name' => 'value', 'value' => '1'),
                ),
                'bindings' => array('eo_field' => $eo_field),
            )),
            $sem('label', 'form-check-label small', $label_text,
                 array('attrs' => array(array('name' => 'for', 'value' => $eo_field)))),
        ), $extra);
    };

    // ── Textarea helper ──
    $textarea = function ($eo_field, $rows, $placeholder = '') use ($sem) {
        return $sem('textarea', 'form-control', null, array(
            'attrs' => array(
                array('name' => 'rows',        'value' => (string)$rows),
                array('name' => 'placeholder', 'value' => $placeholder),
            ),
            'bindings' => array('eo_field' => $eo_field),
        ));
    };

    // ── Root tree ───────────────────────────────────────────────────────────
    return array(
        'type'  => 'root',
        'props' => array(),
        'children' => array(

            // ----- NO errors / notices band here on purpose.
            // The widget already carries a `messages` content node ("PHP
            // Messages") fed by the SAME liveform, so having both printed
            // every error twice. The messages node is the general mechanism
            // (shared by every system widget, movable and stylable by the
            // designer), so it is the single source; the renderer blanks the
            // `errors_notices` section whenever a messages node exists.

            // ----- Upsell offers band — Bootstrap alerts from view_offers.php
            // (e.g. "Spend ₺100 more, get free shipping"). Empty when no
            // qualifying offers — designer sees an invisible marker but the
            // section is still in the tree for when an offer triggers.
            // (Also dropped col-12 prefix — same reasoning as above.)
            $bind('upsell_offers', 'mb-2 pg-eo-upsell-anchor'),

            // ----- Main 2-column grid (8/4 split at sm+).
            // Designer canvas iframe is often narrower than the 768px md
            // breakpoint after the property-panel/tree-panel take their
            // share — col-md was still dropping the sidebar below on real
            // workstations. sm (576px) keeps side-by-side everywhere the
            // canvas could reasonably be used.
            $sem('div', 'row g-3 pg-eo-grid', array(

                // ╭───────────── LEFT COLUMN ────────────────────────────╮
                $sem('div', 'col-12 col-sm-8', array(

                    // ┌── CARD: cart summary ("Sepetiniz") ──────────────┐
                    // Bootstrap card wrapper. Dark-theme friendly: bg-body
                    // (not bg-white) on header so it inherits theme bg.
                    // Body uses p-0 so the table flushes to the card edge.
                    // Saved cart link sits INSIDE the body, above the table.
                    $sem('div', 'card mb-3 pg-eo-cart', array(
                        $sem('div', 'card-header d-flex justify-content-between align-items-center', array(
                            $sem('h2', 'h6 mb-0 fw-semibold', lang('Your cart')),
                        )),
                        $sem('div', 'card-body p-0', array(
                            // Saved-cart link appears inside the cart body
                            // (when the order has a reference_code) — same
                            // alert pattern as the legacy /sepet layout.
                            $bind('saved_cart_link', 'p-3 pb-0 pg-eo-saved-cart-anchor'),
                            // Responsive stacking below md — Bootstrap-only
                            // equivalent of the legacy `table.mobile_stacked`
                            // media query (livesite.src.css:490). The four
                            // right-hand columns are pinned at 6+8+8+3 = 25rem,
                            // which squeezed the item column to ~188px under
                            // 900px and wrapped the product name to five lines.
                            // `table-responsive` can't help — the table never
                            // overflows, it just compresses.
                            // MUST stay in lockstep with _buildExpressOrderStarterTree()
                            // in style_designer.js (the "Load Default Layout" twin).
                            $sem('div', 'table-responsive', array(
                                $sem('table', 'table align-middle mb-0', array(
                                    $sem('thead', 'd-none d-md-table-header-group', array(
                                        $sem('tr', 'd-block d-md-table-row', array(
                                            $sem('th', 'text-muted small ps-3 d-block d-md-table-cell', lang('Item')),
                                            $sem('th', 'text-muted small text-end d-block d-md-table-cell', lang('Quantity'), array('style' => 'width:6rem')),
                                            $sem('th', 'text-muted small text-end d-block d-md-table-cell', lang('Unit Price'),  array('style' => 'width:8rem')),
                                            $sem('th', 'text-muted small text-end d-block d-md-table-cell', lang('Amount'),  array('style' => 'width:8rem')),
                                            $sem('th', 'd-block d-md-table-cell', '',         array('style' => 'width:3rem')),
                                        )),
                                    )),
                                    $sem('tbody', '', array(
                                        array(
                                            'type'  => 'loop_area',
                                            'props' => array(),
                                            'children' => array(
                                                // border-bottom separates stacked rows on
                                                // mobile, where cell borders stop reading
                                                // as a single row.
                                                $sem('tr', 'd-block d-md-table-row border-bottom', array(
                                                    // Item cell: image + short description + full description.
                                                    // EACH element is independent + data-binding driven —
                                                    // designer can delete any of them, replace with a different
                                                    // element type, and re-bind from the data binding menu.
                                                    // No magic CSS classes; layout uses ONLY Bootstrap utilities.
                                                    $sem('td', 'ps-3 d-block d-md-table-cell', array(
                                                        $sem('div', 'd-flex gap-3 align-items-start', array(
                                                            // Image — real <img> with src bound to __item_image.
                                                            array('type' => 'semantic', 'props' => array(
                                                                'tag' => 'img',
                                                                'cssClass' => 'rounded border',
                                                                '_attrs' => array(
                                                                    array('name' => 'alt',   'value' => ''),
                                                                    array('name' => 'style', 'value' => 'width:64px;height:64px;object-fit:cover;flex:0 0 auto'),
                                                                    array('name' => 'loading', 'value' => 'lazy'),
                                                                ),
                                                                '_bindings' => array('src' => '__item_image'),
                                                            ), 'children' => array()),
                                                            $sem('div', 'flex-grow-1 min-width-0', array(
                                                                // Short description — real <p> with text binding.
                                                                $sem('p', 'fw-semibold mb-1', lang('Sample Product Name'), array('bindings' => array('text' => '__item_short_description'))),
                                                                // Full description — real <p> with text binding.
                                                                $sem('p', 'small text-muted mb-0', lang('A sample product description — short and clear.'), array('bindings' => array('text' => '__item_description'))),
                                                            )),
                                                        )),
                                                    )),
                                                    // Qty cell — input-group + - / + stepper. JS handler wires
                                                    // the buttons to the input. Designer can swap to a plain
                                                    // input or remove the steppers; works either way.
                                                    $sem('td', 'text-start text-md-end align-middle d-block d-md-table-cell', array(
                                                        $sem('div', 'input-group input-group-sm pg-qty-stepper ms-0 ms-md-auto', array(
                                                            array('type' => 'semantic', 'props' => array(
                                                                'tag' => 'button',
                                                                'cssClass' => 'btn btn-outline-secondary',
                                                                'text' => '−',
                                                                '_attrs' => array(
                                                                    array('name' => 'type',                'value' => 'button'),
                                                                    array('name' => 'data-pg-qty-action', 'value' => 'dec'),
                                                                    array('name' => 'tabindex',           'value' => '-1'),
                                                                    array('name' => 'aria-label',         'value' => lang('Decrease')),
                                                                ),
                                                            ), 'children' => array()),
                                                            array('type' => 'semantic', 'props' => array(
                                                                'tag' => 'input',
                                                                'cssClass' => 'form-control form-control-sm text-center',
                                                                '_attrs' => array(
                                                                    array('name' => 'type',  'value' => 'number'),
                                                                    array('name' => 'min',   'value' => '0'),
                                                                    array('name' => 'step',  'value' => '1'),
                                                                    array('name' => 'name',  'value' => 'quantity[^^__item_id^^]'),
                                                                    array('name' => 'value', 'value' => '^^__item_qty^^'),
                                                                    array('name' => 'form',  'value' => '^^__form_id^^'),
                                                                ),
                                                            ), 'children' => array()),
                                                            array('type' => 'semantic', 'props' => array(
                                                                'tag' => 'button',
                                                                'cssClass' => 'btn btn-outline-secondary',
                                                                'text' => '+',
                                                                '_attrs' => array(
                                                                    array('name' => 'type',                'value' => 'button'),
                                                                    array('name' => 'data-pg-qty-action', 'value' => 'inc'),
                                                                    array('name' => 'tabindex',           'value' => '-1'),
                                                                    array('name' => 'aria-label',         'value' => lang('Increase')),
                                                                ),
                                                            ), 'children' => array()),
                                                        // max-width, not width: a hard 9rem made
                                                        // the qty column the floor the whole
                                                        // table sized itself against.
                                                        ), array('style' => 'max-width:9rem;display:inline-flex')),
                                                    )),
                                                    // Value goes on a <span> INSIDE the cell, never on the <td>
                                                    // itself. A <td> is layout: it owns alignment, width and the
                                                    // column it belongs to. Binding text to it means the designer
                                                    // can't wrap the value in a link, add a badge next to it, or
                                                    // restyle just the number without touching the cell — and if
                                                    // they ever drop another element into the cell, the binding
                                                    // silently overwrites it. Every other binding in this file
                                                    // targets a content element (span / heading / p / a / img);
                                                    // these two were the exception and the default tree is what
                                                    // every new design gets copied from.
                                                    $sem('td', 'text-start text-md-end align-middle d-block d-md-table-cell', array(
                                                        $sem('span', '', pg_money_text(39.95), array('bindings' => array('text' => '__item_price'))),
                                                    )),
                                                    $sem('td', 'text-start text-md-end fw-semibold align-middle d-block d-md-table-cell', array(
                                                        $sem('span', '', pg_money_text(79.9), array('bindings' => array('text' => '__item_total'))),
                                                    )),
                                                    $sem('td', 'text-start text-md-end align-middle pe-3 d-block d-md-table-cell', array(
                                                        // Remove link — action-bound. Designer can swap the
                                                        // <a> for a <button>, change icon, restyle freely;
                                                        // the action binding wires the right href/handler.
                                                        $sem('a', 'text-danger', array(
                                                            array('type' => 'content', 'props' => array('contentType' => 'icon', 'iconName' => 'bi-x-lg'), 'children' => array()),
                                                        ), array(
                                                            'attrs' => array(array('name' => 'title', 'value' => lang('Remove'))),
                                                            'bindings' => array('action' => 'remove_from_cart'),
                                                        )),
                                                    )),
                                                )),
                                                $sem('tr', 'd-none', array(
                                                    // Binding lives on a <div> inside the cell, not on the <td> —
                                                    // the cell keeps colspan and layout, the div carries the value.
                                                    $sem('td', '', array(
                                                        $sem('div', '', '', array('bindings' => array('text' => '__item_form_html'))),
                                                    ), array(
                                                        'attrs' => array(array('name' => 'colspan', 'value' => '5')),
                                                    )),
                                                )),
                                            ),
                                        ),
                                    )),
                                )),
                            )),
                        )),
                    )),

                    // ┌── CARD: billing details ("Fatura Bilgileri") ───┐
                    // Tax-exempt checkbox lives at the bottom of this card
                    // — wrapped in visibility binding so it disappears when
                    // the site hasn\'t enabled ECOMMERCE_TAX + ECOMMERCE_TAX_EXEMPT.
                    // The separate "Order Preferences" card was removed
                    // (opt_in moved to the totals card, tax_exempt moved here).
                    $card(lang('Billing Information'), array(
                        $sem('div', 'row', array(
                            $field('col-12 col-md-3 mb-3', lang('Salutation'), 'billing_salutation', 'text', lang('Mr/Ms')),
                            $sem('div', 'col-md-9'),
                            $field('col-12 col-md-6 mb-3', lang('First Name'), 'billing_first_name', 'text', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Last Name'),  'billing_last_name',  'text', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Company'),    'billing_company',    'text'),
                            $field('col-12 col-md-6 mb-3', lang('Email'),      'billing_email_address', 'email', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Address 1'),  'billing_address_1',  'text', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Address 2'),  'billing_address_2',  'text'),
                            $field('col-12 col-md-6 mb-3', lang('City'),       'billing_city',       'text', '', true),
                            $sem('div', 'col-12 col-md-6 mb-3', array(
                                $label('billing_country', lang('Country'), true),
                                // Real <select> with eo_field binding — server
                                // injects the <option> list (240+ countries) at
                                // render time. Designer can swap to a different
                                // select element or rebind to billing_state etc.
                                $sem('select', 'form-select', null, array(
                                    'bindings' => array('eo_field' => 'billing_country'),
                                )),
                            )),
                            $field('col-12 col-md-6 mb-3', lang('State / Province'), 'billing_state',        'text', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Zip Code'),         'billing_zip_code',     'text', '', true),
                            $field('col-12 col-md-6 mb-3', lang('Phone'),            'billing_phone_number', 'tel', '', true),
                        )),
                        // Tax-exempt checkbox — wrapped in visibility binding;
                        // only renders when both ECOMMERCE_TAX and
                        // ECOMMERCE_TAX_EXEMPT site settings are enabled.
                        // Sits right after the address fields so corporate
                        // buyers can flag the order at the same step.
                        $sem('div', 'pt-2 border-top mt-2', array(
                            $check_row('tax_exempt', lang('I am tax exempt (corporate purchases)'), 'mb-0'),
                        ), array('bindings' => array('eo_visible_if' => 'tax_exempt_allowed'))),
                    )),

                    // ┌── CARD: offer code ("Special Offer Code") ──────┐
                    $card(lang('Special Offer Code'), array(
                        $sem('div', 'input-group mb-1', array(
                            $input('special_offer_code', 'text', lang('Enter your code if you have one'), false),
                            $sem('button', 'btn btn-outline-primary', lang('Apply'),
                                 array('bindings' => array('action' => 'eo_submit_update'))),
                        )),
                        $sem('div', 'form-text small text-muted',
                             lang('Enter your discount code and click "Apply" — the totals are recalculated.')),
                    )),

                    // ┌── CARD: shipping address — visibility-bound at card level (drops
                    // when no shippable items) AND nested row visibility for
                    // single-vs-multi recipient. Single-recipient: tree\'s real
                    // form fields render. Multi-recipient: row dropped, server
                    // section binding renders the full per-recipient form.
                    $sem('div', 'card mb-3 pg-eo-shipping', array(
                        $sem('div', 'card-header', array(
                            $sem('h2', 'h6 mb-0 fw-semibold', lang('Shipping Address')),
                        )),
                        $sem('div', 'card-body', array(
                            // Address fields row — VISIBLE only for single-
                            // recipient orders. For multi-recipient, this row
                            // is dropped from the tree before render (the
                            // server-rendered shipping section below supplies
                            // a full address form PER recipient).
                            $sem('div', 'row', array(
                                $field('col-12 col-md-3 mb-3', lang('Salutation'),  'shipping_salutation',  'text', lang('Mr/Ms')),
                                $sem('div', 'col-md-9'),
                                $field('col-12 col-md-6 mb-3', lang('First Name'),  'shipping_first_name',  'text', '', true),
                                $field('col-12 col-md-6 mb-3', lang('Last Name'),   'shipping_last_name',   'text', '', true),
                                $field('col-12 col-md-6 mb-3', lang('Company'),     'shipping_company',     'text'),
                                $field('col-12 col-md-6 mb-3', lang('Phone'),       'shipping_phone_number','tel'),
                                $field('col-12 col-md-6 mb-3', lang('Address 1'),   'shipping_address_1',   'text', '', true),
                                $field('col-12 col-md-6 mb-3', lang('Address 2'),   'shipping_address_2',   'text'),
                                $field('col-12 col-md-6 mb-3', lang('City'),        'shipping_city',        'text', '', true),
                                $sem('div', 'col-12 col-md-6 mb-3', array(
                                    $label('shipping_country', lang('Country'), true),
                                    $sem('select', 'form-select', null, array(
                                        'bindings' => array('eo_field' => 'shipping_country'),
                                    )),
                                )),
                                $field('col-12 col-md-6 mb-3', lang('State / Province'), 'shipping_state',     'text', '', true),
                                $field('col-12 col-md-6 mb-3', lang('Zip Code'),         'shipping_zip_code',  'text', '', true),
                            ), array('bindings' => array('eo_visible_if' => 'has_single_recipient'))),
                            // Arrival date + shipping method picker section.
                            // Single-recipient: shows ONLY extras (auto-skip
                            // because tree has real address fields).
                            // Multi-recipient: shows FULL per-recipient form
                            // (auto-detection bails out → server includes
                            // address fields per recipient).
                            $bind('shipping', 'pg-eo-shipping-extras mt-3'),
                        )),
                    ), array('bindings' => array('eo_visible_if' => 'has_shipping'))),

                    // ┌── CARD: payment method + card + installments + 2nd submit ┐
                    // Card footer hosts a secondary "Complete Order" button
                    // so visitor doesn\'t have to scroll up to the sidebar.
                    //
                    // CC fields use `data-pg-cc-required` instead of `required`
                    // because they sit inside .pg-eo-cc-fields which is HIDDEN
                    // when the visitor picks Offline / PayPal / EFT. With raw
                    // `required` the browser tries to focus the (hidden) field
                    // on submit and throws "An invalid form control with
                    // name=card_number is not focusable" — and the form silently
                    // refuses to submit. The widget JS toggles `required` ON/OFF
                    // when the payment method radio changes (only ON when the
                    // CC fields are actually visible). See _eo_render_widget_js.
                    $sem('div', 'card mb-3 pg-eo-payment', array(
                        $sem('div', 'card-header', array(
                            $sem('h2', 'h6 mb-0 fw-semibold', lang('Payment Method')),
                        )),
                        $sem('div', 'card-body', array(
                            $bind('payment_methods', 'mb-3'),
                            $sem('div', 'pg-eo-cc-fields', array(
                                $sem('p', 'small text-muted mb-2', lang('The card fields appear when Credit/Debit Card is selected.')),
                                $sem('div', 'row', array(
                                    $field('col-12 mb-3', lang('Card Number'), 'card_number', 'text', '•••• •••• •••• ••••', false, true),
                                    $field('col-6 mb-3',  lang('Expiry date'),  'expiration',  'text', lang('MM / YY'), false, true),
                                    $field('col-6 mb-3',  lang('Security Code (CVC)'), 'card_verification_number', 'text', 'CVC', false, true),
                                )),
                                $bind('installment', 'mt-2'),
                            ), array('attrs' => array(array('name' => 'data-pg-eo-cc-fields', 'value' => '1')))),
                        )),
                        // Secondary submit at the bottom of payment so visitor
                        // can submit without scrolling back up. Same eo_action
                        // binding → same submit_purchase_now POST.
                        $sem('div', 'card-footer', array(
                            $sem('div', 'd-grid', array(
                                $sem('button', 'btn btn-primary btn-lg', lang('Complete Order'),
                                     array('bindings' => array('action' => 'eo_submit_purchase'))),
                            )),
                        )),
                    )),
                )),
                // ╰─────────────────────────────────────────────────────╯

                // ╭──────────── RIGHT COLUMN (sticky summary) ──────────╮
                // Totals are a REAL <table> with one row per line. Designer
                // can delete rows for line items they don\'t want shown, or
                // re-style them. The value cells use _bindings.text='X'
                // which _apply_bindings rewrites to ^^X^^ at render time;
                // we then str_replace with the live formatted value.
                //
                // sticky-sm-top (Bootstrap 5) pins the panel to the viewport
                // top from sm (576px) up — matches the column split breakpoint.
                $sem('div', 'col-12 col-sm-4', array(
                    $sem('div', 'sticky-sm-top', array(
                        $sem('div', 'card pg-eo-totals mb-3', array(
                            $sem('div', 'card-header', array(
                                $sem('h2', 'h6 mb-0 fw-semibold', lang('Order summary')),
                            )),
                            $sem('div', 'card-body', array(
                                $sem('table', 'table table-sm mb-3', array(
                                    $sem('tbody', '', array(
                                        // Subtotal — always visible (every order has a subtotal).
                                        // valueSpanCss='pg-eo-subtotal-formatted' — the shipping-method
                                        // picker's recomputeSidebarTotal() (below, in the shipping JS)
                                        // reads this exact class to live-recalculate the total when the
                                        // visitor changes shipping method before a full page reload.
                                        // Without it, that JS silently treated the row as 0 and
                                        // overwrote the correct total with "0.00" — see recomputeSidebarTotal's
                                        // guard comment for the full story.
                                        $tot_row(lang('Subtotal'),       '__cart_subtotal',                '', '', 'text-end pe-0', 'pg-eo-subtotal-formatted'),
                                        // Discount — only when an offer / coupon has produced a discount
                                        $tot_row(lang('Discount'),       '__cart_discount',                'has_discount'),
                                        // Tax — only when ECOMMERCE_TAX is on AND the order has taxable lines.
                                        // valueSpanCss — same reason as Subtotal above.
                                        $tot_row(lang('Tax'),            '__cart_tax',                     'has_tax', '', 'text-end pe-0', 'pg-eo-tax-formatted'),
                                        // Shipping — only when ship cost > 0 (digital orders skip).
                                        // valueSpanCss — same reason as Subtotal above.
                                        $tot_row(lang('Shipping'),       '__cart_shipping',                'has_shipping_cost', '', 'text-end pe-0', 'pg-eo-shipping-formatted'),
                                        // Gift card — only when a gift card was redeemed
                                        $tot_row(lang('Gift card'),      '__cart_gift_card_discount',      'has_gift_card'),
                                        // Card surcharge — only when CC is selected AND a surcharge applies
                                        $tot_row(lang('Card surcharge'), '__cart_surcharge',               'has_surcharge', 'pg-eo-surcharge-row'),
                                        // Instalment fee — visibility-bound; the instalment JS also updates the value text on plan change.
                                        $installment_fee_row,
                                        // Total — always visible (last row, bold + border-top accent)
                                        $tot_row(lang('Total'),          '__cart_total_with_surcharge',    '',
                                                 'fw-bold border-top', 'text-end pe-0', 'pg-eo-total-formatted'),
                                        // Amount charged — only when the visitor reads another currency
                                        // than the base one the gateway takes; the instalment JS keeps it
                                        // in step with the chosen plan.
                                        $tot_row(lang('Amount charged'), '__cart_total_charged',           'has_foreign_currency',
                                                 'small', 'text-end pe-0', 'pg-eo-charged-formatted'),
                                    )),
                                )),
                                $sem('p', 'small text-muted pg-eo-currency-note', '',
                                     array('bindings' => array('text' => '__currency_disclaimer', 'eo_visible_if' => 'has_foreign_currency'))),
                                // Active promotion list — sourced from
                                // orders.discount_offer_id /
                                // order_items.offer_id / ship_tos.offer_id.
                                $bind('applied_offers', 'pg-eo-applied-offers-anchor'),

                                // ── Terms consent checkbox ─────────────────
                                // Real form-check with real <input> (eo_field bound)
                                // + label containing a real <a> with Bootstrap modal
                                // trigger attrs. The modal itself is a REAL Bootstrap
                                // modal element at the end of the tree — designer
                                // edits modal content visually with heading/paragraph
                                // nodes inside modal-body. No system extraction.
                                $sem('div', 'form-check pg-eo-terms mb-2', array(
                                    array('type' => 'semantic', 'props' => array(
                                        'tag' => 'input',
                                        'cssClass' => 'form-check-input',
                                        '_attrs' => array(
                                            array('name' => 'type',     'value' => 'checkbox'),
                                            array('name' => 'value',    'value' => '1'),
                                            array('name' => 'required', 'value' => ''),
                                        ),
                                        '_bindings' => array('eo_field' => 'agree_terms'),
                                    ), 'children' => array()),
                                    $sem('label', 'form-check-label small', array(
                                        $sem('span', '', lang('By completing my order I accept')),
                                        array('type' => 'semantic', 'props' => array(
                                            'tag' => 'a',
                                            'cssClass' => '',
                                            'text' => lang('the sales agreement and the terms of use'),
                                            '_attrs' => array(
                                                array('name' => 'href',           'value' => '#'),
                                                array('name' => 'data-bs-toggle', 'value' => 'modal'),
                                                array('name' => 'data-bs-target', 'value' => '#pg-eo-terms-modal'),
                                            ),
                                        ), 'children' => array()),
                                        $sem('span', '', lang('and confirm I have read them.')),
                                    ), array('attrs' => array(array('name' => 'for', 'value' => 'agree_terms')))),
                                )),

                                // ── Newsletter subscription (opt_in) ───────
                                // Moved here from the "Order Preferences" card —
                                // sits between terms acceptance and the
                                // submit button so the visitor opts-in as
                                // the last step before confirming the order.
                                // Plain checkbox (no required) — purely
                                // permission-based, default off.
                                $check_row('opt_in', lang('I want to subscribe to the campaign and offer newsletter'), 'pg-eo-opt-in mb-3'),

                                // Real <button> elements. eo_action binding
                                // forces type/name/value attrs at render.
                                $sem('div', 'd-grid gap-2 mt-3', array(
                                    $sem('button', 'btn btn-primary btn-lg', lang('Complete Order'),
                                         array('bindings' => array('action' => 'eo_submit_purchase'))),
                                    $sem('button', 'btn btn-outline-secondary', lang('Update'),
                                         array('bindings' => array('action' => 'eo_submit_update'))),
                                )),
                            )),
                        )),
                    ), array('style' => 'top:1rem')),
                )),
                // ╰─────────────────────────────────────────────────────╯
            )),

            // ── Real Bootstrap modal: order terms ("Order Terms") ──
            // No extraction, no system binding — this is a normal Bootstrap
            // modal element the designer can edit, restyle, add fields to,
            // or delete entirely. The trigger <a> above uses standard
            // data-bs-toggle="modal" data-bs-target="#pg-eo-terms-modal".
            //
            // The widget JS (_pg_eo_modal_relocate_inline_js) re-parents
            // any .modal element inside the EO form to <body> on DOM ready
            // so Bootstrap's backdrop manager works (nested-in-form modals
            // leave a stuck black overlay — confirmed bug). The relocation
            // preserves the modal's id + trigger relationships.
            array('type' => 'semantic', 'props' => array(
                'tag' => 'div',
                'cssClass' => 'modal fade',
                'customName' => lang('Terms Modal'),
                '_attrs' => array(
                    array('name' => 'id',                'value' => 'pg-eo-terms-modal'),
                    array('name' => 'tabindex',          'value' => '-1'),
                    array('name' => 'aria-hidden',       'value' => 'true'),
                    array('name' => 'aria-labelledby',   'value' => 'pg-eo-terms-modal-label'),
                ),
            ), 'children' => array(
                $sem('div', 'modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered', array(
                    $sem('div', 'modal-content', array(
                        $sem('div', 'modal-header', array(
                            array('type' => 'semantic', 'props' => array(
                                'tag' => 'h5',
                                'cssClass' => 'modal-title',
                                'text' => lang('Order Terms'),
                                '_attrs' => array(array('name' => 'id', 'value' => 'pg-eo-terms-modal-label')),
                            ), 'children' => array()),
                            array('type' => 'semantic', 'props' => array(
                                'tag' => 'button',
                                'cssClass' => 'btn-close',
                                '_attrs' => array(
                                    array('name' => 'type',           'value' => 'button'),
                                    array('name' => 'data-bs-dismiss','value' => 'modal'),
                                    array('name' => 'aria-label',     'value' => lang('Close')),
                                ),
                            ), 'children' => array()),
                        )),
                        $sem('div', 'modal-body', array(
                            $sem('h6', 'mb-3',         lang('1. General Terms')),
                            $sem('p',  'small',        lang('By confirming your order you agree that the amount is charged with the payment method you chose and that the products/services are supplied to you by us.')),
                            $sem('h6', 'mb-3 mt-4',    lang('2. Cancellation and Refund')),
                            $sem('p',  'small',        lang('Digital products cannot be returned once downloaded. For physical products you have the right to withdraw within 14 days of delivery.')),
                            $sem('h6', 'mb-3 mt-4',    lang('3. Delivery')),
                            $sem('p',  'small',        lang('Delivery time depends on the carrier you choose and on your address. Your tracking number is emailed to you once the order is confirmed.')),
                            $sem('h6', 'mb-3 mt-4',    lang('4. Protection of Personal Data')),
                            $sem('p',  'small',        lang('The information you give while ordering is processed only to complete your order, to invoice it and to deliver it. Your rights under data protection law are reserved.')),
                            $sem('h6', 'mb-3 mt-4',    lang('5. Contact')),
                            $sem('p',  'small mb-0',   lang('You can reach us from our contact page with any question.')),
                        )),
                        $sem('div', 'modal-footer', array(
                            array('type' => 'semantic', 'props' => array(
                                'tag' => 'button',
                                'cssClass' => 'btn btn-outline-secondary',
                                'text' => lang('Close'),
                                '_attrs' => array(
                                    array('name' => 'type',            'value' => 'button'),
                                    array('name' => 'data-bs-dismiss', 'value' => 'modal'),
                                ),
                            ), 'children' => array()),
                            array('type' => 'semantic', 'props' => array(
                                'tag' => 'button',
                                'cssClass' => 'btn btn-primary',
                                'text' => lang('I Have Read and Accept'),
                                '_attrs' => array(
                                    array('name' => 'type',                     'value' => 'button'),
                                    array('name' => 'data-bs-dismiss',          'value' => 'modal'),
                                    array('name' => 'data-pg-eo-terms-accept',  'value' => '1'),
                                ),
                            ), 'children' => array()),
                        )),
                    )),
                )),
            )),
        ),
    );
}

// List of section bindings that MUST be present for the checkout to function.
// Used by the designer panel to warn when the operator dragged out a critical
// block (and by api.php to reject saves that would leave the page unusable).
//
// NOTE on the billing fields: with the rich default tree, billing inputs are
// REAL <input> elements directly in the designer's tree (not bound). Designer
// can rearrange / hide individual fields freely — server validates required
// ones at submit time. So we don't list a `billing` section here; instead
// `payment_methods` and `cart_items` / `totals` are the absolute minimum
// dynamic blocks that have no real-element equivalent in the tree.
function _eo_required_section_bindings()
{
    // With the rich default tree, totals + cart items + buttons + country
    // select are REAL semantic elements with field bindings. The only
    // section binding that still HAS to be present is payment_methods —
    // the radio list depends on site settings (which methods are enabled,
    // which is the default) and can\'t be hand-built in the tree.
    //
    // billing_country_select used to be required as a section binding,
    // but the country dropdown is now a real <select> with
    // _bindings.eo_field='billing_country' — the field-binding walker
    // injects the <option> list at render time. No section anchor needed.
    return array(
        'payment_methods' => true,
    );
}

// ── Helper: render cart summary table ────────────────────────────────────────
// Read-only line items with editable qty input + remove link.
//
// Per-item attached forms (THREE distinct cases):
//   1. products.form = 1 → render the product's CUSTOM form_fields via
//      _pg_render_cart_item_form_data(). Visitor sees ONLY the fields the
//      site admin configured (Recipient Email, Message, custom checkbox,
//      whatever — site decides). No hardcoded layout.
//   2. products.gift_card = 1 AND form = 0 → render hardcoded recipient
//      details (email/from/message/delivery_date). Required because
//      express_order.php validates recipient_email_address per quantity.
//   3. products.gift_card = 1 AND form = 1 → render the custom form ONLY.
//      The site's form is the source of truth for what to ask. We auto-
//      emit a HIDDEN gift_card_recipient_email_address (= billing_email)
//      so the legacy validation at express_order.php:1074 still passes
//      without forcing a duplicate "Recipient Email" field.
//
// Field naming MUST match the legacy convention exactly:
//   order_item_<oi_id>_quantity_number_<n>_gift_card_recipient_email_address
//   order_item_<oi_id>_quantity_number_<n>_gift_card_from_name
//   order_item_<oi_id>_quantity_number_<n>_gift_card_message
//   order_item_<oi_id>_quantity_number_<n>_gift_card_delivery_date
//   order_item_<oi_id>_quantity_number_<n>_form_field_<field_id>
function _eo_render_cart_summary($items, $widget_id, $form_id, $fmt, $lf = null, $gift_card_data = array())
{
    if (!is_array($items) || empty($items)) {
        return '<div class="text-muted small">' . h(lang('No items in cart.')) . '</div>';
    }
    if (!is_array($gift_card_data)) $gift_card_data = array();

    $base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $sw_dir = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';

    // Responsive stacking below md — same Bootstrap-only recipe as the default
    // designer tree (see _eo_default_designer_tree). The fixed right-hand
    // column widths total 24rem, which crushed the item column on narrow
    // screens; from md up they still apply.
    $html = '<div class="table-responsive"><table class="table align-middle mb-0">'
          . '<thead class="d-none d-md-table-header-group"><tr class="d-block d-md-table-row">'
          .   '<th scope="col" class="text-muted small d-block d-md-table-cell">' . h(lang('Item')) . '</th>'
          .   '<th scope="col" class="text-muted small text-end d-block d-md-table-cell" style="width:6rem">' . h(lang('Qty')) . '</th>'
          .   '<th scope="col" class="text-muted small text-end d-block d-md-table-cell" style="width:8rem">' . h(lang('Price')) . '</th>'
          .   '<th scope="col" class="text-muted small text-end d-block d-md-table-cell" style="width:8rem">' . h(lang('Amount')) . '</th>'
          .   '<th scope="col" class="d-block d-md-table-cell" style="width:2rem"></th>'
          . '</tr></thead><tbody>';

    // remove_item_from_cart.php enforces validate_token_field() — without the
    // session CSRF token in the query string visitors hit "Maalesef,
    // oturumunuzun süresi dolmuş…" and the cart row never disappears. The
    // shopping cart widget already passes it; mirror that exact set of params
    // here so the legacy handler accepts both screens.
    $csrf_token  = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
    $eo_back_url = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';

    foreach ($items as $it) {
        $iid       = (int)$it['item_id'];
        $qty       = (int)$it['quantity'];
        $price     = (int)$it['price'];      // cents
        $line      = $price * $qty;
        $title     = (string)(!empty($it['short_description']) ? $it['short_description'] : $it['product_name']);
        $is_gc      = !empty($it['gift_card']);
        $has_form   = !empty($it['form']);
        $form_qt    = isset($it['form_quantity_type']) ? (string)$it['form_quantity_type'] : 'One Form per Product';
        // Remove link MUST include the CSRF token + a send_to back to the
        // current page (otherwise the legacy handler bounces the visitor to
        // the shopping cart screen) AND a screen marker so the legacy script
        // applies the right post-remove behaviour.
        $remove_url = $base . $sw_dir . '/remove_item_from_cart.php'
                    . '?order_item_id=' . $iid
                    . '&screen=express_order'
                    . ($eo_back_url !== '' ? '&send_to=' . rawurlencode($eo_back_url) : '')
                    . ($csrf_token  !== '' ? '&token='   . rawurlencode($csrf_token)  : '');

        // Qty cell — input-group with - / + buttons attached. The JS handler
        // (emitted once per page via _pg_qty_stepper_inline_js()) wires the
        // buttons to the closest input[type=number] within the same .input-group
        // and dispatches a synthetic `input` event so any framework / aria
        // listeners see the change. The buttons do NOT auto-submit; the visitor
        // confirms the new quantities by clicking the Update button (designer-
        // placed via `_bindings.action='eo_submit_update'`).
        // NOTE: the colspan rows further down (product form / gift card) keep
        // normal table-row display on purpose — they hold a single full-width
        // cell, and `d-block` on a colspan cell would cancel the span.
        $html .=
            '<tr class="d-block d-md-table-row border-bottom">'
          .   '<td class="pg-eo-item-name d-block d-md-table-cell">' . h($title) . '</td>'
          .   '<td class="text-start text-md-end d-block d-md-table-cell">'
          .     '<div class="input-group input-group-sm pg-qty-stepper ms-0 ms-md-auto" style="max-width:9rem;display:inline-flex">'
          .       '<button type="button" class="btn btn-outline-secondary" data-pg-qty-action="dec"'
          .              ' aria-label="' . h(lang('Decrease')) . '" tabindex="-1">&minus;</button>'
          .       '<input type="number" min="0" step="1" class="form-control form-control-sm text-center"'
          .             ' name="quantity[' . $iid . ']" value="' . $qty . '" form="' . h($form_id) . '">'
          .       '<button type="button" class="btn btn-outline-secondary" data-pg-qty-action="inc"'
          .              ' aria-label="' . h(lang('Increase')) . '" tabindex="-1">+</button>'
          .     '</div>'
          .   '</td>'
          .   '<td class="text-start text-md-end d-block d-md-table-cell">' . $fmt($price) . '</td>'
          .   '<td class="text-start text-md-end fw-semibold d-block d-md-table-cell">' . $fmt($line) . '</td>'
          .   '<td class="text-start text-md-end d-block d-md-table-cell">'
          .     '<a href="' . h($remove_url) . '" class="text-danger" title="' . h(lang('Remove')) . '">'
          .       '<i class="bi bi-x-lg"></i>'
          .     '</a>'
          .   '</td>'
          . '</tr>';

        // CASE 1: Product has its own form (form_fields configured) → render
        // it via the existing helper. Site admin's form layout is the source
        // of truth; we don't second-guess it. For gift_card products that
        // ALSO have a form, we additionally emit a HIDDEN
        // gift_card_recipient_email_address (= billing email) further below
        // so express_order.php's hardcoded validation still passes without
        // forcing a duplicate "Recipient Email" field on top of the form.
        if ($has_form && function_exists('_pg_render_cart_item_form_data')) {
            $form_html = _pg_render_cart_item_form_data(
                $iid, (int)$it['product_id'], $qty,
                (string)(!empty($it['short_description']) ? $it['short_description'] : $it['product_name']),
                $form_qt, $form_id,
                // The admin-configured `products.form_name` becomes the
                // fieldset legend inside the renderer — no separate heading
                // row above the block, no decorative icon.
                (isset($it['form_name']) && trim((string)$it['form_name']) !== '')
                    ? (string)lang(trim((string)$it['form_name'])) : ''
            );
            if ($form_html !== '') {
                $html .= '<tr><td colspan="5" class="bg-body-tertiary border-top-0">'
                       . $form_html
                       . '</td></tr>';
            }
        }

        // CASE 2: Hardcoded gift card recipient details — ONLY when product
        // is a gift card AND has NO custom form. With form=1, the form
        // already collects recipient info (the user-configured one).
        if ($is_gc && !$has_form && $qty > 0) {
            $gc_count = min($qty, 100);
            $rows_html = '';
            for ($qn = 1; $qn <= $gc_count; $qn++) {
                $field_prefix = 'order_item_' . $iid . '_quantity_number_' . $qn . '_gift_card_';
                // Get value: liveform session > existing order_item_gift_cards row > ''
                $get_gc = function ($col) use ($field_prefix, $lf, $gift_card_data, $iid, $qn) {
                    $key = $field_prefix . $col;
                    if ($lf) {
                        $v = $lf->get_field_value($key);
                        if ($v !== '' && $v !== null) return (string)$v;
                    }
                    if (isset($gift_card_data[$iid][$qn][_eo_gc_col_map($col)])) {
                        $val = (string)$gift_card_data[$iid][$qn][_eo_gc_col_map($col)];
                        // delivery_date is stored as Y-m-d but the input is
                        // type=date which expects exactly that — no conversion.
                        return $val;
                    }
                    return '';
                };

                // <fieldset> + <legend> reading just "Gift card" (+ "(n / m)"
                // when the quantity repeats) — the same shape the legacy cart
                // uses. No banner above the box and no icon: that was invented
                // copy the operator never wrote and can't edit.
                // Fields are full-width stacked, not paired into columns: the
                // identical block shape serves operator-defined product forms
                // where the next field could be a select or a file picker, and
                // any fixed pairing collapses as soon as the field mix changes.
                $rows_html .=
                    '<fieldset class="pg-eo-gc-set border rounded p-3 mb-3">'
                  . '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h(lang('Gift card'))
                  .   ($gc_count > 1 ? ' (' . $qn . ' / ' . $gc_count . ')' : '') . '</legend>'
                  . '<div class="mb-3">'
                  .   '<label class="form-label" for="' . h($field_prefix) . 'recipient_email_address">'
                  .     h(lang('Recipient Email')) . ' <span class="text-danger">*</span>'
                  .   '</label>'
                  .   '<input type="email" required class="form-control"'
                  .     ' id="' . h($field_prefix) . 'recipient_email_address"'
                  .     ' name="' . h($field_prefix) . 'recipient_email_address"'
                  .     ' value="' . h($get_gc('recipient_email_address')) . '">'
                  . '</div>'
                  . '<div class="mb-3">'
                  .   '<label class="form-label" for="' . h($field_prefix) . 'from_name">' . h(lang('From Name')) . '</label>'
                  .   '<input type="text" class="form-control"'
                  .     ' id="' . h($field_prefix) . 'from_name"'
                  .     ' name="' . h($field_prefix) . 'from_name"'
                  .     ' value="' . h($get_gc('from_name')) . '" maxlength="100">'
                  . '</div>'
                  . '<div class="mb-3">'
                  .   '<label class="form-label" for="' . h($field_prefix) . 'message">' . h(lang('Message')) . '</label>'
                  .   '<textarea class="form-control" rows="3"'
                  .     ' id="' . h($field_prefix) . 'message"'
                  .     ' name="' . h($field_prefix) . 'message"'
                  .     ' maxlength="500">' . h($get_gc('message')) . '</textarea>'
                  . '</div>'
                  . '<div class="mb-3">'
                  .   '<label class="form-label" for="' . h($field_prefix) . 'delivery_date">' . h(lang('Delivery Date')) . '</label>'
                  .   '<input type="date" class="form-control"'
                  .     ' id="' . h($field_prefix) . 'delivery_date"'
                  .     ' name="' . h($field_prefix) . 'delivery_date"'
                  .     ' value="' . h($get_gc('delivery_date')) . '">'
                  .   '<div class="form-text">' . h(lang('Leave blank to send immediately.')) . '</div>'
                  . '</div>'
                  . '</fieldset>';
            }
            $html .=
                '<tr><td colspan="5" class="bg-body-tertiary border-top-0">'
              . '<div class="pg-eo-gift-cards" data-pg-eo-gc-item="' . $iid . '">'
              .   $rows_html
              . '</div>'
              . '</td></tr>';
        }

        // CASE 3: Gift card WITH a custom form. The form already gathers the
        // recipient info via site-configured fields, so we don't render the
        // hardcoded block above. But express_order.php:1074 still requires
        // gift_card_recipient_email_address per quantity — emit it as a
        // HIDDEN input prefilled with the buyer's billing email so the
        // legacy validation passes silently (visitor never sees the duplicate
        // field but the order completes correctly).
        if ($is_gc && $has_form && $qty > 0) {
            $billing_email = $lf ? (string)$lf->get_field_value('billing_email_address') : '';
            $gc_count = min($qty, 100);
            for ($qn = 1; $qn <= $gc_count; $qn++) {
                $hk = 'order_item_' . $iid . '_quantity_number_' . $qn . '_gift_card_recipient_email_address';
                $html .=
                    '<tr style="display:none"><td colspan="5">'
                  . '<input type="hidden" name="' . h($hk) . '" value="' . h($billing_email) . '">'
                  . '</td></tr>';
            }
        }
    }

    $html .= '</tbody></table></div>';
    return $html;
}

// Map the form-field column suffix to the order_item_gift_cards column name
// (most match exactly except the recipient email which the DB column drops
// the '_address' suffix legacy template kept).
function _eo_gc_col_map($form_suffix)
{
    static $map = array(
        'recipient_email_address' => 'recipient_email_address',
        'from_name'               => 'from_name',
        'message'                 => 'message',
        'delivery_date'           => 'delivery_date',
    );
    return isset($map[$form_suffix]) ? $map[$form_suffix] : $form_suffix;
}

// ── Helper: render billing address standard fields ───────────────────────────
// Field names MUST match the legacy template exactly so express_order.php
// receives them in the expected POST keys (it doesn't accept aliases).
function _eo_render_billing_address($lf, $totals, $cfg)
{
    // Pre-fill from existing order if we have one, else from liveform session.
    $get = function ($k) use ($lf, $totals) {
        // Prefer the liveform session value (visitor's most recent edit) over
        // the order row's stored value (which was last written when they
        // submitted). This matches how get_order_preview.php behaves.
        if ($lf) {
            $v = $lf->get_field_value($k);
            if ($v !== '' && $v !== null) return (string)$v;
        }
        // Fallback: orders.billing_<col> if the column exists
        $col = preg_replace('/^billing_/', '', $k);
        $key = 'billing_' . $col;
        if (is_array($totals) && isset($totals[$key])) return (string)$totals[$key];
        return '';
    };

    // Country options use CODE as value — orders.billing_country stores
    // country code (e.g. 'TR'), and tax rate / shipping validation look it
    // up against `countries.code = '…'`. See _eo_country_options() comment.
    $country_options = _eo_country_options($get('billing_country'));
    // Salutation options come from get_salutation_options() → key/value array
    $sal_options = function_exists('get_salutation_options') ? get_salutation_options() : array('' => '');
    $sal_html = '';
    $sel_sal = $get('billing_salutation');
    foreach ($sal_options as $val => $label) {
        $sal_html .= '<option value="' . h($val) . '"' . ($val === $sel_sal ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    // Two-column row helper
    $row = function ($label, $name, $type = 'text', $value = '', $required = false, $extra = '') {
        $req = $required ? ' <span class="text-danger">*</span>' : '';
        $req_attr = $required ? ' required' : '';
        return '<div class="col-12 col-md-6 mb-3">'
             . '<label class="form-label small fw-semibold" for="' . h($name) . '">' . h($label) . $req . '</label>'
             . '<input type="' . h($type) . '" class="form-control" id="' . h($name) . '" name="' . h($name) . '"'
             .   ' value="' . h($value) . '"' . $req_attr . ' ' . $extra . '>'
             . '</div>';
    };

    $html = '<div class="row">';
    // Salutation (full width, narrow)
    $html .= '<div class="col-12 col-md-3 mb-3">'
           . '<label class="form-label small fw-semibold" for="billing_salutation">' . h(lang('Salutation')) . '</label>'
           . '<select class="form-select" id="billing_salutation" name="billing_salutation">' . $sal_html . '</select>'
           . '</div><div class="col-md-9"></div>';
    $html .= $row(lang('First Name'), 'billing_first_name', 'text', $get('billing_first_name'), true);
    $html .= $row(lang('Last Name'),  'billing_last_name',  'text', $get('billing_last_name'),  true);
    $html .= $row(lang('Company'),    'billing_company',    'text', $get('billing_company'));
    $html .= $row(lang('Email'),      'billing_email_address', 'email', $get('billing_email_address'), true);
    $html .= $row(lang('Address 1'),  'billing_address_1',  'text', $get('billing_address_1'), true);
    $html .= $row(lang('Address 2'),  'billing_address_2',  'text', $get('billing_address_2'));
    $html .= $row(lang('City'),       'billing_city',       'text', $get('billing_city'), true);
    // Country select
    $html .= '<div class="col-12 col-md-6 mb-3">'
           . '<label class="form-label small fw-semibold" for="billing_country">' . h(lang('Country')) . ' <span class="text-danger">*</span></label>'
           . '<select class="form-select" id="billing_country" name="billing_country" required>'
           . '<option value="">' . h(lang('Select...')) . '</option>'
           . $country_options
           . '</select>'
           . '</div>';
    // State / Zip / Phone — server requires these when the picked country has
    // states in DB (TR has 81 il, US has 50 states, etc.) and when
    // countries.zip_code_required is set. submit_order.php:445-468 enforces
    // this. Marking them visually-required up front avoids the silent "form
    // looks fine but submit redirects back with state error" symptom — visitor
    // sees the * and HTML5 catches the missing field before POST.
    $html .= $row(lang('State / Province'), 'billing_state', 'text', $get('billing_state'), true);
    $html .= $row(lang('Zip / Postal Code'), 'billing_zip_code', 'text', $get('billing_zip_code'), true);
    $html .= $row(lang('Phone'), 'billing_phone_number', 'tel', $get('billing_phone_number'), true);
    $html .= '</div>';

    return $html;
}

// ── Helper: render conditional payment methods ───────────────────────────────
// Mirrors the conditional logic in get_order_preview.php: each method only
// shows when its enabling settings are on. payment_method radio values are
// case-sensitive and verbatim — submit_order.php branches on these strings.
// ── Designer-mode helpers ───────────────────────────────────────────────────
// These three functions render INDIVIDUAL slices of the checkout that the
// designer's tree binds via `props._bindings.section='eo_*'`. They share
// logic with the legacy monolithic _eo_render_payment_methods/_eo_render_*
// — kept as separate small functions so the designer can place each piece
// independently (radios in one section, card fields as real <input>s in
// another, installment selector in a third).
function _eo_render_payment_methods_only($lf)
{
    $methods = array();
    $cc_on   = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == true;
    $any_card = (defined('ECOMMERCE_VISA') && ECOMMERCE_VISA == true)
             || (defined('ECOMMERCE_MASTERCARD') && ECOMMERCE_MASTERCARD == true)
             || (defined('ECOMMERCE_AMERICAN_EXPRESS') && ECOMMERCE_AMERICAN_EXPRESS === true)
             || (defined('ECOMMERCE_DINERS_CLUB') && ECOMMERCE_DINERS_CLUB == true)
             || (defined('ECOMMERCE_DISCOVER_CARD') && ECOMMERCE_DISCOVER_CARD == true)
             || (defined('ECOMMERCE_TROY') && ECOMMERCE_TROY == true);
    if ($cc_on && $any_card) $methods[] = 'Credit/Debit Card';
    if (defined('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT') && ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT == true) $methods[] = 'PayPal Express Checkout';
    if (defined('ECOMMERCE_PAY_WITH_IYZICO') && ECOMMERCE_PAY_WITH_IYZICO == true)               $methods[] = 'Pay With Iyzico';
    if (defined('ECOMMERCE_OFFLINE_PAYMENT') && ECOMMERCE_OFFLINE_PAYMENT == true)               $methods[] = 'Offline Payment';
    if (empty($methods)) {
        return '<div class="alert alert-warning mb-0">' . h(lang('No payment methods are configured. Please contact the site administrator.')) . '</div>';
    }
    $sel = $lf ? (string)$lf->get_field_value('payment_method') : '';
    if (!in_array($sel, $methods, true)) $sel = $methods[0];
    if (count($methods) === 1) {
        return '<input type="hidden" name="payment_method" value="' . h($methods[0]) . '">'
             . '<div class="text-muted small">' . h(lang($methods[0])) . '</div>';
    }
    $html = '';
    foreach ($methods as $m) {
        $id = 'pg_eo_pm_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($m));
        $html .= '<div class="form-check">'
              . '<input class="form-check-input" type="radio" name="payment_method"'
              .   ' id="' . h($id) . '" value="' . h($m) . '"'
              .   ($m === $sel ? ' checked' : '') . '>'
              . '<label class="form-check-label" for="' . h($id) . '">' . h(lang($m)) . '</label>'
              . '</div>';
    }
    // Idempotent CC-fields toggle JS — three responsibilities:
    //   1. on `change` of any payment_method radio → toggle CC fields visibility
    //   2. on DOMContentLoaded → run the toggle ONCE so a stale "Offline
    //      Payment" radio (e.g. visitor refreshed after submit failure with
    //      Offline picked) doesn't show stale card inputs alongside it
    //   3. on `pageshow` (bfcache restore) → re-sync, since DOMContentLoaded
    //      doesn't fire when the browser restores from back/forward cache
    /*
     * ── REQUIRED TOGGLE ────────────────────────────────────────────────
     * Browser HTML5 validation can't focus a hidden `required` input,
     * so the form refuses to submit "silently" (no console error visible
     * to non-devs, just nothing happens when clicking Siparişi Tamamla).
     *
     * New trees are authored with `data-pg-cc-required="1"` (no real
     * `required` attr); we flip to real `required` only when the CC
     * block is actually visible.
     *
     * Existing saved trees (created before this fix) still have raw
     * `required` on the CC inputs — the `migrate()` step below detects
     * any inputs inside .pg-eo-cc-fields that have `required` without
     * the `data-pg-cc-required` marker and converts them: stamps the
     * marker, then lets the normal syncRequired flow add `required`
     * back ONLY when CC is the active payment method. One-shot per
     * input via the marker → idempotent across event bursts.
     */
    $html .= '<script>(function(){'
          . 'if(window.__pgEoCcInit)return;window.__pgEoCcInit=1;'
          . 'function migrate(cc){'
          .   'cc.querySelectorAll("input[required]:not([data-pg-cc-required])").forEach(function(el){'
          .     'el.setAttribute("data-pg-cc-required","1");'
          .     'el.removeAttribute("required");'
          .   '});'
          . '}'
          . 'function syncCc(form){'
          .   'if(!form)return;'
          .   'var cc=form.querySelector("[data-pg-eo-cc-fields]");if(!cc)return;'
          .   'migrate(cc);'
          .   'var picked=form.querySelector("input[name=\'payment_method\']:checked");'
          .   'var v=picked?picked.value:"";'
          .   'var visible=(v==="Credit/Debit Card");'
          .   'cc.style.display=visible?"":"none";'
          .   'cc.querySelectorAll("[data-pg-cc-required]").forEach(function(el){'
          .     'if(visible){el.setAttribute("required","");}'
          .     'else{el.removeAttribute("required");}'
          .   '});'
          . '}'
          . 'function syncAll(){'
          .   'document.querySelectorAll("form[data-pg-eo-form]").forEach(syncCc);'
          . '}'
          . 'document.addEventListener("change",function(e){'
          .   'var t=e.target;if(!t||t.name!=="payment_method")return;'
          .   'syncCc(t.closest("form"));'
          . '});'
          . 'if(document.readyState==="loading"){'
          .   'document.addEventListener("DOMContentLoaded",syncAll);'
          . '}else{syncAll();}'
          . 'window.addEventListener("pageshow",syncAll);'
          . '})();</script>';
    return $html;
}

function _eo_render_billing_country_select($lf)
{
    $current = $lf ? (string)$lf->get_field_value('billing_country') : '';
    if ($current === '') {
        $current = (string)db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1");
    }
    // countries table has no `status` column — all rows are considered active.
    $rows = db_items("SELECT code, name FROM countries ORDER BY name ASC");
    if (!is_array($rows)) $rows = array();
    $html = '<select class="form-select" id="billing_country" name="billing_country" required>'
          . '<option value="">' . h(lang('Select...')) . '</option>';
    foreach ($rows as $r) {
        $sel = ((string)$r['code'] === $current) ? ' selected' : '';
        $html .= '<option value="' . h($r['code']) . '"' . $sel . '>' . h($r['name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

// ── Upsell offers section — Bootstrap alerts that nudge the visitor toward
// the next promotional tier ("Spend ₺100 more and get free shipping" etc.).
// Data source: apply_offers_to_cart() returns {pending_offers, upsell_offers}
// where upsell_offers[i] has: upsell_message, description, upsell_action_page_id,
// upsell_action_button_label. Empty array → empty HTML (designer marker is
// invisible, no wasted space).
function _eo_render_upsell_offers($upsell_offers)
{
    if (!is_array($upsell_offers) || empty($upsell_offers)) return '';
    $html = '';
    foreach ($upsell_offers as $offer) {
        if (!is_array($offer)) continue;
        $msg = !empty($offer['upsell_message']) ? (string)$offer['upsell_message']
             : (!empty($offer['description'])  ? (string)$offer['description'] : '');
        if ($msg === '') continue;
        $action_html = '';
        if (!empty($offer['upsell_action_page_id'])) {
            $action_lbl = !empty($offer['upsell_action_button_label'])
                ? (string)$offer['upsell_action_button_label']
                : (string)lang('More Info');
            $action_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . get_page_name((int)$offer['upsell_action_page_id']);
            $action_html = ' <a href="' . h($action_url) . '" class="alert-link ms-2">'
                         . h($action_lbl) . ' <i class="bi bi-arrow-right ms-1"></i></a>';
        }
        // alert-dismissible + close button: info-tier alerts are informational
        // so visitor should be able to dismiss them. danger/warning stay
        // forced visible (no close button) — those are blockers/important.
        $html .= '<div class="alert alert-info alert-dismissible fade show small mb-2 pg-eo-upsell-offer" role="alert">'
              . '<i class="bi bi-tag-fill me-2"></i>' . h($msg) . $action_html
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
              . '</div>';
    }
    return $html;
}

// ── Applied offers section — lists promotions ALREADY active on the order
// (10% off code, free shipping triggered, gift-with-purchase, …). Renders
// as a Bootstrap success alert inside the totals sidebar so visitor sees
// what discounts are baked into the price they\'re about to pay.
function _eo_render_applied_offers()
{
    $oid = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
    if ($oid <= 0) return '';
    // Pull the union of offer IDs referenced anywhere on this order:
    //   • orders.discount_offer_id (cart-wide discount)
    //   • order_items.offer_id (per-item bonus)
    //   • ship_tos.offer_id (per-recipient bonus, e.g. free shipping)
    $offer_ids = array();
    $row = db_item("SELECT discount_offer_id FROM orders WHERE id = '" . (int)$oid . "' LIMIT 1");
    if (is_array($row) && !empty($row['discount_offer_id'])) $offer_ids[(int)$row['discount_offer_id']] = true;
    $rows = db_items("SELECT DISTINCT offer_id FROM order_items WHERE order_id = '" . (int)$oid . "' AND offer_id > 0");
    if (is_array($rows)) foreach ($rows as $r) $offer_ids[(int)$r['offer_id']] = true;
    $rows = db_items("SELECT DISTINCT offer_id FROM ship_tos WHERE order_id = '" . (int)$oid . "' AND offer_id > 0");
    if (is_array($rows)) foreach ($rows as $r) $offer_ids[(int)$r['offer_id']] = true;
    if (empty($offer_ids)) return '';

    // offers.status is an enum('enabled','disabled') — NOT a boolean column.
    $ids_csv = implode(',', array_keys($offer_ids));
    $offers = db_items("SELECT id, code, description FROM offers WHERE id IN ($ids_csv) AND status = 'enabled'");
    if (!is_array($offers) || empty($offers)) return '';

    // The message is optional in the offer editor. An offer without one used
    // to drop out of this list silently, so a discount was applied to the
    // total with nothing on the page saying why; the code stands in for it.
    $items = '';
    $shown = 0;
    foreach ($offers as $o) {
        $desc = pg_offer_public_label($o);
        if ($desc === '') continue;
        $items .= '<li class="pg-eo-applied-offer">' . h($desc) . '</li>';
        $shown++;
    }
    if ($items === '') return '';

    $title = ($shown > 1) ? lang('Applied Offers') : lang('Applied Offer');
    // Intentionally NOT dismissible: applied offers reflect server-side cart
    // state — if the visitor X's them away they'd lose visibility into a
    // discount that's still being applied to their total. Other alerts on
    // the page (notices, errors) remain closable; this and saved_cart are
    // the only two exceptions. Designer can re-add btn-close if they want.
    return '<div class="alert alert-success fade show small mb-3 pg-eo-applied-offers" role="alert">'
         . '<div class="fw-semibold mb-1"><i class="bi bi-check-circle-fill me-1"></i>' . h($title) . '</div>'
         . '<ul class="mb-0 ps-3">' . $items . '</ul>'
         . '</div>';
}

// ── Saved-cart link section. Shows visitor the URL they can revisit later
// to restore THIS cart. Same UX as legacy shopping_cart layout 497.php
// lines 1732-1737 ("Bu Sepet Kaydedildi ..."). reference_code lives on
// the orders row; we wrap it in the current page's URL with `?r=CODE`.
// Returns empty string when no order exists yet (initial visit / no items).
function _eo_render_saved_cart_link($cart_label)
{
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
    if ($order_id <= 0) return '';
    $ref = (string)db_value("SELECT reference_code FROM orders WHERE id = '" . (int)$order_id . "' LIMIT 1");
    if ($ref === '') return '';
    // Resolve current page URL — strip any pre-existing `?r=` so we don't
    // double up if the visitor already arrived via the saved-cart link.
    $base_path = defined('URL_SCHEME') ? URL_SCHEME : 'https://';
    $base_path .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base_path .= defined('PATH') ? PATH : '/';
    $page_name = isset($_GET['page']) ? (string)$_GET['page'] : '';
    if ($page_name === '') {
        // Fallback for /siparis style pretty URLs — strip query string from REQUEST_URI.
        $ru = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $page_name = ltrim(preg_replace('/\?.*$/', '', $ru), '/');
    }
    $retrieve_url = $base_path . $page_name . '?r=' . rawurlencode($ref);
    $lbl = $cart_label !== '' ? $cart_label : (string)lang('cart');
    // alert-secondary auto-adapts to data-bs-theme="dark|light" via Bootstrap
    // 5.3 dark-mode CSS variables. alert-light has FIXED light bg and would
    // render as a near-white box on dark themes (text-muted on light bg → unreadable).
    // Intentionally NOT dismissible — the saved-cart link is the visitor\'s
    // ONLY way to recover their cart on a later device / browser. If they
    // dismiss it they\'d lose access to that recovery URL with no way to
    // surface it again until the page reloads. Other alerts (notices,
    // errors) remain closable; applied_offers + saved_cart are exceptions.
    return
        '<div class="alert alert-secondary fade show small mb-0 pg-eo-saved-cart">'
      . '<div class="text-muted mb-1"><i class="bi bi-bookmark-check me-1"></i>'
      . h(lang(array('string' => 'Your {var:1} is saved. To return to this {var:1} later, please use this link:', 'vars' => array($lbl)))) . '</div>'
      . '<a href="' . h($retrieve_url) . '" class="small text-break">' . h($retrieve_url) . '</a>'
      . '</div>';
}

// ── Address-book select (logged-in members) ─────────────────────────────
// Renders a small `<select>` that lists distinct billing addresses pulled
// from the visitor's past complete/exported orders. Choosing one fills
// the EO billing inputs via inline JS — no fetch, the address payload is
// serialized into the option's data-* attrs.
//
// Returns '' for guests, ghosting operators, or members with no past
// orders, so the section binding `_bindings.section='address_book'` can
// be dropped onto a tree unconditionally; an empty string collapses the
// container at render time without erroring.
function _eo_render_address_book_select()
{
    // Guard: logged-in real visitor only. Operators in ghost mode see the
    // ghosted member's data already (via the existing contact prefill); we
    // don't want to expose their order history selector too — that's the
    // contact view's responsibility.
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) return '';
    if (!empty($_SESSION['software']['ghost'])) return '';

    // Resolve the contact behind the logged-in user. Same pattern that
    // get_express_order.php / _eo_prefill_billing_fields use.
    $uname = isset($_SESSION['sessionusername']) ? (string)$_SESSION['sessionusername'] : '';
    if ($uname === '') return '';
    $contact_id = (int)db_value(
        "SELECT contacts.id FROM user
         LEFT JOIN contacts ON user.user_contact = contacts.id
         WHERE user.user_username = '" . e($uname) . "' LIMIT 1"
    );
    if ($contact_id <= 0) return '';

    // Pull the most recent billing addresses, de-duplicated on the
    // address signature so a customer who places 10 orders to the same
    // address only sees one entry. LIMIT keeps the inline JSON small.
    $rows = db_items(
        "SELECT billing_salutation, billing_first_name, billing_last_name,
                billing_company, billing_address_1, billing_address_2,
                billing_city, billing_state, billing_zip_code, billing_country,
                billing_phone_number, billing_email_address,
                MAX(order_date) AS last_used
         FROM orders
         WHERE contact_id = '" . (int)$contact_id . "'
           AND status IN ('complete','exported')
           AND billing_first_name <> ''
           AND billing_address_1 <> ''
         GROUP BY billing_first_name, billing_last_name, billing_address_1,
                  billing_address_2, billing_city, billing_state,
                  billing_zip_code, billing_country
         ORDER BY last_used DESC
         LIMIT 10"
    );
    if (!is_array($rows) || count($rows) === 0) return '';

    // Build options. The visible label is "First Last — Address, City".
    // The full payload rides as data-pgab='{"...":"..."}'; JS reads it on
    // change and writes the matching billing_* inputs.
    $opts = '<option value="">' . h(lang('Saved addresses…')) . '</option>';
    foreach ($rows as $i => $r) {
        $label_parts = array_filter(array(
            trim((string)$r['billing_first_name'] . ' ' . (string)$r['billing_last_name']),
            trim((string)$r['billing_address_1']),
            trim((string)$r['billing_city']),
        ));
        $label = implode(' — ', $label_parts);
        if ($label === '') $label = (string)lang('Address') . ' #' . ($i + 1);
        // Build the payload — values are exactly the keys the JS will use
        // to populate inputs by their `name` attribute.
        $payload = array(
            'billing_salutation'    => (string)$r['billing_salutation'],
            'billing_first_name'    => (string)$r['billing_first_name'],
            'billing_last_name'     => (string)$r['billing_last_name'],
            'billing_company'       => (string)$r['billing_company'],
            'billing_address_1'     => (string)$r['billing_address_1'],
            'billing_address_2'     => (string)$r['billing_address_2'],
            'billing_city'          => (string)$r['billing_city'],
            'billing_state'         => (string)$r['billing_state'],
            'billing_zip_code'      => (string)$r['billing_zip_code'],
            'billing_country'       => (string)$r['billing_country'],
            'billing_phone_number'  => (string)$r['billing_phone_number'],
            'billing_email_address' => (string)$r['billing_email_address'],
        );
        $opts .= '<option value="' . (int)$i . '" data-pgab=\''
              . htmlspecialchars(json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
              . '\'>' . h($label) . '</option>';
    }

    // The inline JS is wrapped in a self-registering one-shot so multiple
    // EO widgets on the same page don't bind twice. It populates inputs
    // by `name=` attribute, dispatches 'change' so any country-dependent
    // listener (state/region select) sees the update, and skips when the
    // visitor picks the empty default option.
    $js = ''
        . '<script>(function(){'
        . 'if(window.__pgEoAddressBookBound)return;window.__pgEoAddressBookBound=true;'
        . 'document.addEventListener("change",function(e){'
        . 'var sel=e.target;if(!sel||sel.getAttribute("data-pg-eo-address-book")!=="1")return;'
        . 'var opt=sel.options[sel.selectedIndex];if(!opt)return;'
        . 'var raw=opt.getAttribute("data-pgab");if(!raw)return;'
        . 'var data;try{data=JSON.parse(raw);}catch(_){return;}'
        . 'var form=sel.form||sel.closest("form");if(!form)return;'
        . 'Object.keys(data).forEach(function(k){'
        . 'var el=form.querySelector(\'[name="\'+k+\'"]\');'
        . 'if(!el)return;'
        . 'el.value=data[k];'
        . 'el.dispatchEvent(new Event("change",{bubbles:true}));'
        . '});'
        . '});'
        . '})();</script>';

    return '<div class="pg-eo-address-book mb-2">'
         . '<label class="form-label small text-muted">' . h(lang('Use a saved address')) . '</label>'
         . '<select data-pg-eo-address-book="1" class="form-select form-select-sm">' . $opts . '</select>'
         . '</div>'
         . $js;
}

// ── Terms-of-service section: real <input type=checkbox required> +
// label with a link that opens a Bootstrap modal.
//
// This is the fallback path, for a tree that carries no terms row of its
// own; the designer's default tree has one and the operator edits its
// wording on canvas. The wording here is therefore fixed and translated —
// cfg['terms_intro_text'] and cfg['terms_link_text'] used to override it
// from the property panel, but that panel section left with the modal, so
// the two keys had a reader and no writer and always read their default.
function _eo_render_terms_section($lf)
{
    // Returns ONLY the inline checkbox + label + link. The modal HTML is
    // emitted SEPARATELY via _eo_render_terms_modal_html() so it can live
    // OUTSIDE the <form> (Bootstrap modal backdrop misbehaves when the
    // modal element is nested inside a form — clicking the link locked the
    // page with a black overlay that never lifted).
    $link_text  = lang('the sales agreement and the terms of use');
    $intro      = lang('By completing my order I accept');
    $checked    = ($lf && (string)$lf->get_field_value('agree_terms') === '1') ? ' checked' : '';
    $modal_id   = 'pg-eo-terms-modal';

    // Same three pieces, in the same order, as the terms row of the
    // designer's default tree, so both render paths read identically.
    return '<div class="form-check pg-eo-terms mb-3">'
         .   '<input class="form-check-input" type="checkbox" name="agree_terms" id="agree_terms" value="1" required' . $checked . '>'
         .   '<label class="form-check-label small" for="agree_terms">'
         .     h($intro) . ' '
         .     '<a href="#" data-bs-toggle="modal" data-bs-target="#' . h($modal_id) . '">' . h($link_text) . '</a>'
         .     ' ' . h(lang('and confirm I have read them.'))
         .   '</label>'
         . '</div>';
}

// LEGACY-ONLY fallback. The terms modal is now a real Bootstrap modal
// in the designer tree — operator edits title/body/footer/dialog-size on
// canvas. This function is ONLY called when:
// ── Cancellation form HTML (order_view __cancel_form token) ─────────────
// Builds a POST form to cancel_order.php with CSRF token, hidden order_id,
// optional reason textarea, and a destructive submit button. The form's
// outer div carries `eo_visible_if='can_cancel'` so the visibility walker
// removes it from the tree when site policy / order state disallows cancel.
function _eo_render_cancel_order_form($order_id, $payment_method, $total_cents)
{
    $base_path = defined('PATH') ? PATH : '/';
    $sw_dir    = defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'software';
    $action    = $base_path . $sw_dir . '/cancel_order.php';
    $send_to   = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : $base_path;
    $token_field = function_exists('get_token_field') ? get_token_field() : '';
    // Show refund-required warning only when payment is via a real gateway
    // (Iyzipay / PayPal / Stripe). Offline payments don\'t need a refund —
    // operator returns funds via bank transfer, no automation needed.
    $needs_refund = ($total_cents > 0
                    && $payment_method !== ''
                    && $payment_method !== 'Offline Payment');
    $warning_html = $needs_refund
        ? '<div class="alert alert-warning small mb-3" role="alert">'
        .   '<i class="bi bi-exclamation-triangle me-1"></i>'
        .   h(lang('Payment refunds are processed manually. Please contact us for refund status.'))
        . '</div>'
        : '';
    // The form sits inside a card with a destructive accent border (Bootstrap
    // border-danger). On submit it confirms via inline JS (window.confirm)
    // before the POST goes through — visitor double-opt-in for a destructive
    // action. Reason field is optional + capped at 500 chars (server also
    // truncates defensively).
    $confirm_msg = (string)lang('Are you sure you want to cancel this order? This cannot be undone.');
    return
        '<form method="post" action="' . h($action) . '" class="pg-ov-cancel-form" '
      .   'data-pg-confirm-msg="' . h($confirm_msg) . '" '
      .   'onsubmit="return window.confirm(this.dataset.pgConfirmMsg);">'
      .   $token_field
      .   '<input type="hidden" name="order_id" value="' . (int)$order_id . '">'
      .   '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
      .   $warning_html
      .   '<div class="mb-3">'
      .     '<label for="pg-ov-cancel-reason-' . (int)$order_id . '" class="form-label small fw-semibold">'
      .       h(lang('Cancellation reason (optional)'))
      .     '</label>'
      .     '<textarea class="form-control" id="pg-ov-cancel-reason-' . (int)$order_id . '"'
      .       ' name="cancellation_reason" rows="2" maxlength="500"'
      .       ' placeholder="' . h(lang('Tell us why you\'re cancelling — helps us improve.')) . '"></textarea>'
      .   '</div>'
      .   '<button type="submit" class="btn btn-outline-danger">'
      .     '<i class="bi bi-x-circle me-1"></i>'
      .     h(lang('Cancel Order'))
      .   '</button>'
      . '</form>';
}

// ── Cancellation status alert (order_view __cancel_status token) ─────────
// Translates the ?cancelled=X query flag into an appropriate alert. Returns
// empty string when no flag → no alert renders. Designer drops
// ^^__cancel_status^^ at the top of the layout.
function _eo_render_cancel_status_alert($flash)
{
    if ($flash === '' || $flash === null) return '';
    switch ((string)$flash) {
        case '1':
            return '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                 .   '<i class="bi bi-check-circle me-1"></i>'
                 .   h(lang('Your order has been cancelled.'))
                 .   '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
                 . '</div>';
        case 'shipped':
            return '<div class="alert alert-warning alert-dismissible fade show" role="alert">'
                 .   '<i class="bi bi-truck me-1"></i>'
                 .   h(lang('This order can no longer be cancelled — it has already shipped.'))
                 .   '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
                 . '</div>';
        case 'already':
            return '<div class="alert alert-info alert-dismissible fade show" role="alert">'
                 .   '<i class="bi bi-info-circle me-1"></i>'
                 .   h(lang('This order was already cancelled.'))
                 .   '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
                 . '</div>';
    }
    return '';
}

// ── Order timeline HTML (order_view __timeline token) ────────────────────
// Renders a vertical Bootstrap 5 + Bootstrap-Icons timeline of order
// lifecycle events. Only events with a real timestamp are rendered — empty
// rows are skipped so a not-yet-shipped order never shows a hollow
// "Kargoya Verildi" stub. Each $events row:
//   [ 'icon' => 'bi-...', 'color' => 'success|info|warning|danger|secondary',
//     'label' => string,  'ts' => string (already formatted) ]
//
// The CSS lives inline (scoped to .pg-ov-timeline) so designers don't need
// to ship extra stylesheets; widget render output is self-contained.
function _eo_render_order_timeline($events)
{
    $events = array_values(array_filter((array)$events, function ($e) {
        return is_array($e) && !empty($e['ts']);
    }));
    if (count($events) === 0) return '';

    $css = '<style>'
        . '.pg-ov-timeline{list-style:none;padding:0;margin:0;position:relative}'
        . '.pg-ov-timeline::before{content:"";position:absolute;left:1.15rem;top:.5rem;bottom:.5rem;width:2px;background:#dee2e6}'
        . '.pg-ov-timeline-item{position:relative;padding:.25rem 0 1rem 3rem;min-height:2.5rem}'
        . '.pg-ov-timeline-item:last-child{padding-bottom:0}'
        . '.pg-ov-timeline-icon{position:absolute;left:0;top:0;width:2.3rem;height:2.3rem;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem}'
        . '.pg-ov-timeline-label{font-weight:600;line-height:1.2}'
        . '.pg-ov-timeline-ts{color:#6c757d;font-size:.875rem}'
        . '</style>';

    $html = $css . '<ul class="pg-ov-timeline">';
    foreach ($events as $e) {
        $icon  = isset($e['icon'])  ? (string)$e['icon']  : 'bi-circle-fill';
        $color = isset($e['color']) ? (string)$e['color'] : 'secondary';
        $label = isset($e['label']) ? (string)$e['label'] : '';
        $ts    = isset($e['ts'])    ? (string)$e['ts']    : '';
        $html .=
              '<li class="pg-ov-timeline-item">'
            .   '<span class="pg-ov-timeline-icon bg-' . h($color) . '">'
            .     '<i class="bi ' . h($icon) . '"></i>'
            .   '</span>'
            .   '<div class="pg-ov-timeline-label">' . h($label) . '</div>'
            .   '<div class="pg-ov-timeline-ts">' . h($ts) . '</div>'
            . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

//   (a) the monolithic Phase-1 layout is in use (no eo_* tree bindings), OR
//   (b) a saved tree references `#pg-eo-terms-modal` but doesn't actually
//       contain the modal element (very old trees pre-real-modal).
// In both cases we emit a sane default modal so the trigger link doesn't
// dangle. The cfg.terms_modal_title / cfg.terms_html system fields were
// removed from the property panel — only the bundled defaults run here.
function _eo_render_terms_modal_html($cfg = array(), $extracted_body = '')
{
    // Parameters kept for signature stability with any legacy call sites,
    // but the values are ignored — modal is fully designer-controlled now.
    $modal_title = lang('Order Terms');
    $modal_body  = _eo_default_terms_html();
    $modal_id    = 'pg-eo-terms-modal';
    return
        '<div class="modal fade" id="' . h($modal_id) . '" tabindex="-1" aria-labelledby="' . h($modal_id) . '-label" aria-hidden="true" data-pg-eo-terms-modal="1">'
      .   '<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">'
      .     '<div class="modal-content">'
      .       '<div class="modal-header">'
      .         '<h5 class="modal-title" id="' . h($modal_id) . '-label">' . h($modal_title) . '</h5>'
      .         '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>'
      .       '</div>'
      .       '<div class="modal-body pg-eo-terms-body">'
      .          $modal_body
      .       '</div>'
      .       '<div class="modal-footer">'
      .         '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . h(lang('Close')) . '</button>'
      .         '<button type="button" class="btn btn-primary pg-eo-terms-accept" data-bs-dismiss="modal" data-pg-eo-terms-accept="1">' . h(lang('I Have Read and Accept')) . '</button>'
      .       '</div>'
      .     '</div>'
      .   '</div>'
      . '</div>';
}

// Inline JS for the "I Have Read and Accept" button. Ticks the agree_terms
// checkbox and lets Bootstrap dismiss the modal via data-bs-dismiss. Matches
// both .pg-eo-terms-accept (legacy fallback) AND [data-pg-eo-terms-accept]
// (designer-tree default) so either hook works. Emitted once per page.
function _pg_eo_terms_accept_inline_js()
{
    if (defined('PG_EO_TERMS_ACCEPT_JS_EMITTED')) return '';
    define('PG_EO_TERMS_ACCEPT_JS_EMITTED', true);
    return <<<'HTML'
<script>
(function(){
    if (window.__pgEoTermsInit) return; window.__pgEoTermsInit = 1;
    document.addEventListener('click', function(e){
        var t = e.target;
        if (!t) return;
        var hit = (t.classList && t.classList.contains('pg-eo-terms-accept'))
                || (t.hasAttribute && t.hasAttribute('data-pg-eo-terms-accept'))
                || (t.closest && t.closest('[data-pg-eo-terms-accept]'));
        if (!hit) return;
        var cb = document.getElementById('agree_terms');
        if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', {bubbles:true})); }
    });
})();
</script>
HTML;
}

// Default terms-of-service HTML body used by the legacy fallback modal.
// New widgets render the modal from real tree nodes (h6/p inside modal-body)
// and never call this function.
function _eo_default_terms_html()
{
    return '<h6 class="mb-3">' . h(lang('1. General Terms')) . '</h6>'
         . '<p class="small">' . h(lang('By confirming your order you agree that the amount is charged with the payment method you chose and that the products/services are supplied to you by us.')) . '</p>'
         . '<h6 class="mb-3 mt-4">' . h(lang('2. Cancellation and Refund')) . '</h6>'
         . '<p class="small">' . h(lang('Digital products cannot be returned once downloaded. For physical products you have the right to withdraw within 14 days of delivery.')) . '</p>'
         . '<h6 class="mb-3 mt-4">' . h(lang('3. Delivery')) . '</h6>'
         . '<p class="small">' . h(lang('Delivery time depends on the carrier you choose and on your address. Your tracking number is emailed to you once the order is confirmed.')) . '</p>'
         . '<h6 class="mb-3 mt-4">' . h(lang('4. Protection of Personal Data')) . '</h6>'
         . '<p class="small">' . h(lang('The information you give while ordering is processed only to complete your order, to invoice it and to deliver it. Your rights under data protection law are reserved.')) . '</p>'
         . '<h6 class="mb-3 mt-4">' . h(lang('5. Contact')) . '</h6>'
         . '<p class="small">' . h(lang('You can reach us from our contact page with any question.')) . '</p>'
         . '<div class="alert alert-info small mt-4 mb-0">' . h(lang('This text can be customized by the operator:')) . ' <strong>' . h(lang('Designer → Express Order Settings → Terms Text (HTML)')) . '</strong>.</div>';
}

function _eo_render_installment_box($lf)
{
    $max = defined('ECOMMERCE_IYZIPAY_INSTALLMENT') ? (int)ECOMMERCE_IYZIPAY_INSTALLMENT : 0;
    $is_iyzipay = defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay';
    if (!$is_iyzipay || $max < 2) return ''; // operator hasn't opted in
    $saved = $lf ? (int)$lf->get_field_value('installment') : 0;
    if ($saved < 1) $saved = 1;
    $html =
        '<div class="pg-eo-installments mt-2" data-pg-eo-installments="1" data-pg-eo-max="' . (int)$max . '">'
      .   '<input type="hidden" name="installment" value="' . (int)$saved . '" class="pg-eo-installment-input">'
      .   '<div class="small text-muted pg-eo-installment-status">'
      .     h(lang('Enter your card number to view installment options.'))
      .   '</div>'
      .   '<div class="pg-eo-installment-meta small text-muted mb-2" style="display:none"></div>'
      .   '<div class="pg-eo-installment-list"></div>'
      . '</div>';
    // The fetcher JS lives in the monolithic _eo_render_payment_methods so it
    // ALSO needs to be emitted here for the bindings render path. Idempotent
    // — multiple installment containers on a page register only once.
    $api_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    if (defined('OUTPUT_SOFTWARE_DIRECTORY')) $api_path .= OUTPUT_SOFTWARE_DIRECTORY . '/';
    $api_url = $api_path . 'api.php';
    $html .= '<script>(function(){'
          . 'if(window.__pgEoInstInit)return;window.__pgEoInstInit=1;'
          . 'var apiUrl=' . json_encode($api_url) . ';'
          . 'function blockFor(inp){var f=inp.closest("form");if(!f)return null;return f.querySelector("[data-pg-eo-installments]");}'
          // The price the plans are asked for is the order total before any
          // plan: syncTotals() rewrites the total with a plan's increase, and
          // reading it back would ask for instalments on top of instalments
          // when the card number changes. Kept from the first read.
          . 'function currentPriceCents(form){'
          .   'if(form.__pgEoBaseCents)return form.__pgEoBaseCents;'
          .   'var totEl=form.parentNode.querySelector("[data-pg-eo-total-with-surcharge-cents],[data-pg-eo-total-cents]");'
          .   'var c=0;'
          .   'if(!totEl){var h=form.querySelector("input[name=\'total_with_surcharge\']")||form.querySelector("input[name=\'total\']");if(h){c=Math.round(parseFloat(h.value)*100);}}'
          .   'else{var v=totEl.getAttribute("data-pg-eo-total-with-surcharge-cents")||totEl.getAttribute("data-pg-eo-total-cents");c=parseInt(v||"0",10);}'
          .   'if(c>0)form.__pgEoBaseCents=c;return c;'
          . '}'
          // Cache last API response so the radio-change handler can pull the
          // chosen plan's totalPrice/increase WITHOUT re-fetching.
          . 'function render(block,data){'
          .   'block.__pgEoInstData=data;'
          .   'var status=block.querySelector(".pg-eo-installment-status");var meta=block.querySelector(".pg-eo-installment-meta");var list=block.querySelector(".pg-eo-installment-list");var hidden=block.querySelector(".pg-eo-installment-input");'
          .   'if(!data||data.status!=="success"||!data.installments||!data.installments.length){status.textContent=' . json_encode(lang('No installments available for this card.')) . ';list.innerHTML="";meta.style.display="none";return;}'
          .   'status.textContent="";var brand=[data.cardAssociation,data.cardFamilyName,data.bankName].filter(Boolean).join(" / ");meta.textContent=brand;meta.style.display=brand?"":"none";'
          .   'var cur=parseInt(hidden.value||"1",10);var html="<div class=\\"row g-2\\">";'
          .   'data.installments.forEach(function(o){var checked=(o.number===cur)?" checked":"";var label=o.number===1?' . json_encode(lang('Single payment')) . ':(o.number+"x "+(window.software_format_money?software_format_money(o.monthly,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.monthly)).toFixed(2)));var tot=(window.software_format_money?software_format_money(o.total,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.total)).toFixed(2));var inc=parseFloat(o.increase||"0")>0?(" <span class=\\"text-warning\\">+"+(window.software_format_money?software_format_money(o.increase,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.increase)).toFixed(2))+"</span>"):"";html+="<div class=\\"col-12 col-md-6\\"><label class=\\"d-flex justify-content-between align-items-center border rounded p-2 small mb-0\\" style=\\"cursor:pointer\\"><span><input type=\\"radio\\" class=\\"form-check-input me-2 pg-eo-installment-radio\\" name=\\"_pg_eo_installment_radio\\" value=\\""+o.number+"\\""+checked+">"+label+"</span><span class=\\"text-muted\\">"+tot+inc+"</span></label></div>";});html+="</div>";list.innerHTML=html;'
          // Sync the sidebar totals to the currently-selected installment so
          // the visitor sees the price they\'re actually paying.
          .   'syncTotals(block);'
          . '}'
          // Push the selected installment\'s fee into the visible totals
          // table + the hidden total_with_surcharge input. Without this, the
          // visitor saw 1x total but server charged Nx total → "tutar değişti"
          // rejection in the legacy total-change guard (which we also relax
          // server-side, but updating the UI is the proper UX fix).
          . _eo_installment_sync_js()
          . 'var lastBin="",aborter=null;'
          . 'function fetchInst(inp){var bin=(inp.value||"").replace(/[^0-9]/g,"").slice(0,6);var block=blockFor(inp);if(!block)return;if(bin.length<6){var s=block.querySelector(".pg-eo-installment-status");if(s)s.textContent=' . json_encode(lang('Enter your card number to view installment options.')) . ';block.querySelector(".pg-eo-installment-list").innerHTML="";block.querySelector(".pg-eo-installment-meta").style.display="none";lastBin="";return;}if(bin===lastBin)return;lastBin=bin;var price=(currentPriceCents(inp.closest("form"))/100).toFixed(2);if(aborter)aborter.abort();aborter=new AbortController();block.querySelector(".pg-eo-installment-status").textContent=' . json_encode(lang('Loading installment options…')) . ';var body=JSON.stringify({action:"eo_get_installments",card:bin,price:price});fetch(apiUrl,{method:"POST",headers:{"Content-Type":"application/json"},body:body,signal:aborter.signal,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){render(block,d);}).catch(function(e){if(e.name==="AbortError")return;var s=block.querySelector(".pg-eo-installment-status");if(s)s.textContent=' . json_encode(lang('Could not load installments.')) . ';});}'
          . 'var debTimer=null;'
          . 'document.addEventListener("input",function(e){var t=e.target;if(!t||t.id!=="card_number")return;clearTimeout(debTimer);debTimer=setTimeout(function(){fetchInst(t);},250);});'
          // Radio change → update hidden installment + re-sync sidebar totals.
          . 'document.addEventListener("change",function(e){var t=e.target;if(!t||t.name!=="_pg_eo_installment_radio")return;var block=t.closest("[data-pg-eo-installments]");if(!block)return;var hidden=block.querySelector(".pg-eo-installment-input");if(!hidden)return;hidden.value=t.value;syncTotals(block);});'
          . 'document.addEventListener("DOMContentLoaded",function(){var inp=document.getElementById("card_number");if(inp&&inp.value)fetchInst(inp);});'
          . '})();</script>';
    return $html;
}

function _eo_render_payment_methods($lf, $cfg)
{
    $methods = array();

    // Credit/Debit Card (umbrella for 8 sub-gateways)
    $cc_on = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == true;
    $any_card = (defined('ECOMMERCE_VISA') && ECOMMERCE_VISA == true)
             || (defined('ECOMMERCE_MASTERCARD') && ECOMMERCE_MASTERCARD == true)
             || (defined('ECOMMERCE_AMERICAN_EXPRESS') && ECOMMERCE_AMERICAN_EXPRESS === true)
             || (defined('ECOMMERCE_DINERS_CLUB') && ECOMMERCE_DINERS_CLUB == true)
             || (defined('ECOMMERCE_DISCOVER_CARD') && ECOMMERCE_DISCOVER_CARD == true)
             || (defined('ECOMMERCE_TROY') && ECOMMERCE_TROY == true);
    if ($cc_on && $any_card) $methods[] = 'Credit/Debit Card';

    // PayPal Express Checkout
    if (defined('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT') && ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT == true) {
        $methods[] = 'PayPal Express Checkout';
    }
    // Pay With Iyzico
    if (defined('ECOMMERCE_PAY_WITH_IYZICO') && ECOMMERCE_PAY_WITH_IYZICO == true) {
        $methods[] = 'Pay With Iyzico';
    }
    // Offline Payment
    if (defined('ECOMMERCE_OFFLINE_PAYMENT') && ECOMMERCE_OFFLINE_PAYMENT == true) {
        $methods[] = 'Offline Payment';
    }

    if (empty($methods)) {
        return '<div class="alert alert-warning mb-0">'
             . h(lang('No payment methods are configured. Please contact the site administrator.'))
             . '</div>';
    }

    // Pre-selection: persisted from previous submit, else the first available.
    $sel_method = $lf ? (string)$lf->get_field_value('payment_method') : '';
    if (!in_array($sel_method, $methods, true)) $sel_method = $methods[0];

    $only_one = count($methods) === 1;
    $html = '';

    // Method picker — when only one method, render heading; else radios.
    if (!$only_one) {
        $html .= '<div class="mb-3">';
        foreach ($methods as $m) {
            $id = 'pg_eo_pm_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($m));
            $html .= '<div class="form-check">'
                  . '<input class="form-check-input" type="radio" name="payment_method"'
                  .   ' id="' . h($id) . '" value="' . h($m) . '"'
                  .   ($m === $sel_method ? ' checked' : '') . '>'
                  . '<label class="form-check-label" for="' . h($id) . '">' . h(lang($m)) . '</label>'
                  . '</div>';
        }
        $html .= '</div>';
    } else {
        // Single-method case — emit a hidden input so the value still POSTs.
        $html .= '<input type="hidden" name="payment_method" value="' . h($methods[0]) . '">';
        $html .= '<div class="text-muted small mb-3">' . h(lang($methods[0])) . '</div>';
    }

    // Card fields — visible whenever Credit/Debit Card is the active or only
    // method. JS toggles the wrapper when the visitor switches radios; for
    // Phase 1 we keep it simple: always visible if CC is selected on render.
    if (in_array('Credit/Debit Card', $methods, true)) {
        $cc_visible = ($sel_method === 'Credit/Debit Card');
        $html .= '<div class="pg-eo-cc-fields" data-pg-eo-cc-fields="1"'
              .    ($cc_visible ? '' : ' style="display:none"') . '>';
        $cn = $lf ? (string)$lf->get_field_value('card_number') : '';
        $exp = $lf ? (string)$lf->get_field_value('expiration') : '';
        $cvv = $lf ? (string)$lf->get_field_value('card_verification_number') : '';
        // Accepted card brand badges — driven by the same Settings → E-Commerce
        // → Payment toggles that gate the umbrella "Credit/Debit Card" method.
        // Each enabled brand gets a small pill so the visitor knows which
        // cards the merchant accepts BEFORE they start typing the number.
        // Order matches settings.php form order; disabled brands are hidden.
        $card_brands = array();
        if (defined('ECOMMERCE_VISA')             && ECOMMERCE_VISA == true)             $card_brands[] = 'Visa';
        if (defined('ECOMMERCE_MASTERCARD')       && ECOMMERCE_MASTERCARD == true)       $card_brands[] = 'MasterCard';
        if (defined('ECOMMERCE_AMERICAN_EXPRESS') && ECOMMERCE_AMERICAN_EXPRESS === true) $card_brands[] = 'American Express';
        if (defined('ECOMMERCE_DINERS_CLUB')      && ECOMMERCE_DINERS_CLUB == true)      $card_brands[] = 'Diners Club';
        if (defined('ECOMMERCE_DISCOVER_CARD')    && ECOMMERCE_DISCOVER_CARD == true)    $card_brands[] = 'Discover';
        if (defined('ECOMMERCE_TROY')             && ECOMMERCE_TROY == true)             $card_brands[] = 'Troy';
        $brand_pills_html = '';
        if (!empty($card_brands)) {
            $brand_pills_html = '<div class="pg-eo-card-brands d-flex flex-wrap gap-2 mb-2">';
            foreach ($card_brands as $brand) {
                $brand_pills_html .=
                    '<span class="badge bg-light text-dark border" style="font-weight:500;font-size:.7rem">'
                  . '<i class="bi bi-credit-card me-1"></i>' . h($brand)
                  . '</span>';
            }
            $brand_pills_html .= '</div>';
        }

        $html .=
            $brand_pills_html
          . '<div class="row">'
          .   '<div class="col-12 mb-3">'
          .     '<label class="form-label small fw-semibold" for="card_number">' . h(lang('Card Number')) . ' <span class="text-danger">*</span></label>'
          .     '<input type="text" class="form-control" id="card_number" name="card_number" value="' . h($cn) . '" autocomplete="cc-number" inputmode="numeric" placeholder="•••• •••• •••• ••••">'
          .   '</div>'
          .   '<div class="col-6 mb-3">'
          .     '<label class="form-label small fw-semibold" for="expiration">' . h(lang('Expiration')) . ' <span class="text-danger">*</span></label>'
          .     '<input type="text" class="form-control" id="expiration" name="expiration" value="' . h($exp) . '" placeholder="AA / YY" autocomplete="cc-exp">'
          .   '</div>'
          .   '<div class="col-6 mb-3">'
          .     '<label class="form-label small fw-semibold" for="card_verification_number">' . h(lang('Security Code')) . ' <span class="text-danger">*</span></label>'
          .     '<input type="text" class="form-control" id="card_verification_number" name="card_verification_number" value="' . h($cvv) . '" autocomplete="cc-csc" inputmode="numeric" placeholder="CVC">'
          .   '</div>'
          . '</div>';

        // ── Installment selector (Iyzipay only) ─────────────────────────────
        // Visible only when:
        //   • payment_gateway is Iyzipay
        //   • ECOMMERCE_IYZIPAY_INSTALLMENT >= 2 (operator opted into installments)
        // The widget is empty on initial render — JS populates it AFTER the
        // visitor types the first 6 digits of card_number (the BIN), via an
        // AJAX call to api.php?action=eo_get_installments which Iyzipay then
        // pricing-tables for the issuing bank. The visitor picks a radio,
        // and the hidden `installment` input (= the chosen installmentNumber)
        // posts alongside the form. submit_order.php's existing Iyzipay
        // pipeline reads `installment` via $liveform->get_field_value() —
        // no server-side wiring needed beyond this.
        $max_installments = defined('ECOMMERCE_IYZIPAY_INSTALLMENT') ? (int)ECOMMERCE_IYZIPAY_INSTALLMENT : 0;
        $is_iyzipay_gateway = defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay';
        if ($is_iyzipay_gateway && $max_installments >= 2) {
            $saved_inst = $lf ? (int)$lf->get_field_value('installment') : 0;
            if ($saved_inst < 1) $saved_inst = 1;
            $html .=
                '<div class="pg-eo-installments mt-2" data-pg-eo-installments="1" data-pg-eo-max="' . (int)$max_installments . '">'
              .   '<input type="hidden" name="installment" value="' . (int)$saved_inst . '" class="pg-eo-installment-input">'
              .   '<div class="small text-muted pg-eo-installment-status">'
              .     h(lang('Enter your card number to view installment options.'))
              .   '</div>'
              .   '<div class="pg-eo-installment-meta small text-muted mb-2" style="display:none"></div>'
              .   '<div class="pg-eo-installment-list"></div>'
              . '</div>';
        }

        $html .= '</div>';

        // Tiny radio→cc-fields toggle JS. Idempotent global init so multiple
        // express order widgets on a page register only once. Same script
        // block also wires the installment fetcher (debounced BIN → AJAX →
        // radio list) when the operator has enabled Iyzipay installments.
        // ALSO toggles the `required` attribute on `[data-pg-cc-required]`
        // inputs so hidden CC fields don\'t silently block form submission
        // when the visitor picks a non-CC payment method (Offline / PayPal).
        /*
         * Initial-sync block handles the case where a radio is already
         * pre-selected (POST-back) or the form is restored from bfcache.
         */
        $html .= '<script>(function(){'
              .   'if(window.__pgEoCcInit)return;window.__pgEoCcInit=1;'
              .   'function syncRequired(cc,visible){'
              .     'cc.querySelectorAll("[data-pg-cc-required]").forEach(function(el){'
              .       'if(visible){el.setAttribute("required","");}'
              .       'else{el.removeAttribute("required");}'
              .     '});'
              .   '}'
              .   'document.addEventListener("change",function(e){'
              .     'var t=e.target;'
              .     'if(!t||t.name!=="payment_method")return;'
              .     'var f=t.closest("form");if(!f)return;'
              .     'var cc=f.querySelector("[data-pg-eo-cc-fields]");if(!cc)return;'
              .     'var visible=(t.value==="Credit/Debit Card");'
              .     'cc.style.display=visible?"":"none";'
              .     'syncRequired(cc,visible);'
              .   '});'
              .   'function initial(){'
              .     'document.querySelectorAll("form[data-pg-eo-form]").forEach(function(f){'
              .       'var cc=f.querySelector("[data-pg-eo-cc-fields]");if(!cc)return;'
              .       'var p=f.querySelector("input[name=\'payment_method\']:checked");'
              .       'var visible=p&&p.value==="Credit/Debit Card";'
              .       'cc.style.display=visible?"":"none";'
              .       'syncRequired(cc,visible);'
              .     '});'
              .   '}'
              .   'if(document.readyState==="loading"){'
              .     'document.addEventListener("DOMContentLoaded",initial);'
              .   '}else{initial();}'
              .   'window.addEventListener("pageshow",initial);'
              . '})();</script>';

        if ($is_iyzipay_gateway && $max_installments >= 2) {
            // Installment fetcher — debounced, scoped per-form so multiple
            // EO widgets on the same page don't clobber each other.
            $api_base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
            if (defined('OUTPUT_SOFTWARE_DIRECTORY')) {
                $api_base_path .= OUTPUT_SOFTWARE_DIRECTORY . '/';
            }
            $api_url = $api_base_path . 'api.php';
            $html .= '<script>(function(){'
                  .   'if(window.__pgEoInstInit)return;window.__pgEoInstInit=1;'
                  .   'var apiUrl=' . json_encode($api_url) . ';'
                  .   'var fmtIso=function(s){return s;};'
                  // Locate the installment block for a given card_number input
                  .   'function blockFor(inp){'
                  .     'var f=inp.closest("form");if(!f)return null;'
                  .     'return f.querySelector("[data-pg-eo-installments]");'
                  .   '}'
                  // Pull current "total" amount from the totals sidebar
                  // (data-pg-eo-total-with-surcharge-cents is rendered by
                  // _eo_render_order_totals when surcharge>0, otherwise we
                  // fall back to data-pg-eo-total-cents).
                  // Same basis as the other copy: the total before any plan.
                  .   'function currentPriceCents(form){'
                  .     'if(form.__pgEoBaseCents)return form.__pgEoBaseCents;'
                  .     'var totEl=form.parentNode.querySelector("[data-pg-eo-total-with-surcharge-cents],[data-pg-eo-total-cents]");'
                  .     'var c=0;'
                  .     'if(!totEl){'
                  .       'var h=form.querySelector("input[name=\'total_with_surcharge\']")||form.querySelector("input[name=\'total\']");'
                  .       'if(h){c=Math.round(parseFloat(h.value)*100);}'
                  .     '}else{'
                  .       'var v=totEl.getAttribute("data-pg-eo-total-with-surcharge-cents")||totEl.getAttribute("data-pg-eo-total-cents");'
                  .       'c=parseInt(v||"0",10);'
                  .     '}'
                  .     'if(c>0)form.__pgEoBaseCents=c;return c;'
                  .   '}'
                  . _eo_installment_sync_js()
                  // Render the installment radio list from the API response.
                  // Highlights the currently-saved installment if available.
                  .   'function render(block,data){'
                  .     'block.__pgEoInstData=data;'
                  .     'var status=block.querySelector(".pg-eo-installment-status");'
                  .     'var meta=block.querySelector(".pg-eo-installment-meta");'
                  .     'var list=block.querySelector(".pg-eo-installment-list");'
                  .     'var hidden=block.querySelector(".pg-eo-installment-input");'
                  .     'if(!data||data.status!=="success"||!data.installments||!data.installments.length){'
                  .       'status.textContent=' . json_encode(lang('No installments available for this card.')) . ';'
                  .       'list.innerHTML="";meta.style.display="none";return;'
                  .     '}'
                  .     'status.textContent="";'
                  .     'var brand=[data.cardAssociation,data.cardFamilyName,data.bankName].filter(Boolean).join(" / ");'
                  .     'meta.textContent=brand;meta.style.display=brand?"":"none";'
                  .     'var cur=parseInt(hidden.value||"1",10);'
                  .     'var html="<div class=\\"row g-2\\">";'
                  .     'data.installments.forEach(function(o){'
                  .       'var checked=(o.number===cur)?" checked":"";'
                  .       'var label=o.number===1?' . json_encode(lang('Single payment')) . ':(o.number+"x "+(window.software_format_money?software_format_money(o.monthly,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.monthly)).toFixed(2)));'
                  .       'var tot=(window.software_format_money?software_format_money(o.total,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.total)).toFixed(2));'
                  .       'var inc=parseFloat(o.increase||"0")>0?(" <span class=\\"text-warning\\">+"+(window.software_format_money?software_format_money(o.increase,(data.currency_symbol||"")):(data.currency_symbol||"")+(+(o.increase)).toFixed(2))+"</span>"):"";'
                  .       'html+="<div class=\\"col-12 col-md-6\\">"+'
                  .         '"<label class=\\"d-flex justify-content-between align-items-center border rounded p-2 small mb-0\\" style=\\"cursor:pointer\\">"+'
                  .         '"<span><input type=\\"radio\\" class=\\"form-check-input me-2 pg-eo-installment-radio\\" name=\\"_pg_eo_installment_radio\\" value=\\""+o.number+"\\""+checked+">"+label+"</span>"+'
                  .         '"<span class=\\"text-muted\\">"+tot+inc+"</span>"+'
                  .         '"</label></div>";'
                  .     '});'
                  .     'html+="</div>";'
                  .     'list.innerHTML=html;'
                  .   '}'
                  // Debounced fetch — fires when the BIN (first 6 digits) is
                  // settled. Aborts in-flight requests so a fast-typing user
                  // only triggers a single fetch per "complete BIN" state.
                  .   'var lastBin="",aborter=null;'
                  .   'function fetchInst(inp){'
                  .     'var bin=(inp.value||"").replace(/[^0-9]/g,"").slice(0,6);'
                  .     'var block=blockFor(inp);if(!block)return;'
                  .     'if(bin.length<6){'
                  .       'var s=block.querySelector(".pg-eo-installment-status");'
                  .       'if(s)s.textContent=' . json_encode(lang('Enter your card number to view installment options.')) . ';'
                  .       'block.querySelector(".pg-eo-installment-list").innerHTML="";'
                  .       'block.querySelector(".pg-eo-installment-meta").style.display="none";'
                  .       'lastBin="";return;'
                  .     '}'
                  .     'if(bin===lastBin)return;lastBin=bin;'
                  .     'var price=(currentPriceCents(inp.closest("form"))/100).toFixed(2);'
                  .     'if(aborter)aborter.abort();'
                  .     'aborter=new AbortController();'
                  .     'block.querySelector(".pg-eo-installment-status").textContent=' . json_encode(lang('Loading installment options…')) . ';'
                  // api.php parses the body via json_decode(php://input) — must
                  // send JSON, NOT form-encoded, or `action` stays null and the
                  // access-control gate returns "Invalid login".
                  .     'var body=JSON.stringify({action:"eo_get_installments",card:bin,price:price});'
                  .     'fetch(apiUrl,{method:"POST",headers:{"Content-Type":"application/json"},body:body,signal:aborter.signal,credentials:"same-origin"})'
                  .       '.then(function(r){return r.json();})'
                  .       '.then(function(d){render(block,d);})'
                  .       '.catch(function(e){if(e.name==="AbortError")return;'
                  .         'var s=block.querySelector(".pg-eo-installment-status");'
                  .         'if(s)s.textContent=' . json_encode(lang('Could not load installments.')) . ';});'
                  .   '}'
                  // Wire input handler + radio change.
                  .   'var debTimer=null;'
                  .   'document.addEventListener("input",function(e){'
                  .     'var t=e.target;if(!t||t.id!=="card_number")return;'
                  .     'clearTimeout(debTimer);debTimer=setTimeout(function(){fetchInst(t);},250);'
                  .   '});'
                  .   'document.addEventListener("change",function(e){'
                  .     'var t=e.target;if(!t||t.name!=="_pg_eo_installment_radio")return;'
                  .     'var block=t.closest("[data-pg-eo-installments]");if(!block)return;'
                  .     'var hidden=block.querySelector(".pg-eo-installment-input");if(!hidden)return;'
                  .     'hidden.value=t.value;'
                  .     'syncTotals(block);'
                  .   '});'
                  // Run once on page load in case the visitor's card_number
                  // was prefilled (e.g. coming back from a 3DS failure).
                  .   'document.addEventListener("DOMContentLoaded",function(){'
                  .     'var inp=document.getElementById("card_number");if(inp&&inp.value)fetchInst(inp);'
                  .   '});'
                  . '})();</script>';
        }
    }

    return $html;
}

// ── Helper: render order totals sidebar ──────────────────────────────────────
// Mirrors the legacy template's <table class="table"> totals block. Discount
// rows hidden when zero so digital-only orders don't show empty "Discount: $0"
// lines. The shipping row uses two spans (.pg-eo-ship-cost-formatted and
// .pg-eo-ship-row-empty) so the shipping JS (Phase 2) can update the value
// in-place when the visitor enters their address and a method is selected —
// no need to re-render the whole sidebar.
function _eo_render_order_totals($sub, $disc, $tax, $ship, $gc_disc, $total, $surcharge, $total_with_surcharge, $fmt, $needs_shipping)
{
    $row = function ($label, $value_html, $emphasis = false, $extra_attrs = '') {
        $cls = $emphasis ? ' class="fw-bold border-top"' : '';
        return '<tr' . $cls . $extra_attrs . '>'
             . '<th class="ps-0 fw-normal text-muted">' . h($label) . '</th>'
             . '<td class="text-end pe-0">' . $value_html . '</td>'
             . '</tr>';
    };

    $html = '<table class="table table-sm mb-3"><tbody>';
    $html .= $row(lang('Subtotal'), '<span class="pg-eo-subtotal-formatted">' . $fmt($sub) . '</span>');
    if ($disc > 0)    $html .= $row(lang('Discount'), '-' . $fmt($disc));
    if ($tax > 0)     $html .= $row(lang('Tax'), '<span class="pg-eo-tax-formatted">' . $fmt($tax) . '</span>');
    if ($needs_shipping) {
        // Empty when no method picked yet — the shipping JS injects the
        // value once the visitor's address resolves a method.
        $html .= $row(lang('Shipping'),
            '<span class="pg-eo-shipping-formatted">' . ($ship > 0 ? $fmt($ship) : '—') . '</span>');
    }
    if ($gc_disc > 0) $html .= $row(lang('Gift card'), '-' . $fmt($gc_disc));
    // Surcharge — only visible when site has surcharge configured AND the
    // CC payment method is selected. We render it always (with class hook)
    // and let the JS toggle visibility on payment_method change. Default
    // visible when CC is the active method (which submit_order.php charges).
    if ($surcharge > 0) {
        $html .= '<tr class="pg-eo-surcharge-row" data-pg-eo-surcharge-cents="' . (int)$surcharge . '">'
              .   '<th class="ps-0 fw-normal text-muted">' . h(lang('Card surcharge')) . '</th>'
              .   '<td class="text-end pe-0"><span class="pg-eo-surcharge-formatted">' . $fmt($surcharge) . '</span></td>'
              . '</tr>';
        // Total row uses the with-surcharge value when CC is the picked
        // method. JS will swap between $total and $total_with_surcharge
        // depending on payment_method radio state.
        $html .= '<tr class="fw-bold border-top">'
              .   '<th class="ps-0 fw-normal text-muted">' . h(lang('Total')) . '</th>'
              .   '<td class="text-end pe-0">'
              .     '<span class="pg-eo-total-formatted"'
              .         ' data-pg-eo-total-cents="' . (int)$total . '"'
              .         ' data-pg-eo-total-with-surcharge-cents="' . (int)$total_with_surcharge . '">'
              .         $fmt($total_with_surcharge)
              .     '</span>'
              .   '</td>'
              . '</tr>';
    } else {
        $html .= $row(lang('Total'), '<span class="pg-eo-total-formatted" data-pg-eo-total-cents="' . (int)$total . '">' . $fmt($total) . '</span>', true);
    }
    $html .= '</tbody></table>';
    return $html;
}

// ── Helper: prefill billing_* fields from saved sources ─────────────────────
// Source priority per field (legacy get_express_order.php:5229+ port):
//   1. Visitor's just-edited value (already in liveform session) → keep
//   2. orders.billing_<col> (last write from this session's order)
//   3. contacts.<col> mapped to billing_<col> (logged-in users only)
//   4. countries.default_selected for billing_country fallback
//
// `$lf->field_in_session()` is the gate — when the visitor has already
// touched a field this request, we don't clobber their value. Without this
// gate the prefill would constantly overwrite edits with stale order/contact
// data on every render.
function _eo_prefill_billing_fields($lf, $order_id, $ghost)
{
    if (!$lf || $order_id <= 0) return;

    // Pull the order row (always exists — initialize_order created it).
    $order = db_item(
        "SELECT billing_salutation, billing_first_name, billing_last_name,
                billing_company, billing_address_1, billing_address_2,
                billing_city, billing_state, billing_zip_code, billing_country,
                billing_phone_number, billing_fax_number, billing_email_address,
                custom_field_1, custom_field_2, opt_in, po_number, tax_exempt,
                referral_source_code
         FROM orders WHERE id = '" . (int)$order_id . "' LIMIT 1"
    );
    if (!is_array($order)) $order = array();

    // Pull contact row when logged in & not ghosting. `contacts` schema uses
    // `business_*` for address fields — they map to billing_* here.
    $contact = array();
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && !$ghost && defined('USER_CONTACT_ID') && USER_CONTACT_ID > 0) {
        $contact = db_item(
            "SELECT salutation, first_name, last_name, company,
                    business_address_1, business_address_2, business_city,
                    business_state, business_zip_code, business_country,
                    business_phone, business_fax, email_address,
                    lead_source, opt_in
             FROM contacts WHERE id = '" . (int)USER_CONTACT_ID . "' LIMIT 1"
        );
        if (!is_array($contact)) $contact = array();
    }

    // Field map: billing_<col> → [order_col, contact_col].
    // contact_col is null when there's no equivalent on contacts (city, state, etc.
    // use business_* — explicit mapping below).
    $map = array(
        'billing_salutation'    => array('billing_salutation',    'salutation'),
        'billing_first_name'    => array('billing_first_name',    'first_name'),
        'billing_last_name'     => array('billing_last_name',     'last_name'),
        'billing_company'       => array('billing_company',       'company'),
        'billing_address_1'     => array('billing_address_1',     'business_address_1'),
        'billing_address_2'     => array('billing_address_2',     'business_address_2'),
        'billing_city'          => array('billing_city',          'business_city'),
        'billing_state'         => array('billing_state',         'business_state'),
        'billing_zip_code'      => array('billing_zip_code',      'business_zip_code'),
        'billing_country'       => array('billing_country',       'business_country'),
        'billing_phone_number'  => array('billing_phone_number',  'business_phone'),
        'billing_fax_number'    => array('billing_fax_number',    'business_fax'),
        'billing_email_address' => array('billing_email_address', 'email_address'),
    );

    foreach ($map as $billing_field => $cols) {
        if ($lf->field_in_session($billing_field)) continue;  // visitor edited — keep
        list($order_col, $contact_col) = $cols;
        $val = '';
        if (!empty($order[$order_col])) {
            $val = (string)$order[$order_col];
        } elseif ($contact_col !== null && !empty($contact[$contact_col])) {
            $val = (string)$contact[$contact_col];
        }
        if ($val !== '') $lf->assign_field_value($billing_field, $val);
    }

    // billing_country fallback to site default when neither order nor contact
    // had a value. Default is the country flagged `default_selected = 1`.
    if (!$lf->field_in_session('billing_country')) {
        $cur = $lf->get_field_value('billing_country');
        if ($cur === '' || $cur === null) {
            $default_code = (string)db_value(
                "SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1"
            );
            if ($default_code !== '') $lf->assign_field_value('billing_country', $default_code);
        }
    }

    // Misc: PO number, custom fields, opt_in, tax_exempt, referral_source.
    // These piggy-back on the same `field_in_session` gate.
    foreach (array('custom_field_1', 'custom_field_2', 'po_number') as $f) {
        if ($lf->field_in_session($f)) continue;
        if (!empty($order[$f])) $lf->assign_field_value($f, (string)$order[$f]);
    }
    if (!$lf->field_in_session('referral_source')) {
        $rs = '';
        if (!empty($order['referral_source_code']))   $rs = (string)$order['referral_source_code'];
        elseif (!empty($contact['lead_source']))      $rs = (string)$contact['lead_source'];
        if ($rs !== '') $lf->assign_field_value('referral_source', $rs);
    }
    if (!$lf->field_in_session('opt_in')) {
        $lf->assign_field_value('opt_in', !empty($order['opt_in']) ? '1' : '');
    }
    if (!$lf->field_in_session('tax_exempt')) {
        $lf->assign_field_value('tax_exempt', !empty($order['tax_exempt']) ? '1' : '');
    }
    // update_contact: default ON for logged-in users (matches legacy intent).
    if (!$lf->field_in_session('update_contact')) {
        $session_pref = isset($_SESSION['software']['update_contact']) ? $_SESSION['software']['update_contact'] : null;
        if ($session_pref === false) {
            $lf->assign_field_value('update_contact', '');
        } else {
            $lf->assign_field_value('update_contact', '1');
        }
    }
}

// ── Helper: prefill shipping_<rid>_* fields from saved sources ──────────────
// Source priority (legacy get_express_order.php:289+ port):
//   1. Visitor's just-edited value (already in liveform) → keep
//   2. address_book row matching (user_id, ship_to_name)  ← PREFERRED
//   3. ship_tos columns from this order
//   4. countries.default_selected for country fallback
function _eo_prefill_shipping_fields($lf, $recipient, $ghost)
{
    if (!$lf || !is_array($recipient) || empty($recipient['id'])) return;
    $rid = (int)$recipient['id'];
    $prefix = 'shipping_' . $rid . '_';

    // Try address_book first (logged-in user matching this ship_to_name).
    $address_book = array();
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && !$ghost && defined('USER_ID') && USER_ID > 0
        && !empty($recipient['ship_to_name'])) {
        $address_book = db_item(
            "SELECT salutation, first_name, last_name, company,
                    address_1, address_2, city, state, zip_code, country,
                    address_type, phone_number
             FROM address_book
             WHERE user = '" . (int)USER_ID . "'
               AND ship_to_name = '" . e((string)$recipient['ship_to_name']) . "'
             LIMIT 1"
        );
        if (!is_array($address_book)) $address_book = array();
    }

    $source = $address_book ? $address_book : $recipient;

    $fields = array(
        'salutation', 'first_name', 'last_name', 'company',
        'address_1', 'address_2', 'city', 'state', 'zip_code',
        'country', 'address_type', 'phone_number'
    );
    foreach ($fields as $f) {
        $key = $prefix . $f;
        if ($lf->field_in_session($key)) continue;
        $val = isset($source[$f]) ? (string)$source[$f] : '';
        if ($val !== '') $lf->assign_field_value($key, $val);
    }

    // country fallback to site default
    if (!$lf->field_in_session($prefix . 'country')) {
        $cur = $lf->get_field_value($prefix . 'country');
        if ($cur === '' || $cur === null) {
            $default_code = (string)db_value(
                "SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1"
            );
            if ($default_code !== '') $lf->assign_field_value($prefix . 'country', $default_code);
        }
    }

    // Arrival date — pre-select existing arrival_date_id, else default-flagged
    if (!$lf->field_in_session($prefix . 'arrival_date')) {
        if (!empty($recipient['arrival_date_id'])) {
            $lf->assign_field_value($prefix . 'arrival_date', (string)$recipient['arrival_date_id']);
        } elseif (defined('ECOMMERCE_END_OF_DAY_TIME')) {
            $today = date('Y-m-d');
            $eod   = ECOMMERCE_END_OF_DAY_TIME;
            $aid = (string)db_value(
                "SELECT id FROM arrival_dates
                 WHERE status = 'enabled'
                   AND (start_date IS NULL OR start_date <= '$today')
                   AND (end_date IS NULL OR CONCAT(end_date, ' " . e($eod) . "') > NOW())
                   AND default_selected = 1
                 ORDER BY sort_order, arrival_date LIMIT 1"
            );
            if ($aid !== '' && $aid !== '0') {
                $lf->assign_field_value($prefix . 'arrival_date', $aid);
            }
        }
    }
}

// ── Helper: country <option> list keyed by countries.code (NOT id) ──────────
// CRITICAL: every shipping / billing path downstream — `validate_product_for_destination`,
// `get_shipping_methods`, `get_tax_rate_for_address`, `ship_tos.country` storage,
// `orders.billing_country` storage — works on `countries.code` ('TR', 'US', …),
// NOT `countries.id`. The default `select_country()` helper uses ID for value
// which silently breaks every shipping API call (cart says "this product can't
// be shipped to your address" because the validator looks up code='233' instead
// of code='TR'). This helper produces the correct value=CODE markup. Cached
// statically per request so a multi-recipient form doesn't re-query countries
// once per recipient.
function _eo_country_options($selected_code = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = db_items(
            "SELECT id, name, code, default_selected
             FROM countries
             ORDER BY name"
        );
        if (!is_array($cache)) $cache = array();
    }
    $sel_code = (string)$selected_code;
    // When no explicit selection, fall back to the country flagged
    // default_selected — same fallback `select_country()` uses, so the picker
    // is pre-populated with the site's default country.
    $default_code = '';
    foreach ($cache as $c) {
        if (!empty($c['default_selected'])) { $default_code = (string)$c['code']; break; }
    }
    if ($sel_code === '') $sel_code = $default_code;
    $out = '';
    foreach ($cache as $c) {
        $code = (string)$c['code'];
        $out .= '<option value="' . h($code) . '"' . ($code === $sel_code ? ' selected' : '') . '>'
              . h((string)$c['name'])
              . '</option>';
    }
    return $out;
}

// ── Helper: state <option> list keyed by states.code (NOT id) ───────────────
// Matches the country-code rationale above. `ship_tos.state` and
// `orders.billing_state` store state code (e.g. 'CA'); validators look it
// up via `states.code = '...'`. Optional country filter narrows the list
// to that country's states once we know which country was picked.
function _eo_state_options($selected_code = '', $country_code = '')
{
    $where = '';
    if ($country_code !== '') {
        $where = " WHERE countries.code = '" . e($country_code) . "'";
    }
    $rows = db_items(
        "SELECT states.code, states.name, countries.name AS country_name
         FROM states
         LEFT JOIN countries ON states.country_id = countries.id
         $where
         ORDER BY countries.name ASC, states.name ASC"
    );
    if (!is_array($rows)) return '';
    $sel = (string)$selected_code;
    $out = '<option value="">' . h(lang('Select...')) . '</option>';
    foreach ($rows as $r) {
        $code = (string)$r['code'];
        $label = $country_code === '' && !empty($r['country_name'])
                    ? ($r['country_name'] . ' — ' . $r['name'])
                    : (string)$r['name'];
        $out .= '<option value="' . h($code) . '"' . ($code === $sel ? ' selected' : '') . '>'
              . h($label)
              . '</option>';
    }
    return $out;
}

// ── Helper: render shipping section (multi-recipient, Phase 2) ───────────────
// One block per ship_tos row. Each block contains:
//   • Recipient heading (when multi-recipient mode)
//   • Address fields (salutation, first/last, company, address1/2, city,
//     country, state — dual text/select, zip, phone, address_type radio)
//   • Arrival date radio group (from arrival_dates table)
//   • Shipping method picker container — JS-populated via
//     /api.php?action=get_shipping_methods (the same endpoint frontend.src.js
//     uses for the legacy template)
//
// Field naming MUST match the legacy `shipping_<rid>_*` convention so the
// existing express_order.php handler accepts the values without changes.
// Helper: walk the tree to detect whether the designer placed REAL shipping
// address inputs (eo_field bindings on shipping_*). Used by the shipping
// section renderer to AVOID emitting duplicate address fields when the
// designer has them in the tree as real elements. Excludes the dynamic
// `shipping_method` / `shipping_arrival_date_id` fields since those are
// always server-rendered, never authored in the tree.
function _eo_tree_has_real_shipping_address_fields($tree)
{
    if (!is_array($tree)) return false;
    if (isset($tree['props']['_bindings']['eo_field'])) {
        $f = (string)$tree['props']['_bindings']['eo_field'];
        if (strpos($f, 'shipping_') === 0
            && !preg_match('/^shipping_\d+_/', $f)  // skip already-rewritten per-rid names
            && $f !== 'shipping_method'
            && $f !== 'shipping_arrival_date_id') {
            return true;
        }
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) {
            if (_eo_tree_has_real_shipping_address_fields($child)) return true;
        }
    }
    return false;
}

// $include_address_fields: when TRUE (default), renders the full address
// form (first_name, last_name, ..., country/state/zip/phone, address_type)
// followed by arrival-date + shipping-method picker — legacy behaviour for
// trees that don\'t have real shipping_* eo_field bindings as DOM elements.
// When FALSE, only the dynamic extras (arrival date radios + shipping
// method picker) are rendered — used when the tree already contains real
// address inputs so they\'re not duplicated.
function _eo_render_shipping_section($recipients, $lf, $form_id, $widget_id, $include_address_fields = true)
{
    $multi_mode = (defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE === 'multi-recipient');
    $count = count($recipients);

    // Pull active arrival dates ONCE for all recipients (date window + cutoff
    // logic mirrors the legacy template's filter at shipping_address_and_arrival.php:135).
    $arrival_dates = array();
    if (defined('ECOMMERCE_END_OF_DAY_TIME')) {
        // Legacy convention: only show dates whose start_date is on/before
        // today AND end_date is on/after today (or NULL = open-ended).
        $today_sql = date('Y-m-d');
        // `default_selected` is REQUIRED — the recipient-block render reads
        // it to honor the admin's chosen default radio (otherwise the form
        // shows whatever happens to be first in sort order, which the
        // operator may not have meant).
        $arrival_dates = db_items(
            "SELECT id, name, arrival_date, custom, custom_maximum_arrival_date, default_selected
             FROM arrival_dates
             WHERE status = 'enabled'
               AND (start_date IS NULL OR start_date <= '$today_sql')
               AND (end_date IS NULL OR end_date >= '$today_sql')
             ORDER BY sort_order ASC, id ASC"
        );
        if (!is_array($arrival_dates)) $arrival_dates = array();
    }

    // Country / state options come from the code-keyed helpers above. Using
    // ID-based options here would silently break shipping — see
    // `_eo_country_options()` comment for the full rationale.
    // Salutation options
    $sal_options = function_exists('get_salutation_options') ? get_salutation_options() : array('' => '');

    $out = '';
    foreach ($recipients as $idx => $r) {
        $rid = (int)$r['id'];
        // Pre-fill priority: liveform session value (visitor's most recent
        // edit) > ship_tos column (DB stored value).
        $get = function ($field) use ($lf, $r, $rid) {
            $key = 'shipping_' . $rid . '_' . $field;
            if ($lf) {
                $v = $lf->get_field_value($key);
                if ($v !== '' && $v !== null) return (string)$v;
            }
            return isset($r[$field]) ? (string)$r[$field] : '';
        };

        // Heading: recipient name when multi-recipient, generic otherwise.
        $heading = '';
        if ($multi_mode && $count > 1) {
            $rname = isset($r['ship_to_name']) && (string)$r['ship_to_name'] !== ''
                        ? (string)$r['ship_to_name'] : (string)lang('Recipient');
            $heading = '<h3 class="h6 text-muted mt-3 mb-2">'
                     . h(lang('Shipping address')) . ' — ' . h($rname)
                     . '</h3>';
        }

        // Address block
        $sal_html = '';
        $sel_sal = $get('salutation');
        foreach ($sal_options as $val => $label) {
            $sal_html .= '<option value="' . h($val) . '"' . ($val === $sel_sal ? ' selected' : '') . '>' . h($label) . '</option>';
        }
        // Country options use CODE as value (matches ship_tos.country storage
        // + every shipping/tax validator downstream).
        $countries_with_selected = _eo_country_options($get('country'));

        $addr_type = $get('address_type');
        if (!in_array($addr_type, array('residential', 'business'), true)) $addr_type = 'residential';

        $row = function ($label, $field, $type = 'text', $required = false, $col = '6') use ($rid, $get) {
            $name = 'shipping_' . $rid . '_' . $field;
            $req = $required ? ' <span class="text-danger">*</span>' : '';
            $req_attr = $required ? ' required' : '';
            return '<div class="col-12 col-md-' . h($col) . ' mb-3">'
                 . '<label class="form-label small fw-semibold" for="' . h($name) . '">' . h($label) . $req . '</label>'
                 . '<input type="' . h($type) . '" class="form-control" id="' . h($name) . '" name="' . h($name) . '" value="' . h($get($field)) . '"' . $req_attr . '>'
                 . '</div>';
        };

        $out .= '<div class="pg-eo-recipient mb-4 p-3 border rounded"'
              .  ' data-pg-eo-recipient="' . $rid . '"'
              .  ' data-pg-eo-rid="' . $rid . '">';
        $out .= $heading;

        // ── Address fields (conditional) ─────────────────────────────────
        // When the designer placed real shipping_* inputs in the tree
        // (eo_field bindings), skip this block to avoid duplicate fields.
        // Country select / state field / zip / phone / address_type are all
        // either editable on canvas or covered by the country dropdown\'s
        // server-injected option list.
        if ($include_address_fields) {
            // Salutation (narrow)
            $out .= '<div class="row">';
            $out .= '<div class="col-12 col-md-3 mb-3">'
                  . '<label class="form-label small fw-semibold" for="shipping_' . $rid . '_salutation">' . h(lang('Salutation')) . '</label>'
                  . '<select class="form-select" id="shipping_' . $rid . '_salutation" name="shipping_' . $rid . '_salutation">' . $sal_html . '</select>'
                  . '</div><div class="col-md-9"></div>';
            $out .= $row(lang('First Name'), 'first_name', 'text', true);
            $out .= $row(lang('Last Name'),  'last_name',  'text', true);
            $out .= $row(lang('Company'),    'company',    'text', false);
            $out .= $row(lang('Address 1'),  'address_1',  'text', true);
            $out .= $row(lang('Address 2'),  'address_2',  'text', false);
            $out .= $row(lang('City'),       'city',       'text', true);

            // Country select
            $out .= '<div class="col-12 col-md-6 mb-3">'
                  . '<label class="form-label small fw-semibold" for="shipping_' . $rid . '_country">' . h(lang('Country')) . ' <span class="text-danger">*</span></label>'
                  . '<select class="form-select pg-eo-country" id="shipping_' . $rid . '_country" name="shipping_' . $rid . '_country" required data-pg-eo-rid="' . $rid . '">'
                  . '<option value="">' . h(lang('Select...')) . '</option>'
                  . $countries_with_selected
                  . '</select>'
                  . '</div>';

            // State — dual input pattern. Same `name` on both; JS toggles which
            // is visible based on selected country (text for countries without
            // states in DB, select otherwise). Both inputs store state CODE
            // (e.g. 'CA') — this is what `validate_product_for_destination` and
            // `get_tax_rate_for_address` look up against `states.code`.
            $sel_state_code = $get('state');
            $state_sel_html = _eo_state_options($sel_state_code, '');  // all countries; JS narrows when country picked
            $out .= '<div class="col-12 col-md-6 mb-3">'
                  . '<label class="form-label small fw-semibold" for="shipping_' . $rid . '_state_text_box">' . h(lang('State / Province')) . '</label>'
                  . '<input type="text" class="form-control pg-eo-state-text" id="shipping_' . $rid . '_state_text_box" name="shipping_' . $rid . '_state" value="' . h($sel_state_code) . '">'
                  . '<select class="form-select pg-eo-state-select" id="shipping_' . $rid . '_state_pick_list" name="shipping_' . $rid . '_state" style="display:none" disabled>' . $state_sel_html . '</select>'
                  . '</div>';

            // Zip + phone
            $out .= '<div class="col-12 col-md-6 mb-3">'
                  . '<label class="form-label small fw-semibold" for="shipping_' . $rid . '_zip_code">' . h(lang('Zip / Postal Code'))
                  . ' <span class="text-danger pg-eo-zip-required" data-pg-eo-rid="' . $rid . '">*</span>'
                  . '</label>'
                  . '<input type="text" class="form-control" id="shipping_' . $rid . '_zip_code" name="shipping_' . $rid . '_zip_code" value="' . h($get('zip_code')) . '">'
                  . '</div>';
            $out .= $row(lang('Phone'), 'phone_number', 'tel', false);

            // Address type
            $out .= '<div class="col-12 mb-3">'
                  . '<label class="form-label small fw-semibold d-block">' . h(lang('Address Type')) . '</label>'
                  . '<div class="form-check form-check-inline">'
                  .   '<input class="form-check-input" type="radio" name="shipping_' . $rid . '_address_type" id="shipping_' . $rid . '_address_type_res" value="residential"' . ($addr_type === 'residential' ? ' checked' : '') . '>'
                  .   '<label class="form-check-label" for="shipping_' . $rid . '_address_type_res">' . h(lang('Residential')) . '</label>'
                  . '</div>'
                  . '<div class="form-check form-check-inline">'
                  .   '<input class="form-check-input" type="radio" name="shipping_' . $rid . '_address_type" id="shipping_' . $rid . '_address_type_biz" value="business"' . ($addr_type === 'business' ? ' checked' : '') . '>'
                  .   '<label class="form-check-label" for="shipping_' . $rid . '_address_type_biz">' . h(lang('Business')) . '</label>'
                  . '</div>'
                  . '</div>';

            $out .= '</div>'; // /row
        } else {
            // When address fields are SKIPPED, emit a hidden address_type
            // input set to 'residential' so the server-side validator (which
            // checks ship_tos.address_type) gets a sensible default. The
            // shipping-method calculator JS reads address_type for some
            // carriers (residential vs business surcharges).
            $out .= '<input type="hidden" name="shipping_' . $rid . '_address_type" value="' . ($addr_type ?: 'residential') . '">';
            // Heading (only when not already shown above)
            if ($heading === '') {
                $out .= '<div class="text-muted small mb-2">'
                      . '<i class="bi bi-truck me-1"></i>'
                      . h(lang('Shipping method')) . '</div>';
            }
        }

        // Arrival date radios
        if (!empty($arrival_dates)) {
            $sel_aid = $get('arrival_date_id');  // numeric arrival_dates.id
            if ($sel_aid === '' || $sel_aid === '0') {
                // Pick the row admin marked default_selected. The old
                // implementation `foreach { $sel = id; if (default) break; }`
                // always landed on the LAST row when nothing was marked default
                // — matching "Talep Edilen Varış Tarihi" (SDATE) on stock
                // installs even though "AT-ONCE" is the admin's choice.
                // Now: scan for the explicit default_selected row first; if
                // none found, fall back to the FIRST row (sorted by sort_order
                // when the query loaded $arrival_dates).
                $sel_aid = '';
                foreach ($arrival_dates as $ad) {
                    if (!empty($ad['default_selected'])) {
                        $sel_aid = (string)$ad['id'];
                        break;
                    }
                }
                if ($sel_aid === '' && !empty($arrival_dates)) {
                    $sel_aid = (string)$arrival_dates[0]['id'];
                }
            }
            $out .= '<div class="mb-3">'
                  . '<label class="form-label small fw-semibold d-block">' . h(lang('Requested Arrival Date')) . '</label>';
            foreach ($arrival_dates as $ad) {
                $aid     = (int)$ad['id'];
                $is_sel  = ((string)$aid === (string)$sel_aid);
                $name_id = 'shipping_' . $rid . '_arrival_date_' . $aid;
                $is_custom = !empty($ad['custom']);
                $out .= '<div class="form-check">'
                      . '<input class="form-check-input pg-eo-arrival-radio" type="radio"'
                      .   ' name="shipping_' . $rid . '_arrival_date" id="' . h($name_id) . '" value="' . $aid . '"'
                      .   ' data-pg-eo-rid="' . $rid . '"'
                      .   ($is_sel ? ' checked' : '') . '>'
                      . '<label class="form-check-label" for="' . h($name_id) . '">' . h($ad['name']) . '</label>'
                      . '</div>';
                if ($is_custom) {
                    $cd_name = 'shipping_' . $rid . '_custom_arrival_date_' . $aid;
                    $cd_val  = $get('custom_arrival_date_' . $aid);
                    $cd_max  = isset($ad['custom_maximum_arrival_date']) ? (string)$ad['custom_maximum_arrival_date'] : '';
                    $out .= '<div class="ms-4 mt-1 mb-2 pg-eo-custom-arrival" data-pg-eo-arrival-id="' . $aid . '"'
                          .   ($is_sel ? '' : ' style="display:none"') . '>'
                          . '<input type="date" class="form-control form-control-sm" name="' . h($cd_name) . '" value="' . h($cd_val) . '"'
                          .   ($cd_max !== '' ? ' max="' . h($cd_max) . '"' : '') . '>'
                          . '</div>';
                }
            }
            $out .= '</div>';
        }

        // Shipping method picker — JS-populated container. A recipient with
        // no method yet carries 0 in ship_tos; posting that 0 back satisfied
        // the handler's "select a shipping method" check, so an order could
        // go through with no method and no shipping charge whenever the
        // script had not filled the choice in. Empty until one is picked.
        $_eo_method_val = $get('shipping_method_id');
        if ((int)$_eo_method_val <= 0) $_eo_method_val = '';
        // A liveform value from the last submit wins over the stored one.
        $_eo_posted_method = $get('method');
        if ((int)$_eo_posted_method > 0) $_eo_method_val = $_eo_posted_method;

        // Without the script nothing fills the list below, and no method can
        // be picked. When the country is already known (an earlier submit,
        // the address book) the methods are worked out here, the same way
        // api.php does for the script, and offered as plain radios that post
        // the method themselves. The script adopts them (renames them and
        // posts through the hidden input, which starts disabled for that
        // reason) and redraws the list whenever the address changes.
        $_eo_status_text = lang('Enter address to see options.');
        $_eo_server_list = '';
        $_eo_country     = $get('country');
        if ($_eo_country !== '') {
            if (!function_exists('get_shipping_methods')) {
                require_once(PG_FUNCTIONS_DIR . '/shipping.php');
            }
            $_eo_aid  = isset($sel_aid) ? (string)$sel_aid : '';
            $_eo_resp = get_shipping_methods(array(
                'ship_to_id'      => $rid,
                'address_1'       => $get('address_1'),
                'state'           => $get('state'),
                'zip_code'        => $get('zip_code'),
                'country'         => $_eo_country,
                'arrival_date_id' => $_eo_aid,
                'arrival_date'    => ($_eo_aid !== '') ? $get('custom_arrival_date_' . $_eo_aid) : '',
            ));
            if (is_array($_eo_resp) && ($_eo_resp['status'] ?? '') === 'success' && !empty($_eo_resp['shipping_methods'])) {
                $_eo_methods = array_values($_eo_resp['shipping_methods']);
                // The script's rule: the previous pick while it is still on
                // offer, otherwise the cheapest (the list comes sorted by cost).
                $_eo_pick = (string)$_eo_methods[0]['id'];
                foreach ($_eo_methods as $_m) {
                    if ((string)$_m['id'] === (string)$_eo_method_val) { $_eo_pick = (string)$_m['id']; break; }
                }
                foreach ($_eo_methods as $_m) {
                    $_mid  = (string)$_m['id'];
                    $_mdom = 'pg_eo_ship_' . $rid . '_' . $_mid;
                    $_eo_server_list .= '<div class="form-check d-flex align-items-center justify-content-between border-bottom py-2">'
                        . '<div>'
                        .   '<input class="form-check-input pg-eo-method-radio" type="radio" name="shipping_' . $rid . '_method"'
                        .     ' id="' . h($_mdom) . '" value="' . h($_mid) . '" data-pg-eo-rid="' . $rid . '"'
                        .     ' data-pg-eo-cost="' . h((string)(float)$_m['cost']) . '"'
                        .     ($_mid === $_eo_pick ? ' checked' : '') . '>'
                        .   '<label class="form-check-label ms-2" for="' . h($_mdom) . '">'
                        .     '<strong>' . h((string)$_m['name']) . '</strong>'
                        .     ((string)$_m['description'] !== '' ? '<div class="small text-muted">' . h((string)$_m['description']) . '</div>' : '')
                        .   '</label>'
                        . '</div>'
                        . '<div class="ms-3 fw-semibold">' . (((float)$_m['cost'] == 0) ? h(lang('Free')) : $_m['cost_info']) . '</div>'
                        . '</div>';
                }
            } elseif (is_array($_eo_resp) && !empty($_eo_resp['message'])) {
                $_eo_status_text = (string)$_eo_resp['message'];
            }
        }
        $out .= '<div class="pg-eo-shipping-methods mt-3" data-pg-eo-rid="' . $rid . '">'
              . '<label class="form-label small fw-semibold d-block">' . h(lang('Shipping Method')) . '</label>'
              . '<div class="text-muted small pg-eo-methods-status"' . ($_eo_server_list !== '' ? ' style="display:none"' : '') . '>' . h($_eo_status_text) . '</div>'
              . '<div class="pg-eo-methods-list mt-2">' . $_eo_server_list . '</div>'
              . '<input type="hidden" name="shipping_' . $rid . '_method" value="' . h($_eo_method_val) . '" class="pg-eo-method-input"'
              .   ($_eo_server_list !== '' ? ' disabled' : '') . '>'
              . '</div>';

        $out .= '</div>'; // /pg-eo-recipient
    }

    return $out;
}

// ── Helper: emit per-widget JS (shipping calculator + address toggles) ──────
// Two responsibilities:
//   1. Country-aware state field swap (text vs select) + zip required asterisk
//   2. Shipping method calculator: debounced fetch of /api.php?action=get_shipping_methods
//      whenever the visitor finishes editing address fields. Renders methods
//      as radio buttons; on selection, updates the hidden shipping_<rid>_method
//      input AND the sidebar shipping cost in-place.
//
// $recipients is passed so the JS knows the rids it needs to wire.
function _eo_render_widget_js($widget_id, $form_id, $needs_shipping, $recipients, $base_path)
{
    // Always emit at minimum the gift-card delivery-date converter — gift
    // cards can be in the cart even on a "digital order" (no ship_tos),
    // and `validate_date()` rejects HTML5 ISO dates so the visitor would
    // hit "Delivery Date must contain a valid date" without the conversion.
    // Shipping-related JS is conditional on $needs_shipping below.
    $api_url = $base_path
             . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
             . '/api.php';
    $rids = array();
    if ($needs_shipping && is_array($recipients)) {
        foreach ($recipients as $r) $rids[] = (int)$r['id'];
    }
    $rids_json = json_encode($rids);
    $api_url_json = json_encode($api_url);
    $form_id_json = json_encode($form_id);

    // DATE_FORMAT — site-wide setting that drives `validate_date()`'s
    // expectation. The HTML5 `<input type="date">` always returns ISO
    // `YYYY-MM-DD`; the server's date validator wants `DD/MM/YYYY` (when
    // DATE_FORMAT='day_month') or `MM/DD/YYYY` (month_day). The JS converts
    // before POSTing — without this the API rejects every custom arrival
    // date with "Sorry, the requested arrival date is not valid".
    $date_format_json = json_encode(defined('DATE_FORMAT') ? DATE_FORMAT : 'month_day');

    // Pre-translated labels — passed as JSON-encoded strings into the JS
    // template. Keeping the lang() calls server-side means visitors get the
    // localized text without bloating the JS with a translate() helper.
    $_eo_label_enter_addr   = json_encode((string)lang('Select country to see shipping options.'));
    $_eo_label_calculating  = json_encode((string)lang('Calculating shipping…'));
    $_eo_label_no_methods   = json_encode((string)lang('No shipping methods available for this address.'));
    $_eo_label_network_err  = json_encode((string)lang('Network error — please retry.'));
    $_eo_label_free         = json_encode((string)lang('Free'));
    $_eo_label_pick_date    = json_encode((string)lang('Please pick an arrival date.'));
    $_eo_label_exp_month    = json_encode((string)lang('The expiration month must be between 01 and 12.'));
    $_eo_label_exp_past     = json_encode((string)lang('The card expiry date has already passed.'));

    // Inline script. Idempotent — multiple express_order widgets on the
    // same page register only one global init via window.__pgEoInit.
    return <<<HTML
<script>
(function(){
    var FORM_ID     = {$form_id_json};
    var API_URL     = {$api_url_json};
    var RIDS        = {$rids_json};
    var DATE_FORMAT = {$date_format_json};
    var form = document.getElementById(FORM_ID);
    if (!form) return;

    // Convert HTML5 <input type="date"> ISO value (YYYY-MM-DD) into the
    // format `validate_date()` accepts (DD/MM/YYYY for day_month sites,
    // MM/DD/YYYY for month_day sites). The PHP `prepare_form_data_for_input`
    // helper later normalizes this back to ISO for storage.
    function isoToServerDate(iso) {
        if (!iso) return '';
        var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) return iso;  // not ISO — pass through unchanged
        return (DATE_FORMAT === 'month_day')
            ? (m[2] + '/' + m[3] + '/' + m[1])
            : (m[3] + '/' + m[2] + '/' + m[1]);
    }

    // ── Form submit interceptor: date format + gift-card email auto-fill ───
    // Two responsibilities at submit time:
    //   1. Convert every <input type="date"> from ISO YYYY-MM-DD to the
    //      server's DATE_FORMAT (DD/MM/YYYY for day_month sites). Same fix
    //      as shipping arrival_date — `validate_date()` regex doesn't accept
    //      ISO; without this the server reports "Delivery Date must contain
    //      a valid date" or the analogous arrival-date error.
    //   2. For gift-card products that ALSO have a custom form (CASE 3 in
    //      _eo_render_cart_summary), auto-fill the hidden
    //      `gift_card_recipient_email_address` from the billing email so
    //      `express_order.php`'s validation passes WITHOUT showing a
    //      duplicate "Recipient Email" field on top of the product form.
    form.addEventListener('submit', function () {
        form.querySelectorAll('input[type="date"]').forEach(function (inp) {
            if (!inp.value) return;
            inp.value = isoToServerDate(inp.value);
        });
        // Gift-card hidden recipient_email auto-fill from billing email.
        var billingEmail = (form.querySelector('[name="billing_email_address"]') || {}).value || '';
        if (billingEmail) {
            form.querySelectorAll('input[type="hidden"][name$="_gift_card_recipient_email_address"]').forEach(function (h) {
                if (!h.value) h.value = billingEmail;
            });
        }
    });

    // ── Card field input masks ─────────────────────────────────────────────
    // Three separate problems, one place to fix them:
    //   • Card number typed as one run of digits is unreadable and easy to
    //     mistype — group it 4-4-4-4. The server strips non-digits
    //     (submit_order.php: preg_replace('/[^0-9]/','',…)) so the spaces
    //     never reach the gateway.
    //   • Expiration must arrive as MM/YY — the server splits it on '/'
    //     (explode('/', …)) and rejects the value outright when the slash is
    //     missing. Typing "12" and moving on produced "The expiration is not
    //     valid." with nothing on screen explaining why, so the slash is
    //     inserted as soon as the month is complete.
    //   • Letters are meaningless in all three fields and only surface as a
    //     validation error two screens later. Filtered on the way in.
    //
    // inputmode/autocomplete are set here rather than in the tree so existing
    // saved trees benefit without being re-authored — mobile keyboards open
    // on the numeric pad and browsers offer stored card details.
    function pgFormatCardNumber(digits) {
        // Amex is 4-6-5, everything else 4-4-4-4. Detected from the first two
        // digits (34/37) the same way the server-side brand check does.
        var isAmex = /^3[47]/.test(digits);
        var groups = isAmex ? [4, 6, 5] : [4, 4, 4, 4];
        var out = [], pos = 0;
        for (var i = 0; i < groups.length && pos < digits.length; i++) {
            out.push(digits.substr(pos, groups[i]));
            pos += groups[i];
        }
        if (pos < digits.length) out.push(digits.substr(pos));
        return out.join(' ');
    }

    // Re-place the caret after reformatting so typing mid-string doesn't jump
    // the cursor to the end (the classic input-mask annoyance).
    function pgApplyMask(el, formatter, maxDigits) {
        function run(ev) {
            // Backspace must be able to delete a separator the mask just
            // added, otherwise the field traps the caret: remove the '/' and
            // the mask instantly puts it back. Formatters receive this flag so
            // they can skip APPENDING a trailing separator while deleting.
            var deleting = !!(ev && ev.inputType && ev.inputType.indexOf('delete') === 0);
            var before = el.value;
            var caret  = el.selectionStart;
            var digitsBeforeCaret = (before.slice(0, caret).match(/\d/g) || []).length;
            var digits = before.replace(/\D/g, '').slice(0, maxDigits);
            var after  = formatter(digits, deleting);
            if (after === before) return;
            el.value = after;
            // Walk forward until we've passed the same number of digits.
            var seen = 0, newCaret = after.length;
            for (var i = 0; i < after.length; i++) {
                if (seen >= digitsBeforeCaret) { newCaret = i; break; }
                if (/\d/.test(after[i])) seen++;
            }
            // Never park the caret on a separator the mask inserted — step
            // over it so the next keystroke lands on the following digit slot.
            while (!deleting && newCaret < after.length && !/\d/.test(after.charAt(newCaret))) newCaret++;
            try { el.setSelectionRange(newCaret, newCaret); } catch (e) {}
        }
        el.addEventListener('input', run);
        el.addEventListener('blur', function () { run(); });
        run();   // normalize any value restored after a failed submit
    }

    var pgCardNo = form.querySelector('[name="card_number"]');
    if (pgCardNo) {
        pgCardNo.setAttribute('inputmode', 'numeric');
        pgCardNo.setAttribute('autocomplete', 'cc-number');
        // maxlength is set to the SEPARATED length (19 digits + 4 spaces).
        // Any maxlength authored for the raw digit count would silently eat
        // the last digits once the mask inserts spaces — the field would look
        // like it just refuses the final keystroke.
        pgCardNo.setAttribute('maxlength', '23');
        pgApplyMask(pgCardNo, pgFormatCardNumber, 19);
    }

    var pgExp = form.querySelector('[name="expiration"]');
    if (pgExp) {
        pgExp.setAttribute('inputmode', 'numeric');
        pgExp.setAttribute('autocomplete', 'cc-exp');
        pgExp.setAttribute('placeholder', 'AA/YY');
        pgExp.setAttribute('maxlength', '7');   // MM/YYYY
        pgApplyMask(pgExp, function (d, deleting) {
            // A leading digit above 1 can only be a single-digit month
            // ("3" → "03"), so the month completes without a second keystroke.
            if (d.length === 1 && d > '1') d = '0' + d;
            if (d.length < 2) return d;
            // Slash appears the moment the month is complete — typing "12"
            // shows "12/" and the year can be typed straight after, which is
            // what the visitor expects and what the server needs (it splits
            // the value on '/'). Suppressed while deleting so backspace can
            // walk back through the separator.
            if (d.length === 2) return deleting ? d : d + '/';
            // Year length follows what the visitor started typing: "20…"
            // means they're writing the century out (2026), anything else is
            // the short form (26). Without this the field kept accepting
            // digits after "12/26" and turned a finished value back into a
            // half-typed one.
            var year = d.slice(2);
            var maxYear = (year.slice(0, 2) === '20') ? 4 : 2;
            return d.slice(0, 2) + '/' + year.slice(0, maxYear);
        }, 6);

        // Minimum expiry = the current month. The server rejects past dates
        // too, but only after a full round trip — flagging it here means the
        // browser refuses to submit and points at the field.
        // setCustomValidity is cleared on every keystroke so the message can
        // never outlive the value that caused it.
        pgExp.addEventListener('input', function () { pgExp.setCustomValidity(''); });
        // Also drop the message whenever the visitor moves off card payment.
        // A custom validity message keeps blocking submit even on a HIDDEN
        // field, and the browser then reports "an invalid form control is not
        // focusable" with no visible cause — the exact failure mode the
        // `data-pg-cc-required` dance elsewhere in this widget exists to avoid.
        form.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'payment_method') pgExp.setCustomValidity('');
        });
        pgExp.addEventListener('blur', function () {
            pgExp.setCustomValidity('');
            var d = pgExp.value.replace(/\D/g, '');
            if (d.length < 4) return;                 // still incomplete — say nothing
            var mm = parseInt(d.slice(0, 2), 10);
            var yy = d.slice(2);
            var year = (yy.length === 2) ? (2000 + parseInt(yy, 10)) : parseInt(yy, 10);
            if (!mm || mm < 1 || mm > 12) {
                pgExp.setCustomValidity({$_eo_label_exp_month});
                return;
            }
            var now = new Date();
            if (year < now.getFullYear() ||
                (year === now.getFullYear() && mm < (now.getMonth() + 1))) {
                pgExp.setCustomValidity({$_eo_label_exp_past});
            }
        });
    }

    var pgCvc = form.querySelector('[name="card_verification_number"]');
    if (pgCvc) {
        pgCvc.setAttribute('inputmode', 'numeric');
        pgCvc.setAttribute('autocomplete', 'cc-csc');
        pgCvc.setAttribute('maxlength', '4');
        pgApplyMask(pgCvc, function (d) { return d; }, 4);
    }

    // Shipping JS only runs when there are recipients. For digital orders
    // (RIDS empty) the script terminates here after wiring the date
    // converter and the card masks — no shipping picker, no AJAX, no extra
    // listeners.
    if (!RIDS.length) return;

    // ── Country → state text/select toggle + zip required ──────────────────
    // States table is queried server-side; here we just swap visibility of
    // the dual inputs based on whether the state select has any matching
    // options for the chosen country code. For now we use a simpler rule:
    // when a country is picked, show the select; when blank, show the text.
    // The full per-country state filter can be wired in Phase 4 once we add
    // the /api.php?action=get_states endpoint.
    function toggleStateField(countryEl) {
        var rid = countryEl.getAttribute('data-pg-eo-rid');
        var txt = form.querySelector('.pg-eo-state-text[name="shipping_' + rid + '_state"]');
        var sel = form.querySelector('.pg-eo-state-select[name="shipping_' + rid + '_state"]');
        if (!txt || !sel) return;
        // For now both inputs share the same name → only one should be in
        // the DOM at submit time. We hide the inactive one with display:none
        // (HTML still submits hidden inputs, but the <input> in display:none
        // still posts its value — same `name` collision risk). Workaround:
        // disable the inactive one so it doesn't post.
        var hasCountry = countryEl.value !== '';
        // Default behavior: text always works. Select fills when we have a
        // states API. For Phase 2 we keep text active by default.
        txt.style.display = hasCountry ? '' : '';
        sel.style.display = 'none';
        sel.disabled = true;
        txt.disabled = false;
    }

    // ── Shipping method fetch (debounced per recipient) ────────────────────
    var debounceTimers = {};
    function fetchMethods(rid) {
        clearTimeout(debounceTimers[rid]);
        debounceTimers[rid] = setTimeout(function () { _doFetch(rid); }, 600);
    }
    function _doFetch(rid) {
        var statusEl = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-methods-status');
        var listEl   = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-methods-list');
        if (!statusEl || !listEl) return;

        var addr1 = (form.querySelector('[name="shipping_' + rid + '_address_1"]') || {}).value || '';
        var city  = (form.querySelector('[name="shipping_' + rid + '_city"]')      || {}).value || '';
        var state = (form.querySelector('[name="shipping_' + rid + '_state"]:not([disabled])') || {}).value || '';
        var zip   = (form.querySelector('[name="shipping_' + rid + '_zip_code"]') || {}).value || '';
        var ctry  = (form.querySelector('[name="shipping_' + rid + '_country"]')  || {}).value || '';
        // Country-only minimum gate. Pinegrap shipping rates are country-
        // (and zone-) based, not city/district-based — so we don't need
        // address_1 or city to be filled to surface the available method
        // list. The visitor sees options as soon as they pick a country;
        // street address remains required at form submit (HTML5 `required`)
        // for the address itself, just not for the rate query.
        if (!ctry) {
            statusEl.style.display = '';
            statusEl.textContent = {$_eo_label_enter_addr};
            return;
        }

        // Arrival date — when a CUSTOM arrival date is the active radio,
        // we must read its date input value and pass it as `arrival_date`
        // (the API rejects custom selections without a valid date with
        // "Sorry, the requested arrival date is not valid"). For non-
        // custom selections (e.g. "At Once"), we just send the id and let
        // the API resolve the date from arrival_dates.arrival_date.
        var arrivalIdEl = form.querySelector('input[name="shipping_' + rid + '_arrival_date"]:checked');
        var arrivalId   = arrivalIdEl ? arrivalIdEl.value : '';
        var arrivalDate = '';
        if (arrivalId) {
            var custInp = form.querySelector('[name="shipping_' + rid + '_custom_arrival_date_' + arrivalId + '"]');
            if (custInp) {
                arrivalDate = custInp.value || '';
                // Custom date picked but not filled yet → don't ping the
                // API (it would error). Show a hint instead so the
                // visitor knows to pick a date.
                if (!arrivalDate) {
                    statusEl.style.display = '';
                    statusEl.textContent = {$_eo_label_pick_date};
                    return;
                }
            }
        }

        statusEl.style.display = '';
        statusEl.textContent = {$_eo_label_calculating};
        listEl.innerHTML = '';

        var body = {
            action:          'get_shipping_methods',
            ship_to_id:      rid,
            address_1:       addr1,
            city:            city,
            state:           state,
            zip_code:        zip,
            country:         ctry,
            arrival_date_id: arrivalId,
            // Convert ISO YYYY-MM-DD from the date input → server's
            // DATE_FORMAT-aware format. Server's validate_date rejects ISO.
            arrival_date:    isoToServerDate(arrivalDate)
        };

        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json || json.status !== 'success' || !Array.isArray(json.shipping_methods)) {
                statusEl.textContent = (json && json.message) ? json.message : {$_eo_label_no_methods};
                return;
            }
            if (json.shipping_methods.length === 0) {
                statusEl.textContent = {$_eo_label_no_methods};
                return;
            }
            statusEl.style.display = 'none';
            renderMethods(rid, json.shipping_methods);
        })
        .catch(function () {
            statusEl.textContent = {$_eo_label_network_err};
        });
    }
    function renderMethods(rid, methods) {
        var listEl = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-methods-list');
        var hidEl  = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-method-input');
        if (!listEl || !hidEl) return;
        var prevPick = hidEl.value;
        // Which entry gets the default tick when there's nothing to restore:
        // the cheapest, falling back to the first when costs tie or are
        // missing. Leaving the group blank meant the visitor could reach
        // "Siparişi Tamamla" with no shipping method chosen and get bounced
        // by the server, and a stale hidden value that no longer matches any
        // offered method produced exactly that state.
        var defaultIdx = 0;
        for (var di = 1; di < methods.length; di++) {
            var c  = parseFloat(methods[di].cost);
            var cb = parseFloat(methods[defaultIdx].cost);
            if (!isNaN(c) && (isNaN(cb) || c < cb)) defaultIdx = di;
        }
        var prevStillOffered = false;
        for (var pi = 0; pi < methods.length; pi++) {
            if (prevPick === String(methods[pi].id)) { prevStillOffered = true; break; }
        }
        var html = '';
        methods.forEach(function (m, i) {
            var id = 'pg_eo_ship_' + rid + '_' + m.id;
            // Re-pick the visitor's previous choice when it's still on offer;
            // otherwise tick the default worked out above.
            var sel = prevStillOffered ? (prevPick === String(m.id)) : (i === defaultIdx);
            var cost = (m.cost === 0) ? {$_eo_label_free} : (m.cost_info || (m.cost + ''));
            html += '<div class="form-check d-flex align-items-center justify-content-between border-bottom py-2">'
                 +   '<div>'
                 +     '<input class="form-check-input pg-eo-method-radio" type="radio" name="pg_eo_ship_picker_' + rid + '"'
                 +       ' id="' + id + '" value="' + m.id + '" data-pg-eo-rid="' + rid + '"'
                 +       ' data-pg-eo-cost="' + (m.cost || 0) + '"'
                 +       (sel ? ' checked' : '') + '>'
                 +     '<label class="form-check-label ms-2" for="' + id + '">'
                 +       '<strong>' + escapeHtml(m.name || '') + '</strong>'
                 +       (m.description ? '<div class="small text-muted">' + escapeHtml(m.description) + '</div>' : '')
                 +     '</label>'
                 +   '</div>'
                 +   '<div class="ms-3 fw-semibold">' + cost + '</div>'
                 + '</div>';
        });
        listEl.innerHTML = html;
        // Sync the initial pick into the hidden input + sidebar cost.
        var picked = listEl.querySelector('input.pg-eo-method-radio:checked');
        if (picked) updateMethodSelection(rid, picked);
    }
    function updateMethodSelection(rid, radio) {
        var hidEl = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-method-input');
        if (!hidEl) return;
        hidEl.value = radio.value;
        // Sidebar shipping update — sum cost across all recipients.
        var total = 0;
        form.querySelectorAll('input.pg-eo-method-radio:checked').forEach(function (r) {
            total += parseFloat(r.getAttribute('data-pg-eo-cost') || 0);
        });
        var shipSpan = document.querySelector('.pg-eo-shipping-formatted');
        // The method costs are in the base currency, the sidebar shows the
        // visitor's.
        if (shipSpan) shipSpan.textContent = total > 0 ? formatCost(total * moneyRate()) : '—';
        // Recompute total too — read other displayed amounts and sum.
        recomputeSidebarTotal();
    }
    function recomputeSidebarTotal() {
        // Guard: only trust this client-side recompute when it can find ALL
        // the pieces it needs. Express Order templates saved before the
        // pg-eo-subtotal-formatted / pg-eo-tax-formatted /
        // pg-eo-shipping-formatted hook classes were added to the Subtotal /
        // Tax / Shipping rows (_eo_default_designer_tree's \$tot_row calls)
        // don't carry those classes on their value <span>s — every lookup
        // below then silently resolves to 0, and this used to blindly
        // overwrite the CORRECT server-rendered "Toplam" with "0.00" the
        // moment a shipping method was auto-selected on page load. That's
        // the "Toplam: 0.00" / "tutar değişti" bug. Bailing out here means
        // older templates simply don't live-update the total on shipping
        // change (a full "Güncelle" still recalculates correctly
        // server-side) instead of showing a wrong number.
        var subEl = document.querySelector('.pg-eo-subtotal-formatted');
        if (!subEl) return;
        var sub  = parseAmt(subEl);
        var tax  = parseAmt(document.querySelector('.pg-eo-tax-formatted'));
        var ship = parseAmt(document.querySelector('.pg-eo-shipping-formatted'));
        // A real cart always has a positive subtotal — never let a parse
        // failure (0) masquerade as a legitimate recompute.
        if (sub <= 0) return;
        var total = sub + tax + ship;
        var totalSpan = document.querySelector('.pg-eo-total-formatted');
        if (totalSpan) totalSpan.textContent = formatCost(total);
    }
    // software_money_format / software_format_money / software_parse_money
    // come from frontend.js and write money the way the server does; the
    // fallbacks below only matter on a page without them.
    function moneyFormat() {
        return (typeof software_money_format !== 'undefined' && software_money_format) ? software_money_format : null;
    }
    function moneyRate() {
        var f = moneyFormat();
        return (f && f.rate > 0) ? f.rate : 1;
    }
    function parseAmt(el) {
        if (!el) return 0;
        if (window.software_parse_money && moneyFormat()) return software_parse_money(el.textContent);
        var t = el.textContent.replace(/[^0-9,.\-]/g, '');
        // Locale-aware: assume Turkish format `1.234,56` if comma present
        // after a dot; else fall back to dot-decimal.
        if (t.indexOf(',') !== -1 && t.lastIndexOf('.') < t.lastIndexOf(',')) {
            t = t.replace(/\./g, '').replace(',', '.');
        }
        var n = parseFloat(t);
        return isNaN(n) ? 0 : n;
    }
    function formatCost(amount) {
        var f = moneyFormat();
        if (f && window.software_format_money) return software_format_money(amount, f.symbol, f.suffix);
        // Match the server-side currency formatter as best we can. The exact
        // symbol comes from the existing rendered span.
        var ref = document.querySelector('.pg-eo-subtotal-formatted');
        var sym = '';
        if (ref) {
            var m = ref.textContent.match(/[^0-9,.\-\s]+/);
            if (m) sym = m[0];
        }
        return sym + amount.toFixed(2);
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c];
        });
    }

    // ── Wire listeners per recipient ────────────────────────────────────────
    RIDS.forEach(function (rid) {
        // Country toggles state field + triggers method fetch
        var ctry = form.querySelector('[name="shipping_' + rid + '_country"]');
        if (ctry) {
            toggleStateField(ctry);
            ctry.addEventListener('change', function () {
                toggleStateField(ctry);
                fetchMethods(rid);
            });
        }
        // Address fields → debounced refetch on change
        ['address_1', 'city', 'state', 'zip_code'].forEach(function (f) {
            var el = form.querySelector('[name="shipping_' + rid + '_' + f + '"]:not([disabled])');
            if (el) el.addEventListener('change', function () { fetchMethods(rid); });
        });
        // Arrival date radios — refetch + show/hide custom date input
        form.querySelectorAll('input.pg-eo-arrival-radio[data-pg-eo-rid="' + rid + '"]').forEach(function (r) {
            r.addEventListener('change', function () {
                // Toggle custom date input visibility
                form.querySelectorAll('.pg-eo-custom-arrival').forEach(function (cd) {
                    cd.style.display = 'none';
                });
                var custWrap = form.querySelector('.pg-eo-custom-arrival[data-pg-eo-arrival-id="' + r.value + '"]');
                if (custWrap) custWrap.style.display = '';
                fetchMethods(rid);
            });
        });
        // Custom arrival DATE input change → refetch (the picked date affects
        // which methods can deliver by then; without this listener the rate
        // list goes stale once the visitor changes the date).
        form.querySelectorAll('.pg-eo-custom-arrival input[type="date"]').forEach(function (inp) {
            inp.addEventListener('change', function () { fetchMethods(rid); });
        });
        // Method picker → update hidden input + sidebar
        form.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('pg-eo-method-radio')) {
                var trid = e.target.getAttribute('data-pg-eo-rid');
                if (String(trid) === String(rid)) updateMethodSelection(rid, e.target);
            }
        });
        // The page may already carry the list (worked out on the server for
        // visitors without the script): adopt it — the radios become the
        // picker and the hidden input posts the choice — instead of asking
        // the server for the same list again.
        var hidInit = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] .pg-eo-method-input');
        var served  = form.querySelectorAll('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] input.pg-eo-method-radio');
        if (hidInit) hidInit.disabled = false;
        if (served.length) {
            served.forEach(function (r) { r.name = 'pg_eo_ship_picker_' + rid; });
            var servedPick = form.querySelector('.pg-eo-shipping-methods[data-pg-eo-rid="' + rid + '"] input.pg-eo-method-radio:checked');
            if (servedPick) updateMethodSelection(rid, servedPick);
        // Initial fetch as soon as a country is set (covers the prefilled
        // case — visitor lands on the page with country=TR already chosen
        // from address_book / orders, so we surface methods immediately
        // rather than making them edit something to trigger the lookup).
        } else if (ctry && ctry.value) {
            _doFetch(rid);
        }
    });
})();
</script>
HTML;
}

// ─────────────────────────────────────────────────────────────────────────────
// Express Order — recipient_loop_area support (multi-recipient designer
// customization). The designer drops a `recipient_loop_area` node into the
// tree; its children form a template that the server renders ONCE per
// shipping recipient. Field bindings (eo_field='shipping_first_name', …) are
// rewritten per iteration to `shipping_<rid>_*` so each recipient gets its
// own POST keys. Per-iteration text tokens (^^__recipient_index^^,
// ^^__recipient_name^^, ^^__recipient_id^^) are replaced at template-clone time.
//
// Single-recipient orders with a recipient_loop_area still work — the loop
// runs exactly once. Trees WITHOUT a recipient_loop_area fall through to the
// existing server-rendered _eo_render_shipping_section() pipeline (backwards
// compat).
// ─────────────────────────────────────────────────────────────────────────────

// Detect: does the tree contain at least one recipient_loop_area node?
function _eo_tree_has_recipient_loop_area($tree)
{
    if (!is_array($tree)) return false;
    if (isset($tree['type']) && $tree['type'] === 'recipient_loop_area') return true;
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) {
            if (_eo_tree_has_recipient_loop_area($child)) return true;
        }
    }
    return false;
}

// Walk the tree, locate the FIRST recipient_loop_area, capture its children
// as a per-recipient template and replace the node with a marker comment.
// Returns: ['static_tree' => $tree_clone, 'template_children' => array|null]
//
// Mirrors _split_widget_tree (cart-item loop) but for recipient iteration.
// Only one recipient_loop_area is honored per widget tree (designer guard
// enforces 1-per-widget too).
function _eo_split_recipient_loop_area($tree)
{
    if (!is_array($tree)) return array('static_tree' => $tree, 'template_children' => null);
    $clone = json_decode(json_encode($tree), true);
    $template_children = null;

    $marker_node = array(
        'type'     => 'content',
        'props'    => array('contentType' => 'custom_html', 'html' => '<!--pg-recipient-loop-slot-->'),
        'children' => array(),
    );

    $walk = function (&$node) use (&$walk, &$template_children, $marker_node) {
        if (!is_array($node) || empty($node['children']) || $template_children !== null) return;
        foreach ($node['children'] as $i => $child) {
            if ($template_children !== null) return;
            if (isset($child['type']) && $child['type'] === 'recipient_loop_area') {
                $template_children = isset($child['children']) ? $child['children'] : array();
                $node['children'][$i] = $marker_node;
                return;
            }
            $walk($node['children'][$i]);
        }
    };
    $walk($clone);

    return array('static_tree' => $clone, 'template_children' => $template_children);
}

// Build per-recipient ^^__token^^ replacements for a single iteration of the
// recipient loop. Keys are tokens that the designer can place inside the
// recipient_loop_area template (text bindings on heading/paragraph/span).
//
// Tokens:
//   ^^__recipient_index^^              "1", "2", …          (1-based)
//   ^^__recipient_count^^              "3"                  (total recipients)
//   ^^__recipient_id^^                 "612"                (ship_tos.id; for input ids)
//   ^^__recipient_name^^               "Ayşe Yılmaz"        (ship_to_name, falls back to "Alıcı N")
//   ^^__recipient_address_first_name^^ session/db value for shipping_<rid>_first_name
//   ^^__recipient_address_last_name^^
//   ^^__recipient_address_address_1^^
//   ^^__recipient_address_address_2^^
//   ^^__recipient_address_city^^
//   ^^__recipient_address_state^^
//   ^^__recipient_address_zip_code^^
//   ^^__recipient_address_country^^
//   ^^__recipient_address_phone_number^^
//
// The address tokens are intended for read-only display (e.g. summary card).
// For actual input fields use _bindings.eo_field='shipping_first_name' inside
// the recipient_loop_area — the field-binding walker rewrites the name to
// shipping_<rid>_first_name automatically.
function _eo_compute_recipient_tokens($recipient, $index, $count, $lf = null)
{
    if (!is_array($recipient)) $recipient = array();
    $rid = isset($recipient['id']) ? (int)$recipient['id'] : 0;
    $get = function ($field) use ($lf, $recipient, $rid) {
        if ($lf && $rid > 0) {
            $key = 'shipping_' . $rid . '_' . $field;
            $v = $lf->get_field_value($key);
            if ($v !== '' && $v !== null) return (string)$v;
        }
        return isset($recipient[$field]) ? (string)$recipient[$field] : '';
    };

    $name = '';
    if (isset($recipient['ship_to_name']) && (string)$recipient['ship_to_name'] !== '') {
        $name = (string)$recipient['ship_to_name'];
    } else {
        $first = $get('first_name');
        $last  = $get('last_name');
        $full  = trim($first . ' ' . $last);
        if ($full !== '') {
            $name = $full;
        } else {
            $name = (string)lang('Recipient') . ' ' . $index;
        }
    }

    $addr1 = $get('address_1');
    $zip   = $get('zip_code');
    $phone = $get('phone_number');

    return array(
        '^^__recipient_index^^'                  => (string)$index,
        '^^__recipient_count^^'                  => (string)$count,
        '^^__recipient_id^^'                     => (string)$rid,
        '^^__recipient_name^^'                   => h($name),
        '^^__recipient_address_first_name^^'     => h($get('first_name')),
        '^^__recipient_address_last_name^^'      => h($get('last_name')),
        '^^__recipient_address_company^^'        => h($get('company')),
        '^^__recipient_address_address_1^^'      => h($addr1),
        '^^__recipient_address_address_2^^'      => h($get('address_2')),
        '^^__recipient_address_city^^'           => h($get('city')),
        '^^__recipient_address_state^^'          => h($get('state')),
        '^^__recipient_address_zip_code^^'       => h($zip),
        '^^__recipient_address_country^^'        => h($get('country')),
        '^^__recipient_address_phone_number^^'   => h($phone),
    );
}

// Render the per-recipient template for every shipping recipient.
//
// For each $recipients row:
//   1. Deep-clone the template_children
//   2. Run _eo_apply_field_bindings with that recipient's rid → rewrites every
//      `_bindings.eo_field='shipping_X'` to `shipping_<rid>_X` and pre-fills
//      the value from liveform session (per-recipient)
//   3. Run _eo_prefill_input_values_in_tree (covers raw <input name="...">
//      shipping_<rid>_* fields if the designer typed them manually)
//   4. Render each template child to HTML, concatenate
//   5. Replace per-recipient ^^__recipient_*^^ tokens
//
// Returns the concatenated HTML for all recipients. Returns '' on no
// recipients or empty template.
function _eo_render_recipient_loop($template_children, $recipients, $lf)
{
    if (!is_array($template_children) || empty($template_children)) return '';
    if (!is_array($recipients) || empty($recipients)) return '';

    $out = '';
    $count = count($recipients);
    $i = 0;
    foreach ($recipients as $r) {
        $i++;
        $rid = isset($r['id']) ? (int)$r['id'] : 0;

        // Deep-clone the template (json round-trip — template is small).
        $clone = json_decode(json_encode($template_children), true);
        if (!is_array($clone)) continue;

        // Apply field bindings PER-RECIPIENT so the eo_field walker rewrites
        // shipping_<field> → shipping_<rid>_<field> for THIS recipient's rid.
        // Walker expects a tree node; wrap children in a synthetic root.
        $wrap = array('type' => 'root', 'props' => array(), 'children' => $clone);
        if ($lf) {
            _eo_apply_field_bindings($wrap, $lf, $rid);
            _eo_prefill_input_values_in_tree($wrap, $lf);
        }
        _eo_apply_action_bindings($wrap);

        // Render each cloned child node.
        $row_html = '';
        if (!empty($wrap['children']) && is_array($wrap['children'])) {
            foreach ($wrap['children'] as $kid) {
                $row_html .= (string)_render_tree_node($kid, 0, 0);
            }
        }

        // Per-recipient token replacement (^^__recipient_index^^ etc.).
        $tokens = _eo_compute_recipient_tokens($r, $i, $count, $lf);
        $row_html = strtr($row_html, $tokens);

        // The `name` of each control already carries the recipient
        // (shipping_<rid>_first_name), but the `id` did not — so with two
        // recipients on the page every <label> focused the first one's field.
        // Ids and the attributes pointing at them move together; the names
        // the server reads on POST are untouched.
        $row_html = pg_sw_uniquify_row_ids($row_html, 0, $rid);

        $out .= $row_html;
    }
    return $out;
}
