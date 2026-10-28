<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for tone rows and cells (.table-primary … .table-info) in
 * panel tables: the panel's card fill on every cell would otherwise cover
 * the tone's --bs-table-bg and leave only coloured text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function table_tone_rows_test_code()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$css = preg_replace('~/\*.*?\*/~s', '', $css);
	return preg_replace('/\s+/', ' ', $css);
}

function test_table_tone_rows_paint_their_band()
{
	$css = table_tone_rows_test_code();
	$tones = ':is(.table-primary, .table-secondary, .table-success, .table-danger, .table-warning, .table-info)';
	$rule = '.table > :not(caption) > ' . $tones . ' > *, .table > :not(caption) > * > ' . $tones . ' { background-color: var(--bs-table-bg); }';

	pg_assert_true(strpos($css, $rule) !== false, 'tone rows and cells paint their own fill');

	// Same layer, after the card fill on every cell and the tone variables.
	$card = strpos($css, '.table > :not(caption) > * > * { background-color: var(--bs-card-bg); }');
	pg_assert_true($card !== false, 'card fill on cells kept');
	pg_assert_true(strpos($css, $rule) > $card, 'after the card fill');
	pg_assert_true(strpos($css, $rule) > strpos($css, '.table-info { --bs-table-color'), 'after the tone variables');
}
