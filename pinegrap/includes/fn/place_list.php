<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Map of places" block's data and script. The block (data-pg-map) is
 * markup only; a page holding it brings assets/js/pg_map.js and the page's
 * id (pg_place_list_inject()), and the script asks places.php for the records
 * the page's form list shows a visitor (pg_place_list_for_page()).
 *
 * The request carries the page id only. The fields come from the saved
 * page, never from the request: the ones the block's panel chose
 * (data-pg-map-fields, pg_place_list_saved_choice()), each part not chosen
 * by the server's name rule (pg_place_list_field_roles()): the coordinates
 * and the address of place_map.php, the record's name, and hours and phone
 * by name; an office-use-only field never leaves.
 *
 * Loaded on demand, by get_page_content.php for a page holding the block
 * and by places.php. (includes/fn/places.php is another module: the
 * country place lists of the address forms.)
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
 * The most places one answer carries. A longer list is cut to its first
 * ones in its own order; narrowing is the list's own filter.
 */
define('PG_PLACE_LIST_MAX', 500);

/**
 * Answers one address may ask for per window.
 */
define('PG_PLACE_LIST_RATE_LIMIT', 30);
define('PG_PLACE_LIST_RATE_WINDOW', 60);

/**
 * Typed-address lookups one address may ask for per window (X12), and the
 * longest text one carries. Each asks Photon once, so the bound is lower
 * than the list's.
 */
define('PG_PLACE_LIST_FIND_LIMIT', 10);
define('PG_PLACE_LIST_FIND_MAX', 200);

/**
 * The block's mark as an attribute of its own (not data-pg-map-page): the
 * Preview's row in _SD_PAGE_SCRIPTS uses the same expression.
 *
 * @return string
 */
function pg_place_list_pattern()
{
    return '/\sdata-pg-map(?=[\s=>\/])/i';
}

/**
 * Whether a page's HTML holds the block.
 *
 * @param string $content
 * @return bool
 */
function pg_place_list_on_page($content)
{
    return stripos((string) $content, 'data-pg-map') !== false
        && (bool) preg_match(pg_place_list_pattern(), (string) $content);
}

/**
 * The script's texts in the page's language, by their English key;
 * pg_map.js falls back to the key.
 *
 * @return array
 */
function pg_place_list_labels()
{
    return array(
        'No place matches your search.' => lang('No place matches your search.'),
        'No places to show yet.' => lang('No places to show yet.'),
        'Directions' => lang('Directions'),
        'Details' => lang('Details'),
        'The places could not be loaded.' => lang('The places could not be loaded.'),
        'Show the map' => lang('Show the map'),
        'The map shows once you allow marketing in the cookie settings.' => lang('The map shows once you allow marketing in the cookie settings.'),
        'Map of places' => lang('Map of places'),
        'Show on the map' => lang('Show on the map'),
        'Near me' => lang('Near me'),
        'Your browser did not give a position.' => lang('Your browser did not give a position.'),
        'Nearest first from your position.' => lang('Nearest first from your position.'),
        'Find near an address' => lang('Find near an address'),
        'Address or postcode' => lang('Address or postcode'),
        'Find' => lang('Find'),
        'Nearest first from: {address}' => lang('Nearest first from: {address}'),
        'The address was not found.' => lang('The address was not found.'),
        'The address service asks to wait. Try again in a minute.' => lang('The address service asks to wait. Try again in a minute.'),
        'The address could not be looked up.' => lang('The address could not be looked up.'),
        '{km} km' => lang('{km} km'),
    );
}

/**
 * The script, its texts and the page's id before the page's last </body>,
 * once, for a page holding the block. Asked once every shared component and
 * system widget is drawn in.
 *
 * @param string      $content page HTML
 * @param int         $page_id
 * @param string|null $src     the script's address; null builds it
 * @param array|null  $map     the map's part (pg_place_tiles_page()); null reads it
 * @return string
 */
