<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: System widgets for the shopping cart and quick add.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Walk the cart tree and apply per-node bindings the designer set on btn /
 * input nodes. Recognized bindings:
 *   • component btn _bindings.action='cart_checkout'
 *       → renders as <a href={checkout_url}> with btnText defaulting to
 *         the configured checkout label
 *   • component btn _bindings.action='cart_update'
 *       → renders as <button type=submit name=submit_update_cart form=cart_form>
 *         with btnText defaulting to the configured update label
 *   • semantic input _bindings.value='cart_qty' (inside loop_area)
 *       → during per-row substitution this is replaced by a real qty input
 *         with name="quantities[N]" form=cart_form_id
 *
 * Lets the designer drop a real Bootstrap button (variant, size, icon, etc.)
 * instead of the static `^^__update_button^^` HTML token. Both paths coexist
 * — token-based nodes keep working unchanged.
 */
function _apply_shopping_cart_bindings(&$node, $context)
{
    if (!is_array($node)) return;
    $bindings = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings']))
                    ? $node['props']['_bindings'] : array();

    // ── Component btn: cart_checkout / cart_update ───────────────────────
    if (isset($node['type']) && $node['type'] === 'component'
        && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn'
        && isset($bindings['action'])) {
        $action = $bindings['action'];
        if ($action === 'cart_checkout') {
            // Anchor to next-page URL; force href (overrides any literal).
            $node['props']['btnElement'] = 'a';
            $node['props']['href']       = isset($context['checkout_url']) ? $context['checkout_url'] : '#';
            if (empty($node['props']['text']) || $node['props']['text'] === 'Click Me'
                || $node['props']['text'] === 'Button') {
                $node['props']['text'] = (($context['checkout_button_label'] ?? '') !== '') ? $context['checkout_button_label'] : lang('Checkout');
            }
        } elseif ($action === 'cart_update') {
            // Submit button against the cart form.
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'submit';
            $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                            ? $node['props']['_attrs'] : array();
            // `formnovalidate` is also managed so we can force it on every
            // designer-placed Update button (mirrors the token-emitted button
            // at line 24723). Without it, per-item product-form fields with
            // `required` would block the qty update — see that block's
            // comment for the rationale.
            $managed  = array('type' => 1, 'name' => 1, 'value' => 1, 'form' => 1, 'formnovalidate' => 1);
            $kept     = array();
            foreach ($existing as $a) {
                if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                    $kept[] = $a;
                }
            }
            $kept[] = array('name' => 'name',  'value' => 'submit_update_cart');
            $kept[] = array('name' => 'value', 'value' => '1');
            $kept[] = array('name' => 'form',  'value' => isset($context['cart_form_id']) ? $context['cart_form_id'] : '');
            $kept[] = array('name' => 'formnovalidate', 'value' => '');
            $node['props']['_attrs'] = $kept;
            if (empty($node['props']['text']) || $node['props']['text'] === 'Click Me'
                || $node['props']['text'] === 'Button') {
                $node['props']['text'] = (($context['update_button_label'] ?? '') !== '') ? $context['update_button_label'] : lang('Update');
            }
        }
    }

    // ── Semantic input: cart_qty (inside loop_area) ──────────────────────
    // Designer placed a real <input> bound to cart_qty. Set the right name /
    // value / form / type attrs so the input becomes a working qty editor.
    // The per-row loop substitutes ^^__item_id^^ / ^^__item_qty^^ tokens so
    // each rendered row gets its own input — the tree node stays a SINGLE
    // real <input> the designer can style, position, and give a label to.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'input'
        && isset($bindings['value']) && $bindings['value'] === 'cart_qty') {
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'value' => 1, 'form' => 1, 'min' => 1, 'step' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'type',  'value' => 'number');
        $kept[] = array('name' => 'name',  'value' => 'quantities[^^__item_id^^]');
        $kept[] = array('name' => 'value', 'value' => '^^__item_qty^^');
        $kept[] = array('name' => 'form',  'value' => isset($context['cart_form_id']) ? $context['cart_form_id'] : '');
        $kept[] = array('name' => 'min',   'value' => '0');
        $kept[] = array('name' => 'step',  'value' => '1');
        $node['props']['_attrs'] = $kept;
        // Default Bootstrap class if designer hasn't set anything obvious.
        if (empty($node['props']['cssClass'])) {
            $node['props']['cssClass'] = 'form-control form-control-sm';
        }
    }

    // ── Qty stepper buttons: cart_qty_inc / cart_qty_dec ─────────────────
    // Designer placed a <button> bound to one of these actions. We force
    // type="button" (so it doesn't accidentally submit the form) and stamp
    // the `data-pg-qty-action="inc|dec"` data attribute that the global
    // qty-stepper JS handler watches for. The handler finds the nearest
    // qty input inside the same .input-group / .pg-qty-stepper wrapper —
    // or honours an explicit `data-pg-qty-target="<input-id>"` when the
    // designer placed +/- far from the input. Works on both component-btn
    // and semantic-button nodes.
    $is_qty_btn_action = isset($bindings['action'])
        && ($bindings['action'] === 'cart_qty_inc' || $bindings['action'] === 'cart_qty_dec');
    if ($is_qty_btn_action && isset($node['type'])) {
        $qty_dir = ($bindings['action'] === 'cart_qty_inc') ? 'inc' : 'dec';
        $is_btn_target = ($node['type'] === 'semantic'
                            && isset($node['props']['tag'])
                            && in_array($node['props']['tag'], array('button', 'a', 'span', 'i'), true))
                      || ($node['type'] === 'component'
                            && isset($node['props']['componentType'])
                            && $node['props']['componentType'] === 'btn');
        if ($is_btn_target) {
            if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) $node['props']['_attrs'] = array();
            // Strip any prior data-pg-qty-action / type entries we manage.
            $managed = array('type' => 1, 'data-pg-qty-action' => 1);
            $kept = array();
            foreach ($node['props']['_attrs'] as $a) {
                if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                    $kept[] = $a;
                }
            }
            // For component btn: rewire to a real <button type="button">.
            if ($node['type'] === 'component') {
                $node['props']['btnElement'] = 'button';
                $node['props']['btnType']    = 'button';
            } else {
                // Semantic button — force type="button" so a parent <form>
                // doesn't submit when the visitor clicks +/-.
                if (isset($node['props']['tag']) && $node['props']['tag'] === 'button') {
                    $kept[] = array('name' => 'type', 'value' => 'button');
                }
            }
            $kept[] = array('name' => 'data-pg-qty-action', 'value' => $qty_dir);
            $node['props']['_attrs'] = $kept;
        }
    }

    // ── remove_from_cart action — universal per-row remove ─────────────
    // Designer wires `_bindings.action='remove_from_cart'` on any clickable
    // element inside the cart loop_area. Handler is element-aware:
    //   • content[contentType=link]  → sets props.href to per-row token
    //   • semantic <a>                → sets href attr to per-row token
    //   • semantic <button|span|div>  → stamps data-pg-remove-href; the
    //                                    cart widget's inline JS handles
    //                                    the click → navigate.
    // The per-row token `^^__item_remove_url^^` is substituted later by
    // the loop renderer with the actual CSRF-tokenized remove URL.
    if (isset($bindings['action']) && $bindings['action'] === 'remove_from_cart') {
        if (isset($node['type']) && $node['type'] === 'content'
            && isset($node['props']['contentType']) && $node['props']['contentType'] === 'link') {
            $node['props']['href'] = '^^__item_remove_url^^';
        } elseif (isset($node['type']) && $node['type'] === 'semantic') {
            $tag_lc = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
            if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) $node['props']['_attrs'] = array();
            if ($tag_lc === 'a') {
                $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) {
                    return !(is_array($a) && isset($a['name'])
                              && in_array($a['name'], array('href', 'rel'), true));
                }));
                $node['props']['_attrs'][] = array('name' => 'href', 'value' => '^^__item_remove_url^^');

                // Destructive action link, same as the legacy cart's remove
                // button. Stamped by the server rather than left to the
                // designer: whether a crawler may follow "remove this item" is
                // not a design choice.
                $node['props']['_attrs'][] = array('name' => 'rel', 'value' => 'nofollow');
            } elseif (in_array($tag_lc, array('button', 'span', 'i', 'div'), true)) {
                $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) {
                    return !(is_array($a) && isset($a['name'])
                              && in_array($a['name'], array('data-pg-remove-href', 'role', 'type'), true));
                }));
                $node['props']['_attrs'][] = array('name' => 'data-pg-remove-href', 'value' => '^^__item_remove_url^^');
                $node['props']['_attrs'][] = array('name' => 'role', 'value' => 'button');
                if ($tag_lc === 'button') {
                    $node['props']['_attrs'][] = array('name' => 'type', 'value' => 'button');
                }
            }
        }
    }

    // ── Semantic form: coupon_form ────────────────────────────────────────
    // Designer places a real <form> + <input> + button so the coupon UI is
    // styleable end-to-end. We rewrite the form's action/method via _attrs
    // and inject a hidden token field + send_to + page_id as children so
    // cart_action.php can read them on POST.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'form'
        && isset($bindings['action']) && $bindings['action'] === 'coupon_form') {
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('action' => 1, 'method' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'action', 'value' => isset($context['cart_action_url']) ? $context['cart_action_url'] : '');
        $kept[] = array('name' => 'method', 'value' => 'post');
        $node['props']['_attrs'] = $kept;
        // Inject hidden CSRF + send_to + page_id as the FIRST child if not
        // already there. Marker `_pg_coupon_hidden` prevents duplicates on
        // subsequent render passes.
        $has_hidden = false;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $ch) {
                if (is_array($ch) && isset($ch['type']) && $ch['type'] === 'content'
                    && isset($ch['props']['contentType']) && $ch['props']['contentType'] === 'custom_html'
                    && isset($ch['props']['_pg_coupon_hidden'])) {
                    $has_hidden = true; break;
                }
            }
        }
        if (!$has_hidden) {
            $hidden_node = array(
                'type'     => 'content',
                'props'    => array(
                    'contentType' => 'custom_html',
                    'html'        => (function_exists('get_token_field') ? get_token_field() : '')
                                   . '<input type="hidden" name="send_to" value="'
                                   . h(isset($context['request_uri']) ? $context['request_uri'] : '') . '">'
                                   . '<input type="hidden" name="page_id" value="'
                                   . (isset($context['page_id']) ? (int)$context['page_id'] : 0) . '">',
                    '_pg_coupon_hidden' => true,
                ),
                'children' => array(),
            );
            if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
            array_unshift($node['children'], $hidden_node);
        }
    }

    // ── Semantic input: coupon_code ──────────────────────────────────────
    // Inside the coupon form. Sets name=special_offer_code + the current
    // value so the field round-trips after a failed apply attempt.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($node['props']['tag']) && $node['props']['tag'] === 'input'
        && isset($bindings['value']) && $bindings['value'] === 'coupon_code') {
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'value' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'type',  'value' => 'text');
        $kept[] = array('name' => 'name',  'value' => 'special_offer_code');
        $kept[] = array('name' => 'value', 'value' => isset($context['special_offer_code']) ? $context['special_offer_code'] : '');
        $node['props']['_attrs'] = $kept;
        if (empty($node['props']['cssClass'])) {
            $node['props']['cssClass'] = 'form-control';
        }
    }

    // ── Semantic div: applied_offers ─────────────────────────────────────
    // Designer drops a real <div> with `_bindings.section='applied_offers'`
    // — backend mutates it into a custom_html block carrying the live
    // alert HTML at render time. The designer canvas sees a real div with
    // a friendly label, not a `^^__applied_offers^^` token text.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($bindings['section']) && $bindings['section'] === 'applied_offers') {
        // Use a token marker — the static_values substitution downstream
        // replaces it with the actual offers HTML. This keeps the section's
        // contents in sync with the freshly-computed $applied_offers_html
        // (which depends on the mutated cart state and runs AFTER bindings).
        $node['type']     = 'content';
        $node['props']    = array(
            'contentType' => 'custom_html',
            'html'        => '^^__applied_offers^^',
        );
        $node['children'] = array();
    }

    // ── Semantic div: pending_offers ─────────────────────────────────────
    // "Gift with purchase" claims — the visitor has qualified but must press
    // Ekle. Same marker-token treatment as applied_offers above: the block is
    // built AFTER apply_offers_to_cart() has run, so we can only leave a
    // placeholder here.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($bindings['section']) && $bindings['section'] === 'pending_offers') {
        $node['type']     = 'content';
        $node['props']    = array(
            'contentType' => 'custom_html',
            'html'        => '^^__pending_offers^^',
        );
        $node['children'] = array();
    }

    // ── Semantic div: quick_add ──────────────────────────────────────────
    // Same marker treatment as pending_offers: the box carries its own form
    // and is built after the cart totals, so only a placeholder fits here.
    if (isset($node['type']) && $node['type'] === 'semantic'
        && isset($bindings['section']) && $bindings['section'] === 'quick_add') {
        $node['type']     = 'content';
        $node['props']    = array(
            'contentType' => 'custom_html',
            'html'        => '^^__quick_add_form^^',
        );
        $node['children'] = array();
    }

    // ── Component btn: apply_coupon ──────────────────────────────────────
    // Coupon form's submit button — drives `submit_special_offer_code=1`.
    if (isset($node['type']) && $node['type'] === 'component'
        && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn'
        && isset($bindings['action']) && $bindings['action'] === 'apply_coupon') {
        $node['props']['btnElement'] = 'button';
        $node['props']['btnType']    = 'submit';
        $existing = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs']))
                        ? $node['props']['_attrs'] : array();
        $managed  = array('type' => 1, 'name' => 1, 'value' => 1);
        $kept     = array();
        foreach ($existing as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[$a['name']])) {
                $kept[] = $a;
            }
        }
        $kept[] = array('name' => 'name',  'value' => 'submit_special_offer_code');
        $kept[] = array('name' => 'value', 'value' => '1');
        $node['props']['_attrs'] = $kept;
        if (empty($node['props']['text']) || $node['props']['text'] === 'Click Me' || $node['props']['text'] === 'Button') {
            $node['props']['text'] = (($context['special_offer_code_label'] ?? '') !== '') ? $context['special_offer_code_label'] : lang('Apply');
        }
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            if (is_array($child)) _apply_shopping_cart_bindings($child, $context);
        }
        unset($child);
    }
}

