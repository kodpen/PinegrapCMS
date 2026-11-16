<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A contact group's minimum order value.
 *
 * One settings text (config_text 'contact_group_minimum_order') maps a
 * contact group's id to an amount in whole minor units of the base
 * currency. A signed-in customer whose contact is in such groups must reach
 * the highest of them; a guest and a customer in none order as before. The
 * amount compared is the goods in the cart (price times quantity of the
 * active lines), before discounts, tax and shipping.
 *
 * pg_minimum_order_check() answers both the refusal when the order is sent
 * (submit_order.php, for the express order and the order preview) and the
 * cart's warning before it (includes/fn/widgets_cart.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The config_text row the group => amount map is kept in (JSON).
define('PG_MINIMUM_ORDER_SETTING', 'contact_group_minimum_order');

/**
 * The map as stored, read without the database: positive group ids to
 * positive whole amounts; anything else is dropped. Pure.
 *
 * @param mixed $raw
 * @return array group id => minor units
 */
function pg_minimum_order_read($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $out = array();

    foreach (is_array($data) ? $data : array() as $group_id => $amount) {
        if (!is_numeric($group_id) || ((int) $group_id <= 0) || !is_numeric($amount)) {
            continue;
        }

        $amount = (int) round((float) $amount);

        if ($amount > 0) {
            $out[(int) $group_id] = $amount;
        }
    }

    return $out;
}

/**
 * The value stored for a map; '' for an empty one, which removes the row. Pure.
 *
 * @param array $map group id => minor units
 * @return string
 */
function pg_minimum_order_encode($map)
{
    $clean = pg_minimum_order_read((string) json_encode((object) $map));
    ksort($clean);

    return empty($clean) ? '' : (string) json_encode((object) $clean);
}

/**
 * The map with one group's amount set; 0 removes the group. Pure.
 *
 * @param array $map
 * @param int   $group_id
 * @param int   $amount minor units
 * @return array
 */
function pg_minimum_order_put($map, $group_id, $amount)
{
    $map = (array) $map;
    unset($map[(int) $group_id]);

    if (((int) $group_id > 0) && ((int) $amount > 0)) {
        $map[(int) $group_id] = (int) $amount;
    }

    return pg_minimum_order_read((string) json_encode((object) $map));
}

/**
 * The entries of groups that still exist. Pure.
 *
 * @param array $map
 * @param int[] $existing ids of the contact groups there are
 * @return array
 */
function pg_minimum_order_existing($map, $existing)
{
    $existing = array_map('intval', (array) $existing);
    $out = array();

    foreach ((array) $map as $group_id => $amount) {
        if (in_array((int) $group_id, $existing, true)) {
            $out[(int) $group_id] = (int) $amount;
        }
    }

    return $out;
}

/**
 * The minimum a customer in these groups must reach: the highest of theirs,
 * 0 for none. Pure.
 *
 * @param array $map
 * @param int[] $group_ids the customer's contact groups
 * @return int minor units
 */
function pg_minimum_order_for_groups($map, $group_ids)
{
    $minimum = 0;

    foreach ((array) $group_ids as $group_id) {
        $minimum = max($minimum, (int) ($map[(int) $group_id] ?? 0));
    }

    return $minimum;
}

/**
 * An amount in the order's currency turned into the base currency: divided
 * by the currency's rate (units per base unit) and rounded, never cut. A
 * rate of zero or less counts as the base currency. Pure.
 *
 * @param int|float $amount minor units of the order's currency
 * @param float     $rate
 * @return int minor units of the base currency
 */
function pg_minimum_order_base_amount($amount, $rate)
{
    $rate = ((float) $rate > 0) ? (float) $rate : 1.0;

    return (int) round((float) $amount / $rate);
}

/**
 * Whether an amount reaches a minimum, and how much is missing. Pure.
 *
 * @param int $minimum minor units, 0 for none
 * @param int $amount  minor units, base currency
 * @return array ok, minimum, amount, missing
 */
function pg_minimum_order_judge($minimum, $amount)
{
    $minimum = max(0, (int) $minimum);
    $amount = (int) $amount;
    $missing = max(0, $minimum - $amount);

    return array('ok' => ($missing === 0), 'minimum' => $minimum, 'amount' => $amount, 'missing' => $missing);
}

/**
 * An amount typed in the money box (major units) in whole minor units; 0 for
 * an empty box, null for a negative amount. Pure.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_minimum_order_posted($value)
{
    if (trim((string) $value) === '') {
        return 0;
    }

    $amount = pg_parse_amount($value);

    return ($amount < 0) ? null : (int) round($amount * 100);
}

/**
 * The map of the groups that still exist.
 *
 * @return array group id => minor units
 */
