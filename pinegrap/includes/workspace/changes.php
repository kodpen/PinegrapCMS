<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - record changes Claude proposes.
 *
 * Asked to change, add or delete a record - a product, its stock, an order, a
 * customer, an ERP account, a product group and the products in it, a
 * channel's summary, a person's role, a page's details, a file, an offer, a
 * calendar event - Claude does not write it: it answers with the change
 * spelled out (the fields, what they hold now and what they would hold), and
 * the change waits under the answer in ws_ai_changes. Only the person who
 * asked can apply it, and only with a right of their own to change that kind
 * of record: Claude is never the way somebody without that right edits it.
 *
 * Applied, the change is written the way the panel and the external API
 * write the same record (the same columns, clean-ups, events and log lines),
 * in the name of the person who applied it, and a decision goes to the
 * channel under the answer. That decision is locked: nobody can change its
 * text, and people with the user role can neither delete it nor take the
 * decision mark off it, so the record of who changed what stays where the
 * conversation was. A product or a product group that is deleted goes to the
 * catalogue's Recycle Bin, where it can be restored, as in the file manager;
 * a page or a file goes to the file manager's Recycle Bin (and is not deleted
 * on a site without one); a contact, an offer, a calendar event, or a
 * catalogue on a site without its bin, is deleted for good, as in the panel. Either way the fields the record held are kept with the
 * proposal and written into the decision.
 *
 * A record that changed after the proposal was made is not overwritten: the
 * values the proposal was made against are compared with what the record
 * holds when somebody applies it, and a difference sets the proposal aside as
 * out of date.
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
 * The most changes one answer may carry.
 */
define('WS_CHANGES_PER_ANSWER', 10);

/**
 * The most products one change may put into a group or take out of it.
 */
define('WS_CHANGES_LIST_MAX', 100);

/**
 * Are the table and its columns there (2026.4.4, 4.87)?
 *
 * @return bool
 */
function ws_changes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_ai_changes', 'status')
            && waf_table_has_column('ws_ai_changes', 'action')
            && waf_table_has_column('ws_messages', 'locked');
    }

    return $ready;
}

/**
 * What can be proposed, and how. Per kind of record: the viewer right that
 * allows it, the tag it is shown with, the table it lives in, the actions it
 * takes and, per field, [how the value is read, its limit, its label, its
 * column]. "create" holds the fields only a new record takes (no column to
 * compare against), "required" what a new record cannot do without (one of
 * them, for a contact), "label" the fields that name a record.
 *
 * Kinds of value: text (one line), long (many lines), money (minor units),
 * bool, int (with a range), enum (with its values), email, ids (a list of
 * record ids of the kind named), role (a user role by name, of the ones
 * listed), date (YYYY-MM-DD), datetime (YYYY-MM-DD HH:MM), keywords (a list
 * of words kept comma-separated). "key" is the table's id column when it is
 * not id.
 *
 * @return array
 */
function ws_change_types()
{
    static $types = null;

    if ($types !== null) {
        return $types;
    }

    $types = array(
        'product' => array(
            'label'    => lang('Product'),
            'right'    => 'ecommerce',
            'tag'      => 'product',
            'table'    => 'products',
            'actions'  => array('update', 'create', 'delete'),
            'required' => array('name'),
            'names'    => array('short_description', 'name'),
            'fields'   => array(
                'name'              => array('text', 255, lang('Name'), 'name'),
                'title'             => array('text', 255, lang('Title'), 'title'),
                'short_description' => array('long', 2000, lang('Short description'), 'short_description'),
                'full_description'  => array('long', 60000, lang('Full description'), 'full_description'),
                'meta_description'  => array('text', 500, lang('Meta description'), 'meta_description'),
                'meta_keywords'     => array('text', 500, lang('Meta keywords'), 'meta_keywords'),
                'keywords'          => array('text', 500, lang('Search keywords'), 'keywords'),
                'brand'             => array('text', 190, lang('Brand'), 'brand'),
                'gtin'              => array('text', 100, lang('GTIN'), 'gtin'),
                'mpn'               => array('text', 100, lang('MPN'), 'mpn'),
                'price'             => array('money', 0, lang('Price'), 'price'),
                'enabled'           => array('bool', 0, lang('On sale'), 'enabled'),
                'notes'             => array('long', 2000, lang('Notes'), 'notes'),
            ),
            'create'   => array(
                'quantity'  => array('int', array(0, 1000000), lang('Quantity in stock'), ''),
                'group_ids' => array('ids', 'product_group', lang('Product Groups'), ''),
            ),
        ),
        'stock' => array(
            'label'   => lang('Stock'),
            'right'   => 'ecommerce',
            'tag'     => 'product',
            'table'   => 'products',
            'actions' => array('update'),
            'names'   => array('short_description', 'name'),
            'fields'  => array(
                'quantity' => array('int', array(0, 1000000), lang('Quantity in stock'), 'inventory_quantity'),
            ),
        ),
        'order' => array(
            'label'   => lang('Order'),
            'right'   => 'ecommerce',
            'tag'     => 'order',
            'table'   => 'orders',
            'actions' => array('update'),
            'names'   => array('order_number'),
            'fields'  => array(
                'status' => array('enum', array('incomplete', 'complete', 'exported', 'cancelled'), lang('Status'), 'status'),
                'notes'  => array('long', 2000, lang('Notes'), 'notes'),
                // Goes with a cancellation; the order keeps it, nothing is
                // compared against it.
                'cancellation_reason' => array('long', 500, lang('Reason for cancelling'), ''),
            ),
        ),
        'contact' => array(
            'label'    => lang('Contact'),
            'right'    => 'contacts',
            'tag'      => 'contact',
            'table'    => 'contacts',
            'actions'  => array('update', 'create', 'delete'),
            'required' => array('first_name', 'last_name', 'company', 'email'),
            'names'    => array('first_name', 'last_name', 'company', 'email_address'),
            'fields'   => array(
                'salutation'  => array('text', 50, lang('Salutation'), 'salutation'),
                'first_name'  => array('text', 50, lang('First name'), 'first_name'),
                'last_name'   => array('text', 50, lang('Last name'), 'last_name'),
                'company'     => array('text', 50, lang('Company'), 'company'),
                'title'       => array('text', 50, lang('Title'), 'title'),
                'email'       => array('email', 100, lang('E-mail'), 'email_address'),
                'phone'       => array('text', 50, lang('Mobile phone'), 'mobile_phone'),
                'address_1'   => array('text', 50, lang('Address'), 'business_address_1'),
                'address_2'   => array('text', 50, lang('Address (line 2)'), 'business_address_2'),
                'city'        => array('text', 50, lang('City'), 'business_city'),
                'state'       => array('text', 50, lang('State'), 'business_state'),
                'zip'         => array('text', 50, lang('Zip code'), 'business_zip_code'),
                'country'     => array('text', 50, lang('Country'), 'business_country'),
                'description' => array('long', 2000, lang('Description'), 'description'),
            ),
        ),
        'erp_account' => array(
            'label'    => lang('ERP account'),
            'right'    => 'erp',
            'tag'      => 'erp_account',
            'table'    => 'erp_accounts',
            'actions'  => array('update', 'create'),
            'required' => array('title'),
            'names'    => array('title'),
            'fields'   => array(
                'title'        => array('text', 255, lang('Name'), 'title'),
                'email'        => array('email', 255, lang('E-mail'), 'email'),
                'phone'        => array('text', 50, lang('Phone'), 'phone'),
                'address'      => array('text', 255, lang('Address'), 'address'),
                'district'     => array('text', 100, lang('District'), 'district'),
                'city'         => array('text', 100, lang('City'), 'city'),
                'state'        => array('text', 100, lang('State'), 'state'),
                'postcode'     => array('text', 20, lang('Postcode'), 'postcode'),
                'tax_number'   => array('text', 32, lang('Tax number'), 'tax_number'),
                'tax_office'   => array('text', 100, lang('Tax office'), 'tax_office'),
                'payment_days' => array('int', array(0, 3650), lang('Payment term (days)'), 'payment_days'),
                'status'       => array('enum', array('active', 'passive'), lang('Status'), 'status'),
                'notes'        => array('long', 2000, lang('Notes'), 'notes'),
            ),
            'create'   => array(
                'kind'      => array('enum', array('customer', 'supplier', 'both'), lang('Kind'), ''),
                'is_person' => array('bool', 0, lang('A person, not a company'), ''),
            ),
        ),
        'product_group' => array(
            'label'    => lang('Product group'),
            'right'    => 'ecommerce',
            'tag'      => 'product_group',
            'table'    => 'product_groups',
            'actions'  => array('update', 'create', 'delete', 'add', 'remove'),
            'required' => array('name'),
            'names'    => array('short_description', 'name'),
            'fields'   => array(
                'name'              => array('text', 100, lang('Name'), 'name'),
                'title'             => array('text', 255, lang('Title'), 'title'),
                'short_description' => array('long', 2000, lang('Short description'), 'short_description'),
                'full_description'  => array('long', 20000, lang('Full description'), 'full_description'),
                'meta_description'  => array('text', 255, lang('Meta description'), 'meta_description'),
                'meta_keywords'     => array('text', 2000, lang('Meta keywords'), 'meta_keywords'),
                'keywords'          => array('text', 2000, lang('Search keywords'), 'keywords'),
                'enabled'           => array('bool', 0, lang('Enabled'), 'enabled'),
            ),
            'create'   => array(
                'parent_id'   => array('ids', 'product_group', lang('Parent group'), ''),
                'product_ids' => array('ids', 'product', lang('Products'), ''),
            ),
            'members'  => array(
                'product_ids' => array('ids', 'product', lang('Products'), ''),
            ),
        ),
        'channel' => array(
            'label'   => lang('Channel'),
            // No right of its own: whoever may post in the channel may write
            // its summary, as on the summary tab.
            'right'   => '',
            'tag'     => 'channel',
            'table'   => 'ws_channels',
            'actions' => array('update'),
            'names'   => array('name'),
            'fields'  => array(
                'summary' => array('long', 20000, lang('Summary'), 'summary'),
            ),
        ),
        // A person's role. Only an administrator applies it, and never to an
        // administrator or to themselves (ws_change_check()).
        'user' => array(
            'label'   => lang('User'),
            'right'   => 'users',
            'tag'     => 'user_account',
            'table'   => 'user',
            'key'     => 'user_id',
            'actions' => array('update'),
            'names'   => array('user_username'),
            'fields'  => array(
                'role' => array('role', array('manager', 'user'), lang('Role'), 'user_role'),
            ),
        ),
        // A page's details, as the page screen and the external API write
        // them; what the page shows is designed on the page itself. Whoever
        // may edit the page's folder may change them.
        'page' => array(
            'label'   => lang('Page'),
            'right'   => '',
            'tag'     => 'page',
            'table'   => 'page',
            'key'     => 'page_id',
            'actions' => array('update', 'delete'),
            'names'   => array('page_name'),
            'fields'  => array(
                'title'            => array('text', 255, lang('Title'), 'page_title'),
                'meta_description' => array('text', 1000, lang('Meta description'), 'page_meta_description'),
                'search'           => array('bool', 0, lang('In site search'), 'page_search'),
                'search_keywords'  => array('keywords', 1000, lang('Search keywords'), 'page_search_keywords'),
                'sitemap'          => array('bool', 0, lang('In the site map'), 'sitemap'),
                'noindex'          => array('bool', 0, lang('Closed to search engines'), 'noindex'),
            ),
        ),
        // A file in the file manager: its description, its folder and, for a
        // text file, its words. Its name is its address, so it is not changed
        // from here.
        'file' => array(
            'label'   => lang('File'),
            'right'   => '',
            'tag'     => 'file',
            'table'   => 'files',
            'actions' => array('update', 'delete'),
            'names'   => array('name'),
            'fields'  => array(
                'description' => array('text', 255, lang('Description'), 'description'),
                'folder_id'   => array('int', array(1, 2147483647), lang('Folder'), 'folder'),
                'content'     => array('long', defined('WS_FILE_TEXT_MAX') ? WS_FILE_TEXT_MAX : 524288, lang('Content'), 'content'),
            ),
        ),
        // An offer is switched on or off, described and dated here; its rules
        // are built on the offer screen.
        'offer' => array(
            'label'   => lang('Offer'),
            'right'   => 'ecommerce',
            'tag'     => 'offer',
            'table'   => 'offers',
            'actions' => array('update', 'delete'),
            'names'   => array('code'),
            'fields'  => array(
                'status'      => array('enum', array('enabled', 'disabled'), lang('Status'), 'status'),
                'description' => array('text', 255, lang('Description'), 'description'),
                'start_date'  => array('date', 0, lang('Start date'), 'start_date'),
                'end_date'    => array('date', 0, lang('End date'), 'end_date'),
            ),
        ),
        // An event of the site's calendars; repeats and reservations stay on
        // the event screen.
        'calendar_event' => array(
            'label'    => lang('Calendar Event'),
            'right'    => 'calendars',
            'tag'      => 'calendar_event',
            'table'    => 'calendar_events',
            'actions'  => array('update', 'create', 'delete'),
            'required' => array('name'),
            'names'    => array('name'),
            'fields'   => array(
                'name'              => array('text', 255, lang('Name'), 'name'),
                'short_description' => array('long', 1000, lang('Short description'), 'short_description'),
                'location'          => array('text', 255, lang('Location'), 'location'),
                'start_time'        => array('datetime', 0, lang('Start'), 'start_time'),
                'end_time'          => array('datetime', 0, lang('End'), 'end_time'),
                'all_day'           => array('bool', 0, lang('All day'), 'all_day'),
                'published'         => array('bool', 0, lang('Published'), 'published'),
            ),
            'create'   => array(
                'calendar_ids' => array('ids', 'calendar', lang('Calendars'), ''),
            ),
        ),
    );

    // Kinds of record the site does not run are not offered.
    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        unset($types['product'], $types['stock'], $types['order'], $types['product_group'], $types['offer']);
    }

    if (defined('CALENDARS') && (CALENDARS !== true)) {
        unset($types['calendar_event']);
    }

    // A page is closed to search engines only where the upgrade that added
    // the switch was taken.
    if (!function_exists('pg_page_noindex_ready') || !pg_page_noindex_ready()) {
        unset($types['page']['fields']['noindex']);
    }

    if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
        unset($types['erp_account']);
    }

    // Orders keep notes only on installations that took the upgrade that
    // added them (the order screen asks the same way).
    if (isset($types['order']) && function_exists('waf_table_has_column') && !waf_table_has_column('orders', 'notes')) {
        unset($types['order']['fields']['notes']);
    }

    return $types;
}

