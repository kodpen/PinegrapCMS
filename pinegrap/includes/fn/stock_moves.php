<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock per location, the operator's side: the locations edited in
 * Settings › Ecommerce, the stock history (view_stock_moves.php), the
 * transfer between two locations and the last moves on the product screen.
 *
 * Every write goes through the writer of includes/fn/stock_locations.php
 * (pg_stock_change()); this module plans, checks and reads. A transfer is
 * two changes in one transaction, so the product's total never moves.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/stock_locations.php');

/** Longest address kept on a location (the column is TEXT; the screen keeps it short). */
define('PG_STOCK_ADDRESS_LENGTH', 1000);

/** Moves the product screen shows. */
define('PG_STOCK_PRODUCT_MOVES', 10);

/**
 * The shown name of each move reason (pg_stock_reasons()).
 *
 * @return array reason => text
 */
function pg_stock_move_reason_labels()
{
    $labels = array(
        'panel'       => lang('Product screen'),
        'bulk_edit'   => lang('Bulk edit'),
        'import'      => lang('Import'),
        'checkout'    => lang('Order'),
        'local_sale'  => lang('Store sale'),
        'cancel'      => lang('Cancellation'),
        'return'      => lang('Return'),
        'barcode'     => lang('Barcode scan'),
        'api'         => lang('API'),
        'transfer'    => lang('Transfer'),
        'count'       => lang('Stock count'),
        'marketplace' => lang('Marketplace'),
        'erp'         => lang('Accounting'),
        'workspace'   => lang('Workspace'),
    );

    $out = array();

    foreach (pg_stock_reasons() as $reason) {
        $out[$reason] = isset($labels[$reason]) ? $labels[$reason] : $reason;
    }

    return $out;
}

/**
 * Who may open the stock history and make a transfer: the e-commerce
 * managers (validate_ecommerce_access()'s rule), on a site with e-commerce on.
 *
 * @param array $user
 * @return bool
 */
function pg_stock_moves_user_may($user)
{
    if (!is_array($user) || !isset($user['role']) || !is_numeric($user['role'])) {
        return false;
    }

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return false;
    }

    return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
}

/**
 * The history's filters, checked: unknown values are dropped, so nothing
 * from the request reaches the query unchecked.
 *
 * @param array $input        product_id, location_id, reason, from, to
 *                            (dates as YYYY-MM-DD, pg_date_input_iso())
 * @param array $location_ids the ids that exist
 * @return array product_id (int, 0 = all), location_id (int, 0 = all),
 *               reason ('' = all), from, to ('' or YYYY-MM-DD)
 */
function pg_stock_move_filters(array $input, array $location_ids)
{
    $location_ids = array_map('intval', $location_ids);

    $product_id = isset($input['product_id']) ? pg_stock_whole_number($input['product_id']) : null;
    $location_id = isset($input['location_id']) ? pg_stock_whole_number($input['location_id']) : null;
    $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : '';

    $filters = array(
        'product_id'  => (($product_id !== null) && ($product_id > 0)) ? $product_id : 0,
        'location_id' => (($location_id !== null) && in_array($location_id, $location_ids, true)) ? $location_id : 0,
        'reason'      => in_array($reason, pg_stock_reasons(), true) ? $reason : '',
        'from'        => '',
        'to'          => '',
    );

    foreach (array('from', 'to') as $key) {
        if (isset($input[$key]) && is_string($input[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$key])) {
            $parts = explode('-', $input[$key]);

            if (checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                $filters[$key] = $input[$key];
            }
        }
    }

    // A period given backwards is read the right way round.
    if (($filters['from'] !== '') && ($filters['to'] !== '') && ($filters['from'] > $filters['to'])) {
        list($filters['from'], $filters['to']) = array($filters['to'], $filters['from']);
    }

    return $filters;
}

/**
 * The WHERE condition of the filters, with ? placeholders. The period runs
 * from the start of 'from' to the end of 'to' in the PHP time zone (the
 * site's, set by init.php).
 *
 * @param array $filters pg_stock_move_filters()
 * @return array array('sql' => string, 'params' => list)
 */
