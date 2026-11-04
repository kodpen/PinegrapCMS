<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Iyzipay protected currency box is drawn only while Multiple Currencies
 * is on. A Store save without it must keep the stored currency, and its
 * label is drawn only beside the box.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_iyzipay_currency_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_iyzipay_currency_written_only_when_posted()
{
	$source = settings_iyzipay_currency_test_source('includes/settings/commerce.save.php');

	pg_assert_contains("array_key_exists('iyzipay_protected_currency_code', \$_POST)", $source, 'the column is written only when its box was posted');
	pg_assert_false((bool) preg_match('/^\s*iyzipay_protected_currency_code = /m', $source), 'no unconditional write in the UPDATE');
	pg_assert_contains('" . $sql_iyzipay_protected_currency . "', $source, 'the fragment is spliced in the same file');
}

function test_settings_iyzipay_currency_label_drawn_with_its_box()
{
	$source = settings_iyzipay_currency_test_source('includes/settings/commerce.php');

	pg_assert_contains("(\$output_iyzipay_protected_currency_select !== '')", $source, 'the label shares the box\'s condition');
}
