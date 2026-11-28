<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Markets: a set of countries sold to in one currency, with a rule that
 * rounds a converted price. A row of `markets` holds the name, the currency
 * code, the rounding rule, whether it is on and its place in the list;
 * `market_countries` ties a country to one market (a country is in one
 * market at most, so the choice is never ambiguous). orders.market_id names
 * the market that priced an order (0: none).
 *
 * This file reads and writes the markets for the Markets screen
 * (view_markets.php). A site with no market row sells exactly as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The visitor's market on the site: its prices, the country list.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/markets_site.php');

// The cart in its market's currency.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/market_cart.php');

// A product's fixed price in a market.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/market_prices.php');

// The longest market name the column keeps.
define('PG_MARKET_NAME_MAX', 100);

/**
 * Whether the markets' tables exist (the 2026.11.0 upgrade has run).
 *
 * @return bool
 */
function pg_markets_ready()
{
    return pg_schema_has('markets') && pg_schema_has('market_countries');
}

/**
 * The rounding rules a market can take, code => label. Pure but for lang().
 *
 * none:  to the currency's smallest unit (12.34)
 * whole: up to whole units (13.00)
 * end99: up to a .99 ending (12.99)
 *
 * @return array
 */
function pg_market_rounding_options()
{
    return array(
        'none'  => lang('To the cent'),
        'whole' => lang('Whole amounts'),
        'end99' => lang('Ending in .99'),
    );
}

/**
 * The rounding rule as stored: one of the codes of
 * pg_market_rounding_options(), 'none' for anything else. Pure.
 *
 * @param mixed $rule
 * @return string
 */
function pg_market_rounding_code($rule)
{
    $rule = strtolower(trim((string) $rule));

    return in_array($rule, array('none', 'whole', 'end99'), true) ? $rule : 'none';
}

/**
 * A market's form post brought to the values it is saved with, and what is
 * wrong with them. Pure.
 *
 * @param array $post        'name', 'currency_code', 'rounding', 'enabled',
 *                           'countries' (country ids)
 * @param array $currencies  The codes a market may take (the site's
 *                           currencies)
 * @param array $country_ids The ids of the site's countries
 * @return array  'values' (name, currency_code, rounding, enabled,
 *                countries) and 'errors' (field => message)
 */
function pg_market_form_values(array $post, array $currencies, array $country_ids)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($post['name'] ?? '')));
    $code = strtoupper(trim((string) ($post['currency_code'] ?? '')));
    $known = array_flip(array_map('intval', $country_ids));
    $countries = array();

    foreach ((array) ($post['countries'] ?? array()) as $id) {
        $id = (int) $id;

        if (($id > 0) && isset($known[$id])) {
            $countries[$id] = $id;
        }
    }

    $values = array(
        'name' => $name,
        'currency_code' => $code,
        'rounding' => pg_market_rounding_code($post['rounding'] ?? ''),
        'enabled' => !empty($post['enabled']) ? 1 : 0,
        'countries' => array_values($countries),
    );

    $errors = array();

    if ($name === '') {
        $errors['name'] = lang('Give the market a name.');
    } elseif (mb_strlen($name) > PG_MARKET_NAME_MAX) {
        $errors['name'] = lang(array('string' => 'The name is at most {var:1} characters.', 'vars' => PG_MARKET_NAME_MAX));
    }

    if (!in_array($code, $currencies, true)) {
        $errors['currency_code'] = lang('Choose one of the store\'s currencies.');
    }

    if (!$countries) {
        $errors['countries'] = lang('Choose at least one country.');
    }

    return array('values' => $values, 'errors' => $errors);
}

/**
 * The chosen countries that another market already holds. Pure.
 *
 * @param array $chosen    Country ids
 * @param array $taken     country id => market id, for every market
 * @param int   $market_id The market being saved, 0 for a new one
 * @return array  country id => market id of those held elsewhere
 */
function pg_market_country_conflicts(array $chosen, array $taken, $market_id)
{
    $market_id = (int) $market_id;
    $conflicts = array();

    foreach ($chosen as $id) {
        $id = (int) $id;

        if (isset($taken[$id]) && ((int) $taken[$id] !== $market_id)) {
            $conflicts[$id] = (int) $taken[$id];
        }
    }

    return $conflicts;
}