function pg_place_list_inject($content, $page_id, $src = null, $map = null)
{
    if (!pg_place_list_on_page($content) || (strpos($content, '/assets/js/pg_map.js') !== false)) {
        return $content;
    }

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_map.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_map.js');
    }

    // The map: the "Maps" switch, the cookie notice and the tiles
    // (includes/fn/place_tiles.php); the same for every visitor.
    if ($map === null) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_tiles.php');
        $map = pg_place_tiles_page();
    }

    $endpoint = (defined('OUTPUT_PATH') ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '') . '/places.php';
    // JSON_HEX_TAG: a credit line or a text holding "</script>" stays inside.
    $file = '<script>window.pgMap = ' . json_encode(array('page' => (int) $page_id, 'endpoint' => $endpoint, 'labels' => pg_place_list_labels(), 'map' => $map, 'find' => pg_place_list_find_on()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>' . "\n"
          . '<script src="' . h($src) . '" defer></script>' . "\n";

    $body = strripos($content, '</body>');

    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}

/**
 * The names the record's name, hours and phone are found by, after
 * pg_place_map_name().
 *
 * @return array name, hours, phone => list of names
 */
function pg_place_list_names()
{
    return array(
        'name'  => array('name', 'title', 'place', 'branch', 'store', 'location', 'ad', 'isim', 'baslik', 'sube', 'magaza'),
        'hours' => array('hours', 'opening_hours', 'open_hours', 'saatler', 'calisma_saatleri', 'acilis_saatleri'),
        'phone' => array('phone', 'telephone', 'tel', 'phone_number', 'telefon'),
    );
}

/**
 * The attribute of the block (data-pg-map) that keeps the fields its panel
 * chose: a JSON object, part => field name.
 *
 * @return string
 */
function pg_place_list_choice_attr()
{
    return 'data-pg-map-fields';
}

/**
 * The parts a block may choose a field for.
 *
 * @return array
 */
function pg_place_list_parts()
{
    return array('name', 'address', 'hours', 'phone', 'lat', 'lng');
}

/**
 * The fields the first "Map of places" block of a saved page tree chose,
 * part => field name; an empty array for none, a broken value or no block.
 * Read from the page's own tree only: a block inside a shared component
 * keeps the name rule. Pure.
 *
 * @param mixed $tree the decoded page tree
 * @return array
 */
function pg_place_list_saved_choice($tree)
{
    $found = null;
    $walk = function ($node) use (&$walk, &$found) {
        if (($found !== null) || !is_array($node)) {
            return;
        }

        $attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();
        $block = false;
        $value = '';

        foreach ($attrs as $attr) {
            $name = (is_array($attr) && isset($attr['name'])) ? (string) $attr['name'] : '';

            if ($name === 'data-pg-map') {
                $block = true;
            } elseif ($name === pg_place_list_choice_attr()) {
                $value = isset($attr['value']) ? (string) $attr['value'] : '';
            }
        }

        if ($block) {
            $found = $value;
            return;
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $walk($child);
            }
        }
    };
    $walk($tree);

    $saved = ($found !== null && $found !== '') ? json_decode($found, true) : null;
    $choice = array();

    foreach (pg_place_list_parts() as $part) {
        if (is_array($saved) && isset($saved[$part]) && is_string($saved[$part]) && (trim($saved[$part]) !== '')) {
            $choice[$part] = trim($saved[$part]);
        }
    }

    return $choice;
}

/**
 * Whether a field may hold a part: a coordinate a number field, the others
 * a plain text box or text area (or a number). Pure.
 *
 * @param array  $field id, name, type, kind
 * @param string $part
 * @return bool
 */
function pg_place_list_field_fits($field, $part)
{
    $kind = isset($field['kind']) ? (string) $field['kind'] : '';
    $type = isset($field['type']) ? (string) $field['type'] : '';

    if (in_array($part, array('lat', 'lng'), true)) {
        return $kind === 'number';
    }

    return in_array($kind, array('', 'number'), true) && in_array($type, array('text box', 'text area'), true);
}

/**
 * The fields a block chose that the form still has: part => field id. A
 * part is taken only when a field of that exact name is there, fits the
 * part and is not office-use-only; the others are left out, for the name
 * rule to find. Pure.
 *
 * @param array $fields rows in form order: id, name, type, kind, office
 * @param array $choice pg_place_list_saved_choice(): part => field name
 * @return array
 */
