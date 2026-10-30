<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address the product screen previews in its search result
 * card (previewAddress() in assets/js/product_builder.js): it reads the one
 * accent table the server uses (pg_transliterate_ascii_table()), and its
 * steps are the ones prepare_catalog_item_address_name() runs on save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_product_address_preview_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The table the page receives is the table the server transliterates with:
// every accented letter comes out of pg_transliterate_to_ascii() as its value.
function test_product_address_preview_table_round_trips()
{
	$table = pg_transliterate_ascii_table();

	pg_assert_true(count($table) > 90, 'table is not empty');
	pg_assert_same('i', $table['ı'], 'dotless i');
	pg_assert_same('I', $table['İ'], 'dotted capital I');
	pg_assert_same('ss', $table['ß'], 'sharp s');

	foreach ($table as $accented => $plain) {
		pg_assert_same($plain, pg_transliterate_to_ascii($accented), 'transliterates ' . $accented);
		pg_assert_true((bool) preg_match('/^[A-Za-z]+$/', $plain), 'plain value of ' . $accented);
	}

	pg_assert_same('Canta & Sapka %50 Indirim', pg_transliterate_to_ascii('Çanta & Şapka %50 İndirim'), 'sentence');
}

// The preview replaces the characters the save replaces, and gets its accent
// table from the page instead of carrying a copy of its own.
function test_product_address_preview_follows_the_save()
{
	$php = pg_test_product_address_preview_source('includes/fn/content.php');
	$js  = pg_test_product_address_preview_source('assets/js/product_builder.js');

	pg_assert_contains("preg_replace('/[^A-Za-z0-9\\[\\]_-]/', '_', \$address_name)", $php, 'server character class');
	pg_assert_contains(".replace(/[^A-Za-z0-9\\[\\]_-]/g, '_')", $js, 'preview character class');
	pg_assert_contains(".replace(/_{2,}/g, '_')", $js, 'preview collapses separators');
	pg_assert_contains(".replace(/^_+|_+\$/g, '')", $js, 'preview trims separators');
	pg_assert_contains("|| 'item'", $js, 'preview falls back to item');
	pg_assert_contains('cfg.asciiTable', $js, 'preview reads the shared table');
	pg_assert_contains('cfg.storedAddress', $js, 'preview reads the stored address');
	pg_assert_false((bool) preg_match('/[\'"](ş|ğ|ı)[\'"]/u', $js), 'no accent table in the script');

	$screen = pg_test_product_address_preview_source('product_builder.php');

	pg_assert_contains("asciiTable: ' . str_replace('</', '<\\/', encode_json(pg_transliterate_ascii_table()))", $screen, 'screen passes the table');
	pg_assert_contains('storedAddress:', $screen, 'screen passes the stored address');
}