function pg_stock_move_condition(array $filters)
{
    $sql = array();
    $params = array();

    if (!empty($filters['product_id'])) {
        $sql[] = 'stock_moves.product_id = ?';
        $params[] = (int) $filters['product_id'];
    }

    if (!empty($filters['location_id'])) {
        $sql[] = 'stock_moves.location_id = ?';
        $params[] = (int) $filters['location_id'];
    }

    if (!empty($filters['reason'])) {
        $sql[] = 'stock_moves.reason = ?';
        $params[] = (string) $filters['reason'];
    }

    if (!empty($filters['from'])) {
        $sql[] = 'stock_moves.created_at >= ?';
        $params[] = (int) strtotime($filters['from'] . ' 00:00:00');
    }

    if (!empty($filters['to'])) {
        $sql[] = 'stock_moves.created_at < ?';
        $params[] = (int) strtotime($filters['to'] . ' 00:00:00 +1 day');
    }

    return array('sql' => $sql ? implode(' AND ', $sql) : '1 = 1', 'params' => $params);
}

/**
 * A product named in a filter or a transfer: its SKU (products.name, the
 * whole value) or '#' and its id.
 *
 * @param mixed $text
 * @return array array('sku' => string) or array('id' => int); empty when blank
 */
function pg_stock_product_reference($text)
{
    $text = is_scalar($text) ? trim((string) $text) : '';

    if ($text === '') {
        return array();
    }

    if (preg_match('/^#([0-9]{1,10})$/', $text, $parts)) {
        return array('id' => (int) $parts[1]);
    }

    return array('sku' => $text);
}

/**
 * The product a reference names: id, name (SKU), short description and
 * inventory, or null when none matches.
 *
 * @param mixed $text
 * @return array|null
 */
function pg_stock_find_product($text)
{
    $reference = pg_stock_product_reference($text);

    if (!$reference) {
        return null;
    }

    $product = isset($reference['id'])
        ? db_item("SELECT id, name, short_description, inventory FROM products WHERE id = '" . (int) $reference['id'] . "'")
        : db_item("SELECT id, name, short_description, inventory FROM products WHERE name = '" . e($reference['sku']) . "' ORDER BY id LIMIT 1");

    return $product ? $product : null;
}

/**
 * What a transfer would do, checked without writing.
 *
 * @param mixed $from_id
 * @param mixed $to_id
 * @param mixed $quantity
 * @param array $locations pg_stock_locations()
 * @return array ok, error ('' | 'quantity' | 'same' | 'from' | 'to'
 *               | 'to_disabled'), from, to, quantity
 */
function pg_stock_transfer_check($from_id, $to_id, $quantity, array $locations)
{
    $from_id = pg_stock_whole_number($from_id);
    $to_id = pg_stock_whole_number($to_id);
    $quantity = pg_stock_clean_quantity($quantity);

    $result = array('ok' => false, 'error' => '', 'from' => (int) $from_id, 'to' => (int) $to_id, 'quantity' => (int) $quantity);

    $known = array();

    foreach ($locations as $location) {
        $known[(int) $location['id']] = ((int) $location['enabled'] === 1);
    }

    if (($quantity === null) || ($quantity < 1)) {
        $result['error'] = 'quantity';
    } elseif (($from_id === null) || !isset($known[$from_id])) {
        $result['error'] = 'from';
    } elseif (($to_id === null) || !isset($known[$to_id])) {
        $result['error'] = 'to';
    } elseif ($from_id === $to_id) {
        $result['error'] = 'same';
    } elseif (!$known[$to_id]) {
        $result['error'] = 'to_disabled';
    } else {
        $result['ok'] = true;
    }

    return $result;
}

/**
 * The reference both moves of one transfer carry, so the history shows
 * them as a pair.
 *
 * @param int $product_id
 * @param int $from_id
 * @param int $to_id
 * @param int $now
 * @return string at most PG_STOCK_REF_LENGTH characters
 */