function pg_place_list_chosen_ids($fields, $choice)
{
    $chosen = array();

    foreach ((array) $choice as $part => $wanted) {
        if (!in_array($part, pg_place_list_parts(), true) || !is_string($wanted)) {
            continue;
        }

        foreach ((array) $fields as $field) {
            $id = isset($field['id']) ? (int) $field['id'] : 0;

            if (($id > 0) && empty($field['office']) && isset($field['name']) && ((string) $field['name'] === $wanted) && pg_place_list_field_fits($field, $part)) {
                $chosen[$part] = $id;
                break;
            }
        }
    }

    return $chosen;
}

/**
 * Which fields an answer carries. A part the block chose ($choice, from the
 * saved page) takes that field when the collection has it, it fits the part
 * and it is not office-use-only; every other part is found by the server's
 * name rule: the place fields of place_map.php and the name, hours and
 * phone by name. Office-use-only fields are never taken. Pure.
 *
 * @param array $fields rows in form order: id, name, type, kind, office
 * @param array $choice pg_place_list_saved_choice(): part => field name
 * @return array|null lat, lng, address (ids), name, hours, phone (id or 0);
 *                    null without the two coordinates and an address
 */
function pg_place_list_field_roles($fields, $choice = array())
{
    $public = array();

    foreach ((array) $fields as $field) {
        if (empty($field['office'])) {
            $public[] = $field;
        }
    }

    $chosen = pg_place_list_chosen_ids($public, $choice);
    $roles = pg_place_map_field_roles_found($public);

    foreach (array('lat', 'lng') as $part) {
        if (isset($chosen[$part])) {
            $roles[$part] = $chosen[$part];
        }
    }

    if (isset($chosen['address'])) {
        $roles['address'] = array($chosen['address']);
    }

    if (($roles['lat'] <= 0) || ($roles['lng'] <= 0) || !$roles['address']) {
        return null;
    }

    $names = pg_place_list_names();
    $roles += array('name' => 0, 'hours' => 0, 'phone' => 0);

    foreach (array('name', 'hours', 'phone') as $part) {
        if (isset($chosen[$part])) {
            $roles[$part] = $chosen[$part];
        }
    }

    foreach ($public as $field) {
        $name = pg_place_map_name(isset($field['name']) ? $field['name'] : '');
        $kind = isset($field['kind']) ? (string) $field['kind'] : '';
        $id = isset($field['id']) ? (int) $field['id'] : 0;

        if (($id <= 0) || ($kind !== '') || !in_array(isset($field['type']) ? $field['type'] : '', array('text box', 'text area'), true)) {
            continue;
        }

        foreach (array('name', 'hours', 'phone') as $part) {
            if (($roles[$part] === 0) && in_array($name, $names[$part], true) && !in_array($id, $roles['address'], true)) {
                $roles[$part] = $id;
            }
        }
    }

    return $roles;
}

/**
 * Why a page gives no places, '' when it may. Pure.
 *
 * @param bool  $page_found
 * @param string $access    get_access_control_type() of its folder
 * @param bool  $binned     its folder is in the Recycle Bin
 * @param array|null $list  pg_sw_page_widget($page_id, 'form_list_view')
 * @param bool  $public     pg_sw_form_widget_public() of the list
 * @return string
 */
function pg_place_list_page_reason($page_found, $access, $binned, $list, $public)
{
    if (!$page_found || $binned || ($access !== 'public')) {
        return 'page';
    }

    if (!is_array($list)) {
        return 'list';
    }

    return $public ? '' : 'page';
}

/**
 * The note a designer is shown for an empty answer or a cut list.
 *
 * @param string $reason page, list, fields, cut
 * @return string
 */
