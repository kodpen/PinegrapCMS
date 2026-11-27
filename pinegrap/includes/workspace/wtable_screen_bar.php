<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the bar of one Data table's screen (workspace_wtables.php?id=N):
 * the Workspace's one-line bar drawn by the server with the classes of
 * workspace.js's barViews() and barFilter(). New row, the Table and Board
 * views, "Filter" holding the query bar (wtable_query.php) with the count of
 * what is set and a tag line under the bar, and a "⋯" menu whose entries
 * run an action or open the screen's drawers (columns, shares, the table's
 * channel, deleted rows). Nothing here reads the database.
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
 * How many things the query sets, for the count on "Filter": each condition
 * typed, and the order when it is not the table's own. Pure.
 *
 * @param array $screen ws_wtable_query_from_get()
 * @return int
 */
function ws_wtable_screen_query_count($screen)
{
    $sorted = ((int) ($screen['sort'] ?? 0) > 0) || ((string) ($screen['dir'] ?? 'asc') === 'desc');

    return count((array) ($screen['typed'] ?? array())) + ($sorted ? 1 : 0);
}

/**
 * What the query sets, as the tags under the bar: one a condition, in the
 * column's words ("Status is Done", a status by its label, a person by name,
 * a checkbox ticked or not), then the order; each with the address that takes
 * it away and keeps the rest (the totals switch included). Pure.
 *
 * @param array  $columns     ws_wtable_columns()
 * @param array  $screen      ws_wtable_query_from_get()
 * @param array  $team        list of array('id', 'name')
 * @param string $table_url   the table's address (…?id=N)
 * @param bool   $totals_all  whether the totals are over every row
 * @param array  $row_choices row link column id => array(row id => text)
 * @return array list of array('text', 'url')
 */
function ws_wtable_screen_query_tags($columns, $screen, $team, $table_url, $totals_all, $row_choices = array())
{
    $typed = array_values((array) ($screen['typed'] ?? array()));
    $screen['typed'] = $typed;
    $operators = ws_wtable_where_operators();
    $extra = $totals_all ? array('totals' => 'all') : array();
    $url = function ($kept) use ($table_url, $extra) {
        $params = ws_wtable_query_params($kept) + $extra;

        return $table_url . (empty($params) ? '' : '&' . http_build_query($params)) . '#ws-wt-rows';
    };
    $tags = array();

    foreach ($typed as $place => $line) {
        $column = $columns[(string) (int) $line['column']] ?? null;
        $name = is_array($column) ? (string) $column['name'] : lang('Column');
        $type = is_array($column) ? (string) $column['type'] : '';
        $value = (string) $line['value'];

        if ($type === 'status') {
            $key = ws_wtable_query_screen_value($column, $value);

            foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                if ((string) $choice['key'] === (string) $key) {
                    $value = (string) $choice['label'];
                }
            }
        } elseif ($type === 'checkbox') {
            $value = array('1' => lang('Ticked'), '0' => lang('Not ticked'))[(string) ws_wtable_query_screen_value($column, $value)] ?? $value;
        } elseif ($type === 'person') {
            foreach ((array) $team as $person) {
                if ((string) (int) $person['id'] === $value) {
                    $value = (string) $person['name'];
                }
            }
        } elseif (($type === 'row') && isset($row_choices[(string) (int) $line['column']][$value])) {
            $value = (string) $row_choices[(string) (int) $line['column']][$value];
        }

        $op = (string) $line['op'];
        $words = array($name, $operators[$op] ?? '…');

        if (!in_array($op, array('empty', 'not_empty'), true)) {
            $words[] = ($value !== '') ? $value : '…';
        }

        $kept = $screen;
        unset($kept['typed'][$place]);
        $tags[] = array('text' => implode(' ', $words), 'url' => $url($kept));
    }

    if (((int) ($screen['sort'] ?? 0) > 0) || ((string) ($screen['dir'] ?? 'asc') === 'desc')) {
        $sort = (int) ($screen['sort'] ?? 0);
        $by = ($sort > 0) ? (string) ($columns[(string) $sort]['name'] ?? lang('Column')) : lang('Table order');
        $kept = $screen;
        $kept['sort'] = 0;
        $kept['dir'] = 'asc';
        $tags[] = array(
            'text' => lang(array('string' => 'Sorted by {var:1}, {var:2}', 'vars' => array($by, ((string) ($screen['dir'] ?? 'asc') === 'desc') ? lang('Descending') : lang('Ascending')))),
            'url'  => $url($kept),
        );
    }

    return $tags;
}