function pg_stock_transfer_ref($product_id, $from_id, $to_id, $now)
{
    return substr('T' . (int) $now . '-' . (int) $product_id . '-' . (int) $from_id . '-' . (int) $to_id, 0, PG_STOCK_REF_LENGTH);
}

/**
 * Move $quantity units of a product from one location to another: two
 * pg_stock_change() calls in one transaction, so both rows change or
 * neither does. The product's total is the same afterwards, so nothing is
 * announced and no marketplace is queued (they read the total).
 *
 * @param int    $product_id
 * @param int    $from_id
 * @param int    $to_id
 * @param int    $quantity
 * @param string $note
 * @return array ok, error (pg_stock_transfer_check()'s, pg_stock_change()'s,
 *               'no_locations'), ref, from (the writer's answer), to
 */
function pg_stock_transfer($product_id, $from_id, $to_id, $quantity, $note = '')
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'ref' => '', 'from' => null, 'to' => null);
    };

    if (!pg_stock_has_locations()) {
        return $refuse('no_locations');
    }

    $check = pg_stock_transfer_check($from_id, $to_id, $quantity, pg_stock_locations());

    if (!$check['ok']) {
        return $refuse($check['error']);
    }

    $ref = pg_stock_transfer_ref($product_id, $check['from'], $check['to'], time());
    $options = array('transaction' => false, 'note' => (string) $note);

    db("START TRANSACTION");

    $taken = pg_stock_change($product_id, $check['from'], -$check['quantity'], 'transfer', $ref, $options);

    if (!$taken['ok']) {
        db("ROLLBACK");
        return $refuse($taken['error']);
    }

    $given = pg_stock_change($product_id, $check['to'], $check['quantity'], 'transfer', $ref, $options);

    if (!$given['ok']) {
        db("ROLLBACK");
        return $refuse($given['error']);
    }

    db("COMMIT");

    return array('ok' => true, 'error' => '', 'ref' => $ref, 'from' => $taken, 'to' => $given);
}

/**
 * The text a refused transfer shows.
 *
 * @param string $error
 * @return string
 */
function pg_stock_transfer_error_text($error)
{
    switch ($error) {
        case 'quantity':
            return lang('Enter a whole number of units, 1 or more.');
        case 'same':
            return lang('Choose two different locations.');
        case 'from':
        case 'to':
        case 'location':
            return lang('Choose a location from the list.');
        case 'to_disabled':
            return lang('The location the units go to is turned off.');
        case 'not_enough':
            return lang('The location the units come from does not hold that many.');
        case 'untracked':
            return lang('This product does not track its inventory.');
        case 'product':
            return lang('No product has this SKU.');
        case 'no_locations':
            return lang('Add a stock location first.');
    }

    return lang('The transfer could not be saved.');
}

/**
 * What a save of the Stock locations card changes, worked out without
 * writing. Locations are never deleted (their moves name them); one is
 * turned off instead. The default location stays on.
 *
 * @param array $posted    names => id => value: 'name', 'address', 'order',
 *                         'enabled' (ids that are on), 'default' (an id),
 *                         'new_name', 'new_address'
 * @param array $locations pg_stock_locations()
 * @return array errors (field => code: 'name' | 'default_disabled'),
 *               updates (id => name, address, enabled, sort_order),
 *               default (id or 0 when unchanged), add (null or name, address)
 */
