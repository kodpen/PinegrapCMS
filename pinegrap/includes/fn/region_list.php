<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The region lists (view_regions.php: ad, login, dynamic, designer and
 * common regions) and the ads list (view_ads.php) printed whole on the list
 * table driver (browser mode, pg_list_table_printed()), and their quick
 * edit: the columns that change in place, the cells a row is drawn with and
 * the write each change makes. The checks are the ones the record's edit
 * screen runs (includes/forms/*_form.php), so the list and the screen
 * refuse the same values.
 *
 * Values the edit screen keeps in a code or rich text editor (a login
 * region's headers and footers, a region's content, a dynamic region's code,
 * an ad's content) span lines and markup a cell cannot hold: they are
 * changed on the edit screen only.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/login_region_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/dynamic_region_form.php');

/**
 * The list name of each region filter of view_regions.php: the name keeps
 * the sort in the session (pg_list_state()) and is in
 * pg_list_table_printed_lists().
 *
 * @return array filter => list name
 */
function pg_region_list_names()
{
    return array(
        'all_ad_regions' => 'view_regions_all_ad_regions',
        'all_login_regions' => 'view_regions_all_login_regions',
        'all_dynamic_regions' => 'view_regions_all_dynamic_regions',
        'all_designer_regions' => 'view_regions_all_designer_regions',
        'all_common_regions' => 'view_regions_all_common_regions',
    );
}

/**
 * Column specs with the quick edit input of each column that has a field:
 * a whole number gets the number input, the rest the input of their type.
 *
 * @param array $specs      key => spec, as pg_list_table_printed() takes them
 * @param array $quick_edit field => definition (pg_list_edit_table())
 * @return array key => spec
 */
function pg_region_list_specs(array $specs, array $quick_edit)
{
    foreach ($specs as $key => $spec) {
        $type = (string) ($quick_edit[$key]['type'] ?? '');

        if ($type === 'integer') {
            $specs[$key]['editable'] = 'number';
        } elseif (in_array($type, array('text', 'money', 'select'), true)) {
            $specs[$key]['editable'] = $type;
        }
    }

    return $specs;
}

/**
 * The choices of a yes/no column.
 *
 * @return array
 */
function pg_region_list_yes_no()
{
    return array('1' => lang('Yes'), '0' => lang('No'));
}

/**
 * A cell that may change in place: its html and class, with the quick edit
 * attributes when the field is offered.
 *
 * @param string $html       escaped
 * @param array  $quick_edit field => definition
 * @param string $field
 * @param int    $id
 * @param mixed  $value      the stored value the input opens with
 * @param array  $attributes more attributes (data-pg-sort-value)
 * @return array pg_list_cell()
 */
function pg_region_list_cell($html, array $quick_edit, $field, $id, $value, array $attributes = array())
{
    return pg_list_cell($html, 'align-middle', pg_list_edit_cell_attributes($quick_edit, $field, $id, $value) + $attributes);
}

/**
 * The answer to a refused quick edit.
 *
 * @param string $message
 * @return array
 */
function pg_region_list_refusal($message)
{
    return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
}

/**
 * The answer to a saved quick edit: the field's value, its cell and the
 * row's other cells, drawn from what is stored.
 *
 * @param array  $cells field => pg_list_cell() of the row
 * @param string $field
 * @return array
 */
function pg_region_list_saved(array $cells, $field)
{
    $cell = $cells[$field];
    $others = $cells;
    unset($others[$field]);

    return array(
        'ok' => true,
        'value' => (string) ($cell['attributes']['data-pg-edit-value'] ?? ''),
        'html' => $cell['html'],
        'class' => $cell['class'],
        'cells' => array_map(function ($other) {
            return array('html' => $other['html'], 'class' => $other['class']);
        }, $others),
    );
}

/* Ad regions */

/**
 * The ad region columns that change in place: every value of the ad
 * region's edit screen.
 *
 * @return array field => definition
 */
function pg_ad_region_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'display_type' => array('type' => 'select', 'label' => lang('Display Type'), 'options' => array('static' => lang('Static'), 'dynamic' => lang('Dynamic'))),
        'transition_type' => array('type' => 'select', 'label' => lang('Transition Type'), 'options' => array('slide' => lang('Slide'), 'fade' => lang('Fade'))),
        'transition_duration' => array('type' => 'integer', 'label' => lang('Transition Duration'), 'min' => 0, 'max' => 9999, 'empty' => true),
        'slideshow' => array('type' => 'select', 'label' => lang('Enable Autoplay'), 'options' => pg_region_list_yes_no()),
        'slideshow_interval' => array('type' => 'integer', 'label' => lang('Interval'), 'min' => 1, 'max' => 999, 'empty' => true),
        'slideshow_continuous' => array('type' => 'select', 'label' => lang('Play Continuously'), 'options' => pg_region_list_yes_no()),
    );
}

