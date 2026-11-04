<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Quick edit on the list table driver's lists beyond the fields a list's
 * own screen draws: one record's row read again through the list's source,
 * so the answer to an edit draws the cell the way the list draws it, and
 * the Pages list's switches (searchable, comments, site map), written by
 * the rules of the page screen and Modify Selected (includes/fn/page_bulk.php).
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

/**
 * One record of a list read again with the list's own query, rights and
 * row builder, as the browser gets a row (pg_list_table_row_data()). Null
 * when the record is not on the list (any more): a change that moved it
 * out of the view, or a record the user may not reach.
 *
 * @param array $source pg_list_table_source()
 * @param int   $id     the record
 * @return array|null
 */
function pg_list_table_record_row(array $source, $id)
{
    if (((int) $id <= 0) || !is_callable($source['rows'])) {
        return null;
    }

    $result = db_q(
        'SELECT ' . $source['id'] . ' AS pg_lt_id' . (($source['select'] !== '') ? ', ' . $source['select'] : '')
        . ' FROM ' . $source['from']
        . ' WHERE (' . $source['where'] . ') AND (' . $source['id'] . ' = ?)'
        . ($source['group'] ? ' GROUP BY ' . $source['id'] : '')
        . ' LIMIT 1',
        array_merge($source['params'], array((string) (int) $id)));

    $record = $result ? mysqli_fetch_assoc($result) : null;

    if (!$record) {
        return null;
    }

    $rows = array_values((array) call_user_func($source['rows'], array($record)));

    return $rows ? pg_list_table_row_data($rows[0], $source['columns']) : null;
}

/**
 * The drawing part of a quick edit's answer (pg_list_edit_reply()) from a
 * row read again: the edited cell's 'html' and 'class', and 'cells' for the
 * other cells of the row that change with it. Nothing for a cell the row
 * does not have.
 *
 * @param array|null $row   pg_list_table_record_row()
 * @param string     $key   the edited cell's column
 * @param array      $also  the other columns to draw again
 * @return array
 */
function pg_list_table_edit_answer($row, $key, array $also = array())
{
    $answer = array();

    if (!is_array($row) || !isset($row['cells']) || !is_array($row['cells'])) {
        return $answer;
    }

    if (isset($row['cells'][$key])) {
        $answer['html'] = (string) $row['cells'][$key]['html'];
        $answer['class'] = (string) $row['cells'][$key]['class'];
    }

    foreach ($also as $other) {
        if (($other !== $key) && isset($row['cells'][$other])) {
            $answer['cells'][$other] = array(
                'html' => (string) $row['cells'][$other]['html'],
                'class' => (string) $row['cells'][$other]['class'],
            );
        }
    }

    return $answer;
}

/**
 * The mark of a switch that is on in a Pages list cell.
 *
 * @return string HTML
 */
function pg_page_list_check_html()
{
    return '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
}


/**
 * The theme whose styles the Pages list shows, and a style quick edit
 * writes, while it is previewed: a theme other than the one activated for
 * the device type (as pg_page_list_view() decides). 0 otherwise.
 *
 * @param string $device_type 'desktop' or 'mobile'
 * @return int
 */
function pg_page_quick_preview_theme($device_type)
{
    $preview = (int) ($_SESSION['software']['preview_theme_id'] ?? 0);

    if ($preview <= 0) {
        return 0;
    }

    $activated = (int) db_value("SELECT id FROM files WHERE activated_" . sql_device_type($device_type) . "_theme = '1'");

    return ($preview != $activated) ? $preview : 0;
}

/**
 * The folders a page may be moved into from the list, in the order and
 * with the indentation of the page screen's picker (pg_page_select_folder()):
 * neither the Recycle Bin nor the Workspace's folder, nor anything below
 * them, and only folders the user may edit. Pure over the rows.
 *
 * @param array    $folders   rows (folder_id, folder_name, folder_parent,
 *                            folder_archived) in the picker's order
 * @param callable $may       function ($folder_id): whether the user may edit it
 * @param int      $workspace the Workspace's folder, 0 when there is none
 * @param int      $bin       the Recycle Bin folder, 0 when there is none
 * @return array folder id => label
 */