function pg_stock_locations_save_plan(array $posted, array $locations)
{
    $plan = array('errors' => array(), 'updates' => array(), 'default' => 0, 'add' => null);

    $names = isset($posted['name']) && is_array($posted['name']) ? $posted['name'] : array();
    $addresses = isset($posted['address']) && is_array($posted['address']) ? $posted['address'] : array();
    $orders = isset($posted['order']) && is_array($posted['order']) ? $posted['order'] : array();
    $enabled = isset($posted['enabled']) && is_array($posted['enabled']) ? array_map('intval', array_keys($posted['enabled'])) : array();

    $current_default = pg_stock_pick_default($locations);
    $default = isset($posted['default']) ? pg_stock_whole_number($posted['default']) : null;
    $by_id = array();

    foreach ($locations as $location) {
        $by_id[(int) $location['id']] = $location;
    }

    if (($default === null) || !isset($by_id[$default])) {
        $default = $current_default;
    }

    foreach ($by_id as $id => $location) {
        // A row the form did not post is left as it is.
        if (!array_key_exists($id, $names)) {
            continue;
        }

        $name = is_scalar($names[$id]) ? trim((string) $names[$id]) : '';

        if (($name === '') || (mb_strlen($name) > PG_STOCK_NAME_LENGTH)) {
            $plan['errors']['stock_location_name[' . $id . ']'] = 'name';
            continue;
        }

        $address = isset($addresses[$id]) && is_scalar($addresses[$id]) ? mb_substr(trim((string) $addresses[$id]), 0, PG_STOCK_ADDRESS_LENGTH) : (string) $location['address'];
        $order = isset($orders[$id]) ? pg_stock_whole_number($orders[$id]) : null;
        $order = (($order !== null) && ($order >= 0) && ($order <= 65535)) ? $order : (int) $location['sort_order'];
        $on = in_array($id, $enabled, true) ? 1 : 0;

        if (($id === $default) && !$on) {
            $plan['errors']['stock_location_enabled[' . $id . ']'] = 'default_disabled';
            continue;
        }

        $row = array('name' => $name, 'address' => $address, 'enabled' => $on, 'sort_order' => $order);
        $was = array('name' => (string) $location['name'], 'address' => (string) $location['address'],
            'enabled' => (int) $location['enabled'], 'sort_order' => (int) $location['sort_order']);

        if ($row !== $was) {
            $plan['updates'][$id] = $row;
        }
    }

    if ($default !== $current_default) {
        $plan['default'] = $default;
    }

    $new_name = isset($posted['new_name']) && is_scalar($posted['new_name']) ? trim((string) $posted['new_name']) : '';

    if ($new_name !== '') {
        if (mb_strlen($new_name) > PG_STOCK_NAME_LENGTH) {
            $plan['errors']['stock_location_new_name'] = 'name';
        } else {
            $new_address = isset($posted['new_address']) && is_scalar($posted['new_address']) ? (string) $posted['new_address'] : '';
            $plan['add'] = array('name' => $new_name, 'address' => mb_substr(trim($new_address), 0, PG_STOCK_ADDRESS_LENGTH));
        }
    }

    return $plan;
}

/**
 * Write a plan of pg_stock_locations_save_plan(). Run only when it has no
 * errors.
 *
 * @param array $plan
 * @return bool false when a statement failed
 */
function pg_stock_locations_save(array $plan)
{
    $ok = true;
    $now = time();

    foreach ($plan['updates'] as $id => $row) {
        $ok = (bool) db_q(
            "UPDATE stock_locations SET name = ?, address = ?, enabled = ?, sort_order = ?, updated_at = ? WHERE id = ?",
            array($row['name'], $row['address'], $row['enabled'], $row['sort_order'], $now, (int) $id)) && $ok;
    }

    // One statement, so there is never a moment with two defaults or none.
    if ($plan['default']) {
        $ok = (bool) db_q(
            "UPDATE stock_locations SET is_default = IF(id = ?, 1, 0), updated_at = ? WHERE (is_default = 1) OR (id = ?)",
            array((int) $plan['default'], $now, (int) $plan['default'])) && $ok;
    }

    if ($plan['add'] !== null) {
        $added = pg_stock_location_add($plan['add']['name'], $plan['add']['address']);
        $ok = $added['ok'] && $ok;
    }

    pg_stock_has_locations(true);

    return $ok;
}

/**
 * The stock history as the list table driver's source
 * (includes/fn/list_table.php), for the screen and its rows action. It is
 * the screen's gate too (pg_stock_moves_user_may()).
 *
 * @param array $user
 * @param array $params 'filters' (pg_stock_move_filters() input)
 * @return array|null
 */