/**
 * The ad region list's columns.
 *
 * @return array key => spec
 */
function pg_ad_region_list_columns()
{
    return pg_region_list_specs(array(
        'name' => array('heading' => lang('Name')),
        'display_type' => array('heading' => lang('Display Type')),
        'transition_type' => array('heading' => lang('Transition Type')),
        'transition_duration' => array('heading' => lang('Transition Duration'), 'kind' => 'number'),
        'slideshow' => array('heading' => lang('Enable Autoplay'), 'kind' => 'check'),
        'slideshow_interval' => array('heading' => lang('Interval'), 'kind' => 'number'),
        'slideshow_continuous' => array('heading' => lang('Play Continuously'), 'kind' => 'check'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ), pg_ad_region_edit_columns());
}

/**
 * An ad region row's cells. The transition and autoplay values are shown
 * for a dynamic region only (a static one shows one ad per page view), and
 * the interval and continuous play only with autoplay on; their cells still
 * change in place, as the edit screen keeps them.
 *
 * @param array $row        ad_regions row with created_username and last_modified_username
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_ad_region_list_cells(array $row, array $quick_edit)
{
    $id = (int) $row['id'];
    $dynamic = ($row['display_type'] === 'dynamic');
    $autoplay = $dynamic && !empty($row['slideshow']);
    $duration = (int) $row['transition_duration'];
    $interval = (int) $row['slideshow_interval'];
    $types = array('slide' => lang('Slide'), 'fade' => lang('Fade'));

    return array(
        'name' => pg_list_cell('<a href="edit_ad_region.php?id=' . $id . '" data-pg-open>' . h($row['name']) . '</a>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, $row['name'])),
        'display_type' => pg_region_list_cell($dynamic ? h(lang('Dynamic')) : h(lang('Static')), $quick_edit, 'display_type', $id, $dynamic ? 'dynamic' : 'static'),
        'transition_type' => pg_region_list_cell($dynamic ? h($types[$row['transition_type']] ?? '') : '', $quick_edit, 'transition_type', $id, $row['transition_type']),
        'transition_duration' => pg_region_list_cell(($dynamic && $duration) ? pg_format_number($duration, 0) : '', $quick_edit, 'transition_duration', $id, $duration ? $duration : '', array('data-pg-sort-value' => $dynamic ? $duration : 0)),
        'slideshow' => pg_list_table_check_cell($autoplay),
        'slideshow_interval' => pg_region_list_cell(($autoplay && $interval) ? pg_format_number($interval, 0) : '', $quick_edit, 'slideshow_interval', $id, $interval ? $interval : '', array('data-pg-sort-value' => $autoplay ? $interval : 0)),
        'slideshow_continuous' => pg_list_table_check_cell($autoplay && !empty($row['slideshow_continuous'])),
        'created' => pg_list_table_when_cell($row['created_timestamp'], (string) ($row['created_username'] ?? '')),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) ($row['last_modified_username'] ?? '')),
    );
}

/**
 * Adds the quick edit attributes to the yes/no cells, which
 * pg_list_table_check_cell() draws without them.
 *
 * @param array $cells      key => pg_list_cell()
 * @param array $quick_edit field => definition
 * @param int   $id
 * @param array $values     field => 1/0 stored
 * @return array
 */
function pg_region_list_check_fields(array $cells, array $quick_edit, $id, array $values)
{
    foreach ($values as $field => $on) {
        $cells[$field]['attributes'] = pg_list_edit_cell_attributes($quick_edit, $field, $id, $on ? '1' : '0')
            + (isset($cells[$field]['attributes']) ? $cells[$field]['attributes'] : array());
        $cells[$field]['attributes'] = array_map('strval', $cells[$field]['attributes']);
    }

    return $cells;
}

/**
 * The ad regions read for the list: every one, or the one asked for.
 *
 * @param int $id 0 for all
 * @return array list of rows
 */
function pg_ad_region_list_rows($id = 0)
{
    return db_items(
        "SELECT
            ad_regions.id, ad_regions.name, ad_regions.display_type, ad_regions.transition_type,
            ad_regions.transition_duration, ad_regions.slideshow, ad_regions.slideshow_interval,
            ad_regions.slideshow_continuous, ad_regions.created_timestamp, ad_regions.last_modified_timestamp,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM ad_regions
        LEFT JOIN user AS created_user ON ad_regions.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON ad_regions.last_modified_user_id = last_modified_user.user_id
        " . ($id ? "WHERE ad_regions.id = '" . (int) $id . "'" : "") . "
        ORDER BY ad_regions.name") ?: array();
}

/**
 * One ad region read for the list.
 *
 * @param int $id
 * @return array|null
 */
function pg_ad_region_list_row($id)
{
    $rows = ((int) $id > 0) ? pg_ad_region_list_rows((int) $id) : array();

    return $rows ? $rows[0] : null;
}

/**
 * An ad region row's cells with every quick edit field.
 *
 * @param array $row
 * @param array $quick_edit
 * @return array
 */
function pg_ad_region_list_row_cells(array $row, array $quick_edit)
{
    return pg_region_list_check_fields(pg_ad_region_list_cells($row, $quick_edit), $quick_edit, $row['id'], array(
        'slideshow' => !empty($row['slideshow']),
        'slideshow_continuous' => !empty($row['slideshow_continuous']),
    ));
}

/**
 * Writes one ad region value changed on the list, checked as the edit
 * screen checks it (pg_ad_region_name_error(),
 * pg_ad_region_behavior_errors() on the region with the new value).
 *
 * @param int    $id
 * @param string $field a pg_ad_region_edit_columns() field
 * @param mixed  $value pg_list_edit_parse()'s value
 * @param int    $user_id
 * @return array the answer pg_list_edit_reply() sends
 */
function pg_ad_region_quick_update($id, $field, $value, $user_id)
{
    $quick_edit = pg_ad_region_edit_columns();
    $row = pg_ad_region_list_row($id);

    if (!$row || !isset($quick_edit[$field])) {
        return pg_region_list_refusal(lang('Record not found.'));
    }

    $value = (string) $value;

    if ($field === 'name') {
        $error = pg_ad_region_name_error($value, (string) $row['name'], function ($name) use ($id) {
            return (int) db_value("SELECT COUNT(*) FROM ad_regions WHERE name = '" . e($name) . "' AND id != '" . (int) $id . "'") > 0;
        });
    } else {
        $fields = array(
            'display_type' => $row['display_type'],
            'transition_type' => $row['transition_type'],
            'transition_duration' => ((int) $row['transition_duration']) ? (string) $row['transition_duration'] : '',
            'slideshow' => (string) (int) $row['slideshow'],
            'slideshow_interval' => ((int) $row['slideshow_interval']) ? (string) $row['slideshow_interval'] : '',
        );
        $fields[$field] = $value;
        $errors = pg_ad_region_behavior_errors($fields);
        $error = $errors ? (string) reset($errors) : '';
    }

    if ($error !== '') {
        return pg_region_list_refusal($error);
    }

    // An empty number is stored as 0 (the default), as the edit screen does.
    if (in_array($field, array('transition_duration', 'slideshow_interval'), true) && ($value === '')) {
        $value = '0';
    }

    db_q("UPDATE ad_regions SET `" . $field . "` = ?, last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?",
        array($value, (string) (int) $user_id, (string) (int) $id)) or output_error('Query failed.');

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('ad region'), ($field === 'name') ? $value : $row['name']))), $_SESSION['sessionusername'] ?? '');

    return pg_region_list_saved(pg_ad_region_list_row_cells(pg_ad_region_list_row($id), $quick_edit), $field);
}

