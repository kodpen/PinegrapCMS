<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Short Links list (view_short_links.php) and its quick edit: every
 * value the short link screen (edit_short_link.php) changes is a field,
 * checked and written by that screen's own form code
 * (includes/forms/short_link_form.php). The destination is shown in two
 * columns, the page and what on it (or the URL or file), so each value is
 * one field.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/short_link_form.php and view_folder_and_files_f.php.
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
 * The destination types, as the list names them.
 *
 * @return array type => label
 */
function pg_short_link_list_type_names()
{
    return array(
        'page' => lang('Page'),
        'product_group' => lang('Product Group'),
        'product' => lang('Product'),
        'url' => lang('URL'),
        'file' => lang('File'),
    );
}

/**
 * A pick list of the forms (label => value, the label escaped, or rows of
 * 'label' and 'value') as value => plain label, without its blank first
 * choice. Pure.
 *
 * @param array $options
 * @return array value => label
 */
function pg_short_link_quick_pairs($options)
{
    $pairs = array();

    foreach ((array) $options as $key => $option) {
        if (is_array($option)) {
            $label = (string) ($option['label'] ?? '');
            $value = (string) ($option['value'] ?? '');
        } else {
            $label = (string) $key;
            $value = (string) $option;
        }

        if ($value === '') {
            continue;
        }

        $pairs[$value] = trim(html_entity_decode(strip_tags($label), ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B");
    }

    return $pairs;
}

/**
 * What the list's pickers offer, read once per request with the pickers
 * of the short link screen: pages, catalog and catalog detail pages,
 * product groups and products, none in the Recycle Bin. A link that still
 * points at one in the bin keeps it, but its cell stays text. Files are
 * not listed: the File cell searches them (kind file).
 *
 * @return array 'pages', 'group_pages', 'product_pages', 'groups',
 *               'products' (value => label), 'page_types'
 *               (page id => 'catalog' or 'catalog detail')
 */
function pg_short_link_quick_choices()
{
    static $read = null;

    if ($read !== null) {
        return $read;
    }

    $catalog = pg_short_link_quick_pairs(get_page_options('', 'catalog', 'edit', false));
    $detail = pg_short_link_quick_pairs(get_page_options('', 'catalog detail', 'edit', false));

    $read = array(
        'pages' => pg_short_link_quick_pairs(get_page_options('', '', 'edit', false)),
        'group_pages' => $catalog + $detail,
        'product_pages' => $detail,
        'groups' => pg_short_link_quick_pairs(pg_short_link_form_product_group_options(array())),
        'products' => pg_short_link_quick_pairs(get_product_options(false)),
        'page_types' => array_fill_keys(array_map('strval', array_keys($catalog)), 'catalog') + array_fill_keys(array_map('strval', array_keys($detail)), 'catalog detail'),
    );

    return $read;
}

/**
 * The quick edit fields, by the choices they offer. How it answers only
 * after the upgrade that adds its column. Pure but for lang().
 *
 * @param array $choices pg_short_link_quick_choices()
 * @param bool  $answers whether links have an answer (pg_short_link_redirects_ready())
 * @param int   $name_max pg_short_link_name_max()
 * @param int   $url_max  pg_short_link_url_max()
 * @return array field => definition
 */
function pg_short_link_quick_edit_columns(array $choices, $answers, $name_max, $url_max)
{
    $columns = array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => (int) $name_max, 'required' => true),
        'destination_type' => array('type' => 'select', 'label' => lang('Destination Type'), 'options' => pg_short_link_list_type_names()),
        'page_id' => array('type' => 'select', 'label' => lang('Page'), 'options' => $choices['pages'] ?? array()),
        'group_page_id' => array('type' => 'select', 'label' => lang('Page'), 'options' => $choices['group_pages'] ?? array()),
        'product_page_id' => array('type' => 'select', 'label' => lang('Catalog Detail Page'), 'options' => $choices['product_pages'] ?? array()),
        'product_group_id' => array('type' => 'select', 'label' => lang('Product Group'), 'options' => $choices['groups'] ?? array()),
        'product_id' => array('type' => 'select', 'label' => lang('Product'), 'options' => $choices['products'] ?? array()),
        'url' => array('type' => 'text', 'label' => lang('Destination URL'), 'max' => (int) $url_max, 'required' => true),
        'file_id' => array('type' => 'search', 'label' => lang('File'), 'source' => pg_file_picker_source('file'), 'required' => true),
        'tracking_code' => array('type' => 'text', 'label' => lang('Tracking Code'), 'max' => pg_short_link_form_tracking_code_max()),
    );

    if ($answers) {
        $columns['redirect_code'] = array('type' => 'select', 'label' => lang('How it answers'), 'options' => array_flip(pg_short_link_answer_options()));
    }

    return $columns;
}

