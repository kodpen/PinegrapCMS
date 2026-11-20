<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Products › Supplier feeds: every feed with its host (never the address),
 * format, interval, the state of its last pull and the products it matched.
 * A row opens the feed (edit_supplier_feed.php), starts its test pull there,
 * or deletes it from its "more" menu (posted to the same screen). Helpers: includes/fn/supplier_feed_screen.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_screen.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!pg_supplier_feed_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('view_supplier_feeds');

$feeds_sort_columns = array(
    'name'     => lang('Name'),
    'host'     => lang('Host'),
    'format'   => lang('Format'),
    'interval' => lang('Pulled every'),
    'state'    => lang('Last pull'),
    'matched'  => lang('Products matched'),
);
$feeds_sort_default = array('sort' => 'name', 'order' => 'asc');
$feeds_sort_levels = pg_list_state('view_supplier_feeds', $feeds_sort_columns, $feeds_sort_default)['sorts'];

$formats = array('' => lang('Automatic'), 'xml' => 'XML', 'csv' => 'CSV', 'json' => 'JSON');
$feeds = pg_supplier_feed_list();
$feeds_rows = array();

foreach ($feeds as $feed) {
    $id = (int) $feed['id'];
    $state = pg_supplier_feed_state($feed, $feed['run']);
    $mapping = pg_supplier_feed_mapping_decode($feed['mapping']);
    $last = ($feed['run'] !== null) ? (int) $feed['run']['started_at'] : 0;

    $feeds_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_supplier_feed.php?id=' . $id . '" data-pg-open>' . h($feed['name']) . '</a>'),
            'host' => pg_list_cell(h($feed['host'])),
            'format' => pg_list_cell(h($formats[$feed['format']] ?? $feed['format'])),
            'interval' => pg_list_cell(h(lang(array('string' => '{var:1} h', 'vars' => array((int) $feed['interval_hours'])))), 'align-middle', array('data-pg-sort-value' => (int) $feed['interval_hours'])),
            'state' => pg_list_cell('<span class="pg-pill pg-tone-' . $state['tone'] . '">' . h($state['label']) . '</span>'
                . (($state['note'] !== '') ? '<div class="pg-text-meta">' . h($state['note']) . '</div>' : '')
                . (($last > 0) ? '<div class="pg-text-meta">' . h(get_relative_time(array('timestamp' => $last, 'format' => 'plain_text'))) . '</div>' : ''),
                'align-middle', array('data-pg-sort-value' => $last)),
            'matched' => pg_list_cell(h(pg_format_number((int) $feed['matched'], 0)), 'align-middle', array('data-pg-sort-value' => (int) $feed['matched'])),
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="edit_supplier_feed.php?id=' . $id . '">' . h(lang('Edit')) . '</a>'
                . ($mapping['confirmed'] ? ' <button type="submit" name="test_pull" value="' . $id . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Test pull')) . '</button>' : '')
                . ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                . '<ul class="menu pg-menu-end"><li><button type="submit" name="submit_delete" value="' . $id . '" formnovalidate class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('The feed and its pull history are deleted. Its products stay and no longer follow it.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li></ul></span>',
                'align-middle text-nowrap'),
        ),
    );
}

$feeds_list = pg_list_table_printed(array(
    'list' => 'view_supplier_feeds',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'host' => array('heading' => lang('Host')),
        'format' => array('heading' => lang('Format')),
        'interval' => array('heading' => lang('Pulled every'), 'kind' => 'number'),
        'state' => array('heading' => lang('Last pull')),
        'matched' => array('heading' => lang('Products matched'), 'kind' => 'number'),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $feeds_sort_levels,
    'rows' => $feeds_rows,
));

// No feed yet: one line on what a feed does and the Add button, in place of
// an empty table and a toolbar.
if ($feeds === array()) {
    $output_list = '
            <div class="pg-card">
                <p class="pg-text-2">' . h(lang('A supplier feed reads a supplier\'s XML, CSV or JSON product list and keeps your products\' prices and stock in step with it.')) . '</p>
                <a class="pg-button pg-button-fill" href="edit_supplier_feed.php"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add supplier feed')) . '</a>
            </div>';
} else {
    $output_list = pg_screen_toolbar(array(
                'primary' => array('label' => lang('Add supplier feed'), 'url' => 'edit_supplier_feed.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_supplier_feeds'), pg_list_sort_menu('view_supplier_feeds.php', $feeds_sort_columns, $feeds_sort_levels, $feeds_sort_default)),
            )) . '
            <form name="form" action="edit_supplier_feed.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($feeds_list['source'], $feeds_list['page'], lang('Supplier feeds')) . '
            </form>';
}

echo
    pg_page_shell(array(
        'title' => lang('Supplier feeds'),
        'extra classes' => 'supplier-feeds pg-measure-list',
        'icon' => 'store',
        'heading' => lang('Supplier feeds'),
        'heading_description' => lang('Your suppliers\' product lists, read on a schedule to keep prices and stock in step.'),
        'breadcrumb' => array(
            array('label' => lang('Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'),
            array('label' => lang('Supplier feeds')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . $output_list . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . output_footer();

$liveform->remove_form();