/* Login regions */

/**
 * The login region columns that change in place. The headers and footers
 * are code (code editor) and change on the edit screen.
 *
 * @return array field => definition
 */
function pg_login_region_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'login_form' => array('type' => 'select', 'label' => lang('Show Login Form'), 'options' => pg_region_list_yes_no()),
    );
}

/**
 * The login region list's columns.
 *
 * @return array key => spec
 */
function pg_login_region_list_columns()
{
    return pg_region_list_specs(array(
        'name' => array('heading' => lang('Name')),
        'not_logged_in_header' => array('heading' => lang('Not Logged In Header')),
        'login_form' => array('heading' => lang('Show Login Form'), 'kind' => 'check'),
        'not_logged_in_footer' => array('heading' => lang('Not Logged In Footer')),
        'logged_in_header' => array('heading' => lang('Logged In Header')),
        'logged_in_footer' => array('heading' => lang('Logged In Footer')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ), pg_login_region_edit_columns());
}

/**
 * A preview of stored code: its first 50 characters, escaped, so stored
 * markup never renders in the list.
 *
 * @param string $text
 * @return string
 */
function pg_region_list_preview($text)
{
    $text = (string) $text;

    return h((mb_strlen($text) > 50) ? mb_substr($text, 0, 50) . '...' : $text);
}

/**
 * A preview of stored rich text (a common region): its text without the
 * markup, entities decoded and whitespace collapsed, then its first 50
 * characters escaped. A block tag ends a word, so paragraphs do not run
 * together.
 *
 * @param string|null $html
 * @return string
 */
function pg_region_list_text_preview($html)
{
    $html = preg_replace('#<(/?(?:p|div|h[1-6]|li|br|tr|td|th|ul|ol|table|blockquote)\b)#i', ' <$1', (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

    return pg_region_list_preview($text);
}

/**
 * A login region row's cells.
 *
 * @param array $row        login_regions row with created_username and last_modified_username
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_login_region_list_cells(array $row, array $quick_edit)
{
    $id = (int) $row['id'];
    $cells = array(
        'name' => pg_list_cell('<a href="edit_login_region.php?id=' . $id . '" data-pg-open>' . h($row['name']) . '</a>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, $row['name'])),
        'not_logged_in_header' => pg_list_cell(pg_region_list_preview($row['not_logged_in_header'])),
        'login_form' => pg_list_table_check_cell(!empty($row['login_form'])),
        'not_logged_in_footer' => pg_list_cell(pg_region_list_preview($row['not_logged_in_footer'])),
        'logged_in_header' => pg_list_cell(pg_region_list_preview($row['logged_in_header'])),
        'logged_in_footer' => pg_list_cell(pg_region_list_preview($row['logged_in_footer'])),
        'created' => pg_list_table_when_cell($row['created_timestamp'], (string) ($row['created_username'] ?? '')),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) ($row['last_modified_username'] ?? '')),
    );

    return pg_region_list_check_fields($cells, $quick_edit, $id, array('login_form' => !empty($row['login_form'])));
}

/**
 * The login regions read for the list: every one, or the one asked for.
 *
 * @param int $id 0 for all
 * @return array list of rows
 */
function pg_login_region_list_rows($id = 0)
{
    return db_items(
        "SELECT
            login_regions.id, login_regions.name, login_regions.not_logged_in_header, login_regions.login_form,
            login_regions.not_logged_in_footer, login_regions.logged_in_header, login_regions.logged_in_footer,
            login_regions.created_timestamp, login_regions.last_modified_timestamp,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM login_regions
        LEFT JOIN user AS created_user ON login_regions.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON login_regions.last_modified_user_id = last_modified_user.user_id
        " . ($id ? "WHERE login_regions.id = '" . (int) $id . "'" : "") . "
        ORDER BY login_regions.name") ?: array();
}

/**
 * One login region read for the list.
 *
 * @param int $id
 * @return array|null
 */
function pg_login_region_list_row($id)
{
    $rows = ((int) $id > 0) ? pg_login_region_list_rows((int) $id) : array();

    return $rows ? $rows[0] : null;
}

/**
 * Writes one login region value changed on the list: a name is required
 * and not used by another login region, as on the edit screen.
 *
 * @param int    $id
 * @param string $field
 * @param mixed  $value
 * @param int    $user_id
 * @return array
 */
function pg_login_region_quick_update($id, $field, $value, $user_id)
{
    $quick_edit = pg_login_region_edit_columns();
    $row = pg_login_region_list_row($id);

    if (!$row || !isset($quick_edit[$field])) {
        return pg_region_list_refusal(lang('Record not found.'));
    }

    $value = (string) $value;

    if (($field === 'name') && ((int) db_value("SELECT COUNT(*) FROM login_regions WHERE name = '" . e($value) . "' AND id != '" . (int) $id . "'") > 0)) {
        return pg_region_list_refusal(lang('The name that you entered is already in use, so please enter a different name.'));
    }

    db_q("UPDATE login_regions SET `" . $field . "` = ?, last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?",
        array($value, (string) (int) $user_id, (string) (int) $id)) or output_error('Query failed.');

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('login region'), ($field === 'name') ? $value : $row['name']))), $_SESSION['sessionusername'] ?? '');

    return pg_region_list_saved(pg_login_region_list_cells(pg_login_region_list_row($id), $quick_edit), $field);
}