// Render a 'shopping_cart' system widget. Reads the current session cart
// (via $_SESSION['ecommerce']['order_id']), iterates order_items in loop_area,
// and exposes the full set of cart tokens used by the designer template.
//
// Static tokens — summary numbers (currency-formatted; do NOT h() these):
//   ^^__cart_subtotal^^, ^^__cart_tax^^, ^^__cart_surcharge^^,
//   ^^__cart_discount^^, ^^__cart_shipping^^, ^^__cart_total^^,
//   ^^__cart_count^^, ^^__has_shippable_item^^
// Static tokens — labels / messages (text):
//   ^^__shopping_cart_label^^, ^^__quick_add_label^^,
//   ^^__special_offer_code_label^^, ^^__special_offer_code_message^^,
//   ^^__update_button_label^^, ^^__checkout_button_label^^,
//   ^^__cart_empty_message^^, ^^__reference_code^^, ^^__special_offer_code^^,
//   ^^__checkout_url^^
// Static tokens — pre-built HTML (already markup):
//   ^^__special_offer_code_form^^, ^^__update_button^^, ^^__checkout_button^^,
//   ^^__applied_offers^^,
//   ^^__quick_add_form^^     quick-add box (own <form>) for the product group
//                            chosen in the settings panel. Empty when unset.
//                            Also available as section binding 'quick_add'.
//   ^^__pending_offers^^     claimable "gift with purchase" block. Carries its
//                            OWN <form> → must sit OUTSIDE the cart-update
//                            form (nested forms are dropped by the parser).
//                            Also available as section binding 'pending_offers'.
// Static tokens — notices (plain text):
//   ^^__tax_shipping_notice^^  "Vergi ve kargo ödeme adımında hesaplanacaktır."
//                              Names only what is actually still unknown; empty
//                              on an all-digital cart at a tax-free site.
// Loop tokens (per item):
//   ^^__item_name^^, ^^__item_qty^^, ^^__item_qty_input^^,
//   ^^__item_price^^, ^^__item_total^^, ^^__item_image^^,
//   ^^__item_url^^, ^^__item_remove_url^^
// Loop tokens — row kind (drives which control the qty column shows):
//   ^^__item_is_donation^^      selection_type='donation' → the qty control is
//                               a currency amount box (donations[<id>]), and
//                               the row has no meaningful unit price
//   ^^__item_added_by_offer^^   order_items.added_by_offer → qty is printed,
//                               not editable (the offer owns the quantity)
// Loop tokens — stock:
//   ^^__item_has_low_stock^^, ^^__item_stock_warning^^,
//   ^^__item_inventory_quantity^^, ^^__item_out_of_stock^^,
//   ^^__item_out_of_stock_message^^ (product's own WYSIWYG message, inlined)
//
// Architecture notes:
//   • A single empty <form id="pg-cart-form-wN"> is rendered up front; every
//     qty input + the Update button reference it via the HTML5 `form` attr.
//     This avoids nesting other forms (coupon) inside the cart-update form.
//   • Currency-bearing tokens are emitted RAW (not h()'d) because
//     VISITOR_CURRENCY_SYMBOL may be an HTML entity ("&#8378;") and
//     htmlspecialchars would double-encode it.
//   • Next-page redirect picks shipping vs. no-shipping target based on
//     whether the cart has at least one shippable item.
//
// Note: requires ECOMMERCE to be active. Returns empty if ECOMMERCE is off.
function _render_system_widget_shopping_cart($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';

    // Register the cart page for legacy callers (initialize_recipients,
    // remove_item_from_cart redirects, etc.) so the back/forward navigation
    // through the checkout flow keeps working. $_pg_cart_pid is reused
    // further down by the hidden page_id fields on the coupon + update
    // forms — those fields are what shopping_cart.php's POST handler reads
    // to look up properties for this cart page.
    $_pg_cart_pid = 0;
    if (isset($_GET['page'])) {
        $_pg_cart_pid = (int)db_value("SELECT page_id FROM page WHERE page_name = '" . e((string)$_GET['page']) . "' LIMIT 1");
        if ($_pg_cart_pid > 0) {
            $_SESSION['ecommerce']['shopping_cart_page_id'] = $_pg_cart_pid;
            unset($_SESSION['ecommerce']['express_order_page_id']);
        }
    }

    // ── POST-handling moved to cart_action.php ─────────────────────────────
    // The cart-update + coupon forms now POST to /software/cart_action.php
    // which processes the mutation and 302-redirects back. Handling POST in
    // the widget render itself was unreliable: PHP output buffering inside
    // the page-render pipeline made `header('Location: …')` race the page
    // string-builder, and the visitor saw the "form yeniden gönderme" prompt
    // on F5. The dedicated handler runs in a clean request lifecycle so the
    // redirect always fires.
    //
    // We still need a liveform handle for inventory checks below — it shares
    // the 'shopping_cart' name so notices set by cart_action.php on the
    // PREVIOUS request flow into this render.
    $_pg_cart_form_lf = class_exists('liveform') ? new liveform('shopping_cart') : null;

    // Saved-cart link restore: visitor came from `/Sepet?r=REFERENCE_CODE`
    // (their previous session\'s saved cart). Swap session order_id BEFORE
    // initialize_order() runs.
    if (function_exists('_eo_restore_cart_from_reference_code')) {
        _eo_restore_cart_from_reference_code();
    }

    // Make sure offers/prices/inventory checks have run for this cart so the
    // discount / shipping / tax computed below reflect the latest state.
    if (function_exists('initialize_order'))         initialize_order();
    if (function_exists('check_inventory') && $_pg_cart_form_lf) {
        check_inventory($_pg_cart_form_lf);
    }
    if (function_exists('update_order_item_prices')) update_order_item_prices();
    // Capture offers result so the cart widget can render upsell banners
    // (e.g. "Spend ₺X more, get free shipping") and an applied-offers
    // summary. Same data shape express_order uses.
    $_pg_cart_offers = function_exists('apply_offers_to_cart') ? apply_offers_to_cart() : array();
    if (!is_array($_pg_cart_offers)) $_pg_cart_offers = array();

    // ── Decode + split widget tree ────────────────────────────────────────
    // We delay rendering the static_html until AFTER we've also processed any
    // cfg-injected forms (coupon, update, quick-add) so widget-defined token
    // placeholders pick up the live HTML.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    // Cart action URL (dedicated POST handler) — needed in the bindings
    // context so coupon_form / cart_update buttons point at the right place.
    // Computed here (not later with the form HTML strings) because the
    // bindings pass runs before the form HTML is built.
    $output_base_for_action  = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $software_dir_for_action = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $cart_action_url         = $output_base_for_action . $software_dir_for_action . '/cart_action.php';

    // No checkout target configured → the "Ödemeye Geç" button renders as
    // href="#" and does nothing when clicked, with no explanation anywhere.
    // That silent dead end reads like a code bug when it is a missing setting.
    // Surface it through the widget's message area, but only to staff
    // (role < 3): a customer can't fix it and shouldn't be shown site config
    // problems, while the operator is told exactly what to set.
    //
    // Raised HERE, before the messages node is rendered a few lines down —
    // liveform messages are consumed at render time, so anything added after
    // that point would not appear until the NEXT page load.
    //
    // Only fires when BOTH targets are missing (config and session fallback
    // alike), which is unambiguous. The narrower "shipping page unset while
    // the cart holds a shippable item" case needs the item loop and so can't
    // be detected this early.
    if ($_pg_cart_form_lf
        && defined('USER_LOGGED_IN') && USER_LOGGED_IN
        && defined('USER_ROLE') && USER_ROLE < 3
        && (int)(isset($cfg['next_page_id_with_shipping'])    ? $cfg['next_page_id_with_shipping']    : 0) <= 0
        && (int)(isset($cfg['next_page_id_without_shipping']) ? $cfg['next_page_id_without_shipping'] : 0) <= 0
        && empty($_SESSION['ecommerce']['shipping_address_and_arrival_page_id'])
        && empty($_SESSION['ecommerce']['billing_information_page_id'])) {
        $_pg_cart_form_lf->add_warning(lang(
            'Checkout page is not set, so the checkout button has nowhere to go. Set the billing information page (and the shipping page when the cart contains shippable items) in the e-commerce settings.'
        ));
    }

    // Per-widget messages safety net — same pattern as the other system widgets.
    if (function_exists('_pg_inject_messages_node')) {
        _pg_inject_messages_node($tree_decoded, 'shopping_cart');
    }

    // Inject upsell offers as a custom_html content node right after the
    // messages node so the alert renders INSIDE the cart widget\'s message
    // area (not as a floating banner before the widget container).
    // _pg_inject_html_after_messages walks the tree, finds the messages
    // content node, and splices a sibling node directly after it.
    $_pg_cart_upsell_html = (function_exists('_eo_render_upsell_offers') && !empty($_pg_cart_offers['upsell_offers']))
        ? _eo_render_upsell_offers($_pg_cart_offers['upsell_offers'])
        : '';
    if ($_pg_cart_upsell_html !== '') {
        _pg_inject_html_after_messages($tree_decoded,
            '<div class="pg-cart-upsell-auto mb-3">' . $_pg_cart_upsell_html . '</div>');
    }

    // Cart bindings pass — converts designer-placed btn / input nodes with
    // `_bindings.action='cart_checkout'`, `_bindings.action='cart_update'`,
    // `_bindings.value='cart_qty'` into the right submit/href/form attrs.
    // Lets the designer drop a real Bootstrap button/input where they want
    // (instead of the static `^^__update_button^^` HTML token). Both styles
    // work side-by-side — token nodes keep rendering as before.
    // checkout_url + button labels are unknown at this point, so we pass
    // token placeholders that the static_values substitution swaps later.
    _apply_shopping_cart_bindings($tree_decoded, array(
        'checkout_url'             => '^^__checkout_url^^',
        'checkout_button_label'    => isset($cfg['checkout_button_label'])    ? (string)$cfg['checkout_button_label']    : '',
        'update_button_label'      => isset($cfg['update_button_label'])      ? (string)$cfg['update_button_label']      : '',
        'special_offer_code_label' => isset($cfg['special_offer_code_label']) ? (string)$cfg['special_offer_code_label'] : '',
        // Coupon binding context — cart_action_url is the dedicated POST
        // handler that processes coupon/qty submits + redirects.
        // request_uri is the send_to value the handler uses for the redirect.
        // The special_offer_code token placeholder is replaced during
        // static_values substitution.
        'request_uri'              => function_exists('get_request_uri') ? get_request_uri() : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'),
        'cart_action_url'          => $cart_action_url,
        'page_id'                  => isset($_pg_cart_pid) ? (int)$_pg_cart_pid : 0,
        'special_offer_code'       => '^^__special_offer_code^^',
        'cart_form_id'             => 'pg-cart-form-w' . (int)$widget_id,
    ));

    // The messages node prints and CONSUMES the 'shopping_cart' liveform when
    // the static tree is drawn a few lines down — before the item rows are
    // built. The gift-card and recurring-schedule blocks in those rows
    // re-fill the visitor's rejected values and mark the rejected fields, so
    // the fields are copied out first; the copy answers the three questions
    // the row builders ask and nothing ever consumes it.
    $_pg_cart_lf_snapshot = new pg_liveform_snapshot(
        ($_pg_cart_form_lf && isset($_SESSION['software']['liveforms']['shopping_cart'][0])
            && is_array($_SESSION['software']['liveforms']['shopping_cart'][0]))
            ? $_SESSION['software']['liveforms']['shopping_cart'][0] : array()
    );

    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    $output_base  = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $software_dir = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $request_uri  = function_exists('get_request_uri') ? get_request_uri() : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

    // ── Currency symbol ────────────────────────────────────────────────────
    // Important: VISITOR_CURRENCY_SYMBOL may be either a literal unicode glyph
    // (e.g. ₺) OR an HTML entity string (e.g. &#8378;) — the legacy convention
    // is documented in prepare_price_for_output(). We must NEVER pass the
    // symbol through htmlspecialchars() because that would double-encode the
    // entity form (& → &amp;) and the visitor sees the raw `&#8378;299.95`
    // string. The numeric portion of the price is digits + . + , so it's
    // already HTML-safe; we concatenate the symbol AS-IS.
    $currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '₺';
    if (!empty($cfg['currency']) && is_string($cfg['currency'])) {
        $currency_symbol = $cfg['currency'];
    }
    $fmt_money = function ($cents) use ($currency_symbol) {
        // Pinegrap stores money as integer cents. Display as "{symbol}{N.NN}".
        return $currency_symbol . number_format(((int)$cents) / 100, 2, '.', ',');
    };

    // ── Cart settings (from cfg, with sensible defaults) ───────────────────
    $shopping_cart_label    = (string)(isset($cfg['shopping_cart_label'])    ? $cfg['shopping_cart_label']    : lang('Shopping Cart'));
    $quick_add_label        = (string)(isset($cfg['quick_add_label'])        ? $cfg['quick_add_label']        : lang('Quick Add'));
    $quick_add_group_id     = (int)(isset($cfg['quick_add_product_group_id']) ? $cfg['quick_add_product_group_id'] : 0);
    $special_offer_lbl      = (string)(isset($cfg['special_offer_code_label']) ? $cfg['special_offer_code_label'] : lang('Special Offer Code'));
    $special_offer_msg      = (string)(isset($cfg['special_offer_code_message']) ? $cfg['special_offer_code_message'] : '');
    $update_button_label    = (string)(isset($cfg['update_button_label'])    ? $cfg['update_button_label']    : lang('Update'));
    $checkout_button_label  = (string)(isset($cfg['checkout_button_label'])  ? $cfg['checkout_button_label']  : lang('Checkout'));
    $next_pid_with_ship     = (int)(isset($cfg['next_page_id_with_shipping']) ? $cfg['next_page_id_with_shipping'] : 0);
    $next_pid_no_ship       = (int)(isset($cfg['next_page_id_without_shipping']) ? $cfg['next_page_id_without_shipping'] : 0);
    $cart_empty_message     = (string)(isset($cfg['cart_empty_message'])     ? $cfg['cart_empty_message']     : lang('Your cart is empty.'));

    // Persist next-page ids in session for legacy callers (remove_item_from_cart,
    // shopping_cart.php submit handler) that read these slots.
    if ($next_pid_with_ship > 0) {
        $_SESSION['ecommerce']['shipping_address_and_arrival_page_id'] = $next_pid_with_ship;
    }
    if ($next_pid_no_ship > 0) {
        $_SESSION['ecommerce']['billing_information_page_id'] = $next_pid_no_ship;
    }

    // ── Resolve the active order + summary fields ──────────────────────────
    // CRITICAL: orders.subtotal/tax/total/etc. are NOT updated until checkout
    // submission. Reading them here always returned 0 — the user reported the
    // entire summary panel showing zeros even with items in the cart.
    //
    // Compute LIVE instead:
    //   • subtotal       → sum(price * quantity) from order_items (cents)
    //   • discount       → $_SESSION['ecommerce']['order_discount'] in cents
    //                      (set by apply_offers_to_cart when an order-level
    //                      discount offer applies)
    //   • shipping       → sum(shipping_cost) from ship_tos for this order
    //                      (only meaningful after the visitor has gone through
    //                      the shipping step; usually 0 in cart phase)
    //   • tax / surcharge → 0 in cart phase (computed at checkout). Tokens
    //                      still surface so the designer can hide the rows.
    //   • total          → subtotal + tax + shipping + surcharge − discount
    //   • special_offer_code / reference_code → from orders row (string cols
    //                      are kept current by shopping_cart.php / our POST
    //                      handler above).
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
    $sub_cents      = 0;
    $tax_cents      = 0;
    $surcharge_cents= 0;
    $discount_cents = 0;
    $shipping_cents = 0;
    $total_cents    = 0;
    $special_offer_code_value = '';
    $reference_code = '';
    $items     = array();
    $has_shippable_item = false;
    $applied_offers_html  = '';
    $applied_offers_count = 0;

    // Cart form id — computed EARLY so per-item product-form inputs
    // (rendered inside the items loop) can use HTML5 form="..." to
    // submit alongside the cart-update form even when nested under
    // a different parent in the designer's row template.
    $cart_form_id = 'pg-cart-form-w' . $widget_id;
    // HTML5 `form="…"` attribute for inputs rendered OUTSIDE the cart-update
    // <form> element. The designer is free to nest the item rows anywhere, so
    // per-item inputs can't rely on being descendants of the form — this
    // attribute keeps them submitting with it regardless of DOM position.
    $_pg_cart_form_attr = ' form="' . h($cart_form_id) . '"';

    if ($order_id > 0) {
        $oid_esc = e($order_id);
        // String columns from orders — these ARE kept current.
        $order_row = db_item(
            "SELECT special_offer_code, reference_code
             FROM orders WHERE id = '$oid_esc' LIMIT 1"
        );
        if (is_array($order_row)) {
            $special_offer_code_value = (string)$order_row['special_offer_code'];
            $reference_code  = (string)$order_row['reference_code'];
        }
        // Subtotal — live sum from order_items (in cents).
        if (function_exists('get_order_subtotal')) {
            $sub_cents = (int)get_order_subtotal();
        }
        // Discount — apply_offers_to_cart writes the cents amount here.
        if (isset($_SESSION['ecommerce']['order_discount'])) {
            $discount_cents = (int)($_SESSION['ecommerce']['order_discount'] ?? 0);
        }
        // Shipping — sum across recipients' chosen methods (only meaningful
        // after the visitor has completed the shipping step). The starter
        // tree no longer includes a Nakliye row in the cart summary, but we
        // still expose the token for designers who want to surface it.
        $_pg_ship_sum = (int)db_value(
            "SELECT COALESCE(SUM(shipping_cost), 0) FROM ship_tos WHERE order_id = '$oid_esc'"
        );
        if ($_pg_ship_sum > 0) $shipping_cents = $_pg_ship_sum;

        // Tax — two strategies:
        //   (a) If the visitor already went through the checkout once,
        //       order_items.tax_total is populated → use that SUM directly.
        //   (b) Otherwise compute a PREVIEW tax based on the site's default
        //       country tax_zone × taxable products' line total. This lets
        //       the cart summary show a representative VAT/KDV figure instead
        //       of always 0 — visitor sees the real rate they'll pay (or
        //       very close, modulo state/region overrides at checkout).
        // tax_total is the tax on the whole line, so this is a plain sum.
        $tax_cents = (int)db_value(
            "SELECT COALESCE(SUM(tax_total), 0) FROM order_items WHERE order_id = '$oid_esc'"
        );
        if ($tax_cents <= 0 && (!defined('ECOMMERCE_TAX') || ECOMMERCE_TAX == true) && function_exists('get_tax_rate_for_address')) {
            $_pg_def_rate = get_default_tax_rate();
            if ($_pg_def_rate !== false) {
                // Rate per product, not one rate over the basket: a shop on a
                // value-added tax carries different rates on different articles
                // and a single multiplication would quote the wrong figure on
                // any mixed basket. COALESCE puts the site rate under the
                // products that carry none, which is what they will be charged
                // at once an address is known.
                $tax_cents = (int)db_value(
                    "SELECT COALESCE(SUM(ROUND(oi.price * oi.quantity * COALESCE(p.tax_rate, " . (float)$_pg_def_rate . ") / 100)), 0)
                     FROM order_items oi
                     LEFT JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id = '$oid_esc' AND p.taxable = 1"
                );
            }
        }

        // Surcharge — never computed in cart phase; checkout step adds it
        // (e.g. payment-method surcharges like cash-on-delivery). Token
        // remains exposed; default starter no longer renders the row.
        $surcharge_cents = 0;

        // Total — formula matches what submit_order.php writes to orders.total.
        $total_cents = $sub_cents + $tax_cents + $surcharge_cents + $shipping_cents - $discount_cents;
        if ($total_cents < 0) $total_cents = 0;

        // ── Applied / eligible offers info ──────────────────────────────
        // Two sources of offer descriptions:
        //
        // 1) APPLIED offers — already touched the cart state. Includes:
        //      • orders.discount_offer_id        (order-level discount)
        //      • order_items.offer_id (DISTINCT) (per-item discount)
        //    NOTE: ship_tos.offer_id is INTENTIONALLY excluded — shipping
        //    discounts belong on checkout pages, not the cart summary.
        //
        // 2) ELIGIBLE auto offers — enabled offers without a required code
        //    whose offer_rule (subtotal/qty threshold) is currently met AND
        //    whose action targets are present in the cart. Listed even when
        //    not yet "applied" to the order_items state, so the visitor
        //    sees what's coming. Skips shipping-only offers (same reason
        //    as #1).
        //
        // Combined and de-duplicated by id.
        $_pg_applied_ids = array();
        $_pg_doi = (int)db_value("SELECT discount_offer_id FROM orders WHERE id = '$oid_esc' LIMIT 1");
        if ($_pg_doi > 0) $_pg_applied_ids[$_pg_doi] = 1;
        $_pg_oi_offers = db_items(
            "SELECT DISTINCT offer_id FROM order_items WHERE order_id = '$oid_esc' AND offer_id > 0"
        );
        if (is_array($_pg_oi_offers)) {
            foreach ($_pg_oi_offers as $_pg_oi) { $_pg_applied_ids[(int)$_pg_oi['offer_id']] = 1; }
        }

        // Eligible auto offers — pull every enabled offer in date range that
        // either doesn't require a code OR matches the order's special_offer_
        // code. Then check BOTH the offer's rule AND its actions:
        //
        //   • Rule check: validate_offer() — subtotal/quantity threshold met?
        //   • Action check: for 'discount product' actions, the targeted
        //     product must actually be in the cart. Otherwise an offer like
        //     "Yeşil Sandalye'de %10 indirim" would falsely list itself even
        //     when only the Mocha chair is in the cart (rule satisfied but
        //     no matching item to discount).
        //
        // Excluded action types:
        //   • 'discount shipping' — shipping is calculated during the
        //     checkout steps, so it is not shown in the cart. The cart
        //     screen only surfaces order/item level offers.
        //   • 'add product' — pending offer (visitor must opt-in via button)
        //     not a passive applied/eligible offer.
        //
        // Only 'discount order' (always-applies-when-rule-met) and
        // 'discount product' (when target product is in cart) make the list.
        $_pg_auto_ids   = array();
        $_pg_auto_label = array();   // id => 'description'
        $_pg_order_code = (string)db_value(
            "SELECT special_offer_code FROM orders WHERE id = '$oid_esc' LIMIT 1"
        );
        $_pg_offer_code = '';
        if ($_pg_order_code !== '' && function_exists('get_offer_code_for_special_offer_code')) {
            $_pg_offer_code = (string)get_offer_code_for_special_offer_code($_pg_order_code);
        }
        $_pg_auto_query = "SELECT id, description, code, require_code
                           FROM offers
                           WHERE status = 'enabled'
                             AND start_date <= CURRENT_DATE()
                             AND CURRENT_DATE() <= end_date
                             AND (require_code = 0 "
                          . ($_pg_offer_code !== ''
                                ? "OR code = '" . e($_pg_offer_code) . "'"
                                : "")
                          . ")";
        $_pg_auto_rows = db_items($_pg_auto_query);
        if (is_array($_pg_auto_rows) && function_exists('validate_offer')) {
            // Distinct product_ids in the current cart — used to gate
            // 'discount product' offers.
            $_pg_cart_pids = array();
            $_pg_cart_pid_rows = db_items(
                "SELECT DISTINCT product_id FROM order_items WHERE order_id = '$oid_esc' AND product_id > 0"
            );
            if (is_array($_pg_cart_pid_rows)) {
                foreach ($_pg_cart_pid_rows as $_pg_cpr) {
                    $_pg_cart_pids[(int)$_pg_cpr['product_id']] = 1;
                }
            }
            foreach ($_pg_auto_rows as $_pg_aor) {
                $_pg_aid = (int)$_pg_aor['id'];
                if ($_pg_aid <= 0) continue;
                // Step 1 — rule must be met (subtotal/quantity threshold).
                if (validate_offer($_pg_aid, 0, $sub_cents) !== true) continue;
                // Step 2 — at least one of the offer's actions must be an
                // applicable order-level or item-level discount whose target
                // is in the cart. Shipping/add-product actions don't count
                // toward "show in sepet özeti".
                $_pg_actions = db_items(
                    "SELECT oa.type, oa.discount_product_product_id, oa.discount_product_group_id, oa.discount_product_target
                     FROM offers_offer_actions_xref xref
                     LEFT JOIN offer_actions oa ON oa.id = xref.offer_action_id
                     WHERE xref.offer_id = '$_pg_aid'"
                );
                if (!is_array($_pg_actions) || empty($_pg_actions)) continue;
                // First pass: if ANY action is shipping-related, skip the
                // ENTIRE offer (mixed-type defensive check). Shipping
                // discounts stay out of the cart — they are calculated in
                // the later checkout steps. Shipping discounts only become
                // meaningful after address+method selection, so they belong
                // on checkout pages, not the cart summary.
                $_pg_has_shipping = false;
                foreach ($_pg_actions as $_pg_act) {
                    if (isset($_pg_act['type']) && $_pg_act['type'] === 'discount shipping') {
                        $_pg_has_shipping = true;
                        break;
                    }
                }
                if ($_pg_has_shipping) continue;
                // Second pass: at least one action must be applicable.
                $_pg_has_applicable = false;
                foreach ($_pg_actions as $_pg_act) {
                    $_pg_at = isset($_pg_act['type']) ? (string)$_pg_act['type'] : '';
                    if ($_pg_at === 'discount order') {
                        $_pg_has_applicable = true;
                        break;
                    }
                    if ($_pg_at === 'discount product') {
                        // A group target counts when the cart holds any
                        // product of that group, the same test the discount
                        // loop applies item by item.
                        if (isset($_pg_act['discount_product_target']) && ($_pg_act['discount_product_target'] === 'cheapest')) {
                            if (!empty($_pg_cart_pids)) {
                                $_pg_has_applicable = true;
                                break;
                            }
                        }
                        $_pg_group = isset($_pg_act['discount_product_group_id']) ? (int)$_pg_act['discount_product_group_id'] : 0;
                        if ($_pg_group > 0) {
                            foreach (pg_offer_group_products($_pg_group) as $_pg_gp) {
                                if (isset($_pg_cart_pids[(int)$_pg_gp])) {
                                    $_pg_has_applicable = true;
                                    break;
                                }
                            }
                            if ($_pg_has_applicable) {
                                break;
                            }
                        } else {
                            $_pg_target = (int)$_pg_act['discount_product_product_id'];
                            if ($_pg_target > 0 && isset($_pg_cart_pids[$_pg_target])) {
                                $_pg_has_applicable = true;
                                break;
                            }
                        }
                    }
                    // 'add product' — pending offers, surfaced separately
                    // (not in the auto-applied list).
                }
                if ($_pg_has_applicable) {
                    $_pg_auto_ids[$_pg_aid]   = 1;
                    $_pg_auto_label[$_pg_aid] = pg_offer_public_label($_pg_aor);
                }
            }
        }

        // Merge: applied first, eligible-only after (de-dup by id).
        $_pg_all_ids = $_pg_applied_ids + $_pg_auto_ids;
        $applied_offers_html  = '';
        $applied_offers_count = count($_pg_all_ids);
        if ($applied_offers_count > 0) {
            // Pull descriptions for any IDs we don't already have a label for.
            $_pg_need_lookup = array_diff(array_keys($_pg_all_ids), array_keys($_pg_auto_label));
            $_pg_lookup_labels = $_pg_auto_label;
            if (!empty($_pg_need_lookup)) {
                $_pg_ids_str = implode(',', array_map('intval', $_pg_need_lookup));
                // The code comes along because the offer editor's message
                // field is optional: an offer without one used to drop out of
                // this list, so the total carried a discount the page never
                // explained.
                $_pg_desc_rows = db_items(
                    "SELECT id, code, description FROM offers WHERE id IN ($_pg_ids_str)"
                );
                if (is_array($_pg_desc_rows)) {
                    foreach ($_pg_desc_rows as $_pg_dr) {
                        $_pg_lookup_labels[(int)$_pg_dr['id']] = pg_offer_public_label($_pg_dr);
                    }
                }
            }
            $_pg_offer_items = '';
            $_pg_offer_shown = 0;
            foreach ($_pg_all_ids as $_pg_oid_each => $_unused) {
                $_pg_desc = isset($_pg_lookup_labels[$_pg_oid_each]) ? $_pg_lookup_labels[$_pg_oid_each] : '';
                if ($_pg_desc === '') continue;
                $_pg_offer_items .= '<li>' . h($_pg_desc) . '</li>';
                $_pg_offer_shown++;
            }
            // The token counts what the visitor can actually see, so a
            // designer binding on it does not announce offers the list does
            // not name.
            $applied_offers_count = $_pg_offer_shown;
            if ($_pg_offer_shown > 0) {
                $applied_offers_html  = '<div class="pg-cart-applied-offers alert alert-success py-2 px-3 mb-2">';
                $applied_offers_html .= '<div class="fw-semibold small mb-1"><i class="bi bi-tag-fill me-1"></i>'
                                      . h(($_pg_offer_shown > 1) ? lang('Applied Offers') : lang('Applied Offer')) . '</div>';
                $applied_offers_html .= '<ul class="mb-0 ps-3 small">' . $_pg_offer_items . '</ul></div>';
            }
        }

        // Build the items list — join products to get address_name + image_name
        // for the per-row detail link. Detail URL preference:
        //   1) cfg.detail_page_id (designer-picked)
        //   2) session shopping_cart_page_id (legacy)
        //   3) bare /<address_name> as a last-ditch fallback (matches legacy
        //      `OUTPUT_PATH . encode_url_path($address_name)` behaviour for
        //      sites that route product slugs at the root).
        $detail_page_name = '';
        if (!empty($cfg['detail_page_id'])) {
            $detail_page_name = (string)db_value(
                "SELECT page_name FROM page WHERE page_id = '" . (int)$cfg['detail_page_id'] . "' LIMIT 1"
            );
        }
        // SELECT also includes products.short_description + full_description
        // because Pinegrap installs typically use products.short_description
        // as the user-facing display name (products.name is treated as an
        // internal/SKU code). Surfaced as `__item_short_description` and
        // `__item_description` tokens so designer templates can bind to
        // either — the cart starter defaults to short_description.
        // Also pull `p.price` (original sticker) so we can detect when
        // apply_offers_to_cart() has mutated `oi.price` below the sticker
        // — that's the trigger for the strike-through visual the user
        // wants in cart rows. When equal, no discount → no strike-through.
        // Plus `p.id` + `p.form` + `p.form_quantity_type` so we can render
        // the per-row product-form data (a gift card's recipient email,
        // sender name, message, delivery date, …) when the product carries a
        // form (`p.form = 1`).
        // `p.form_name` mirrors what the express order widget shows above the
        // custom form block — keeps cart and checkout visually consistent.
        $rows = db_items(
            "SELECT oi.id AS item_id, oi.product_id, oi.product_name, oi.quantity, oi.price,
                    oi.added_by_offer,
                    p.address_name, p.image_name, p.shippable,
                    p.short_description, p.full_description,
                    p.price AS product_orig_price,
                    p.form AS product_has_form,
                    p.form_name,
                    p.form_quantity_type,
                    p.selection_type,
                    p.inventory, p.inventory_quantity, p.backorder,
                    p.out_of_stock_message,
                    p.gift_card AS product_is_gift_card,
                    oi.ship_to_id, st.ship_to_name,
                    oi.calendar_event_id, oi.recurrence_number,
                    p.recurring, p.payment_period,
                    p.recurring_schedule_editable_by_customer,
                    p.number_of_payments AS product_number_of_payments,
                    p.start AS recurring_start_days,
                    oi.recurring_start_date, oi.recurring_payment_period,
                    oi.recurring_number_of_payments
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN ship_tos st ON oi.ship_to_id = st.id
             WHERE oi.order_id = '$oid_esc'
             ORDER BY oi.ship_to_id ASC, oi.id ASC"
        );

        // A recurring row gets its schedule written the first time it is
        // shown. The columns are only ever written by a cart update; the
        // legacy cart forces one on the way to checkout (its checkout button
        // posts the form), this widget's checkout is a plain link, so a
        // visitor who never presses Update would reach the gateway with a
        // blank schedule. Product defaults, same as the legacy update writes
        // for a row the customer may not edit; the customer's own choice
        // (cart_action.php) overwrites them.
        if (is_array($rows)) {
            foreach ($rows as $_ri => $_rr) {
                if ((int)($_rr['recurring'] ?? 0) !== 1) continue;
                if ((string)($_rr['recurring_payment_period'] ?? '') !== '') continue;
                $_def = _pg_cart_recurring_defaults($_rr);
                db("UPDATE order_items
                    SET recurring_payment_period = '" . e($_def['period']) . "',
                        recurring_number_of_payments = '" . (int)$_def['payments'] . "',
                        recurring_start_date = '" . e($_def['start_date']) . "'
                    WHERE id = '" . (int)$_rr['item_id'] . "' AND order_id = '$oid_esc'");
                $rows[$_ri]['recurring_payment_period']     = $_def['period'];
                $rows[$_ri]['recurring_number_of_payments'] = $_def['payments'];
                $rows[$_ri]['recurring_start_date']         = $_def['start_date'];
            }
        }

        // Pre-load saved gift-card recipient data so the inputs re-fill across
        // submit roundtrips. Indexed item_id → quantity_number. Mirrors the
        // express order widget's preload (see _render_system_widget_express_order).
        $cart_gift_card_data = array();
        if (is_array($rows) && $rows) {
            $_has_gc = false;
            foreach ($rows as $_r) { if (!empty($_r['product_is_gift_card'])) { $_has_gc = true; break; } }
            if ($_has_gc) {
                $gc_rows = db_items(
                    "SELECT order_item_id, quantity_number, from_name,
                            recipient_email_address, message, delivery_date
                     FROM order_item_gift_cards
                     WHERE order_id = '$oid_esc'"
                );
                if (is_array($gc_rows)) {
                    foreach ($gc_rows as $g) {
                        $cart_gift_card_data[(int)$g['order_item_id']][(int)$g['quantity_number']] = $g;
                    }
                }
            }
        }
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $qty        = (int)$r['quantity'];
                $unit_cents = (int)$r['price'];                  // effective (post-offer)
                $orig_cents = (int)($r['product_orig_price'] ?? $unit_cents);  // sticker
                $line_cents = $unit_cents * $qty;
                $orig_line_cents = $orig_cents * $qty;
                $has_d      = ($unit_cents < $orig_cents) ? 1 : 0;
                if (!empty($r['shippable'])) $has_shippable_item = true;

                $image_url = !empty($r['image_name'])
                    ? $output_base . encode_url_path((string)$r['image_name'])
                    : '';
                if (!empty($r['address_name'])) {
                    if ($detail_page_name !== '') {
                        // Path-style: /<detail_page_name>/<address_name>
                        $detail_url = $output_base . encode_url_path($detail_page_name) . '/' . encode_url_path((string)$r['address_name']);
                    } else {
                        $detail_url = $output_base . encode_url_path((string)$r['address_name']);
                    }
                } else {
                    $detail_url = '#';
                }
                // Build with RAW `&` separators — get_token_query_string_field()
                // returns `&amp;token=...` (already HTML-escaped) which would
                // double-encode through the h() pass below, breaking the
                // `token=` query param name. Use the bare token directly so
                // the single h() in row_values produces a valid URL.
                $remove_url = $output_base . $software_dir . '/remove_item_from_cart.php'
                    . '?order_item_id=' . (int)$r['item_id']
                    . '&screen=shopping_cart'
                    . '&send_to=' . urlencode($request_uri)
                    . '&token=' . (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '');

                // Per-row product-form HTML — populated only for products
                // that carry an order form (a gift card's recipient email,
                // message, delivery date …). Empty string for everything
                // else, so the designer's `^^__item_form_data_html^^`
                // token quietly disappears for non-form rows.
                $form_data_html = '';
                if (!empty($r['product_has_form'])) {
                    // Pass $cart_form_id so the inputs use HTML5 form="..."
                    // and submit alongside the cart-update form even when
                    // designer's layout nests them under a different parent.
                    // The admin-set `form_name` becomes the fieldset legend —
                    // no separate heading, no icon. Anything printed above the
                    // block would be copy the operator never wrote and can't
                    // change from the product editor.
                    $cart_form_name = isset($r['form_name']) ? trim((string)$r['form_name']) : '';
                    $rendered_form = _pg_render_cart_item_form_data(
                        (int)$r['item_id'],
                        (int)$r['product_id'],
                        $qty,
                        (string)$r['product_name'],
                        (string)$r['form_quantity_type'],
                        $cart_form_id,
                        $cart_form_name !== '' ? (string)lang($cart_form_name) : ''
                    );
                    if ($rendered_form !== '') {
                        $form_data_html = $rendered_form;
                    }
                }

                // Gift-card recipient block. Gift cards are NOT driven by
                // `products.form` — they use their own `products.gift_card`
                // flag and a fixed four-field block (recipient e-mail, from
                // name, message, delivery date) stored in order_item_gift_cards.
                // The legacy cart (get_shopping_cart.php) and the express order
                // widget both render it; this widget did not, so a cart holding
                // a gift card showed no recipient inputs at all — and because
                // `recipient_email_address` is required further down the flow,
                // checkout silently refused to advance.
                $gift_card_html = '';
                if (!empty($r['product_is_gift_card']) && $qty > 0) {
                    $_gc_iid   = (int)$r['item_id'];
                    $_gc_count = min($qty, 100);   // same cap as legacy
                    $_gc_rows_html = '';
                    for ($_qn = 1; $_qn <= $_gc_count; $_qn++) {
                        $_fp = 'order_item_' . $_gc_iid . '_quantity_number_' . $_qn . '_gift_card_';
                        // Value precedence: visitor's just-submitted value (the
                        // liveform copy taken before the messages node consumed
                        // it) → saved DB row → empty.
                        $_gcv = function ($col) use ($_fp, $_pg_cart_lf_snapshot, $cart_gift_card_data, $_gc_iid, $_qn) {
                            $key = $_fp . $col;
                            $v = $_pg_cart_lf_snapshot->get_field_value($key);
                            if ($v !== '' && $v !== null) return (string)$v;
                            $mapped = function_exists('_eo_gc_col_map') ? _eo_gc_col_map($col) : $col;
                            if (isset($cart_gift_card_data[$_gc_iid][$_qn][$mapped])) {
                                return (string)$cart_gift_card_data[$_gc_iid][$_qn][$mapped];
                            }
                            return '';
                        };
                        // Bootstrap 5.3 CONTROL classes, but no LAYOUT opinion:
                        // every field is its own full-width row, stacked. The
                        // earlier version paired fields into col-md-6 / col-md-8
                        // / col-md-4 because it happened to look tidy for these
                        // four known fields — but the very same renderer shape is
                        // used for operator-defined product forms, where the next
                        // field could be a select, a rich-text editor or a file
                        // picker. Any fixed pairing falls apart the moment the
                        // field mix changes, leaving half the form at col-6 and
                        // half at col-12. Full-width stacking is the only
                        // arrangement that stays correct for every field order.
                        // <fieldset> + <legend> — identical shape to the express
                        // order widget and to the product-form renderer, so a
                        // gift card looks the same wherever the visitor meets it.
                        // Legend is just "Gift card" (+ "(n / m)"): the wording
                        // legacy uses, with no invented banner above the box.
                        $_gc_legend = '<legend class="float-none w-auto px-2 fs-6 fw-semibold">'
                                    . h(lang('Gift card'))
                                    . ($_gc_count > 1 ? ' (' . $_qn . ' / ' . $_gc_count . ')' : '')
                                    . '</legend>';
                        $_gc_field = function ($suffix, $label, $control, $required = false, $help = '') use ($_fp) {
                            return '<div class="mb-3 pg-cart-gc-field" data-pg-gc-field="' . h($suffix) . '">'
                                 . '<label class="form-label" for="' . h($_fp . $suffix) . '">' . h($label)
                                 . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
                                 . $control
                                 . ($help !== '' ? '<div class="form-text">' . h($help) . '</div>' : '')
                                 . '</div>';
                        };
                        $_gc_rows_html .=
                            '<fieldset class="pg-cart-gc-set border rounded p-3 mb-3" data-pg-gc-set="' . $_qn . '">' . $_gc_legend
                          . $_gc_field('recipient_email_address', lang('Recipient Email'),
                                '<input type="email" required class="form-control"' . $_pg_cart_form_attr
                              . ' id="' . h($_fp) . 'recipient_email_address" name="' . h($_fp) . 'recipient_email_address"'
                              . ' value="' . h($_gcv('recipient_email_address')) . '">', true)
                          . $_gc_field('from_name', lang('From Name'),
                                '<input type="text" class="form-control"' . $_pg_cart_form_attr
                              . ' id="' . h($_fp) . 'from_name" name="' . h($_fp) . 'from_name"'
                              . ' value="' . h($_gcv('from_name')) . '" maxlength="100">')
                          . $_gc_field('message', lang('Message'),
                                '<textarea class="form-control" rows="3"' . $_pg_cart_form_attr
                              . ' id="' . h($_fp) . 'message" name="' . h($_fp) . 'message"'
                              . ' maxlength="500">' . h($_gcv('message')) . '</textarea>')
                          . $_gc_field('delivery_date', lang('Delivery Date'),
                                '<input type="date" class="form-control"' . $_pg_cart_form_attr
                              . ' id="' . h($_fp) . 'delivery_date" name="' . h($_fp) . 'delivery_date"'
                              . ' value="' . h($_gcv('delivery_date')) . '">', false,
                                lang('Leave blank to send immediately.'))
                          . '</fieldset>';
                    }
                    $gift_card_html =
                        '<div class="pg-cart-gift-cards" data-pg-cart-gc-item="' . $_gc_iid . '">'
                      . $_gc_rows_html
                      . '</div>';
                }

                // Recurring schedule the customer may set (frequency, number
                // of payments, start date). Only when the product allows it;
                // every other recurring row keeps the product's schedule and
                // shows it through the read-only tokens.
                $recurring_editable = ((int)($r['recurring'] ?? 0) === 1)
                                   && ((int)($r['recurring_schedule_editable_by_customer'] ?? 0) === 1);
                $recurring_schedule_html = $recurring_editable
                    ? _pg_render_cart_item_recurring_schedule($r, $_pg_cart_lf_snapshot, $_pg_cart_form_attr)
                    : '';
                // Discount strike-through values — emit EMPTY when no
                // discount applies so designer-bound strike-through spans
                // auto-hide (via the `[data-pg-bind*="original_price"]:empty`
                // CSS rule). Same auto-hide pattern as catalog/product
                // detail discount tokens.
                $unit_orig_price_str = $has_d ? $fmt_money($orig_cents) : '';
                $line_orig_total_str = $has_d ? $fmt_money($orig_line_cents) : '';
                $items[] = array(
                    'item_id'                  => (int)$r['item_id'],
                    'name'                     => (string)$r['product_name'],
                    'short_description'        => isset($r['short_description']) ? (string)$r['short_description'] : '',
                    'description'              => isset($r['full_description'])  ? (string)$r['full_description']  : '',
                    'qty'                      => $qty,
                    'unit_price'               => $fmt_money($unit_cents),
                    'line_total'               => $fmt_money($line_cents),
                    'image'                    => $image_url,
                    'url'                      => $detail_url,
                    'remove_url'               => $remove_url,
                    // Discount tokens for cart rows — same visual as
                    // catalog cards / product detail / cross-sell so the
                    // visitor sees a consistent "this was discounted"
                    // strike-through everywhere their product is shown.
                    'unit_orig_price'          => $unit_orig_price_str,
                    'line_orig_total'          => $line_orig_total_str,
                    'has_discount'             => (string)$has_d,
                    'unit_price_block'         => _pg_format_price_with_discount($orig_cents, $unit_cents, $currency_symbol),
                    'line_total_block'         => _pg_format_price_with_discount($orig_line_cents, $line_cents, $currency_symbol),
                    // Per-row product-form data block (read-only review).
                    'form_data_html'           => $form_data_html,
                    'gift_card_html'           => $gift_card_html,
                    'recurring_editable'       => $recurring_editable ? 1 : 0,
                    'recurring_schedule_html'  => $recurring_schedule_html,
                    // Row kind — drives which control the qty column shows.
                    //   donation      → currency amount box, no unit price
                    //   added_by_offer→ plain number, not editable (the offer
                    //                   decides the quantity, not the visitor)
                    //   otherwise     → the normal qty stepper
                    // Legacy get_shopping_cart.php:770-815 branches the same way.
                    'selection_type'           => isset($r['selection_type']) ? (string)$r['selection_type'] : '',
                    'added_by_offer'           => !empty($r['added_by_offer']) ? 1 : 0,
                    'line_cents'               => $line_cents,
                    // Inventory feeds the low-stock warning tokens
                    // (ECOMMERCE_LOW_STOCK_THRESHOLD, 2026.1.28) and the
                    // per-row out-of-stock notice below. These columns were
                    // never selected before, so every isset($item['inventory'])
                    // read false and the low-stock tokens could never fire.
                    'inventory'                => (int)($r['inventory'] ?? 0),
                    'inventory_quantity'       => (int)($r['inventory_quantity'] ?? 0),
                    'backorder'                => (int)($r['backorder'] ?? 0),
                    'out_of_stock_message'     => (string)($r['out_of_stock_message'] ?? ''),
                    // Recipient grouping. The query now orders by ship_to_id
                    // first (matching legacy, which loops ship_tos in id order
                    // and items within each), so rows for one recipient are
                    // contiguous and the loop below can mark group starts.
                    'ship_to_id'               => (int)($r['ship_to_id'] ?? 0),
                    'ship_to_name'             => (string)($r['ship_to_name'] ?? ''),
                    // Calendar reservation rows carry the event they booked.
                    // Legacy appends name + date range to the description
                    // (get_shopping_cart.php:754); we expose it as its own
                    // token so the designer can place it independently.
                    'calendar_event_id'        => (int)($r['calendar_event_id'] ?? 0),
                    'recurrence_number'        => (int)($r['recurrence_number'] ?? 0),
                    // Recurring subscription rows. Legacy splits the cart into
                    // "Today's Charges" and "Recurring Charges"
                    // (get_shopping_cart.php:1155). A recurring product still
                    // counts as "today" when its first payment is today —
                    // otherwise the visitor sees a ₺0 total for an order they
                    // are about to be charged for.
                    'recurring'                => (int)($r['recurring'] ?? 0),
                    'payment_period'           => (string)($r['payment_period'] ?? ''),
                    'recurring_start_date'     => (string)($r['recurring_start_date'] ?? ''),
                    'recurring_payment_period' => (string)($r['recurring_payment_period'] ?? ''),
                    'recurring_number_of_payments' => (int)($r['recurring_number_of_payments'] ?? 0),
                );
            }
        }
    }
    // ── Recurring vs. today's charges ────────────────────────────────────
    // Legacy rule (get_shopping_cart.php:1155): a row belongs to "Today's
    // Charges" when the product isn't recurring at all, OR its first payment
    // falls today. The ClearCommerce carve-out is kept for fidelity even
    // though Pinegrap ships with Iyzipay — if someone runs that gateway, the
    // legacy behaviour is what their bookkeeping expects.
    $_today_cents     = 0;
    $_recurring_cents = 0;
    $_today_str = date('Y-m-d');
    $_cc_gateway = (defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == true)
                && (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'ClearCommerce');
    foreach ($items as $_i => $_it) {
        $_start = (string)$_it['recurring_start_date'];
        $_starts_today = ($_start === '' || $_start === '0000-00-00' || $_start === $_today_str);
        $_is_rec = !((int)$_it['recurring'] === 0 || ($_starts_today && !$_cc_gateway));
        $items[$_i]['is_recurring'] = $_is_rec ? 1 : 0;
        if ($_is_rec) $_recurring_cents += (int)$_it['line_cents'];
        else          $_today_cents     += (int)$_it['line_cents'];
    }
    // Group rows so each bucket is contiguous: recipient first (matching the
    // SQL order), then today's charges before recurring, then insertion order.
    // Done in PHP because is_recurring depends on a date comparison the query
    // can't express cheaply.
    usort($items, function ($a, $b) {
        if ($a['ship_to_id'] !== $b['ship_to_id']) return $a['ship_to_id'] - $b['ship_to_id'];
        if ($a['is_recurring'] !== $b['is_recurring']) return $a['is_recurring'] - $b['is_recurring'];
        return $a['item_id'] - $b['item_id'];
    });

    $cart_count = count($items);

    // Empty cart notice — surfaces through the Messages content node
    // (auto-injected at the top of the cart widget container by
    // `_pg_inject_messages_node`). This puts the alert INSIDE the system
    // widget but OUTSIDE the loop_area (the loop is the products list);
    // exactly the placement legacy `get_shopping_cart.php` used via
    // `$form->add_notice()`. Re-added on every render so the notice
    // persists as long as the cart stays empty (Messages render consumes
    // it, but the next render re-adds it if the cart is still empty).
    if ($cart_count === 0 && $cart_empty_message !== '' && $_pg_cart_form_lf) {
        $_pg_cart_form_lf->add_notice($cart_empty_message);
    }

    // ── Next-page redirect URL ─────────────────────────────────────────────
    // Pick the shipping vs. no-shipping target based on whether the cart has
    // at least one shippable item. Falls back to the legacy session slots,
    // then to '#' (so the button is visible but inert).
    $next_pid = $has_shippable_item ? $next_pid_with_ship : $next_pid_no_ship;
    if ($next_pid <= 0) {
        if ($has_shippable_item && !empty($_SESSION['ecommerce']['shipping_address_and_arrival_page_id'])) {
            $next_pid = (int)$_SESSION['ecommerce']['shipping_address_and_arrival_page_id'];
        } elseif (!empty($_SESSION['ecommerce']['billing_information_page_id'])) {
            $next_pid = (int)$_SESSION['ecommerce']['billing_information_page_id'];
        }
    }
    $checkout_url = '#';
    if ($next_pid > 0) {
        $next_page_name = db_value("SELECT page_name FROM page WHERE page_id = '" . e($next_pid) . "' LIMIT 1");
        if ($next_page_name) $checkout_url = $output_base . encode_url_path((string)$next_page_name);
    }

    // ── Special offer code (coupon) form ───────────────────────────────────
    // Posts to cart_action.php (dedicated handler) so the redirect after
    // applying a coupon doesn't get tripped up by the page-render output
    // pipeline. The handler validates the code, updates orders.special_
    // offer_code, refreshes offers, then 302s back to send_to.
    $special_offer_form = '<form method="post" action="' . h($cart_action_url)
                        . '" class="pg-cart-coupon-form">'
                        . get_token_field()
                        . '<input type="hidden" name="send_to" value="' . h($request_uri) . '">'
                        . '<input type="hidden" name="page_id" value="' . (isset($_pg_cart_pid) ? (int)$_pg_cart_pid : 0) . '">'
                        . '<div class="input-group input-group-sm">'
                        .   '<input type="text" name="special_offer_code" class="form-control" value="' . h($special_offer_code_value) . '" placeholder="' . h($special_offer_lbl) . '">'
                        .   '<button type="submit" name="submit_special_offer_code" value="1" class="btn btn-outline-primary">' . h($special_offer_lbl) . '</button>'
                        . '</div>'
                        . '</form>';

    // ── Quantity-update form (HTML5 form-association strategy) ─────────────
    // Cannot wrap the whole cart in <form>: the coupon code form would be
    // nested inside it and HTML5 closes the outer form on a nested <form>
    // open tag — orphaning the closing </form> and breaking submission.
    //
    // Instead: render an empty placeholder <form id="pg-cart-form-XXX">
    // before the cart body, and have each qty input + the Update button
    // declare `form="pg-cart-form-XXX"` (HTML5 attribute that lets inputs
    // outside a form still submit through it). The coupon form sits
    // independently — no nesting, no breakage.
    // Cart-update form points at cart_action.php (dedicated POST handler
    // that processes + redirects). Posting to the current URL caused a
    // "form yeniden gönderme" prompt because the in-widget header()
    // PRG sometimes fired AFTER intermediate output buffering, so the
    // browser stayed on the POST URL.
    // $cart_action_url is computed earlier (above the bindings call) so the
    // bindings context can use it.
    // NOTE: $cart_form_id is now computed BEFORE the items loop so per-item
    // form-data inputs (rendered by _pg_render_cart_item_form_data) can use
    // HTML5 form="..." to bind to the cart-update form even when the inputs
    // sit nested under a different parent element.
    $update_form_html = '<form id="' . $cart_form_id . '" method="post" action="'
                      . h($cart_action_url)
                      . '" class="pg-cart-update-form" style="display:none">'
                      . get_token_field()
                      . '<input type="hidden" name="send_to" value="' . h($request_uri) . '">'
                      . '<input type="hidden" name="page_id" value="' . (isset($_pg_cart_pid) ? (int)$_pg_cart_pid : 0) . '">'
                      . '</form>';
    // Diagnostic log — same file as cart_action.php uses, so the operator
    // can inspect both the render-side action URL and the POST-side receipt
    // in one stream. Strips after we confirm everything works end-to-end.
    @file_put_contents(
        PG_FUNCTIONS_DIR . '/data/cart_action.log',
        '[' . date('c') . '] RENDER cart widget · form_id=' . $cart_form_id
            . ' · action=' . $cart_action_url
            . ' · request_uri=' . $request_uri
            . ' · widget_id=' . (int)$widget_id . "\n",
        FILE_APPEND | LOCK_EX
    );

    // Update button HTML — submits through the cart form via form="..." attr.
    // w-100 so it fills the d-grid container in the default layout (designer
    // can override the class via wrapper if a smaller button is preferred).
    //
    // `formnovalidate` is intentional: per-item product-form fields
    // (rendered by _pg_render_cart_item_form_data) bind to this same cart
    // form via HTML5 `form="..."`. Without `formnovalidate`, clicking
    // Update on a cart that contains a product with required form fields
    // (e.g. a gift card's recipient name/email) would block the qty update
    // until every required field is filled — even when the visitor just
    // wants to bump the quantity. Required-field enforcement happens at
    // checkout, not at every quantity update.
    $update_button_html = '<button type="submit" name="submit_update_cart" value="1" form="' . h($cart_form_id) . '" formnovalidate class="btn btn-outline-secondary w-100">' . h($update_button_label) . '</button>';

    // Checkout button — posts via standard <a> to the resolved next page.
    // w-100 to match the d-grid container in the default layout.
    $checkout_button_html = '<a href="' . h($checkout_url) . '" class="btn btn-primary w-100">' . h($checkout_button_label) . '</a>';

    // ── Build per-row HTML ─────────────────────────────────────────────────
    // Each row exposes both display tokens (price, total, name) AND an
    // editable quantity <input> (so the visitor can change qty + hit Update).
    // CRITICAL: do NOT h() the price strings — they may contain HTML entity
    // currency symbols (&#8378;) that htmlspecialchars would double-encode.
    $loop_rendered = '';
    // Recipient-grouping cursor: tracks which ship_to the previous row
    // belonged to, so the loop can mark the first row of each run. Starts at
    // 0 because ship_to_id 0 means "myself" and never gets a heading.
    $_pg_prev_rcp = 0;
    // Same cursor for the recurring block. Only ever flips false→true because
    // usort() puts every recurring row after every non-recurring one.
    $_pg_prev_rec = false;
    if ($loop_template !== '' && $cart_count > 0) {
        foreach ($items as $idx => $item) {
            // Qty input emitted as an input-group with attached -/+ steppers.
            // JS (see _pg_qty_stepper_inline_js below) handles the click → input
            // bump. Designers who want full control can replace this token with
            // a real <input> bound to `cart_qty` + <button>s bound to
            // `cart_qty_inc` / `cart_qty_dec` inside their loop_area template.
            // The qty column is not one control but three, chosen by row kind
            // — same branching as legacy get_shopping_cart.php:770-815.
            $_it_is_donation = (isset($item['selection_type']) && $item['selection_type'] === 'donation');
            $_it_by_offer    = !empty($item['added_by_offer']);

            if ($_it_is_donation) {
                // A donation has no meaningful quantity — the visitor edits the
                // AMOUNT. Field name `donations[<item_id>]` matches what the
                // legacy cart posts, so the handler logic is shared.
                // type=text, not number: the value is displayed grouped
                // ("1.250,00") and a number input would reject the separators.
                $_don_amount = number_format((int)$item['line_cents'] / 100, 2, '.', ',');
                $qty_input = '<div class="input-group input-group-sm pg-cart-donation" style="max-width:11rem;display:inline-flex">'
                           // Symbol emitted RAW, like every other price in this
                           // widget: VISITOR_CURRENCY_SYMBOL may already be an
                           // entity ("&#8378;") and h() would turn it into the
                           // literal text "&#8378;". See $fmt_money above.
                           .   '<span class="input-group-text">' . $currency_symbol . '</span>'
                           .   '<input type="text" inputmode="decimal" name="donations[' . (int)$item['item_id'] . ']"'
                           .         ' value="' . h($_don_amount) . '" form="' . h($cart_form_id) . '"'
                           .         ' class="form-control form-control-sm text-end"'
                           .         ' aria-label="' . h(lang('Amount')) . '">'
                           . '</div>';
            } elseif ($_it_by_offer) {
                // Added by an offer: the promotion fixes the quantity. Showing
                // an editable box would invite a change the next
                // apply_offers_to_cart() pass silently reverts.
                $qty_input = '<span class="pg-cart-qty-fixed">' . (int)$item['qty'] . '</span>';
            } else {
                // max-width rather than width: inside a narrow grid column a hard
                // 9rem forced horizontal overflow instead of shrinking.
                $qty_input = '<div class="input-group input-group-sm pg-qty-stepper" style="max-width:9rem;display:inline-flex">'
                           .   '<button type="button" class="btn btn-outline-secondary" data-pg-qty-action="dec"'
                           .          ' aria-label="' . h(lang('Decrease')) . '" tabindex="-1">&minus;</button>'
                           .   '<input type="number" name="quantities[' . (int)$item['item_id'] . ']" value="' . (int)$item['qty']
                           .         '" min="0" step="1" form="' . h($cart_form_id) . '" class="form-control form-control-sm text-center">'
                           .   '<button type="button" class="btn btn-outline-secondary" data-pg-qty-action="inc"'
                           .          ' aria-label="' . h(lang('Increase')) . '" tabindex="-1">+</button>'
                           . '</div>';
            }
            $row_values = array(
                '__item_id'                => (string)(int)$item['item_id'],   // numeric ID, safe
                '__item_name'              => h($item['name']),                 // products.name (often SKU)
                '__item_short_description' => h($item['short_description']),    // products.short_description (display title)
                '__item_description'       => h($item['description']),          // products.full_description (long text)
                '__item_qty'               => (string)$item['qty'],            // numeric, safe
                '__item_qty_input'         => $qty_input,                       // editable input (legacy token)
                // Item price tokens — when discount is active, expand to
                // the unified strike+new HTML block (red strike-through
                // original + green discounted) so binding to "Birim fiyat"
                // / "Satır toplamı" Just Works without composing two spans.
                '__item_price'             => !empty($item['has_discount']) ? $item['unit_price_block'] : $item['unit_price'],
                '__item_total'             => !empty($item['has_discount']) ? $item['line_total_block'] : $item['line_total'],
                '__item_image'             => h($item['image']),
                '__item_url'               => h($item['url']),
                '__item_remove_url'        => h($item['remove_url']),
                // Discount tokens — empty when no discount applies (auto-
                // hides strike-through via CSS :empty rule). When discount
                // is active, designer can strike+colour these to show savings.
                '__item_original_price'    => isset($item['unit_orig_price']) ? $item['unit_orig_price'] : '',
                '__item_original_total'    => isset($item['line_orig_total']) ? $item['line_orig_total'] : '',
                '__item_has_discount'      => isset($item['has_discount']) ? $item['has_discount'] : '0',
                // Pre-rendered strike+new HTML — drop-in for designers
                // who want the unified discount visual without composing
                // their own bound spans.
                '__item_price_block'       => isset($item['unit_price_block']) ? $item['unit_price_block'] : $item['unit_price'],
                '__item_total_block'       => isset($item['line_total_block']) ? $item['line_total_block'] : $item['line_total'],
                // Per-item product-form data (a gift card's recipient,
                // message, delivery date …). Empty for products without
                // a form. Designer drops `^^__item_form_data_html^^`
                // inside the cart row template to render this block.
                '__item_form_data_html'    => isset($item['form_data_html']) ? $item['form_data_html'] : '',
                // Gift-card recipient block (products.gift_card = 1). Separate
                // token from __item_form_data_html because the two are driven
                // by different product flags and a product can carry either.
                '__item_gift_card_html'    => isset($item['gift_card_html']) ? $item['gift_card_html'] : '',
                // Recurring schedule controls (products.recurring_schedule_
                // editable_by_customer = 1): frequency, number of payments,
                // start date. Empty for every other row. The flag lets the
                // designer show a "you can change the schedule" hint.
                '__item_recurring_editable'      => !empty($item['recurring_editable']) ? '1' : '',
                '__item_recurring_schedule_html' => isset($item['recurring_schedule_html']) ? $item['recurring_schedule_html'] : '',
                // Row-kind flags. Empty string (not '0') for the false case so
                // the standard `[data-pg-bind]:empty` rule hides anything bound
                // to them, and so `eo_visible_if` reads them as falsy.
                '__item_is_donation'       => (isset($item['selection_type']) && $item['selection_type'] === 'donation') ? '1' : '',
                '__item_added_by_offer'    => !empty($item['added_by_offer']) ? '1' : '',
            );

            // Low-stock warning tokens. Threshold is opt-in via
            // ECOMMERCE_LOW_STOCK_THRESHOLD config define (0 / undefined =
            // feature off). The warning only fires when inventory is tracked
            // (inventory=1), backorder is NOT allowed, and the remaining
            // quantity is > 0 and <= threshold. Empty tokens collapse via the
            // standard preg_replace cleanup line below — designer who doesn't
            // place the token sees no change.
            $_low_thresh = (defined('ECOMMERCE_LOW_STOCK_THRESHOLD') && (int)ECOMMERCE_LOW_STOCK_THRESHOLD > 0)
                ? (int)ECOMMERCE_LOW_STOCK_THRESHOLD : 0;
            $_inv_tracked = (int)(isset($item['inventory']) ? $item['inventory'] : 0);
            $_inv_qty     = (int)(isset($item['inventory_quantity']) ? $item['inventory_quantity'] : 0);
            $_inv_backord = (int)(isset($item['backorder']) ? $item['backorder'] : 0);
            $_low_stock = ($_low_thresh > 0 && $_inv_tracked === 1 && $_inv_backord !== 1
                           && $_inv_qty > 0 && $_inv_qty <= $_low_thresh);
            $row_values['__item_has_low_stock']      = $_low_stock ? '1' : '';
            $row_values['__item_stock_warning']      = $_low_stock
                ? h(lang(array(
                    'string' => 'Last {var:1} left',
                    'vars'   => $_inv_qty,
                  )))
                : '';
            $row_values['__item_inventory_quantity'] = ($_inv_tracked === 1) ? (string)$_inv_qty : '';

            // Per-row out-of-stock notice — the product's OWN message, shown
            // on the cart line rather than only on the product page. Same gate
            // as the catalog widgets (inventory tracked, nothing left,
            // backorder not allowed), so a row can never claim to be out of
            // stock while the site is happily accepting backorders.
            //
            // The stored message is WYSIWYG (it arrives wrapped in <p>…</p>),
            // so it goes through _pg_rich_text_to_inline() before being bound
            // to anything — a block element inside the designer's <p> would
            // make the browser close that paragraph early and orphan the text.
            $_item_oos = ($_inv_tracked === 1 && $_inv_backord !== 1 && $_inv_qty <= 0);
            $row_values['__item_out_of_stock']         = $_item_oos ? '1' : '';
            $row_values['__item_out_of_stock_message'] = ($_item_oos && !empty($item['out_of_stock_message']))
                ? _pg_rich_text_to_inline((string)$item['out_of_stock_message'])
                : '';

            // ── Recipient grouping ─────────────────────────────────────
            // Legacy prints a "Ship to <name>" band above each recipient's
            // block, and ONLY under three conditions (get_shopping_cart.php
            // :1373): shipping is on, the site is in multi-recipient mode,
            // and the row belongs to a real ship_to (id 0 = "myself", which
            // gets no band because there is nothing to distinguish).
            //
            // The widget loop is flat, so instead of a band we mark the
            // FIRST row of each recipient run. Rows arrive grouped because
            // the query orders by ship_to_id. The designer binds a heading's
            // visibility to __item_starts_recipient and its text to
            // __item_ship_to_name; on every other row both are empty and the
            // element collapses.
            $_rcp_multi = (!defined('ECOMMERCE_SHIPPING') || ECOMMERCE_SHIPPING == true)
                       && (defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE === 'multi-recipient');
            $_rcp_id    = (int)(isset($item['ship_to_id']) ? $item['ship_to_id'] : 0);
            $_rcp_name  = (string)(isset($item['ship_to_name']) ? $item['ship_to_name'] : '');
            $_rcp_show  = ($_rcp_multi && $_rcp_id > 0 && $_rcp_name !== '');
            // $_pg_prev_rcp persists across iterations of this foreach.
            $_rcp_start = ($_rcp_show && $_rcp_id !== $_pg_prev_rcp);
            if ($_rcp_show) $_pg_prev_rcp = $_rcp_id;

            // ── Calendar event reservation ─────────────────────────────
            // Same gate as legacy: the CALENDARS module must be on AND the
            // row must actually reference an event. get_calendar_event()
            // resolves the recurrence, so a weekly class shows the specific
            // occurrence the visitor booked rather than the series.
            $_cal_name  = '';
            $_cal_range = '';
            if (defined('CALENDARS') && CALENDARS == true
                && !empty($item['calendar_event_id'])
                && function_exists('get_calendar_event')) {
                $_cal = get_calendar_event((int)$item['calendar_event_id'], (int)$item['recurrence_number']);
                if (is_array($_cal)) {
                    $_cal_name  = (string)($_cal['name'] ?? '');
                    // date_and_time_range is pre-formatted markup from the
                    // calendar module — not h()'d, same as legacy.
                    $_cal_range = (string)($_cal['date_and_time_range'] ?? '');
                }
            }
            $row_values['__item_calendar_event_name']  = h($_cal_name);
            $row_values['__item_calendar_event_dates'] = $_cal_range;
            $row_values['__item_calendar_event']       = ($_cal_name !== '' || $_cal_range !== '')
                ? '<div class="pg-cart-calendar-event small text-muted mt-1">'
                  . ($_cal_name !== '' ? '<span class="fw-semibold">' . h($_cal_name) . '</span><br>' : '')
                  . $_cal_range
                  . '</div>'
                : '';

            // ── Recurring grouping ─────────────────────────────────────
            // Same "mark the first row of the run" trick as recipients. The
            // period label comes from the order item's own choice when the
            // customer was allowed to pick one, otherwise the product default.
            $_rec_is    = !empty($item['is_recurring']);
            $_rec_start = ($_rec_is && !$_pg_prev_rec);
            if ($_rec_is) $_pg_prev_rec = true;

            $_rec_period = (string)($item['recurring_payment_period'] !== ''
                ? $item['recurring_payment_period'] : $item['payment_period']);
            $row_values['__item_is_recurring']       = $_rec_is ? '1' : '';
            $row_values['__item_recurring_period']   = $_rec_period !== '' ? h(lang($_rec_period)) : '';
            $row_values['__item_recurring_payments'] = ((int)$item['recurring_number_of_payments'] > 0)
                ? (string)(int)$item['recurring_number_of_payments'] : '';
            // First charge date in the site's date format; blank when the row
            // is not recurring or the schedule has not been written yet.
            $_rec_start_raw = (string)$item['recurring_start_date'];
            $row_values['__item_recurring_start_date'] = ($_rec_is && $_rec_start_raw !== '' && $_rec_start_raw !== '0000-00-00')
                ? h(prepare_form_data_for_output($_rec_start_raw, 'date', false)) : '';
            $row_values['__item_starts_recurring']   = $_rec_start ? '1' : '';
            $row_values['__item_recurring_heading']  = $_rec_start
                ? '<div class="pg-cart-recurring-heading fw-semibold mt-3 mb-2">'
                  . h(lang('Recurring Charges')) . '</div>'
                : '';

            $row_values['__item_ship_to_name']      = $_rcp_show ? h($_rcp_name) : '';
            $row_values['__item_starts_recipient']  = $_rcp_start ? '1' : '';
            // Pre-built band for designers who just want the legacy look
            // without composing a bound heading themselves.
            $row_values['__item_recipient_heading'] = $_rcp_start
                ? '<div class="pg-cart-recipient-heading fw-semibold mt-3 mb-2">'
                  . h(lang('Ship to')) . ' <span class="text-primary">' . h($_rcp_name) . '</span></div>'
                : '';

            $row_html = $loop_template;
            $rkeys = array_keys($row_values);
            usort($rkeys, function ($a, $b) { return strlen($b) - strlen($a); });
            foreach ($rkeys as $k) {
                $row_html = str_replace('^^' . $k . '^^', $row_values[$k], $row_html);
            }
            $row_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $row_html);

            // Auto-append form data when the row HAS form_data_html AND the
            // designer hasn't placed `^^__item_form_data_html^^` in the
            // loop_template. Without this fallback, products with order
            // forms (a gift card's recipient email, message, delivery date)
            // would silently lose the data the visitor entered at add-to-
            // cart time on legacy cart trees that pre-date the new token.
            // Designer who DOES place the token retains full control over
            // where the form data appears within the row.
            if (!empty($row_values['__item_form_data_html'])
                && strpos($loop_template, '^^__item_form_data_html^^') === false) {
                $row_html .= $row_values['__item_form_data_html'];
            }
            // Same fallback for the gift-card recipient block. Required here,
            // not merely convenient: `recipient_email_address` is validated
            // downstream, so a cart tree without the token would block checkout
            // with no visible cause.
            if (!empty($row_values['__item_gift_card_html'])
                && strpos($loop_template, '^^__item_gift_card_html^^') === false) {
                $row_html .= $row_values['__item_gift_card_html'];
            }
            // And for the recurring schedule: without the controls the row
            // would silently keep the product's schedule while the product
            // promises the customer may change it.
            if (!empty($row_values['__item_recurring_schedule_html'])
                && strpos($loop_template, '^^__item_recurring_schedule_html^^') === false) {
                $row_html .= $row_values['__item_recurring_schedule_html'];
            }

            // Uniquify Bootstrap component IDs per item row.
            // `for="…"` is suffixed alongside `id="…"` — the product-form and
            // gift-card blocks appended above pair every input with a
            // <label for="…">, and rewriting only the id would break that
            // association (clicking the label would stop focusing its field,
            // and screen readers would announce the input unlabelled).
            // `name=` is deliberately NOT rewritten: the server matches those
            // verbatim (order_item_<id>_quantity_number_<n>_…).
            $iter_suffix = '_pgw' . $widget_id . 'r' . $idx;
            $row_html = preg_replace('/\bid="([^"]+)"/',            'id="$1'              . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/\bfor="([^"]+)"/',           'for="$1'             . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/data-bs-target="#([^"]+)"/', 'data-bs-target="#$1' . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/aria-controls="([^"]+)"/',   'aria-controls="$1'   . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/href="#([^"]+)"/',           'href="#$1'           . $iter_suffix . '"', $row_html);

            $loop_rendered .= $row_html;
        }
    }

    // ── Static tokens ──────────────────────────────────────────────────────
    // Currency-bearing tokens are NOT h()'d (entity preservation, see note
    // above). Plain text fields are h()'d as usual.
    $static_values = array(
        // Summary numbers (currency symbol included)
        '__cart_subtotal'              => $fmt_money($sub_cents),
        '__cart_tax'                   => $fmt_money($tax_cents),
        '__cart_surcharge'             => $fmt_money($surcharge_cents),
        '__cart_discount'              => $fmt_money($discount_cents),
        '__cart_shipping'              => $fmt_money($shipping_cents),
        '__cart_total'                 => $fmt_money($total_cents > 0 ? $total_cents : ($sub_cents + $tax_cents + $surcharge_cents + $shipping_cents - $discount_cents)),
        '__cart_count'                 => (string)$cart_count,
        '__has_shippable_item'         => $has_shippable_item ? '1' : '0',

        // Labels (h()'d — pure text)
        '__shopping_cart_label'        => h($shopping_cart_label),
        '__quick_add_label'            => h($quick_add_label),

        // Quick add box — pick a product from the configured group and add it
        // without leaving the cart. Own <form>, so it goes OUTSIDE the
        // cart-update form (the designer drops it above or beside the table).
        // Empty when no group is set or nothing in it can be bought now.
        '__quick_add_form'             => _pg_render_quick_add(
            $quick_add_group_id, $quick_add_label, $cart_action_url, $request_uri
        ),
        '__special_offer_code_label'   => h($special_offer_lbl),
        '__special_offer_code_message' => h($special_offer_msg),
        '__update_button_label'        => h($update_button_label),
        '__checkout_button_label'      => h($checkout_button_label),

        // URLs + messages
        '__checkout_url'               => h($checkout_url),
        '__cart_empty_message'         => h($cart_empty_message),
        '__reference_code'             => h($reference_code),

        // Pre-built form / button HTML (NOT h()'d — these ARE HTML)
        '__special_offer_code_form'    => $special_offer_form,
        '__update_button'              => $update_button_html,
        '__checkout_button'            => $checkout_button_html,

        // Current coupon value (for display "Applied: COUPON123")
        '__special_offer_code'         => h($special_offer_code_value),

        // Applied offers info — alert-style block listing every offer
        // currently affecting the cart (item-level + order-level discount +
        // shipping discount). Empty string when no offers apply.
        '__applied_offers'             => $applied_offers_html,

        // Pending offers — bonus products the cart has QUALIFIED for but that
        // only join the basket once the visitor claims them. Own <form>
        // (posts add_pending_offer_… to cart_action.php), so it must not be
        // nested inside the cart-update form — the designer places it in the
        // sidebar or above the item list. Empty when nothing is claimable.
        '__pending_offers'             => _pg_render_pending_offers(
            isset($_pg_cart_offers['pending_offers']) ? $_pg_cart_offers['pending_offers'] : array(),
            $cart_action_url,
            $request_uri
        ),

        // Tax / shipping disclaimer — legacy prints this under the cart
        // totals so a visitor doesn't read the subtotal as the final price.
        // Wording adapts to what is actually still unknown: shipping is only
        // mentioned when the cart holds a shippable item AND the site charges
        // shipping; tax only when the site charges tax. Empty when neither
        // applies (all-digital cart on a tax-free site) — no filler sentence.
        '__tax_shipping_notice'        => _pg_cart_tax_shipping_notice($has_shippable_item),

        // Offline-payment toggle. NOT a customer control — legacy gates it on
        // ECOMMERCE_OFFLINE_PAYMENT plus a logged-in user who is either staff
        // (role < 3) or explicitly flagged `set_offline_payment`. It lets an
        // operator taking an order on the phone mark this cart as payable by
        // bank transfer. Empty string for everyone else, so a visitor never
        // sees a checkbox that would let them skip paying.
        // Submits with the cart-update form via the HTML5 form attribute.
        '__offline_payment_checkbox'   => _pg_cart_offline_payment_checkbox(
            $order_id, $cart_form_id, $shopping_cart_label
        ),

        // Currency switcher. Own <form> posting to update_currency.php — keep
        // it OUTSIDE the cart-update form. Empty unless multicurrency is on
        // and more than one currency is usable.
        '__currency_selector'          => _pg_cart_currency_selector($request_uri),

        // Recurring split. "Bugünün Ücretleri" is what the card is charged
        // now; "Sürekli Yinelenen Ücretler" is what will be billed on each
        // future period. Showing only one number for a cart that mixes the
        // two misstates the amount either way. Both are empty strings when
        // the cart has no recurring rows, so the labels collapse.
        '__cart_today_total'           => $_recurring_cents > 0 ? $fmt_money($_today_cents) : '',
        '__cart_recurring_total'       => $_recurring_cents > 0 ? $fmt_money($_recurring_cents) : '',
        '__has_recurring_items'        => $_recurring_cents > 0 ? '1' : '',
        '__applied_offers_count'       => (string)$applied_offers_count,

        // Saved-cart link alert: same UX as Express Order. Visitor sees a
        // dismissible alert with the URL they can revisit later to restore
        // THIS exact cart (orders.reference_code). The function returns ''
        // when the visitor has no in-flight order (initial visit / empty
        // cart), so the token simply renders as nothing in that case.
        // Designer drops `^^__saved_cart_link^^` wherever they want the
        // alert to appear (typically above the cart table).
        '__saved_cart_link'            => _eo_render_saved_cart_link($shopping_cart_label),
    );

    // Two-pass token replacement. First the static template, then we wrap the
    // whole thing in the cart-update <form> so EVERY qty input + the Update
    // button submit through it.
    if ($static_html === '') {
        // No loop_area split — the template is the loop body, render once.
        $rendered = $loop_template;
        $skeys = array_keys($static_values);
        usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($skeys as $k) {
            $rendered = str_replace('^^' . $k . '^^', $static_values[$k], $rendered);
        }
        $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
        // Same applied_offers placeholder substitution as above.
        if (strpos($rendered, '<!--pg-applied-offers-placeholder-->') !== false) {
            $rendered = str_replace('<!--pg-applied-offers-placeholder-->', $applied_offers_html, $rendered);
        }
        return _pg_widget_layout_css_once() . $update_form_html . $rendered . _pg_qty_stepper_inline_js() . _pg_remove_from_cart_inline_js();
    }

    $skeys = array_keys($static_values);
    usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($skeys as $k) {
        $static_html = str_replace('^^' . $k . '^^', $static_values[$k], $static_html);
    }
    $static_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static_html);

    $body = str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
    // Replace the applied-offers content type marker with the live alert HTML.
    // Designer drops a `<content contentType="applied_offers">` node in the
    // tree → backend renders `<!--pg-applied-offers-placeholder-->` → we swap
    // it here for the actual alert. Empty replacement when there are no
    // active offers (so the placeholder simply disappears).
    $applied_placeholder_used = (strpos($body, '<!--pg-applied-offers-placeholder-->') !== false);
    if ($applied_placeholder_used) {
        $body = str_replace('<!--pg-applied-offers-placeholder-->', $applied_offers_html, $body);
    }

    // Upsell offers were injected as a tree node (right after messages)
    // BEFORE the tree was rendered to HTML, so they\'re already inside
    // $body at the proper position — no string-level prepend needed.
    // Applied offers still use the placeholder-marker mechanism for designer
    // control; if no marker was placed, append them after the messages too.
    if (!$applied_placeholder_used && $applied_offers_html !== '') {
        // Inject applied offers right after messages in $body via simple
        // marker swap. The messages node emits a known wrapper we can split
        // on; if not present, prepend to the form body as a last resort.
        $body = preg_replace(
            '/(<div[^>]*class="[^"]*pg-cart-upsell-auto[^"]*"[^>]*>.*?<\/div>)/s',
            '$1' . str_replace('$', '\\$', $applied_offers_html),
            $body,
            1,
            $_replaced
        );
        if (empty($_replaced)) {
            // No upsell anchor — prepend applied to the body.
            $body = $applied_offers_html . $body;
        }
    }

    return _pg_widget_layout_css_once() . $update_form_html . $body . _pg_qty_stepper_inline_js() . _pg_remove_from_cart_inline_js();
}