/**
 * Every field a kind of record takes for an action.
 *
 * @param array  $type
 * @param string $action
 * @return array
 */
function ws_change_fields_for($type, $action)
{
    if ($action === 'create') {
        return $type['fields'] + ($type['create'] ?? array());
    }

    if (($action === 'add') || ($action === 'remove')) {
        return $type['members'] ?? array();
    }

    return $type['fields'];
}

/**
 * The field of a kind of record, whichever list it is in.
 *
 * @param array  $type
 * @param string $name
 * @return array|null
 */
function ws_change_field($type, $name)
{
    return $type['fields'][$name] ?? ($type['create'][$name] ?? ($type['members'][$name] ?? null));
}

/**
 * A value in the shape its field keeps, or null when it cannot be one.
 *
 * @param array $field
 * @param mixed $value
 * @return mixed
 */
function ws_change_value($field, $value)
{
    switch ($field[0]) {
        case 'text':
        case 'email':
            if (is_array($value) || is_object($value)) {
                return null;
            }

            return trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $value), 0, (int) $field[1]));

        case 'long':
            if (is_array($value) || is_object($value)) {
                return null;
            }

            return trim(mb_substr(str_replace("\r\n", "\n", (string) $value), 0, (int) $field[1]));

        case 'money':
            if (!is_numeric($value) || ((int) $value < 0) || ((float) $value != (int) $value)) {
                return null;
            }

            return (int) $value;

        case 'int':
            if (!is_numeric($value) || ((float) $value != (int) $value)
                || ((int) $value < $field[1][0]) || ((int) $value > $field[1][1])) {
                return null;
            }

            return (int) $value;

        case 'bool':
            if (is_bool($value)) {
                return $value;
            }

            if (in_array($value, array(1, '1', 'true', 'yes'), true)) {
                return true;
            }

            if (in_array($value, array(0, '0', 'false', 'no'), true)) {
                return false;
            }

            return null;

        case 'enum':
            return in_array((string) $value, $field[1], true) ? (string) $value : null;

        case 'role':
            // By name (manager, user) or by number; only the ones listed.
            $roles = array('administrator' => 0, 'designer' => 1, 'manager' => 2, 'user' => 3);
            $number = is_numeric($value) ? (int) $value : ($roles[strtolower(trim((string) (is_scalar($value) ? $value : '')))] ?? -1);

            foreach ($field[1] as $name) {
                if ($roles[$name] === $number) {
                    return $number;
                }
            }

            return null;

        case 'date':
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $match)
                || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
                return null;
            }

            return trim($value);

        case 'datetime':
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/', trim($value), $match)
                || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
                || ((int) ($match[4] ?? 0) > 23) || ((int) ($match[5] ?? 0) > 59)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d %02d:%02d:00', $match[1], $match[2], $match[3], (int) ($match[4] ?? 0), (int) ($match[5] ?? 0));

        case 'keywords':
            // A list, or words separated by commas; kept as the page screen
            // keeps them.
            if (is_object($value)) {
                return null;
            }

            $words = array();

            foreach ((is_array($value) ? $value : explode(',', (string) $value)) as $word) {
                $word = is_scalar($word) ? trim(preg_replace('/\s+/u', ' ', str_replace(',', ' ', (string) $word))) : '';

                if ($word !== '') {
                    $words[mb_strtolower($word)] = $word;
                }
            }

            $joined = implode(',', $words);

            return (mb_strlen($joined) > (int) $field[1]) ? null : $joined;

        case 'ids':
            // One id is a list of one.
            $list = is_array($value) ? $value : array($value);
            $ids = array();

            foreach ($list as $id) {
                if (!is_numeric($id) || ((int) $id <= 0) || ((float) $id != (int) $id)) {
                    return null;
                }

                $ids[(int) $id] = (int) $id;
            }

            if (empty($ids) || (count($ids) > WS_CHANGES_LIST_MAX)) {
                return null;
            }

            $types = ws_change_types();
            $table = $types[$field[1]]['table'] ?? (ws_change_id_tables()[$field[1]] ?? '');

            if ($table === '') {
                return null;
            }

            // Only records that are there: not ones in the Recycle Bin.
            $live = (function_exists('waf_table_has_column') && waf_table_has_column($table, 'recycled')) ? " AND recycled = '0'" : '';

            if ((int) db_value("SELECT COUNT(*) FROM " . $table . " WHERE id IN (" . implode(',', $ids) . ")" . $live) !== count($ids)) {
                return null;
            }

            return array_values($ids);
    }

    return null;
}

/**
 * What a record holds in a field now, in the field's shape.
 *
 * @param array $field
 * @param array $row
 * @return mixed
 */
function ws_change_current_value($field, $row)
{
    if ($field[3] === '') {
        return ($field[0] === 'long' || $field[0] === 'text') ? '' : null;
    }

    $raw = $row[$field[3]] ?? '';

    switch ($field[0]) {
        case 'money':
        case 'int':
        case 'role':
            return (int) $raw;

        case 'bool':
            return ((int) $raw === 1);

        case 'long':
            return trim(str_replace("\r\n", "\n", (string) $raw));

        default:
            return trim((string) $raw);
    }
}

/**
 * The record a change is aimed at, or null.
 *
 * @param string $type
 * @param int    $id
 * @return array|null
 */
function ws_change_record($type, $id)
{
    $types = ws_change_types();

    if (!isset($types[$type]) || ((int) $id <= 0)) {
        return null;
    }

    $key = $types[$type]['key'] ?? 'id';
    $row = db_item("SELECT * FROM " . $types[$type]['table'] . " WHERE " . $key . " = '" . (int) $id . "' LIMIT 1");

    if (!is_array($row)) {
        return null;
    }

    $row['id'] = (int) $row[$key];

    // A text file's words, for a change to them to be laid against.
    if ($type === 'file') {
        $text = (function_exists('ws_file_is_text') && ws_file_is_text($row['name'])) ? ws_file_text_read($row['id'], true) : null;
        $row['content'] = is_string($text) ? $text : '';
    }

    return $row;
}

/**
 * The tables of the records an ids field may name that are not kinds of
 * change themselves.
 *
 * @return array kind => table
 */
function ws_change_id_tables()
{
    return array('calendar' => 'calendars');
}

/**
 * A person's rights that the workspace viewer does not carry, read once.
 *
 * @param int $user_id
 * @return array role, delete_pages, publish_events
 */
function ws_change_person($user_id)
{
    static $cache = array();

    $user_id = (int) $user_id;

    if (!isset($cache[$user_id])) {
        $row = db_item("SELECT user_role, user_delete_pages, user_publish_calendar_events FROM user WHERE user_id = '" . $user_id . "'");
        $yes = function ($value) {
            return in_array($value, array(1, '1', 'yes', true), true);
        };

        $cache[$user_id] = array(
            'role'           => is_array($row) ? (int) $row['user_role'] : 3,
            'delete_pages'   => is_array($row) && $yes($row['user_delete_pages']),
            'publish_events' => is_array($row) && $yes($row['user_publish_calendar_events']),
        );
    }

    return $cache[$user_id];
}

/**
 * May the person work with this calendar (the event screens' rule)?
 *
 * @param array $viewer
 * @param int   $calendar_id
 * @return bool
 */
function ws_change_calendar_access($viewer, $calendar_id)
{
    if ((int) $viewer['role'] < 3) {
        return true;
    }

    return (int) db_value("SELECT COUNT(*) FROM users_calendars_xref
        WHERE user_id = '" . (int) $viewer['id'] . "' AND calendar_id = '" . (int) $calendar_id . "'") > 0;
}

/**
 * The file manager's Recycle Bin, loaded for a page or a file on its way
 * into it.
 *
 * @return bool the bin is there
 */
function ws_change_recycle_ready()
{
    if (!function_exists('pg_recycle_ready') && file_exists(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php')) {
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    }

    return function_exists('pg_recycle_ready') && pg_recycle_ready();
}

/**
 * What a record is called, from its own columns.
 *
 * @param string $type
 * @param array  $row column => value
 * @return string
 */
function ws_change_record_name($type, $row)
{
    $types = ws_change_types();
    $parts = array();

    foreach ($types[$type]['names'] ?? array() as $column) {
        $value = trim(strip_tags((string) ($row[$column] ?? '')));

        if ($value === '') {
            continue;
        }

        // A contact is named by its whole name; the others by their first
        // non-empty column.
        if ($type !== 'contact') {
            return mb_substr($value, 0, 120);
        }

        $parts[] = $value;

        if (count($parts) === 2) {
            break;
        }
    }

    return mb_substr(implode(' ', $parts), 0, 120);
}

/**
 * The account of a person, as the workspace sees their rights.
 *
 * @param int $user_id
 * @return array|null
 */
function ws_change_viewer_for_id($user_id)
{
    $row = db_item("SELECT * FROM user WHERE user_id = '" . (int) $user_id . "'");

    if (!is_array($row)) {
        return null;
    }

    return ws_viewer(array(
        'id'               => (int) $row['user_id'],
        'role'             => (int) $row['user_role'],
        'manage_ecommerce' => $row['user_manage_ecommerce'] ?? '',
        'manage_contacts'  => $row['user_manage_contacts'] ?? '',
        'manage_erp'       => $row['manage_erp'] ?? 0,
        'manage_erp_cash'  => $row['manage_erp_cash'] ?? 0,
        'manage_calendars' => $row['user_manage_calendars'] ?? '',
        'manage_forms'     => $row['user_manage_forms'] ?? '',
    ));
}

/**
 * May this person change this kind of record (and, for a channel, this
 * channel)?
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $record_id
 * @return bool
 */
function ws_change_allowed($viewer, $type, $record_id = 0)
{
    $types = ws_change_types();

    if (!$viewer || !isset($types[$type])) {
        return false;
    }

    $right = $types[$type]['right'];

    if (($right !== '') && empty($viewer[$right])) {
        return false;
    }

    if ($type === 'channel') {
        $channel = ws_channel($record_id);

        return $channel && ws_can_post_channel($viewer, $channel);
    }

    // A role is an administrator's to give.
    if ($type === 'user') {
        return ((int) $viewer['role'] === 0);
    }

    // A page or a file: whoever may edit its folder (a design file only a
    // designer or an administrator), as in the file manager.
    if (($type === 'page') && ($record_id > 0)) {
        $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) $record_id . "'");

        return ($folder !== null) && ($folder !== false) && pg_folder_edit_access($folder, $viewer['id'], $viewer['role']);
    }

    if (($type === 'file') && ($record_id > 0)) {
        $file = db_item("SELECT folder, design FROM files WHERE id = '" . (int) $record_id . "'");

        return is_array($file) && pg_folder_edit_access($file['folder'], $viewer['id'], $viewer['role'])
            && (((int) $file['design'] !== 1) || ((int) $viewer['role'] <= 1));
    }

    // An event: in one of the person's calendars.
    if (($type === 'calendar_event') && ($record_id > 0) && ((int) $viewer['role'] >= 3)) {
        foreach ((array) db_values("SELECT calendar_id FROM calendar_events_calendars_xref WHERE calendar_event_id = '" . (int) $record_id . "'") as $calendar_id) {
            if (ws_change_calendar_access($viewer, $calendar_id)) {
                return true;
            }
        }

        return false;
    }

    return true;
}