function pg_stock_move_list_source($user, $params)
{
    if (!pg_stock_moves_user_may($user) || !pg_stock_ready()) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $locations = pg_stock_locations();
    $filters = pg_stock_move_filters(
        (is_array($params) && isset($params['filters']) && is_array($params['filters'])) ? $params['filters'] : array(),
        array_column($locations, 'id'));
    $condition = pg_stock_move_condition($filters);

    $columns = array(
        'created' => array('heading' => lang('Date'), 'width' => '11rem', 'sort' => 'stock_moves.created_at'),
        'product' => array('heading' => lang('Product'), 'width' => '16rem', 'sort' => 'products.name'),
        'location' => array('heading' => lang('Location'), 'width' => '10rem', 'sort' => 'stock_locations.name'),
        'reason' => array('heading' => lang('Reason'), 'width' => '9rem'),
        'delta' => array('heading' => lang('Change'), 'width' => '6rem', 'kind' => 'number', 'sort' => 'stock_moves.delta'),
        'after' => array('heading' => lang('Quantity after'), 'width' => '7rem', 'kind' => 'number'),
        'ref' => array('heading' => lang('Reference'), 'width' => '10rem'),
        'note' => array('heading' => lang('Note'), 'width' => '14rem'),
        'who' => array('heading' => lang('User'), 'width' => '8rem'),
    );

    $state = pg_list_state('view_stock_moves', pg_list_table_sort_columns(pg_list_table_columns($columns)), array('sort' => 'created', 'order' => 'desc'));
    $labels = pg_stock_move_reason_labels();
    $names = array();

    foreach ($locations as $location) {
        $names[(int) $location['id']] = (string) $location['name'];
    }

    return pg_list_table_source(array(
        'list' => 'view_stock_moves',
        'columns' => $columns,
        'from' => 'stock_moves
            LEFT JOIN products ON products.id = stock_moves.product_id
            LEFT JOIN stock_locations ON stock_locations.id = stock_moves.location_id
            LEFT JOIN user ON user.user_id = stock_moves.user_id',
        'where' => $condition['sql'],
        'params' => $condition['params'],
        'id' => 'stock_moves.id',
        'id_order' => 'desc',
        'select' => 'stock_moves.product_id, stock_moves.location_id, stock_moves.delta, stock_moves.quantity_after,
            stock_moves.reason, stock_moves.ref, stock_moves.note, stock_moves.created_at,
            products.name AS product_name, products.short_description AS product_title, user.user_username AS username',
        'search' => array(
            'columns' => array('products.name', 'products.short_description', 'stock_locations.name', 'stock_moves.ref', 'stock_moves.note', 'user.user_username'),
            'labels' => array(array('expr' => 'stock_moves.reason', 'labels' => $labels)),
        ),
        'levels' => $state['sorts'],
        'created' => 'stock_moves.created_at',
        'rows' => function ($records) use ($labels, $names) {
            $rows = array();

            foreach ($records as $record) {
                $rows[] = pg_stock_move_row($record, $labels, $names);
            }

            return $rows;
        },
        'params_out' => array('filters' => $filters),
    ));
}

/**
 * One line of the history.
 *
 * @param array $record a record of pg_stock_move_list_source()'s read
 * @param array $labels pg_stock_move_reason_labels()
 * @param array $names  location id => name
 * @return array array('id', 'class', 'cells')
 */