/* Dynamic, designer and common regions */

/**
 * The columns of a dynamic, designer or common region that change in
 * place: the name, and the region type of a designer or common region.
 * Content and code change on the edit screen.
 *
 * @param string $kind 'dynamic', 'designer' or 'common'
 * @return array field => definition
 */
function pg_content_region_edit_columns($kind)
{
    $columns = array('name' => array('type' => 'text', 'label' => lang('Name'), 'max' => pg_name_max_length(), 'required' => true));

    if ($kind !== 'dynamic') {
        $columns['type'] = array('type' => 'select', 'label' => lang('Region Type'), 'options' => array('common' => lang('Common Region'), 'designer' => lang('Designer Region')));
    }

    return $columns;
}

/**
 * The list columns of a dynamic, designer or common region.
 *
 * @param string $kind
 * @return array key => spec
 */
function pg_content_region_list_columns($kind)
{
    $specs = array('name' => array('heading' => lang('Name')));

    if ($kind !== 'dynamic') {
        $specs['type'] = array('heading' => lang('Region Type'));
    }

    $specs[($kind === 'dynamic') ? 'code' : 'content'] = array('heading' => ($kind === 'dynamic') ? lang('Code Preview') : lang('Content Preview'));
    $specs['last_modified'] = array('heading' => lang('Last Modified'), 'kind' => 'date');

    return pg_region_list_specs($specs, pg_content_region_edit_columns($kind));
}

