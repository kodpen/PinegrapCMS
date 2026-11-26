<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce as folded rows (includes/settings/fold.php): the
 * rows' order, names and icons, and each part's one line of state with its
 * pill. While the store is off every pill reads Off and no row asks for
 * attention; the line still says the part's own state. The
 * pg_commerce_fold_<part>() lines are pure but for lang();
 * includes/settings/commerce.php reads the facts they take.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The rows' keys in reading order: a shop's set-up first, its programmes
 * after. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_commerce_fold_order()
{
    return array('store', 'payments', 'shipping', 'address-autocomplete', 'stock-locations', 'giftcards', 'rewards', 'reference-price', 'affiliate', 'referral', 'feeds');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_commerce_fold_order()
 * @return array name, icon, tone
 */
function pg_commerce_fold_face($key)
{
    switch ($key) {
        case 'store':
            return array('name' => lang('Store'), 'icon' => 'bi-shop', 'tone' => 'pg-tone-shop');
        case 'payments':
            return array('name' => lang('Payment methods'), 'icon' => 'bi-credit-card', 'tone' => 'pg-tone-blue');
        case 'shipping':
            return array('name' => lang('Shipping'), 'icon' => 'bi-truck', 'tone' => 'pg-tone-amber');
        case 'address-autocomplete':
            return array('name' => lang('Address autocomplete'), 'icon' => 'bi-geo-alt', 'tone' => 'pg-tone-teal');
        case 'stock-locations':
            return array('name' => lang('Stock locations'), 'icon' => 'bi-boxes', 'tone' => 'pg-tone-amber');
        case 'giftcards':
            return array('name' => lang('Gift cards'), 'icon' => 'bi-gift', 'tone' => 'pg-tone-violet');
        case 'rewards':
            return array('name' => lang('Rewards'), 'icon' => 'bi-award', 'tone' => 'pg-tone-violet');
        case 'reference-price':
            return array('name' => lang('Reference price'), 'icon' => 'bi-tag', 'tone' => 'pg-tone-blue');
        case 'affiliate':
            return array('name' => lang('Affiliate programme'), 'icon' => 'bi-people', 'tone' => 'pg-tone-shop');
        case 'referral':
            return array('name' => lang('Referral programme'), 'icon' => 'bi-person-plus', 'tone' => 'pg-tone-shop');
        default:
            return array('name' => lang('Product feeds'), 'icon' => 'bi-rss', 'tone' => 'pg-tone-teal');
    }
}

/**
 * The On or Off pill of a part: Off whenever the store is off.
 *
 * @param bool $store_on Enable Commerce
 * @param bool $on       the part's own switch
 * @return array words, tone class
 */
function pg_commerce_fold_pill($store_on, $on)
{
    return ($store_on && $on) ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * A row's line, pill and attention; attention only while the store is on.
 *
 * @param bool       $store_on
 * @param string     $line
 * @param array|null $pill      the pill while the store is on
 * @param bool       $attention
 * @return array line, pill, attention
 */
function pg_commerce_fold_state($store_on, $line, $pill, $attention = false)
{
    return array(
        'line'      => (string) $line,
        'pill'      => $store_on ? $pill : pg_commerce_fold_pill(false, false),
        'attention' => $store_on && $attention,
    );
}

/**
 * Store's line: the next order number and whether tax-exempt orders are
 * allowed.
 *
 * @param bool $store_on
 * @param int  $next_order_number
 * @param bool $tax_exempt Tax and Allow tax-exempt are both on
 * @return array line, pill, attention
 */
function pg_commerce_fold_store($store_on, $next_order_number, $tax_exempt)
{
    $line = lang(array('string' => 'Next order number {var:1}', 'vars' => array((int) $next_order_number)));

    if ($tax_exempt) {
        $line .= ' · ' . lang('tax-exempt orders allowed');
    }

    return pg_commerce_fold_state($store_on, $line, pg_commerce_fold_pill(true, true));
}

/**
 * Payment methods' line: the methods that are on by name, or what is
 * missing.
 *
 * @param bool     $store_on
 * @param string[] $methods     the names of the methods switched on
 * @param string   $unconnected the card gateway chosen whose keys are not
 *                              all on Connections, '' for none
 * @return array line, pill, attention
 */
function pg_commerce_fold_payments($store_on, $methods, $unconnected)
{
    $unconnected = trim((string) $unconnected);
    $methods = array_values(array_filter(array_map('trim', (array) $methods), 'strlen'));

    if ($unconnected !== '') {
        return pg_commerce_fold_state($store_on, lang(array('string' => 'Connect {var:1} in Connections', 'vars' => array($unconnected))), array(lang('Not connected'), 'pg-tone-amber'), true);
    }

    if (!$methods) {
        return pg_commerce_fold_state($store_on, lang('No payment method is on'), array(lang('None'), 'pg-tone-amber'), true);
    }

    return pg_commerce_fold_state($store_on, implode(', ', $methods), pg_commerce_fold_pill(true, true));
}

/**
 * Shipping's line: one or several recipients an order, then the carriers
 * connected by name.
 *
 * @param bool     $store_on
 * @param bool     $shipping_on the Shipping switch
 * @param bool     $multi       multi-recipient
 * @param string[] $carriers    the names of the carriers connected
 * @return array line, pill, attention
 */
function pg_commerce_fold_shipping($store_on, $shipping_on, $multi, $carriers)
{
    if (!$shipping_on) {
        return pg_commerce_fold_state($store_on, lang('Off'), pg_commerce_fold_pill(true, false));
    }

    $line = $multi ? lang('Several recipients an order') : lang('One recipient an order');
    $carriers = array_values(array_filter(array_map('trim', (array) $carriers), 'strlen'));

    if ($carriers) {
        $line .= ' · ' . implode(', ', $carriers);
    }

    return pg_commerce_fold_state($store_on, $line, pg_commerce_fold_pill(true, true));
}

/**
 * Address autocomplete's line: the provider that suggests, or Off.
 *
 * @param bool   $store_on
 * @param string $provider the provider's name, '' while autocomplete is off
 * @return array line, pill, attention
 */
function pg_commerce_fold_address($store_on, $provider)
{
    $provider = trim((string) $provider);

    return pg_commerce_fold_state($store_on, ($provider !== '') ? $provider : lang('Off'), pg_commerce_fold_pill(true, $provider !== ''));
}

/**
 * Stock locations' line: how many locations, how many of them off.
 *
 * @param bool $store_on
 * @param bool $ready the locations' tables are there
 * @param int  $total
 * @param int  $off   the locations turned off
 * @return array line, pill, attention
 */
function pg_commerce_fold_stock($store_on, $ready, $total, $off)
{
    $total = (int) $total;
    $off = (int) $off;

    if (!$ready || ($total <= 0)) {
        return pg_commerce_fold_state($store_on, lang('Not in use'), null);
    }

    if ($off > 0) {
        $line = lang(array('string' => '{var:1} locations, {var:2} off', 'vars' => array($total, $off)));
    } elseif ($total === 1) {
        $line = lang('1 location');
    } else {
        $line = lang(array('string' => '{var:1} locations', 'vars' => array($total)));
    }

    return pg_commerce_fold_state($store_on, $line, null);
}

/**
 * Gift cards' line: accepted and for how long, or Off.
 *
 * @param bool       $store_on
 * @param bool       $on   Accept gift cards
 * @param int|string $days the validity, blank or 0 for no expiry
 * @return array line, pill, attention
 */
function pg_commerce_fold_giftcards($store_on, $on, $days)
{
    if (!$on) {
        return pg_commerce_fold_state($store_on, lang('Off'), pg_commerce_fold_pill(true, false));
    }

    $days = (int) $days;
    $line = ($days > 0) ? lang(array('string' => 'Accepted, valid {var:1} days', 'vars' => array($days))) : lang('Accepted, never expire');

    return pg_commerce_fold_state($store_on, $line, pg_commerce_fold_pill(true, true));
}

/**
 * Rewards' line: the goal in points, or Off.
 *
 * @param bool       $store_on
 * @param bool       $on   the reward programme's switch
 * @param int|string $goal points to the goal
 * @return array line, pill, attention
 */
function pg_commerce_fold_rewards($store_on, $on, $goal)
{
    if (!$on) {
        return pg_commerce_fold_state($store_on, lang('Off'), pg_commerce_fold_pill(true, false));
    }

    return pg_commerce_fold_state($store_on, lang(array('string' => 'On: {var:1} points to the goal', 'vars' => array((int) $goal))), pg_commerce_fold_pill(true, true));
}

/**
 * Reference price's line: the rule chosen, in its own words.
 *
 * @param bool   $store_on
 * @param string $rule pg_price_reference_rule() value
 * @return array line, pill, attention
 */
function pg_commerce_fold_reference($store_on, $rule)
{
    switch ((string) $rule) {
        case 'lowest_10d':
            $line = lang('Lowest price of the last 10 days');
            break;
        case 'manual':
            $line = lang('Compare-at price');
            break;
        default:
            $line = lang('Lowest price of the last 30 days');
            break;
    }

    return pg_commerce_fold_state($store_on, $line, null);
}

/**
 * Affiliate programme's line: On with its default rate, or Off.
 *
 * @param bool   $store_on
 * @param bool   $on
 * @param string $rate the default commission rate in percent
 * @return array line, pill, attention
 */
function pg_commerce_fold_affiliate($store_on, $on, $rate)
{
    if (!$on) {
        return pg_commerce_fold_state($store_on, lang('Off'), pg_commerce_fold_pill(true, false));
    }

    $rate = trim((string) $rate);
    $line = (is_numeric($rate) && ((float) $rate > 0)) ? lang(array('string' => 'On, {var:1}% commission', 'vars' => array($rate))) : lang('On');

    return pg_commerce_fold_state($store_on, $line, pg_commerce_fold_pill(true, true));
}

/**
 * Referral programme's line: On with the customer's reward, or Off.
 *
 * @param bool   $store_on
 * @param bool   $on
 * @param string $reward   the store credit for each friend, as typed
 * @param string $currency the base currency's code
 * @return array line, pill, attention
 */
function pg_commerce_fold_referral($store_on, $on, $reward, $currency)
{
    if (!$on) {
        return pg_commerce_fold_state($store_on, lang('Off'), pg_commerce_fold_pill(true, false));
    }

    $reward = trim((string) $reward);
    $line = (is_numeric($reward) && ((float) $reward > 0))
        ? lang(array('string' => 'On, {var:1} {var:2} for each friend', 'vars' => array($reward, (string) $currency)))
        : lang('On');

    return pg_commerce_fold_state($store_on, $line, pg_commerce_fold_pill(true, true));
}

/**
 * Product feeds' line: the last build and its products, or why there is
 * none.
 *
 * @param bool $store_on
 * @param bool $ready    the feeds' column is there
 * @param int  $on       the feeds switched on
 * @param int  $built_at the last build of a feed switched on, 0 for none
 * @param int  $count    the products of that build
 * @param int    $now
 * @param string $time_code the site's clock as a date() code (pg_time_format_code())
 * @param string $date_code the site's day and month (get_date_format_code())
 * @return array line, pill, attention
 */
function pg_commerce_fold_feeds($store_on, $ready, $on, $built_at, $count, $now, $time_code = 'H:i', $date_code = 'j/n')
{
    $built_at = (int) $built_at;

    if (!$store_on) {
        return pg_commerce_fold_state(false, lang('The store is off: no feed is built'), null);
    }

    if ($ready && ((int) $on === 0)) {
        return pg_commerce_fold_state(true, lang('No feed is switched on'), pg_commerce_fold_pill(true, false));
    }

    if (!$ready || ($built_at <= 0)) {
        return pg_commerce_fold_state(true, lang('Not built yet'), pg_commerce_fold_pill(true, true));
    }

    $line = (date('Y-m-d', $built_at) === date('Y-m-d', (int) $now))
        ? lang(array('string' => 'Built today {var:1} · {var:2} products', 'vars' => array(date($time_code, $built_at), (int) $count)))
        : lang(array('string' => 'Built {var:1} · {var:2} products', 'vars' => array(date($date_code . ' ' . $time_code, $built_at), (int) $count)));

    return pg_commerce_fold_state(true, $line, pg_commerce_fold_pill(true, true));
}

/* ---------------------------------------------------------------------------
   Inside the rows: the pane's switch, choice, ticks and group heads
   --------------------------------------------------------------------------- */

/**
 * A pg-switch with its label and at most one line of help, as every switch
 * inside an Ecommerce row is drawn. Pure.
 *
 * @param string $name       the field's name and id
 * @param bool   $on         it is on
 * @param string $label      plain text
 * @param string $help       plain text, '' for none
 * @param string $attributes more attributes of the input, already escaped
 * @return string HTML
 */
function pg_commerce_settings_switch($name, $on, $label, $help = '', $attributes = '')
{
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-5">
                            <input type="checkbox" class="pg-switch" value="1"' . ($on ? ' checked="checked"' : '') . ' id="' . h($name) . '" name="' . h($name) . '"' . (($help !== '') ? ' aria-describedby="' . h($name) . '_help"' : '') . $attributes . '/>
                            <label class="pg-text-2" for="' . h($name) . '">' . h($label) . '</label>
                        </div>' . (($help !== '') ? '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="' . h($name) . '_help">' . h($help) . '</p>' : '') . '
                    </div>';
}

/**
 * A group headed by its switch: a payment method's switch drawn as the
 * group's label, its fields following it whatever its state. Pure.
 *
 * @param string $name       the field's name and id
 * @param bool   $on         it is on
 * @param string $label      plain text
 * @param string $attributes more attributes of the input, already escaped
 * @return string HTML
 */
function pg_commerce_settings_group_switch($name, $on, $label, $attributes = '')
{
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-5">
                            <input type="checkbox" class="pg-switch" value="1"' . ($on ? ' checked="checked"' : '') . ' id="' . h($name) . '" name="' . h($name) . '"' . $attributes . '/>
                            <label class="pg-group-label mb-0" for="' . h($name) . '">' . h($label) . '</label>
                        </div>
                    </div>';
}

/**
 * A group's label. Pure.
 *
 * @param string $label plain text
 * @return string HTML
 */
function pg_commerce_settings_group($label)
{
    return '
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . h($label) . '</h3></div>';
}

/**
 * A choice of a few as a pg-seg under its legend, posting the radios'
 * values as the plain radios did. Pure.
 *
 * @param string $legend  plain text
 * @param string $name    the radios' name
 * @param array  $options each value, id, label (plain text), on
 * @return string HTML, the inside of a column
 */
function pg_commerce_settings_seg($legend, $name, $options)
{
    $items = '';

    foreach ($options as $option) {
        $items .= '
                                <label class="pg-seg-item" for="' . h($option['id']) . '"><input value="' . h($option['value']) . '" class="visually-hidden" type="radio" id="' . h($option['id']) . '" name="' . h($name) . '"' . ($option['on'] ? ' checked="checked"' : '') . '/>' . h($option['label']) . '</label>';
    }

    return '
                        <fieldset><legend class="form-label pg-field-label">' . h($legend) . '</legend>
                            <div class="pg-seg flex-wrap">' . $items . '
                            </div>
                        </fieldset>';
}

/**
 * Switches of one kind as a wrapped list of ticks under a legend, each
 * posting 1 under its own name. Pure.
 *
 * @param string $legend plain text
 * @param array  $items  each name, label (plain text), on
 * @return string HTML
 */
function pg_commerce_settings_ticks($legend, $items)
{
    $ticks = '';

    foreach ($items as $item) {
        $ticks .= '
                                <label class="d-inline-flex align-items-center gap-2 pg-text-2" for="' . h($item['name']) . '"><input type="checkbox" class="pg-checkbox" value="1" id="' . h($item['name']) . '" name="' . h($item['name']) . '"' . ($item['on'] ? ' checked="checked"' : '') . '/>' . h($item['label']) . '</label>';
    }

    return '
                    <div class="col-12">
                        <fieldset><legend class="form-label pg-field-label">' . h($legend) . '</legend>
                            <div class="d-flex flex-wrap gap-5">' . $ticks . '
                            </div>
                        </fieldset>
                    </div>';
}

/**
 * A quiet button that opens a box of fields under it, closed at first: the
 * fields of something new, drawn only when asked for. The box posts its
 * fields whether open or not. Pure.
 *
 * @param string $id    the box's id without _box
 * @param string $label plain text
 * @param string $icon  a Bootstrap icon's name without bi-
 * @param string $html  the box's fields, already escaped
 * @return string HTML
 */
function pg_commerce_settings_reveal($id, $label, $icon, $html)
{
    $box = $id . '_box';

    return '
                    <div class="col-12">
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm" aria-expanded="false" aria-controls="' . h($box) . '" data-pg-commerce-reveal="' . h($box) . '"><i class="bi bi-' . h($icon) . '" aria-hidden="true"></i>' . h($label) . '</button>
                    </div>
                    <div class="col-12" id="' . h($box) . '" hidden>
                        <div class="row gy-5">' . $html . '
                        </div>
                    </div>';
}

/**
 * The pane's script for pg_commerce_settings_reveal(): a click opens or
 * closes the box the button names. Delegated to the document, so the pane's
 * markup may be replaced after a save. Pure.
 *
 * @return string
 */
function pg_commerce_settings_reveal_script()
{
    return '
    <script>
        (function () {
            if (window.pgCommerceRevealBound) {
                return;
            }

            window.pgCommerceRevealBound = true;

            document.addEventListener("click", function (event) {
                var button = event.target.closest ? event.target.closest("[data-pg-commerce-reveal]") : null;
                var box = button ? document.getElementById(button.getAttribute("data-pg-commerce-reveal")) : null;

                if (!box) {
                    return;
                }

                box.hidden = !box.hidden;
                button.setAttribute("aria-expanded", box.hidden ? "false" : "true");

                if (!box.hidden) {
                    var first = box.querySelector("input, select, textarea");

                    if (first) {
                        first.focus();
                    }
                }
            });
        })();
    </script>';
}
