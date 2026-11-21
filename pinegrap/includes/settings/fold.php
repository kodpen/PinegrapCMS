<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Folded rows of a Site Settings pane: each part of the pane is one row with
 * an icon tile, its name, one line of state, a state pill and a chevron, and
 * opens to show its card. One row is open at a time.
 *
 * The card inside a row is drawn by its own function as before, its id,
 * fields, buttons and drawer triggers unchanged: the search index, the
 * anchors and the save read them through the row. Which row opens on the
 * client (a click, an anchor, a search hit, a field the save marked, a
 * drawer opened again, the row kept over a save) is pgSettingsFoldPick() and
 * pgSettingsFoldReveal() in backend.src.js; the row a pane arrives with is
 * pg_settings_fold_pick().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY') && !defined('PG_API_PANEL')) {
    exit;
}

/**
 * The row a pane arrives with open: the first part that needs attention,
 * else the first row. Pure.
 *
 * @param array $rows list of key, attention
 * @return string the row's key, '' for no rows
 */
function pg_settings_fold_pick($rows)
{
    foreach ((array) $rows as $row) {
        if (!empty($row['attention'])) {
            return (string) $row['key'];
        }
    }

    $first = reset($rows);

    return is_array($first) ? (string) $first['key'] : '';
}

/**
 * One folded row. The row's ids start with pg-fold-, never pgset-: a pgset-
 * id ends the card before it for the search index
 * (pg_settings_card_labels()).
 *
 * @param array $row  key, name, icon (a bi- class), tone (a pg-tone- class or
 *                    ''), line, pill (array words, tone class; or null),
 *                    attention, html (the card)
 * @param bool  $open
 * @return string HTML
 */
function pg_settings_fold_row($row, $open)
{
    $id = 'pg-fold-' . preg_replace('/[^a-z0-9-]/', '', strtolower((string) $row['key']));
    $tone = ((string) ($row['tone'] ?? '') !== '') ? ' ' . h($row['tone']) : '';
    $pill = '';

    if (!empty($row['pill'])) {
        $pill = '<span class="pg-pill ' . h($row['pill'][1]) . '">' . h($row['pill'][0]) . '</span>';
    }

    return '
    <section class="pg-set-fold-item' . ($open ? ' is-open' : '') . '" id="' . $id . '" data-pg-fold="' . h($row['key']) . '"' . (!empty($row['attention']) ? ' data-pg-fold-attention' : '') . '>
        <button type="button" class="pg-set-fold-head" id="' . $id . '-head" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $id . '-body">
            <span class="pg-icon-tile pg-icon-tile-lg' . $tone . '"><i class="bi ' . h($row['icon']) . '" aria-hidden="true"></i></span>
            <span class="pg-set-fold-text">
                <span class="pg-set-fold-name">' . h($row['name']) . '</span>
                <span class="pg-set-fold-line">' . h($row['line']) . '</span>
            </span>
            ' . $pill . '
            <i class="bi bi-chevron-down pg-set-fold-chevron" aria-hidden="true"></i>
        </button>
        <div class="pg-set-fold-body" id="' . $id . '-body" role="region" aria-labelledby="' . $id . '-head"' . ($open ? '' : ' hidden') . '>
            ' . $row['html'] . '
        </div>
    </section>';
}

/**
 * The pane's rows as one list, the row of pg_settings_fold_pick() open.
 *
 * @param array $rows as pg_settings_fold_row() takes them, in reading order
 * @return string HTML, '' for no rows
 */
function pg_settings_fold($rows)
{
    if (empty($rows)) {
        return '';
    }

    $open = pg_settings_fold_pick($rows);
    $html = '';

    foreach ($rows as $row) {
        $html .= pg_settings_fold_row($row, (string) $row['key'] === $open);
    }

    return '
    <div class="pg-set-fold" data-pg-folds>' . $html . '
    </div>';
}
