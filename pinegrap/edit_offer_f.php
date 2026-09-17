<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Offer editor support: one screen owns the offer together with its rule row
// (offer_rules) and its action rows (offer_actions). The screen never asks the
// operator to name or reuse those rows; they are written here as part of the
// offer. Loaded by edit_offer.php (screen), view_offers.php (summary helpers)
// and api.php (action "offer_editor", JSON requests from offer_editor.js).
//
// The storefront side (apply_offers_to_cart(), validate_offer(), the pending
// offer screens) keeps reading the same tables in the same shape, so nothing
// in this file is needed at checkout time.

// An offer with no explicit end runs until this date. The list and the editor
// show such an offer as open-ended instead of printing the year 2099.
if (!defined('PG_OFFER_OPEN_END_DATE')) {
    define('PG_OFFER_OPEN_END_DATE', '2099-12-31');
}

// ── Type registries ──────────────────────────────────────────────────────
//
// The "add condition" and "add result" menus in the editor are built from
// these two lists. offer_editor.js carries the same keys; a type that is
// missing on either side cannot be added or saved, so the two lists are kept
// in the same order on purpose.

function _pg_offer_condition_types()
{
    $types = array(
        'subtotal' => array(
            'label' => lang('Cart subtotal is at least'),
            'icon'  => 'bi-funnel'),
        'products' => array(
            'label' => lang('Cart contains one of these products'),
            'icon'  => 'bi-box-seam'));
    // A condition about the customer needs the table the upgrade creates; a
    // site that has not run it yet is not offered a row it cannot save.
    if (_pg_offer_conditions_table()) {
        $types['product group'] = array(
            'label' => lang('Cart contains a product from these groups'),
            'icon'  => 'bi-collection');
        $types['cart quantity'] = array(
            'label' => lang('Cart holds at least this many items'),
            'icon'  => 'bi-123');
        $types['new customer'] = array(
            'label' => lang('The customer is new'),
            'icon'  => 'bi-person-plus');
        $types['usage limit'] = array(
            'label' => lang('Limit how often the offer is used'),
            'icon'  => 'bi-hourglass-split');
    }
    return $types;
}

// offer_actions.discount_product_group_id arrived with 2026.4.4 too. A site
// that is behind keeps every existing action working; only the group target is
// out of reach, and the editor hides it rather than losing it on save.
function _pg_offer_group_discount_column()
{
    static $exists = null;
    if ($exists === null) {
        $exists = (bool) db_item("SHOW COLUMNS FROM offer_actions LIKE 'discount_product_group_id'");
    }
    return $exists;
}

// "<cents>:<percent>,<cents>:<percent>" - the shape pg_offer_tiers() reads at
// checkout. Kept sorted from the cheapest rung up, which is how the editor
// lists them.
function _pg_offer_tiers_parse($text)
{
    $tiers = array();
    foreach (explode(',', (string) $text) as $pair) {
        $parts = explode(':', $pair);
        if (count($parts) != 2) {
            continue;
        }
        $min = (int) $parts[0];
        $percent = (int) $parts[1];
        if (($min < 0) || ($percent <= 0) || ($percent > 100)) {
            continue;
        }
        $tiers[] = array('min' => $min, 'percent' => $percent);
    }
    usort($tiers, function ($a, $b) {
        return ($a['min'] == $b['min']) ? 0 : (($a['min'] < $b['min']) ? -1 : 1);
    });
    return $tiers;
}

function _pg_offer_tiers_text($tiers)
{
    $parts = array();
    foreach ($tiers as $tier) {
        $parts[] = ((int) $tier['min']) . ':' . ((int) $tier['percent']);
    }
    return implode(',', $parts);
}

// The ladder of the offer's order discount, or an empty list.
function _pg_offer_action_tiers($actions)
{
    foreach ($actions as $action) {
        if (($action['type'] == 'discount order') && !empty($action['tiers'])) {
            return $action['tiers'];
        }
    }
    return array();
}

// The third target for a product discount arrived with the same upgrade.
function _pg_offer_target_column()
{
    static $exists = null;
    if ($exists === null) {
        $exists = (bool) db_item("SHOW COLUMNS FROM offer_actions LIKE 'discount_product_target'");
    }
    return $exists;
}

// offer_conditions arrived with 2026.4.4; everything that touches it asks here
// first so an installation that is behind keeps working.
function _pg_offer_conditions_table()
{
    static $exists = null;
    if ($exists === null) {
        $exists = (bool) db_item("SHOW TABLES LIKE 'offer_conditions'");
    }
    return $exists;
}

// What "new" is allowed to mean. The keys are stored in
// offer_conditions.text_value.
function _pg_offer_new_customer_modes()
{
    return array(
        'no_orders'  => lang('has never ordered'),
        'registered' => lang('registered within the last {var:1} day(s)'),
        'both'       => lang('has never ordered and registered within the last {var:1} day(s)'));
}

// Keys are the offer_actions.type enum values, so the wire format and the
// database use the same word for a type.
function _pg_offer_action_types()
{
    return array(
        'discount order' => array(
            'label' => lang('Discount on the order'),
            'icon'  => 'bi-cart3',
            'units' => array('percent', 'amount')),
        'discount product' => array(
            'label' => lang('Discount on a product'),
            'icon'  => 'bi-percent',
            'units' => array('percent', 'amount')),
        'add product' => array(
            'label' => lang('Add a gift product'),
            'icon'  => 'bi-gift',
            'units' => array('percent', 'amount')),
        'discount shipping' => array(
            'label' => lang('Discount on shipping'),
            'icon'  => 'bi-truck',
            'units' => array('percent')));
}

function _pg_offer_action_icon($type)
{
    $types = _pg_offer_action_types();
    return isset($types[$type]) ? $types[$type]['icon'] : 'bi-question';
}

// ── Reference data for the pickers ───────────────────────────────────────

// products.name is the code the operator types (SKU); the storefront prints
// short_description. The picker shows both, the summaries use the printed one.
function _pg_offer_products()
{
    $rows = db_items(
        "SELECT id, name, short_description, enabled, price
        FROM products
        ORDER BY short_description, name");
    $products = array();
    if ($rows) {
        foreach ($rows as $row) {
            $products[] = array(
                'id'      => (int) $row['id'],
                'code'    => (string) $row['name'],
                'label'   => (trim((string) $row['short_description']) != '') ? (string) $row['short_description'] : (string) $row['name'],
                'enabled' => (int) $row['enabled'],
                'price'   => (int) $row['price']);
        }
    }
    return $products;
}

// Product groups are the catalogue's own categories, and they nest. The label
// carries the path ("Mobilya › Sandalyeler") so two groups with the same name
// under different parents can be told apart in the picker.
function _pg_offer_product_groups()
{
    // The list screen asks for a label once per group per offer, so the tree is
    // built once per request rather than on every call.
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $rows = db_items("SELECT id, name, parent_id FROM product_groups ORDER BY sort_order, name");
    if (!$rows) {
        $cache = array();
        return $cache;
    }
    $names = array();
    $parents = array();
    foreach ($rows as $row) {
        $names[(int) $row['id']] = (string) $row['name'];
        $parents[(int) $row['id']] = (int) $row['parent_id'];
    }
    $groups = array();
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $path = array($names[$id]);
        $parent = $parents[$id];
        // A group that points at itself or at a missing parent would loop; the
        // guard counts the hops instead of trusting the data.
        $hops = 0;
        while (($parent > 0) && isset($names[$parent]) && ($hops < 10)) {
            array_unshift($path, $names[$parent]);
            $parent = $parents[$parent];
            $hops++;
        }
        $groups[] = array(
            'id'    => $id,
            'name'  => $names[$id],
            'label' => implode(' › ', $path));
    }
    usort($groups, function ($a, $b) {
        return strcasecmp($a['label'], $b['label']);
    });
    $cache = $groups;
    return $cache;
}

// $full picks the path ("Mağaza › Sandalyeler"), which is what the picker
// shows; the chips and the summary sentence use the short name instead, or a
// condition naming five groups fills the row.
function _pg_offer_group_label($group_id, $full = false)
{
    foreach (_pg_offer_product_groups() as $group) {
        if ($group['id'] == $group_id) {
            return $full ? $group['label'] : $group['name'];
        }
    }
    return '';
}

function _pg_offer_shipping_methods()
{
    $rows = db_items("SELECT id, name, code FROM shipping_methods ORDER BY name");
    $methods = array();
    if ($rows) {
        foreach ($rows as $row) {
            $methods[] = array(
                'id'   => (int) $row['id'],
                'name' => (string) $row['name'],
                'code' => (string) $row['code']);
        }
    }
    return $methods;
}

function _pg_offer_product_label($product_id, $products = null)
{
    if ($products === null) {
        $products = _pg_offer_products();
    }
    foreach ($products as $product) {
        if ($product['id'] == $product_id) {
            return $product['label'];
        }
    }
    return '';
}

// ── Load ─────────────────────────────────────────────────────────────────