function pg_place_list_reason_text($reason)
{
    switch ($reason) {
        case 'list':
            return lang('Map of places: this page has no form list. Add a form list of a collection to the page.');
        case 'fields':
            return lang('Map of places: the list\'s collection needs two number fields named latitude and longitude and an address field.');
        case 'cut':
            return lang(array('string' => 'Map of places: the list has more than {var:1} records, so only the first {var:1} are shown and "Near me" is not offered. Narrow the list with its filters.', 'vars' => array((string) PG_PLACE_LIST_MAX)));
        case 'page':
            return lang('Map of places: the page is not open to everyone, so it shows no places.');
    }

    return '';
}

/**
 * The list's order as the list widget gives it: the record's id or date, or
 * a field of the form (pg_sw_form_list_field_order()), the record's id last.
 * A statistic order or an unknown field falls back to the date.
 *
 * @param int   $form_page_id
 * @param array $cfg the list widget's settings
 * @return array sql, join
 */
function pg_place_list_order($form_page_id, $cfg)
{
    $field = isset($cfg['order_by_field']) ? (string) $cfg['order_by_field'] : 'submitted_date_and_time';
    $direction = (isset($cfg['order_by_direction']) && (strtoupper((string) $cfg['order_by_direction']) === 'ASC')) ? 'ASC' : 'DESC';
    $order = array('sql' => 'forms.submitted_timestamp ' . $direction, 'join' => '');

    if ($field === 'submitted_form_id') {
        $order['sql'] = 'forms.id ' . $direction;
    } elseif (($field !== '') && ($field !== 'submitted_date_and_time')) {
        $row = db_item("SELECT id, type, multiple, collection_kind FROM form_fields WHERE page_id = '" . (int) $form_page_id . "' AND name = '" . e($field) . "' LIMIT 1");

        if ($row) {
            $order = pg_sw_form_list_field_order('sort_data', (int) $row['id'], (string) $row['type'], $row['multiple'], (string) $row['collection_kind'], $direction);
        }
    }

    $order['sql'] .= ', forms.id ' . $direction;

    return $order;
}

/**
 * The query of the records an answer may carry: complete ones, under the
 * list's and its detail page's filters, in the list's order, one more than
 * the bound so a cut is seen. Pure.
 *
 * @param int   $form_page_id
 * @param array $clause pg_sw_list_record_filter_clause(): join, where
 * @param array $order  pg_place_list_order()
 * @return string
 */
function pg_place_list_records_sql($form_page_id, $clause, $order)
{
    return "SELECT forms.id, forms.reference_code, forms.address_name
        FROM forms
        " . $clause['join'] . ' ' . $order['join'] . "
        WHERE forms.page_id = '" . (int) $form_page_id . "'
          AND forms.complete = 1
          " . $clause['where'] . "
        ORDER BY " . $order['sql'] . "
        LIMIT " . (PG_PLACE_LIST_MAX + 1);
}

/**
 * A stored coordinate as a number, null when it is none or out of range.
 * A number field's text written by an import or the API is kept as sent, so
 * a decimal comma ("40,98") reads as the record's form would save it
 * (pg_coll_number_value()). Pure.
 *
 * @param string $text
 * @param int    $bound 90 for a latitude, 180 for a longitude
 * @return float|null
 */
function pg_place_list_coordinate($text, $bound)
{
    $text = str_replace(',', '.', trim((string) $text));

    if (!preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/D', $text)) {
        return null;
    }

    $value = (float) $text;

    return (abs($value) <= $bound) ? $value : null;
}

/**
 * One place as the answer carries it. Pure.
 *
 * @param array  $record id, reference_code, address_name
 * @param array  $values field id => value
 * @param array  $roles  pg_place_list_field_roles()
 * @param string $url    the record's address ('' without a detail page)
 * @return array
 */
function pg_place_list_item($record, $values, $roles, $url)
{
    $value = function ($id) use ($values) {
        return ($id > 0 && isset($values[$id])) ? trim((string) $values[$id]) : '';
    };

    $lat = pg_place_list_coordinate($value($roles['lat']), 90);
    $lng = pg_place_list_coordinate($value($roles['lng']), 180);
    $point = ($lat !== null) && ($lng !== null);

    return array(
        'name'    => $value($roles['name']),
        'address' => pg_place_map_address_text($values, $roles['address']),
        'hours'   => $value($roles['hours']),
        'phone'   => $value($roles['phone']),
        'lat'     => $point ? $lat : null,
        'lng'     => $point ? $lng : null,
        'url'     => $url,
    );
}