function pg_page_quick_folder_choices($folders, $may, $workspace, $bin)
{
    $children = array();

    foreach ((array) $folders as $folder) {
        $children[(int) $folder['folder_parent']][] = $folder;
    }

    $choices = array();
    $seen = array();
    $archived = ' [' . lang('ARCHIVED') . ']';

    $walk = function ($parent, $level) use (&$walk, &$choices, &$seen, $children, $may, $workspace, $bin, $archived) {
        foreach ($children[$parent] ?? array() as $folder) {
            $id = (int) $folder['folder_id'];

            // A folder left out takes what is below it along; a loop in
            // folder_parent is walked once.
            if (isset($seen[$id]) || ($id === (int) $workspace) || ($id === (int) $bin)) {
                continue;
            }

            $seen[$id] = true;
            $next = $level;

            if (call_user_func($may, $id)) {
                $choices[$id] = str_repeat("\u{00A0}\u{00A0}", $level) . (string) $folder['folder_name']
                    . (((string) ($folder['folder_archived'] ?? '') === '1') ? $archived : '');
                $next = $level + 1;
            }

            $walk($id, $next);
        }
    };

    $walk(0, 0);

    return $choices;
}

/**
 * The class each offered folder's option wears: the folder's own access
 * type, as the page screen's picker prints it (select_folder()), so the
 * list colours every folder as the picker and the folder tree do. A folder
 * that inherits its access, or has an unknown type, gets none. Pure.
 *
 * @param array $folders rows (folder_id, folder_access_control_type)
 * @param array $choices pg_page_quick_folder_choices()
 * @return array folder id => class
 */
function pg_page_quick_folder_classes($folders, $choices)
{
    $known = array('public', 'guest', 'registration', 'membership', 'private');
    $classes = array();

    foreach ((array) $folders as $folder) {
        $id = (int) $folder['folder_id'];
        $type = strtolower((string) ($folder['folder_access_control_type'] ?? ''));

        if (isset($choices[$id]) && in_array($type, $known, true)) {
            $classes[$id] = $type;
        }
    }

    return $classes;
}

/**
 * The value => label pairs of a picker's <option> list, as the page screen
 * prints it (select_style(), get_mobile_style_options(), select_page_type()).
 * Pure.
 *
 * @param string $html
 * @return array value => label, in the picker's order
 */
function pg_page_quick_option_pairs($html)
{
    $pairs = array();

    if (preg_match_all('/<option value="([^"]*)"[^>]*>(.*?)<\/option>/s', (string) $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $pairs[html_entity_decode($match[1], ENT_QUOTES, 'UTF-8')] = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES, 'UTF-8'));
        }
    }

    return $pairs;
}

/**
 * What the Pages list's fields offer the user, read once per request: the
 * folders, the styles of the device type the list shows, the theme a
 * style goes to while another one is previewed and the page types the user
 * may set (the page screen's picker, select_page_type()).
 *
 * @param array $user the signed-in user
 * @return array 'device', 'preview_theme_id', 'folders', 'folder_classes', 'styles', 'style_label', 'types'
 */
function pg_page_quick_edit_choices($user)
{
    static $read = array();

    $role = (int) ($user['role'] ?? 99);
    $key = (int) ($user['id'] ?? 0) . ':' . $role;

    if (isset($read[$key])) {
        return $read[$key];
    }

    $device = pg_page_list_device_type();
    $preview = pg_page_quick_preview_theme($device);

    $rows = db_items("SELECT folder_id, folder_name, folder_parent, folder_archived, folder_access_control_type FROM folder ORDER BY folder_level, folder_order, folder_name");
    $rows = is_array($rows) ? $rows : array();
    $folders = pg_page_quick_folder_choices($rows, function ($folder_id) {
        return check_edit_access($folder_id);
    }, (int) pg_workspace_folder_id(), (int) pg_recycle_bin_folder_id());

    $styles = array();

    // Staff only, as on the page screen and in Modify Selected.
    if ($role < 3) {
        $default = $preview ? '[' . lang('Activated') . ']' : lang('Default') . ' (' . lang('inherit') . ')';
        $styles = pg_page_quick_option_pairs(($device === 'mobile') ? get_mobile_style_options('', $default) : select_style('', $default));
    }

    $read[$key] = array(
        'types' => pg_page_quick_option_pairs(select_page_type('', $user)),
        'device' => $device,
        'preview_theme_id' => $preview,
        'folders' => $folders,
        'folder_classes' => pg_page_quick_folder_classes($rows, $folders),
        'styles' => $styles,
        'style_label' => ($device === 'mobile') ? lang('Mobile Page Style') : lang('Desktop Page Style'),
    );

    return $read[$key];
}