// ========================== EXPRESS ORDER (Phase 1) ==========================
// Single-page checkout: cart + billing + (shipping when needed) + payment.
// Submits to the legacy `express_order.php` action handler so all order /
// payment plumbing — Iyzipay 3DS, PayPal Express, address verification,
// shipping cost calculation, recurring billing, gift cards, tax computation —
// is reused unchanged. The widget is purely a renderer.
//
// Phase-1 scope (this commit):
//   • Cart summary table (read-only items + qty editor + remove)
//   • Billing address (12 standard fields)
//   • Payment methods (settings-aware: Credit/Debit Card, PayPal Express,
//     Pay With Iyzico, Offline Payment) — same field names as legacy template
//     so the action handler validates correctly
//   • Order totals (subtotal / discount / tax / shipping / total)
//   • Submit button (name=submit_purchase_now)
//
// Out-of-scope for Phase 1, planned for later phases:
//   • Multi-recipient shipping forms + arrival date + shipping method picker
//   • Special offer code + gift card code inputs
//   • Custom billing / shipping forms (express_order_pages.form / shipping_form)
//   • Designer-bindable sections (cart_summary / billing_address / etc.)
//   • Quick-add box, pending offers, recurring items section
//
// The function ALSO handles 3DSecure callbacks (`?mode=...`) by forwarding
// the request to express_order.php verbatim — so paid-by-3DS flows that land
// back on the page complete cleanly without re-rendering the form.
function _render_system_widget_express_order($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // Hard gate — the entire e-commerce subsystem must be on.
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
        return '<!-- pg-express-order: ECOMMERCE is disabled -->';
    }

    // ── 3DSecure / payment-gateway return callbacks ──────────────────────────
    // Iyzipay 3DS, Pay With Iyzico, and PayPal Express all return to the
    // ORIGINAL express order URL with `?mode=…` so they hit this widget render
    // (not express_order.php directly). Forward to the legacy handler so
    // submit_order.php's 3DS-confirm logic runs in its real request lifecycle.
    // We exit() afterward so nothing else on the page gets rendered (the
    // handler emits its own redirect / receipt).
    $_eo_mode = isset($_GET['mode']) ? (string)($_GET['mode'] ?? '') : '';
    if (in_array($_eo_mode, array('paypal_express_checkout_return', 'iyzipay_threedsecure_return', 'pay_with_iyzico_return'), true)) {
        // The handler reads $_POST['page_id'] for express_order_pages lookup.
        // Callbacks usually arrive as GET (after gateway redirect) — synthesize
        // page_id from the current page so the lookup finds something (even
        // a missing row is graceful, but page_id has to be set).
        if (empty($_POST['page_id']) && isset($_GET['page'])) {
            $_eo_pid = (int)db_value("SELECT page_id FROM page WHERE page_name = '"
                . e((string)$_GET['page']) . "' LIMIT 1");
            if ($_eo_pid > 0) $_POST['page_id'] = $_eo_pid;
        }
        require_once PG_FUNCTIONS_DIR . '/express_order.php';
        exit();
    }

    // ── Resolve our page id ──────────────────────────────────────────────────
    // Used as the form's hidden `page_id` so express_order.php can look up
    // the express_order_pages config row (it gracefully degrades when the row
    // is absent — labels become empty, no custom forms render).
    $_eo_page_id = 0;
    if (isset($_GET['page'])) {
        $_eo_page_id = (int)db_value("SELECT page_id FROM page WHERE page_name = '"
            . e((string)$_GET['page']) . "' LIMIT 1");
    }
    if ($_eo_page_id > 0) {
        $_SESSION['ecommerce']['express_order_page_id'] = $_eo_page_id;
        unset($_SESSION['ecommerce']['shopping_cart_page_id']);
    }

    // Auto-provision an express_order_pages row for this page if missing.
    // Without it the legacy handler runs OK but error messages reference an
    // empty `$shopping_cart_label` / `$special_offer_code_label`. A tiny
    // INSERT IGNORE keeps the rest of the system happy.
    if ($_eo_page_id > 0) {
        // Compute offline_payment_always_allowed default from the global
        // toggle. Without this, picking "Çevrimdışı Ödeme" from the radio
        // list hits the "Seçilen ödeme yöntemi mevcut değil" guard in
        // submit_order.php (the per-page flag defaults to 0 — legacy
        // expressorder_pages admin had to set it manually). When the global
        // setting is enabled, the operator's clear intent is "offline payment
        // is acceptable" — we mirror that into the per-page row.
        $_eo_offline_default = (defined('ECOMMERCE_OFFLINE_PAYMENT') && ECOMMERCE_OFFLINE_PAYMENT == true) ? 1 : 0;

        $_eo_has_row = (int)db_value("SELECT COUNT(*) FROM express_order_pages WHERE page_id = '" . (int)$_eo_page_id . "'");
        if ($_eo_has_row === 0) {
            // shopping_cart_label / special_offer_code_label default to
            // sensible Turkish strings; designers can edit via cfg later.
            $_eo_cart_lbl = isset($cfg['cart_section_label']) && (string)$cfg['cart_section_label'] !== ''
                                ? (string)$cfg['cart_section_label'] : (string)lang('cart');
            $_eo_offer_lbl = isset($cfg['special_offer_code_label']) && (string)$cfg['special_offer_code_label'] !== ''
                                ? (string)$cfg['special_offer_code_label'] : (string)lang('Special Offer Code');
            db("INSERT INTO express_order_pages (page_id, shopping_cart_label, special_offer_code_label, shipping_form, form, offline_payment_always_allowed)
                 VALUES ('" . (int)$_eo_page_id . "', '" . e($_eo_cart_lbl) . "', '" . e($_eo_offer_lbl) . "', 0, 0, '" . (int)$_eo_offline_default . "')");
        } else {
            // Defensive sync for EXISTING rows: when the global toggle is on
            // and the per-page flag is still 0, surface the operator's intent
            // by enabling it. We never DISABLE — if the operator explicitly
            // turned it off on a specific page, that wins.
            if ($_eo_offline_default === 1) {
                db("UPDATE express_order_pages SET offline_payment_always_allowed = 1
                     WHERE page_id = '" . (int)$_eo_page_id . "' AND offline_payment_always_allowed = 0");
            }
        }

        // Sync cfg.next_page_id to express_order_pages.next_page_id so
        // submit_order.php's success redirect lands on the designer's chosen
        // receipt page. We update on EVERY render so designer changes in the
        // property panel take effect without manual DB editing. cfg.next_page_id
        // = 0 means "no preference", leave the existing DB value alone.
        $_eo_next_page = isset($cfg['next_page_id']) ? (int)$cfg['next_page_id'] : 0;
        if ($_eo_next_page > 0) {
            // Defensive: confirm the page exists before saving. A stale cfg
            // (designer deleted the receipt page) would otherwise redirect
            // visitors to a broken URL.
            $_eo_next_exists = (int)db_value("SELECT page_id FROM page WHERE page_id = '" . (int)$_eo_next_page . "' LIMIT 1");
            if ($_eo_next_exists > 0) {
                db("UPDATE express_order_pages SET next_page_id = '" . (int)$_eo_next_page . "' WHERE page_id = '" . (int)$_eo_page_id . "'");
            }
        }
    }

    // ── Initialize order state + run pricing / offers / tax pass ─────────────
    // Mirrors what get_order_preview.php does at the top so the totals shown
    // here reflect the live cart state (not stale subtotals from a previous
    // visit).
    $_eo_lf = class_exists('liveform') ? new liveform('express_order') : null;
    // CRITICAL: do NOT call $_eo_lf->add_fields_to_session() here. That
    // method WIPES the existing session values FIRST and then re-populates
    // from $_REQUEST. On GET renders (post-redirect from express_order.php
    // when validation fails) $_REQUEST is empty, so the wipe nukes every
    // field the visitor just typed — symptom: the form empties itself on
    // submit. The legacy `express_order.php` action handler already
    // calls add_fields_to_session() on POST (line 57); the widget render
    // only READS that session via get_field_value(), never writes.
    // Saved-cart link restore: visitor came from `/siparis?r=REFERENCE_CODE`
    // (their previous session\'s saved cart). Find that order, swap session
    // order_id BEFORE initialize_order() so the rest of the pipeline picks
    // up the restored cart instead of creating a fresh empty one.
    if (function_exists('_eo_restore_cart_from_reference_code')) {
        _eo_restore_cart_from_reference_code();
    }

    if (function_exists('initialize_order'))         initialize_order();
    if (function_exists('check_inventory') && $_eo_lf) check_inventory($_eo_lf);
    if (function_exists('update_order_item_prices')) update_order_item_prices();
    // Capture the offer-processing result so the upsell/applied bindings
    // below can render Bootstrap alerts. apply_offers_to_cart() returns
    // ['pending_offers' => [...], 'upsell_offers' => [...]] — see
    // get_express_order.php:111-114 for the canonical contract.
    $_eo_offers = function_exists('apply_offers_to_cart') ? apply_offers_to_cart() : array();
    if (!is_array($_eo_offers)) $_eo_offers = array();

    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

    // Pre-seed billing_country/state on the order so update_order_item_taxes()
    // can compute the right per-line tax at FORM RENDER time, not just after
    // the visitor submits. Without this, the displayed total = subtotal +
    // surcharge (no tax) but submit_order.php recomputes WITH tax (because
    // the visitor by then HAS filled billing_country) → real_total > seen
    // total → "tutar değişti" rejection (and an HTTP 500 fatal further
    // down in the legacy log_activity call). Priority:
    //   1. Visitor's just-edited billing_country (liveform session)
    //   2. Saved billing_country on the order row
    //   3. countries.default_selected (site default)
    if ($order_id > 0 && defined('ECOMMERCE_TAX') && ECOMMERCE_TAX === true) {
        $_eo_tax_country = '';
        $_eo_tax_state   = '';
        if ($_eo_lf) {
            $_eo_tax_country = (string)$_eo_lf->get_field_value('billing_country');
            $_eo_tax_state   = (string)$_eo_lf->get_field_value('billing_state');
        }
        if ($_eo_tax_country === '') {
            $_eo_row = db_item("SELECT billing_country, billing_state FROM orders WHERE id = '" . (int)$order_id . "' LIMIT 1");
            if (is_array($_eo_row)) {
                $_eo_tax_country = (string)($_eo_row['billing_country'] ?? '');
                $_eo_tax_state   = (string)($_eo_row['billing_state']   ?? '');
            }
        }
        if ($_eo_tax_country === '') {
            $_eo_tax_country = (string)db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1");
        }
        if ($_eo_tax_country !== '') {
            db("UPDATE orders SET billing_country = '" . e($_eo_tax_country) . "'"
             . ($_eo_tax_state !== '' ? ", billing_state = '" . e($_eo_tax_state) . "'" : "")
             . " WHERE id = '" . (int)$order_id . "'");
        }
    }

    if (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX === true && function_exists('update_order_item_taxes')) {
        update_order_item_taxes();
    }

    // ── Prefill billing + shipping fields from saved sources ─────────────────
    // Source priority (each field independently):
    //   • Visitor's just-edited value (already in liveform session) → keep
    //   • Last write from this session's order (orders.billing_*) → 2nd
    //   • Logged-in user's contact (contacts.*) → 3rd
    //   • Site default (countries.default_selected) for country fallback
    //
    // For shipping, address_book row (matched on user + ship_to_name) wins
    // over the ship_tos columns when available.
    if ($_eo_lf && $order_id > 0) {
        $_eo_ghost = isset($_SESSION['software']['ghost']) ? (bool)$_SESSION['software']['ghost'] : false;
        _eo_prefill_billing_fields($_eo_lf, $order_id, $_eo_ghost);
    }

    // ── Load cart items + per-recipient totals ───────────────────────────────
    // Phase-1 simplified: collapse to a single flat list. Phase-2 will split
    // by ship_to_id when multi-recipient is enabled.
    $items = array();
    $subtotal_cents = 0;
    if ($order_id > 0) {
        // `form_name` is the admin-set heading that introduces the per-item
        // custom form (e.g. "Üyelik Bilgisi", "Katılımcı Bilgileri"). Without
        // it the form fields appear under the row with no context — visitors
        // see floating labels and can't tell why they're being asked.
        // "Save for later" filter — when feature is enabled AND the column
        // exists (upgrade 2026.1.28), hide rows the visitor has explicitly
        // parked. Defensive: missing column or disabled feature means the
        // legacy "show everything" behaviour wins.
        $_eo_sfl_filter = '';
        if (defined('ECOMMERCE_SAVE_FOR_LATER') && ECOMMERCE_SAVE_FOR_LATER === true) {
            static $_eo_sfl_has_col = null;
            if ($_eo_sfl_has_col === null) {
                $_p = db_value("SHOW COLUMNS FROM order_items LIKE 'saved_for_later'");
                $_eo_sfl_has_col = ($_p !== '' && $_p !== null);
            }
            if ($_eo_sfl_has_col) {
                $_eo_sfl_filter = " AND oi.saved_for_later = 0";
            }
        }
        $items = db_items(
            "SELECT oi.id AS item_id, oi.product_id, oi.quantity, oi.price, oi.tax_total,
                    oi.ship_to_id, p.name AS product_name, p.short_description, p.full_description,
                    p.shippable, p.image_name, p.address_name, p.selection_type, p.gift_card,
                    p.form, p.form_name, p.form_quantity_type,
                    p.inventory, p.inventory_quantity, p.backorder
             FROM order_items oi
             INNER JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = '" . (int)$order_id . "'" . $_eo_sfl_filter . "
             ORDER BY oi.id ASC"
        );
        if (is_array($items)) {
            foreach ($items as $it) {
                $subtotal_cents += (int)$it['price'] * (int)$it['quantity'];
            }
        }
    }
    $cart_count = is_array($items) ? count($items) : 0;

    // Pre-load existing gift_card data for any item in the cart so the
    // form re-fills the recipient email / from name / message / delivery
    // date inputs across submit roundtrips. Indexed by item_id then
    // quantity_number for fast lookup at render time.
    $gift_card_data = array();
    if ($order_id > 0 && $cart_count > 0) {
        $gc_rows = db_items(
            "SELECT order_item_id, quantity_number, from_name,
                    recipient_email_address, message, delivery_date
             FROM order_item_gift_cards
             WHERE order_id = '" . (int)$order_id . "'"
        );
        if (is_array($gc_rows)) {
            foreach ($gc_rows as $g) {
                $gift_card_data[(int)$g['order_item_id']][(int)$g['quantity_number']] = $g;
            }
        }
    }

    // Detect digital-only vs has-shippable. Drives whether the shipping
    // section is needed (Phase 2 will render it when this flag is true).
    $has_shippable = false;
    if ($cart_count > 0) {
        foreach ($items as $it) {
            if ((int)$it['shippable'] === 1) { $has_shippable = true; break; }
        }
    }
    $needs_shipping = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING === true && $has_shippable);

    // ── Pull order-level totals (already computed by apply_offers_to_cart) ───
    // NOTE: orders.subtotal is only refreshed at FINAL submit (submit_order.php).
    // For live preview between submits we trust the order_items SUM computed
    // above instead — orders.subtotal is typically 0 until checkout completes.
    // discount + gift_card_discount DO get updated by apply_offers_to_cart()
    // so we still pull those from the orders row.
    $totals = $order_id > 0
        ? db_item("SELECT subtotal, discount, gift_card_discount, surcharge, tax_exempt, billing_country, billing_state
                   FROM orders WHERE id = '" . (int)$order_id . "' LIMIT 1")
        : array();
    if (!is_array($totals)) $totals = array();
    $sub_cents      = $subtotal_cents;  // live sum from order_items, not stale orders.subtotal
    $discount_cents = isset($totals['discount']) ? (int)$totals['discount'] : 0;
    $gc_disc_cents  = isset($totals['gift_card_discount']) ? (int)$totals['gift_card_discount'] : 0;
    // order_items.tax_total is the tax on the whole line, written by
    // update_order_item_taxes() as round(rate / 100 * price * quantity). This
    // widget and the checkout now work the figure out the same way; they used to
    // differ (this one on the line, the checkout per unit) and the visitor's
    // total could fail the recheck at submit with a "tutar değişti" rejection.
    $tax_cents = $order_id > 0
        ? (int)db_value("SELECT COALESCE(SUM(tax_total),0) FROM order_items WHERE order_id = '" . (int)$order_id . "'")
        : 0;
    $ship_cents = $order_id > 0 && $needs_shipping
        ? (int)db_value("SELECT COALESCE(SUM(shipping_cost),0) FROM ship_tos WHERE order_id = '" . (int)$order_id . "'")
        : 0;
    $total_cents = $sub_cents - $discount_cents + $tax_cents + $ship_cents - $gc_disc_cents;
    if ($total_cents < 0) $total_cents = 0;

    // Surcharge — applied by submit_order.php (line 853-859) ONLY when
    // ECOMMERCE_SURCHARGE_PERCENTAGE > 0 AND payment_method is Credit/Debit
    // Card. We compute it separately so we can post BOTH the base total
    // (used when surcharge=0) AND the with-surcharge total (used otherwise).
    // The "total has changed" guard (line 1296-1306) picks one based on
    // the runtime $surcharge value — which is computed AFTER POST. Posting
    // both lets the comparison succeed regardless of which branch fires.
    $surcharge_cents = 0;
    if (defined('ECOMMERCE_SURCHARGE_PERCENTAGE') && ECOMMERCE_SURCHARGE_PERCENTAGE > 0) {
        $surcharge_cents = (int)round((float)ECOMMERCE_SURCHARGE_PERCENTAGE / 100 * $total_cents);
    }
    $total_with_surcharge_cents = $total_cents + $surcharge_cents;

    // ── Currency formatter ────────────────────────────────────────────────────
    $_eo_fmt = function ($cents) {
        if (function_exists('format_price_for_display')) {
            return format_price_for_display(((int)$cents) / 100);
        }
        $sym = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '';
        return $sym . number_format(((int)$cents) / 100, 2);
    };

    // ── Build URLs / hidden fields ───────────────────────────────────────────
    $base_path = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    // express_order.php lives INSIDE the software directory (e.g.
    // /pinegrap/express_order.php). The directory name is configurable
    // (OUTPUT_SOFTWARE_DIRECTORY constant), so we always build it from the
    // constant — never hardcode the segment. Same pattern shopping_cart
    // widget uses for cart_action.php.
    $sw_dir = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $eo_action_url = $base_path . $sw_dir . '/express_order.php';
    $request_uri   = function_exists('get_request_uri') ? get_request_uri()
                        : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    $form_id = 'pg-eo-form-w' . $widget_id;

    // ── Empty cart short-circuit ─────────────────────────────────────────────
    if ($cart_count === 0) {
        return _pg_widget_layout_css_once()
            . '<div class="container py-4">'
            . '<div class="pg-eo-empty alert alert-secondary text-center py-4 my-4">'
            .   '<h3 class="mb-2">' . h(lang('Your cart is empty')) . '</h3>'
            .   '<p class="mb-0 text-muted">' . h(lang('Browse the catalog to add products to your cart.')) . '</p>'
            . '</div></div>';
    }

    // ── Per-recipient grouping for shipping/cart sections ────────────────────
    // Phase-2: when shipping is needed we walk ship_tos and render one
    // address block per recipient. For digital orders ship_tos is empty so
    // there's just one implicit "no recipient" group of items.
    $recipients = array();
    if ($needs_shipping) {
        // Pull every recipient already stamped on this order's items.
        $recipients = db_items(
            "SELECT id, ship_to_name, salutation, first_name, last_name, company,
                    address_1, address_2, city, state, zip_code, country, phone_number,
                    address_type, arrival_date_id, arrival_date,
                    shipping_method_id, shipping_cost, complete
             FROM ship_tos
             WHERE order_id = '" . (int)$order_id . "'
             ORDER BY id ASC"
        );
    }
    if (!is_array($recipients)) $recipients = array();

    // Per-recipient shipping prefill — runs AFTER recipients are loaded so
    // we have the ship_to_name to match against address_book.
    if ($_eo_lf && !empty($recipients)) {
        $_eo_ghost = isset($_SESSION['software']['ghost']) ? (bool)$_SESSION['software']['ghost'] : false;
        foreach ($recipients as $r) {
            _eo_prefill_shipping_fields($_eo_lf, $r, $_eo_ghost);
        }
    }

    // ── Section: Cart summary ────────────────────────────────────────────────
    $cart_html = _eo_render_cart_summary($items, $widget_id, $form_id, $_eo_fmt, $_eo_lf, $gift_card_data);

    // ── Section: Shipping (multi-recipient, Phase 2) ─────────────────────────
    $shipping_html = '';
    if ($needs_shipping) {
        if (empty($recipients)) {
            // Defensive: shippable items but no ship_tos rows. This shouldn't
            // happen in practice (add_order_item creates a row for every
            // shippable add) but we degrade with a notice rather than a
            // confusing empty block.
            $shipping_html =
                '<div class="alert alert-warning mb-0">'
                . h(lang('No shipping recipient could be resolved for this order.'))
                . '</div>';
        } else {
            // Detect whether the designer placed real shipping_* form inputs
            // in the tree (eo_field bindings). When yes AND there\'s exactly
            // ONE recipient, the section binding ONLY emits the dynamic
            // extras (arrival date + shipping method picker) — address
            // fields live in the tree as real DOM elements.
            //
            // ── MULTI-RECIPIENT SAFETY ─────────────────────────────────
            // When the order has MORE THAN ONE ship_to row, the tree-based
            // approach breaks: the designer\'s tree only has ONE set of
            // shipping inputs (using recipients[0].id), but the validator
            // iterates ALL recipients and expects shipping_<rid_N>_* fields
            // for each one. POST is missing fields for recipients 2..N →
            // every required-field check fails with "First Name is
            // required, Last Name is required, …" cascade — exact symptom
            // the user reported.
            //
            // For multi-recipient orders we fall back to the server-rendered
            // address form (which loops over recipients and renders a full
            // address block per ship_to). The designer\'s tree-side shipping
            // card is also dropped from the tree (see below) to avoid the
            // duplicate fields problem.
            $_eo_shipping_skip_addr = false;
            $_eo_multi_recipient    = count($recipients) > 1;
            $_eo_has_recipient_loop = false;
            if ($tree_json !== '' && $tree_json !== null) {
                $_tree_for_detect = json_decode($tree_json, true);
                if (is_array($_tree_for_detect)) {
                    if (!$_eo_multi_recipient) {
                        $_eo_shipping_skip_addr = _eo_tree_has_real_shipping_address_fields($_tree_for_detect);
                    }
                    // Multi-recipient designer customization — when the tree
                    // contains a recipient_loop_area, the designer-authored
                    // per-recipient template replaces the server-rendered
                    // address forms. The eo_shipping section binding then
                    // emits ONLY the arrival date + shipping method extras.
                    if (_eo_tree_has_recipient_loop_area($_tree_for_detect)) {
                        $_eo_has_recipient_loop = true;
                        $_eo_shipping_skip_addr = true;
                    }
                }
            }
            $shipping_html = _eo_render_shipping_section(
                $recipients, $_eo_lf, $form_id, $widget_id,
                !$_eo_shipping_skip_addr   // include_address_fields = NOT skip
            );
        }
    }

    // ── Section: Billing address ─────────────────────────────────────────────
    $billing_html = _eo_render_billing_address($_eo_lf, $totals, $cfg);

    // ── Section: Payment methods (conditional on settings + total > 0) ───────
    $payment_html = ($total_cents > 0)
        ? _eo_render_payment_methods($_eo_lf, $cfg)
        : '<div class="alert alert-info mb-3">' . h(lang('No payment is required for this order.')) . '</div>';

    // ── Section: Order totals sidebar ────────────────────────────────────────
    // Pass surcharge separately so the sidebar can show it as its own line
    // (only when ECOMMERCE_SURCHARGE_PERCENTAGE > 0 AND payment is CC).
    // Visitor sees the post-surcharge total in the "Toplam" row when CC is
    // active, matching what submit_order.php will actually charge.
    $totals_html = _eo_render_order_totals(
        $sub_cents, $discount_cents, $tax_cents, $ship_cents, $gc_disc_cents,
        $total_cents, $surcharge_cents, $total_with_surcharge_cents,
        $_eo_fmt, $needs_shipping
    );

    // ── Designer bindings dispatch ───────────────────────────────────────────
    // If the saved tree contains nodes flagged with `_bindings.eo_section='X'`
    // we hand control to the designer-bindings render path: walk the tree,
    // replace each marked node's children with the matching section HTML,
    // emit the tree as-is. The designer can therefore reorder sections, wrap
    // them in cards, add headings around them, and mix in static blocks —
    // without giving up the dynamic checkout logic that has to stay on the
    // server. When the tree has zero section bindings we fall through to the
    // monolithic render below, preserving the Phase-1 behaviour for widgets
    // that never visited the designer.
    $sections_html = array(
        'cart_items'             => $cart_html,
        'shipping'               => $shipping_html,
        'billing'                => $billing_html,            // legacy full block
        'billing_country_select' => _eo_render_billing_country_select($_eo_lf),
        'payment'                => $payment_html,            // legacy full block
        'payment_methods'        => _eo_render_payment_methods_only($_eo_lf),
        'installment'            => _eo_render_installment_box($_eo_lf),
        'terms'                  => _eo_render_terms_section($_eo_lf, $cfg),
        'totals'                 => $totals_html,
        'saved_cart_link'        => _eo_render_saved_cart_link(isset($cfg['cart_section_label']) ? (string)$cfg['cart_section_label'] : ''),
        // Address-book select — surfaces distinct billing addresses the
        // logged-in visitor has used on past complete/exported orders.
        // Renders nothing for guests or members with no past orders, so
        // designers can drop the binding everywhere without conditional
        // visibility. Selecting an option fills the billing_* inputs via
        // inline JS (no fetch — addresses are inlined as JSON).
        'address_book'           => _eo_render_address_book_select(),
        // Promotional sections sourced from view_offers.php: upsell nudges
        // visitors toward higher tiers; applied confirms current discounts.
        'upsell_offers'          => _eo_render_upsell_offers(isset($_eo_offers['upsell_offers']) ? $_eo_offers['upsell_offers'] : array()),
        'applied_offers'         => _eo_render_applied_offers(),
        'errors_notices'         => '',                       // populated below before form wrap
    );
    // Resolve submit / update labels here so the bindings path can render the
    // buttons section identically to the monolithic path further down.
    $_eo_submit_label = isset($cfg['purchase_button_label']) && (string)$cfg['purchase_button_label'] !== ''
                            ? (string)$cfg['purchase_button_label'] : (string)lang('Place Order');
    $_eo_update_label = isset($cfg['update_button_label']) && (string)$cfg['update_button_label'] !== ''
                            ? (string)$cfg['update_button_label'] : (string)lang('Update');
    $sections_html['submit_button'] =
        '<button type="submit" name="submit_purchase_now" value="1" class="btn btn-primary btn-lg w-100">'
      .   h($_eo_submit_label)
      . '</button>';
    $sections_html['update_button'] =
        '<button type="submit" name="submit_update" value="1" formnovalidate class="btn btn-outline-secondary w-100">'
      .   h($_eo_update_label)
      . '</button>';

    // ── Submit + Update buttons ──────────────────────────────────────────────
    $submit_label = isset($cfg['purchase_button_label']) && (string)$cfg['purchase_button_label'] !== ''
                        ? (string)$cfg['purchase_button_label'] : (string)lang('Place Order');
    $update_label = isset($cfg['update_button_label']) && (string)$cfg['update_button_label'] !== ''
                        ? (string)$cfg['update_button_label'] : (string)lang('Update');
    $buttons_html =
        '<div class="d-grid gap-2 mt-3">'
        . '<button type="submit" name="submit_purchase_now" value="1" class="btn btn-primary btn-lg">'
        .   h($submit_label)
        . '</button>'
        // formnovalidate — Update should recalc totals without forcing every
        // required field. Same rationale as the cart Update button.
        . '<button type="submit" name="submit_update" value="1" formnovalidate class="btn btn-outline-secondary">'
        .   h($update_label)
        . '</button>'
        . '</div>';

    // ── Per-widget JS (shipping calculator + country/state toggle) ───────────
    // `_pg_qty_stepper_inline_js()` is appended so the cart-summary -/+ buttons
    // (and any designer-placed `_bindings.action='eo_qty_inc'` button) are
    // wired. `_pg_eo_modal_relocate_inline_js()` moves any designer-placed
    // .modal out of the EO form to <body> so Bootstrap's backdrop manager
    // works. All helpers guard themselves with one-shot constants so multiple
    // express_order widgets on the same page emit the script ONCE total.
    $widget_js = _eo_render_widget_js($widget_id, $form_id, $needs_shipping, $recipients, $base_path)
               . _pg_qty_stepper_inline_js()
               . _pg_eo_modal_relocate_inline_js()
               . _pg_eo_terms_accept_inline_js()
               . _pg_remove_from_cart_inline_js();

    // ── Wrap everything in the form ──────────────────────────────────────────
    // Single big form (mirrors legacy template line 184). All inputs above
    // bind to it via being children. Token + page_id + send_to are the
    // contract express_order.php expects.
    //
    // `<div class="container">` wrapper guarantees consistent gutters even
    // when the host page layout doesn't already provide one — the express
    // order screen IS effectively a checkout page, so we own the layout.
    $token_field = function_exists('get_token_field') ? get_token_field() : '';
    // Error / notice banners — surface validation failures from the action
    // handler (legacy express_order.php uses $liveform->mark_error and
    // add_notice; both end up in the liveform session and roundtrip to the
    // next render). Without explicitly outputting them, visitors see a
    // silent redirect-back with no clue what failed — the exact "submit,
    // and not even an error" symptom. Reuse the legacy output helpers so
    // the styling matches the rest of the site (Bootstrap alerts).
    $errors_html  = $_eo_lf ? (string)$_eo_lf->output_errors()  : '';
    $notices_html = $_eo_lf ? (string)$_eo_lf->output_notices() : '';
    // CONSUME-ON-RENDER: errors/notices are ONE-SHOT — wipe them from
    // session after rendering. Otherwise they would stick around forever
    // (e.g. visitor submits with empty CC fields → sees errors → switches
    // payment method to Offline → reloads → STILL sees the now-irrelevant
    // CC errors). Legacy `clear_notices()` at the top of express_order.php
    // handles notices but NOT field errors; without this manual unmark
    // the only way to clear field errors is to re-submit and pass.
    if ($_eo_lf) {
        if (method_exists($_eo_lf, 'unmark_errors')) $_eo_lf->unmark_errors();
        if (method_exists($_eo_lf, 'clear_notices')) $_eo_lf->clear_notices();
    }

    // Hidden `total` / `total_with_surcharge` fields — submit_order.php
    // (line 1296-1308) rejects the submit if the recomputed server-side
    // total is GREATER than the value the visitor saw on screen (a "cost
    // changed since you reviewed the order" guard). The check reads
    // $liveform->get('total') as DOLLARS (not cents). Without these hidden
    // inputs the comparison effectively becomes "real_total > 0" which
    // fails for every non-zero order, and the visitor sees the silent
    // "Sorry, the order was not accepted, because the total has changed"
    // rejection — followed by a fatal `abs('')` because the log_activity
    // call passes the empty $old_total to prepare_amount(). Posting both
    // values covers both code paths (with/without surcharge) AND we ALSO
    // pre-seed the liveform session as defense-in-depth so a corrupted
    // POST roundtrip can't trigger the same fatal.
    $total_dollars  = $total_cents / 100;
    $total_str      = number_format($total_dollars, 2, '.', '');
    $tws_dollars    = $total_with_surcharge_cents / 100;
    $tws_str        = number_format($tws_dollars, 2, '.', '');
    $totals_hidden_html =
        '<input type="hidden" name="total" value="' . h($total_str) . '">'
      . '<input type="hidden" name="total_with_surcharge" value="' . h($tws_str) . '">';
    if ($_eo_lf) {
        // Pre-seed session so prepare_amount() never sees empty even if
        // POST roundtrip somehow strips the value. add_fields_to_session
        // runs on every POST and rewrites this from $_REQUEST, so visitor
        // edits to total (which shouldn't happen — it's hidden) still win.
        $_eo_lf->assign_field_value('total', $total_str);
        $_eo_lf->assign_field_value('total_with_surcharge', $tws_str);
    }

    // Decode the saved tree ONCE and check whether the designer placed any
    // section bindings. If yes, walk the tree replacing each marker's
    // children with the section's HTML and use that as the body. Otherwise
    // we fall through to the monolithic layout below (legacy behaviour).
    $tree_decoded_for_eo = ($tree_json !== '' && $tree_json !== null) ? json_decode($tree_json, true) : null;
    $tree_has_bindings = is_array($tree_decoded_for_eo)
        ? _eo_tree_has_section_bindings($tree_decoded_for_eo) : false;

    // Errors / notices live in the dynamic sections dictionary so designer
    // trees can place them via `_bindings.eo_section='errors_notices'`. The
    // monolithic fallback always renders them at the top of the form below.
    //
    // BUT the tree usually also carries a `messages` content node ("PHP
    // Messages"), which reads the SAME liveform. With both present the
    // visitor saw every error twice. The messages node is the general
    // mechanism — every other system widget uses it, and the designer can
    // move and style it — so it wins: when one exists, the errors_notices
    // section renders empty and its content flows through the messages node.
    $eo_has_messages_node = is_array($tree_decoded_for_eo)
        ? _eo_tree_has_messages_node($tree_decoded_for_eo) : false;
    $sections_html['errors_notices'] = $eo_has_messages_node ? '' : ($errors_html . $notices_html);

    if ($tree_has_bindings) {
        // ── 1. Mutate the tree in-place:
        //   a. EXTRACT terms modal body (designer-authored content) — must
        //      run FIRST so the extracted HTML is preserved before any
        //      other walker mutates the nodes
        //   b. visibility bindings — drop containers whose condition is false
        //      (e.g. the Delivery card when the cart has no shippable items)
        //   c. section bindings — substitute children with live HTML
        //   d. field bindings — set name/id/value from eo_field; for shipping_*
        //      fields, rewrite to shipping_<rid>_* using the order\'s ship_to row
        //   e. raw-name prefill — fallback for legacy trees without bindings
        //   f. action bindings — submit_purchase_now / submit_update wiring
        $_eo_ship_rid = 0;
        if ($needs_shipping && !empty($recipients) && is_array($recipients[0])) {
            $_eo_ship_rid = (int)$recipients[0]['id'];
        }
        $_eo_vis_ctx = array(
            'has_shipping'       => (bool)$needs_shipping,
            // The tree's address-fields row is wrapped in this flag — it
            // shows ONLY for single-recipient orders. For multi-recipient
            // orders the row is dropped and the server-rendered shipping
            // section (which loops per recipient) supplies the full form
            // via the eo_shipping section binding.
            'has_single_recipient' => (bool)$needs_shipping
                && !(isset($_eo_multi_recipient) && $_eo_multi_recipient),
            // Inverse of has_single_recipient for the multi-recipient branch.
            // Used to wrap a recipient_loop_area (or any block) that should
            // ONLY appear when the order has more than one shipping recipient.
            // When the cart needs shipping AND has >1 recipient, this is true.
            'is_multi_recipient' => (bool)$needs_shipping
                && (isset($_eo_multi_recipient) && $_eo_multi_recipient),
            'has_offers'         => !empty($_eo_offers['upsell_offers']) || !empty($_eo_offers['pending_offers']),
            'has_upsell'         => !empty($_eo_offers['upsell_offers']),
            'has_terms'          => true,  // terms section is always available
            // Site-level tax-exempt switch. Both flags must be ON: tax
            // calculation must be enabled AND the operator must have
            // explicitly opened the exemption option. When false, the
            // tax_exempt checkbox is dropped from the tree before render.
            'tax_exempt_allowed' => (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX === true
                                  && defined('ECOMMERCE_TAX_EXEMPT') && ECOMMERCE_TAX_EXEMPT === true),
            // ── Granular totals-table row visibility ──────────────────────
            // Each flag corresponds to one row in the order summary table.
            // When false, the entire <tr> (label + value) drops from the
            // tree. The thresholds below are deliberately RELAXED for the
            // recurring rows the visitor expects to monitor while filling
            // the form:
            //
            //   • has_tax = "is the site collecting tax at all" (not "is
            //     it already > 0"). Initial render the visitor has no
            //     address yet so tax is ₺0,00; the row should still appear
            //     so they see it'll be added once they enter their address.
            //   • has_shipping_cost = "does the cart have a shippable
            //     item" (not "is cost already > 0"). Same reasoning — and
            //     when the resolved cost is 0 we relabel the value to
            //     "Ücretsiz" via the __shipping_formatted token below,
            //     instead of "₺0,00" which reads as missing data.
            //
            // Discount / gift card / surcharge stay strict (>0): showing
            // them as ₺0,00 would be visual clutter (the visitor wouldn\'t
            // know what they mean).
            'has_discount'       => $discount_cents > 0,
            'has_tax'            => (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX === true),
            'has_shipping_cost'  => (bool)$needs_shipping,
            'has_gift_card'      => $gc_disc_cents > 0,
            'has_surcharge'      => $surcharge_cents > 0,
        );
        _eo_apply_visibility_bindings($tree_decoded_for_eo, $_eo_vis_ctx);
        _eo_apply_section_bindings($tree_decoded_for_eo, $sections_html);

        // ── 1b. Split off any recipient_loop_area BEFORE the field-bindings
        // walker runs. The walker takes a single ship_rid and rewrites every
        // shipping_<X> field name accordingly — applying it to a node that
        // lives inside recipient_loop_area would burn-in recipients[0]'s rid
        // for ALL iterations. By extracting the template first we can re-run
        // the walker per-recipient with that recipient's rid in
        // _eo_render_recipient_loop().
        //
        // The static tree gets a <!--pg-recipient-loop-slot--> marker which is
        // str_replace'd with the concatenated per-recipient HTML after the
        // static render. Trees without a recipient_loop_area get NULL and the
        // marker substitution becomes a no-op.
        $eo_recipient_tmpl  = null;
        if (!empty($_eo_has_recipient_loop)) {
            $rl_split          = _eo_split_recipient_loop_area($tree_decoded_for_eo);
            $tree_decoded_for_eo = $rl_split['static_tree'];
            $eo_recipient_tmpl = $rl_split['template_children'];
        }

        if ($_eo_lf) _eo_apply_field_bindings($tree_decoded_for_eo, $_eo_lf, $_eo_ship_rid);
        if ($_eo_lf) _eo_prefill_input_values_in_tree($tree_decoded_for_eo, $_eo_lf);
        _eo_apply_action_bindings($tree_decoded_for_eo);

        // ── 2. Split off any loop_area the designer placed (typically the
        // cart items <tbody>). _split_widget_tree replaces the loop_area
        // with a `<!--pg-loop-slot-->` marker so we can render the static
        // surrounding markup once and inject the per-item rendering into
        // the marker's position. Trees without a loop_area get a NULL
        // loop_children and we skip the loop pass.
        $eo_split        = _split_widget_tree($tree_decoded_for_eo);
        $eo_static_tree  = $eo_split['static_tree'];
        $eo_loop_kids    = $eo_split['loop_children'];

        // ── 3. Render the static portion → HTML with ^^token^^ placeholders.
        $designed_body = (string)_render_tree_node($eo_static_tree, 0, 0);

        // ── 4. Per-item loop pass: render template once, str_replace per-item
        // tokens, concat, inject at marker. The template renders the same
        // way regardless of how many items — we just substitute per iteration.
        if (is_array($eo_loop_kids) && !empty($eo_loop_kids) && !empty($items)) {
            $tmpl_html = '';
            foreach ($eo_loop_kids as $kid) {
                $tmpl_html .= (string)_render_tree_node($kid, 0, 0);
            }
            $csrf_token  = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
            $eo_back_url = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            $loop_html = '';
            foreach ($items as $_it) {
                $item_vars = _eo_compute_item_tokens($_it, $widget_id, $form_id, $_eo_fmt, $_eo_lf, $gift_card_data, $base_path, $sw_dir, $csrf_token, $eo_back_url);
                $loop_html .= _eo_replace_tokens($tmpl_html, $item_vars);
            }
            $designed_body = str_replace('<!--pg-loop-slot-->', $loop_html, $designed_body);
        } else {
            // No loop_area OR empty cart — just strip the marker if present.
            $designed_body = str_replace('<!--pg-loop-slot-->', '', $designed_body);
        }

        // ── 4b. Per-recipient loop pass: when the designer placed a
        // recipient_loop_area, render its template ONCE PER recipient.
        // Each iteration rewrites shipping_<field> eo_field bindings to
        // shipping_<rid>_<field> via _eo_apply_field_bindings, pre-fills
        // session values, then replaces per-recipient ^^__recipient_*^^
        // tokens. Empty recipients list collapses the marker silently.
        if (is_array($eo_recipient_tmpl) && !empty($eo_recipient_tmpl) && !empty($recipients)) {
            $recipient_loop_html = _eo_render_recipient_loop($eo_recipient_tmpl, $recipients, $_eo_lf);
            $designed_body = str_replace('<!--pg-recipient-loop-slot-->', $recipient_loop_html, $designed_body);
        } else {
            $designed_body = str_replace('<!--pg-recipient-loop-slot-->', '', $designed_body);
        }

        // ── 5. Static token replacement: subtotal_formatted, total_formatted,
        // cart_count, currency_symbol, form_id, … See _eo_compute_static_tokens
        // for the full list. These resolve OUTSIDE the loop.
        $static_vars = _eo_compute_static_tokens(array(
            'subtotal_cents'             => $sub_cents,
            'tax_cents'                  => $tax_cents,
            'shipping_cents'             => $ship_cents,
            'discount_cents'             => $discount_cents,
            'gift_card_discount_cents'   => $gc_disc_cents,
            'surcharge_cents'            => $surcharge_cents,
            'total_cents'                => $total_cents,
            'total_with_surcharge_cents' => $total_with_surcharge_cents,
            'cart_count'                 => $cart_count,
            'form_id'                    => $form_id,
            'fmt'                        => $_eo_fmt,
            // Drives the "Ücretsiz" relabel on shipping when cost is 0
            // but the cart still needs a shipping line (free shipping promo,
            // no method picked yet). Digital orders don\'t pass this branch
            // because the entire row is hidden via has_shipping_cost=false.
            'needs_shipping'             => (bool)$needs_shipping,
        ));
        $designed_body = _eo_replace_tokens($designed_body, $static_vars);

        // ── 5b. Auto-inject upsell + applied offers when designer\'s saved
        // tree has no eo_upsell_offers / eo_applied_offers binding. Without
        // this fallback, designers who customized their tree BEFORE the
        // offers feature shipped wouldn\'t see the banner — they\'d have to
        // re-add the bindings manually. Auto-injection is non-destructive:
        // if the tree already includes the section anchor, the offers HTML
        // was already substituted into it; we detect via the anchor classes.
        $eo_auto_upsell = (!empty($sections_html['upsell_offers'])
                           && strpos($designed_body, 'pg-eo-upsell-offer') === false)
                              ? '<div class="container pg-eo-upsell-auto pt-3">' . $sections_html['upsell_offers'] . '</div>'
                              : '';
        $eo_auto_applied = (!empty($sections_html['applied_offers'])
                            && strpos($designed_body, 'pg-eo-applied-offers') === false)
                              ? '<div class="container pg-eo-applied-auto pt-3">' . $sections_html['applied_offers'] . '</div>'
                              : '';

        // Backward-compat: legacy trees that use the `terms` section binding
        // (server-rendered checkbox + link → `#pg-eo-terms-modal`) but don't
        // have a real modal element in the tree would have a dangling link
        // to nowhere. Detect this by checking if the rendered body references
        // the modal id without actually defining it, and append the default
        // Bootstrap modal as a safety net. New trees with the real modal
        // node in the tree skip this branch entirely.
        $eo_modal_fallback = '';
        if (strpos($designed_body, '#pg-eo-terms-modal') !== false
            && strpos($designed_body, 'id="pg-eo-terms-modal"') === false) {
            $eo_modal_fallback = _eo_render_terms_modal_html($cfg);
        }

        // ── 6. Wrap in the host <form>.
        // Terms modal: the designer places a REAL Bootstrap modal element
        // in the tree (it's rendered as part of $designed_body). The
        // accompanying _pg_eo_modal_relocate_inline_js() (appended to
        // $widget_js below) re-parents any .modal node inside the EO form
        // to <body> on DOM ready so the Bootstrap backdrop manager works
        // properly (nested-in-form modals leave a stuck black overlay).
        $form_html =
            '<div class="container py-4 pg-eo-wrap">'
            . $eo_auto_upsell
            . $eo_auto_applied
            . '<form id="' . h($form_id) . '" method="post" action="' . h($eo_action_url) . '" class="pg-eo-form" data-pg-eo-form="1" data-pg-eo-widget-id="' . (int)$widget_id . '">'
            . $token_field
            . '<input type="hidden" name="page_id" value="' . (int)$_eo_page_id . '">'
            . '<input type="hidden" name="current_url" value="' . h($request_uri) . '">'
            . $totals_hidden_html
            . $designed_body
            . '</form>'
            . $eo_modal_fallback
            . $widget_js
            . '</div>';
        return _pg_widget_layout_css_once() . $form_html;
    }

    // ── Fallback: monolithic Phase-1 layout (untouched by designer) ──────────
    // Auto-inject upsell + applied offers above the form, same as the
    // bindings-first path. Designers using the legacy layout still get
    // the promotional banners without any extra setup.
    $eo_mono_upsell  = !empty($sections_html['upsell_offers'])
                        ? '<div class="pg-eo-upsell-auto mb-3">' . $sections_html['upsell_offers'] . '</div>' : '';
    $eo_mono_applied = !empty($sections_html['applied_offers'])
                        ? '<div class="pg-eo-applied-auto mb-3">' . $sections_html['applied_offers'] . '</div>' : '';
    $form_html =
        '<div class="container py-4 pg-eo-wrap">'
        . $errors_html
        . $notices_html
        . $eo_mono_upsell
        . $eo_mono_applied
        . '<form id="' . h($form_id) . '" method="post" action="' . h($eo_action_url) . '" class="pg-eo-form" data-pg-eo-form="1" data-pg-eo-widget-id="' . (int)$widget_id . '">'
        . $token_field
        . '<input type="hidden" name="page_id" value="' . (int)$_eo_page_id . '">'
        . '<input type="hidden" name="current_url" value="' . h($request_uri) . '">'
        . $totals_hidden_html
        . '<div class="row g-3">'
        .   '<div class="col-12 col-lg-8">'
        .     '<section class="pg-eo-section pg-eo-cart mb-4">'
        .       '<h2 class="h5 mb-3 text-muted">' . h(lang('Order items')) . '</h2>'
        .       $cart_html
        .     '</section>'
        .     ($shipping_html !== ''
                ? '<section class="pg-eo-section pg-eo-shipping mb-4">'
                .   '<h2 class="h4 mb-3">' . h(lang('Shipping')) . '</h2>'
                .   $shipping_html
                . '</section>'
                : '')
        .     '<section class="pg-eo-section pg-eo-billing mb-4">'
        .       '<h2 class="h4 mb-3">' . h(lang('Billing')) . '</h2>'
        .       $billing_html
        .     '</section>'
        .     '<section class="pg-eo-section pg-eo-payment mb-4">'
        .       '<h2 class="h4 mb-3">' . h(lang('Payment')) . '</h2>'
        .       $payment_html
        .     '</section>'
        .   '</div>'
        .   '<div class="col-12 col-lg-4">'
        .     '<section class="pg-eo-section pg-eo-totals position-lg-sticky" style="top:1rem">'
        .       '<h2 class="h4 mb-3">' . h(lang('Order summary')) . '</h2>'
        .       $totals_html
        .       $buttons_html
        .     '</section>'
        .   '</div>'
        . '</div>'
        . '</form>'
        // Terms modal — outside the form so Bootstrap backdrop works.
        . _eo_render_terms_modal_html($cfg)
        . $widget_js
        . '</div>';

    return _pg_widget_layout_css_once() . $form_html;
}

// ── Read-only copy of a liveform's fields ────────────────────────────────
//
// The cart renderer draws the Messages node before it builds the item rows,
// and that node removes the liveform from the session. Row builders that
// want the visitor's rejected values (gift-card recipient, recurring
// schedule) read this copy instead: the same three questions a liveform
// answers, taken from the session array before anything consumed it.
if (!class_exists('pg_liveform_snapshot')) {
    class pg_liveform_snapshot
    {
        private $fields;

        public function __construct($fields)
        {
            $this->fields = is_array($fields) ? $fields : array();
        }

        public function field_in_session($field)
        {
            return !empty($this->fields[$field]);
        }

        public function get_field_value($field)
        {
            return isset($this->fields[$field]['value']) ? $this->fields[$field]['value'] : '';
        }

        public function check_field_error($field)
        {
            return !empty($this->fields[$field]['error']);
        }
    }
}

// ── Recurring schedule: product defaults ────────────────────────────────
//
// The schedule a recurring row carries when the customer has not (or may
// not) choose one. Same three rules the legacy cart update applies to a row
// whose schedule is not editable: a blank product period means Monthly, the
// number of payments is the product's, the first charge falls `start` days
// from today.
//
// @param array $row  Cart row with product columns payment_period,
//                    product_number_of_payments, recurring_start_days
// @return array      period, payments (int), start_date (Y-m-d)
function _pg_cart_recurring_defaults($row)
{
    $period = trim((string)($row['payment_period'] ?? ''));
    if ($period === '') $period = 'Monthly';
    $days = (int)($row['recurring_start_days'] ?? 0);
    return array(
        'period'     => $period,
        'payments'   => (int)($row['product_number_of_payments'] ?? 0),
        'start_date' => date('Y-m-d', time() + (86400 * max(0, $days))),
    );
}

// ── Recurring schedule controls for one cart row ─────────────────────────
//
// Frequency, number of payments and start date for a product whose schedule
// the customer may set (products.recurring_schedule_editable_by_customer).
// Field names are the legacy cart's (recurring_payment_period_<item id> …),
// so cart_action.php and the legacy shopping_cart.php read the same POST.
//
// The gateway decides two things, exactly as in get_shopping_cart.php: the
// number of payments is required (and range-limited) on ClearCommerce and
// First Data, and the start date box is absent on ClearCommerce, which
// always bills from today. The date box is <input type="date">, so the value
// travels as Y-m-d — the same shape the gift-card delivery date uses in this
// widget; cart_action.php also accepts the legacy d/m/Y typing.
//
// Values: the visitor's rejected attempt (liveform) wins, then the row's
// stored schedule, which the renderer seeds from the product before this
// runs — so the controls never open blank.
//
// @param array                 $row        Cart row (see the SELECT in the renderer)
// @param pg_liveform_snapshot  $lf         Copy of the cart's liveform, for values and errors
// @param string                $form_attr  ` form="…"` attribute tying inputs to the cart form
// @return string                   Fieldset markup
function _pg_render_cart_item_recurring_schedule($row, $lf, $form_attr)
{
    $iid = (int)($row['item_id'] ?? 0);
    if ($iid <= 0) return '';

    $cc_on   = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD == true;
    $gateway = defined('ECOMMERCE_PAYMENT_GATEWAY') ? (string)ECOMMERCE_PAYMENT_GATEWAY : '';
    $payments_required = $cc_on && ($gateway === 'ClearCommerce' || $gateway === 'First Data Global Gateway');
    $show_start_date   = !$cc_on || $gateway !== 'ClearCommerce';
    $payments_min = $cc_on && $gateway === 'ClearCommerce' ? 2 : 1;
    $payments_max = ($cc_on && $gateway === 'ClearCommerce') ? 999
                  : (($cc_on && $gateway === 'First Data Global Gateway') ? 99 : 0);

    $name_period   = 'recurring_payment_period_' . $iid;
    $name_payments = 'recurring_number_of_payments_' . $iid;
    $name_start    = 'recurring_start_date_' . $iid;

    // Rejected attempt first, stored schedule second.
    $value = function ($name, $stored) use ($lf) {
        if ($lf && $lf->field_in_session($name)) {
            return (string)$lf->get_field_value($name);
        }
        return (string)$stored;
    };
    $invalid = function ($name) use ($lf) {
        return ($lf && $lf->check_field_error($name)) ? ' is-invalid' : '';
    };

    $period   = $value($name_period, (string)($row['recurring_payment_period'] ?? ''));
    $payments = $value($name_payments, (int)($row['recurring_number_of_payments'] ?? 0) > 0
                                        ? (string)(int)$row['recurring_number_of_payments'] : '');
    $start    = (string)($row['recurring_start_date'] ?? '');
    if ($start === '0000-00-00') $start = '';
    $start    = $value($name_start, $start);
    // The legacy screen's typing (d/m/Y) may sit in the session after a
    // rejected update; the date box only understands Y-m-d.
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && function_exists('validate_date') && validate_date($start)) {
        $start = prepare_form_data_for_input($start, 'date');
    }

    $field = function ($name, $label, $control, $required, $help = '') {
        return '<div class="mb-3 pg-cart-recurring-field">'
             . '<label class="form-label" for="' . h($name) . '">' . h($label)
             . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
             . $control
             . ($help !== '' ? '<div class="form-text">' . h($help) . '</div>' : '')
             . '</div>';
    };

    $options = '';
    foreach (get_payment_period_options() as $label => $val) {
        if ((string)$val === '') continue;
        $options .= '<option value="' . h($val) . '"' . ((string)$val === $period ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $html = '<fieldset class="pg-cart-recurring-set border rounded p-3 mb-3" data-pg-recurring-item="' . $iid . '">'
          . '<legend class="float-none w-auto px-2 fs-6 fw-semibold">' . h(lang('Payment Schedule')) . '</legend>'
          . $field($name_period, lang('Frequency'),
                '<select class="form-select' . $invalid($name_period) . '" required' . $form_attr
              . ' id="' . h($name_period) . '" name="' . h($name_period) . '">' . $options . '</select>', true)
          . $field($name_payments, lang('Number of Payments'),
                '<input type="number" class="form-control' . $invalid($name_payments) . '"' . $form_attr
              . ' id="' . h($name_payments) . '" name="' . h($name_payments) . '"'
              . ' value="' . h($payments) . '" min="' . $payments_min . '"' . ($payments_max > 0 ? ' max="' . $payments_max . '"' : '')
              . ' step="1"' . ($payments_required ? ' required' : '') . '>',
                $payments_required, trim((string)get_number_of_payments_message(), ' ()'));
    if ($show_start_date) {
        $html .= $field($name_start, lang('Start Date'),
                '<input type="date" class="form-control' . $invalid($name_start) . '" required' . $form_attr
              . ' id="' . h($name_start) . '" name="' . h($name_start) . '"'
              . ' value="' . h($start) . '" min="' . date('Y-m-d') . '">', true);
    }
    $html .= '</fieldset>';
    return $html;
}

// ── Tax / shipping disclaimer for the cart totals ────────────────────────
//
// The cart shows a subtotal, but on most sites neither tax nor shipping can
// be known until the visitor has entered an address. Legacy prints a short
// "will be calculated at checkout" line under the totals; without it the
// subtotal reads as the final price and the number jumps at checkout.
//
// The sentence names only what is genuinely still unknown:
//   • shipping — only when the cart holds a shippable item AND the site
//     charges shipping at all
//   • tax      — only when the site charges tax
// Neither applies (all-digital cart, tax-free site) → empty string, so the
// bound element collapses instead of printing a disclaimer about nothing.
//
// @param bool $has_shippable_item Cart contains at least one shippable item
// @return string                  Plain sentence (already lang()'d), or ''
function _pg_cart_tax_shipping_notice($has_shippable_item)
{
    $tax_applies = (!defined('ECOMMERCE_TAX') || ECOMMERCE_TAX == true);
    $ship_applies = $has_shippable_item
                 && (!defined('ECOMMERCE_SHIPPING') || ECOMMERCE_SHIPPING == true);

    if ($tax_applies && $ship_applies) return (string)lang('Tax and shipping will be calculated at checkout.');
    if ($ship_applies)                 return (string)lang('Shipping will be calculated at checkout.');
    if ($tax_applies)                  return (string)lang('Tax will be calculated at checkout.');
    return '';
}

// ── Currency selector for the cart ───────────────────────────────────────
//
// Legacy builds this at get_shopping_cart.php:2992-3037 and posts it to the
// existing update_currency.php endpoint (which stores the choice in the
// session, the visitor row and the order). We reuse that endpoint rather than
// adding a second write path — currency is a site-wide visitor preference,
// not a cart-local one.
//
// Renders nothing unless ECOMMERCE_MULTICURRENCY is on AND there is more than
// one usable currency: a lone "TRY" dropdown is a control with no choice in
// it. Exchange rate 0 rows are excluded, same as legacy.
//
// Own <form> (posts to update_currency.php), so like the pending-offers block
// it must NOT be nested inside the cart-update form.
//
// @param string $send_to Same-host path to return to after switching
// @return string         HTML block, or '' when there is nothing to choose.
function _pg_cart_currency_selector($send_to)
{
    if (!defined('ECOMMERCE_MULTICURRENCY') || !ECOMMERCE_MULTICURRENCY) return '';

    $rows = db_items(
        "SELECT id, name, code
         FROM currencies
         WHERE exchange_rate != '0'
         ORDER BY base DESC, name ASC"
    );
    if (!is_array($rows) || count($rows) < 2) return '';

    $current = defined('VISITOR_CURRENCY_ID') ? (int)VISITOR_CURRENCY_ID : 0;
    $base    = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $sw_dir  = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $token   = function_exists('get_token_field') ? get_token_field() : '';

    // onchange submit: a separate "apply" button for a two-item dropdown is
    // one click more than the choice is worth. Still inside a real <form>, so
    // it degrades to a normal submit when JS is off — hence the fallback
    // button, hidden from JS-capable browsers by the noscript-style class.
    $html = '<form class="pg-cart-currency" method="post" action="'
          . h($base . $sw_dir . '/update_currency.php') . '">'
          . $token
          . '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
          . '<label class="form-label small mb-1" for="pgCartCurrency">'
          . h(lang('Currency')) . '</label>'
          . '<select class="form-select form-select-sm" id="pgCartCurrency" name="currency_id"'
          . ' onchange="this.form.submit()">';
    foreach ($rows as $c) {
        $label = (string)$c['name'] . ' (' . (string)$c['code'] . ')';
        $html .= '<option value="' . (int)$c['id'] . '"'
               . ((int)$c['id'] === $current ? ' selected' : '') . '>'
               . h($label) . '</option>';
    }
    $html .= '</select>'
           . '<noscript><button type="submit" class="btn btn-sm btn-outline-secondary mt-1">'
           . h(lang('Update')) . '</button></noscript>'
           . '</form>';
    return $html;
}

// ── Offline-payment toggle for the cart (STAFF ONLY) ─────────────────────
//
// Legacy renders this checkbox at get_shopping_cart.php:1601-1621 behind
// three conditions, all of which are preserved here:
//   1. ECOMMERCE_OFFLINE_PAYMENT is on
//   2. someone is logged in
//   3. that user is staff (role < 3) OR carries the `set_offline_payment` flag
//
// This is deliberately NOT a customer-facing control: it marks the order as
// payable offline (bank transfer / invoice), which for a visitor would be a
// way to check out without paying. It exists so an operator taking an order
// over the phone can flag it. Returning '' for everyone else means the
// designer's bound element collapses rather than rendering a live checkbox.
//
// @param int    $order_id     Current session order
// @param string $cart_form_id id of the cart-update <form> (HTML5 form attr)
// @param string $cart_label   Widget's cart label, used in the sentence
// @return string              Checkbox block, or '' when not permitted.
function _pg_cart_offline_payment_checkbox($order_id, $cart_form_id, $cart_label)
{
    if (!defined('ECOMMERCE_OFFLINE_PAYMENT') || ECOMMERCE_OFFLINE_PAYMENT != true) return '';
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) return '';

    $role = defined('USER_ROLE') ? (int)USER_ROLE : 99;
    $can  = ($role < 3);
    if (!$can && defined('USER_ID') && (int)USER_ID > 0) {
        $can = (int)db_value(
            "SELECT user_set_offline_payment FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1"
        ) === 1;
    }
    if (!$can) return '';

    $checked = ((int)$order_id > 0)
        && ((int)db_value("SELECT offline_payment_allowed FROM orders WHERE id = '" . (int)$order_id . "' LIMIT 1") === 1);

    $label = trim((string)$cart_label);
    if ($label === '') $label = (string)lang('Cart');

    $form_attr = ($cart_form_id !== '') ? ' form="' . h($cart_form_id) . '"' : '';

    return '<div class="form-check pg-cart-offline-payment">'
         . '<input class="form-check-input" type="checkbox" value="1"'
         . ' name="offline_payment_allowed" id="pgCartOfflinePayment"'
         . $form_attr . ($checked ? ' checked' : '') . '>'
         . '<label class="form-check-label small" for="pgCartOfflinePayment">'
         . h(lang(array(
               'string' => 'Allow offline payment option for this {var:1} (and click update to apply).',
               'vars'   => $label,
           )))
         . '</label>'
         . '</div>';
}

// ── Pending offers section — "gift with purchase" the visitor must CLAIM ──
//
// A pending offer is an offer whose action is `add product`: the cart already
// qualifies, but the bonus product only lands in the basket when the visitor
// presses "Ekle". Legacy renders this as the "Special Offers" fieldset at the
// top of /sepet (get_shopping_cart.php:132-295). The system widget had no
// equivalent at all — a campaign gift simply could not be claimed, which is
// why this is the highest-impact gap in the parity list.
//
// Field names are copied verbatim from the legacy template because
// add_pending_offers() (functions.php) parses them with substr/explode:
//   pending_offers                                        hidden, 'true'
//   add_pending_offer_<offer_id>_<action_id>              submit
//   pending_offer_<offer_id>_<action_id>_ship_to          select (shippable only)
//   pending_offer_<offer_id>_<action_id>_add_name         text   (shippable only)
//
// The recipient controls only appear when the bonus product is shippable AND
// the site is in multi-recipient mode — same three-way gate as legacy.
//
// @param array  $pending_offers apply_offers_to_cart()['pending_offers']
// @param string $post_url       endpoint the form posts to (cart_action.php)
// @param string $send_to        same-host return path for the redirect
// @return string                HTML block; '' when nothing is claimable.
function _pg_render_pending_offers($pending_offers, $post_url, $send_to)
{
    if (!is_array($pending_offers) || empty($pending_offers)) return '';

    // Multi-recipient gate — identical to legacy's condition. When shipping is
    // off, or the site only ever ships to one address, there is nothing to ask.
    $recipient_mode_multi = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == true)
                          && (!defined('ECOMMERCE_RECIPIENT_MODE') || ECOMMERCE_RECIPIENT_MODE !== 'single recipient');

    $rows = '';
    foreach ($pending_offers as $offer) {
        if (!is_array($offer) || empty($offer['offer_actions'])) continue;
        $offer_id = (int)$offer['id'];

        // When an offer adds MORE than one product the description alone is
        // ambiguous ("Buy 2 get a gift" — which gift?), so legacy appends the
        // action name. Same query, same rule.
        $multi_action = (int)db_value(
            "SELECT COUNT(offers_offer_actions_xref.offer_action_id)
             FROM offers_offer_actions_xref
             LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id
             WHERE offers_offer_actions_xref.offer_id = '" . $offer_id . "'
               AND offer_actions.type = 'add product'"
        ) > 1;

        foreach ($offer['offer_actions'] as $action) {
            if (!is_array($action)) continue;
            $action_id = (int)$action['id'];
            $field_base = 'pending_offer_' . $offer_id . '_' . $action_id;

            $label = (string)$offer['description'];
            if ($multi_action && !empty($action['name'])) {
                $label .= ' (' . (string)$action['name'] . ')';
            }

            // ── Recipient picker ──────────────────────────────────────────
            $recipient_html = '';
            $shippable = !empty($action['add_product_shippable']);
            // When the cart already says where the gift belongs - the recipient
            // paying for what earned it - the visitor is told, not asked.
            $decided_ship_to_id = 0;
            if ($shippable && $recipient_mode_multi && function_exists('get_ship_to_id_for_offer_gift')) {
                $decided_ship_to_id = get_ship_to_id_for_offer_gift(
                    $offer_id,
                    (!empty($action['allowed_recipients']) && is_array($action['allowed_recipients'])) ? $action['allowed_recipients'] : array());
            }
            if ($decided_ship_to_id) {
                $decided_name = (string) db_value("SELECT ship_to_name FROM ship_tos WHERE id = '" . (int) $decided_ship_to_id . "' LIMIT 1");
                if ($decided_name !== '') {
                    $recipient_html = '<span class="small text-body-secondary">' . h(lang('Ship to')) . ' <span class="fw-semibold">' . h($decided_name) . '</span></span>';
                }
            } elseif ($shippable && $recipient_mode_multi) {
                $options = array('' => '');
                if (!empty($action['allowed_recipients']) && is_array($action['allowed_recipients'])) {
                    // Offer restricted to specific ship_tos — only those names.
                    foreach ($action['allowed_recipients'] as $ship_to_id) {
                        $name = (string)db_value(
                            "SELECT ship_to_name FROM ship_tos WHERE id = '" . (int)$ship_to_id . "' LIMIT 1"
                        );
                        if ($name !== '') $options[$name] = $name;
                    }
                    $allow_new_name = false;
                } else {
                    // Any recipient — offer 'myself' plus every name already in
                    // the session, and let the visitor type a new one.
                    if (function_exists('initialize_recipients')) initialize_recipients();
                    // Key is the option value here, and the value is what the
                    // cart stores as the recipient name; the English word would
                    // open a second recipient beside the buyer's own.
                    $options[lang('myself')] = lang('myself');
                    if (!empty($_SESSION['ecommerce']['recipients']) && is_array($_SESSION['ecommerce']['recipients'])) {
                        foreach ($_SESSION['ecommerce']['recipients'] as $recipient) {
                            $options[(string)$recipient] = (string)$recipient;
                        }
                    }
                    $allow_new_name = true;
                }

                $recipient_html = '<label class="form-label small mb-1" for="' . h($field_base) . '_ship_to">'
                                . h(lang('Ship to')) . '</label>'
                                . '<select class="form-select form-select-sm" id="' . h($field_base) . '_ship_to"'
                                . ' name="' . h($field_base) . '_ship_to">';
                foreach ($options as $value => $text) {
                    $recipient_html .= '<option value="' . h($value) . '">' . h($text) . '</option>';
                }
                $recipient_html .= '</select>';
                if ($allow_new_name) {
                    // placeholder rather than legacy's "or add name" default
                    // VALUE + onfocus-clear hack — a real value would be
                    // submitted verbatim if the visitor never focused the field.
                    $recipient_html .= '<input type="text" class="form-control form-control-sm mt-1"'
                                     . ' name="' . h($field_base) . '_add_name" maxlength="50"'
                                     . ' placeholder="' . h(lang('or add name')) . '">';
                }
            }

            $rows .= '<div class="pg-cart-pending-offer d-flex flex-wrap align-items-end gap-2 py-2 border-bottom">'
                   . '<div class="flex-grow-1 min-width-0">'
                   .   '<span class="fw-semibold">' . h($label) . '</span>'
                   . '</div>'
                   . ($recipient_html !== '' ? '<div class="pg-cart-pending-offer-recipient">' . $recipient_html . '</div>' : '')
                   . '<div>'
                   .   '<button type="submit" class="btn btn-primary btn-sm"'
                   .          ' name="add_pending_offer_' . $offer_id . '_' . $action_id . '" value="1">'
                   .     h(lang('Add')) . '</button>'
                   . '</div>'
                   . '</div>';
        }
    }

    if ($rows === '') return '';

    $token = function_exists('get_token_field') ? get_token_field() : '';

    return '<form class="pg-cart-pending-offers card card-body mb-3" method="post" action="' . h($post_url) . '">'
         . $token
         . '<input type="hidden" name="pending_offers" value="true">'
         . '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
         . '<div class="fw-semibold mb-2"><i class="bi bi-gift me-1"></i>' . h(lang('Special Offers')) . '</div>'
         . $rows
         . '</form>';
}

