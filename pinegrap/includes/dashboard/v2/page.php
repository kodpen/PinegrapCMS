<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New dashboard - the page.
 *
 * welcome.php hands over here, before it prints anything, when the person
 * has switched the new dashboard on; the current dashboard is not drawn at
 * all then. The page wears the same shell, title and heading as welcome.php
 * and draws, in order: the switch row, the greeting card, the card grid and
 * one JSON config the scripts read (the card list, its categories and the
 * strings they show). Each part is a page_<part>.php file of its own, so the
 * steps that build them stay out of each other's files.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_grid.php');

/**
 * The page's stylesheet: the minified copy when the site runs minified and
 * the copy is there, else the source, versioned by its modification time (as
 * pg_image_editor_style_url()).
 *
 * @return string URL
 */
function pg_dashboard_v2_style_url()
{
    $minified = 'assets/css/dashboard_v2.min.css';
    $bundles = pg_css_bundles();
    $file = $minified;

    if ((defined('ENVIRONMENT_SUFFIX') && (ENVIRONMENT_SUFFIX !== 'min')) || !is_file(PG_FUNCTIONS_DIR . '/' . $minified)) {
        $file = $bundles[$minified];
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $file);
}

/**
 * What the scripts read from #pg_dash_config. Card entries the person may
 * not see are filtered by the card frame step; until a card is built it is
 * listed with built false and nothing draws it.
 *
 * @return array
 */
function pg_dashboard_v2_config()
{
    $cards = array();

    foreach (pg_dashboard_v2_cards() as $id => $card) {
        $cards[] = array(
            'id'       => $id,
            'title'    => $card['title'],
            'icon'     => $card['icon'],
            'category' => $card['category'],
            'width'    => $card['width'],
            'period'   => $card['period'],
            'built'    => $card['built'],
        );
    }

    return array(
        'v'          => 1,
        'cards'      => $cards,
        'categories' => pg_dashboard_v2_card_categories(),
    );
}

/**
 * The whole page, ready to print.
 *
 * @param array $user the signed-in person (validate_user())
 * @param array $row their dashboard_layout row (pg_dashboard_v2_person_row())
 * @param array $appearance pg_dashboard_appearance(): widget_theme, backdrop
 * @return string
 */
function pg_dashboard_v2_page($user, $row, $appearance)
{
    return
        pg_page_shell(
            array(
                'title' => lang('Welcome'),
                'extra classes' => 'setting welcome pg-dash-v2-page',
                'icon' => 'welcome',
                'heading' => lang('Welcome'),
                'heading_description' => lang('Your site at a glance: visitors, orders and health'),
                'head' => '<link rel="stylesheet" href="' . h(pg_dashboard_v2_style_url()) . '">',
            )
        ) .
        '<main id="content" class="container-fluid pg-dash-v2" data-widget-theme="' . h($appearance['widget_theme']) . '" data-panel-backdrop="' . h($appearance['backdrop']) . '">' .
            pg_dashboard_v2_switch_row(true) . '
            <div class="pg-dash-top">
                ' . pg_dashboard_v2_greeting_card($user, $row) . '
            </div>
            ' . pg_dashboard_v2_grid() . '
            <script type="application/json" id="pg_dash_config">' . pg_dashboard_v2_json(pg_dashboard_v2_config()) . '</script>' .
            pg_js_bundle_tags('assets/js/dashboard_switch.min.js') .
            pg_js_bundle_tags('assets/js/dashboard_v2.min.js') . '
        </main>' .
        output_footer();
}

/**
 * JSON safe to print inside a <script> element: "<", ">" and "&" are written
 * as \u escapes, so no "</script>" in a title can close it.
 *
 * @param mixed $value
 * @return string
 */
function pg_dashboard_v2_json($value)
{
    return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