/**
 * The Pages list's cells that change in place, keyed by the list's
 * columns: the name, the folder, the style (staff), the custom form's name
 * and switch (a user who may set that page type) and the switches the
 * page screen and Modify Selected set. Without a user, the switches only.
 *
 * @param array|null $user    the signed-in user
 * @param array|null $choices pg_page_quick_edit_choices(), read when null
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_page_quick_edit_columns($user = null, $choices = null)
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));
    $columns = array();

    if (is_array($user)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');

        $choices = is_array($choices) ? $choices : pg_page_quick_edit_choices($user);

        $columns['name'] = array('type' => 'text', 'label' => lang('Name'), 'required' => true, 'max' => 100);
        $columns['folder'] = array('type' => 'select', 'label' => lang('Folder'), 'options' => $choices['folders'],
            'option_classes' => $choices['folder_classes'] ?? array());

        if ((int) $user['role'] < 3) {
            $columns['style'] = array('type' => 'select', 'label' => $choices['style_label'], 'options' => $choices['styles']);
        }

        $columns['page_type'] = array('type' => 'select', 'label' => lang('Page Type'), 'options' => $choices['types'] ?? array());

        if (pg_page_type_user_may_set($user, 'custom form')) {
            $columns['form_name'] = array('type' => 'text', 'label' => lang('Form Name'), 'max' => 100);
            $columns['form_enabled'] = array('type' => 'select', 'label' => lang('Form Enabled'), 'options' => $yes_no);
        }
    }

    $columns['searchable'] = array('type' => 'select', 'label' => lang('Searchable'), 'options' => $yes_no);
    $columns['comments'] = array('type' => 'select', 'label' => lang('Comments'), 'options' => $yes_no);
    $columns['sitemap'] = array('type' => 'select', 'label' => lang('Site Map'), 'options' => $yes_no);

    return $columns;
}

/**
 * The value a page cell's field opens with.
 *
 * @param string $field a key of pg_page_quick_edit_columns()
 * @param array  $page  a row of the list's read (page_name, page_folder,
 *                      page_search, comments, sitemap, form_name, enabled,
 *                      and 'style_value', the style the cell shows)
 * @return string
 */
function pg_page_quick_edit_value($field, $page)
{
    $switches = array('searchable' => 'page_search', 'comments' => 'comments', 'sitemap' => 'sitemap', 'form_enabled' => 'enabled');

    if (isset($switches[$field])) {
        return ((string) ($page[$switches[$field]] ?? '') === '1') ? '1' : '0';
    }

    switch ($field) {
        case 'name':
            return (string) ($page['page_name'] ?? '');
        case 'folder':
            return (string) (int) ($page['page_folder'] ?? 0);
        case 'style':
            return (string) (int) ($page['style_value'] ?? 0);
        case 'form_name':
            return (string) ($page['form_name'] ?? '');
        case 'page_type':
            return (string) ($page['page_type'] ?? '');
    }

    return '';
}

/**
 * The fields a page cell offers: the name and folder of every page, the
 * style unless the visual editor opens the page (its layout was built for
 * its style), the form's two fields on a custom form page only, the type
 * when the user may set the page's present type (edit_page.php writes a
 * type only then).
 *
 * @param string $field   a key of pg_page_quick_edit_columns()
 * @param array  $page    a row of the list's read
 * @param bool   $visual  whether the visual editor opens the page
 * @param array  $columns pg_page_quick_edit_columns()
 * @return bool
 */