/**
 * The list's columns. Page, Destination, Tracking Code and How it answers
 * each show one value of the link, so each is one field.
 *
 * @param bool $answers pg_short_link_redirects_ready()
 * @param bool $edit    whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_short_link_list_columns($answers, $edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'destination_type' => array('heading' => lang('Destination Type'), 'editable' => 'select'),
        'page' => array('heading' => lang('Page'), 'editable' => 'select'),
        'destination' => array('heading' => lang('Destination'), 'editable' => 'text'),
        'tracking_code' => array('heading' => lang('Tracking Code'), 'editable' => 'text'),
        'redirect_code' => array('heading' => lang('How it answers'), 'editable' => 'select'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        'visit' => array('heading' => lang('Visit'), 'kind' => 'actions'),
    );

    if (!$answers) {
        unset($columns['redirect_code']);
    }

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * The field a column's cell of a link changes, and the stored value it
 * opens with; null when the cell is text for this link. A one-time link
 * (no name) is changed in the File Manager, so none of its cells is a
 * field; the page and the destination follow the destination type. Pure.
 *
 * @param string $column a pg_short_link_list_columns() key
 * @param array  $link   short_links row
 * @return array|null array(field, value)
 */
function pg_short_link_quick_field($column, array $link)
{
    if ((string) ($link['name'] ?? '') === '') {
        return null;
    }

    $type = (string) ($link['destination_type'] ?? '');
    $page_types = array('page' => 'page_id', 'product_group' => 'group_page_id', 'product' => 'product_page_id');
    $targets = array('product_group' => 'product_group_id', 'product' => 'product_id', 'url' => 'url', 'file' => 'file_id');

    switch ($column) {
        case 'name':
            return array('name', (string) $link['name']);

        case 'destination_type':
            return array('destination_type', $type);

        case 'page':
            return isset($page_types[$type]) ? array($page_types[$type], (string) (int) $link['page_id']) : null;

        case 'destination':
            if (!isset($targets[$type])) {
                return null;
            }

            return array($targets[$type], ($type === 'url') ? (string) $link['url'] : (string) (int) $link[$targets[$type]]);

        case 'tracking_code':
            return isset($page_types[$type]) ? array('tracking_code', (string) $link['tracking_code']) : null;

        case 'redirect_code':
            return array_key_exists('redirect_code', $link) ? array('redirect_code', (string) (int) $link['redirect_code']) : null;
    }

    return null;
}

/**
 * The quick edit attributes of a link's cell: none when the cell is text
 * for the link, or when its stored choice is not offered (a page, group or
 * product in the Recycle Bin or out of the user's folders), so the list
 * never shows a picker that cannot hold the stored value. A searching
 * cell (the file) carries its stored choice's name instead; a file that no
 * longer exists has none, and its cell stays text. Pure.
 *
 * @param string $column
 * @param array  $link
 * @param array  $quick_edit field => definition, empty for none
 * @param string $text the stored choice as the cell shows it, plain text
 * @return array
 */