/**
 * Quick add box — pick one of a chosen product group's items and drop it in
 * the cart without leaving the basket. Legacy: get_shopping_cart.php:313-500
 * plus the POST branch in shopping_cart.php:42-185.
 *
 * Own <form> (posts quick_add=1 to cart_action.php), so it must not sit
 * inside the cart-update form — nested forms are dropped by the parser and
 * "Add" would submit the wrong one.
 *
 * Legacy showed / hid the quantity, amount and recipient rows with a
 * per-product JS array. Here every product carries its own fields in a
 * data attribute and one small inline script (no jQuery, no globals) shows
 * the rows the selected product needs. When only one kind of product is in
 * the group, the rows that can never apply are not rendered at all.
 *
 * Returns '' when the group is unset, disabled, empty, or holds nothing the
 * visitor can actually buy right now — no empty box with a dead button.
 */
function _pg_render_quick_add($group_id, $label, $post_url, $send_to)
{
    $group_id = (int)$group_id;
    if ($group_id <= 0) return '';
    if (!db_value("SELECT enabled FROM product_groups WHERE id = '" . e($group_id) . "'")) return '';

    $rows = db_items(
        "SELECT products.id, products.name, products.short_description, products.price,
                products.shippable, products.selection_type, products.default_quantity,
                products.inventory, products.inventory_quantity, products.backorder,
                products.out_of_stock_message
         FROM products_groups_xref
         LEFT JOIN products ON products.id = products_groups_xref.product
         WHERE products_groups_xref.product_group = '" . e($group_id) . "'
           AND products.enabled = '1'
         ORDER BY products_groups_xref.sort_order, products.name");
    if (!is_array($rows) || empty($rows)) return '';

    $discounted = function_exists('get_discounted_product_prices') ? get_discounted_product_prices() : array();
    $multi_recipient = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == true)
                    && (defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE == 'multi-recipient');

    $options   = '';
    $any       = false;
    $needs_qty = false;
    $needs_amt = false;
    $needs_to  = false;

    foreach ($rows as $r) {
        $id             = (int)$r['id'];
        $selection_type = (string)$r['selection_type'];
        $in_stock       = (($r['inventory'] == 0) || ($r['inventory_quantity'] > 0) || ($r['backorder'] == 1));
        $recipient      = ($multi_recipient && $r['shippable'] == 1) ? 1 : 0;

        if ($in_stock) {
            $any = true;
            if ($selection_type == 'quantity') $needs_qty = true;
            if ($selection_type == 'donation') $needs_amt = true;
            if ($recipient)                    $needs_to  = true;
        }

        // Label: name - description (price), plus the out-of-stock line when
        // there is one. Same composition legacy used.
        $text = ((string)$r['name'] !== '' ? (string)$r['name'] . ' - ' : '') . (string)$r['short_description'];
        if ($selection_type != 'donation' && function_exists('prepare_price_for_output')) {
            $has_disc = isset($discounted[$id]);
            $text .= ' (' . prepare_price_for_output($r['price'], $has_disc, $has_disc ? $discounted[$id] : '', 'plain_text') . ')';
        }
        if (($r['inventory'] == 1) && ($r['inventory_quantity'] == 0)
            && ((string)$r['out_of_stock_message'] !== '') && ((string)$r['out_of_stock_message'] !== '<p></p>')) {
            $oos = trim(function_exists('convert_html_to_text') ? convert_html_to_text($r['out_of_stock_message']) : strip_tags($r['out_of_stock_message']));
            if (mb_strlen($oos) > 50) $oos = mb_substr($oos, 0, 50) . '...';
            $text .= ' - ' . $oos;
        }

        // prepare_price_for_output() returns the currency symbol as an HTML
        // entity (&#8378;) and the short description may carry entities of
        // its own. Decode first, escape once — otherwise the option reads
        // "&#8378;299.95" literally.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        $options .= '<option value="' . $id . '"'
                  . ' data-qty="' . (($selection_type == 'quantity') ? '1' : '0') . '"'
                  . ' data-amount="' . (($selection_type == 'donation') ? '1' : '0') . '"'
                  . ' data-recipient="' . $recipient . '"'
                  . ' data-default-qty="' . (int)$r['default_quantity'] . '"'
                  . ($in_stock ? '' : ' disabled')
                  . '>' . h($text) . '</option>';
    }
    if (!$any) return '';

    // Recipient picker: "myself" plus whoever is already on the order, and a
    // free-text field for a new name — the same three-way choice legacy gave.
    $ship_to_html = '';
    if ($needs_to) {
        if (function_exists('initialize_recipients')) initialize_recipients();
        $opts = '<option value=""></option><option value="' . h(lang('myself')) . '">' . h(lang('myself')) . '</option>';
        if (!empty($_SESSION['ecommerce']['recipients']) && is_array($_SESSION['ecommerce']['recipients'])) {
            foreach ($_SESSION['ecommerce']['recipients'] as $recipient) {
                $opts .= '<option value="' . h((string)$recipient) . '">' . h((string)$recipient) . '</option>';
            }
        }
        $ship_to_html =
            '<div class="col-12 col-sm-4 pg-qa-row pg-qa-ship-to" style="display:none">'
          .   '<label class="form-label small mb-1" for="pg-qa-ship-to">' . h(lang('Ship to')) . '</label>'
          .   '<select class="form-select form-select-sm" id="pg-qa-ship-to" name="quick_add_ship_to">' . $opts . '</select>'
          .   '<input type="text" class="form-control form-control-sm mt-1" name="quick_add_add_name" maxlength="50" placeholder="' . h(lang('or add name')) . '">'
          . '</div>';
    }

    $qty_html = $needs_qty
        ? '<div class="col-6 col-sm-3 pg-qa-row pg-qa-qty" style="display:none">'
        .    '<label class="form-label small mb-1" for="pg-qa-qty">' . h(lang('Quantity')) . '</label>'
        .    '<input type="text" inputmode="numeric" class="form-control form-control-sm" id="pg-qa-qty" name="quick_add_quantity" value="1" size="4">'
        .  '</div>'
        : '';

    $amount_html = $needs_amt
        ? '<div class="col-6 col-sm-3 pg-qa-row pg-qa-amount" style="display:none">'
        .    '<label class="form-label small mb-1" for="pg-qa-amount">' . h(lang('Amount')) . '</label>'
        .    '<div class="input-group input-group-sm">'
        .      '<span class="input-group-text">' . (defined('BASE_CURRENCY_SYMBOL') ? html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8') : '') . '</span>'
        .      '<input type="text" inputmode="decimal" class="form-control" id="pg-qa-amount" name="quick_add_amount" size="6">'
        .    '</div>'
        .  '</div>'
        : '';

    $token = function_exists('get_token_field') ? get_token_field() : '';

    return
        '<form class="pg-cart-quick-add card card-body mb-3" method="post" action="' . h($post_url) . '">'
      .   $token
      .   '<input type="hidden" name="quick_add" value="1">'
      .   '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
      .   ($label !== '' ? '<div class="fw-semibold mb-2"><i class="bi bi-lightning-charge me-1"></i>' . h($label) . '</div>' : '')
      .   '<div class="row g-2 align-items-end">'
      .     '<div class="col-12 col-sm">'
      .       '<label class="form-label small mb-1 visually-hidden" for="pg-qa-product">' . h(lang('Product')) . '</label>'
      .       '<select class="form-select form-select-sm" id="pg-qa-product" name="quick_add_product_id">'
      .         '<option value=""></option>' . $options
      .       '</select>'
      .     '</div>'
      .     $qty_html . $amount_html . $ship_to_html
      .     '<div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">' . h(lang('Add')) . '</button></div>'
      .   '</div>'
      . '</form>'
      . '<script>(function(){'
      .   'var f=document.currentScript.previousElementSibling;if(!f)return;'
      .   'var s=f.querySelector("#pg-qa-product");if(!s)return;'
      .   'function show(sel,on){var e=f.querySelector(sel);if(e)e.style.display=on?"":"none";}'
      .   'function sync(){var o=s.options[s.selectedIndex],q=f.querySelector("#pg-qa-qty");'
      .     'var qty=o&&o.dataset.qty==="1",amt=o&&o.dataset.amount==="1",rec=o&&o.dataset.recipient==="1";'
      .     'show(".pg-qa-qty",qty);show(".pg-qa-amount",amt);show(".pg-qa-ship-to",rec);'
      .     'if(qty&&q&&o.dataset.defaultQty&&o.dataset.defaultQty!=="0")q.value=o.dataset.defaultQty;}'
      .   's.addEventListener("change",sync);sync();'
      . '})();</script>';
}

// ── Inline JS: remove-from-cart click handler (universal action binding) ──
// When the designer wires `_bindings.action='remove_from_cart'` on a non-<a>
// element (button, span, div), the action-binding walker stamps
// `data-pg-remove-href="^^__item_remove_url^^"` on it. The per-row token
// substitution swaps in the real URL, then this delegated click handler
// navigates the page to that URL on click. For real <a> elements the
// walker sets href directly and this handler is a no-op (anchor handles
// its own navigation).
function _pg_remove_from_cart_inline_js()
{
    if (defined('PG_REMOVE_FROM_CART_JS_EMITTED')) return '';
    define('PG_REMOVE_FROM_CART_JS_EMITTED', true);
    return <<<'HTML'
<script>
(function(){
    if (window.__pgRemoveFromCart) return; window.__pgRemoveFromCart = 1;
    document.addEventListener('click', function(e){
        var t = e.target && e.target.closest && e.target.closest('[data-pg-remove-href]');
        if (!t) return;
        var url = t.getAttribute('data-pg-remove-href');
        if (!url || url === '#') return;
        e.preventDefault();
        window.location.href = url;
    });
})();
</script>
HTML;
}

// ── Inline JS: relocate any .modal inside the EO form out to <body> ─────
// Bootstrap modals nested inside a <form> can leave a stuck backdrop after
// dismiss (the `body.modal-open` class lifts but the `.modal-backdrop`
// element isn't always cleaned because the modal's own dismiss handler
// races with the form's click capture). Moving the modal to be a direct
// child of <body> sidesteps this entirely — Bootstrap's own examples all
// place modals at the body level.
//
// We do this at DOM-ready instead of at render time so the designer's tree
// stays clean (the modal node is just a normal child of the EO root and the
// tree editor sees it as one of the children). The relocation preserves the
// modal's id + every data-bs-target reference because we move the actual
// DOM node — references resolve by id, location doesn't matter.
//
// Idempotent via window.__pgEoModalReloc; runs once per page even with
// multiple EO widgets.
function _pg_eo_modal_relocate_inline_js()
{
    if (defined('PG_EO_MODAL_RELOC_JS_EMITTED')) return '';
    define('PG_EO_MODAL_RELOC_JS_EMITTED', true);
    return <<<'HTML'
<script>
(function(){
    if (window.__pgEoModalReloc) return; window.__pgEoModalReloc = 1;
    function relocate(){
        var forms = document.querySelectorAll('[data-pg-eo-form]');
        for (var i = 0; i < forms.length; i++) {
            var modals = forms[i].querySelectorAll('.modal');
            for (var j = 0; j < modals.length; j++) {
                var m = modals[j];
                // Skip if already a child of body (defensive — double-call safety).
                if (m.parentNode === document.body) continue;
                document.body.appendChild(m);
            }
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', relocate);
    } else {
        relocate();
    }
})();
</script>
HTML;
}

// ── Inline JS: qty stepper buttons (cart + express_order + any designer-placed) ──
// Wires `[data-pg-qty-action="inc"|"dec"]` buttons to the nearest qty <input>
// (either inside the SAME .input-group / .pg-qty-stepper wrapper, or via
// `data-pg-qty-target="<input-id>"` when the designer placed +/- buttons far
// from the input). The handler:
//   1. Respects min / max / step on the input (default step=1, min=0)
//   2. Dispatches a synthetic `input` + `change` event so framework listeners
//      (and the legacy submit-update flow's dirty-state tracking) see the bump
//   3. Idempotent: re-running registers ONCE per page via window.__pgQtyStep
//
// The script is intentionally tiny and dependency-free — emitted once per page
// regardless of how many widgets (cart, express_order, custom) use steppers.
function _pg_qty_stepper_inline_js()
{
    if (defined('PG_QTY_STEPPER_JS_EMITTED')) return '';
    define('PG_QTY_STEPPER_JS_EMITTED', true);
    return <<<'HTML'
<script>
(function(){
    if (window.__pgQtyStep) return; window.__pgQtyStep = 1;
    function findInput(btn){
        // Explicit target wins — designer can wire +/- buttons to an input
        // anywhere on the page by setting data-pg-qty-target="some-input-id".
        var explicit = btn.getAttribute('data-pg-qty-target');
        if (explicit) { var el = document.getElementById(explicit); if (el) return el; }
        // Otherwise: walk up to the nearest grouping element and find the
        // first number/text input inside it. .pg-qty-stepper is our hint;
        // .input-group is the Bootstrap default. Either works.
        var wrap = btn.closest('.pg-qty-stepper, .input-group') || btn.parentNode;
        if (!wrap) return null;
        return wrap.querySelector('input[type="number"], input[type="text"], input[name^="quantity"], input[name^="quantities"]');
    }
    function step(input, dir){
        if (!input || input.disabled || input.readOnly) return;
        var cur  = parseFloat(input.value); if (isNaN(cur)) cur = 0;
        var stp  = parseFloat(input.step);  if (isNaN(stp) || stp <= 0) stp = 1;
        var min  = parseFloat(input.min);
        var max  = parseFloat(input.max);
        var next = cur + (dir * stp);
        if (!isNaN(min) && next < min) next = min;
        if (!isNaN(max) && next > max) next = max;
        // Preserve integer formatting when step is integer (avoids "1.0").
        var asStr = (Math.floor(stp) === stp) ? String(Math.round(next)) : String(next);
        if (input.value === asStr) return;
        input.value = asStr;
        // Fire input + change so listeners (frameworks, analytics, dirty
        // trackers) react — addEventListener('input') is the canonical hook.
        try { input.dispatchEvent(new Event('input',  { bubbles: true })); } catch(e){}
        try { input.dispatchEvent(new Event('change', { bubbles: true })); } catch(e){}
    }
    document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest && e.target.closest('[data-pg-qty-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-pg-qty-action');
        if (action !== 'inc' && action !== 'dec') return;
        e.preventDefault();
        var input = findInput(btn);
        if (input) step(input, action === 'inc' ? 1 : -1);
    });
})();
</script>
HTML;
}
