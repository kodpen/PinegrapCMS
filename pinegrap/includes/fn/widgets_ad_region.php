<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Ad Region system widget (kind 'ad_region'): the
 * classic <ad> region drawn by the designer. The ads stay in the Ads screen
 * (ads, ad_regions) and with the users who may edit that region
 * (users_ad_regions_xref); the widget reads them on every request.
 *
 * The main loop repeats the ads: one picked at random in a static region,
 * every ad in a dynamic one, in their sort order (shuffled when none has
 * one), as the classic region orders them. The starter turns them into a
 * Bootstrap carousel, its rotation taken from the region's slideshow
 * settings.
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
 * The ads a region shows, in order, as the classic region picks them: one
 * at random in a static region; every ad in a dynamic one, by sort order,
 * shuffled when no ad has a sort order.
 *
 * @param array    $ads     ads rows (id, name, content, caption, label, sort_order), by sort order
 * @param string   $display ad_regions.display_type ('static' or 'dynamic')
 * @param callable $shuffle reorders an array (null: shuffle())
 * @return array
 */
function pg_aw_pick($ads, $display, $shuffle = null)
{
    $ads = is_array($ads) ? array_values(array_filter($ads, 'is_array')) : array();
    if (!$ads) return array();
    $shuffle = ($shuffle !== null) ? $shuffle : function ($list) { shuffle($list); return $list; };
    if ($display === 'static') {
        $mixed = call_user_func($shuffle, $ads);
        return array(reset($mixed));
    }
    foreach ($ads as $ad) {
        if (!empty($ad['sort_order'])) return $ads;
    }
    return array_values(call_user_func($shuffle, $ads));
}

/**
 * The carousel settings of a region's slideshow, as attribute values:
 * ride, interval in milliseconds and wrap. Without a slideshow the
 * carousel waits for the visitor.
 *
 * @param array $region ad_regions row (slideshow, slideshow_interval, slideshow_continuous)
 * @return array
 */
function pg_aw_rotation($region)
{
    $on = is_array($region) && !empty($region['slideshow']);
    $seconds = $on ? max(1, (int)$region['slideshow_interval']) : 0;
    return array(
        '__ad_ride'     => $on ? 'carousel' : 'false',
        '__ad_interval' => $on ? (string)($seconds * 1000) : 'false',
        '__ad_wrap'     => ($on && empty($region['slideshow_continuous'])) ? 'false' : 'true',
    );
}

/**
 * $html with class "active" on its first element when that element is a
 * carousel item: Bootstrap shows only the active item, so the first ad
 * must carry it.
 *
 * @param string $html one row's markup
 * @return string
 */
function pg_aw_mark_first_active($html)
{
    if (!preg_match('/^(\s*(?:<!--.*?-->\s*)*)<([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/s', (string)$html, $m)) return $html;
    if (!preg_match('/\sclass\s*=\s*"([^"]*)"/i', $m[3], $c)) return $html;
    $classes = preg_split('/\s+/', trim($c[1]));
    if (!in_array('carousel-item', $classes, true) || in_array('active', $classes, true)) return $html;
    $attrs = preg_replace('/(\sclass\s*=\s*")([^"]*)"/i', '$1$2 active"', $m[3], 1);
    return $m[1] . '<' . $m[2] . $attrs . '>' . substr($html, strlen($m[0]));
}

/**
 * Whether the user may edit the ads of region $region_id, as the classic
 * region's edit buttons ask: a manager or above, or a user granted the
 * region.
 *
 * @param int $region_id
 * @return bool
 */
function pg_aw_can_edit_region($region_id)
{
    if (!pg_sw_edit_chips_on()) return false;
    if (defined('USER_ROLE') && (int)USER_ROLE < 3) return true;
    return defined('USER_ID') && in_array((string)(int)$region_id, array_map('strval', (array)get_items_user_can_edit('ad_regions', USER_ID)), true);
}

// Render an 'ad_region' system widget.
//
// loop_area: one row per ad shown (pg_aw_pick()). Row tokens:
// ^^__ad_name^^, ^^__ad_label^^, ^^__ad_content_html^^ and
// ^^__ad_caption_html^^ (the ad's own markup). Row flag: ad_has_caption.
// The first row's carousel item is marked active.
// Tokens: ^^__ad_region_name^^, ^^__ad_count^^, ^^__ad_ride^^,
// ^^__ad_interval^^, ^^__ad_wrap^^ (pg_aw_rotation(), for the carousel's
// data-bs-* attributes).
// Flags: has_ads, no_ads, has_several_ads. Messages: 'ad_region'.
// cfg: ad_region_name (the region, by its name as the classic tag names it).
// Draws nothing while ads are switched off.
function _render_system_widget_ad_region($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    static $placement = 0;
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!defined('ADS') || ADS !== true) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $name = isset($cfg['ad_region_name']) && is_string($cfg['ad_region_name']) ? trim($cfg['ad_region_name']) : '';
    $region = ($name !== '') ? db_item(
        "SELECT id, name, display_type, slideshow, slideshow_interval, slideshow_continuous
        FROM ad_regions WHERE name = '" . e($name) . "'") : null;
    $region_id = is_array($region) ? (int)$region['id'] : 0;
    $ads = ($region_id > 0) ? pg_aw_pick((array)db_items(
        "SELECT id, name, content, caption, label, sort_order
        FROM ads WHERE ad_region_id = '" . $region_id . "'
        ORDER BY sort_order ASC"), (string)$region['display_type']) : array();

    // A row's own flag is decided per row, below.
    _eo_apply_visibility_bindings($tree, array(
        'has_ads'         => (bool)$ads,
        'no_ads'          => !$ads,
        'has_several_ads' => count($ads) > 1,
        'ad_has_caption'  => true,
    ));
    _pg_inject_messages_node($tree, 'ad_region');

    $split = _split_widget_tree($tree);
    $can_edit = ($region_id > 0 && $mode !== 'background' && pg_aw_can_edit_region($region_id));
    $rows = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($ads as $i => $ad) {
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array('ad_has_caption' => trim((string)$ad['caption']) !== ''));
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            // The layout's ids are made the row's own before the ad's markup
            // goes in: an id in the ad stays as the ad's author wrote it.
            $html = pg_sw_uniquify_row_ids(trim($html), $widget_id, (int)$ad['id']);
            $html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($html, pg_sw_escape_token_values(array(
                '__ad_name'         => (string)$ad['name'],
                '__ad_label'        => (string)$ad['label'],
                '__ad_content_html' => (string)$ad['content'],
                '__ad_caption_html' => (string)$ad['caption'],
            ))));
            if ($i === 0) $html = pg_aw_mark_first_active($html);
            if ($can_edit) {
                $html = pg_sw_add_edit_chip($html, pg_sw_backend_edit_url('edit_ad.php', (int)$ad['id']),
                    lang('Ad') . ': ' . (string)$ad['name'], 'page');
            }
            $rows .= $html;
        }
    }

    $static = trim(_render_tree_node($split['static_tree'], 0, 0));
    $tokens = pg_sw_escape_token_values(pg_aw_rotation($region) + array(
        '__ad_region_name' => is_array($region) ? (string)$region['name'] : '',
        '__ad_count'       => (string)count($ads),
    ));
    // A page may place one region twice.
    $static = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, $tokens)), $widget_id, 1000 + (++$placement));
    return str_replace('<!--pg-loop-slot-->', $rows, $static);
}