/**
 * The places a page's form list shows a visitor.
 *
 * @param int $page_id
 * @return array places (list), cut (bool), reason ('' or a pg_place_list_reason_text() key)
 */
function pg_place_list_for_page($page_id)
{
    $empty = array('places' => array(), 'cut' => false, 'reason' => '');
    $page = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '" . (int) $page_id . "'");
    $folder = $page ? (int) $page['page_folder'] : 0;
    $list = $page ? pg_sw_page_widget((int) $page_id, 'form_list_view') : null;
    $reason = pg_place_list_page_reason((bool) $page, $page ? (string) get_access_control_type($folder) : '', $page ? pg_folder_binned($folder) : false, $list, is_array($list) && pg_sw_form_widget_public($list['cfg']));

    if ($reason !== '') {
        return array('reason' => $reason) + $empty;
    }

    $cfg = $list['cfg'];
    $form_page_id = pg_sw_form_widget_form_page_id($cfg);
    $fields = array();

    if (pg_coll_ready()) {
        foreach ((array) db_items("SELECT id, name, type, collection_kind, office_use_only FROM form_fields WHERE page_id = '" . (int) $form_page_id . "' ORDER BY sort_order") as $row) {
            $fields[] = array('id' => $row['id'], 'name' => $row['name'], 'type' => $row['type'], 'kind' => pg_coll_field_kind($row['collection_kind'], $row['type']), 'office' => (int) $row['office_use_only']);
        }
    }

    $roles = pg_place_list_field_roles($fields, pg_place_list_saved_choice(json_decode((string) pg_page_tree_json((int) $page_id), true)));

    if ($roles === null) {
        return array('reason' => 'fields') + $empty;
    }

    $detail_page_id = isset($cfg['detail_page_id']) ? (int) $cfg['detail_page_id'] : 0;
    $detail_view = ($detail_page_id > 0) ? pg_sw_page_widget($detail_page_id, 'form_item_view', function ($c) use ($form_page_id) {
        return pg_sw_form_widget_form_page_id($c) === $form_page_id;
    }) : null;
    $clause = pg_sw_list_record_filter_clause($form_page_id, $cfg, $detail_view ? $detail_view['cfg'] : array(), $detail_page_id, null);

    if (!empty($clause['closed'])) {
        return $empty;
    }

    $records = (array) db_items(pg_place_list_records_sql($form_page_id, $clause, pg_place_list_order($form_page_id, $cfg)));
    $cut = count($records) > PG_PLACE_LIST_MAX;
    $records = array_slice($records, 0, PG_PLACE_LIST_MAX);
    $target = pg_sw_list_detail_target($detail_page_id, $form_page_id);
    $ids = array_merge($roles['address'], array($roles['lat'], $roles['lng'], $roles['name'], $roles['hours'], $roles['phone']));
    $places = array();

    foreach ($records as $record) {
        $url = ($target['page_name'] !== '') ? pg_sw_record_url($target['page_name'], $record['reference_code'], $record['address_name'], $target['pretty']) : '';
        $places[] = pg_place_list_item($record, pg_place_map_record_values($record['id'], array_filter($ids)), $roles, $url);
    }

    return array('places' => $places, 'cut' => $cut, 'reason' => $cut ? 'cut' : '');
}

/**
 * Whether one address has asked too often in the window, with the
 * firewall's counters.
 *
 * @param string|null   $ip     the client address; null asks the firewall
 * @param callable|null $record waf_rate_record()'s shape; null uses it
 * @return bool
 */
function pg_place_list_throttled($ip = null, $record = null)
{
    return pg_place_list_rate('place_list', PG_PLACE_LIST_RATE_LIMIT, 'rate-place-list', $ip, $record);
}

