<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the OpenAPI document's SEO issue item (seo.issues[] of a page, a
 * product and a product group) declares every field api_seo_issues() puts in
 * a row, and nothing it never sends. tools/check_api_schema.php compares only
 * the top-level keys of the Seo object, so the list inside it is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/seo.php');

// The keys api_seo_issues() writes into a row, read from its source: every
// 'key' => inside the function, and every $entry['key'] = added afterwards.
function api_seo_issue_fields_test_emitted()
{
	$function = new ReflectionFunction('api_seo_issues');

	$lines = file($function->getFileName());

	$body = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	preg_match_all("/'([a-z_]+)'\\s*=>/", $body, $arrows);
	preg_match_all("/\\\$entry\\['([a-z_]+)'\\]\\s*=/", $body, $added);

	$keys = array_unique(array_merge($arrows[1], $added[1]));

	sort($keys);

	return $keys;
}

function api_seo_issue_fields_test_declared()
{
	$schema = api_seo_schema();

	$keys = array_keys($schema['issues'][0]);

	sort($keys);

	return $keys;
}

function test_api_seo_issue_fields_every_emitted_field_is_declared()
{
	$emitted = api_seo_issue_fields_test_emitted();

	pg_assert_true(in_array('earned', $emitted, true), 'the source reader finds the meta row fields');
	pg_assert_true(in_array('layer', $emitted, true), 'the source reader finds the field added afterwards');

	pg_assert_same(array(), array_values(array_diff($emitted, api_seo_issue_fields_test_declared())));
}

function test_api_seo_issue_fields_nothing_declared_is_never_sent()
{
	pg_assert_same(array(), array_values(array_diff(api_seo_issue_fields_test_declared(), api_seo_issue_fields_test_emitted())));
}

function test_api_seo_issue_fields_types()
{
	$schema = api_seo_schema();
	$item = $schema['issues'][0];

	pg_assert_same('integer', $item['weight']);
	pg_assert_same('integer', $item['earned']);
	pg_assert_same('integer', $item['occurrences']);
	pg_assert_same('string', $item['label']);
	pg_assert_same('string', $item['source']);
}