/**
 * The line of tags under the bar, each with its "×" (a link that takes it
 * away), then Clear; empty when nothing is set.
 *
 * @param array  $tags      ws_wtable_screen_query_tags()
 * @param string $clear_url the table with no query
 * @return string HTML
 */
function ws_wtable_screen_tags_html($tags, $clear_url)
{
    if (empty($tags)) {
        return '';
    }

    $out = '';

    foreach ($tags as $tag) {
        $remove = lang(array('string' => 'Remove the filter: {var:1}', 'vars' => array((string) $tag['text'])));
        $out .= '<span class="ws-bar-tag">' . h((string) $tag['text'])
            . '<a class="ws-bar-tag-remove" href="' . h((string) $tag['url']) . '" title="' . h($remove) . '" aria-label="' . h($remove) . '"><i class="bi bi-x" aria-hidden="true"></i></a></span>';
    }

    return '<div class="ws-bar-tags">' . $out
        . '<a class="pg-button pg-button-ghost pg-button-sm" href="' . h($clear_url) . '">' . h(lang('Clear')) . '</a></div>';
}

/**
 * The table's one-line bar: New row (filled), the views as a segmented
 * control and the select a narrow bar shows, the grow slot, "Filter" with
 * its count and its box, and "⋯". A part left empty is not drawn.
 *
 * @param array $bar new_row (address or ''), views (list of array('label',
 *                   'icon', 'url', 'here')), filter (the box's form or ''),
 *                   count, menu (ws_wtable_screen_menu() or ''), tags (HTML)
 * @return string HTML
 */
function ws_wtable_screen_bar($bar)
{
    $out = '';

    if ((string) ($bar['new_row'] ?? '') !== '') {
        $out .= '<a class="pg-button pg-button-fill" href="' . h((string) $bar['new_row']) . '"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="ws-bar-label">' . h(lang('New row')) . '</span></a>';
    }

    if (!empty($bar['views'])) {
        $seg = '';
        $options = '';

        foreach ($bar['views'] as $view) {
            $here = !empty($view['here']);
            $seg .= '<a class="pg-seg-item' . ($here ? ' is-active' : '') . '" href="' . h((string) $view['url']) . '"' . ($here ? ' aria-current="page"' : '') . '>'
                . '<i class="bi ' . h((string) $view['icon']) . '" aria-hidden="true"></i>' . h((string) $view['label']) . '</a>';
            $options .= '<option value="' . h((string) $view['url']) . '"' . ($here ? ' selected' : '') . '>' . h((string) $view['label']) . '</option>';
        }

        $out .= '<div class="ws-bar-views"><div class="pg-seg ws-bar-views-seg" role="group" aria-label="' . h(lang('View')) . '">' . $seg . '</div>'
            . '<select class="pg-input pg-input-sm ws-bar-views-select" data-ws-bar-go aria-label="' . h(lang('View')) . '">' . $options . '</select></div>';
    }

    $out .= '<span class="pg-toolbar-grow"></span>';

    if ((string) ($bar['filter'] ?? '') !== '') {
        $count = (int) ($bar['count'] ?? 0);
        $named = ($count > 0) ? lang(array('string' => 'Filter, {var:1} set', 'vars' => array($count))) : lang('Filter');
        $out .= '<span class="ws-bar-filter ws-wt-filter">'
            . '<button type="button" class="pg-button pg-button-quiet ws-bar-filter-button" id="ws-wt-filter-button" aria-haspopup="dialog" aria-expanded="false" aria-controls="ws-wt-filter-box" aria-label="' . h($named) . '">'
            . '<i class="bi bi-funnel" aria-hidden="true"></i><span class="ws-bar-filter-label">' . h(lang('Filter')) . '</span>'
            . '<span class="ws-bar-count" aria-hidden="true"' . (($count > 0) ? '' : ' hidden') . '>' . $count . '</span></button>'
            . '<div class="pg-card ws-bar-box ws-wt-filter-box" id="ws-wt-filter-box" role="dialog" aria-label="' . h(lang('Filter')) . '" hidden>' . $bar['filter'] . '</div>'
            . '</span>';
    }

    $out .= (string) ($bar['menu'] ?? '');

    return '<div class="ws-bar-frame"><nav class="pg-toolbar ws-bar" aria-label="' . h(lang('Button Bar')) . '">' . $out . '</nav>'
        . (string) ($bar['tags'] ?? '') . '</div>';
}

