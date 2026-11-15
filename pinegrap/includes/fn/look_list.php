<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shared look A parts of list and settings screens: a list's filters as a
 * segmented control with a count per segment, and the icon tile at the left
 * of a settings row. The look of both is in backend.src.css (block "Look A:
 * list title and settings tile"); the count beside a list screen's title is
 * written by the list table driver (assets/js/pg_list_table.js).
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
 * A list's filters as a segmented control (.pg-seg): one button per value of
 * one column of a list on the table driver. The driver's filter listener
 * (data-pg-list-filter) narrows the list and moves aria-pressed, which draws
 * the chosen segment as the raised thumb.
 *
 * @param string $list    the list's name (data-pg-list-table)
 * @param string $column  the column key the filter reads
 * @param array  $items   value => label, or value => array('label' => …,
 *                        'count' => int); '' is the segment that shows every
 *                        value. Labels are plain text, escaped here.
 * @param string $label   the group's accessible name; defaults to "Filter"
 * @param string $current the value chosen when the screen opens
 * @return string '' when there are no items
 */
function pg_list_seg_filters($list, $column, array $items, $label = '', $current = '')
{
    if (!$items) {
        return '';
    }

    $label = ((string) $label !== '') ? (string) $label : lang('Filter');
    $current = (string) $current;
    $html = '';

    foreach ($items as $value => $item) {
        $value = (string) $value;
        $text = is_array($item) ? (string) ($item['label'] ?? '') : (string) $item;
        $count = (is_array($item) && isset($item['count'])) ? '<span class="pg-seg-count pg-tabular">' . (int) $item['count'] . '</span>' : '';
        $on = ($value === $current);

        $html .= '<button type="button" class="pg-seg-item' . ($on ? ' active' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '"'
            . ' data-pg-list-filter="' . h((string) $list) . '" data-pg-filter-column="' . h((string) $column) . '" data-pg-filter-value="' . h($value) . '">'
            . h($text) . $count . '</button>';
    }

    return '<div class="pg-seg pg-list-seg" role="group" aria-label="' . h($label) . '">' . $html . '</div>';
}

/**
 * The 34 px icon tile at the left of a settings row: printed as the first
 * child of the row's .form-field (a switch row) or of the row's cell (a
 * label and field row); the settings card's grid gives it a column of its
 * own. Decorative: the row's label names the setting.
 *
 * @param string $icon a Bootstrap Icons name, with or without "bi-"
 * @param string $tone a look tone (blue, violet, amber, teal, rose, gray) or
 *                     module tone (team, shop, content, accounting, warning,
 *                     reports, system); '' is gray
 * @return string '' for an icon name that is not one
 */
function pg_settings_tile($icon, $tone = '')
{
    $icon = preg_replace('/^bi-/', '', (string) $icon);

    if (!preg_match('/^[a-z0-9-]+$/', $icon)) {
        return '';
    }

    $tone = (string) $tone;
    $tone_class = preg_match('/^(blue|violet|amber|teal|rose|gray|team|shop|content|accounting|warning|reports|system)$/', $tone) ? ' pg-tone-' . $tone : '';

    return '<span class="pg-icon-tile pg-icon-tile-lg pg-set-tile' . $tone_class . '" aria-hidden="true"><i class="bi bi-' . $icon . '"></i></span>';
}