/**
 * Every market with its countries, in the list's order.
 *
 * @return array  Rows: id, name, currency_code, rounding, enabled,
 *                sort_order, countries (ids)
 */
function pg_market_list()
{
    if (!pg_markets_ready()) {
        return array();
    }

    $markets = array();

    foreach (db_items("SELECT id, name, currency_code, rounding, enabled, sort_order FROM markets ORDER BY sort_order, name, id") as $row) {
        $row['id'] = (int) $row['id'];
        $row['enabled'] = (int) $row['enabled'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['countries'] = array();
        $markets[$row['id']] = $row;
    }

    foreach (db_items("SELECT market_id, country_id FROM market_countries") as $row) {
        if (isset($markets[(int) $row['market_id']])) {
            $markets[(int) $row['market_id']]['countries'][] = (int) $row['country_id'];
        }
    }

    return $markets;
}

/**
 * Which market holds each country.
 *
 * @return array  country id => market id
 */
function pg_market_country_map()
{
    $map = array();

    foreach (db_items("SELECT market_id, country_id FROM market_countries") as $row) {
        $map[(int) $row['country_id']] = (int) $row['market_id'];
    }

    return $map;
}

/**
 * The site's currencies a market may take, code => label (the label in the
 * panel's language, its code beside it).
 *
 * @return array
 */
function pg_market_currency_options()
{
    $options = array();

    foreach (db_items("SELECT name, code FROM currencies ORDER BY base DESC, name") as $row) {
        $code = strtoupper(trim((string) $row['code']));

        if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
            $options[$code] = pg_currency_label($row['name']) . ' (' . $code . ')';
        }
    }

    return $options;
}

/**
 * The site's countries, id => name in the panel's language, ordered by it.
 *
 * @return array
 */
function pg_market_country_options()
{
    $countries = array();

    foreach (db_items("SELECT id, name, code FROM countries") as $row) {
        $countries[] = array('id' => (int) $row['id'], 'label' => pg_geo_country_label($row['name'], $row['code']));
    }

    $options = array();

    foreach (pg_geo_sort_by_label($countries) as $country) {
        $options[$country['id']] = $country['label'];
    }

    return $options;
}

/**
 * Saves a market and its countries. The values come from
 * pg_market_form_values() without errors; a country another market holds
 * is refused before anything is written.
 *
 * @param array $values
 * @param int   $market_id 0 adds a market
 * @return array  'ok', 'id', 'error' (a message when refused)
 */
function pg_market_save(array $values, $market_id = 0)
{
    $market_id = (int) $market_id;

    if (($market_id > 0) && !db_value("SELECT id FROM markets WHERE id = '" . e($market_id) . "'")) {
        return array('ok' => false, 'id' => 0, 'error' => lang('This market no longer exists.'));
    }

    $conflicts = pg_market_country_conflicts($values['countries'], pg_market_country_map(), $market_id);

    if ($conflicts) {
        $names = pg_market_country_options();
        $markets = pg_market_list();
        $taken = array();

        foreach ($conflicts as $country_id => $owner) {
            $taken[] = ($names[$country_id] ?? $country_id) . ' (' . ($markets[$owner]['name'] ?? '') . ')';
        }

        return array('ok' => false, 'id' => $market_id, 'error' => lang(array('string' => 'A country is in one market only. Take these out of their market first: {var:1}', 'vars' => implode(', ', $taken))));
    }

    $row = array($values['name'], $values['currency_code'], $values['rounding'], (int) $values['enabled']);

    if ($market_id > 0) {
        $ok = db_q("UPDATE markets SET name = ?, currency_code = ?, rounding = ?, enabled = ? WHERE id = ?", array_merge($row, array($market_id)));
    } else {
        $next = (int) db_value("SELECT MAX(sort_order) FROM markets") + 1;
        $ok = db_q("INSERT INTO markets (name, currency_code, rounding, enabled, sort_order) VALUES (?, ?, ?, ?, ?)", array_merge($row, array($next)));
        $market_id = $ok ? (int) mysqli_insert_id(db::$con) : 0;
    }

    if (!$ok || ($market_id === 0)) {
        return array('ok' => false, 'id' => $market_id, 'error' => lang('The market could not be saved.'));
    }

    db_q("DELETE FROM market_countries WHERE market_id = ?", array($market_id));

    foreach ($values['countries'] as $country_id) {
        db_q("INSERT IGNORE INTO market_countries (market_id, country_id) VALUES (?, ?)", array($market_id, (int) $country_id));
    }

    return array('ok' => true, 'id' => $market_id, 'error' => '');
}