/**
 * What else a change of these kinds must satisfy, for the person who would
 * apply it: asked when it is proposed and again when it is applied.
 *
 * @param array      $viewer
 * @param string     $type
 * @param string     $action
 * @param array|null $record
 * @param array      $values field => new value
 * @return string why not, or ''
 */
function ws_change_check($viewer, $type, $action, $record, $values)
{
    switch ($type) {

        case 'user':
            if ((int) $viewer['role'] !== 0) {
                return lang('Only an administrator can change a role.');
            }

            if ((int) $record['user_role'] === 0) {
                return lang('The role of an administrator is not changed from here.');
            }

            if ((int) $record['id'] === (int) $viewer['id']) {
                return lang('Nobody changes their own role.');
            }

            return '';

        case 'page':
            if ($action === 'delete') {
                if (!ws_change_recycle_ready()) {
                    return lang('This site has no recycle bin, so a page is not deleted from here.');
                }

                if (pg_recycle_is_inside($record['page_folder'])) {
                    return lang('This page is already in the recycle bin.');
                }

                if ((string) $record['page_home'] === 'yes') {
                    return lang('The home page is not deleted from here.');
                }

                if (in_array((string) $record['page_type'], ws_change_system_page_types(), true)) {
                    return lang(array('string' => 'A page of type {var:1} is part of how the site works and is not deleted from here.', 'vars' => $record['page_type']));
                }

                $person = ws_change_person($viewer['id']);

                if (((int) $viewer['role'] >= 3) && !$person['delete_pages']) {
                    return lang('You may not delete pages.');
                }

                return '';
            }

            // The three search switches as they would be after the change.
            $noindex = array_key_exists('noindex', $values) ? (bool) $values['noindex'] : !empty($record['noindex']);
            $sitemap = array_key_exists('sitemap', $values) ? (bool) $values['sitemap'] : !empty($record['sitemap']);

            if ($noindex && $sitemap && array_key_exists('sitemap', $values) && $values['sitemap']) {
                return lang('A page that is closed to search engines cannot be in the site map.');
            }

            if (array_key_exists('sitemap', $values) && $values['sitemap'] && !in_array((string) $record['page_type'], ws_change_sitemap_page_types(), true)) {
                return lang(array('string' => 'A page of type {var:1} cannot be included in the site map.', 'vars' => $record['page_type']));
            }

            return '';

        case 'file':
            if (ws_change_recycle_ready() && pg_recycle_is_inside($record['folder'])) {
                return lang('This file is in the recycle bin.');
            }

            if ($action === 'delete') {
                return ws_change_recycle_ready() ? '' : lang('This site has no recycle bin, so a file is not deleted from here.');
            }

            if (array_key_exists('content', $values) && !(function_exists('ws_file_is_text') && ws_file_is_text($record['name']))) {
                return lang('Only the words of a text file (Markdown, plain text, CSV and the like) are changed from here.');
            }

            if (array_key_exists('folder_id', $values)) {
                $folder = (int) $values['folder_id'];

                if ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $folder . "'") === 0) {
                    return lang('There is no folder with that id.');
                }

                if (ws_change_recycle_ready() && pg_recycle_is_inside($folder)) {
                    return lang('A file goes to the recycle bin by being deleted, not moved.');
                }

                if (!pg_folder_edit_access($folder, $viewer['id'], $viewer['role'])) {
                    return lang('You may not edit that folder.');
                }
            }

            return '';

        case 'offer':
            $start = $values['start_date'] ?? ($record['start_date'] ?? '');
            $end = $values['end_date'] ?? ($record['end_date'] ?? '');

            if (($start !== '') && ($end !== '') && ($end < $start)) {
                return lang('An offer cannot end before it starts.');
            }

            return '';

        case 'calendar_event':
            if ($action === 'delete') {
                return '';
            }

            $start = $values['start_time'] ?? ($record['start_time'] ?? '');
            $end = $values['end_time'] ?? ($record['end_time'] ?? $start);

            if ($action === 'create') {
                if (empty($values['start_time'])) {
                    return lang('A new event needs start_time.');
                }

                if (empty($values['calendar_ids'])) {
                    return lang('A new event needs calendar_ids: the calendars it goes on.');
                }
            }

            if (($start !== '') && ($end !== '') && ($end < $start)) {
                return lang('An event cannot end before it starts.');
            }

            foreach ((array) ($values['calendar_ids'] ?? array()) as $calendar_id) {
                if (!ws_change_calendar_access($viewer, $calendar_id)) {
                    return lang(array('string' => 'You may not use the calendar {var:1}.', 'vars' => '#' . (int) $calendar_id));
                }
            }

            if (array_key_exists('published', $values) && ((int) $viewer['role'] >= 3) && !ws_change_person($viewer['id'])['publish_events']) {
                return lang('You may not publish calendar events.');
            }

            return '';
    }

    return '';
}

/**
 * Page types the storefront and the account area need in order to work; they
 * are not deleted from here (the external API refuses them the same way).
 *
 * @return string[]
 */
function ws_change_system_page_types()
{
    return array(
        'change password', 'set password', 'email a friend', 'error', 'forgot password',
        'login', 'logout', 'membership confirmation', 'membership entrance', 'my account',
        'my account profile', 'email preferences', 'view order', 'update address book',
        'custom form confirmation', 'shopping cart', 'shipping address and arrival',
        'shipping method', 'billing information', 'order preview', 'order receipt',
        'order form', 'registration confirmation', 'registration entrance',
        'affiliate sign up form', 'affiliate sign up confirmation', 'affiliate welcome',
    );
}

/**
 * Page types that may appear in the site map (the page screen's list).
 *
 * @return string[]
 */
function ws_change_sitemap_page_types()
{
    return array(
        'standard', 'folder view', 'photo gallery', 'custom form', 'form list view',
        'form item view', 'form view directory', 'calendar view', 'calendar event view',
        'catalog', 'catalog detail', 'express order', 'order form', 'shopping cart',
        'search results',
    );
}

/**
 * The products that are in a group now.
 *
 * @param int $group_id
 * @return int[]
 */
function ws_change_group_members($group_id)
{
    return array_map('intval', (array) db_values("SELECT product FROM products_groups_xref WHERE product_group = '" . (int) $group_id . "'"));
}

/**
 * Does the catalogue have its Recycle Bin (2026.4.4)? The file manager's own
 * question, asked the same way: the bin, its retention setting, the flag on
 * both tables and a bin that takes catalogue rows.
 *
 * @return bool
 */
function ws_change_catalog_bin()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('config', 'recycle_retention_days')
            && waf_table_has_column('products', 'recycled')
            && waf_table_has_column('product_groups', 'recycled')
            && waf_table_has_column('recycle_bin', 'item_type');

        if ($ready) {
            $item_type = db_item("SHOW COLUMNS FROM recycle_bin WHERE Field = 'item_type'");
            $ready = is_array($item_type) && (strpos((string) $item_type['Type'], 'product_group') !== false);
        }
    }

    return $ready;
}

/**
 * Is a deletion of this kind taken back from the Recycle Bin?
 *
 * @param string $type
 * @return bool
 */
function ws_change_binned($type)
{
    if (in_array($type, array('page', 'file'), true)) {
        return ws_change_recycle_ready();
    }

    return in_array($type, array('product', 'product_group'), true) && ws_change_catalog_bin();
}

/**
 * Why a record cannot be deleted, or '' when it can.
 *
 * @param string $type
 * @param array  $record
 * @return string
 */
function ws_change_delete_blocked($type, $record)
{
    if (!empty($record['recycled'])) {
        return lang('This record is already in the Recycle Bin.');
    }

    if ($type === 'product_group') {
        if ((int) $record['parent_id'] === 0) {
            return lang('This product group could not be deleted because it is the root product group.');
        }

        if ((int) db_value("SELECT COUNT(*) FROM product_groups WHERE parent_id = '" . (int) $record['id'] . "'") > 0) {
            return lang('This product group has groups inside it. Move or delete those first.');
        }
    }

    // A contact that is somebody's account would leave that account without
    // its name and address.
    if (($type === 'contact') && ((int) db_value("SELECT COUNT(*) FROM user WHERE user_contact = '" . (int) $record['id'] . "'") > 0)) {
        return lang('This contact belongs to a user account and is not deleted from here.');
    }

    return '';
}

/**
 * The changes an answer proposes, checked and laid against what the records
 * hold now.
 *
 * @param mixed $changes    the answer's changes, as sent
 * @param int   $asker_id   the person who asked, who will apply them
 * @param int   $channel_id the channel the request came from
 * @return array ok, error, rows (type, action, record_id, fields, reason, snapshot)
 */