function pg_stock_move_row(array $record, array $labels, array $names)
{
    $delta = (int) $record['delta'];
    $product = ((string) $record['product_name'] !== '')
        ? '<a class="pg-link-body-emphasis text-decoration-none" href="edit_product.php?id=' . (int) $record['product_id'] . '">' . h($record['product_name']) . '</a>'
            . (((string) $record['product_title'] !== '') ? '<div class="small fg-secondary">' . h(strip_tags((string) $record['product_title'])) . '</div>' : '')
        : '<span class="fg-secondary">#' . (int) $record['product_id'] . '</span>';
    $location_id = (int) $record['location_id'];

    return array(
        'id' => (int) $record['pg_lt_id'],
        'class' => '',
        'cells' => array(
            'created' => pg_list_cell(get_absolute_time(array('timestamp' => (int) $record['created_at']))),
            'product' => pg_list_cell($product),
            'location' => pg_list_cell(isset($names[$location_id]) ? h($names[$location_id]) : '#' . $location_id),
            'reason' => pg_list_cell(h(isset($labels[$record['reason']]) ? $labels[$record['reason']] : (string) $record['reason'])),
            'delta' => pg_list_cell('<span class="' . (($delta < 0) ? 'fg-danger' : 'fg-success') . '">' . (($delta > 0) ? '+' : '') . $delta . '</span>'),
            'after' => pg_list_cell((string) (int) $record['quantity_after']),
            'ref' => pg_list_cell(h($record['ref'])),
            'note' => pg_list_cell(h($record['note'])),
            'who' => pg_list_cell(h((string) $record['username'])),
        ),
    );
}

/**
 * The product screen's card of its last moves: '' when the site keeps no
 * locations or the product tracks no inventory.
 *
 * @param int $product_id
 * @return string HTML
 */
function pg_stock_product_moves_card($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !pg_stock_has_locations()) {
        return '';
    }

    $product = db_item("SELECT name, inventory FROM products WHERE id = '" . $product_id . "'");

    if (!$product || ((int) $product['inventory'] !== 1)) {
        return '';
    }

    $names = array();

    foreach (pg_stock_locations() as $location) {
        $names[(int) $location['id']] = (string) $location['name'];
    }

    $labels = pg_stock_move_reason_labels();
    $rows = '';
    $stock = '';

    foreach (pg_stock_rows($product_id) as $location_id => $quantity) {
        $stock .= '<li class="d-flex justify-content-between"><span>' . h(isset($names[$location_id]) ? $names[$location_id] : '#' . $location_id) . '</span><span>' . (int) $quantity . '</span></li>';
    }

    $moves = db_items(
        "SELECT location_id, delta, reason, created_at FROM stock_moves
        WHERE product_id = '" . $product_id . "'
        ORDER BY created_at DESC, id DESC
        LIMIT " . PG_STOCK_PRODUCT_MOVES);

    foreach ($moves as $move) {
        $delta = (int) $move['delta'];
        $rows .= '<tr>
            <td>' . get_relative_time(array('timestamp' => (int) $move['created_at'])) . '</td>
            <td>' . h(isset($names[(int) $move['location_id']]) ? $names[(int) $move['location_id']] : '#' . (int) $move['location_id']) . '<div class="small fg-secondary">' . h(isset($labels[$move['reason']]) ? $labels[$move['reason']] : $move['reason']) . '</div></td>
            <td class="text-end ' . (($delta < 0) ? 'fg-danger' : 'fg-success') . '">' . (($delta > 0) ? '+' : '') . $delta . '</td>
        </tr>';
    }

    return '
                    <div class="card mb-5">
                        <div class="card-header bg-reset border-0 text-uppercase h6 fg-primary fw-bold">' . lang('Stock by location') . '</div>
                        <div class="card-body pt-0">
                            ' . (($stock !== '') ? '<ul class="list-unstyled mb-3">' . $stock . '</ul>' : '') . '
                            ' . (($rows !== '')
                                ? '<table class="table table-sm mb-3"><caption class="visually-hidden">' . lang('Last stock moves') . '</caption><thead><tr><th scope="col">' . lang('Date') . '</th><th scope="col">' . lang('Location') . '</th><th scope="col" class="text-end">' . lang('Change') . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                                : '<p class="form-text">' . lang('No stock moves yet.') . '</p>') . '
                            <a class="btn btn-sm btn-text theme-secondary" href="view_stock_moves.php?product=' . h(urlencode((string) $product['name'])) . '"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>' . lang('Stock history') . '</a>
                        </div>
                    </div>';
}