/**
 * How many orders a market priced: such a market is switched off, not
 * deleted, so its orders keep naming it.
 *
 * @param int $market_id
 * @return int
 */
function pg_market_order_count($market_id)
{
    if (!pg_schema_has('orders', 'market_id')) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM orders WHERE market_id = '" . e((int) $market_id) . "'");
}

/**
 * Deletes a market and its countries, refused while an order names it.
 *
 * @param int $market_id
 * @return array  'ok', 'error'
 */
function pg_market_delete($market_id)
{
    $market_id = (int) $market_id;

    if (pg_market_order_count($market_id) > 0) {
        return array('ok' => false, 'error' => lang('Orders were priced in this market, so it cannot be deleted. Switch it off instead.'));
    }

    db_q("DELETE FROM market_countries WHERE market_id = ?", array($market_id));
    pg_market_prices_delete_market($market_id);
    db_q("DELETE FROM markets WHERE id = ?", array($market_id));

    return array('ok' => true, 'error' => '');
}

/**
 * Moves a market one place up or down the list, the list renumbered from 1.
 *
 * @param int    $market_id
 * @param string $direction 'up' or 'down'
 */
function pg_market_move($market_id, $direction)
{
    $ids = array_keys(pg_market_list());
    $ids = pg_market_move_ids($ids, (int) $market_id, $direction);

    foreach ($ids as $place => $id) {
        db_q("UPDATE markets SET sort_order = ? WHERE id = ?", array($place + 1, $id));
    }
}

/**
 * The ids in their new order after one moves a place up or down; the list
 * as it is when the id is missing or already at that end. Pure.
 *
 * @param array  $ids
 * @param int    $market_id
 * @param string $direction 'up' or 'down'
 * @return array
 */
function pg_market_move_ids(array $ids, $market_id, $direction)
{
    $ids = array_values(array_map('intval', $ids));
    $at = array_search((int) $market_id, $ids, true);

    if ($at === false) {
        return $ids;
    }

    $to = ($direction === 'up') ? $at - 1 : $at + 1;

    if (($to < 0) || ($to >= count($ids))) {
        return $ids;
    }

    $ids[$at] = $ids[$to];
    $ids[$to] = (int) $market_id;

    return $ids;
}

/**
 * The state pill of a market: On teal, Off grey.
 *
 * @param int $enabled
 * @return string
 */
function pg_market_pill($enabled)
{
    return $enabled
        ? '<span class="pg-pill pg-tone-teal">' . h(lang('On')) . '</span>'
        : '<span class="pg-pill pg-tone-gray">' . h(lang('Off')) . '</span>';
}

/**
 * The countries cell of the list: the first names, then "and N more".
 *
 * @param array $country_ids
 * @param array $names id => name (pg_market_country_options())
 * @param int   $shown       How many are named
 * @return string  Plain text
 */
function pg_market_country_summary(array $country_ids, array $names, $shown = 3)
{
    $labels = array();

    foreach ($names as $id => $label) {
        if (in_array((int) $id, array_map('intval', $country_ids), true)) {
            $labels[] = $label;
        }
    }

    if (count($labels) <= $shown) {
        return implode(', ', $labels);
    }

    return lang(array('string' => '{var:1} and {var:2} more', 'vars' => array(implode(', ', array_slice($labels, 0, $shown)), count($labels) - $shown)));
}

/**
 * The drawer that adds or changes a market: name, currency, countries,
 * rounding and the switch, Save filled and Cancel quiet in its foot; a
 * market that exists also gets Delete there.
 *
 * @param array $args 'id' (drawer id), 'market_id' (0 adds), 'values'
 *                    (pg_market_form_values()['values']), 'errors'
 *                    (field => message, plus 'form'), 'currencies' and
 *                    'countries' (option lists), 'open' (bool)
 * @return string
 */