function pg_page_quick_cell_editable($field, $page, $visual, $columns = array())
{
    if ($field === 'page_type') {
        return isset($columns['page_type']['options'][(string) ($page['page_type'] ?? '')]);
    }

    if ($field === 'style') {
        return !$visual;
    }

    if (($field === 'form_name') || ($field === 'form_enabled')) {
        return ((string) ($page['page_type'] ?? '') === 'custom form');
    }

    return true;
}

/**
 * What one quick edit of a page's folder, style or switch decides, by the
 * bulk edit's rules (pg_page_bulk_decide()), or why it cannot be made: the
 * site map switch goes on only for a page type the page screen offers it
 * to, and never on a page closed to search engines; a page the visual
 * editor opens keeps its style. Said rather than skipped, since the cell
 * would otherwise show the old value without a reason.
 *
 * @param array  $page   pg_page_bulk_fetch()
 * @param string $field  folder, style, searchable, comments or sitemap
 * @param string $value  as pg_list_edit_parse() read it
 * @param int    $role   the operator's role
 * @param string $device the device type whose style the list shows
 * @return array array('ok' => bool, 'decision' => pg_page_bulk_decide(), 'message' => the refusal)
 */
function pg_page_quick_decide($page, $field, $value, $role, $device = 'desktop')
{
    $keys = array(
        'searchable' => 'search',
        'comments' => 'comments',
        'sitemap' => 'sitemap',
        'folder' => 'folder',
        'style' => ($device === 'mobile') ? 'mobile_style' : 'style',
    );

    if (!isset($keys[$field])) {
        return array('ok' => false, 'decision' => null, 'message' => lang('This column cannot be changed here.'));
    }

    if (in_array($field, array('searchable', 'comments', 'sitemap'), true)) {
        $value = ((string) $value === '1') ? '1' : '0';
    } else {
        $value = (string) (int) $value;
    }

    if (($field === 'sitemap') && ($value === '1')) {
        if (!empty($page['noindex'])) {
            return array('ok' => false, 'decision' => null, 'message' => lang('A page closed to search engines is not listed in the site map. Open it to search engines on the page screen first.'));
        }

        if (!in_array((string) $page['page_type'], pg_page_bulk_sitemap_types(), true)) {
            return array('ok' => false, 'decision' => null, 'message' => lang('Pages of this type are not listed in the site map.'));
        }
    }

    $decision = pg_page_bulk_decide($page, array($keys[$field] => $value), $role);

    if (($field === 'style') && ($decision['style_skipped'] || !$decision['styles'])) {
        return array('ok' => false, 'decision' => null, 'message' => lang('A page made in the visual editor keeps the style it was designed with.'));
    }

    return array('ok' => true, 'decision' => $decision, 'message' => '');
}

/**
 * The page's stored values the quick edit compares with and logs: its
 * columns, its custom form's and the style the list's cell shows.
 *
 * @param int   $page_id
 * @param array $choices pg_page_quick_edit_choices()
 * @return array
 */
