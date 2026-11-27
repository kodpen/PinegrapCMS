<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Commerce › Markets: sets of countries sold to in their own currency, each
 * with its countries, currency, rounding rule and state, in the order they
 * are listed. Printed whole and sorted and searched in the browser by the
 * list table driver; a market is added and changed in this screen's drawer,
 * which posts back here. Store managers (validate_ecommerce_access()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!pg_markets_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('view_markets');
$markets_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_markets.php';
$markets_currencies = pg_market_currency_options();
$markets_countries = pg_market_country_options();
$markets_empty = array('name' => '', 'currency_code' => '', 'rounding' => 'none', 'enabled' => 1, 'countries' => array());

// A refused save is drawn again in its drawer, with what was typed.
$markets_refused = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $market_id = (int) ($_POST['id'] ?? 0);

    if (isset($_POST['submit_delete'])) {
        $market_id = (int) $_POST['submit_delete'];
        $markets_all = pg_market_list();
        $deleted = pg_market_delete($market_id);

        if ($deleted['ok']) {
            $liveform->add_notice(h(lang(array('string' => 'The market "{var:1}" was deleted.', 'vars' => array($markets_all[$market_id]['name'] ?? '')))));
        } else {
            $liveform->add_error(h($deleted['error']));
        }

        go($markets_self);
    }

    if (isset($_POST['submit_move'])) {
        $move = explode(':', (string) $_POST['submit_move']);
        pg_market_move((int) $move[0], ($move[1] ?? '') === 'up' ? 'up' : 'down');
        go($markets_self);
    }

    if (isset($_POST['submit_save'])) {
        $form = pg_market_form_values($_POST, array_keys($markets_currencies), array_keys($markets_countries));
        $errors = $form['errors'];

        if (!$errors) {
            $saved = pg_market_save($form['values'], $market_id);

            if ($saved['ok']) {
                $liveform->add_notice(h(lang(array('string' => 'The market "{var:1}" was saved.', 'vars' => array($form['values']['name'])))));
                go($markets_self);
            }

            $errors['form'] = $saved['error'];
        }

        $markets_refused = array('market_id' => $market_id, 'values' => $form['values'], 'errors' => $errors);
    }
}

$markets = pg_market_list();
$markets_rounding = pg_market_rounding_options();

$markets_sort_columns = array(
    'place'     => lang('List order'),
    'name'      => lang('Market'),
    'countries' => lang('Countries'),
    'currency'  => lang('Currency'),
    'rounding'  => lang('Rounding'),
    'status'    => lang('Status'),
);
$markets_sort_default = array('sort' => 'place', 'order' => 'asc');
$markets_sort_levels = pg_list_state('view_markets', $markets_sort_columns, $markets_sort_default)['sorts'];
$markets_rows = array();
$markets_place = 0;
$markets_last = count($markets);

foreach ($markets as $market) {
    $id = (int) $market['id'];
    $markets_place++;
    $url = $markets_self . '?id=' . $id;
    $move = '';

    if ($markets_place > 1) {
        $move .= '<li><button type="submit" form="markets_list_form" name="submit_move" value="' . $id . ':up" class="menu-item"><i class="bi bi-arrow-up me-3" aria-hidden="true"></i>' . h(lang('Move up')) . '</button></li>';
    }

    if ($markets_place < $markets_last) {
        $move .= '<li><button type="submit" form="markets_list_form" name="submit_move" value="' . $id . ':down" class="menu-item"><i class="bi bi-arrow-down me-3" aria-hidden="true"></i>' . h(lang('Move down')) . '</button></li>';
    }

    $markets_rows[] = array(
        'id' => $id,
        'cells' => array(
            'place' => pg_list_cell(h(pg_format_number($markets_place, 0)), 'align-middle', array('data-pg-sort-value' => $markets_place)),
            'name' => pg_list_cell('<a href="' . h($url) . '" data-pg-open>' . h($market['name']) . '</a>'),
            'countries' => pg_list_cell(h(pg_market_country_summary($market['countries'], $markets_countries)), 'align-middle', array('data-pg-sort-value' => count($market['countries']))),
            'currency' => pg_list_cell(h($markets_currencies[$market['currency_code']] ?? $market['currency_code']), 'align-middle'),
            'rounding' => pg_list_cell(h($markets_rounding[pg_market_rounding_code($market['rounding'])]), 'align-middle'),
            'status' => pg_list_cell(pg_market_pill($market['enabled']), 'align-middle', array('data-pg-sort-value' => $market['enabled'])),
            // The menu opens on the body, outside the list's clipping; its
            // buttons post the list's form through their form attribute.
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url) . '">' . h(lang('Edit')) . '</a>'
                . ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                . '<ul class="menu pg-menu-end">' . $move . '<li><button type="submit" form="markets_list_form" name="submit_delete" value="' . $id . '" formnovalidate class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('The market and its list of countries are deleted. Its visitors see the base currency again.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li></ul></span>',
                'align-middle text-nowrap text-end'),
        ),
    );
}