function pg_market_drawer_html(array $args)
{
    $id = (string) $args['id'];
    $market_id = (int) $args['market_id'];
    $values = $args['values'];
    $errors = $args['errors'];
    $title = ($market_id > 0) ? lang('Edit market') : lang('Add market');

    $field = function ($name, $label, $control, $help) use ($id, $errors) {
        $help_id = $id . '_' . $name . '_help';
        $note = isset($errors[$name])
            ? '<div class="pg-field-help pg-field-error" id="' . $help_id . '">' . h($errors[$name]) . '</div>'
            : (($help !== '') ? '<div class="pg-field-help" id="' . $help_id . '">' . h($help) . '</div>' : '');

        return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="' . $id . '_' . $name . '">' . h($label) . '</label>' . $control . $note . '</div>';
    };

    $invalid = function ($name) use ($id, $errors) {
        return ' aria-describedby="' . $id . '_' . $name . '_help"' . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
    };

    $currency_options = '<option value=""></option>';

    foreach ($args['currencies'] as $code => $label) {
        $currency_options .= '<option value="' . h($code) . '"' . (($code === $values['currency_code']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $chosen = array_map('intval', $values['countries']);
    $country_options = '';

    foreach ($args['countries'] as $country_id => $label) {
        $country_options .= '<option value="' . (int) $country_id . '"' . (in_array((int) $country_id, $chosen, true) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $rounding_options = '';

    foreach (pg_market_rounding_options() as $code => $label) {
        $rounding_options .= '<option value="' . h($code) . '"' . (($code === $values['rounding']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $delete = ($market_id > 0)
        ? '<button type="submit" name="submit_delete" value="' . $market_id . '" formnovalidate class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The market and its list of countries are deleted. Its visitors see the base currency again.')) . '">' . h(lang('Delete')) . '</button>'
        : '';

    return '
    <dialog class="drawer drawer-end" id="' . h($id) . '" aria-labelledby="' . h($id) . '_title" style="width:min(520px,100%)"' . (!empty($args['open']) ? ' data-pg-market-open="1"' : '') . '>
        <form method="post" action="view_markets.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . $market_id . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-w-0">
                    <h5 class="drawer-title mb-0" id="' . h($id) . '_title">' . h($title) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('Visitors from these countries see prices in its currency.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                ' . (isset($errors['form']) ? '<div class="pg-field-help pg-field-error mb-5" role="alert">' . h($errors['form']) . '</div>' : '') . '
                ' . $field('name', lang('Name'), '<input type="text" class="pg-input" id="' . h($id) . '_name" name="name" maxlength="' . PG_MARKET_NAME_MAX . '" required value="' . h($values['name']) . '"' . $invalid('name') . '>', lang('For you only, e.g. Europe or United States.')) . '
                ' . $field('countries', lang('Countries'), '<select class="pg-input" id="' . h($id) . '_countries" name="countries[]" multiple data-pg-combobox data-placeholder="' . h(lang('Choose countries')) . '"' . $invalid('countries') . '>' . $country_options . '</select>', lang('A country belongs to one market.')) . '
                ' . $field('currency_code', lang('Currency'), '<select class="pg-input" id="' . h($id) . '_currency_code" name="currency_code" required' . $invalid('currency_code') . '>' . $currency_options . '</select>', lang('Prices are shown and charged in it. Add a currency under Currencies.')) . '
                ' . $field('rounding', lang('Rounding'), '<select class="pg-input" id="' . h($id) . '_rounding" name="rounding"' . $invalid('rounding') . '>' . $rounding_options . '</select>', lang('How a price converted from the base currency is rounded.')) . '
                <div class="d-flex align-items-center gap-3"><input type="checkbox" class="pg-switch" id="' . h($id) . '_enabled" name="enabled" value="1"' . ($values['enabled'] ? ' checked' : '') . '><label class="pg-text-2" for="' . h($id) . '_enabled">' . h(lang('On: its visitors see its prices')) . '</label></div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">' . $delete . '<span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>
            </div>
        </form>
    </dialog>';
}