function ws_changes_input($changes, $asker_id, $channel_id = 0)
{
    $out = array('ok' => true, 'error' => '', 'rows' => array());

    if (($changes === null) || ($changes === '') || ($changes === array())) {
        return $out;
    }

    if (!ws_changes_ready()) {
        return array('ok' => false, 'error' => lang('This site cannot take proposed changes yet: its database has to be upgraded.'), 'rows' => array());
    }

    if (!is_array($changes)) {
        return array('ok' => false, 'error' => lang('changes is a list of objects.'), 'rows' => array());
    }

    $types = ws_change_types();
    $asker = ws_change_viewer_for_id($asker_id);

    foreach (array_slice(array_values($changes), 0, WS_CHANGES_PER_ANSWER) as $index => $change) {
        $where = 'changes[' . $index . ']: ';
        $refuse = function ($message) use ($where) {
            return array('ok' => false, 'error' => $where . $message, 'rows' => array());
        };

        if (!is_array($change)) {
            return $refuse(lang('each change is an object of {type, action, id, fields, reason}.'));
        }

        $type = (string) ($change['type'] ?? '');

        if (!isset($types[$type])) {
            return $refuse(lang(array('string' => 'type is one of {var:1}.', 'vars' => implode(', ', array_keys($types)))));
        }

        $action = (string) ($change['action'] ?? 'update');

        if (!in_array($action, $types[$type]['actions'], true)) {
            return $refuse(lang(array('string' => 'A {var:1} takes the actions {var:2}.', 'vars' => array($type, implode(', ', $types[$type]['actions'])))));
        }

        $record_id = (int) ($change['id'] ?? 0);

        // A channel's summary is the channel the request came from.
        if (($type === 'channel') && ($record_id !== (int) $channel_id)) {
            return $refuse(lang('A summary is proposed for the channel the request came from.'));
        }

        // Nobody gets a button for a change they could not make themselves.
        if (!ws_change_allowed($asker, $type, $record_id)) {
            return $refuse(lang(array('string' => 'The person who asked may not change a record of this kind ({var:1}). Say so in the answer instead of proposing it.', 'vars' => $types[$type]['label'])));
        }

        $record = null;

        if ($action !== 'create') {
            $record = ws_change_record($type, $record_id);

            if ($record === null) {
                return $refuse(lang(array('string' => 'There is no record of this kind with the id {var:1}.', 'vars' => $record_id)));
            }
        }

        $allowed = ws_change_fields_for($types[$type], $action);
        $fields = is_array($change['fields'] ?? null) ? $change['fields'] : array();
        $list = array();
        $snapshot = null;

        // A group's products come as product_ids, beside or inside fields.
        if ((($action === 'add') || ($action === 'remove')) && isset($change['product_ids'])) {
            $fields['product_ids'] = $change['product_ids'];
        }

        if ($action === 'delete') {
            $blocked = ws_change_delete_blocked($type, $record);

            if ($blocked !== '') {
                return $refuse($blocked);
            }

            // What goes, field by field, and the row as it was. A file's words
            // stay in the file, which waits in the recycle bin.
            foreach ($types[$type]['fields'] as $name => $field) {
                if (($field[3] !== '') && !(($type === 'file') && ($name === 'content'))) {
                    $list[] = array('name' => $name, 'from' => ws_change_current_value($field, $record), 'to' => null);
                }
            }

            $snapshot = $record;
            unset($snapshot['content']);
        } else {
            if (empty($fields)) {
                return $refuse(lang('fields names at least one field and its new value.'));
            }

            foreach ($fields as $name => $value) {
                $name = (string) $name;

                if (!isset($allowed[$name])) {
                    return $refuse(lang(array('string' => '{var:1} cannot be changed. The fields are: {var:2}.', 'vars' => array($name, implode(', ', array_keys($allowed))))));
                }

                $field = $allowed[$name];
                $to = ws_change_value($field, $value);

                if ($to === null) {
                    return $refuse(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => $name)));
                }

                if (($field[0] === 'email') && ($to !== '') && function_exists('validate_email_address') && !validate_email_address($to)) {
                    return $refuse(lang('That is not an e-mail address.'));
                }

                if ($action === 'create') {
                    if ((($to !== '') && ($to !== null)) || ($field[0] === 'bool')) {
                        $list[] = array('name' => $name, 'from' => null, 'to' => $to);
                    }

                    continue;
                }

                if (($action === 'add') || ($action === 'remove')) {
                    // Only what would change: products already in the group are
                    // not added again, products not in it are not taken out.
                    $members = ws_change_group_members($record_id);
                    $to = array_values(($action === 'add') ? array_diff($to, $members) : array_intersect($to, $members));

                    if (empty($to)) {
                        return $refuse(($action === 'add')
                            ? lang('Those products are already in this group.')
                            : lang('None of those products is in this group.'));
                    }

                    $list[] = array('name' => $name, 'from' => null, 'to' => $to);
                    continue;
                }

                $from = ws_change_current_value($field, $record);

                // What the record already holds is not a change.
                if (($field[3] !== '') && ($from === $to)) {
                    continue;
                }

                $list[] = array('name' => $name, 'from' => $from, 'to' => $to);
            }

            if ($action === 'update') {
                // A reason on its own changes nothing.
                $real = array_filter($list, function ($item) use ($types, $type) {
                    return $types[$type]['fields'][$item['name']][3] !== '';
                });

                if (empty($real)) {
                    return $refuse(lang('Nothing in it differs from what the record holds now.'));
                }
            }

            if ($action === 'create') {
                $check = ws_change_create_check($type, $list);

                if ($check !== '') {
                    return $refuse($check);
                }
            }
        }

        $proposed = array();

        foreach ($list as $item) {
            if ($action !== 'delete') {
                $proposed[$item['name']] = $item['to'];
            }
        }

        $check = ws_change_check($asker, $type, $action, $record, $proposed);

        if ($check !== '') {
            return $refuse($check);
        }

        if (($type === 'order') && ($action === 'update')) {
            $status = null;

            foreach ($list as $item) {
                if ($item['name'] === 'status') {
                    $status = $item['to'];
                }
            }

            foreach ($list as $item) {
                if (($item['name'] === 'cancellation_reason') && ($status !== 'cancelled')) {
                    return $refuse(lang('cancellation_reason goes only with status cancelled.'));
                }
            }
        }

        $out['rows'][] = array(
            'type'      => $type,
            'action'    => $action,
            'record_id' => ($action === 'create') ? 0 : $record_id,
            'fields'    => $list,
            'reason'    => trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($change['reason'] ?? '')), 0, 500)),
            'snapshot'  => $snapshot,
        );
    }

    return $out;
}

/**
 * Why a new record cannot be made from these fields, or ''.
 *
 * @param string $type
 * @param array  $list [name, from, to]
 * @return string
 */
function ws_change_create_check($type, $list)
{
    $types = ws_change_types();
    $values = array();

    foreach ($list as $item) {
        $values[$item['name']] = $item['to'];
    }

    $required = $types[$type]['required'] ?? array();
    $has = array_filter($required, function ($name) use ($values) {
        return isset($values[$name]) && (trim((string) $values[$name]) !== '');
    });

    if (!empty($required) && empty($has)) {
        // A contact needs one of its names or an address to write to; the
        // others need their name.
        return ($type === 'contact')
            ? lang('A customer needs at least a name, a company or an e-mail address.')
            : lang(array('string' => '{var:1} is required', 'vars' => array($required[0])));
    }

    // The name is the product's own code and the panel does not take the
    // same one twice.
    if (($type === 'product') && ($taken = (int) db_value("SELECT id FROM products WHERE name = '" . e($values['name']) . "' LIMIT 1"))) {
        return lang(array('string' => 'A product with the name {var:1} already exists (id {var:2}).', 'vars' => array($values['name'], $taken)));
    }

    if (($type === 'product_group') && ($taken = (int) db_value("SELECT id FROM product_groups WHERE name = '" . e($values['name']) . "' LIMIT 1"))) {
        return lang(array('string' => 'A product group with the name {var:1} already exists (id {var:2}).', 'vars' => array($values['name'], $taken)));
    }

    if (($type === 'product_group') && isset($values['parent_id']) && (count($values['parent_id']) !== 1)) {
        return lang('parent_id is one product group.');
    }

    return '';
}

/**
 * Keeps the checked changes under the answer they came with.
 *
 * @param array $request the ws_ai_requests row
 * @param int   $message_id the answer
 * @param array $rows from ws_changes_input()
 */
