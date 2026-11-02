<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's card grid and the configuration its script starts
 * from: the cards the person may see with their titles, icons, categories,
 * widths and periods, and the texts the script shows.
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
 * The cards the grid draws, in order: the stored layout's cards the person
 * may see (unknown ids, repeats and bad values dropped or set to the card's
 * default), or every card they may see when nothing is stored. A stored card
 * the person may not see stays stored and is only skipped here.
 *
 * @param mixed $stored  the layout's cards list
 * @param array $visible id => card, the cards the person may see
 * @return array list of id, w, per, cmp, badge
 */
function pg_dashboard_v2_grid_layout($stored, $visible)
{
    $out = array();
    $seen = array();

    $entries = (is_array($stored) && !empty($stored)) ? $stored : array();

    if (empty($entries)) {
        foreach (array_keys($visible) as $id) {
            $entries[] = array('id' => $id);
        }
    }

    foreach ($entries as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id']) || !isset($visible[$entry['id']]) || isset($seen[$entry['id']])) {
            continue;
        }

        $id = $entry['id'];
        $card = $visible[$id];
        $seen[$id] = true;

        $per = (isset($entry['per']) && is_int($entry['per']) && ($entry['per'] >= 0) && ($entry['per'] <= 2)) ? $entry['per'] : (int) $card['period'];

        $out[] = array(
            'id'    => $id,
            'w'     => (isset($entry['w']) && in_array($entry['w'], array('S', 'M', 'L'), true)) ? $entry['w'] : $card['width'],
            'per'   => ($card['period'] === null) ? null : $per,
            'cmp'   => (isset($entry['cmp']) && is_int($entry['cmp']) && ($entry['cmp'] >= 0) && ($entry['cmp'] <= 2)) ? $entry['cmp'] : 0,
            'badge' => isset($entry['badge']) ? (int) (bool) $entry['badge'] : 1,
        );
    }

    return $out;
}

/**
 * The script's configuration: the cards, the drawn layout, and what the edit
 * mode needs (the presets, the shortcuts and figures the person may pick,
 * the stored greeting settings and the texts). With nothing stored the
 * layout is the first default preset's.
 *
 * @param array      $user
 * @param array|null $row  the person's dashboard_layout row
 * @param array|null $look pg_dashboard_v2_look()'s answer; the defaults when null
 * @return array
 */
function pg_dashboard_v2_config($user, $row = null, $look = null)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/presets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/gallery.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_status.php');

    if (!is_array($look)) {
        $look = pg_dashboard_v2_look('', null);
    }

    $gallery = pg_dashboard_v2_gallery_cards();

    $visible = pg_dashboard_v2_cards_for($user);
    $shop = defined('ECOMMERCE') && (ECOMMERCE === true);
    $cards = array();

    foreach ($visible as $id => $card) {
        $cards[$id] = array(
            'title'    => $card['title'],
            'icon'     => $card['icon'],
            'category' => $card['category'],
            'period'   => $card['period'],
            'width'    => $card['width'],
            'desc'     => isset($gallery[$id]) ? $gallery[$id]['desc'] : '',
            'new'      => isset($gallery[$id]) ? $gallery[$id]['new'] : 0,
            'sketch'   => isset($gallery[$id]) ? $gallery[$id]['sketch'] : 'boxes',
        );
    }

    $json = (is_array($row) && isset($row['layout']) && is_string($row['layout'])) ? $row['layout'] : '';
    $layout = json_decode($json, true);
    $settings = pg_dashboard_v2_greeting_settings($json, $shop);

    $stored = (is_array($layout) && isset($layout['cards']) && is_array($layout['cards']))
        ? $layout['cards']
        : pg_dashboard_v2_preset_cards($settings['preset']);

    $presets = array();
    foreach (pg_dashboard_v2_presets() as $key => $preset) {
        $greeting = pg_dashboard_v2_greeting_preset($key, $shop);
        $presets[$key] = array(
            'title'       => $preset['title'],
            'cards'       => pg_dashboard_v2_grid_layout(pg_dashboard_v2_preset_cards($key), $visible),
            'actions'     => $greeting['actions'],
            'tiles'       => $greeting['tiles'],
            'recommended' => pg_dashboard_v2_recommended($preset['recommended'], $visible),
        );
    }

    $shortcuts = array();
    $pool = pg_dashboard_v2_shortcuts();
    foreach (pg_dashboard_v2_shortcuts_allowed($user) as $key) {
        $shortcuts[$key] = array('label' => $pool[$key]['label'], 'icon' => $pool[$key]['icon']);
    }

    $figures = array();
    $all = pg_dashboard_v2_figures();
    foreach (pg_dashboard_v2_figures_allowed($user) as $key) {
        $figures[$key] = $all[$key]['label'];
    }

    return array(
        'cards'      => $cards,
        'layout'     => pg_dashboard_v2_grid_layout($stored, $visible),
        'timing'     => pg_dashboard_v2_card_timing(),
        'categories' => pg_dashboard_v2_categories(),
        'periods'    => pg_dashboard_v2_periods(),
        'refresh'    => pg_dashboard_refresh_seconds($_SESSION['software']['welcome']['widget']['refresh'] ?? '', 60),
        'edit'       => array(
            'locked'    => pg_dashboard_v2_locked_for($user),
            'share'     => pg_dashboard_v2_share_config($user),
            'preset'    => $settings['preset'],
            'top'       => $settings['top'],
            'actions'   => pg_dashboard_v2_shortcuts_pick($settings['actions'], array_keys($shortcuts)),
            'tiles'     => pg_dashboard_v2_tiles_resolve($settings['tiles'], array_keys($figures)),
            'presets'   => $presets,
            'shortcuts' => $shortcuts,
            'offer'     => pg_dashboard_v2_shortcut_offer(),
            'figures'   => $figures,
            'look'      => array(
                'style'    => $look['style'],
                'backdrop' => $look['backdrop'],
                'dense'    => $look['dense'] ? 1 : 0,
                'calm'     => $look['calm'] ? 1 : 0,
            ),
            'choices'   => pg_dashboard_v2_look_choices(),
        ),
        'strings'    => array(
            'empty'   => lang('No cards on your dashboard yet.'),
            'error'   => lang('This card could not be loaded.'),
            'retry'   => lang('Try again'),
            'loading' => lang('Loading…'),
            'period'  => lang('Period: {var:1}. Choose to change.'),
            'all'     => lang('See all'),
        ) + pg_dashboard_v2_edit_strings() + pg_dashboard_v2_gallery_strings(),
    );
}

