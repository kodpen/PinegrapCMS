<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the follow-ups of switching a product group on or off
 * (update_product_group_status()): the groups and products whose enabled
 * really changes get the SEO stale mark, a product also resyncs its tag
 * cloud keywords and tells the marketplace queue; and the legacy product
 * group tag cloud rebuild (includes/fn/seo.php) leaves out a disabled
 * product's keywords while keeping its xref row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_group_status_followups_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_product_group_status.php');
}

// The body of update_tag_cloud_keywords_for_search_results_page_product_group().
function product_group_status_followups_test_rebuild()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');
	$start = strpos($source, 'function update_tag_cloud_keywords_for_search_results_page_product_group(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 10);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_product_group_status_group_update_carries_stale_mark_when_changed()
{
	$source = product_group_status_followups_test_source();

	// The groups' current enabled is read so only a real change is marked.
	pg_assert_true((bool) preg_match('/SELECT\s+id,\s*enabled\s+FROM product_groups/', $source), 'group enabled read before the update');
	pg_assert_true((bool) preg_match('/UPDATE product_groups\s+SET\s+enabled = \'\$enabled\',\s*\$sql_seo_stale/', $source), 'group UPDATE carries the stale mark');
}

function test_product_group_status_reads_products_without_audit_snapshot()
{
	$source = product_group_status_followups_test_source();

	// pg_audit_fetch_many() returns nothing while auditing is off, so the
	// products' enabled and keywords come from a read of their own.
	pg_assert_true((bool) preg_match('/SELECT\s+id,\s*enabled,\s*keywords\s+FROM products\s+WHERE id IN \(/', $source), 'products read with id, enabled, keywords');
}

function test_product_group_status_product_followups_only_for_changed()
{
	$source = product_group_status_followups_test_source();

	$changed = strpos($source, "\$changed = (\$current !== null) && ((int) \$current['enabled'] !== \$enabled);");
	$update = strpos($source, 'UPDATE products');
	$guard = strpos($source, 'if ($changed) {');
	$sync = strpos($source, 'pg_pb_sync_tag_cloud_keywords(');
	$queue = strpos($source, 'pg_marketplace_product_changed(');

	pg_assert_true($changed !== false, 'a real change is worked out per product');
	pg_assert_contains("\$sql_seo_stale = \$changed ? \"seo_analysis_current = '0',\" : \"\";", $source);
	pg_assert_true((bool) preg_match('/UPDATE products\s+SET\s+enabled = \'\$enabled\',\s*\$sql_seo_stale/', $source), 'product UPDATE carries the stale mark');
	pg_assert_true($update !== false && $guard !== false && $guard > $update, 'follow-ups guarded after the UPDATE');
	pg_assert_true($sync !== false && $sync > $guard, 'tag cloud synced inside the guard');
	pg_assert_true($queue !== false && $queue > $guard, 'marketplace queued inside the guard');
	pg_assert_contains("function_exists('pg_marketplace_product_changed')", $source);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/product_builder.php')", $source);
}

function test_product_group_status_rebuild_skips_disabled_product_keywords()
{
	$body = product_group_status_followups_test_rebuild();

	pg_assert_true($body !== '', 'rebuild function found');
	pg_assert_true((bool) preg_match('/products\.keywords,\s*products\.enabled/', $body), 'rebuild reads products.enabled');
	pg_assert_contains("'enabled' => \$row['enabled']", $body);

	$skip = strpos($body, "(\$item['type'] == 'product') && ((string) \$item['enabled'] !== '1')");
	$keyword_insert = strpos($body, 'INSERT INTO tag_cloud_keywords ');
	$xref_insert = strpos($body, 'INSERT INTO tag_cloud_keywords_xref');

	pg_assert_true($skip !== false, 'disabled product keywords skipped');
	pg_assert_true($skip !== false && $keyword_insert !== false && $skip < $keyword_insert, 'skip comes before the keyword insert');
	pg_assert_true($xref_insert !== false, 'xref insert kept');
}