function ws_changes_store($request, $message_id, $rows)
{
    $now = time();

    foreach ($rows as $row) {
        $snapshot = ($row['snapshot'] !== null) ? json_encode($row['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;

        db("INSERT INTO ws_ai_changes (request_id, channel_id, message_id, requested_by, record_type, action, record_id, fields, reason, snapshot, created_at)
            VALUES (
                '" . (int) $request['id'] . "',
                '" . (int) $request['channel_id'] . "',
                '" . (int) $message_id . "',
                '" . (int) $request['requested_by'] . "',
                '" . e($row['type']) . "',
                '" . e($row['action']) . "',
                '" . (int) $row['record_id'] . "',
                '" . e(json_encode($row['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "',
                '" . e($row['reason']) . "',
                " . (($snapshot !== null) ? "'" . e($snapshot) . "'" : 'NULL') . ",
                '" . $now . "')");
    }
}

/**
 * A value as the card and the decision print it.
 *
 * @param string $type
 * @param string $name
 * @param mixed  $value
 * @param int    $limit characters of text shown
 * @return string
 */
function ws_change_show($type, $name, $value, $limit = 300)
{
    $types = ws_change_types();
    $field = isset($types[$type]) ? (ws_change_field($types[$type], $name) ?? array('text')) : array('text');

    switch ($field[0]) {
        case 'money':
            return ws_money_out((int) $value);

        case 'bool':
            return $value ? lang('Yes') : lang('No');

        case 'int':
            return (string) (int) $value;

        case 'ids':
            // Calendars by their names; records by their number (the card
            // shows their tags beside).
            if ($field[1] === 'calendar') {
                $ids = array_filter(array_map('intval', (array) $value));
                $names = empty($ids) ? array() : (array) db_values("SELECT name FROM calendars WHERE id IN (" . implode(',', $ids) . ") ORDER BY name");

                return implode(', ', $names);
            }

            return implode(', ', array_map(function ($id) {
                return '#' . (int) $id;
            }, (array) $value));

        case 'role':
            return pg_user_role_name((int) $value);

        case 'date':
            $parts = explode('-', (string) $value);

            return (count($parts) === 3) ? $parts[2] . '.' . $parts[1] . '.' . $parts[0] : (string) $value;

        case 'datetime':
            return mb_substr((string) $value, 0, 16);

        case 'enum':
            $labels = array(
                'incomplete' => lang('Incomplete'),
                'complete'   => lang('Complete'),
                'exported'   => lang('Exported'),
                'cancelled'  => lang('Cancelled'),
                'active'     => lang('Active'),
                'passive'    => lang('Passive'),
                'customer'   => lang('Customer'),
                'supplier'   => lang('Supplier'),
                'both'       => lang('Customer and supplier'),
                'enabled'    => lang('Enabled'),
                'disabled'   => lang('Disabled'),
            );

            return $labels[(string) $value] ?? (string) $value;
    }

    // Descriptions may carry markup: the card shows the words.
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    if ($text === '') {
        return lang('(empty)');
    }

    return (mb_strlen($text) > $limit) ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

/**
 * The tags of the records an ids field names, as a message writes them.
 *
 * @param array $field
 * @param array $ids
 * @return string
 */
function ws_change_ids_tokens($field, $ids)
{
    $types = ws_change_types();
    $tag = $types[$field[1]]['tag'] ?? '';

    return implode(' ', array_map(function ($id) use ($tag) {
        return '<#' . $tag . ':' . (int) $id . '>';
    }, (array) $ids));
}

/**
 * The changes proposed under a set of messages, as the card draws them for
 * this reader. The values are shown only to somebody who may see records of
 * that kind; the others learn that a change is waiting and for whom.
 *
 * @param array $viewer
 * @param int[] $message_ids
 * @return array message_id => change[]
 */
function ws_changes_map($viewer, $message_ids)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_changes_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_ai_changes WHERE message_id IN (" . implode(',', $message_ids) . ") ORDER BY id");

    if (empty($rows)) {
        return array();
    }

    $types = ws_change_types();
    $people_ids = array();
    $tokens = array();

    foreach ($rows as $row) {
        $people_ids[] = (int) $row['requested_by'];
        $people_ids[] = (int) $row['decided_by'];

        if (!isset($types[$row['record_type']])) {
            continue;
        }

        if ((int) $row['record_id'] > 0) {
            $tokens[] = array('type' => $types[$row['record_type']]['tag'], 'id' => (int) $row['record_id']);
        }

        // Claude may tag a person or a record in its reason.
        $tokens = array_merge($tokens, ws_tokens((string) $row['reason']));

        foreach ((array) json_decode((string) $row['fields'], true) as $item) {
            $field = ws_change_field($types[$row['record_type']], (string) ($item['name'] ?? ''));

            if ($field && ($field[0] === 'ids') && isset($types[$field[1]])) {
                foreach ((array) $item['to'] as $id) {
                    $tokens[] = array('type' => $types[$field[1]]['tag'], 'id' => (int) $id);
                }
            }
        }
    }

    $people = ws_people(array_unique(array_filter($people_ids)));
    $refs = ws_refs_resolve($viewer, $tokens);
    $can_post = array();
    $out = array();

    foreach ($rows as $row) {
        $type = (string) $row['record_type'];

        if (!isset($types[$type])) {
            continue;
        }

        $action = (string) ($row['action'] ?? 'update');
        $channel_id = (int) $row['channel_id'];

        if (!isset($can_post[$channel_id])) {
            $can_post[$channel_id] = ws_can_post_channel($viewer, ws_channel($channel_id));
        }

        $right = $types[$type]['right'];
        $sees = ($right === '') || !empty($viewer[$right]);
        $asker = ((int) $row['requested_by'] === (int) $viewer['id']);

        // The one who asked may apply it only with a right to this very record
        // (a role only an administrator, a page only in a folder they edit).
        $may = $asker && $sees && ws_change_allowed($viewer, $type, (int) $row['record_id']);
        $fields = json_decode((string) $row['fields'], true);
        $fields = is_array($fields) ? $fields : array();
        $items = array();
        $names = array();
        $cancels = false;

        foreach ($fields as $item) {
            $name = (string) ($item['name'] ?? '');
            $field = ws_change_field($types[$type], $name);

            if ($field === null) {
                continue;
            }

            if (($type === 'order') && ($name === 'status') && (($item['to'] ?? '') === 'cancelled')) {
                $cancels = true;
            }

            // What names the record, for a new one or one that is gone.
            if (in_array($field[3], $types[$type]['names'] ?? array(), true)) {
                $names[$field[3]] = ($action === 'delete') ? ($item['from'] ?? '') : ($item['to'] ?? '');
            }

            // A deleted record's empty fields say nothing about it.
            if (!$sees || (($action === 'delete') && in_array($item['from'] ?? null, array('', null), true))) {
                continue;
            }

            $entry = array(
                'label' => $field[2],
                'from'  => (($action === 'update') && ($field[3] !== '')) || ($action === 'delete') ? ws_change_show($type, $name, $item['from'] ?? '') : '',
                'to'    => ($action === 'delete') ? '' : ws_change_show($type, $name, $item['to'] ?? ''),
                'html'  => '',
            );

            if (($field[0] === 'ids') && ($action !== 'delete') && isset($types[$field[1]])) {
                $entry['html'] = ws_render_inline(ws_change_ids_tokens($field, $item['to'] ?? array()), $refs);
            }

            $items[] = $entry;
        }

        // For a new record that is not made yet, or a deleted one, the name
        // from the proposal stands in for the tag.
        $label = '';

        if (($action === 'create') || ($action === 'delete')) {
            $label = ws_change_record_name($type, $names);

            if (($label === '') && is_string($row['snapshot'] ?? null)) {
                $label = ws_change_record_name($type, (array) json_decode($row['snapshot'], true));
            }
        }

        $warning = '';

        if ($sees && $cancels) {
            $warning = lang('Cancelling tries to refund the payment and e-mails the customer.');
        } elseif ($sees && ($action === 'delete')) {
            $warning = ws_change_binned($type)
                ? lang('This moves the record to the Recycle Bin, where it can be restored.')
                : lang('This deletes the record for good. What it held stays on this card and in the decision.');
        }

        $pending = ($row['status'] === 'pending');
        $record_html = '';

        if (((int) $row['record_id'] > 0) && !(($action === 'delete') && ($row['status'] === 'applied'))) {
            $record_html = ws_render_inline('<#' . $types[$type]['tag'] . ':' . (int) $row['record_id'] . '>', $refs);
        }

        $action_labels = array(
            'update' => '',
            'create' => lang('New record'),
            'delete' => ws_change_binned($type) ? lang('To the Recycle Bin') : lang('To be deleted'),
            'add'    => lang('Into the group'),
            'remove' => lang('Out of the group'),
        );

        $out[(int) $row['message_id']][] = array(
            'id'           => (int) $row['id'],
            'type'         => $type,
            'type_label'   => $types[$type]['label'],
            'action'       => $action,
            'action_label' => $action_labels[$action] ?? '',
            'record_html'  => $record_html,
            'record_label' => $label,
            'fields'       => $items,
            'count'        => count($fields),
            'hidden'       => !$sees,
            'reason'       => $sees ? ws_plain_text($viewer, (string) $row['reason'], $refs) : '',
            'warning'      => $warning,
            'status'       => (string) $row['status'],
            'error'        => (string) $row['error'],
            'asker'        => (string) ($people[(int) $row['requested_by']]['name'] ?? ''),
            'decided_by'   => (string) ($people[(int) $row['decided_by']]['name'] ?? ''),
            'decided'      => ((int) $row['decided_at'] > 0) ? ws_time_label($row['decided_at']) : '',
            'decision_id'  => (int) $row['decision_message_id'],
            'can_apply'    => $pending && $may && !empty($can_post[$channel_id]),
            'no_right'     => $pending && $asker && !$may,
            'can_dismiss'  => $pending && !empty($can_post[$channel_id]) && ($asker || ((int) $viewer['role'] < 3)),
        );
    }

    return $out;
}

/**
 * Applies a proposed change, in the name of the person who asked for it.
 *
 * @param array $viewer
 * @param int   $change_id
 * @return array ok, error, stale, decision_id
 */
function ws_change_apply($viewer, $change_id)
{
    $fail = function ($error, $stale = false) {
        return array('ok' => false, 'error' => $error, 'stale' => $stale, 'decision_id' => 0);
    };

    if (!ws_changes_ready()) {
        return $fail(lang('Invalid request.'));
    }

    $change = db_item("SELECT * FROM ws_ai_changes WHERE id = '" . (int) $change_id . "'");

    if (!is_array($change)) {
        return $fail(lang('That proposal could not be found.'));
    }

    if ($change['status'] !== 'pending') {
        return $fail(lang('Somebody has already decided about this proposal.'));
    }

    if ((int) $change['requested_by'] !== (int) $viewer['id']) {
        return $fail(lang('Only the person who asked Claude can apply this change.'));
    }

    $types = ws_change_types();
    $type = (string) $change['record_type'];
    $action = (string) ($change['action'] ?? 'update');

    if (!isset($types[$type]) || !ws_change_allowed($viewer, $type, (int) $change['record_id'])) {
        return $fail(lang('You may not change a record of this kind.'));
    }

    $channel = ws_channel($change['channel_id']);

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    // Taken before anything is checked, so two clicks cannot both apply it.
    db("UPDATE ws_ai_changes SET status = 'applying' WHERE id = '" . (int) $change['id'] . "' AND status = 'pending'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return $fail(lang('Somebody has already decided about this proposal.'));
    }

    $release = function ($status, $error = '') use ($change, $viewer) {
        db("UPDATE ws_ai_changes SET status = '" . e($status) . "', error = '" . e(mb_substr((string) $error, 0, 250)) . "',
                decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "'
            WHERE id = '" . (int) $change['id'] . "'");
        ws_message_touch($change['message_id']);
    };

    $record = null;

    if ($action !== 'create') {
        $record = ws_change_record($type, $change['record_id']);

        if ($record === null) {
            $release('failed', lang('The record is gone.'));

            return $fail(lang('The record is gone.'));
        }
    }

    $fields = json_decode((string) $change['fields'], true);
    $fields = is_array($fields) ? $fields : array();
    $values = array();

    // Made against other values than the record holds now: somebody changed it
    // meanwhile, and their change is not written over (nor deleted).
    foreach ($fields as $item) {
        $field = ws_change_field($types[$type], (string) $item['name']);

        if ($field === null) {
            $release('failed', lang('Invalid request.'));

            return $fail(lang('Invalid request.'));
        }

        $moved = (($action === 'update') || ($action === 'delete')) && ($field[3] !== '')
            && (ws_change_current_value($field, $record) !== $item['from']);

        // A record the change names (a product for a group, a parent group)
        // that is gone since.
        if (($field[0] === 'ids') && (ws_change_value($field, $item['to']) === null)) {
            $moved = true;
        }

        if ($moved) {
            $release('stale');

            return $fail(lang('The record changed after Claude proposed this, so it was not applied. Ask Claude again.'), true);
        }

        $values[$item['name']] = $item['to'];
    }

    if ($action === 'create') {
        $check = ws_change_create_check($type, $fields);

        if ($check !== '') {
            $release('failed', $check);

            return $fail($check);
        }
    }

    if ($action === 'delete') {
        $blocked = ws_change_delete_blocked($type, $record);

        if ($blocked !== '') {
            $release('failed', $blocked);

            return $fail($blocked);
        }
    }

    $check = ws_change_check($viewer, $type, $action, $record, ($action === 'delete') ? array() : $values);

    if ($check !== '') {
        $release('failed', $check);

        return $fail($check);
    }

    $done = call_user_func('ws_change_write_' . $type, $viewer, $action, $record, $values);

    if (!$done['ok']) {
        $release('failed', $done['error']);

        return $fail($done['error']);
    }

    $record_id = ($action === 'create') ? (int) $done['id'] : (int) $change['record_id'];

    // The decision: who changed what, on Claude's proposal, under the answer.
    $tag = '<#' . $types[$type]['tag'] . ':' . $record_id . '>';
    $lines = array();

    foreach ($fields as $item) {
        $field = ws_change_field($types[$type], (string) $item['name']);

        if ($field[0] === 'ids') {
            $lines[] = $field[2] . ': ' . ws_change_ids_tokens($field, $item['to']);
        } elseif ($action === 'update') {
            $lines[] = ($field[3] === '')
                ? $field[2] . ': “' . ws_change_show($type, $item['name'], $item['to'], 120) . '”'
                : $field[2] . ': “' . ws_change_show($type, $item['name'], $item['from'], 120) . '” → “' . ws_change_show($type, $item['name'], $item['to'], 120) . '”';
        } elseif ($action === 'delete') {
            if (!in_array($item['from'], array('', null, false, 0), true)) {
                $lines[] = $field[2] . ': “' . ws_change_show($type, $item['name'], $item['from'], 120) . '”';
            }
        } else {
            $lines[] = $field[2] . ': “' . ws_change_show($type, $item['name'], $item['to'], 120) . '”';
        }
    }

    switch ($action) {
        case 'create':
            $sentence = '{var:1} was added on Claude\'s proposal. {var:2}';
            break;

        case 'delete':
            $tag = '“' . ws_change_record_name($type, $record) . '” (' . $types[$type]['label'] . ' #' . $record_id . ')';
            $sentence = !empty($done['binned'])
                ? '{var:1} was moved to the Recycle Bin on Claude\'s proposal. It held: {var:2}'
                : '{var:1} was deleted on Claude\'s proposal. It held: {var:2}';
            break;

        case 'add':
            $sentence = 'Added to {var:1} on Claude\'s proposal. {var:2}';
            break;

        case 'remove':
            $sentence = 'Taken out of {var:1} on Claude\'s proposal. {var:2}';
            break;

        default:
            $sentence = '{var:1} was changed on Claude\'s proposal. {var:2}';
    }

    $body = lang(array('string' => $sentence, 'vars' => array($tag, implode(' · ', $lines))));
    $sent = ws_message_send($viewer, $channel, mb_substr($body, 0, WS_MESSAGE_MAX), array('kind' => 'decision', 'parent_id' => (int) $change['message_id']));
    $decision_id = $sent['ok'] ? (int) $sent['message_id'] : 0;

    if ($decision_id > 0) {
        db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $decision_id . "'");
    }

    db("UPDATE ws_ai_changes SET status = 'applied', error = '', record_id = '" . $record_id . "', decision_message_id = '" . $decision_id . "',
            decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "'
        WHERE id = '" . (int) $change['id'] . "'");

    ws_message_touch($change['message_id']);

    return array('ok' => true, 'error' => '', 'stale' => false, 'decision_id' => $decision_id);
}

/**
 * Sets a proposed change aside: the person who asked, or staff.
 *
 * @param array $viewer
 * @param int   $change_id
 * @return array ok, error
 */
function ws_change_dismiss($viewer, $change_id)
{
    if (!ws_changes_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $change = db_item("SELECT * FROM ws_ai_changes WHERE id = '" . (int) $change_id . "'");

    if (!is_array($change)) {
        return array('ok' => false, 'error' => lang('That proposal could not be found.'));
    }

    if ($change['status'] !== 'pending') {
        return array('ok' => false, 'error' => lang('Somebody has already decided about this proposal.'));
    }

    if (((int) $change['requested_by'] !== (int) $viewer['id']) && ((int) $viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('Only the person who asked Claude can set this change aside.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($change['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    db("UPDATE ws_ai_changes SET status = 'dismissed', decided_by = '" . (int) $viewer['id'] . "', decided_at = '" . time() . "'
        WHERE id = '" . (int) $change['id'] . "' AND status = 'pending'");

    ws_message_touch($change['message_id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The activity log line for a change, in the name of the person who made it.
 *
 * @param string $what
 */
function ws_change_log($what)
{
    log_activity($what, (string) ($_SESSION['sessionusername'] ?? ''));
}

/**
 * The search results pages that gather a group rebuild their keyword cloud,
 * as the product group screens do after a change to the group.
 *
 * @param int  $group_id
 * @param bool $clear_only take the clouds down (before the group is deleted)
 * @return array the pages that gather it, for a rebuild after
 */
function ws_change_group_clouds($group_id, $clear_only = false)
{
    if (!function_exists('get_product_groups_in_product_group_tree')) {
        return array();
    }

    $pages = array();

    foreach ((array) db_items("SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'") as $page) {
        foreach ((array) get_product_groups_in_product_group_tree($page['product_group_id']) as $group) {
            if ((int) $group['id'] === (int) $group_id) {
                $pages[] = $page;
                break;
            }
        }
    }

    foreach ($pages as $page) {
        delete_tag_cloud_keywords_for_search_results_page($page['page_id']);

        if (!$clear_only) {
            update_tag_cloud_keywords_for_search_results_page_product_group($page['page_id'], $page['product_group_id']);
        }
    }

    return $pages;
}

/* ---------------------------------------------------------------------------
   The writes. Each one does what the panel and the external API do with the
   same record (the same columns, clean-ups, events and follow-ups), as the
   person. They answer ok, error and, for a new record, its id.
   --------------------------------------------------------------------------- */

/**
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values field => new value
 * @return array ok, error, id
 */
function ws_change_write_product($viewer, $action, $record, $values)
{
    if (!function_exists('pg_pb_create_product') && file_exists(PG_FUNCTIONS_DIR . '/product_builder.php')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    $fields = ws_change_types()['product']['fields'];

    if ($action === 'create') {
        if (!function_exists('pg_pb_create_product')) {
            return array('ok' => false, 'error' => lang('The product could not be created.'), 'id' => 0);
        }

        // The same starting row the external API's create writes: not on sale
        // unless the proposal says so, taxable, shippable, no stock tracking.
        $product = array(
            'name' => $values['name'], 'enabled' => '0', 'price' => 0, 'taxable' => '1', 'shippable' => '1', 'free_shipping' => '0',
            'title' => '', 'short_description' => '', 'meta_description' => '', 'brand' => '', 'gtin' => '', 'mpn' => '', 'keywords' => '',
            'weight' => 0, 'length' => 0, 'width' => 0, 'height' => 0, 'inventory' => '0', 'inventory_quantity' => 0,
            'user' => (int) $viewer['id'], 'full_description' => '', 'meta_keywords' => '', 'notes' => '', 'details' => '',
            'seo_analysis' => '', 'code' => '', 'out_of_stock_message' => '', 'order_receipt_message' => '', 'add_comment_message' => '',
            'gift_card_email_body' => '', 'google_product_category' => '', 'tax_rate' => null,
        );

        foreach ($values as $name => $value) {
            if (!isset($fields[$name])) {
                continue;
            }

            if ($fields[$name][0] === 'bool') {
                $product[$fields[$name][3]] = $value ? '1' : '0';
            } elseif ($fields[$name][0] === 'money') {
                $product[$fields[$name][3]] = (int) $value;
            } else {
                $product[$fields[$name][3]] = $value;
            }
        }

        if (isset($values['quantity'])) {
            $product['inventory'] = '1';
            $product['inventory_quantity'] = (int) $values['quantity'];
        }

        if ($product['title'] !== '') {
            $product['address_name'] = $product['title'];
        }

        $id = (int) pg_pb_create_product($product, array('group_ids' => (array) ($values['group_ids'] ?? array()), 'attributes' => array(), 'submit_form' => array()));

        if ($id <= 0) {
            return array('ok' => false, 'error' => lang('The product could not be created.'), 'id' => 0);
        }

        ws_change_log(lang(array('string' => 'Product ({var:1}) was added in the Workspace, on Claude\'s proposal.', 'vars' => $values['name'])));
        pg_announce('product.created', array('id' => $id, 'name' => (string) $values['name']));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    if ($action === 'delete') {
        $id = (int) $record['id'];

        // To the Recycle Bin, as the file manager bins it: flagged and switched
        // off, with what it was kept so that restoring puts it back as it was.
        if (ws_change_binned('product')) {
            db("UPDATE products SET recycled = '1', recycled_enabled = enabled, enabled = '0' WHERE id = '" . $id . "' AND recycled = '0'");

            db("DELETE FROM recycle_bin WHERE (item_type = 'product') AND (item_id = '" . $id . "')");

            db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                VALUES ('product', '" . $id . "', '0', UNIX_TIMESTAMP(), '" . (int) $viewer['id'] . "')");

            ws_change_log(lang(array('string' => 'The product, {var:1}, was moved to the Recycle Bin.', 'vars' => h($record['name']))));

            if (function_exists('pg_marketplace_product_changed')) {
                pg_marketplace_product_changed($id);
            }

            return array('ok' => true, 'error' => '', 'id' => $id, 'binned' => true);
        }

        if (!function_exists('pg_pb_delete_product')) {
            return array('ok' => false, 'error' => lang('The product could not be deleted.'), 'id' => 0);
        }

        // The panel's own deletion: the row, its images, group links, zones,
        // attributes, barcodes, form fields, keywords, SEO findings, and the
        // product.deleted webhook.
        pg_pb_delete_product($id);

        ws_change_log(lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($record['name']))));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    $set = array();

    foreach ($values as $name => $value) {
        $column = $fields[$name][3];

        switch ($fields[$name][0]) {
            case 'bool':
                $set[] = $column . " = '" . ($value ? '1' : '0') . "'";
                break;

            case 'money':
                $set[] = $column . " = '" . (int) $value . "'";
                break;

            default:
                $set[] = $column . " = '" . e($value) . "'";
        }
    }

    // Every one of these fields feeds the meta half of the SEO score.
    $set[] = "seo_analysis_current = '0'";
    $set[] = "user = '" . (int) $viewer['id'] . "'";
    $set[] = "timestamp = UNIX_TIMESTAMP()";

    db("UPDATE products SET " . implode(', ', $set) . " WHERE id = '" . (int) $record['id'] . "' LIMIT 1");

    // Site search keeps its own index of the promoted keywords.
    $indexed = db_item("SELECT name, keywords, enabled FROM products WHERE id = '" . (int) $record['id'] . "' LIMIT 1");

    if (is_array($indexed) && function_exists('pg_pb_sync_tag_cloud_keywords')) {
        pg_pb_sync_tag_cloud_keywords((int) $record['id'], $indexed['keywords'], ((int) $indexed['enabled'] === 1));
    }

    ws_change_log(lang(array('string' => 'Product ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => $record['name'])));

    pg_announce('product.updated', array('id' => (int) $record['id'], 'name' => (string) ($indexed['name'] ?? $record['name'])));

    if (function_exists('pg_marketplace_product_changed')) {
        pg_marketplace_product_changed((int) $record['id']);
    }

    return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
}

/**
 * The stock level, set as a count (the external API's op=set): tracking is
 * switched on with it.
 *
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_stock($viewer, $action, $record, $values)
{
    $quantity = max(0, (int) ($values['quantity'] ?? 0));

    // out_of_stock first: MySQL assigns left to right.
    db("UPDATE products
        SET out_of_stock = '" . (($quantity <= 0) ? '1' : '0') . "',
            inventory_quantity = '" . $quantity . "',
            inventory = '1',
            timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $record['id'] . "'
        LIMIT 1");

    ws_change_log(lang(array('string' => 'Stock for product ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => $record['name'])));

    pg_announce('inventory.changed', array(
        'product_id'   => (int) $record['id'],
        'quantity'     => $quantity,
        'out_of_stock' => ($quantity <= 0),
    ));

    // Crossing the low-stock line, announced once.
    $threshold = (defined('ECOMMERCE_LOW_STOCK_THRESHOLD') && ((int) ECOMMERCE_LOW_STOCK_THRESHOLD > 0)) ? (int) ECOMMERCE_LOW_STOCK_THRESHOLD : 0;

    if (($threshold > 0) && ($quantity <= $threshold) && (((int) $record['inventory'] !== 1) || ((int) $record['inventory_quantity'] > $threshold))) {
        pg_announce('stock.low', array(
            'product_id' => (int) $record['id'],
            'name'       => (string) $record['name'],
            'quantity'   => $quantity,
            'threshold'  => $threshold,
        ));
    }

    if (function_exists('pg_marketplace_product_changed')) {
        pg_marketplace_product_changed((int) $record['id']);
    }

    return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
}

/**
 * The status and the notes. Cancelling goes through the store's own
 * cancellation, which refunds, records who and why, and mails the customer.
 *
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_order($viewer, $action, $record, $values)
{
    $status = $values['status'] ?? null;

    if (($status === 'cancelled') && ($record['status'] !== 'cancelled')) {
        if (!function_exists('process_order_cancellation')) {
            return array('ok' => false, 'error' => lang('Orders cannot be cancelled on this site.'), 'id' => 0);
        }

        $reason = trim((string) ($values['cancellation_reason'] ?? ''));
        $outcome = process_order_cancellation((int) $record['id'], ($reason !== '') ? $reason : lang('Cancelled in the Workspace, on Claude\'s proposal.'), true, (int) $viewer['id']);

        if (!in_array($outcome['status'], array('ok', 'success', 'cancelled', 'already'), true)) {
            return array('ok' => false, 'error' => (string) ($outcome['message'] ?? lang('The order could not be cancelled.')), 'id' => 0);
        }
    } elseif (($status !== null) && ($status !== $record['status'])) {
        db("UPDATE orders SET status = '" . e($status) . "' WHERE id = '" . (int) $record['id'] . "' LIMIT 1");

        pg_announce('order.status_changed', array(
            'id'           => (int) $record['id'],
            'order_number' => $record['order_number'],
            'status'       => $status,
            'previous'     => $record['status'],
        ));
    }

    if (array_key_exists('notes', $values)) {
        db("UPDATE orders SET notes = '" . e($values['notes']) . "' WHERE id = '" . (int) $record['id'] . "' LIMIT 1");
    }

    ws_change_log(lang(array('string' => 'Order ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => $record['order_number'])));

    return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
}

/**
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_contact($viewer, $action, $record, $values)
{
    $fields = ws_change_types()['contact']['fields'];

    if ($action === 'delete') {
        // What the contact screen's delete removes.
        db("DELETE FROM contacts WHERE id = '" . (int) $record['id'] . "'");
        db("DELETE FROM contacts_contact_groups_xref WHERE contact_id = '" . (int) $record['id'] . "'");
        db("DELETE FROM opt_in WHERE contact_id = '" . (int) $record['id'] . "'");

        ws_change_log(lang(array('string' => 'Contact ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'vars' => (int) $record['id'])));

        return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
    }

    $set = array();

    foreach ($values as $name => $value) {
        if (isset($fields[$name])) {
            $set[] = $fields[$name][3] . " = '" . e($value) . "'";
        }
    }

    $set[] = "timestamp = '" . time() . "'";

    if ($action === 'create') {
        $set[] = "user = '" . (int) $viewer['id'] . "'";

        db("INSERT INTO contacts SET " . implode(', ', $set));
        $id = (int) mysqli_insert_id(db::$con);

        if ($id <= 0) {
            return array('ok' => false, 'error' => lang('The contact could not be saved.'), 'id' => 0);
        }

        ws_change_log(lang(array('string' => 'Contact ({var:1}) was added in the Workspace, on Claude\'s proposal.', 'vars' => $id)));

        if (function_exists('pg_announce_contact_created')) {
            pg_announce_contact_created($id, (string) ($values['email'] ?? ''));
        } else {
            pg_announce('customer.created', array('id' => $id, 'email' => (string) ($values['email'] ?? '')));
        }

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    db("UPDATE contacts SET " . implode(', ', $set) . " WHERE id = '" . (int) $record['id'] . "' LIMIT 1");

    ws_change_log(lang(array('string' => 'Contact ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => (int) $record['id'])));

    $email = (string) db_value("SELECT email_address FROM contacts WHERE id = '" . (int) $record['id'] . "'");

    pg_announce('customer.updated', array('id' => (int) $record['id'], 'email' => $email));

    return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
}

/**
 * Through the ERP module's own save, with its tax number rules.
 *
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_erp_account($viewer, $action, $record, $values)
{
    if (!function_exists('erp_account_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    }

    if ($action === 'create') {
        $data = array(
            'id'         => 0,
            'kind'       => (string) ($values['kind'] ?? 'customer'),
            'is_person'  => !empty($values['is_person']),
            'status'     => 'active',
            'created_by' => (int) $viewer['id'],
        );
    } else {
        $data = array(
            'id'           => (int) $record['id'],
            'title'        => (string) $record['title'],
            'kind'         => (string) $record['kind'],
            'is_person'    => ((int) $record['is_person'] === 1),
            'tax_number'   => (string) $record['tax_number'],
            'tax_office'   => (string) $record['tax_office'],
            'email'        => (string) $record['email'],
            'phone'        => (string) $record['phone'],
            'address'      => (string) $record['address'],
            'district'     => (string) $record['district'],
            'city'         => (string) $record['city'],
            'state'        => (string) ($record['state'] ?? ''),
            'country_code' => (string) $record['country_code'],
            'postcode'     => (string) $record['postcode'],
            'currency'     => (string) $record['currency'],
            'status'       => (string) $record['status'],
            'notes'        => (string) $record['notes'],
            'payment_days' => (int) ($record['payment_days'] ?? 0),
        );

        if (array_key_exists('overdue_notify_days', $record)) {
            $data['overdue_notify_days'] = (int) $record['overdue_notify_days'];
        }

        if (array_key_exists('overdue_notify_customer', $record)) {
            $data['overdue_notify_customer'] = ((int) $record['overdue_notify_customer'] === 1);
        }
    }

    foreach ($values as $name => $value) {
        if (isset(ws_change_types()['erp_account']['fields'][$name])) {
            $data[$name] = $value;
        }
    }

    if (trim((string) ($data['title'] ?? '')) === '') {
        return array('ok' => false, 'error' => lang('Enter a name.'), 'id' => 0);
    }

    $country = (string) ($data['country_code'] ?? (function_exists('erp_default_country_code') ? erp_default_country_code() : ''));
    $tax = erp_tax_number_check((string) ($data['tax_number'] ?? ''), $country);

    if ($tax['error'] !== '') {
        return array('ok' => false, 'error' => $tax['error'], 'id' => 0);
    }

    $data['tax_number'] = $tax['value'];

    if (array_key_exists('tax_number', $values) && ($data['tax_number'] !== '')) {
        $taken = (int) db_value("SELECT id FROM erp_accounts
            WHERE tax_number = '" . e($data['tax_number']) . "' AND status = 'active' AND id <> '" . (int) ($record['id'] ?? 0) . "'
            ORDER BY id ASC LIMIT 1");

        if ($taken > 0) {
            return array('ok' => false, 'error' => lang(array('string' => 'An account with this tax number already exists (#{var:1}).', 'vars' => $taken)), 'id' => 0);
        }
    }

    // In Turkey the province is the city: a state fills an empty city.
    if (function_exists('erp_account_country') && (erp_account_country($country) === 'TR') && array_key_exists('state', $data)) {
        if ((trim((string) ($data['city'] ?? '')) === '') && (trim((string) $data['state']) !== '')) {
            $data['city'] = trim((string) $data['state']);
        }

        $data['state'] = '';
    }

    $saved = erp_account_save($data);

    if (empty($saved['success'])) {
        return array('ok' => false, 'error' => (string) ($saved['error'] ?? lang('The account could not be saved.')), 'id' => 0);
    }

    $id = (int) ($saved['id'] ?? ($record['id'] ?? 0));

    ws_change_log(lang(array(
        'string' => ($action === 'create') ? 'ERP account #{var:1} ({var:2}) was added in the Workspace, on Claude\'s proposal.' : 'ERP account #{var:1} ({var:2}) was changed in the Workspace, on Claude\'s proposal.',
        'vars'   => array($id, $data['title']),
    )));

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * A product group: made, changed, deleted, or products put into it or taken
 * out of it, the way the product group screens do it.
 *
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_product_group($viewer, $action, $record, $values)
{
    $fields = ws_change_types()['product_group']['fields'];

    if ($action === 'create') {
        $parent = (int) (((array) ($values['parent_id'] ?? array()))[0] ?? 0);

        // Under the top of the catalogue when no parent is named.
        if ($parent <= 0) {
            $parent = (int) db_value("SELECT id FROM product_groups WHERE parent_id = '0' ORDER BY id LIMIT 1");
        }

        $short = (string) ($values['short_description'] ?? '');
        $address = function_exists('prepare_catalog_item_address_name') ? prepare_catalog_item_address_name(($short !== '') ? $short : $values['name']) : '';
        $full = (string) ($values['full_description'] ?? '');

        if (function_exists('prepare_rich_text_editor_content_for_input')) {
            $full = prepare_rich_text_editor_content_for_input($full);
        }

        db("INSERT INTO product_groups (name, enabled, parent_id, short_description, full_description, details, code, keywords, image_name,
                display_type, address_name, title, meta_description, meta_keywords, attributes, user, timestamp)
            VALUES (
                '" . e($values['name']) . "',
                '" . (!empty($values['enabled']) ? '1' : '0') . "',
                '" . $parent . "',
                '" . e($short) . "',
                '" . e($full) . "',
                '', '',
                '" . e((string) ($values['keywords'] ?? '')) . "',
                '',
                'browse',
                '" . e($address) . "',
                '" . e((string) ($values['title'] ?? '')) . "',
                '" . e((string) ($values['meta_description'] ?? '')) . "',
                '" . e((string) ($values['meta_keywords'] ?? '')) . "',
                '1',
                '" . (int) $viewer['id'] . "',
                UNIX_TIMESTAMP())");

        $id = (int) mysqli_insert_id(db::$con);

        if ($id <= 0) {
            return array('ok' => false, 'error' => lang('The product group could not be created.'), 'id' => 0);
        }

        foreach ((array) ($values['product_ids'] ?? array()) as $product_id) {
            db("INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES ('" . (int) $product_id . "', '" . $id . "', '0')");
        }

        ws_change_group_clouds($id);
        ws_change_log(lang(array('string' => 'product group ({var:1}) was created', 'vars' => $values['name'])));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    if ($action === 'delete') {
        $id = (int) $record['id'];

        // To the Recycle Bin, as the file manager bins a group. A group with
        // groups inside it is not proposed, so it goes alone; its products stay
        // where else they are listed.
        if (ws_change_binned('product_group')) {
            db("UPDATE product_groups SET recycled = '1', recycled_enabled = enabled, enabled = '0' WHERE id = '" . $id . "' AND recycled = '0'");

            db("DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id = '" . $id . "')");

            db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                VALUES ('product_group', '" . $id . "', '" . (int) $record['parent_id'] . "', UNIX_TIMESTAMP(), '" . (int) $viewer['id'] . "')");

            ws_change_log(lang(array('string' => 'The product group, {var:1}, was moved to the Recycle Bin.', 'vars' => h($record['name']))));

            return array('ok' => true, 'error' => '', 'id' => $id, 'binned' => true);
        }

        // What the group screen's delete does: the search clouds that gather
        // the group come down, the group and its links go, the clouds are
        // built again without it.
        $pages = ws_change_group_clouds($id, true);

        db("DELETE FROM product_groups_images_xref WHERE product_group = '" . $id . "'");
        db("DELETE FROM product_groups WHERE id = '" . $id . "'");
        db("DELETE FROM products_groups_xref WHERE product_group = '" . $id . "'");
        db("DELETE FROM product_groups_attributes_xref WHERE product_group_id = '" . $id . "'");
        db("DELETE FROM short_links WHERE (destination_type = 'product_group') AND (product_group_id = '" . $id . "')");

        foreach ($pages as $page) {
            update_tag_cloud_keywords_for_search_results_page_product_group($page['page_id'], $page['product_group_id']);
        }

        ws_change_log(lang(array('string' => '{var:1} ({var:2}) was deleted', 'vars' => array(lang('product group'), $record['name']))));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    $id = (int) $record['id'];

    if (($action === 'add') || ($action === 'remove')) {
        $members = ws_change_group_members($id);

        foreach ((array) ($values['product_ids'] ?? array()) as $product_id) {
            $product_id = (int) $product_id;

            if (($action === 'add') && !in_array($product_id, $members, true)) {
                db("INSERT INTO products_groups_xref (product, product_group, sort_order) VALUES ('" . $product_id . "', '" . $id . "', '0')");
            }

            if (($action === 'remove') && in_array($product_id, $members, true)) {
                db("DELETE FROM products_groups_xref WHERE product = '" . $product_id . "' AND product_group = '" . $id . "'");
            }
        }

        ws_change_group_clouds($id);
        ws_change_log(lang(array('string' => 'Products of product group ({var:1}) were changed in the Workspace, on Claude\'s proposal.', 'vars' => $record['name'])));
        pg_announce('product_group.updated', array('id' => $id, 'name' => (string) $record['name']));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    $set = array();

    foreach ($values as $name => $value) {
        if (!isset($fields[$name]) || ($name === 'enabled')) {
            continue;
        }

        if (($name === 'full_description') && function_exists('prepare_rich_text_editor_content_for_input')) {
            $value = prepare_rich_text_editor_content_for_input($value);
        }

        $set[] = $fields[$name][3] . " = '" . e($value) . "'";
    }

    if (!empty($set)) {
        $set[] = "seo_analysis_current = '0'";
        $set[] = "user = '" . (int) $viewer['id'] . "'";
        $set[] = "timestamp = UNIX_TIMESTAMP()";

        db("UPDATE product_groups SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");
    }

    // Publishing reaches the groups inside and their products, the way the
    // group screen and the external API do it.
    if (array_key_exists('enabled', $values) && ((bool) $values['enabled'] !== ((int) $record['enabled'] === 1))) {
        if (!function_exists('update_product_group_status') && file_exists(PG_FUNCTIONS_DIR . '/update_product_group_status.php')) {
            require_once(PG_FUNCTIONS_DIR . '/update_product_group_status.php');
        }

        if (function_exists('update_product_group_status')) {
            update_product_group_status(array('id' => $id, 'status' => $values['enabled'] ? 'enabled' : 'disabled', 'user' => (int) $viewer['id']));
        }
    }

    ws_change_group_clouds($id);

    if (!function_exists('pg_seo_schema_ready') && file_exists(PG_FUNCTIONS_DIR . '/seo.php')) {
        require_once(PG_FUNCTIONS_DIR . '/seo.php');
    }

    if (function_exists('pg_seo_schema_ready') && pg_seo_schema_ready()) {
        pg_seo_recalculate('product_group', array($id));
    }

    ws_change_log(lang(array('string' => 'Product group ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => $record['name'])));
    pg_announce('product_group.updated', array('id' => $id, 'name' => (string) $record['name']));

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * A channel's summary, as the summary tab writes it.
 *
 * @param array      $viewer
 * @param string     $action
 * @param array|null $record
 * @param array      $values
 * @return array ok, error, id
 */
function ws_change_write_channel($viewer, $action, $record, $values)
{
    $result = ws_channel_set_summary($viewer, $record, (string) ($values['summary'] ?? ''));

    return array('ok' => $result['ok'], 'error' => $result['error'], 'id' => (int) $record['id']);
}

/**
 * A person's role, as the user screen writes it.
 *
 * @return array ok, error, id
 */
function ws_change_write_user($viewer, $action, $record, $values)
{
    $role = (int) $values['role'];

    db("UPDATE user SET user_role = '" . $role . "', user_user = '" . (int) $viewer['id'] . "', user_timestamp = UNIX_TIMESTAMP()
        WHERE user_id = '" . (int) $record['id'] . "' AND user_role <> '0' LIMIT 1");

    ws_change_log(lang(array(
        'string' => 'User ({var:1}) was given the role {var:2} in the Workspace, on Claude\'s proposal.',
        'vars'   => array((string) $record['user_username'], pg_user_role_name($role)),
    )));

    return array('ok' => true, 'error' => '', 'id' => (int) $record['id']);
}

/**
 * A page's details, or the page into the recycle bin, as the external API
 * does both.
 *
 * @return array ok, error, id, binned
 */
function ws_change_write_page($viewer, $action, $record, $values)
{
    $id = (int) $record['id'];

    if ($action === 'delete') {
        $bin_id = ws_change_recycle_ready() ? (int) pg_recycle_folder_id(true) : 0;

        if ($bin_id <= 0) {
            return array('ok' => false, 'error' => lang('This site has no recycle bin, so a page is not deleted from here.'), 'id' => $id);
        }

        db("UPDATE page SET page_folder = '" . $bin_id . "', page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int) $viewer['id'] . "'
            WHERE page_id = '" . $id . "' LIMIT 1");

        // The address goes out of circulation with the page, so a new page can
        // take the name while this one waits in the bin.
        pg_recycle_park_name('page', $id, array('id' => (int) $viewer['id']));

        db("DELETE FROM recycle_bin WHERE item_type = 'page' AND item_id = '" . $id . "'");
        db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
            VALUES ('page', '" . $id . "', '" . (int) $record['page_folder'] . "', UNIX_TIMESTAMP(), '" . (int) $viewer['id'] . "')");

        ws_change_log(lang(array('string' => 'Page ({var:1}) was moved to the recycle bin in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['page_name'])));

        return array('ok' => true, 'error' => '', 'id' => $id, 'binned' => true);
    }

    $fields = ws_change_types()['page']['fields'];
    $set = array();

    foreach ($values as $name => $value) {
        if (isset($fields[$name])) {
            $set[$fields[$name][3]] = is_bool($value) ? ($value ? 1 : 0) : $value;
        }
    }

    // Closed to search engines, a page leaves the site map; opened again, it
    // follows links again (the page screen's rule).
    if (array_key_exists('noindex', $set)) {
        if ((int) $set['noindex'] === 1) {
            $set['sitemap'] = 0;
        } else {
            $set['nofollow'] = 0;
        }
    }

    $parts = array();

    foreach ($set as $column => $value) {
        $parts[] = $column . " = '" . e((string) $value) . "'";
    }

    if (isset($record['seo_analysis_current'])) {
        $parts[] = "seo_analysis_current = '0'";
    }

    db("UPDATE page SET " . implode(', ', $parts) . ", page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int) $viewer['id'] . "'
        WHERE page_id = '" . $id . "' LIMIT 1");

    // Site search keeps its own index of the promoted keywords.
    if (function_exists('update_tag_cloud_keywords_for_page') && (array_key_exists('page_search', $set) || array_key_exists('page_search_keywords', $set))) {
        update_tag_cloud_keywords_for_page(
            $id,
            $set['page_search'] ?? $record['page_search'],
            $set['page_search_keywords'] ?? $record['page_search_keywords'],
            $record['page_search'],
            $record['page_search_keywords']);
    }

    // The score follows the details at once, as after the API's write.
    if (file_exists(PG_FUNCTIONS_DIR . '/seo.php')) {
        require_once(PG_FUNCTIONS_DIR . '/seo.php');

        if (function_exists('pg_seo_schema_ready') && pg_seo_schema_ready() && function_exists('pg_seo_recalculate')) {
            pg_seo_recalculate('page', array($id));
        }
    }

    ws_change_log(lang(array('string' => 'Page ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['page_name'])));

    pg_announce('page.updated', array('id' => $id, 'name' => (string) $record['page_name']));

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * A file's description, folder or words, or the file into the recycle bin,
 * as the file manager does them.
 *
 * @return array ok, error, id, binned
 */
function ws_change_write_file($viewer, $action, $record, $values)
{
    $id = (int) $record['id'];

    if ($action === 'delete') {
        $bin_id = ws_change_recycle_ready() ? (int) pg_recycle_folder_id(true) : 0;

        if ($bin_id <= 0) {
            return array('ok' => false, 'error' => lang('This site has no recycle bin, so a file is not deleted from here.'), 'id' => $id);
        }

        db("UPDATE files SET folder = '" . $bin_id . "' WHERE id = '" . $id . "' LIMIT 1");

        // The name goes out of circulation with the row, so the same file can
        // be uploaded again while this one waits in the bin.
        pg_recycle_park_name('file', $id, array('id' => (int) $viewer['id']));

        db("DELETE FROM recycle_bin WHERE item_type = 'file' AND item_id = '" . $id . "'");
        db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
            VALUES ('file', '" . $id . "', '" . (int) $record['folder'] . "', UNIX_TIMESTAMP(), '" . (int) $viewer['id'] . "')");

        ws_change_log(lang(array('string' => 'File ({var:1}) was moved to the recycle bin in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['name'])));

        return array('ok' => true, 'error' => '', 'id' => $id, 'binned' => true);
    }

    $parts = array();

    if (array_key_exists('description', $values)) {
        $parts[] = "description = '" . e((string) $values['description']) . "'";
    }

    if (array_key_exists('folder_id', $values)) {
        $parts[] = "folder = '" . (int) $values['folder_id'] . "'";
    }

    // The words of a text file, written in place: its name and address stay.
    if (array_key_exists('content', $values)) {
        $path = FILE_DIRECTORY_PATH . '/' . $record['name'];
        $text = (string) $values['content'];

        if (substr((string) $record['content'], -1) === "\n") {
            $text .= "\n";
        }

        if (@file_put_contents($path, $text, LOCK_EX) === false) {
            return array('ok' => false, 'error' => lang('The file could not be saved.'), 'id' => $id);
        }

        clearstatcache(true, $path);
        $parts[] = "size = '" . (int) @filesize($path) . "'";
    }

    db("UPDATE files SET " . implode(', ', $parts) . ", timestamp = UNIX_TIMESTAMP(), user = '" . (int) $viewer['id'] . "'
        WHERE id = '" . $id . "' LIMIT 1");

    ws_change_log(lang(array('string' => 'File ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['name'])));

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * An offer switched on or off, described or dated, or deleted, as the offer
 * screen does it.
 *
 * @return array ok, error, id
 */
function ws_change_write_offer($viewer, $action, $record, $values)
{
    $id = (int) $record['id'];

    if ($action === 'delete') {
        if (!function_exists('_pg_offer_delete')) {
            require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
        }

        $deleted = _pg_offer_delete($id, null, lang(array('string' => 'Offer ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['code'])));

        return $deleted ? array('ok' => true, 'error' => '', 'id' => $id) : array('ok' => false, 'error' => lang('The offer could not be found.'), 'id' => $id);
    }

    $fields = ws_change_types()['offer']['fields'];
    $parts = array();

    foreach ($values as $name => $value) {
        if (isset($fields[$name])) {
            $parts[] = $fields[$name][3] . " = '" . e((string) $value) . "'";
        }
    }

    db("UPDATE offers SET " . implode(', ', $parts) . ", user = '" . (int) $viewer['id'] . "', timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $id . "' LIMIT 1");

    ws_change_log(lang(array('string' => 'Offer ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['code'])));

    return array('ok' => true, 'error' => '', 'id' => $id);
}

/**
 * A calendar event: a new one on the calendars named, a change to one, or
 * one deleted with what goes with it, as the event screens do.
 *
 * @return array ok, error, id
 */
function ws_change_write_calendar_event($viewer, $action, $record, $values)
{
    if ($action === 'delete') {
        $id = (int) $record['id'];

        db("DELETE FROM calendar_events WHERE id = '" . $id . "'");
        db("DELETE FROM calendar_event_exceptions WHERE calendar_event_id = '" . $id . "'");
        db("DELETE FROM calendar_events_calendars_xref WHERE calendar_event_id = '" . $id . "'");
        db("DELETE FROM calendar_events_calendar_event_locations_xref WHERE calendar_event_id = '" . $id . "'");
        db("DELETE FROM remaining_reservation_spots WHERE calendar_event_id = '" . $id . "'");

        ws_change_log(lang(array('string' => 'Calendar event ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['name'])));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    $fields = ws_change_types()['calendar_event']['fields'];
    $set = array();

    foreach ($values as $name => $value) {
        if (isset($fields[$name])) {
            $set[$fields[$name][3]] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
    }

    // An event without an end ends when it starts.
    if (isset($set['start_time']) && !isset($set['end_time']) && ($action === 'create')) {
        $set['end_time'] = $set['start_time'];
    }

    if ($action === 'create') {
        // The event screen's starting values for everything this does not set.
        $set += array(
            'published' => '0', 'unpublish_days' => '45', 'short_description' => '', 'full_description' => '', 'notes' => '',
            'all_day' => '0', 'show_start_time' => '1', 'show_end_time' => '1', 'recurrence' => '0', 'recurrence_number' => '1',
            'recurrence_type' => 'day', 'recurrence_day_sun' => '1', 'recurrence_day_mon' => '1', 'recurrence_day_tue' => '1',
            'recurrence_day_wed' => '1', 'recurrence_day_thu' => '1', 'recurrence_day_fri' => '1', 'recurrence_day_sat' => '1',
            'recurrence_month_type' => 'day_of_the_month', 'location' => '', 'reservations' => '0', 'separate_reservations' => '1',
            'limit_reservations' => '1', 'number_of_initial_spots' => '1',
            'no_remaining_spots_message' => '<p>' . lang('Sorry, we are not accepting any more reservations at this time.') . '</p>',
            'reserve_button_label' => lang('Reserve'), 'product_id' => '0', 'next_page_id' => '0',
        );
        $set['created_user_id'] = (int) $viewer['id'];
        $set['last_modified_user_id'] = (int) $viewer['id'];

        $parts = array();

        foreach ($set as $column => $value) {
            $parts[] = $column . " = '" . e((string) $value) . "'";
        }

        db("INSERT INTO calendar_events SET " . implode(', ', $parts) . ", created_timestamp = UNIX_TIMESTAMP(), last_modified_timestamp = UNIX_TIMESTAMP()");
        $id = (int) mysqli_insert_id(db::$con);

        if ($id <= 0) {
            return array('ok' => false, 'error' => lang('The event could not be saved.'), 'id' => 0);
        }

        foreach ((array) ($values['calendar_ids'] ?? array()) as $calendar_id) {
            if (ws_change_calendar_access($viewer, $calendar_id)) {
                db("INSERT INTO calendar_events_calendars_xref (calendar_event_id, calendar_id) VALUES ('" . $id . "', '" . (int) $calendar_id . "')");
            }
        }

        ws_change_log(lang(array('string' => 'Calendar event ({var:1}) was added in the Workspace, on Claude\'s proposal.', 'vars' => (string) $set['name'])));

        return array('ok' => true, 'error' => '', 'id' => $id);
    }

    $id = (int) $record['id'];
    $parts = array();

    foreach ($set as $column => $value) {
        $parts[] = $column . " = '" . e($value) . "'";
    }

    db("UPDATE calendar_events SET " . implode(', ', $parts) . ", last_modified_user_id = '" . (int) $viewer['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $id . "' LIMIT 1");

    ws_change_log(lang(array('string' => 'Calendar event ({var:1}) was changed in the Workspace, on Claude\'s proposal.', 'vars' => (string) $record['name'])));

    return array('ok' => true, 'error' => '', 'id' => $id);
}
