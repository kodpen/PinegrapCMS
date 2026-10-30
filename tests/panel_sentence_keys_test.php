<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel messages are whole literal lang() keys: a key built at run time
 * from an English verb ('was'/'were') is invisible to check_lang.php and
 * leaves English in a Turkish sentence, and a plural-only key reads
 * "1 minutes ago" in English. These tests pin the screens that used to do
 * either, and refuse a Turkish value that keeps an English plural suffix.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * The source of a product file.
 *
 * @param string $path relative to pinegrap/
 * @return string
 */
function panel_sentence_keys_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_sentence_keys_no_verb_built_keys()
{
	$edit_products = panel_sentence_keys_source('edit_products.php');

	pg_assert_true(!preg_match('/lang\(\s*\$verb/', $edit_products), 'edit_products.php: no lang($verb ...) key');
	pg_assert_true(strpos($edit_products, '. $verb .') === false, 'edit_products.php: no key glued to $verb');
	pg_assert_true(strpos($edit_products, "\$log_message .= ','") === false, 'edit_products.php: parts are not joined with a bare comma');
	pg_assert_true(strpos($edit_products, 'Inventroy') === false, 'edit_products.php: Track Inventory is spelt right');

	pg_assert_true(strpos(panel_sentence_keys_source('find_and_replace.php'), '$match_verb') === false, 'find_and_replace.php: no English verb passed as a variable');
	pg_assert_true(strpos(panel_sentence_keys_source('print_packing_slip.php'), "' of '") === false, 'print_packing_slip.php: the form counter goes through lang()');
	pg_assert_true(strpos(panel_sentence_keys_source('duplicate_products.php'), "' exported'") === false, 'duplicate_products.php: the export log line goes through lang()');
}

function test_panel_sentence_keys_pages_and_files_notices_are_sentences()
{
	foreach (array('edit_pages.php', 'edit_files.php') as $path) {
		$source = panel_sentence_keys_source($path);

		pg_assert_true(strpos($source, "'were moved to {var:1}'") === false, $path . ': no "were moved to {var:1}" piece');
		pg_assert_true(!preg_match("/lang\\(\\s*'and'\\s*\\)/", $source), $path . ': changes are not joined with lang(\'and\')');
		pg_assert_true(strpos($source, "'{var:1} page(s)") === false, $path . ': no "{var:1} page(s)" count key');
		pg_assert_true(strpos($source, "'{var:1} file(s)") === false, $path . ': no "{var:1} file(s)" count key');
		pg_assert_true(strpos($source, "lang('file(s)')") === false, $path . ': no count glued outside lang()');
	}

	$edit_pages = panel_sentence_keys_source('edit_pages.php');

	foreach (array("lang('preview ')", 'page style changed to {var:2}', 'had site search turned', "'were {var:1} sitemap.xml'", "lang('removed from')") as $piece) {
		pg_assert_true(strpos($edit_pages, $piece) === false, 'edit_pages.php: no "' . $piece . '" piece');
	}

	pg_assert_true(strpos(panel_sentence_keys_source('edit_files.php'), 'had design turned') === false, 'edit_files.php: no "had design turned" piece');
	pg_assert_true(strpos(panel_sentence_keys_source('view_fields.php'), "'Add fields to this {var:1}'") === false, 'view_fields.php: the subheading names its form in one key');
}

function test_panel_sentence_keys_relative_times_have_singular()
{
	foreach (array('api_settings.php', 'chat.php', 'marketplace_settings.php') as $path) {
		$source = panel_sentence_keys_source($path);

		foreach (array('minutes', 'hours', 'days') as $unit) {
			pg_assert_true(strpos($source, "'{var:1} " . $unit . " ago'") === false, $path . ': no plural-only "' . $unit . ' ago" key');
		}

		foreach (array('minute', 'hour', 'day') as $unit) {
			pg_assert_true(strpos($source, "'{var:1} " . $unit . "{suffix:1} ago'") !== false, $path . ': "' . $unit . '{suffix:1} ago" key used');
		}
	}
}

function test_panel_sentence_keys_new_keys_are_translated()
{
	$map = pg_test_language_map('tr');

	$keys = array(
		'{var:1} match{suffix:1} found and replaced in {var:2} item{suffix:2}.',
		'{var:1} product was updated ({var:2}).',
		'{var:1} products were updated ({var:2}).',
		'{var:1} product was deleted.',
		'{var:1} products were deleted.',
		'{var:1} minute{suffix:1} ago',
		'{var:1} hour{suffix:1} ago',
		'{var:1} day{suffix:1} ago',
		'stock quantity changed',
		'shipping zones changed',
		'price changed',
		'tax rate changed',
		'tax rate cleared',
		'stock tracking on',
		'stock tracking off',
		'Option for Track Inventory',
		'{var:1} page was changed: {var:2}.',
		'{var:1} pages were changed: {var:2}.',
		'moved to the folder {var:1}',
		'page style set to {var:1}',
		'preview page style set to {var:1}',
		'mobile page style set to {var:1}',
		'mobile preview page style set to {var:1}',
		'site search turned on',
		'site search turned off',
		'added to sitemap.xml',
		'removed from sitemap.xml',
		'{var:1} page was moved to the Recycle Bin.',
		'{var:1} pages were moved to the Recycle Bin.',
		'{var:1} page was deleted.',
		'{var:1} pages were deleted.',
		'{var:1} file was changed: {var:2}.',
		'{var:1} files were changed: {var:2}.',
		'design turned on',
		'design turned off',
		'{var:1} file was deleted.',
		'{var:1} files were deleted.',
		'Add fields to this custom form.',
		'Add fields to this custom shipping form.',
		'Add fields to this custom billing form.',
	);

	foreach ($keys as $key) {
		pg_assert_true(isset($map[$key]), 'no Turkish value for "' . $key . '"');
	}
}

function test_panel_sentence_keys_no_turkish_value_keeps_a_suffix()
{
	foreach (pg_test_language_map('tr') as $key => $value) {
		pg_assert_true(strpos((string) $value, '{suffix') === false, 'the Turkish value of "' . $key . '" keeps an English plural suffix');
	}
}