$markets_list = pg_list_table_printed(array(
    'list' => 'view_markets',
    'columns' => array(
        'place' => array('heading' => lang('List order'), 'kind' => 'number'),
        'name' => array('heading' => lang('Market')),
        'countries' => array('heading' => lang('Countries')),
        'currency' => array('heading' => lang('Currency')),
        'rounding' => array('heading' => lang('Rounding')),
        'status' => array('heading' => lang('Status')),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $markets_sort_levels,
    'rows' => $markets_rows,
));

// The Add market drawer, and the drawer of the market opened from the list
// (?id=) or of a refused save, which opens with the screen.
$markets_drawers = pg_market_drawer_html(array(
    'id' => 'market_add',
    'market_id' => 0,
    'values' => (($markets_refused !== null) && ($markets_refused['market_id'] === 0)) ? $markets_refused['values'] : $markets_empty,
    'errors' => (($markets_refused !== null) && ($markets_refused['market_id'] === 0)) ? $markets_refused['errors'] : array(),
    'currencies' => $markets_currencies,
    'countries' => $markets_countries,
    'open' => (($markets_refused !== null) && ($markets_refused['market_id'] === 0)) || isset($_GET['add']),
));

$markets_open_id = ($markets_refused !== null) ? $markets_refused['market_id'] : ((isset($_GET['id']) && is_scalar($_GET['id'])) ? (int) $_GET['id'] : 0);

if (($markets_open_id > 0) && isset($markets[$markets_open_id])) {
    $markets_drawers .= pg_market_drawer_html(array(
        'id' => 'market_edit',
        'market_id' => $markets_open_id,
        'values' => ($markets_refused !== null) ? $markets_refused['values'] : $markets[$markets_open_id],
        'errors' => ($markets_refused !== null) ? $markets_refused['errors'] : array(),
        'currencies' => $markets_currencies,
        'countries' => $markets_countries,
        'open' => true,
    ));
}

echo
    pg_page_shell(array(
        'title' => lang('Markets'),
        'extra classes' => 'products pg-measure-list',
        'icon' => 'store',
        'heading' => lang('Markets'),
        'heading_description' => lang('Sell to a group of countries in their own currency. Without a market, every visitor sees the base currency.'),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => '<button type="button" class="pg-button pg-button-fill" data-pg-market-drawer="market_add"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add market')) . '</button>',
                'search'  => array(pg_list_table_search_box('view_markets'), pg_list_sort_menu('view_markets.php', $markets_sort_columns, $markets_sort_levels, $markets_sort_default)),
            )) . '
            <form name="form" id="markets_list_form" action="view_markets.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($markets_list['source'], $markets_list['page'], lang('Markets')) . '
            </form>
            ' . $markets_drawers . '
        </div>
    </div>
</main>
' . pg_fields_script() . '
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/markets.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/markets.js') . '"></script>
' . output_footer();

$liveform->remove_form();