function pg_minimum_order_map()
{
    $map = pg_minimum_order_read(pg_config_text(PG_MINIMUM_ORDER_SETTING));

    if (empty($map)) {
        return array();
    }

    return pg_minimum_order_existing($map, (array) db_values("SELECT id FROM contact_groups WHERE id IN (" . implode(',', array_map('intval', array_keys($map))) . ")"));
}

/**
 * One group's minimum, 0 for none.
 *
 * @param int $group_id
 * @return int minor units
 */
function pg_minimum_order_of_group($group_id)
{
    return (int) (pg_minimum_order_read(pg_config_text(PG_MINIMUM_ORDER_SETTING))[(int) $group_id] ?? 0);
}

/**
 * The contact of the customer placing the order: a signed-in visitor's, not
 * while staff look at the site as somebody else; 0 for a guest.
 *
 * @return int
 */
function pg_minimum_order_customer()
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN || !empty($_SESSION['software']['ghost'])) {
        return 0;
    }

    return defined('USER_CONTACT_ID') ? (int) USER_CONTACT_ID : 0;
}

/**
 * Whether the customer's order reaches the minimum of their groups, with the
 * sentence to show when it does not: the refusal when the order is sent, or
 * the cart's warning before it. Amounts are shown in the visitor's currency.
 *
 * @param int       $contact_id the customer's contact, 0 for a guest
 * @param int|float $amount     the goods, minor units of the order's currency
 * @param float     $rate       the order currency's rate (1 for the base)
 * @param string    $context    submit | cart
 * @return array ok, minimum, amount, missing, message ('' when ok)
 */
function pg_minimum_order_check($contact_id, $amount, $rate = 1.0, $context = 'submit')
{
    $minimum = 0;

    if ((int) $contact_id > 0) {
        $map = pg_minimum_order_map();

        if (!empty($map)) {
            $minimum = pg_minimum_order_for_groups($map, (array) db_values("SELECT contact_group_id FROM contacts_contact_groups_xref WHERE contact_id = '" . (int) $contact_id . "'"));
        }
    }

    $judged = pg_minimum_order_judge($minimum, pg_minimum_order_base_amount($amount, $rate));
    $judged['message'] = '';

    if (!$judged['ok']) {
        $vars = array(pg_visitor_money($judged['minimum'] / 100), pg_visitor_money($judged['missing'] / 100));
        $judged['message'] = ($context === 'cart')
            ? lang(array('string' => 'Orders from your account start at {var:1}. Add {var:2} more to place your order.', 'vars' => $vars))
            : lang(array('string' => 'Sorry, the order was not accepted: orders from your account start at {var:1}, and {var:2} is still missing.', 'vars' => $vars));
    }

    return $judged;
}

/**
 * Sets one group's minimum in the one settings text, 0 removing it. Written
 * only while the text still holds what was read, so two groups saved at the
 * same time do not take each other's amount away.
 *
 * @param int $group_id
 * @param int $amount minor units
 * @return bool
 */
function pg_minimum_order_save($group_id, $amount)
{
    for ($try = 0; $try < 3; $try++) {
        $read = pg_schema_has('config_text')
            ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_MINIMUM_ORDER_SETTING) . "'") : '';
        $value = pg_minimum_order_encode(pg_minimum_order_put(pg_minimum_order_read($read), $group_id, $amount));

        if ($value === $read) {
            return true;
        }

        if ($value === '') {
            return pg_config_text_save(PG_MINIMUM_ORDER_SETTING, '');
        }

        if (pg_config_text_claim(PG_MINIMUM_ORDER_SETTING, $read, $value)) {
            return true;
        }
    }

    return false;
}

/**
 * May this person set a group's minimum: the shop is on and they have its
 * rights.
 *
 * @param array $user validate_user() row
 * @return bool
 */
function pg_minimum_order_may_set($user)
{
    return defined('ECOMMERCE') && (ECOMMERCE === true) && is_array($user)
        && (((int) ($user['role'] ?? 3) < 3) || !empty($user['manage_ecommerce']));
}

/**
 * The contact group edit screen's card: the minimum order value in the base
 * currency, empty for none.
 *
 * @param liveform $liveform
 * @return string HTML
 */
function pg_minimum_order_card($liveform)
{
    return '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Minimum Order') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 sm:col-6 my-3">
                                            <label for="minimum_order" class="form-label">' . lang('Minimum order value') . '</label>
                                            <div class="input-group">
                                                <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                ' . $liveform->output_field(array('type' => 'text', 'name' => 'minimum_order', 'id' => 'minimum_order', 'inputmode' => 'decimal', 'class' => 'form-control', 'aria-describedby' => 'minimum_order_help') + pg_money_mask_attributes()) . '
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text" id="minimum_order_help">' . lang('Signed-in customers in this group can place an order from this value of goods on, before discounts, tax and shipping. In several groups, the highest applies. Leave it empty for no minimum.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
}
