<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for assembling a marketplace listing
 * (includes/api/outbound/listings.php): a category whose attributes could not
 * be read refuses the article instead of sending it without attributes, and
 * the products of an article that are in the catalogue's Recycle Bin are left
 * out of the listing, the queue and the variant count.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/listings.php');

// A failed read of the category's attributes (a timeout, a 5xx, the
// per-minute limit) is an error, not a category without attributes: read as
// one, the mandatory check has nothing to check and the variant attribute is
// never sent.
function test_marketplace_listings_unread_attributes_refuse_the_article()
{
	$definitions = mp_listing_definitions(mp_error('Trendyol did not answer in time.', true));

	pg_assert_false($definitions['ok']);
	pg_assert_same(array(), $definitions['definitions']);
	pg_assert_contains('Trendyol did not answer in time.', $definitions['error']);
	pg_assert_true($definitions['retry']);

	$refused = mp_listing_definitions(mp_error('Category not found.'));

	pg_assert_false($refused['ok']);
	pg_assert_false($refused['retry']);
}

function test_marketplace_listings_read_attributes_are_the_definitions()
{
	$rows = array(array('attribute_id' => '47', 'name' => 'Renk', 'mandatory' => '1', 'is_variant' => '1', 'values' => array()));

	$definitions = mp_listing_definitions(mp_ok(array('attributes' => $rows)));

	pg_assert_true($definitions['ok']);
	pg_assert_same($rows, $definitions['definitions']);
	pg_assert_same('', $definitions['error']);

	// A category that really has no attributes is still fine.
	$none = mp_listing_definitions(mp_ok(array('attributes' => array())));

	pg_assert_true($none['ok']);
	pg_assert_same(array(), $none['definitions']);
}

// The body of one function in listings.php, from its declaration to the next.
function marketplace_listings_test_function_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/listings.php');

	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A product in the Recycle Bin keeps its products_groups_xref rows so that
// restoring it puts it back, but the shop no longer sells it: every query that
// reads an article's products through that table leaves it out.
function test_marketplace_listings_article_queries_leave_the_recycle_bin_out()
{
	foreach (array('mp_listing_articles', 'mp_listing_items', 'mp_listing_enqueue') as $name) {

		$body = marketplace_listings_test_function_body($name);

		pg_assert_true($body !== '', $name . ' exists');
		pg_assert_contains("pg_catalog_bin_live_sql('products')", $body);
	}
}
