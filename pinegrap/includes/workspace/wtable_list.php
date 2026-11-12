<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Data list: the workflow tables a person reaches, on the
 * list table driver and printed whole (browser mode), as
 * workspace_wtables.php draws it without ?id=. The place is named Data; a
 * single one stays a table, and the address, the API names and the MCP
 * tools keep theirs.
 *
 * Here: the list's scopes and their counts, the columns and the cells of a
 * table and of a deleted one, and the segmented controls above the list.
 * The store (wtables.php, wtable_shares.php, wtable_bin.php) still decides
 * what a person reaches; this file only draws what it is given. Loaded by
 * the screen, after includes/fn/list_table.php, which it needs.
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
 * The list's name: its pg_list_state() key, its columns preferences and its
 * place in pg_list_table_printed_lists().
 */
const WS_WTABLE_LIST = 'workspace_wtables';

/**
 * The name of the list of deleted tables. It keeps no sort: the latest
 * deleted leads, as the bin gives them.
 */
const WS_WTABLE_LIST_DELETED = 'workspace_wtables_deleted';

/**
 * The scopes of the list, in the order the segmented control shows them.
 * The tables of channels are a scope of their own once the store has them.
 *
 * @param bool $channels whether the store keeps tables that belong to a
 *                       channel
 * @return array scope => label
 */
function ws_wtable_list_scopes($channels = false)
{
    $scopes = array('all' => lang('All'), 'mine' => lang('Mine'));

    if ($channels) {
        $scopes['channels'] = lang('Channels');
    }

    $scopes['shared'] = lang('Shared with me');

    return $scopes;
}

/**
 * The scope an address asks for; anything else is every table.
 *
 * @param mixed $value    $_GET['scope']
 * @param bool  $channels as ws_wtable_list_scopes() takes it
 * @return string all | mine | channels | shared
 */
function ws_wtable_list_scope($value, $channels = false)
{
    return (is_string($value) && ($value !== 'all') && array_key_exists($value, ws_wtable_list_scopes($channels))) ? $value : 'all';
}

/**
 * The scope a reached table falls in: a channel's (whatever the person may
 * do with it), the person's own, or somebody else's that reaches them
 * through a share.
 *
 * A table is a channel's only when the store found its channel: a
 * channel_id that names no channel any more leaves the table its maker's,
 * so the screen hands over the channels it resolved rather than the column.
 *
 * @param array $table    a ws_wtables_reached() row: id, access
 * @param array $channels table id => the channel the table belongs to, as
 *                        the store resolved it; empty while the store keeps
 *                        no channel tables
 * @return string channels | mine | shared
 */
function ws_wtable_list_scope_of($table, array $channels = array())
{
    if (isset($channels[(int) ($table['id'] ?? 0)])) {
        return 'channels';
    }

    return ((string) ($table['access'] ?? '') === 'owner') ? 'mine' : 'shared';
}

/**
 * How many tables each scope holds.
 *
 * @param array $tables   ws_wtables_reached($viewer, 'all')
 * @param array $channels as ws_wtable_list_scope_of() takes it
 * @return array all, mine, channels, shared => count
 */
function ws_wtable_list_counts(array $tables, array $channels = array())
{
    $counts = array('all' => count($tables), 'mine' => 0, 'channels' => 0, 'shared' => 0);

    foreach ($tables as $table) {
        $counts[ws_wtable_list_scope_of($table, $channels)]++;
    }

    return $counts;
}

/**
 * The tables of one scope, in the order they were given.
 *
 * @param array  $tables   ws_wtables_reached($viewer, 'all')
 * @param string $scope    ws_wtable_list_scope()
 * @param array  $channels as ws_wtable_list_scope_of() takes it
 * @return array
 */
function ws_wtable_list_pick(array $tables, $scope, array $channels = array())
{
    if ($scope === 'all') {
        return array_values($tables);
    }

    return array_values(array_filter($tables, function ($table) use ($scope, $channels) {
        return ws_wtable_list_scope_of($table, $channels) === $scope;
    }));
}

/**
 * How many columns each table has, in one query.
 *
 * @param array $table_ids
 * @return array table id => count, 0 for a table without columns
 */
function ws_wtable_list_column_counts(array $table_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $table_ids), function ($id) {
        return $id > 0;
    })));
    $counts = array_fill_keys($ids, 0);

    if (empty($ids) || !ws_wtables_ready()) {
        return $counts;
    }

    foreach ((array) db_items("SELECT table_id, COUNT(*) AS column_count FROM ws_wtable_columns
        WHERE table_id IN ('" . implode("','", $ids) . "') GROUP BY table_id") as $row) {
        $counts[(int) $row['table_id']] = (int) $row['column_count'];
    }

    return $counts;
}