/**
 * The edit mode's texts.
 *
 * @return array
 */
function pg_dashboard_v2_edit_strings()
{
    return array(
        'editing'     => lang('You are editing the dashboard'),
        'hint'        => lang('Drag a card, or move it with the arrow keys on its grip. Changes are only on your dashboard.'),
        'preset'      => lang('Preset'),
        'default'     => lang('Back to default'),
        'cancel'      => lang('Cancel'),
        'save'        => lang('Save'),
        'saved'       => lang('Saved'),
        'failed'      => lang('The change could not be saved.'),
        'leave'       => lang('Your dashboard changes are not saved yet.'),
        'move'        => lang('Move {var:1}'),
        'moved'       => lang('Moved to place {var:1} of {var:2}'),
        'edit_card'   => lang('Edit {var:1}'),
        'width'       => lang('Width'),
        'narrow'      => lang('Narrow'),
        'medium'      => lang('Medium'),
        'full'        => lang('Full'),
        'settings'    => lang('Settings'),
        'forward'     => lang('Bring forward'),
        'back'        => lang('Move back'),
        'removed'     => lang('{var:1} removed'),
        'per_title'   => lang('Period'),
        'cmp_title'   => lang('Compare'),
        'cmp'         => array(lang('Previous period'), lang('Last year'), lang('Off')),
        'badge'       => lang('Show the "New" badge'),
        'done'        => lang('Done'),
        'add_action'  => lang('Add shortcut'),
        'remove_item' => lang('Remove {var:1}'),
        'tile'        => lang('Tile {var:1}'),
        'hide'        => lang('Hide'),
        'show'        => lang('Show'),
        'edit'        => lang('Edit dashboard'),
    );
}

/**
 * The grid, empty until the script draws the cards, and its configuration.
 *
 * @param array      $user
 * @param array|null $row
 * @param array|null $look
 * @return string HTML
 */
function pg_dashboard_v2_grid($user, $row = null, $look = null)
{
    return '
        <div class="pg-dash-grid" id="pg_dash_grid" aria-live="polite"></div>
        <script type="application/json" id="pg_dash_config">' . pg_dashboard_v2_json(pg_dashboard_v2_config($user, $row, $look)) . '</script>';
}

/**
 * JSON safe inside a <script> element: no "</" can close it early.
 *
 * @param mixed $value
 * @return string
 */
function pg_dashboard_v2_json($value)
{
    return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
