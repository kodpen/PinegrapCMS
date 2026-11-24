<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Packing card: four figures (addresses waiting to be packed, the
 * oldest ship-by day among them, those due today or late, packed today),
 * then a row a packer for this week: packs, units an hour, refused scans.
 * Read from the pack records alone (pg_pack_card_data()); See all opens
 * Pick waves. For the orders right, not the "Packing only" right alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The card's figures and rows from pg_pack_card_data(). Pure but for the
 * texts and the date format.
 *
 * @param array $figures pg_pack_card_figures() with 'names'
 * @return array tiles, rows
 */
function pg_dashboard_v2_packing_view(array $figures)
{
    $tiles = array(
        array('label' => lang('Waiting'), 'text' => pg_format_number($figures['waiting'], 0), 'note' => '', 'tone' => ''),
        array('label' => lang('Oldest'), 'text' => ($figures['oldest'] > 0)
            ? get_absolute_time(array('timestamp' => $figures['oldest'], 'type' => 'date', 'size' => 'short', 'format' => 'plain_text'))
            : '—', 'note' => lang('Ship-by day'), 'tone' => ''),
        array('label' => lang('Due today or late'), 'text' => pg_format_number($figures['due'], 0),
            'note' => ($figures['due'] > 0) ? lang('Pack these first') : '', 'tone' => ($figures['due'] > 0) ? 'bad' : ''),
        array('label' => lang('Packed today'), 'text' => pg_format_number($figures['packed_today'], 0), 'note' => '', 'tone' => ''),
    );

    $rows = array();

    foreach ($figures['packers'] as $packer) {
        $name = isset($figures['names'][$packer['packer_id']]) ? $figures['names'][$packer['packer_id']] : lang('A removed user');

        $rows[] = array(
            'text' => $name,
            'meta' => lang(array('string' => '{var:1} packs · {var:2} units an hour', 'vars' => array(
                pg_format_number($packer['packs'], 0),
                ($packer['per_hour'] === null) ? '—' : pg_format_number($packer['per_hour'], 0),
            ))),
            'note' => lang(array('string' => '{var:1} refused', 'vars' => array(pg_format_number($packer['refused'], 0)))),
            'tone' => ($packer['refused'] > 0) ? 'warn' : '',
        );
    }

    return array('tiles' => $tiles, 'rows' => $rows);
}

function pg_dashboard_v2_card_packing($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop', $user)) {
        return array('status' => 'denied');
    }

    if (!pg_pick_waves_ready()) {
        return array('status' => 'success', 'data' => array('empty' => lang('Packing is counted once the software update has run.')));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/pack.php');

    $view = pg_dashboard_v2_packing_view(pg_pack_card_data());

    return array('status' => 'success', 'data' => array(
        'tiles'      => $view['tiles'],
        'rows'       => $view['rows'],
        'rows_title' => lang('This week'),
        'none'       => lang('No parcel was packed this week.'),
        'url'        => 'view_pick_waves.php',
    ));
}
