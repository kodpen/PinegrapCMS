<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's look A type and recipes in backend.src.css:
 * Inter ships self-hosted with its OFL licence, the @font-face rules point at
 * files that exist, the type scale and tone tokens are declared, every recipe
 * class is defined, and the block keeps to the weights of the scale.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_recipes_test_block()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$start = strpos($css, 'Look A: the panel\'s type and recipes.');

	return $start === false ? '' : substr($css, $start);
}

function test_look_inter_is_self_hosted_with_its_licence()
{
	$dir = dirname(__DIR__) . '/pinegrap/assets/fonts/inter/';

	pg_assert_contains('SIL Open Font License, Version 1.1', (string) @file_get_contents($dir . 'LICENSE.txt'), 'the OFL licence ships beside the font');
	pg_assert_same('693b77d4f32ee9b8bfc995589b5fad5e99adf2832738661f5402f9978429a8e3', (string) @hash_file('sha256', $dir . 'InterVariable.woff2'), 'InterVariable.woff2 is the 4.1 release file');
	pg_assert_same('e564f652916db6c139570fefb9524a77c4d48f30c92928de9db19b6b5c7a262a', (string) @hash_file('sha256', $dir . 'InterVariable-Italic.woff2'), 'InterVariable-Italic.woff2 is the 4.1 release file');
}

function test_look_font_face_urls_exist()
{
	$block = look_recipes_test_block();

	preg_match_all('#url\("\.\./fonts/([^"]+)"\)#', $block, $m);
	pg_assert_same(2, count($m[1]), 'two @font-face sources, upright and italic');
	foreach ($m[1] as $path) {
		pg_assert_true(is_file(dirname(__DIR__) . '/pinegrap/assets/fonts/' . $path), $path . ' exists');
	}
	pg_assert_contains('--bs-body-font-family: var(--pg-font-sans);', substr($block, strrpos($block, '@layer custom') ?: 0), 'the body family is set after the layer, unlayered');
}

function test_look_tokens_and_recipes_are_defined()
{
	$block = look_recipes_test_block();

	foreach (array('--pg-font-sans', '--pg-text-count', '--pg-text-meta', '--pg-text-2', '--pg-text-base', '--pg-text-status-title', '--pg-text-section-title', '--pg-text-screen-title', '--pg-weight-strong', '--pg-weight-title', '--pg-tint-from', '--pg-shadow-card', '--pg-shadow-float', '--pg-thumb-shadow') as $token) {
		pg_assert_contains($token . ':', $block, $token . ' is declared');
	}
	foreach (array('team', 'shop', 'content', 'accounting', 'warning', 'reports', 'system', 'blue', 'violet', 'amber', 'teal', 'rose', 'gray') as $tone) {
		pg_assert_contains('.pg-tone-' . $tone . ' { --pg-tone: var(--pg-tone-' . $tone . '); }', $block, 'tone ' . $tone);
	}
	foreach (array('.pg-title-screen', '.pg-title-section', '.pg-card', '.pg-card-inner', '.pg-floatbar', '.pg-field-label', '.pg-input', '.pg-field-help', '.pg-switch', '.pg-checkbox', '.pg-button-fill', '.pg-button-quiet', '.pg-button-ghost', '.pg-button-danger', '.pg-icon-button', '.pg-seg', '.pg-seg-item', '.pg-pill', '.pg-icon-tile', '.pg-initials', '.pg-count', '.pg-unread-dot', '.pg-list-row', '.pg-gather', '.pg-gather-toggle') as $class) {
		pg_assert_true(preg_match('/^' . preg_quote($class, '/') . '[ ,{:.]/m', $block) === 1, $class . ' is defined');
	}
}

function test_look_light_dark_holds_only_colours()
{
	preg_match_all('/light-dark\(([^,()]+)/', look_recipes_test_block(), $m);
	foreach ($m[1] as $first) {
		pg_assert_true(preg_match('/^\s*(#|rgba?$|var$)/', $first) === 1 || strpos($first, 'rgba') === 0, 'light-dark() takes a colour, not ' . $first);
	}
}

function test_look_weights_stay_on_the_scale()
{
	preg_match_all('/font-weight:\s*(\d+)/', look_recipes_test_block(), $m);
	foreach ($m[1] as $weight) {
		pg_assert_true((int) $weight < 700, 'no weight of 700 or above (' . $weight . ')');
	}
}