function pg_page_quick_stored($page_id, $choices)
{
    $result = db_q(
        "SELECT
            page.page_name, page.page_folder, page.page_style, page.mobile_style_id,
            page.page_search, page.comments, page.sitemap,
            custom_form_pages.form_name, custom_form_pages.enabled
        FROM page
        LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id
        WHERE page.page_id = ?", array((int) $page_id));
    $stored = $result ? mysqli_fetch_assoc($result) : null;
    $stored = is_array($stored) ? $stored : array();

    if ($choices['preview_theme_id'] > 0) {
        $stored['style_value'] = (int) db_value(
            "SELECT style_id FROM preview_styles
            WHERE (page_id = '" . (int) $page_id . "') AND (theme_id = '" . (int) $choices['preview_theme_id'] . "')
            AND (device_type = '" . e($choices['device']) . "')");
    } else {
        $stored['style_value'] = (int) ($stored[($choices['device'] === 'mobile') ? 'mobile_style_id' : 'page_style'] ?? 0);
    }

    return $stored;
}

/**
 * Rename a page from the list as the page screen does: the name cleaned
 * and checked by its rules, free among pages, files and short links, the
 * SEO score marked stale, and, for staff, the old address sending visitors
 * on (pg_short_link_page_renamed(), the page screen's box left on).
 *
 * @param array  $user
 * @param array  $page pg_page_bulk_fetch()
 * @param string $name the cleaned name
 * @return array array('ok' => bool, 'message' => the refusal or what became of the old address)
 */
function pg_page_quick_rename($user, $page, $name)
{
    if (check_name_availability(array('name' => $name, 'ignore_item_id' => $page['page_id'], 'ignore_item_type' => 'page', 'ignore_redirects_to_page' => $page['page_id'])) == false) {
        return array('ok' => false, 'message' => lang('The page name that you entered is already in use. Please enter a different page name.'));
    }

    $updated = db_q(
        "UPDATE page
        SET page_name = ?, seo_analysis_current = '0', page_timestamp = UNIX_TIMESTAMP(), page_user = ?
        WHERE page_id = ?", array($name, (int) $user['id'], (int) $page['page_id']));

    if ($updated === false) {
        return array('ok' => false, 'message' => lang('The change could not be saved.'));
    }

    $said = pg_short_link_page_renamed_messages(
        pg_short_link_page_renamed((int) $page['page_id'], $page['page_name'], $name, ((int) $user['role'] < 3), (int) $user['id'], (int) $page['page_folder']),
        PATH);

    return array('ok' => true, 'message' => implode(' ', array_merge($said['notes'], $said['warnings'])));
}

/**
 * Write a custom form page's form name or switch, as the page screen's
 * form settings do: on a custom form page only, the name unique among
 * the forms.
 *
 * @param array  $user
 * @param array  $page  pg_page_bulk_fetch()
 * @param string $field form_name or form_enabled
 * @param string $value
 * @return array array('ok' => bool, 'message' => the refusal)
 */
function pg_page_quick_form_write($user, $page, $field, $value)
{
    $page_id = (int) $page['page_id'];

    if ((string) $page['page_type'] !== 'custom form') {
        return array('ok' => false, 'message' => lang('This column cannot be changed here.'));
    }

    if ($field === 'form_name') {
        $taken = db_q("SELECT COUNT(*) AS taken FROM custom_form_pages WHERE (form_name = ?) AND (page_id != ?)", array((string) $value, $page_id));
        $taken = $taken ? mysqli_fetch_assoc($taken) : null;

        if (!$taken || ((int) $taken['taken'] > 0)) {
            return array('ok' => false, 'message' => lang('The form name that you entered is already in use. Please enter a different form name.'));
        }

        $updated = db_q("UPDATE custom_form_pages SET form_name = ? WHERE page_id = ?", array((string) $value, $page_id));
    } else {
        $updated = db_q("UPDATE custom_form_pages SET enabled = ? WHERE page_id = ?", array(((string) $value === '1') ? 1 : 0, $page_id));
    }

    if (($updated === false) || !pg_page_bulk_touch($page_id, (int) $user['id'])) {
        return array('ok' => false, 'message' => lang('The change could not be saved.'));
    }

    return array('ok' => true, 'message' => '');
}

/**
 * Write one quick edit from the Pages list by the page screen's rules: the
 * folder's edit right checked (the destination's too), a folder, style or
 * switch written the way Modify Selected writes it (pg_page_bulk_apply()),
 * a name the way the page screen renames, the custom form's fields the way
 * its form settings save them; the audit trail and the log told. The
 * answer draws the row's cells from the page as stored, read again through
 * the list in the view it shows.
 *
 * @param array  $user    the signed-in user
 * @param int    $page_id
 * @param string $field   a key of pg_page_quick_edit_columns()
 * @param string $value   as pg_list_edit_parse() read it
 * @param string $filter  the list's view
 * @return array the answer for pg_list_edit_reply()
 */
function pg_page_quick_update($user, $page_id, $field, $value, $filter = '')
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

    $page = pg_page_bulk_fetch((int) $page_id);

    if (!$page) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Page not found.'));
    }

    if (check_edit_access($page['page_folder']) == false) {
        return array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.'));
    }

    $choices = pg_page_quick_edit_choices($user);
    $columns = pg_page_quick_edit_columns($user, $choices);
    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => $message);
    };

    if ($field === 'name') {
        $clean = pg_page_form_clean_name((string) $value, (string) $page['page_type']);

        if ($clean['error'] !== '') {
            return $refuse($clean['error']);
        }

        $value = $clean['name'];
    } elseif ($field !== 'form_name') {
        $value = (string) $value;
    }

    $before = pg_page_quick_stored($page['page_id'], $choices);
    $was = pg_page_quick_edit_value($field, $before);
    $notice = '';

    // The same value again writes nothing and logs nothing.
    if ($was !== (string) $value) {
        $audit_before = pg_audit_fetch('page', $page['page_id']);

        if ($field === 'name') {
            $written = pg_page_quick_rename($user, $page, $value);
        } elseif (($field === 'form_name') || ($field === 'form_enabled')) {
            $written = pg_page_quick_form_write($user, $page, $field, $value);
        } elseif ($field === 'page_type') {
            $written = pg_page_quick_type_write($user, $page, $value);
        } else {
            if ($field === 'folder') {
                if (check_edit_access((int) $value) == false) {
                    return $refuse(lang('Access denied.'), 'denied');
                }

                $folder_errors = pg_page_form_folder_errors((int) $value, (int) $page['page_folder']);

                if ($folder_errors) {
                    return $refuse(implode(' ', $folder_errors));
                }
            }

            $decided = pg_page_quick_decide($page, $field, $value, (int) $user['role'], $choices['device']);

            if (!$decided['ok']) {
                return $refuse($decided['message']);
            }

            $written = array('ok' => true, 'message' => '');

            if (($decided['decision']['columns'] || $decided['decision']['styles'])
                && !pg_page_bulk_apply($page, $decided['decision'], (int) $user['id'], ($field === 'style') ? $choices['preview_theme_id'] : 0)) {
                $written = array('ok' => false, 'message' => lang('The change could not be saved.'));
            }
        }

        if (!$written['ok']) {
            return $refuse($written['message'], 'failed');
        }

        $notice = $written['message'];

        pg_audit_record('update', 'page', $page['page_id'], $audit_before);

        $shown = function ($stored) use ($columns, $field) {
            $options = $columns[$field]['options'] ?? null;

            return (is_array($options) && isset($options[$stored])) ? trim($options[$stored]) : $stored;
        };

        log_activity(
            lang(array(
                'string' => 'page ({var:1}) was changed on the page list: {var:2} from "{var:3}" to "{var:4}"',
                'vars' => array($page['page_name'], $columns[$field]['label'], $shown($was), $shown((string) $value)),
            )),
            $_SESSION['sessionusername'] ?? '');
    }

    $now = pg_page_quick_edit_value($field, pg_page_quick_stored($page['page_id'], $choices));

    // The row as the list draws it, in the view it shows.
    $source = pg_page_list_source($user, array('filter' => (string) $filter));
    $row = $source ? pg_list_table_record_row($source, $page['page_id']) : null;
    $answer = array('ok' => true, 'value' => $now)
        + pg_list_table_edit_answer($row, $field, array('name', 'seo', 'sitemap', 'form_name', 'form_enabled', 'actions', 'last_modified'));

    // Out of the view now (another folder, say): the cell still says what
    // was stored.
    if (!isset($answer['html'])) {
        $answer['html'] = in_array($field, array('searchable', 'comments', 'sitemap', 'form_enabled'), true)
            ? (($now === '1') ? pg_page_list_check_html() : '')
            : (isset($columns[$field]['options'])
            ? h(trim((string) ($columns[$field]['options'][$now] ?? '')))
            : h($now));
    }

    if ($notice !== '') {
        $answer['notice'] = $notice;
    }

    return $answer;
}

