<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests of tools/look_rules.php, what tools/check_look.php counts: font
 * sizes and weights off look A's type scale and colour literals outside the
 * tokens, with custom properties and the look's own blocks left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/look_rules.php');

function test_look_sizes_on_and_off_the_scale()
{
	foreach (array('14px', '0.875rem', '.75rem', '24px', '1.5rem', '1em', '90%', 'small', 'var(--pg-text-2)', 'inherit', '13px !important') as $value) {
		pg_assert_false(pg_look_size_off_scale($value), $value . ' passes');
	}
	foreach (array('16px', '1rem', '1.1rem', '10px', '18px', '10pt') as $value) {
		pg_assert_true(pg_look_size_off_scale($value), $value . ' is off the scale');
	}
}

function test_look_weights_on_and_off_the_scale()
{
	foreach (array('400', '500', '550', '650', 'normal', 'var(--pg-weight-title)', 'inherit') as $value) {
		pg_assert_false(pg_look_weight_off_scale($value), $value . ' passes');
	}
	foreach (array('600', '700', 'bold', 'bolder', '300') as $value) {
		pg_assert_true(pg_look_weight_off_scale($value), $value . ' is off the scale');
	}
}

function test_look_colour_literals_are_counted()
{
	pg_assert_same(0, pg_look_colour_literals('var(--pg-fg-2)'), 'a token');
	pg_assert_same(0, pg_look_colour_literals('color-mix(in srgb, var(--pg-primary) 12%, transparent)'), 'a mix of a token');
	pg_assert_same(1, pg_look_colour_literals('#fff'), 'hex');
	pg_assert_same(2, pg_look_colour_literals('0 1px 2px rgba(0, 0, 0, .2), 0 0 0 1px white'), 'rgba and white');
	pg_assert_same(0, pg_look_colour_literals('url(#clip)'), 'a fragment that is not hex');
}

function test_look_counts_skip_tokens_comments_and_the_look_blocks()
{
	$css = ":root { --pg-x: #123456; }\n"
		. "/* color: #000; font-size: 18px */\n"
		. "a:hover { color: #ff0000; font-size: 18px; font-weight: 700; }\n"
		. "@media (min-width: 1px) { .b { font-weight: 550; font-size: 13px; color: var(--pg-fg-2); } }\n"
		. "/* ==========\n   Look A: recipes.\n   ========== */\n"
		. ".pg-x { color: #fff; font-size: 10px; font-weight: 800; }\n"
		. "/* ==========\n   Another block.\n   ========== */\n"
		. ".c { background: rgb(1, 2, 3); }\n";

	pg_assert_same(array('size' => 1, 'weight' => 1, 'colour' => 2), pg_look_counts($css), 'one size, one weight, two colours outside the look block');
}

function test_look_baseline_lists_every_stylesheet()
{
	$baseline = json_decode((string) file_get_contents(dirname(__DIR__) . '/tools/check_look_baseline.json'), true);

	foreach (pg_look_stylesheets() as $path) {
		pg_assert_true(isset($baseline[$path]['size'], $baseline[$path]['weight'], $baseline[$path]['colour']), $path . ' has a baseline');
	}
}
