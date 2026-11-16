<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shared list and settings parts of look A
 * (includes/fn/look_list.php): the segmented filters of a list and the
 * icon tile of a settings row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/look_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_look_list_seg_filters()
{
	pg_assert_same('', pg_list_seg_filters('orders', 'status', array()), 'no items, no control');

	$html = pg_list_seg_filters('view_log', 'type', array('' => 'All', 'error' => array('label' => 'Error <Log>', 'count' => 7)), 'Type');

	pg_assert_true(strpos($html, '<div class="pg-seg pg-list-seg" role="group" aria-label="Type">') === 0, 'a named group with the recipe classes');
	pg_assert_true(strpos($html, 'class="pg-seg-item active" aria-pressed="true" data-pg-list-filter="view_log" data-pg-filter-column="type" data-pg-filter-value=""') !== false, 'the "every value" segment is chosen by default');
	pg_assert_true(strpos($html, 'class="pg-seg-item" aria-pressed="false"') !== false, 'the others are not pressed');
	pg_assert_true(strpos($html, 'Error &lt;Log&gt;<span class="pg-seg-count pg-tabular">7</span>') !== false, 'label escaped, count beside it');

	$chosen = pg_list_seg_filters('view_log', 'type', array('' => 'All', 'error' => 'Error'), '', 'error');
	pg_assert_true(strpos($chosen, 'aria-pressed="true" data-pg-list-filter="view_log" data-pg-filter-column="type" data-pg-filter-value="error"') !== false, 'the current value is chosen');
}

function test_look_list_settings_tile()
{
	pg_assert_same('<span class="pg-icon-tile pg-icon-tile-lg pg-set-tile pg-tone-shop" aria-hidden="true"><i class="bi bi-truck"></i></span>', pg_settings_tile('truck', 'shop'), 'a toned tile');
	pg_assert_same(pg_settings_tile('truck'), pg_settings_tile('bi-truck', 'nope'), 'the bi- prefix is optional; an unknown tone is gray');
	pg_assert_same('', pg_settings_tile('x" onclick="y'), 'a name that is no icon name prints nothing');
}

function test_look_list_offers_shows_its_count()
{
	pg_assert_true(is_array(pg_list_table_no_head_count()), 'the left-out lists are a list');
	pg_assert_true(!in_array('view_offers', pg_list_table_no_head_count(), true), 'All Offers shows its count beside the title');
}