function pg_short_link_quick_attributes($column, array $link, array $quick_edit, $text = '')
{
    $field = pg_short_link_quick_field($column, $link);

    if (($field === null) || !isset($quick_edit[$field[0]])) {
        return array();
    }

    $type = (string) ($quick_edit[$field[0]]['type'] ?? '');

    if (($type === 'select') && !isset($quick_edit[$field[0]]['options'][$field[1]])) {
        return array();
    }

    if ($type === 'search') {
        if (((int) $field[1] > 0) && ((string) $text === '')) {
            return array();
        }

        return pg_list_edit_cell_attributes($quick_edit, $field[0], (int) $link['id'], $field[1], (string) $text);
    }

    return pg_list_edit_cell_attributes($quick_edit, $field[0], (int) $link['id'], $field[1]);
}

/**
 * The form's values for a change made on the list: the stored link as the
 * edit screen opens it (pg_short_link_form_values()) with the one changed
 * value put into the field the screen posts it in. A product group's page
 * goes to the catalog or the catalog detail picker by its type; a new
 * destination type takes the stored page into its own picker. Pure.
 *
 * @param array  $link       short_links row with page_type
 * @param string $field      a quick edit field
 * @param mixed  $value      the value read
 * @param array  $page_types page id => page type (pg_short_link_quick_choices())
 * @return array field => value
 */
function pg_short_link_quick_form_values(array $link, $field, $value, array $page_types)
{
    $value = (string) $value;

    switch ($field) {
        case 'destination_type':
            $link['destination_type'] = $value;
            break;

        case 'page_id':
        case 'product_page_id':
            $link['page_id'] = $value;
            break;

        case 'group_page_id':
            $link['page_id'] = $value;
            $link['page_type'] = $page_types[$value] ?? '';
            break;

        case 'file_id':
            $link['file_id'] = $value;
            break;

        case 'name':
        case 'product_group_id':
        case 'product_id':
        case 'url':
        case 'tracking_code':
        case 'redirect_code':
            $link[$field] = $value;
            break;
    }

    return pg_short_link_form_values($link);
}

/**
 * The list's query, without its ORDER BY: the links but a workspace guest's
 * way in and those in the Recycle Bin.
 *
 * @param int $id one link only, 0 for all
 * @return string
 */
function pg_short_link_list_sql($id = 0)
{
    $live = pg_short_link_live_filter();

    return "SELECT
            short_links.id,
            short_links.name,
            short_links.destination_type,
            short_links.page_id,
            short_links.product_group_id,
            short_links.product_id,
            short_links.file_id,
            " . (pg_short_link_modes_ready() ? "short_links.link_mode, short_links.token_hint, short_links.expires_at, short_links.used_at," : "") . "
            " . (pg_short_link_redirects_ready() ? "short_links.redirect_code, short_links.is_pattern," : "") . "
            page.page_name,
            page.page_type,
            page.page_folder AS folder_id,
            product_groups.address_name AS product_group_address_name,
            products.address_name AS product_address_name,
            files.name AS file_name,
            short_links.tracking_code,
            short_links.url,
            created_user.user_username AS created_username,
            short_links.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            short_links.last_modified_timestamp
        FROM short_links
        LEFT JOIN page ON short_links.page_id = page.page_id
        LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
        LEFT JOIN products ON short_links.product_id = products.id
        LEFT JOIN files ON short_links.file_id = files.id
        LEFT JOIN user AS created_user ON short_links.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON short_links.last_modified_user_id = last_modified_user.user_id
        WHERE short_links.destination_type <> 'workspace_guest'"
        . (($live !== '') ? ' AND ' . $live : '')
        . (((int) $id > 0) ? " AND short_links.id = '" . (int) $id . "'" : '');
}

