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
 * The Pages list's cells that change in place: the switches the page
 * screen and Modify Selected set, keyed by the list's columns.
 *
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_page_quick_edit_columns()
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));

    return array(
        'searchable' => array('type' => 'select', 'label' => lang('Searchable'), 'options' => $yes_no),
        'comments' => array('type' => 'select', 'label' => lang('Comments'), 'options' => $yes_no),
        'sitemap' => array('type' => 'select', 'label' => lang('Site Map'), 'options' => $yes_no),
    );
}

/**
 * The value a page cell's field opens with.
 *
 * @param string $field a key of pg_page_quick_edit_columns()
 * @param array  $page  a row of the list's read (page_search, comments, sitemap)
 * @return string '1' or '0'
 */
function pg_page_quick_edit_value($field, $page)
{
    $columns = array('searchable' => 'page_search', 'comments' => 'comments', 'sitemap' => 'sitemap');

    return (isset($columns[$field]) && ((string) ($page[$columns[$field]] ?? '') === '1')) ? '1' : '0';
}

/**
 * What one quick edit of a page decides, by the bulk edit's rules
 * (pg_page_bulk_decide()), or why it cannot be made: the site map switch
 * goes on only for a page type the page screen offers it to, and never on a
 * page closed to search engines. Said rather than skipped, since the cell
 * would otherwise show the old value without a reason.
 *
 * @param array  $page  pg_page_bulk_fetch()
 * @param string $field a key of pg_page_quick_edit_columns()
 * @param string $value '0' or '1'
 * @param int    $role  the operator's role
 * @return array array('ok' => bool, 'decision' => pg_page_bulk_decide(), 'message' => the refusal)
 */
function pg_page_quick_decide($page, $field, $value, $role)
{
    $keys = array('searchable' => 'search', 'comments' => 'comments', 'sitemap' => 'sitemap');

    if (!isset($keys[$field])) {
        return array('ok' => false, 'decision' => null, 'message' => lang('This column cannot be changed here.'));
    }

    $value = ((string) $value === '1') ? '1' : '0';

    if (($field === 'sitemap') && ($value === '1')) {
        if (!empty($page['noindex'])) {
            return array('ok' => false, 'decision' => null, 'message' => lang('A page closed to search engines is not listed in the site map. Open it to search engines on the page screen first.'));
        }

        if (!in_array((string) $page['page_type'], pg_page_bulk_sitemap_types(), true)) {
            return array('ok' => false, 'decision' => null, 'message' => lang('Pages of this type are not listed in the site map.'));
        }
    }

    return array('ok' => true, 'decision' => pg_page_bulk_decide($page, array($keys[$field] => $value), $role), 'message' => '');
}

/**
 * Write one quick edit from the Pages list the way Modify Selected writes
 * the same switch (pg_page_bulk_apply()): the folder's edit right checked,
 * the keyword cloud and the SEO score kept in step, the audit trail and the
 * log told. The answer draws the cell from the page as stored.
 *
 * @param array  $user    the signed-in user
 * @param int    $page_id
 * @param string $field   a key of pg_page_quick_edit_columns()
 * @param string $value   as pg_list_edit_parse() read it
 * @return array the answer for pg_list_edit_reply()
 */
function pg_page_quick_update($user, $page_id, $field, $value)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');

    $page = pg_page_bulk_fetch((int) $page_id);

    if (!$page) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Page not found.'));
    }

    if (check_edit_access($page['page_folder']) == false) {
        return array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.'));
    }

    $decided = pg_page_quick_decide($page, $field, $value, (int) $user['role']);

    if (!$decided['ok']) {
        return array('ok' => false, 'code' => 'invalid', 'message' => $decided['message']);
    }

    $before = db_item("SELECT page_search, comments, sitemap FROM page WHERE page_id = '" . (int) $page['page_id'] . "'");
    $value = ((string) $value === '1') ? '1' : '0';
    $was = pg_page_quick_edit_value($field, $before ? $before : array());

    // The same value again writes nothing and logs nothing.
    if (($was !== $value) && $decided['decision']['columns']) {
        $audit_before = pg_audit_fetch('page', $page['page_id']);

        if (!pg_page_bulk_apply($page, $decided['decision'], (int) $user['id'])) {
            return array('ok' => false, 'code' => 'failed', 'message' => lang('The change could not be saved.'));
        }

        pg_audit_record('update', 'page', $page['page_id'], $audit_before);

        $columns = pg_page_quick_edit_columns();

        log_activity(
            lang(array(
                'string' => 'page ({var:1}) was changed on the page list: {var:2} from "{var:3}" to "{var:4}"',
                'vars' => array($page['page_name'], $columns[$field]['label'], $columns[$field]['options'][$was], $columns[$field]['options'][$value]),
            )),
            $_SESSION['sessionusername'] ?? '');
    }

    // The cell as the list draws it, from what is stored, and the last
    // change beside it.
    $stored = db_item(
        "SELECT page.page_search, page.comments, page.sitemap, page.page_timestamp, user.user_username
        FROM page
        LEFT JOIN user ON user.user_id = page.page_user
        WHERE page.page_id = '" . (int) $page['page_id'] . "'");
    $stored = $stored ? $stored : array();
    $now = pg_page_quick_edit_value($field, $stored);

    return array(
        'ok' => true,
        'value' => $now,
        'html' => ($now === '1') ? pg_page_list_check_html() : '',
        'class' => 'align-middle',
        'cells' => array('last_modified' => array('html' => pg_list_when($stored['page_timestamp'] ?? 0, $stored['user_username'] ?? ''), 'class' => 'align-middle')),
    );
}
