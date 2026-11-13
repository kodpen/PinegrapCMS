<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's page widths in backend.src.css: the measures
 * are tokens with the decided values, an add or edit screen and a list take
 * them through their body class, and the first two screens carry it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_page_widths_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function look_page_widths_test_block()
{
	$css = look_page_widths_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: page widths.');

	return $start === false ? '' : substr($css, $start);
}

function test_page_width_tokens_hold_the_decided_measures()
{
	$block = look_page_widths_test_block();

	foreach (array('--pg-measure-edit: 1120px;', '--pg-measure-form: 760px;', '--pg-measure-status: 336px;', '--pg-measure-gap: 24px;', '--pg-measure-list-min: 1280px;') as $token) {
		pg_assert_contains($token, $block, $token);
	}
}

function test_page_width_rules_read_the_tokens()
{
	$block = look_page_widths_test_block();

	pg_assert_contains('body.pg-measure-edit #content {', $block, 'an edit screen holds its content box');
	pg_assert_contains('grid-template-columns: minmax(0, var(--pg-measure-form)) minmax(0, var(--pg-measure-status));', $block, 'form and status columns from the tokens');
	pg_assert_contains('.pg-edit-columns > .order-3 {', $block, 'the save bar spans both columns');
	pg_assert_contains('min-width: min(100%, var(--pg-measure-list-min) + 2rem);', $block, 'a list is at least the list measure or the window');
	pg_assert_contains('body.pg-measure-list .pg-list-table-grid > thead > tr > th:first-child {', $block, 'the name column takes the slack');
}

function test_first_screens_take_the_measures()
{
	$builder = look_page_widths_test_source('product_builder.php');

	pg_assert_contains("'extra classes' => 'products pg-measure-edit',", $builder, 'the product screen is an edit screen');
	pg_assert_contains('<div class="row pg-edit-columns">', $builder, 'its column row');
	pg_assert_contains("'extra classes'=>'products pg-measure-list',", look_page_widths_test_source('view_products.php'), 'the products list is a list');
}