// Returns the editor state for one offer, or false when it does not exist.
// The state is what offer_editor.js works on and what offer_save receives
// back; amounts are in cents, dates in Y-m-d.
function _pg_offer_load($offer_id)
{
    $offer = db_item("SELECT * FROM offers WHERE id = '" . e($offer_id) . "'");
    if (!$offer) {
        return false;
    }

    $conditions = array();
    if ($offer['offer_rule_id'] > 0) {
        $rule = db_item("SELECT * FROM offer_rules WHERE id = '" . e($offer['offer_rule_id']) . "'");
        if ($rule) {
            if ($rule['required_subtotal'] > 0) {
                $conditions[] = array(
                    'type'   => 'subtotal',
                    'amount' => (int) $rule['required_subtotal']);
            }
            $product_ids = db_values(
                "SELECT product_id FROM offer_rules_products_xref
                WHERE offer_rule_id = '" . e($rule['id']) . "'");
            if ($product_ids) {
                $conditions[] = array(
                    'type'        => 'products',
                    'product_ids' => array_map('intval', $product_ids),
                    'quantity'    => (int) $rule['required_quantity']);
            }
        }
    }

    if (_pg_offer_conditions_table()) {
        $rows = db_items(
            "SELECT type, int_value, text_value
            FROM offer_conditions
            WHERE offer_id = '" . e($offer_id) . "'
            ORDER BY id");
        if ($rows) {
            foreach ($rows as $row) {
                if ($row['type'] == 'product group') {
                    $group_ids = array_values(array_filter(array_map('intval', explode(',', (string) $row['text_value']))));
                    $conditions[] = array(
                        'type'      => 'product group',
                        'group_ids' => $group_ids,
                        'quantity'  => ((int) $row['int_value'] > 0) ? (int) $row['int_value'] : 1);
                } elseif ($row['type'] == 'cart quantity') {
                    $conditions[] = array(
                        'type'     => 'cart quantity',
                        'quantity' => ((int) $row['int_value'] > 0) ? (int) $row['int_value'] : 1);
                } elseif ($row['type'] == 'usage limit') {
                    $conditions[] = array(
                        'type'           => 'usage limit',
                        'total_limit'    => max(0, (int) $row['int_value']),
                        'customer_limit' => max(0, (int) $row['text_value']));
                } elseif ($row['type'] == 'new customer') {
                    $conditions[] = array(
                        'type' => 'new customer',
                        'mode' => ($row['text_value'] != '') ? $row['text_value'] : 'no_orders',
                        'days' => ((int) $row['int_value'] > 0) ? (int) $row['int_value'] : 30);
                }
            }
        }
    }

    // The ladder belongs to the offer but is edited on the order-discount
    // row, so it is handed to the first one.
    $tiers = array();
    if (_pg_offer_conditions_table()) {
        $tier_row = db_item("SELECT text_value FROM offer_conditions WHERE offer_id = '" . e($offer_id) . "' AND type = 'tiers' LIMIT 1");
        if ($tier_row) {
            $tiers = _pg_offer_tiers_parse($tier_row['text_value']);
        }
    }

    $actions = array();
    $rows = db_items(
        "SELECT offer_actions.*
        FROM offers_offer_actions_xref
        INNER JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id
        WHERE offers_offer_actions_xref.offer_id = '" . e($offer_id) . "'
        ORDER BY offer_actions.id");
    if ($rows) {
        $tiers_given = false;
        foreach ($rows as $row) {
            $action = _pg_offer_action_from_row($row);
            if (($action['type'] == 'discount order') && $tiers && !$tiers_given) {
                $action['tiers'] = $tiers;
                $tiers_given = true;
            }
            $actions[] = $action;
        }
    }

    $open_ended = ($offer['end_date'] == PG_OFFER_OPEN_END_DATE) && ($offer['start_date'] <= date('Y-m-d'));

    return array(
        'offer' => array(
            'id'                    => (int) $offer['id'],
            'code'                  => (string) $offer['code'],
            'description'           => (string) $offer['description'],
            'enabled'               => ($offer['status'] == 'enabled') ? 1 : 0,
            'require_code'          => (int) $offer['require_code'],
            'period'                => $open_ended ? 'open' : 'range',
            'start_date'            => (string) $offer['start_date'],
            'end_date'              => (string) $offer['end_date'],
            'only_apply_best_offer' => (int) $offer['only_apply_best_offer'],
            'scope'                 => ($offer['scope'] == 'recipient') ? 'recipient' : 'order',
            'multiple_recipients'   => (int) $offer['multiple_recipients'],
            'upsell' => array(
                'enabled'          => (int) $offer['upsell'],
                'message'          => (string) $offer['upsell_message'],
                'trigger_subtotal' => (int) $offer['upsell_trigger_subtotal'],
                'trigger_quantity' => (int) $offer['upsell_trigger_quantity'],
                'button_label'     => (string) $offer['upsell_action_button_label'],
                'page_id'          => (int) $offer['upsell_action_page_id'])),
        'conditions' => $conditions,
        'actions'    => $actions,
        'meta' => array(
            'user'      => (int) $offer['user'],
            'timestamp' => (int) $offer['timestamp']));
}

// One offer_actions row as the editor sees it: a value with a unit instead of
// a pair of amount/percentage columns. The storefront reads the amount column
// first (functions.php, apply_offers_to_cart), so a row with both filled shows
// its amount here and loses the percentage on the next save.
function _pg_offer_action_from_row($row)
{
    $action = array(
        'id'                  => (int) $row['id'],
        'type'                => (string) $row['type'],
        'value'               => 0,
        'unit'                => 'percent',
        'target'              => 'product',
        'product_id'          => 0,
        'group_id'            => 0,
        'quantity'            => 0,
        'tiers'               => array(),
        'shipping_method_ids' => array());
    switch ($row['type']) {
        case 'discount order':
            if ($row['discount_order_amount'] != 0) {
                $action['value'] = (int) $row['discount_order_amount'];
                $action['unit'] = 'amount';
            } else {
                $action['value'] = (int) $row['discount_order_percentage'];
            }
            break;
        case 'discount product':
            $action['product_id'] = (int) $row['discount_product_product_id'];
            // The column only exists after the 2026.4.4 upgrade; a row read
            // from an older schema has no group and stays on the product.
            $action['group_id'] = isset($row['discount_product_group_id']) ? (int) $row['discount_product_group_id'] : 0;
            $stored_target = isset($row['discount_product_target']) ? (string) $row['discount_product_target'] : '';
            if ($stored_target == 'cheapest') {
                $action['target'] = 'cheapest';
            } elseif (($stored_target == 'group') || ($action['group_id'] > 0)) {
                $action['target'] = 'group';
            }
            if ($row['discount_product_amount'] != 0) {
                $action['value'] = (int) $row['discount_product_amount'];
                $action['unit'] = 'amount';
            } else {
                $action['value'] = (int) $row['discount_product_percentage'];
            }
            break;
        case 'add product':
            $action['product_id'] = (int) $row['add_product_product_id'];
            $action['quantity'] = (int) $row['add_product_quantity'];
            if ($row['add_product_discount_amount'] != 0) {
                $action['value'] = (int) $row['add_product_discount_amount'];
                $action['unit'] = 'amount';
            } else {
                $action['value'] = (int) $row['add_product_discount_percentage'];
            }
            break;
        case 'discount shipping':
            $action['value'] = (int) $row['discount_shipping_percentage'];
            $ids = db_values(
                "SELECT shipping_method_id FROM offer_actions_shipping_methods_xref
                WHERE offer_action_id = '" . e($row['id']) . "'");
            $action['shipping_method_ids'] = $ids ? array_map('intval', $ids) : array();
            break;
    }
    return $action;
}

// A state for an offer that does not exist yet. With $template_of set the
// rows of that offer are copied in, ids dropped, so "duplicate" opens the
// editor unsaved: nothing is written until the operator presses Save.
function _pg_offer_new_state($template_of = 0)
{
    $state = array(
        'offer' => array(
            'id'                    => 0,
            'code'                  => '',
            'description'           => '',
            'enabled'               => 1,
            'require_code'          => 0,
            'period'                => 'open',
            'start_date'            => date('Y-m-d'),
            'end_date'              => PG_OFFER_OPEN_END_DATE,
            'only_apply_best_offer' => 1,
            'scope'                 => 'order',
            'multiple_recipients'   => 0,
            'upsell' => array(
                'enabled'          => 0,
                'message'          => '',
                'trigger_subtotal' => 0,
                'trigger_quantity' => 0,
                'button_label'     => '',
                'page_id'          => 0)),
        'conditions' => array(),
        'actions'    => array(),
        'meta'       => array('user' => 0, 'timestamp' => 0));
    if ($template_of > 0) {
        $source = _pg_offer_load($template_of);
        if ($source) {
            $state['offer'] = $source['offer'];
            $state['offer']['id'] = 0;
            $state['offer']['code'] = '';
            $state['conditions'] = $source['conditions'];
            $state['actions'] = $source['actions'];
            foreach ($state['actions'] as $key => $action) {
                $state['actions'][$key]['id'] = 0;
            }
            $state['meta']['duplicate_of'] = (int) $template_of;
        }
    }
    return $state;
}

// Everything the screen embeds for offer_editor.js: the state plus the
// reference lists and the few facts the editor shows in context (does another
// offer share this code, how many key codes hang off it, and so on).
function _pg_offer_editor_context($state)
{
    $offer = $state['offer'];
    $same_code_count = 0;
    $key_code_count = 0;
    $order_count = 0;
    if ($offer['code'] != '') {
        $same_code_count = (int) db_value(
            "SELECT COUNT(*) FROM offers
            WHERE (code = '" . e($offer['code']) . "') AND (id != '" . e($offer['id']) . "')");
        $key_code_count = (int) db_value(
            "SELECT COUNT(*) FROM key_codes WHERE offer_code = '" . e($offer['code']) . "'");
        $order_count = (int) db_value(
            "SELECT COUNT(*) FROM orders
            WHERE (special_offer_code = '" . e($offer['code']) . "') AND (status IN ('complete', 'exported'))");
    }
    $modified_by = '';
    if (!empty($state['meta']['user'])) {
        $modified_by = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . e($state['meta']['user']) . "'");
    }
    return array(
        'multi_recipient'  => ((defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING == true) && (defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE == 'multi-recipient')),
        'group_discount'   => _pg_offer_group_discount_column(),
        'offer_conditions' => _pg_offer_conditions_table(),
        'cheapest_target'  => _pg_offer_target_column(),
        'same_code_count'  => $same_code_count,
        'key_code_count'   => $key_code_count,
        'order_count'      => $order_count,
        'modified_by'      => $modified_by,
        'modified_ago'     => (!empty($state['meta']['timestamp'])) ? get_relative_time(array('timestamp' => $state['meta']['timestamp'], 'format' => 'plain_text')) : '',
        'currency'         => _pg_offer_currency(),
        'date_format'      => (DATE_FORMAT == 'month_day') ? 'month_day' : 'day_month',
        'today'            => date('Y-m-d'),
        'open_end_date'    => PG_OFFER_OPEN_END_DATE,
        'key_codes_url'    => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php',
        'list_url'         => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_offers.php',
        'edit_url'         => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_offer.php',
        'api_url'          => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php');
}

// ── Validation ───────────────────────────────────────────────────────────

// Reads a money value sent by the editor. The editor sends cents as integers;
// a decimal string with a comma or a dot is accepted too so a hand-made
// request behaves the same way.
function _pg_offer_cents($value)
{
    if (is_int($value)) {
        return $value;
    }
    $value = str_replace(' ', '', (string) $value);
    if ($value === '') {
        return 0;
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return (int) $value;
    }
    $value = str_replace(',', '.', $value);
    return (int) round(((float) $value) * 100);
}

// Validates a save request and normalises it. Returns array(errors, clean).
// Nothing is written while this runs; the caller writes only when errors is
// empty. Error keys name the row the message belongs to
// ("actions.1.value"), which is how the editor puts the message under the
// right row.
function _pg_offer_validate($payload)
{
    $errors = array();
    $offer_in = (isset($payload['offer']) && is_array($payload['offer'])) ? $payload['offer'] : array();
    $conditions_in = (isset($payload['conditions']) && is_array($payload['conditions'])) ? $payload['conditions'] : array();
    $actions_in = (isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array();

    $offer = array();
    $offer['id'] = isset($offer_in['id']) ? (int) $offer_in['id'] : 0;
    $offer['code'] = isset($offer_in['code']) ? trim((string) $offer_in['code']) : '';
    $offer['description'] = isset($offer_in['description']) ? trim((string) $offer_in['description']) : '';
    $offer['enabled'] = !empty($offer_in['enabled']) ? 1 : 0;
    $offer['require_code'] = !empty($offer_in['require_code']) ? 1 : 0;
    $offer['only_apply_best_offer'] = !empty($offer_in['only_apply_best_offer']) ? 1 : 0;
    $offer['scope'] = (isset($offer_in['scope']) && $offer_in['scope'] == 'recipient') ? 'recipient' : 'order';
    $offer['multiple_recipients'] = !empty($offer_in['multiple_recipients']) ? 1 : 0;

    if ($offer['code'] == '') {
        $errors['offer.code'] = lang('Please enter an offer code.');
    } elseif (mb_strlen($offer['code']) > 50) {
        $errors['offer.code'] = lang('The offer code can be at most 50 characters long.');
    }
    if (mb_strlen($offer['description']) > 255) {
        $errors['offer.description'] = lang('The message can be at most 255 characters long.');
    }

    // Dates come as Y-m-d. An open-ended offer keeps its start (or starts
    // today) and ends on the open end date; a ranged one needs both dates.
    $today = date('Y-m-d');
    $period = (isset($offer_in['period']) && $offer_in['period'] == 'range') ? 'range' : 'open';
    $start = isset($offer_in['start_date']) ? trim((string) $offer_in['start_date']) : '';
    $end = isset($offer_in['end_date']) ? trim((string) $offer_in['end_date']) : '';
    if ($period == 'open') {
        if (!_pg_offer_valid_date($start) || ($start > $today)) {
            $start = $today;
        }
        $end = PG_OFFER_OPEN_END_DATE;
    } else {
        if (!_pg_offer_valid_date($start) || !_pg_offer_valid_date($end)) {
            $errors['offer.dates'] = lang('Please enter a start date and an end date.');
        } elseif ($start > $end) {
            $errors['offer.dates'] = lang('The end date must not be before the start date.');
        }
    }
    $offer['start_date'] = $start;
    $offer['end_date'] = $end;

    $upsell_in = (isset($offer_in['upsell']) && is_array($offer_in['upsell'])) ? $offer_in['upsell'] : array();
    $offer['upsell'] = array(
        'enabled'          => !empty($upsell_in['enabled']) ? 1 : 0,
        'message'          => isset($upsell_in['message']) ? trim((string) $upsell_in['message']) : '',
        'trigger_subtotal' => isset($upsell_in['trigger_subtotal']) ? max(0, _pg_offer_cents($upsell_in['trigger_subtotal'])) : 0,
        'trigger_quantity' => isset($upsell_in['trigger_quantity']) ? max(0, (int) $upsell_in['trigger_quantity']) : 0,
        'button_label'     => isset($upsell_in['button_label']) ? trim((string) $upsell_in['button_label']) : '',
        'page_id'          => isset($upsell_in['page_id']) ? (int) $upsell_in['page_id'] : 0);
    if (mb_strlen($offer['upsell']['message']) > 255) {
        $errors['offer.upsell.message'] = lang('The up-sell message can be at most 255 characters long.');
    }
    if (mb_strlen($offer['upsell']['button_label']) > 50) {
        $errors['offer.upsell.button_label'] = lang('The button label can be at most 50 characters long.');
    }

    // Conditions: the current schema folds every condition into one
    // offer_rules row, so each type may appear once.
    $condition_types = _pg_offer_condition_types();
    $conditions = array();
    $seen = array();
    foreach ($conditions_in as $index => $condition_in) {
        $key = 'conditions.' . (int) $index;
        $type = (is_array($condition_in) && isset($condition_in['type'])) ? (string) $condition_in['type'] : '';
        if (!isset($condition_types[$type])) {
            $errors[$key] = lang('This condition type is not available.');
            continue;
        }
        if (isset($seen[$type])) {
            $errors[$key] = lang('This condition is already in the offer.');
            continue;
        }
        $seen[$type] = true;
        $condition = array('type' => $type);
        if ($type == 'subtotal') {
            $condition['amount'] = isset($condition_in['amount']) ? _pg_offer_cents($condition_in['amount']) : 0;
            if ($condition['amount'] <= 0) {
                $errors[$key . '.amount'] = lang('Please enter a subtotal.');
            }
        } elseif ($type == 'products') {
            $ids = (isset($condition_in['product_ids']) && is_array($condition_in['product_ids'])) ? array_values(array_unique(array_filter(array_map('intval', $condition_in['product_ids'])))) : array();
            $condition['product_ids'] = $ids;
            $condition['quantity'] = isset($condition_in['quantity']) ? (int) $condition_in['quantity'] : 0;
            if (!$ids) {
                $errors[$key . '.product_ids'] = lang('Please select at least one product.');
            } elseif ($condition['quantity'] < 1) {
                $errors[$key . '.quantity'] = lang('Please enter a quantity of at least 1.');
            }
        } elseif ($type == 'product group') {
            $ids = (isset($condition_in['group_ids']) && is_array($condition_in['group_ids'])) ? array_values(array_unique(array_filter(array_map('intval', $condition_in['group_ids'])))) : array();
            $condition['group_ids'] = $ids;
            $condition['quantity'] = isset($condition_in['quantity']) ? (int) $condition_in['quantity'] : 0;
            if (!$ids) {
                $errors[$key . '.group_ids'] = lang('Please select at least one product group.');
            } elseif ($condition['quantity'] < 1) {
                $errors[$key . '.quantity'] = lang('Please enter a quantity of at least 1.');
            }
        } elseif ($type == 'cart quantity') {
            $condition['quantity'] = isset($condition_in['quantity']) ? (int) $condition_in['quantity'] : 0;
            if ($condition['quantity'] < 1) {
                $errors[$key . '.quantity'] = lang('Please enter a quantity of at least 1.');
            }
        } elseif ($type == 'usage limit') {
            $condition['total_limit'] = isset($condition_in['total_limit']) ? max(0, (int) $condition_in['total_limit']) : 0;
            $condition['customer_limit'] = isset($condition_in['customer_limit']) ? max(0, (int) $condition_in['customer_limit']) : 0;
            // Both empty is a condition that limits nothing, which reads as a
            // mistake rather than as "no limit".
            if (($condition['total_limit'] < 1) && ($condition['customer_limit'] < 1)) {
                $errors[$key . '.total_limit'] = lang('Please enter at least one limit.');
            }
        } elseif ($type == 'new customer') {
            $modes = _pg_offer_new_customer_modes();
            $mode = isset($condition_in['mode']) ? (string) $condition_in['mode'] : 'no_orders';
            if (!isset($modes[$mode])) {
                $mode = 'no_orders';
            }
            $condition['mode'] = $mode;
            $condition['days'] = isset($condition_in['days']) ? (int) $condition_in['days'] : 0;
            if ($mode != 'no_orders') {
                if ($condition['days'] < 1) {
                    $errors[$key . '.days'] = lang('Please enter a number of days of at least 1.');
                } elseif ($condition['days'] > 3650) {
                    $errors[$key . '.days'] = lang('Please enter at most 3650 days.');
                }
            } else if ($condition['days'] < 1) {
                $condition['days'] = 30;
            }
        }
        $conditions[] = $condition;
    }
    // Up-sell only means something next to a cart condition: it counts the
    // customer down to a subtotal or a quantity they can still reach. A
    // condition about the customer gives it nothing to count.
    if (!_pg_offer_cart_conditions($conditions)) {
        $offer['upsell']['enabled'] = 0;
    }

    $action_types = _pg_offer_action_types();
    $actions = array();
    foreach ($actions_in as $index => $action_in) {
        $key = 'actions.' . (int) $index;
        $type = (is_array($action_in) && isset($action_in['type'])) ? (string) $action_in['type'] : '';
        if (!isset($action_types[$type])) {
            $errors[$key] = lang('This result type is not available.');
            continue;
        }
        $unit = (isset($action_in['unit']) && $action_in['unit'] == 'amount') ? 'amount' : 'percent';
        if (!in_array($unit, $action_types[$type]['units'])) {
            $unit = $action_types[$type]['units'][0];
        }
        $action = array(
            'id'                  => isset($action_in['id']) ? (int) $action_in['id'] : 0,
            'type'                => $type,
            'unit'                => $unit,
            'value'               => 0,
            'target'              => (isset($action_in['target']) && in_array($action_in['target'], array('group', 'cheapest'))) ? (string) $action_in['target'] : 'product',
            'product_id'          => isset($action_in['product_id']) ? (int) $action_in['product_id'] : 0,
            'group_id'            => isset($action_in['group_id']) ? (int) $action_in['group_id'] : 0,
            'quantity'            => isset($action_in['quantity']) ? (int) $action_in['quantity'] : 0,
            'tiers'               => array(),
            'shipping_method_ids' => array());
        // A ladder only means something on an order discount, and only where
        // the schema can hold it.
        if (($type == 'discount order') && ($unit == 'percent') && _pg_offer_conditions_table()
            && isset($action_in['tiers']) && is_array($action_in['tiers'])) {
            $tiers = array();
            foreach ($action_in['tiers'] as $tier_in) {
                if (!is_array($tier_in)) {
                    continue;
                }
                $min = isset($tier_in['min']) ? _pg_offer_cents($tier_in['min']) : 0;
                $percent = isset($tier_in['percent']) ? (int) round((float) str_replace(',', '.', (string) $tier_in['percent'])) : 0;
                if ($min < 0) {
                    $min = 0;
                }
                if (($percent <= 0) || ($percent > 100)) {
                    continue;
                }
                $tiers[] = array('min' => $min, 'percent' => $percent);
            }
            usort($tiers, function ($a, $b) {
                return ($a['min'] == $b['min']) ? 0 : (($a['min'] < $b['min']) ? -1 : 1);
            });
            // Two rungs at the same amount would make the winner depend on the
            // sort, so the later one is dropped.
            $seen_min = array();
            $unique = array();
            foreach ($tiers as $tier) {
                if (isset($seen_min[$tier['min']])) {
                    continue;
                }
                $seen_min[$tier['min']] = true;
                $unique[] = $tier;
            }
            $action['tiers'] = $unique;
        }
        // A group target needs the table's new column; without the upgrade the
        // row cannot be stored, so the action falls back to a product.
        if (($action['target'] == 'group') && (!_pg_offer_group_discount_column())) {
            $action['target'] = 'product';
        }
        if (($action['target'] == 'cheapest') && (!_pg_offer_target_column())) {
            $action['target'] = 'product';
        }
        if ($action['target'] == 'product') {
            $action['group_id'] = 0;
        } elseif ($action['target'] == 'group') {
            $action['product_id'] = 0;
        } else {
            // The cheapest line is found in the cart, so neither column is
            // filled in.
            $action['product_id'] = 0;
            $action['group_id'] = 0;
        }
        if ($unit == 'amount') {
            $action['value'] = isset($action_in['value']) ? _pg_offer_cents($action_in['value']) : 0;
        } else {
            $action['value'] = isset($action_in['value']) ? (int) round((float) str_replace(',', '.', (string) $action_in['value'])) : 0;
            if ($action['value'] > 100) {
                $errors[$key . '.value'] = lang('A percentage cannot be more than 100.');
            }
        }
        if ($action['value'] < 0) {
            $errors[$key . '.value'] = lang('Please enter a value.');
        }
        // A gift with no discount is allowed (the product is added at full
        // price); every other result needs a value to do anything.
        if (($type != 'add product') && ($action['value'] <= 0) && !isset($errors[$key . '.value'])) {
            $errors[$key . '.value'] = lang('Please enter a value.');
        }
        if ($action['tiers']) {
            $action['value'] = (int) $action['tiers'][0]['percent'];
            unset($errors[$key . '.value']);
        }
        if (($type == 'discount product') && ($action['target'] == 'group')) {
            if ($action['group_id'] <= 0) {
                $errors[$key . '.group_id'] = lang('Please select a product group.');
            } elseif (!db_value("SELECT COUNT(*) FROM product_groups WHERE id = '" . e($action['group_id']) . "'")) {
                $errors[$key . '.group_id'] = lang('Please select a product group.');
            }
        } elseif (($type == 'discount product') && ($action['target'] == 'cheapest')) {
            // Nothing to pick: the cart decides which line it is.
        } elseif (($type == 'discount product') || ($type == 'add product')) {
            if ($action['product_id'] <= 0) {
                $errors[$key . '.product_id'] = lang('Please select a product.');
            } elseif (!db_value("SELECT COUNT(*) FROM products WHERE id = '" . e($action['product_id']) . "'")) {
                $errors[$key . '.product_id'] = lang('Please select a product.');
            }
        }
        if ($type == 'add product') {
            if ($action['quantity'] < 1) {
                $action['quantity'] = 1;
            }
        } else {
            $action['quantity'] = 0;
        }
        if ($type == 'discount shipping') {
            $ids = (isset($action_in['shipping_method_ids']) && is_array($action_in['shipping_method_ids'])) ? array_values(array_unique(array_filter(array_map('intval', $action_in['shipping_method_ids'])))) : array();
            $action['shipping_method_ids'] = $ids;
            if (!$ids) {
                $errors[$key . '.shipping_method_ids'] = lang('Please select at least one shipping method.');
            }
        }
        if (($type != 'discount product') && ($type != 'add product')) {
            $action['product_id'] = 0;
        }
        if ($type != 'discount product') {
            $action['target'] = 'product';
            $action['group_id'] = 0;
        }
        $actions[] = $action;
    }

    return array(
        'errors' => $errors,
        'clean'  => array(
            'offer'      => $offer,
            'conditions' => $conditions,
            'actions'    => $actions));
}

function _pg_offer_valid_date($date)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $parts)) {
        return false;
    }
    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

// ── Save ─────────────────────────────────────────────────────────────────

// How many offers use a rule or action row. A row used by another offer is
// never edited in place from here: editing it would silently change every
// other offer that uses it, which is what the old rule and action screens
// did. Such a row is copied for this offer instead.
function _pg_offer_rule_users($rule_id, $except_offer_id)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM offers
        WHERE (offer_rule_id = '" . e($rule_id) . "') AND (id != '" . e($except_offer_id) . "')");
}

function _pg_offer_action_users($action_id, $except_offer_id)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM offers_offer_actions_xref
        WHERE (offer_action_id = '" . e($action_id) . "') AND (offer_id != '" . e($except_offer_id) . "')");
}

// Writes the whole offer. Child rows go first, the offer row last, so a
// request that dies half way leaves at most an unused rule or action row
// (the cleanup action removes those) and never an offer pointing at a rule
// that does not exist. Returns the offer id.
function _pg_offer_save($clean, $user)
{
    $offer = $clean['offer'];
    $offer_id = $offer['id'];
    $is_new = ($offer_id <= 0);
    $existing = $is_new ? false : db_item("SELECT * FROM offers WHERE id = '" . e($offer_id) . "'");
    if (!$is_new && !$existing) {
        return 0;
    }
    if ($is_new) {
        $offer_id = 0;
    }
    $products = _pg_offer_products();

    // Rule row.
    $old_rule_id = $existing ? (int) $existing['offer_rule_id'] : 0;
    $rule_id = 0;
    $rule_conditions = _pg_offer_rule_conditions($clean['conditions']);
    if ($rule_conditions) {
        $rule_id = _pg_offer_rule_write($offer_id, $old_rule_id, $rule_conditions, $offer['code'], $products, $user);
    }
    if (($old_rule_id > 0) && ($old_rule_id != $rule_id) && (_pg_offer_rule_users($old_rule_id, $offer_id) == 0)) {
        _pg_offer_rule_delete($old_rule_id);
    }

    // Action rows.
    $old_action_ids = $existing ? array_map('intval', (array) db_values(
        "SELECT offer_action_id FROM offers_offer_actions_xref WHERE offer_id = '" . e($offer_id) . "'")) : array();
    $action_ids = array();
    foreach ($clean['actions'] as $action) {
        // An id that does not belong to this offer is treated as a new row;
        // the editor cannot attach somebody else's action by guessing an id.
        if (($action['id'] > 0) && !in_array($action['id'], $old_action_ids)) {
            $action['id'] = 0;
        }
        $action_ids[] = _pg_offer_action_write($offer_id, $action, $offer['code'], $products, $user);
    }
    foreach ($old_action_ids as $old_action_id) {
        if (!in_array($old_action_id, $action_ids) && (_pg_offer_action_users($old_action_id, $offer_id) == 0)) {
            _pg_offer_action_delete($old_action_id);
        }
    }

    // Offer row.
    $columns = array(
        'code'                       => $offer['code'],
        'description'                => $offer['description'],
        'require_code'               => $offer['require_code'],
        'status'                     => $offer['enabled'] ? 'enabled' : 'disabled',
        'start_date'                 => $offer['start_date'],
        'end_date'                   => $offer['end_date'],
        'offer_rule_id'              => $rule_id,
        'upsell'                     => $offer['upsell']['enabled'],
        'upsell_message'             => $offer['upsell']['message'],
        'upsell_trigger_subtotal'    => $offer['upsell']['trigger_subtotal'],
        'upsell_trigger_quantity'    => $offer['upsell']['trigger_quantity'],
        'upsell_action_button_label' => $offer['upsell']['button_label'],
        'upsell_action_page_id'      => $offer['upsell']['page_id'],
        'scope'                      => $offer['scope'],
        'multiple_recipients'        => $offer['multiple_recipients'],
        'only_apply_best_offer'      => $offer['only_apply_best_offer'],
        'user'                       => (int) $user['id']);
    $assignments = array();
    foreach ($columns as $column => $value) {
        $assignments[] = $column . " = '" . e($value) . "'";
    }
    $assignments[] = "timestamp = UNIX_TIMESTAMP()";
    if ($is_new) {
        db("INSERT INTO offers SET " . implode(', ', $assignments));
        $offer_id = (int) mysqli_insert_id(db::$con);
    } else {
        db("UPDATE offers SET " . implode(', ', $assignments) . " WHERE id = '" . e($offer_id) . "'");
        db("DELETE FROM offers_offer_actions_xref WHERE offer_id = '" . e($offer_id) . "'");
    }
    foreach ($action_ids as $action_id) {
        db("INSERT INTO offers_offer_actions_xref (offer_id, offer_action_id)
            VALUES ('" . e($offer_id) . "', '" . e($action_id) . "')");
    }

    // Conditions that do not fit the offer_rules row live in their own table,
    // keyed by the offer, so they are never shared with another offer and are
    // rewritten wholesale here.
    _pg_offer_conditions_write($offer_id, $clean['conditions'], $clean['actions']);

    if ($is_new) {
        log_activity(lang(array('string' => 'offer ({var:1}) was created', 'vars' => $offer['code'])), $_SESSION['sessionusername']);
    } else {
        log_activity(lang(array('string' => 'offer ({var:1}) was modified', 'vars' => $offer['code'])), $_SESSION['sessionusername']);
    }
    return $offer_id;
}

// The conditions the offer_rules row can hold. The other types are stored per
// offer in offer_conditions; an offer that has only those needs no rule row at
// all, and writing one would attach an empty rule that matches every cart.
function _pg_offer_rule_conditions($conditions)
{
    $kept = array();
    foreach ($conditions as $condition) {
        if (($condition['type'] == 'subtotal') || ($condition['type'] == 'products')) {
            $kept[] = $condition;
        }
    }
    return $kept;
}

// Conditions the customer can still satisfy by putting something in the cart.
// The up-sell nudge counts them down ("₺40 more and shipping is free"), so a
// condition about the customer is not one of them.
function _pg_offer_cart_conditions($conditions)
{
    $kept = array();
    foreach ($conditions as $condition) {
        if (($condition['type'] != 'new customer') && ($condition['type'] != 'usage limit')) {
            $kept[] = $condition;
        }
    }
    return $kept;
}

// Rewrites the offer_conditions rows of one offer. Called on every save, so a
// condition the operator removed disappears with it. Silent on an installation
// that has not run the 2026.4.4 upgrade yet: there the type is not offered
// either, so there is nothing to write.
function _pg_offer_conditions_write($offer_id, $conditions, $actions = array())
{
    if (!_pg_offer_conditions_table()) {
        return;
    }
    db("DELETE FROM offer_conditions WHERE offer_id = '" . e($offer_id) . "'");
    $tiers = _pg_offer_action_tiers($actions);
    if ($tiers) {
        db("INSERT INTO offer_conditions (offer_id, type, int_value, text_value)
            VALUES (
                '" . e($offer_id) . "',
                'tiers',
                '0',
                '" . e(_pg_offer_tiers_text($tiers)) . "')");
    }
    foreach ($conditions as $condition) {
        if ($condition['type'] == 'product group') {
            db("INSERT INTO offer_conditions (offer_id, type, int_value, text_value)
                VALUES (
                    '" . e($offer_id) . "',
                    'product group',
                    '" . e((int) $condition['quantity']) . "',
                    '" . e(implode(',', array_map('intval', $condition['group_ids']))) . "')");
        } elseif ($condition['type'] == 'cart quantity') {
            db("INSERT INTO offer_conditions (offer_id, type, int_value, text_value)
                VALUES (
                    '" . e($offer_id) . "',
                    'cart quantity',
                    '" . e((int) $condition['quantity']) . "',
                    '')");
        } elseif ($condition['type'] == 'usage limit') {
            db("INSERT INTO offer_conditions (offer_id, type, int_value, text_value)
                VALUES (
                    '" . e($offer_id) . "',
                    'usage limit',
                    '" . e((int) $condition['total_limit']) . "',
                    '" . e((int) $condition['customer_limit']) . "')");
        } elseif ($condition['type'] == 'new customer') {
            db("INSERT INTO offer_conditions (offer_id, type, int_value, text_value)
                VALUES (
                    '" . e($offer_id) . "',
                    'new customer',
                    '" . e((int) $condition['days']) . "',
                    '" . e($condition['mode']) . "')");
        }
    }
}

// Writes the rule row for an offer and returns its id. Reuses the offer's
// current rule when only this offer uses it, copies it when another offer
// does, and leaves a shared row untouched when nothing changed.
function _pg_offer_rule_write($offer_id, $current_rule_id, $conditions, $offer_code, $products, $user)
{
    $subtotal = 0;
    $quantity = 0;
    $product_ids = array();
    foreach ($conditions as $condition) {
        if ($condition['type'] == 'subtotal') {
            $subtotal = (int) $condition['amount'];
        } elseif ($condition['type'] == 'products') {
            $product_ids = $condition['product_ids'];
            $quantity = (int) $condition['quantity'];
        }
    }
    $name = _pg_offer_auto_name(_pg_offer_conditions_text($conditions, $products) . ' — ' . $offer_code);

    $target_id = 0;
    if ($current_rule_id > 0) {
        $current = db_item("SELECT * FROM offer_rules WHERE id = '" . e($current_rule_id) . "'");
        if ($current) {
            $current_ids = array_map('intval', (array) db_values(
                "SELECT product_id FROM offer_rules_products_xref WHERE offer_rule_id = '" . e($current_rule_id) . "'"));
            sort($current_ids);
            $new_ids = $product_ids;
            sort($new_ids);
            $unchanged = ((int) $current['required_subtotal'] == $subtotal)
                && ((int) $current['required_quantity'] == $quantity)
                && ($current_ids == $new_ids);
            if ($unchanged) {
                return $current_rule_id;
            }
            if (_pg_offer_rule_users($current_rule_id, $offer_id) == 0) {
                $target_id = $current_rule_id;
            }
        }
    }

    if ($target_id > 0) {
        db("UPDATE offer_rules SET
                name = '" . e($name) . "',
                required_subtotal = '" . e($subtotal) . "',
                required_quantity = '" . e($quantity) . "',
                user = '" . e($user['id']) . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($target_id) . "'");
        db("DELETE FROM offer_rules_products_xref WHERE offer_rule_id = '" . e($target_id) . "'");
    } else {
        db("INSERT INTO offer_rules (name, required_subtotal, required_quantity, user, timestamp)
            VALUES (
                '" . e($name) . "',
                '" . e($subtotal) . "',
                '" . e($quantity) . "',
                '" . e($user['id']) . "',
                UNIX_TIMESTAMP())");
        $target_id = (int) mysqli_insert_id(db::$con);
    }
    foreach ($product_ids as $product_id) {
        db("INSERT INTO offer_rules_products_xref (offer_rule_id, product_id)
            VALUES ('" . e($target_id) . "', '" . e($product_id) . "')");
    }
    return $target_id;
}

function _pg_offer_rule_delete($rule_id)
{
    db("DELETE FROM offer_rules WHERE id = '" . e($rule_id) . "'");
    db("DELETE FROM offer_rules_products_xref WHERE offer_rule_id = '" . e($rule_id) . "'");
}

// Writes one action row for an offer and returns its id, with the same
// reuse / copy / leave-alone rule as the rule row. Columns of the other
// types are reset so a row that changed type does not keep stale values.
function _pg_offer_action_write($offer_id, $action, $offer_code, $products, $user)
{
    $columns = array(
        'type'                           => $action['type'],
        'discount_order_amount'          => 0,
        'discount_order_percentage'      => 0,
        'discount_product_product_id'    => 0,
        'discount_product_amount'        => 0,
        'discount_product_percentage'    => 0,
        'add_product_product_id'         => 0,
        'add_product_quantity'           => 0,
        'add_product_discount_amount'    => 0,
        'add_product_discount_percentage'=> 0,
        'discount_shipping_percentage'   => 0);
    if (_pg_offer_group_discount_column()) {
        $columns['discount_product_group_id'] = 0;
    }
    if (_pg_offer_target_column()) {
        $columns['discount_product_target'] = '';
    }
    switch ($action['type']) {
        case 'discount order':
            if ($action['unit'] == 'amount') {
                $columns['discount_order_amount'] = $action['value'];
            } else {
                $columns['discount_order_percentage'] = $action['value'];
            }
            break;
        case 'discount product':
            $columns['discount_product_product_id'] = $action['product_id'];
            if (_pg_offer_group_discount_column()) {
                $columns['discount_product_group_id'] = (isset($action['group_id']) && ($action['target'] == 'group')) ? (int) $action['group_id'] : 0;
            }
            if (_pg_offer_target_column()) {
                $columns['discount_product_target'] = ($action['target'] == 'product') ? '' : $action['target'];
            }
            if ($action['unit'] == 'amount') {
                $columns['discount_product_amount'] = $action['value'];
            } else {
                $columns['discount_product_percentage'] = $action['value'];
            }
            break;
        case 'add product':
            $columns['add_product_product_id'] = $action['product_id'];
            $columns['add_product_quantity'] = $action['quantity'];
            if ($action['unit'] == 'amount') {
                $columns['add_product_discount_amount'] = $action['value'];
            } else {
                $columns['add_product_discount_percentage'] = $action['value'];
            }
            break;
        case 'discount shipping':
            $columns['discount_shipping_percentage'] = $action['value'];
            break;
    }
    // The pending-offer screens print the action name to the customer when an
    // offer adds more than one product, so a gift row is named after its
    // product. Other rows are named after what they do; nobody sees those.
    if ($action['type'] == 'add product') {
        $name = _pg_offer_auto_name(_pg_offer_product_label($action['product_id'], $products));
    } else {
        $name = _pg_offer_auto_name(_pg_offer_action_text($action, $products, array()) . ' — ' . $offer_code);
    }

    $target_id = 0;
    if ($action['id'] > 0) {
        $current = db_item("SELECT * FROM offer_actions WHERE id = '" . e($action['id']) . "'");
        if ($current) {
            $unchanged = true;
            foreach ($columns as $column => $value) {
                if ((string) $current[$column] != (string) $value) {
                    $unchanged = false;
                    break;
                }
            }
            if ($unchanged) {
                $current_ids = array_map('intval', (array) db_values(
                    "SELECT shipping_method_id FROM offer_actions_shipping_methods_xref WHERE offer_action_id = '" . e($action['id']) . "'"));
                sort($current_ids);
                $new_ids = $action['shipping_method_ids'];
                sort($new_ids);
                if ($current_ids != $new_ids) {
                    $unchanged = false;
                }
            }
            if ($unchanged) {
                return (int) $action['id'];
            }
            if (_pg_offer_action_users($action['id'], $offer_id) == 0) {
                $target_id = (int) $action['id'];
            }
        }
    }

    $assignments = array("name = '" . e($name) . "'");
    foreach ($columns as $column => $value) {
        $assignments[] = $column . " = '" . e($value) . "'";
    }
    $assignments[] = "user = '" . e($user['id']) . "'";
    $assignments[] = "timestamp = UNIX_TIMESTAMP()";
    if ($target_id > 0) {
        db("UPDATE offer_actions SET " . implode(', ', $assignments) . " WHERE id = '" . e($target_id) . "'");
        db("DELETE FROM offer_actions_shipping_methods_xref WHERE offer_action_id = '" . e($target_id) . "'");
    } else {
        db("INSERT INTO offer_actions SET " . implode(', ', $assignments));
        $target_id = (int) mysqli_insert_id(db::$con);
    }
    foreach ($action['shipping_method_ids'] as $method_id) {
        db("INSERT INTO offer_actions_shipping_methods_xref (offer_action_id, shipping_method_id)
            VALUES ('" . e($target_id) . "', '" . e($method_id) . "')");
    }
    return $target_id;
}

function _pg_offer_action_delete($action_id)
{
    db("DELETE FROM offer_actions WHERE id = '" . e($action_id) . "'");
    db("DELETE FROM offer_actions_shipping_methods_xref WHERE offer_action_id = '" . e($action_id) . "'");
    db("DELETE FROM offers_offer_actions_xref WHERE offer_action_id = '" . e($action_id) . "'");
}

// Rule and action names are 50 characters wide and only ever generated here.
function _pg_offer_auto_name($text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if (mb_strlen($text) > 50) {
        $text = mb_substr($text, 0, 49) . '…';
    }
    return $text;
}

// Deletes the offer and the rule / action rows that no other offer uses.
function _pg_offer_delete($offer_id)
{
    $offer = db_item("SELECT * FROM offers WHERE id = '" . e($offer_id) . "'");
    if (!$offer) {
        return false;
    }
    $action_ids = array_map('intval', (array) db_values(
        "SELECT offer_action_id FROM offers_offer_actions_xref WHERE offer_id = '" . e($offer_id) . "'"));
    db("DELETE FROM offers WHERE id = '" . e($offer_id) . "'");
    db("DELETE FROM offers_offer_actions_xref WHERE offer_id = '" . e($offer_id) . "'");
    if (_pg_offer_conditions_table()) {
        db("DELETE FROM offer_conditions WHERE offer_id = '" . e($offer_id) . "'");
    }
    foreach ($action_ids as $action_id) {
        if (_pg_offer_action_users($action_id, $offer_id) == 0) {
            _pg_offer_action_delete($action_id);
        }
    }
    if (($offer['offer_rule_id'] > 0) && (_pg_offer_rule_users($offer['offer_rule_id'], $offer_id) == 0)) {
        _pg_offer_rule_delete($offer['offer_rule_id']);
    }
    log_activity(lang(array('string' => 'offer ({var:1}) was deleted', 'vars' => $offer['code'])), $_SESSION['sessionusername']);
    return true;
}

// Rule and action rows that no offer points at. The old screens let those
// pile up (a rule created and never attached, an action detached when an
// offer was edited); the list screen offers to remove them in one go.
function _pg_offer_orphans()
{
    $rules = (array) db_values(
        "SELECT offer_rules.id FROM offer_rules
        LEFT JOIN offers ON offers.offer_rule_id = offer_rules.id
        WHERE offers.id IS NULL");
    $actions = (array) db_values(
        "SELECT offer_actions.id FROM offer_actions
        LEFT JOIN offers_offer_actions_xref ON offers_offer_actions_xref.offer_action_id = offer_actions.id
        WHERE offers_offer_actions_xref.offer_id IS NULL");
    return array('rules' => array_map('intval', $rules), 'actions' => array_map('intval', $actions));
}

function _pg_offer_cleanup_orphans()
{
    $orphans = _pg_offer_orphans();
    foreach ($orphans['rules'] as $rule_id) {
        _pg_offer_rule_delete($rule_id);
    }
    foreach ($orphans['actions'] as $action_id) {
        _pg_offer_action_delete($action_id);
    }
    $count = count($orphans['rules']) + count($orphans['actions']);
    if ($count > 0) {
        log_activity(lang(array('string' => '{var:1} unused offer rule/action record(s) were deleted', 'vars' => $count)), $_SESSION['sessionusername']);
    }
    return $count;
}

// ── Summaries ────────────────────────────────────────────────────────────
//
// The same sentence is used by the editor strip (built again in JavaScript
// as the operator types), the list screen and the dashboard, so an offer
// reads the same everywhere.

// BASE_CURRENCY_SYMBOL is stored as an HTML entity ("&#8378;"). The
// summaries are plain text that the templates escape again, so the symbol is
// decoded here once.
function _pg_offer_currency()
{
    return html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8');
}

function _pg_offer_money($cents)
{
    return html_entity_decode(prepare_amount($cents / 100), ENT_QUOTES, 'UTF-8');
}

function _pg_offer_conditions_text($conditions, $products)
{
    $parts = array();
    foreach ($conditions as $condition) {
        if ($condition['type'] == 'subtotal') {
            $parts[] = lang(array('string' => 'cart is {var:1} or more', 'vars' => _pg_offer_money($condition['amount'])));
        } elseif ($condition['type'] == 'products') {
            $names = array();
            foreach ($condition['product_ids'] as $product_id) {
                $label = _pg_offer_product_label($product_id, $products);
                if ($label != '') {
                    $names[] = $label;
                }
            }
            $parts[] = lang(array('string' => 'cart has at least {var:1} of {var:2}', 'vars' => array((int) $condition['quantity'], $names ? implode(' / ', $names) : '?')));
        } elseif ($condition['type'] == 'product group') {
            $names = array();
            foreach ($condition['group_ids'] as $group_id) {
                $label = _pg_offer_group_label($group_id);
                if ($label != '') {
                    $names[] = $label;
                }
            }
            $parts[] = lang(array('string' => 'cart has at least {var:1} from {var:2}', 'vars' => array((int) $condition['quantity'], $names ? implode(' / ', $names) : '?')));
        } elseif ($condition['type'] == 'cart quantity') {
            $parts[] = lang(array('string' => 'cart holds at least {var:1} item(s)', 'vars' => (int) $condition['quantity']));
        } elseif ($condition['type'] == 'usage limit') {
            $parts[] = _pg_offer_usage_limit_text($condition);
        } elseif ($condition['type'] == 'new customer') {
            $parts[] = _pg_offer_new_customer_text($condition);
        }
    }
    if (!$parts) {
        return lang('every order');
    }
    return implode(' ' . lang('and') . ' ', $parts);
}

// "the customer has never ordered" / "… registered in the last 30 days". The
// days number only belongs to the modes that use it.
function _pg_offer_new_customer_text($condition)
{
    $mode = isset($condition['mode']) ? $condition['mode'] : 'no_orders';
    $days = isset($condition['days']) ? (int) $condition['days'] : 30;
    if ($mode == 'registered') {
        return lang(array('string' => 'the customer registered in the last {var:1} day(s)', 'vars' => $days));
    }
    if ($mode == 'both') {
        return lang(array('string' => 'the customer registered in the last {var:1} day(s) and has never ordered', 'vars' => $days));
    }
    return lang('the customer has never ordered');
}

// An action state may come from an older save (or from a site that has not
// run the upgrade), so the group target is asked for rather than assumed.
function _pg_offer_action_targets_group($action)
{
    return (isset($action['target']) && ($action['target'] == 'group') && isset($action['group_id']) && ((int) $action['group_id'] > 0));
}

// "used at most 100 times" / "once per customer" / both.
function _pg_offer_usage_limit_text($condition)
{
    $total = isset($condition['total_limit']) ? (int) $condition['total_limit'] : 0;
    $customer = isset($condition['customer_limit']) ? (int) $condition['customer_limit'] : 0;
    $parts = array();
    if ($total > 0) {
        $parts[] = lang(array('string' => 'used at most {var:1} time(s) in total', 'vars' => $total));
    }
    if ($customer > 0) {
        $parts[] = lang(array('string' => 'at most {var:1} time(s) per customer', 'vars' => $customer));
    }
    if (!$parts) {
        return lang('no usage limit');
    }
    return implode(' ' . lang('and') . ' ', $parts);
}

function _pg_offer_value_text($action)
{
    if ($action['unit'] == 'amount') {
        return _pg_offer_money($action['value']);
    }
    return '%' . (int) $action['value'];
}

function _pg_offer_action_text($action, $products, $shipping_methods)
{
    switch ($action['type']) {
        case 'discount order':
            if (!empty($action['tiers'])) {
                $rungs = array();
                foreach ($action['tiers'] as $tier) {
                    $rungs[] = lang(array('string' => '{var:1}+ → %{var:2}', 'vars' => array(_pg_offer_money($tier['min']), (int) $tier['percent'])));
                }
                return lang(array('string' => 'off the order, by tier: {var:1}', 'vars' => implode(' · ', $rungs)));
            }
            return lang(array('string' => '{var:1} off the order', 'vars' => _pg_offer_value_text($action)));
        case 'discount product':
            if (isset($action['target']) && ($action['target'] == 'cheapest')) {
                if (($action['unit'] == 'percent') && ($action['value'] >= 100)) {
                    return lang('the cheapest item in the cart is free');
                }
                return lang(array('string' => '{var:1} off the cheapest item in the cart', 'vars' => _pg_offer_value_text($action)));
            }
            if (_pg_offer_action_targets_group($action)) {
                return lang(array('string' => '{var:1} off everything in {var:2}', 'vars' => array(_pg_offer_value_text($action), _pg_offer_group_label($action['group_id']))));
            }
            return lang(array('string' => '{var:1} off {var:2}', 'vars' => array(_pg_offer_value_text($action), _pg_offer_product_label($action['product_id'], $products))));
        case 'add product':
            $label = _pg_offer_product_label($action['product_id'], $products);
            if (($action['unit'] == 'percent') && ($action['value'] >= 100)) {
                return lang(array('string' => '{var:1} × {var:2} added as a gift', 'vars' => array(max(1, (int) $action['quantity']), $label)));
            }
            if ($action['value'] <= 0) {
                return lang(array('string' => '{var:1} × {var:2} added to the cart', 'vars' => array(max(1, (int) $action['quantity']), $label)));
            }
            return lang(array('string' => '{var:1} × {var:2} added with {var:3} off', 'vars' => array(max(1, (int) $action['quantity']), $label, _pg_offer_value_text($action))));
        case 'discount shipping':
            $names = array();
            foreach ($shipping_methods as $method) {
                if (in_array($method['id'], $action['shipping_method_ids'])) {
                    $names[] = $method['name'];
                }
            }
            return lang(array('string' => '%{var:1} off shipping ({var:2})', 'vars' => array((int) $action['value'], $names ? implode(', ', $names) : '?')));
    }
    return '';
}

// Short forms for the chips on the list screen.
function _pg_offer_condition_chip($condition, $products)
{
    if ($condition['type'] == 'cart quantity') {
        return lang(array('string' => '{var:1} item(s) in cart', 'vars' => (int) $condition['quantity']));
    }
    if ($condition['type'] == 'usage limit') {
        $customer = isset($condition['customer_limit']) ? (int) $condition['customer_limit'] : 0;
        $total = isset($condition['total_limit']) ? (int) $condition['total_limit'] : 0;
        if ($customer > 0) {
            return lang(array('string' => '{var:1}× per customer', 'vars' => $customer));
        }
        return lang(array('string' => 'max {var:1} use(s)', 'vars' => $total));
    }
    if ($condition['type'] == 'product group') {
        $names = array();
        foreach ($condition['group_ids'] as $group_id) {
            $label = _pg_offer_group_label($group_id);
            if ($label != '') {
                $names[] = $label;
            }
        }
        return implode(' / ', $names) . ' × ' . (int) $condition['quantity'];
    }
    if ($condition['type'] == 'new customer') {
        $mode = isset($condition['mode']) ? $condition['mode'] : 'no_orders';
        $days = isset($condition['days']) ? (int) $condition['days'] : 30;
        if ($mode == 'no_orders') {
            return lang('New customer');
        }
        return lang('New customer') . ' · ' . lang(array('string' => '{var:1} day(s)', 'vars' => $days));
    }
    if ($condition['type'] == 'subtotal') {
        return lang(array('string' => 'Cart ≥ {var:1}', 'vars' => _pg_offer_money($condition['amount'])));
    }
    $names = array();
    foreach ($condition['product_ids'] as $product_id) {
        $label = _pg_offer_product_label($product_id, $products);
        if ($label != '') {
            $names[] = $label;
        }
    }
    return implode(' / ', $names) . ' × ' . (int) $condition['quantity'];
}

function _pg_offer_action_chip($action, $products)
{
    switch ($action['type']) {
        case 'discount order':
            if (!empty($action['tiers'])) {
                return lang('Order') . ' ' . lang(array('string' => '{var:1} tiers', 'vars' => count($action['tiers'])));
            }
            return lang('Order') . ' ' . _pg_offer_value_text($action);
        case 'discount product':
            if (isset($action['target']) && ($action['target'] == 'cheapest')) {
                return lang('Cheapest item') . ' ' . (($action['unit'] == 'amount') ? '−' : '') . _pg_offer_value_text($action);
            }
            if (_pg_offer_action_targets_group($action)) {
                return _pg_offer_group_label($action['group_id']) . ' ' . (($action['unit'] == 'amount') ? '−' : '') . _pg_offer_value_text($action);
            }
            return _pg_offer_product_label($action['product_id'], $products) . ' ' . (($action['unit'] == 'amount') ? '−' : '') . _pg_offer_value_text($action);
        case 'add product':
            return max(1, (int) $action['quantity']) . ' × ' . _pg_offer_product_label($action['product_id'], $products) . ' ' . lang('gift');
        case 'discount shipping':
            return lang('Shipping') . ' %' . (int) $action['value'];
    }
    return '';
}

// The one-line sentence: "Automatic · cart is ₺100.00 or more → %100 off shipping (…)".
function _pg_offer_sentence($state, $products, $shipping_methods)
{
    $offer = $state['offer'];
    if ($offer['require_code']) {
        $trigger = lang(array('string' => 'When the customer enters {var:1}', 'vars' => $offer['code']));
    } else {
        $trigger = lang('Automatic');
    }
    $results = array();
    foreach ($state['actions'] as $action) {
        $results[] = _pg_offer_action_text($action, $products, $shipping_methods);
    }
    return $trigger . ' · ' . _pg_offer_conditions_text($state['conditions'], $products) . ' → '
        . ($results ? implode(' + ', $results) : lang('nothing happens'));
}

// The summary sentence for many offers at once, as array(offer_id => sentence).
//
// _pg_offer_load() is four queries per offer, which is fine for one editor
// screen and wrong for a dashboard tile listing sixty. This reads the same
// tables once each and assembles the states in memory, so the cost does not
// grow with the number of offers.
function _pg_offer_sentences($offer_ids)
{
    $ids = array();
    foreach ((array) $offer_ids as $offer_id) {
        $offer_id = (int) $offer_id;
        if ($offer_id > 0) {
            $ids[$offer_id] = true;
        }
    }
    if (!$ids) {
        return array();
    }
    $in = "('" . implode("', '", array_map('e', array_keys($ids))) . "')";

    $offers = array();
    foreach ((array) db_items("SELECT id, code, require_code, offer_rule_id FROM offers WHERE id IN $in") as $row) {
        $offers[(int) $row['id']] = $row;
    }
    if (!$offers) {
        return array();
    }

    // Rules, and the products each rule requires.
    $rule_ids = array();
    foreach ($offers as $row) {
        if ((int) $row['offer_rule_id'] > 0) {
            $rule_ids[(int) $row['offer_rule_id']] = true;
        }
    }
    $rules = array();
    $rule_products = array();
    if ($rule_ids) {
        $rule_in = "('" . implode("', '", array_map('e', array_keys($rule_ids))) . "')";
        foreach ((array) db_items("SELECT id, required_subtotal, required_quantity FROM offer_rules WHERE id IN $rule_in") as $row) {
            $rules[(int) $row['id']] = $row;
        }
        foreach ((array) db_items("SELECT offer_rule_id, product_id FROM offer_rules_products_xref WHERE offer_rule_id IN $rule_in") as $row) {
            $rule_products[(int) $row['offer_rule_id']][] = (int) $row['product_id'];
        }
    }

    // Conditions that live outside the rule row.
    $extra = array();
    if (_pg_offer_conditions_table()) {
        foreach ((array) db_items("SELECT offer_id, type, int_value, text_value FROM offer_conditions WHERE offer_id IN $in ORDER BY id") as $row) {
            $extra[(int) $row['offer_id']][] = $row;
        }
    }

    // Actions.
    $actions = array();
    foreach ((array) db_items(
        "SELECT offers_offer_actions_xref.offer_id, offer_actions.*
        FROM offers_offer_actions_xref
        INNER JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id
        WHERE offers_offer_actions_xref.offer_id IN $in
        ORDER BY offer_actions.id") as $row) {
        $actions[(int) $row['offer_id']][] = _pg_offer_action_from_row($row);
    }

    $products = _pg_offer_products();
    $shipping_methods = _pg_offer_shipping_methods();

    $out = array();
    foreach ($offers as $offer_id => $row) {
        $conditions = array();
        $rule_id = (int) $row['offer_rule_id'];
        if ($rule_id > 0 && isset($rules[$rule_id])) {
            if ($rules[$rule_id]['required_subtotal'] > 0) {
                $conditions[] = array(
                    'type'   => 'subtotal',
                    'amount' => (int) $rules[$rule_id]['required_subtotal']);
            }
            if (!empty($rule_products[$rule_id])) {
                $conditions[] = array(
                    'type'        => 'products',
                    'product_ids' => $rule_products[$rule_id],
                    'quantity'    => (int) $rules[$rule_id]['required_quantity']);
            }
        }
        if (isset($extra[$offer_id])) {
            foreach ($extra[$offer_id] as $condition_row) {
                if ($condition_row['type'] == 'product group') {
                    $conditions[] = array(
                        'type'      => 'product group',
                        'group_ids' => array_values(array_filter(array_map('intval', explode(',', (string) $condition_row['text_value'])))),
                        'quantity'  => ((int) $condition_row['int_value'] > 0) ? (int) $condition_row['int_value'] : 1);
                } elseif ($condition_row['type'] == 'cart quantity') {
                    $conditions[] = array(
                        'type'     => 'cart quantity',
                        'quantity' => ((int) $condition_row['int_value'] > 0) ? (int) $condition_row['int_value'] : 1);
                } elseif ($condition_row['type'] == 'usage limit') {
                    $conditions[] = array(
                        'type'           => 'usage limit',
                        'total_limit'    => max(0, (int) $condition_row['int_value']),
                        'customer_limit' => max(0, (int) $condition_row['text_value']));
                } elseif ($condition_row['type'] == 'new customer') {
                    $conditions[] = array(
                        'type' => 'new customer',
                        'mode' => ($condition_row['text_value'] != '') ? $condition_row['text_value'] : 'no_orders',
                        'days' => ((int) $condition_row['int_value'] > 0) ? (int) $condition_row['int_value'] : 30);
                }
            }
        }
        $offer_actions = isset($actions[$offer_id]) ? $actions[$offer_id] : array();
        if (isset($extra[$offer_id])) {
            foreach ($extra[$offer_id] as $condition_row) {
                if ($condition_row['type'] != 'tiers') {
                    continue;
                }
                $ladder = _pg_offer_tiers_parse($condition_row['text_value']);
                foreach ($offer_actions as $i => $offer_action) {
                    if (($offer_action['type'] == 'discount order') && $ladder) {
                        $offer_actions[$i]['tiers'] = $ladder;
                        break;
                    }
                }
            }
        }
        $state = array(
            'offer'      => array(
                'code'         => (string) $row['code'],
                'require_code' => (int) $row['require_code']),
            'conditions' => $conditions,
            'actions'    => $offer_actions);
        $out[$offer_id] = _pg_offer_sentence($state, $products, $shipping_methods);
    }
    return $out;
}

// One status for the list: what the offer is doing today, rather than the
// status column and the date range read separately.
function _pg_offer_status($offer)
{
    $today = date('Y-m-d');
    if ($offer['status'] != 'enabled') {
        return 'disabled';
    }
    if ($offer['start_date'] > $today) {
        return 'scheduled';
    }
    if ($offer['end_date'] < $today) {
        return 'expired';
    }
    return 'active';
}

function _pg_offer_status_label($status)
{
    switch ($status) {
        case 'active':
            return lang('Active');
        case 'scheduled':
            return lang('Planned');
        case 'expired':
            return lang('Expired');
    }
    return lang('Disabled');
}

// An offer that is saved but cannot do anything at checkout: the list marks
// these so the operator does not go looking for the reason in the storefront.
function _pg_offer_is_incomplete($state)
{
    if (!$state['actions']) {
        return true;
    }
    if ($state['offer']['require_code'] && ($state['offer']['code'] == '')) {
        return true;
    }
    foreach ($state['conditions'] as $condition) {
        if (($condition['type'] == 'products') && ((!$condition['product_ids']) || ($condition['quantity'] < 1))) {
            return true;
        }
        if (($condition['type'] == 'product group') && ((!$condition['group_ids']) || ($condition['quantity'] < 1))) {
            return true;
        }
        if (($condition['type'] == 'cart quantity') && ($condition['quantity'] < 1)) {
            return true;
        }
        if (($condition['type'] == 'usage limit') && ($condition['total_limit'] < 1) && ($condition['customer_limit'] < 1)) {
            return true;
        }
    }
    foreach ($state['actions'] as $action) {
        // A product discount names a product or a group; the value check below
        // still applies to both.
        $group_target = (($action['type'] == 'discount product')
            && (_pg_offer_action_targets_group($action) || (isset($action['target']) && ($action['target'] == 'cheapest'))));
        if ((!$group_target) && ((($action['type'] == 'discount product') || ($action['type'] == 'add product')) && ($action['product_id'] <= 0))) {
            return true;
        }
        if (($action['type'] == 'discount shipping') && !$action['shipping_method_ids']) {
            return true;
        }
        if (($action['type'] != 'add product') && ($action['value'] <= 0)) {
            return true;
        }
    }
    return false;
}

// ── API handler ──────────────────────────────────────────────────────────

// Called from api.php for action "offer_editor". Every sub-action checks the
// token and the e-commerce permission itself, because the general gate in
// api.php asks for a designer role and offers belong to whoever manages the
// store. Responds and exits.
// A key code for this offer. Ten characters from an alphabet with no lower
// case, so a code read off a screen or a card cannot be mistyped as another
// one, and never a code that already exists.
function _pg_offer_key_code()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= mb_substr($characters, mt_rand(0, 35), 1);
        }
        if (!db_value("SELECT id FROM key_codes WHERE code = '" . e($code) . "'")) {
            return $code;
        }
    }
    return '';
}

// Codes the customer types instead of the offer code itself: one per customer,
// per card, per mailing. They are single use by default because that is the
// reason to hand out separate codes at all; anything else about them (notes,
// an expiry of their own, reusable codes) belongs on the key code screen, and
// the offer screen only hands out a batch.
function _pg_offer_key_codes_generate($offer_code, $quantity, $user)
{
    $codes = array();
    for ($i = 0; $i < $quantity; $i++) {
        $code = _pg_offer_key_code();
        if ($code == '') {
            break;
        }
        db("INSERT INTO key_codes (
                code,
                offer_code,
                enabled,
                expiration_date,
                notes,
                single_use,
                report,
                user,
                timestamp)
            VALUES (
                '" . e($code) . "',
                '" . e($offer_code) . "',
                '1',
                '0000-00-00',
                '',
                '1',
                'key_code',
                '" . e($user['id']) . "',
                UNIX_TIMESTAMP())");
        $codes[] = $code;
    }
    return $codes;
}

function pg_offer_editor_handle($request)
{
    validate_token();
    $user = validate_user();
    if (($user['role'] >= 3) && !$user['manage_ecommerce']) {
        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
    }
    $type = isset($request['type']) ? (string) $request['type'] : '';

    switch ($type) {
        case 'offer_load':
            $offer_id = isset($request['id']) ? (int) $request['id'] : 0;
            $state = ($offer_id > 0) ? _pg_offer_load($offer_id) : _pg_offer_new_state();
            if (!$state) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'state'   => $state,
                'context' => _pg_offer_editor_context($state)));
            break;

        case 'offer_save':
            $checked = _pg_offer_validate($request);
            if ($checked['errors']) {
                respond(array(
                    'status'  => 'error',
                    'request' => $type,
                    'errors'  => $checked['errors'],
                    'message' => lang('Please correct the highlighted rows.')));
            }
            $was_new = (isset($checked['clean']['offer']['id']) && ((int) $checked['clean']['offer']['id'] > 0)) ? false : true;
            $offer_id = _pg_offer_save($checked['clean'], $user);
            if ($offer_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            // The editor sends the operator back to the list once it has
            // saved, so the confirmation is left where the list will find it -
            // the same hand-off every other edit screen uses.
            include_once(dirname(__FILE__) . '/liveform.class.php');
            $liveform_view_offers = new liveform('view_offers');
            $liveform_view_offers->add_notice($was_new
                ? lang('The offer has been created.')
                : lang('The offer has been saved.'));
            $state = _pg_offer_load($offer_id);
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'id'      => $offer_id,
                'state'   => $state,
                'context' => _pg_offer_editor_context($state),
                'message' => lang('The offer has been saved.')));
            break;

        case 'offer_toggle_status':
            $offer_id = isset($request['id']) ? (int) $request['id'] : 0;
            $offer = db_item("SELECT id, code, status FROM offers WHERE id = '" . e($offer_id) . "'");
            if (!$offer) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            $status = ($offer['status'] == 'enabled') ? 'disabled' : 'enabled';
            db("UPDATE offers SET
                    status = '" . e($status) . "',
                    user = '" . e($user['id']) . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . e($offer_id) . "'");
            log_activity(lang(array('string' => 'offer ({var:1}) was modified', 'vars' => $offer['code'])), $_SESSION['sessionusername']);
            $offer = db_item("SELECT * FROM offers WHERE id = '" . e($offer_id) . "'");
            $offer_status = _pg_offer_status($offer);
            respond(array(
                'status'       => 'success',
                'request'      => $type,
                'id'           => $offer_id,
                'enabled'      => ($status == 'enabled') ? 1 : 0,
                'offer_status' => $offer_status,
                'label'        => _pg_offer_status_label($offer_status)));
            break;

        case 'offer_delete':
            $offer_id = isset($request['id']) ? (int) $request['id'] : 0;
            if (!_pg_offer_delete($offer_id)) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            include_once(dirname(__FILE__) . '/liveform.class.php');
            $liveform_view_offers = new liveform('view_offers');
            $liveform_view_offers->add_notice(lang('The offer has been deleted.'));
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'message' => lang('The offer has been deleted.')));
            break;

        // Every key code that hangs off this offer's code. The key code screen
        // can only empty the whole table; this removes one offer's batch, which
        // is what an operator who generated a batch by mistake actually wants.
        case 'offer_key_codes_delete':
            $offer_id = isset($request['id']) ? (int) $request['id'] : 0;
            $offer = db_item("SELECT id, code FROM offers WHERE id = '" . e($offer_id) . "'");
            if (!$offer || ($offer['code'] == '')) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            $count = (int) db_value("SELECT COUNT(*) FROM key_codes WHERE offer_code = '" . e($offer['code']) . "'");
            if ($count > 0) {
                db("DELETE FROM key_codes WHERE offer_code = '" . e($offer['code']) . "'");
                log_activity(lang(array(
                    'string' => '{var:1} key code(s) of offer ({var:2}) were deleted',
                    'vars'   => array(number_format($count), $offer['code']))), $_SESSION['sessionusername']);
            }
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'count'   => 0,
                'deleted' => $count,
                'message' => lang(array('string' => '{var:1} key code(s) were deleted.', 'vars' => number_format($count)))));
            break;

        case 'offer_key_codes':
            $offer_id = isset($request['id']) ? (int) $request['id'] : 0;
            $offer = db_item("SELECT id, code FROM offers WHERE id = '" . e($offer_id) . "'");
            if (!$offer || ($offer['code'] == '')) {
                respond(array('status' => 'error', 'message' => lang('The offer could not be found.')));
            }
            $quantity = isset($request['quantity']) ? (int) $request['quantity'] : 0;
            if ($quantity < 1) {
                $quantity = 1;
            }
            // The screen's stepper stops at 100; a hand-made request is held
            // to the same number.
            if ($quantity > 100) {
                $quantity = 100;
            }
            $codes = _pg_offer_key_codes_generate($offer['code'], $quantity, $user);
            log_activity(lang(array('string' => '{var:1} key codes were created.', 'vars' => number_format(count($codes)))), $_SESSION['sessionusername']);
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'codes'   => $codes,
                'count'   => (int) db_value("SELECT COUNT(*) FROM key_codes WHERE offer_code = '" . e($offer['code']) . "'"),
                'message' => lang(array('string' => '{var:1} key code(s) were created.', 'vars' => count($codes)))));
            break;

        case 'offer_cleanup_orphans':
            $count = _pg_offer_cleanup_orphans();
            respond(array(
                'status'  => 'success',
                'request' => $type,
                'count'   => $count,
                'message' => lang(array('string' => '{var:1} unused record(s) were deleted.', 'vars' => $count))));
            break;
    }

    respond(array('status' => 'error', 'message' => lang('Invalid request.')));
}