/**
 * The type options a page starts with when the list changes its type: what
 * the page screen saves for the type's fields as a new page draws them
 * (pg_page_form_new_page_properties()), the rest empty, a custom form
 * switched on as the screen draws it. Empty for a type without a table of
 * its own.
 *
 * @param string $page_type
 * @param int    $page_id
 * @return array the row for create_or_update_page_type_record()
 */
function pg_page_quick_type_properties($page_type, $page_id)
{
    $properties = pg_page_form_type_properties_from_post($page_type, (int) $page_id, array());

    if (!$properties) {
        return array();
    }

    $defaults = pg_page_form_new_page_properties();
    $defaults = $defaults[$page_type] ?? array();

    if ($page_type === 'custom form') {
        $defaults['enabled'] = '1';
    }

    return array_merge($properties, array_intersect_key($defaults, $properties));
}

/**
 * Change a page's type from the list by the page screen's rules
 * (edit_page.php): the user may set both the present and the new type; a
 * custom form with submitted forms keeps its type; the old type's options,
 * its form list links and its search results keywords go; the new type
 * starts with the options a new page has (pg_page_quick_type_properties());
 * a type the site map does not list leaves it.
 *
 * @param array  $user
 * @param array  $page pg_page_bulk_fetch()
 * @param string $type the new type
 * @return array array('ok' => bool, 'message' => the refusal, or what to do next)
 */