/**
 * The dynamic, designer or common regions read for the list: every one of
 * the kind, or the one asked for (of any type, for a quick edit that just
 * changed it), with the shared component a common region was made (0 for
 * none).
 *
 * @param string $kind 'dynamic', 'designer' or 'common'
 * @param int    $id   0 for all
 * @return array list of rows: id, name, type, preview, timestamp, username, component
 */
function pg_content_region_list_rows($kind, $id = 0)
{
    if ($kind === 'dynamic') {
        $rows = db_items(
            "SELECT dregion_id AS id, dregion_name AS name, '' AS type, dregion_code AS preview, dregion_timestamp AS timestamp, user.user_username AS username
            FROM dregion LEFT JOIN user ON dregion.dregion_user = user.user_id
            " . ($id ? "WHERE dregion_id = '" . (int) $id . "'" : "") . "
            ORDER BY dregion_name");
    } else {
        $where = $id ? "cregion_id = '" . (int) $id . "'" : "cregion_designer_type = '" . (($kind === 'designer') ? 'yes' : 'no') . "'";
        $rows = db_items(
            "SELECT cregion_id AS id, cregion_name AS name, IF(cregion_designer_type = 'yes', 'designer', 'common') AS type, cregion_content AS preview, cregion_timestamp AS timestamp, user.user_username AS username
            FROM cregion LEFT JOIN user ON cregion.cregion_user = user.user_id
            WHERE " . $where . "
            ORDER BY cregion_name");
    }

    $rows = $rows ?: array();
    $common = array();

    foreach ($rows as $index => $row) {
        $rows[$index]['component'] = 0;

        if ($row['type'] === 'common') {
            $common[] = (int) $row['id'];
        }
    }

    if ($common) {
        require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
        $components = pg_common_region_components($common);

        foreach ($rows as $index => $row) {
            $rows[$index]['component'] = (int) ($components[(int) $row['id']] ?? 0);
        }
    }

    return $rows;
}

/**
 * One dynamic, designer or common region read for the list.
 *
 * @param string $kind
 * @param int    $id
 * @return array|null
 */
function pg_content_region_list_row($kind, $id)
{
    $rows = ((int) $id > 0) ? pg_content_region_list_rows($kind, (int) $id) : array();

    return $rows ? $rows[0] : null;
}

/**
 * A dynamic, designer or common region row's cells. A common region made a
 * shared component keeps its type: its type cell does not change in place.
 *
 * @param string $kind
 * @param array  $row        pg_content_region_list_row()
 * @param array  $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_content_region_list_cells($kind, array $row, array $quick_edit)
{
    $id = (int) $row['id'];
    $screens = array('dynamic' => 'edit_dynamic_region.php', 'designer' => 'edit_designer_region.php', 'common' => 'edit_common_region.php');
    $screen = $screens[($kind === 'dynamic') ? 'dynamic' : $row['type']] ?? 'edit_common_region.php';
    $cells = array('name' => pg_list_cell('<a href="' . $screen . '?id=' . $id . '" data-pg-open>' . h($row['name']) . '</a>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, $row['name'])));

    if ($kind !== 'dynamic') {
        $type_edit = !empty($row['component']) ? array() : $quick_edit;
        $cells['type'] = pg_region_list_cell(($row['type'] === 'designer') ? h(lang('Designer Region')) : h(lang('Common Region')), $type_edit, 'type', $id, $row['type']);
    }

    // A common region holds rich text and previews its text; a designer or
    // dynamic region holds code and previews the code.
    $cells[($kind === 'dynamic') ? 'code' : 'content'] = pg_list_cell((($kind !== 'dynamic') && ($row['type'] === 'common')) ? pg_region_list_text_preview($row['preview']) : h(mb_substr((string) $row['preview'], 0, 50)));
    $cells['last_modified'] = pg_list_table_when_cell($row['timestamp'], (string) ($row['username'] ?? ''));

    return $cells;
}

/**
 * Writes the name or type of a dynamic, designer or common region changed
 * on the list, refused as the edit screen refuses it
 * (pg_region_form_name_refusal()); a shared component's region keeps its
 * type.
 *
 * @param string $kind
 * @param int    $id
 * @param string $field
 * @param mixed  $value
 * @param int    $user_id
 * @return array
 */
function pg_content_region_quick_update($kind, $id, $field, $value, $user_id)
{
    $quick_edit = pg_content_region_edit_columns($kind);
    $row = pg_content_region_list_row($kind, $id);

    if (!$row || !isset($quick_edit[$field])) {
        return pg_region_list_refusal(lang('Record not found.'));
    }

    $value = (string) $value;
    $dynamic = ($kind === 'dynamic');

    if ($field === 'name') {
        $error = pg_region_form_name_refusal($value, $dynamic ? pg_dregion_name_taken($value, $id) : pg_cregion_name_taken($value, $id));

        if ($error !== '') {
            return pg_region_list_refusal($error);
        }
    } elseif (!empty($row['component'])) {
        return pg_region_list_refusal(lang('This column cannot be changed here.'));
    }

    if ($dynamic) {
        db_q("UPDATE dregion SET dregion_name = ?, dregion_user = ?, dregion_timestamp = UNIX_TIMESTAMP() WHERE dregion_id = ?",
            array($value, (string) (int) $user_id, (string) (int) $id)) or output_error('Query failed.');
    } else {
        db_q("UPDATE cregion SET " . (($field === 'name') ? 'cregion_name' : 'cregion_designer_type') . " = ?, cregion_user = ?, cregion_timestamp = UNIX_TIMESTAMP() WHERE cregion_id = ?",
            array(($field === 'name') ? $value : (($value === 'designer') ? 'yes' : 'no'), (string) (int) $user_id, (string) (int) $id)) or output_error('Query failed.');
    }

    $names = array('dynamic' => lang('dynamic region'), 'designer' => lang('designer region'), 'common' => lang('common region'));
    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array($names[$dynamic ? 'dynamic' : $row['type']], ($field === 'name') ? $value : $row['name']))), $_SESSION['sessionusername'] ?? '');

    return pg_region_list_saved(pg_content_region_list_cells($kind, pg_content_region_list_row($kind, $id), $quick_edit), $field);
}

