<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the panel search box's relevance score (pg_panel_search_score()):
 * İ, I, ı and i count as one letter on both sides, so the exact "İade"
 * page outranks a page that only contains the word, and a "#" before an
 * order number does not stop the order from scoring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The file only defines the panel actions; its gate is the dispatcher's.
if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}
require_once(PG_FUNCTIONS_DIR . '/includes/panel/search.php');

function test_panel_search_score_folds_turkish_i()
{
	pg_assert_same(100, pg_panel_search_score('İade', 'iade'), 'capital İ in the name');
	pg_assert_same(100, pg_panel_search_score('iade', 'İADE'), 'capital İ in the query');
	pg_assert_same(60, pg_panel_search_score('İade Politikası', 'iade'), 'starts with');
	pg_assert_same(100, pg_panel_search_score('Işık', 'ışık'), 'dotless ı against capital I');
	pg_assert_same(30, pg_panel_search_score('Ödeme ve iade', 'İade'), 'contains');
	pg_assert_true(pg_panel_search_score('İade', 'iade') > pg_panel_search_score('Ödeme ve iade', 'iade'), 'exact page first');
}

function test_panel_search_score_secondary_and_misses()
{
	pg_assert_same(15, pg_panel_search_score('Ayşe Kaya', 'istanbul', 'İstanbul şubesi'), 'secondary only');
	pg_assert_same(0, pg_panel_search_score('Hakkımızda', 'iade'), 'no match');
	pg_assert_same(0, pg_panel_search_score(null, 'iade', null), 'null name');
}

function test_panel_search_orders_score_without_the_hash()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains("escape(pg_list_like_pattern(\$order_query))", $php, 'order number pattern escaped');
	pg_assert_contains("pg_panel_search_score(\$r['order_number'], \$order_query, \$r['customer'])", $php, 'order scored without the #');
}