function pg_page_quick_type_write($user, $page, $type)
{
    $page_id = (int) $page['page_id'];
    $current = (string) $page['page_type'];
    $type = (string) $type;

    if (!pg_page_type_user_may_set($user, $current) || !pg_page_type_user_may_set($user, $type) || (get_page_type_name($type) === null)) {
        return array('ok' => false, 'message' => lang('Access denied.'));
    }

    if (($current === 'custom form') && ((int) db_value("SELECT COUNT(*) FROM forms WHERE page_id = '" . $page_id . "'") > 0)) {
        return array('ok' => false, 'message' => lang('The page type for this page could not be changed because there are submitted forms for this page.  All submitted forms for this page must be deleted before the page type for this page is allowed to be changed.'));
    }

    $properties = pg_page_quick_type_properties($type, $page_id);

    // The form name is the form's address among the forms; an empty one
    // another form already has would be refused on the page screen too.
    if (($type === 'custom form')
        && ((int) db_value("SELECT COUNT(*) FROM custom_form_pages WHERE (form_name = '" . e((string) $properties['form_name']) . "') AND (page_id != '" . $page_id . "')") > 0)) {
        return array('ok' => false, 'message' => lang('Another custom form has no name either. Change the type on the page screen, where the form gets its name.'));
    }

    if ($current === 'search results') {
        delete_tag_cloud_keywords_for_search_results_page($page_id);
    }

    // The table name comes from the page type list, never from the request.
    if (check_for_page_type_properties($current)) {
        db_q("DELETE FROM " . str_replace(' ', '_', $current) . "_pages WHERE page_id = ?", array($page_id));
    }

    if ($current === 'form list view') {
        db_q("DELETE FROM form_view_directories_form_list_views_xref WHERE form_list_view_page_id = ?", array($page_id));
    }

    if ($current === 'form view directory') {
        db_q("DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = ?", array($page_id));
    }

    if ($properties) {
        create_or_update_page_type_record($type, $properties);
    }

    $updated = db_q(
        "UPDATE page
        SET
            page_type = ?,
            sitemap = IF(?, sitemap, 0),
            seo_analysis_current = '0',
            page_timestamp = UNIX_TIMESTAMP(),
            page_user = ?
        WHERE page_id = ?",
        array($type, pg_page_form_sitemap_type($type) ? 1 : 0, (int) $user['id'], $page_id));

    if ($updated === false) {
        return array('ok' => false, 'message' => lang('The change could not be saved.'));
    }

    // The page screen goes on to the form designer for a new form, and
    // the type's own options are set there.
    $next = ($type === 'custom form')
        ? lang('Add the fields of the new form in the form designer.')
        : ($properties ? lang('Set the options of the new page type on the page screen.') : '');

    return array('ok' => true, 'message' => $next);
}