/**
 * The line under the screen's name: how many tables the person reaches.
 *
 * @param int $count
 * @return string plain text
 */
function ws_wtable_list_count_text($count)
{
    return lang(array('string' => '{var:1} tables', 'vars' => (int) $count));
}

/**
 * A segmented control whose segments are addresses (look A's .pg-seg): the
 * chosen one is the current page, a count sits beside a label that has one.
 *
 * @param string $label the control's accessible name
 * @param array  $items list of array('label', 'url', 'current' (bool),
 *                      'count' (left out for a segment without one))
 * @return string
 */
function ws_wtable_list_segments($label, array $items)
{
    $html = '';

    foreach ($items as $item) {
        $current = !empty($item['current']);

        $html .= '<a class="pg-seg-item' . ($current ? ' is-active' : '') . '" href="' . h((string) $item['url']) . '"' . ($current ? ' aria-current="page"' : '') . '>'
            . h((string) $item['label'])
            . (isset($item['count']) ? ' <span class="pg-seg-count">' . (int) $item['count'] . '</span>' : '')
            . '</a>';
    }

    return '<span class="pg-seg" role="group" aria-label="' . h((string) $label) . '">' . $html . '</span>';
}

/**
 * The columns of the list, as pg_list_table_printed() takes them. The name
 * leads (it opens the table); the row's buttons do not sort.
 *
 * @return array key => spec
 */