/**
 * The "⋯" menu of the bar. An item is a header (array('header' => text)), a
 * divider (array('divider' => true)) or an entry: label, icon, and one of
 * drawer (the id it opens), href (with download when it is a file), attrs
 * (attributes as they stand, already escaped) or action (a .ws-wt-action
 * post with its data and confirm); danger marks the last, red entry.
 * Empty when no entry is left.
 *
 * @param array $items
 * @return string HTML
 */
function ws_wtable_screen_menu($items)
{
    $out = '';
    $entries = 0;

    foreach ($items as $item) {
        if (isset($item['header'])) {
            $out .= '<li><h6 class="menu-header">' . h((string) $item['header']) . '</h6></li>';
            continue;
        }

        if (!empty($item['divider'])) {
            $out .= '<li><hr class="menu-divider"></li>';
            continue;
        }

        $entries++;
        $class = 'menu-item' . (!empty($item['danger']) ? ' fg-emphasis-danger' : '');
        $inner = '<i class="bi ' . h((string) $item['icon']) . ' me-3" aria-hidden="true"></i>' . h((string) $item['label']);

        if (isset($item['href'])) {
            $out .= '<li><a class="' . $class . '" href="' . h((string) $item['href']) . '"' . (!empty($item['download']) ? ' download' : '') . '>' . $inner . '</a></li>';
        } elseif (isset($item['drawer'])) {
            $out .= '<li><button type="button" class="' . $class . '" data-bs-toggle="drawer" data-bs-target="#' . h((string) $item['drawer']) . '" aria-controls="' . h((string) $item['drawer']) . '">' . $inner . '</button></li>';
        } elseif (isset($item['action'])) {
            $data = '';

            foreach ((array) ($item['data'] ?? array()) as $key => $value) {
                $data .= ' data-' . h((string) $key) . '="' . h((string) $value) . '"';
            }

            $out .= '<li><button type="button" class="' . $class . ' ws-wt-action" data-action="' . h((string) $item['action']) . '"' . $data
                . ((string) ($item['confirm'] ?? '') !== '' ? ' data-confirm="' . h((string) $item['confirm']) . '"' : '') . '>' . $inner . '</button></li>';
        } else {
            $out .= '<li><button type="button" class="' . $class . '" ' . (string) ($item['attrs'] ?? '') . '>' . $inner . '</button></li>';
        }
    }

    if ($entries === 0) {
        return '';
    }

    return '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button ws-wt-more" data-bs-toggle="menu" aria-expanded="false" title="' . h(lang('More actions')) . '" aria-label="' . h(lang('More actions')) . '">'
        . '<i class="bi bi-three-dots" aria-hidden="true"></i></button><ul class="menu pg-menu-end ws-wt-menu">' . $out . '</ul></span>';
}

/**
 * A drawer of the table's screen, opened from "⋯" or by the address's hash
 * (its id, or the id of something inside it): the icon tile and title in its
 * head, the close cross, the body as given.
 *
 * @param string $id    the drawer's id, the old card's anchor
 * @param string $icon  a bi-* icon
 * @param string $tone  a pg-tone-* name (blue, teal, violet, amber, gray, rose)
 * @param string $title
 * @param string $body  HTML
 * @return string HTML
 */
function ws_wtable_screen_drawer($id, $icon, $tone, $title, $body)
{
    return '
    <dialog class="drawer drawer-end ws-wt-drawer" id="' . h($id) . '" aria-labelledby="' . h($id) . '-title">
        <div class="drawer-header">
            <span class="pg-icon-tile pg-tone-' . h($tone) . '" aria-hidden="true"><i class="bi ' . h($icon) . '"></i></span>
            <h5 class="drawer-title flex-grow-1 mb-0" id="' . h($id) . '-title">' . h($title) . '</h5>
            <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
        </div>
        <div class="drawer-body">' . $body . '</div>
    </dialog>';
}