/**
 * Whether one address has looked up too many typed addresses in the window.
 *
 * @param string|null   $ip     the client address; null asks the firewall
 * @param callable|null $record waf_rate_record()'s shape; null uses it
 * @return bool
 */
function pg_place_list_find_throttled($ip = null, $record = null)
{
    return pg_place_list_rate('place_find', PG_PLACE_LIST_FIND_LIMIT, 'rate-place-find', $ip, $record);
}

/**
 * Counts one request of a scope against the client address; true once the
 * limit is passed. The log line names the rule and the address only, never
 * what was asked.
 *
 * @param string        $scope
 * @param int           $limit  requests per PG_PLACE_LIST_RATE_WINDOW
 * @param string        $rule   the firewall log's rule name
 * @param string|null   $ip     the client address; null asks the firewall
 * @param callable|null $record waf_rate_record()'s shape; null uses it
 * @return bool
 */
function pg_place_list_rate($scope, $limit, $rule, $ip = null, $record = null)
{
    if (!function_exists('waf_rate_record') || !function_exists('waf_ip_subject') || (($ip === null) && !function_exists('waf_client_ip'))) {
        return false;
    }

    $ip = (string) (($ip === null) ? waf_client_ip() : $ip);

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip))) {
        return false;
    }

    $record = is_callable($record) ? $record : 'waf_rate_record';
    $exceeded = ((int) call_user_func($record, waf_ip_subject($ip), $scope, PG_PLACE_LIST_RATE_WINDOW) > $limit);

    if ($record !== 'waf_rate_record') {
        return $exceeded;
    }

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 100) === 1)) {
        waf_rate_sweep();
    }

    if ($exceeded && function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => $rule));
    }

    return $exceeded;
}

/**
 * Whether a typed address can be looked up (X12): only through Photon, as
 * "Find from the address" (place_map.php), which needs cURL; without it the
 * page draws no address box.
 *
 * @param bool|null $curl whether cURL is there; null asks
 * @return bool
 */
function pg_place_list_find_on($curl = null)
{
    if ($curl === null) {
        $curl = function_exists('curl_init');
    }

    return $curl && is_file(PG_FUNCTIONS_DIR . '/includes/geo/geocode/photon.php');
}

/**
 * The typed text as it is asked: one line, at most PG_PLACE_LIST_FIND_MAX
 * characters. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_place_list_find_text($text)
{
    if (!is_string($text)) {
        return '';
    }

    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return function_exists('mb_substr') ? mb_substr($text, 0, PG_PLACE_LIST_FIND_MAX, 'UTF-8') : substr($text, 0, PG_PLACE_LIST_FIND_MAX);
}

/**
 * What a lookup's answer gives the page: its state and, when found, the
 * point as numbers. Nothing of the typed text comes back or is kept. Pure.
 *
 * @param array $result pg_place_map_lookup()
 * @return array ok, state (found, not_found, empty, wait, error), lat, lng
 */
function pg_place_list_find_answer($result)
{
    $state = (is_array($result) && isset($result['state'])) ? (string) $result['state'] : 'error';

    if (!in_array($state, array('found', 'not_found', 'empty', 'wait', 'error'), true)) {
        $state = 'error';
    }

    $found = ($state === 'found') && is_numeric($result['lat']) && is_numeric($result['lng']);

    return array(
        'ok'    => true,
        'state' => ($state === 'found' && !$found) ? 'error' : $state,
        'lat'   => $found ? (float) $result['lat'] : null,
        'lng'   => $found ? (float) $result['lng'] : null,
    );
}

/**
 * Looks up one typed address, once, through Photon (the site's server while
 * its account is on, else the public instance). Called by places.php when a
 * visitor pressed Find. The text is neither stored nor logged: it goes to
 * Photon and is dropped with the request.
 *
 * @param mixed $text
 * @return array pg_place_list_find_answer()
 */
function pg_place_list_find($text)
{
    $text = pg_place_list_find_text($text);

    if ($text === '') {
        return pg_place_list_find_answer(array('state' => 'empty'));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_map.php');

    return pg_place_list_find_answer(pg_place_map_lookup($text));
}
