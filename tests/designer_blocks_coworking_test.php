<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking block family (assets/js/style_designer_blocks_
 * coworking.js), run under Node by helpers/designer_family_run.php (skipped
 * on a machine without Node): its own palette group, the workspace hero's
 * one h1 and no other heading, no fixed colour class, no year, every
 * entrance kept by the interaction normalizer, a way in per kind of space
 * with its price and unit as separate texts, alt text on both pictures, and
 * its rules reaching the block stylesheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/designer_family_run.php');

function test_designer_blocks_coworking_family_and_hero()
{
	$got = pg_test_designer_family_run('coworking');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('coworking-', $got['prefix']);
	pg_assert_same('Coworking', $got['group'], 'its own palette group');
	pg_assert_same(array('coworking-hero'), array_keys($got['blocks']));
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');

	$hero = $got['blocks']['coworking-hero'];
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'btn-light', 'btn-dark');
	pg_assert_same(array('semantic', 'section'), $hero['root'], 'a section');
	pg_assert_same(array('h1'), $hero['headings'], 'one h1: a hero opens the page');
	pg_assert_same(array(), array_values(array_intersect($hero['classes'], $fixed)), 'no fixed colour class');
	pg_assert_same(array(), array_values(preg_grep('/\\{var|\\b(19|20)\\d\\d\\b/', $hero['texts'])), 'no empty slot, no year');
	pg_assert_same(array('ok', 'ok', 'ok'), $hero['fx'], 'text, ways and pictures come in on load, kept by the normalizer');
	pg_assert_same(4, count(array_keys($hero['classes'], 'pg-cw-way', true)), 'a way in per kind of space');
	foreach (array('$25', '/day', '$290', '/month', '$35', '/hour') as $t) {
		pg_assert_true(in_array($t, $hero['texts'], true), "price and unit apart: $t");
	}
	pg_assert_true(in_array('Members at long desks under the warehouse windows', $hero['texts'], true)
		&& in_array('A phone booth with a lamp and a stool', $hero['texts'], true), 'both pictures say what they show');
}

function test_designer_blocks_coworking_rules_reach_the_stylesheet()
{
	$parsed = _pg_designer_block_css_parse(pg_test_designer_family_file('coworking'));
	pg_assert_same(array('pg-cw-way', 'pg-cw-mosaic', 'pg-cw-price'), array_keys($parsed['rules']));
	pg_assert_same(array('pg-cw-way', 'pg-cw-mosaic', 'pg-cw-price'), $parsed['needs']['coworking-hero']);
	pg_assert_contains('prefers-reduced-motion', $parsed['rules']['pg-cw-way'], 'the way in keeps still under reduced motion');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-cw-way'], $source['rules']['pg-cw-mosaic'], $source['rules']['pg-cw-price']), 'the block stylesheet holds the rules');
}