/**
 * A link's cells on the list, with their quick edit attributes.
 *
 * @param array $short_link pg_short_link_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_short_link_list_cells(array $short_link, array $quick_edit)
{
    $type_names = pg_short_link_list_type_names();
    $type = (string) $short_link['destination_type'];

    // A destination in the Recycle Bin shows the name it had, marked, never
    // its parked pgbin_ name.
    $binned_original = pg_short_link_binned_original($short_link);
    $binned = ($binned_original !== '') ? ' [' . h(lang('IN RECYCLE BIN')) . ']' : '';

    if ($binned_original !== '') {
        if ($type === 'file') {
            $short_link['file_name'] = $binned_original;
        } else {
            $short_link['page_name'] = $binned_original;
        }
    }

    $output_page = '';
    $output_destination = '';

    // Names are text to read and search here, as the Pages and Files lists
    // show them: "pg-ğ" and "a#b", never their address form "pg-%C4%9F".
    if (in_array($type, array('page', 'product_group', 'product'), true)) {
        $output_page = h((string) $short_link['page_name']) . $binned;
    }

    switch ($type) {
        case 'product_group':
            $output_destination = h((string) $short_link['product_group_address_name']);
            break;

        case 'product':
            $output_destination = h((string) $short_link['product_address_name']);
            break;

        case 'url':
            $output_destination = h((string) $short_link['url']);
            break;

        case 'file':
            $output_destination = h((string) $short_link['file_name']) . $binned;
            break;
    }

    // A one-time link has a generated address, shown once when it was made:
    // it is known here by its first characters, and has no address to
    // visit. Opened once or until a time, it says where it stands.
    $token = ((string) $short_link['name'] === '');
    $state = pg_short_link_state($short_link);
    $pattern = !empty($short_link['is_pattern']);
    $output_name = $token ? h((string) ($short_link['token_hint'] ?? '')) . '…' : h($short_link['name']);

    if ($pattern) {
        $output_name .= ' <span class="badge rounded-pill badge-subtle theme-secondary border">' . h(lang('Wildcard')) . '</span>';
    }

    if ($state['label'] !== '') {
        $output_name .= '<div class="small fg-secondary">' . h($state['label']) . '</div>';
    }

    // The answer the link gives: a closing link answers 302 even when saved
    // with 301 (pg_short_link_status()).
    $answer = pg_short_link_answer_label(pg_short_link_status($short_link));
    $id = (int) $short_link['id'];
    // The file cell searches, so it carries the name its field opens with.
    $file_text = (($type === 'file') && ((string) ($short_link['file_name'] ?? '') !== ''))
        ? (string) $short_link['file_name'] . (($binned !== '') ? ' [' . lang('IN RECYCLE BIN') . ']' : '')
        : '';
    $cell = function ($column, $html) use ($short_link, $quick_edit, $file_text) {
        return pg_list_cell($html, 'align-middle', pg_short_link_quick_attributes($column, $short_link, $quick_edit, ($column === 'destination') ? $file_text : ''));
    };

    $cells = array(
        'name' => $cell('name', '<a href="edit_short_link.php?id=' . $id . '" data-pg-open>' . $output_name . '</a>'),
        'destination_type' => $cell('destination_type', h($type_names[$type] ?? '')),
        'page' => $cell('page', $output_page),
        'destination' => $cell('destination', $output_destination),
        'tracking_code' => $cell('tracking_code', h((string) $short_link['tracking_code'])),
        'redirect_code' => $cell('redirect_code', ($answer !== '') ? '<span class="badge rounded-pill badge-subtle theme-secondary border">' . h($answer) . '</span>' : ''),
        'created' => pg_list_cell(pg_list_when($short_link['created_timestamp'], $short_link['created_username'] ?? ''), 'align-middle', array('data-pg-sort-value' => (int) $short_link['created_timestamp'])),
        'last_modified' => pg_list_cell(pg_list_when($short_link['last_modified_timestamp'], $short_link['last_modified_username'] ?? ''), 'align-middle', array('data-pg-sort-value' => (int) $short_link['last_modified_timestamp'])),
        'visit' => pg_list_cell(($token || $pattern) ? '' : '<a href="' . OUTPUT_PATH . h(encode_url_path($short_link['name'])) . '" class="btn btn-sm btn-text theme-secondary" title="' . h(lang('Visit')) . '" aria-label="' . h(lang('Visit')) . '"><i class="bi bi-link" aria-hidden="true"></i></a>'),
    );

    if (!array_key_exists('redirect_code', $short_link)) {
        unset($cells['redirect_code']);
    }

    return $cells;
}

/**
 * The quick edit answer from a link read again: the edited cell and every
 * other cell of the row, each with the value its field opens with.
 *
 * @param array  $cells  pg_short_link_list_cells()
 * @param string $column the edited cell's column
 * @return array
 */