function ws_wtable_list_columns()
{
    return array(
        'name'    => array('heading' => lang('Name'), 'kind' => 'link'),
        'rows'    => array('heading' => lang('Rows'), 'kind' => 'number'),
        'columns' => array('heading' => lang('Columns'), 'kind' => 'number'),
        'place'   => array('heading' => lang('Where')),
        'updated' => array('heading' => lang('Updated'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    );
}

/**
 * The name cell of a table or a deleted one: its tile, its name and, in
 * the second text, its description.
 *
 * @param array  $table name, description
 * @param string $url   the address the name opens, '' for a name that
 *                      opens nothing (a deleted table)
 * @return string
 */
function ws_wtable_list_name_html($table, $url)
{
    $name = h((string) ($table['name'] ?? ''));
    $description = trim((string) ($table['description'] ?? ''));

    // Inline parts, so a long description ends in the cell's ellipsis; one
    // wrapper, so a phone's card (a flex cell under its label) keeps them
    // together as one value that wraps.
    return '<span>'
        . '<span class="pg-icon-tile d-inline-grid align-middle me-3 ' . (($url !== '') ? 'pg-tone-teal' : 'pg-tone-gray') . '" aria-hidden="true"><span class="bi bi-table"></span></span>'
        . (($url !== '') ? '<a href="' . h($url) . '" data-pg-open>' . $name . '</a>' : '<span>' . $name . '</span>')
        . (($description !== '') ? ' <span class="pg-text-2 fg-secondary">· ' . h($description) . '</span>' : '')
        . '</span>';
}

/**
 * Where a table belongs, for the person looking: its channel, their own,
 * or its owner with what the share lets them do.
 *
 * @param array      $table   access (owner | edit | view)
 * @param array|null $owner   the owner's brief (ws_people()), null when
 *                            it is not known
 * @param array|null $channel the channel the table belongs to (name, kind),
 *                            null for a person's table
 * @return string
 */
function ws_wtable_list_place_html($table, $owner, $channel = null)
{
    if (is_array($channel)) {
        return '<span class="d-inline-flex align-items-center gap-2 pg-text-2">'
            . '<i class="bi ' . (((string) ($channel['kind'] ?? '') === 'private') ? 'bi-lock' : 'bi-hash') . ' fg-secondary" aria-hidden="true"></i>'
            . '<span>' . h((string) ($channel['name'] ?? '')) . '</span></span>';
    }

    $access = (string) ($table['access'] ?? '');
    $levels = array('edit' => lang('Can edit'), 'view' => lang('Can view'));

    // The name stands beside the picture, so the picture itself says nothing.
    $picture = is_array($owner) ? pg_avatar_markup(array('name' => '', 'presence' => '') + $owner, 'xs') : '';
    $text = ($access === 'owner')
        ? h(lang('Mine'))
        : h(is_array($owner) ? (string) $owner['name'] : lang('Former member')) . (isset($levels[$access]) ? ' · ' . h($levels[$access]) : '');

    return '<span class="d-inline-flex align-items-center gap-2 pg-text-2">' . $picture . '<span>' . $text . '</span></span>';
}

/**
 * The cells of one table the person reaches.
 *
 * @param array $table a ws_wtables_reached() row: id, name, description,
 *                     row_count, updated_at, access
 * @param array $facts url (the screen's address), columns (the table's
 *                     column count), owner (the owner's brief or null),
 *                     channel (the channel it belongs to, or null), confirm
 *                     (the question a delete asks, for the owner)
 * @return array key => pg_list_cell()
 */
function ws_wtable_list_cells(array $table, array $facts)
{
    $id = (int) $table['id'];
    $url = (string) ($facts['url'] ?? '') . '?id=' . $id;
    $rows = (int) $table['row_count'];
    $columns = (int) ($facts['columns'] ?? 0);

    $actions = '<a class="pg-icon-button pg-icon-button-sm" href="' . h($url . '&download=xlsx') . '" download'
        . ' title="' . h(lang('Download as Excel')) . '" aria-label="' . h(lang('Download as Excel')) . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></a>';

    // Only the owner deletes a table; the store checks it again.
    if (ws_wtable_may((string) $table['access'], 'manage')) {
        $actions .= '<button type="button" class="pg-icon-button pg-icon-button-sm ws-wt-action" data-action="table_delete" data-table="' . $id . '"'
            . ' data-confirm="' . h((string) ($facts['confirm'] ?? '')) . '"'
            . ' title="' . h(lang('Delete table')) . '" aria-label="' . h(lang('Delete table')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';
    }

    return array(
        'name'    => pg_list_cell(ws_wtable_list_name_html($table, $url), 'align-middle', array('data-pg-sort-value' => (string) $table['name'])),
        'rows'    => pg_list_cell((string) $rows, 'align-middle pg-tabular', array('data-pg-sort-value' => $rows)),
        'columns' => pg_list_cell((string) $columns, 'align-middle pg-tabular', array('data-pg-sort-value' => $columns)),
        'place'   => pg_list_cell(ws_wtable_list_place_html($table, $facts['owner'] ?? null, $facts['channel'] ?? null)),
        'updated' => pg_list_table_when_cell((int) $table['updated_at']),
        'actions' => pg_list_cell($actions),
    );
}

/**
 * The rows of the list, as pg_list_table_printed() takes them.
 *
 * @param array $tables the tables of the chosen scope
 * @param array $facts  url, counts (table id => column count), people
 *                      (user id => brief, the owners), channels (table id
 *                      => the channel a table belongs to), confirms (table
 *                      id => the delete question)
 * @return array list of array('id', 'cells')
 */
function ws_wtable_list_rows(array $tables, array $facts)
{
    $rows = array();

    foreach ($tables as $table) {
        $id = (int) $table['id'];

        $rows[] = array('id' => $id, 'cells' => ws_wtable_list_cells($table, array(
            'url'     => (string) ($facts['url'] ?? ''),
            'columns' => (int) ($facts['counts'][$id] ?? 0),
            'owner'   => $facts['people'][(int) $table['user_id']] ?? null,
            'channel' => $facts['channels'][$id] ?? null,
            'confirm' => (string) ($facts['confirms'][$id] ?? ''),
        )));
    }

    return $rows;
}

/**
 * The columns of the deleted tables' list.
 *
 * @return array key => spec
 */
function ws_wtable_list_deleted_columns()
{
    return array(
        'name'    => array('heading' => lang('Name')),
        'rows'    => array('heading' => lang('Rows'), 'kind' => 'number'),
        'deleted' => array('heading' => lang('Deleted'), 'kind' => 'date'),
        'expires' => array('heading' => lang('Deleted for good on'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    );
}

/**
 * The cells of one deleted table of the person's own: it opens nothing and
 * is restored whole.
 *
 * @param array $table a ws_wtables_binned() row: id, name, description,
 *                     row_count, deleted_at, expires_at
 * @return array key => pg_list_cell()
 */
function ws_wtable_list_deleted_cells(array $table)
{
    $rows = (int) $table['row_count'];
    $expires = (int) $table['expires_at'];

    $restore = '<button type="button" class="pg-button pg-button-quiet pg-button-sm ws-wt-action" data-action="table_restore" data-table="' . (int) $table['id'] . '">'
        . '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>' . h(lang('Restore')) . '</button>';

    return array(
        'name'    => pg_list_cell(ws_wtable_list_name_html($table, ''), 'align-middle', array('data-pg-sort-value' => (string) $table['name'])),
        'rows'    => pg_list_cell((string) $rows, 'align-middle pg-tabular', array('data-pg-sort-value' => $rows)),
        'deleted' => pg_list_table_when_cell((int) $table['deleted_at']),
        'expires' => pg_list_cell(h(date('d.m.Y', $expires)), 'align-middle pg-tabular', array('data-pg-sort-value' => $expires)),
        'actions' => pg_list_cell($restore),
    );
}