/* Ads */

/**
 * The ad columns that change in place: name, ad region (one the user may
 * put ads in), label and sort order. Content and caption are rich text and
 * change on the ad's screen.
 *
 * @param array $regions id => name of the ad regions the user may choose
 * @return array field => definition
 */
function pg_ad_edit_columns(array $regions)
{
    $options = array();

    foreach ($regions as $region_id => $name) {
        $options[(string) $region_id] = (string) $name;
    }

    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'ad_region_id' => array('type' => 'select', 'label' => lang('Ad Region'), 'options' => $options),
        'label' => array('type' => 'text', 'label' => lang('Label'), 'max' => 255),
        'sort_order' => array('type' => 'integer', 'label' => lang('Sort Order'), 'min' => 0, 'max' => 9999, 'empty' => true),
    );
}

/**
 * The ads list's columns. Display Type is the region's and does not change
 * here.
 *
 * @param array $quick_edit
 * @return array key => spec
 */
function pg_ad_list_columns(array $quick_edit)
{
    return pg_region_list_specs(array(
        'name' => array('heading' => lang('Name')),
        'ad_region_id' => array('heading' => lang('Ad Region')),
        'display_type' => array('heading' => lang('Display Type')),
        'label' => array('heading' => lang('Label')),
        'sort_order' => array('heading' => lang('Sort Order'), 'kind' => 'number'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ), $quick_edit);
}

/**
 * An ad row's cells. Label and sort order are shown for an ad of a dynamic
 * region only, as before; they still change in place.
 *
 * @param array $row        ads row with ad_region_name, ad_region_display_type and the user names
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_ad_list_cells(array $row, array $quick_edit)
{
    $id = (int) $row['id'];
    $dynamic = ($row['ad_region_display_type'] === 'dynamic');
    $types = array('static' => lang('Static'), 'dynamic' => lang('Dynamic'));
    $sort_order = (int) $row['sort_order'];

    return array(
        'name' => pg_list_cell('<a href="edit_ad.php?id=' . $id . '" data-pg-open>' . h($row['name']) . '</a>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, $row['name'])),
        'ad_region_id' => pg_region_list_cell(h((string) $row['ad_region_name']), $quick_edit, 'ad_region_id', $id, $row['ad_region_id']),
        'display_type' => pg_list_cell(h($types[$row['ad_region_display_type']] ?? '')),
        'label' => pg_region_list_cell($dynamic ? h($row['label']) : '', $quick_edit, 'label', $id, $row['label']),
        'sort_order' => pg_region_list_cell(($dynamic && $sort_order) ? pg_format_number($sort_order, 0) : '', $quick_edit, 'sort_order', $id, $sort_order ? $sort_order : '', array('data-pg-sort-value' => $dynamic ? $sort_order : 0)),
        'created' => pg_list_table_when_cell($row['created_timestamp'], (string) ($row['created_username'] ?? '')),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) ($row['last_modified_username'] ?? '')),
    );
}

/**
 * The SELECT of the ads list, without its WHERE and ORDER BY.
 *
 * @return string
 */
function pg_ad_list_select()
{
    return "SELECT
            ads.id, ads.name, ads.ad_region_id, ads.label, ads.sort_order,
            ads.created_timestamp, ads.last_modified_timestamp,
            ad_regions.name AS ad_region_name,
            ad_regions.display_type AS ad_region_display_type,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM ads
        LEFT JOIN user AS created_user ON ads.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON ads.last_modified_user_id = last_modified_user.user_id
        LEFT JOIN ad_regions ON ads.ad_region_id = ad_regions.id";
}

/**
 * Writes one ad value changed on the list, checked as the ad's screen
 * checks it: the name required, at most 100 characters and not used by
 * another ad; the region one the user may choose; the sort order
 * (pg_ad_form_sort_order_error()). A user role changes only ads of its own
 * regions.
 *
 * @param int    $id
 * @param string $field
 * @param mixed  $value
 * @param array  $user    validate_user()
 * @param array  $regions id => name of the ad regions the user may choose
 * @return array
 */
function pg_ad_quick_update($id, $field, $value, array $user, array $regions)
{
    $quick_edit = pg_ad_edit_columns($regions);
    $row = db_item(pg_ad_list_select() . " WHERE ads.id = '" . (int) $id . "'");

    if (!$row || !isset($quick_edit[$field]) || (($user['role'] == 3) && !isset($regions[$row['ad_region_id']]))) {
        return pg_region_list_refusal(lang('Record not found.'));
    }

    $value = (string) $value;

    if (($field === 'name') && ((int) db_value("SELECT COUNT(*) FROM ads WHERE name = '" . e($value) . "' AND id != '" . (int) $id . "'") > 0)) {
        return pg_region_list_refusal(lang('The name that you entered is already in use, so please enter a different name.'));
    }

    if ($field === 'sort_order') {
        $error = pg_ad_form_sort_order_error($value);

        if ($error !== '') {
            return pg_region_list_refusal($error);
        }

        $value = ($value === '') ? '0' : $value;
    }

    db_q("UPDATE ads SET `" . $field . "` = ?, last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?",
        array($value, (string) (int) $user['id'], (string) (int) $id)) or output_error('Query failed.');

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('ad'), ($field === 'name') ? $value : $row['name']))), $_SESSION['sessionusername'] ?? '');

    return pg_region_list_saved(pg_ad_list_cells(db_item(pg_ad_list_select() . " WHERE ads.id = '" . (int) $id . "'"), $quick_edit), $field);
}

/**
 * The ad regions a user may put ads in, by name: every one, or for a user
 * role the ones it was given.
 *
 * @param array $user validate_user()
 * @return array id => name
 */
function pg_ad_list_regions(array $user)
{
    $where = ($user['role'] == 3)
        ? "WHERE id IN (SELECT ad_region_id FROM users_ad_regions_xref WHERE user_id = '" . (int) $user['id'] . "')"
        : '';
    $regions = array();

    foreach ((db_items("SELECT id, name FROM ad_regions " . $where . " ORDER BY name") ?: array()) as $region) {
        $regions[(string) $region['id']] = (string) $region['name'];
    }

    return $regions;
}