function pg_short_link_quick_saved(array $cells, $column)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        if (isset($cell['attributes']['data-pg-edit-text'])) {
            $out['text'] = (string) $cell['attributes']['data-pg-edit-text'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$column]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $column) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * The column a quick edit field's cell sits in. Pure.
 *
 * @param string $field
 * @return string
 */
function pg_short_link_quick_column($field)
{
    $columns = array(
        'name' => 'name',
        'destination_type' => 'destination_type',
        'page_id' => 'page', 'group_page_id' => 'page', 'product_page_id' => 'page',
        'product_group_id' => 'destination', 'product_id' => 'destination', 'url' => 'destination', 'file_id' => 'destination',
        'tracking_code' => 'tracking_code',
        'redirect_code' => 'redirect_code',
    );

    return $columns[$field] ?? '';
}

/**
 * Writes one value changed on the list through the short link screen's
 * own checks and save: the rewrite file must be writable, the name is read
 * as a pasted address and must be free, the destination must exist and not
 * be in the Recycle Bin (unless the link already points at it), and how it
 * answers is checked with the name and the destination.
 *
 * @param int    $id
 * @param string $field a pg_short_link_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_short_link_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $link = db_item(pg_short_link_list_sql($id));
    $column = pg_short_link_quick_column($field);

    if (!$link) {
        return $refuse(lang('Record not found.'));
    }

    // A cell drawn before a destination type change still names the old
    // type's field.
    $own = pg_short_link_quick_field($column, $link);

    if (($own === null) || ($own[0] !== $field)) {
        return $refuse(lang('The short link has changed since the list was loaded. Reload the list to change this value.'));
    }

    $handle = @fopen(HTACCESS_FILE_PATH, 'a');

    if ($handle === false) {
        return $refuse(lang(array('string' => '{var:1} is not writeable so your request could not be completed.', 'vars' => HTACCESS_FILE_NAME)));
    }

    @fclose($handle);

    $choices = pg_short_link_quick_choices();
    $form = new liveform('short_link_quick_edit');
    $form->remove_form();

    foreach (pg_short_link_quick_form_values($link, $field, $value, $choices['page_types']) as $form_field => $form_value) {
        $form->assign_field_value($form_field, $form_value);
    }

    $name = pg_short_link_form_validate_name($form, $id);
    $page_id = pg_short_link_form_validate_destination($form, $link);
    $answer = pg_short_link_form_answer($form, $id, $name, isset($link['redirect_code']) ? (int) $link['redirect_code'] : 0);

    if ($form->check_form_errors()) {
        $messages = array_values(array_filter($form->field_error_texts(), 'strlen'));
        $form->remove_form();

        return $refuse($messages ? $messages[0] : lang('The value could not be saved.'));
    }

    $saved = pg_short_link_form_save($form, $id, $name, $page_id, $answer, (int) $user['id']);
    $form->remove_form();

    if (!$saved) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'short link ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_short_link_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_short_link_quick_saved(pg_short_link_list_cells($row, $quick_edit), $column);
}
