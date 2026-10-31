<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel pictures that fail to load: both jQuery Lazy calls in
 * backend.src.js take the shared option object, whose onError swaps the
 * endlessly spinning loading.gif for a still, text-free placeholder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function lazy_picture_failure_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_every_lazy_call_takes_the_shared_options()
{
	$source = lazy_picture_failure_source('assets/js/backend.src.js');

	preg_match_all('/\.Lazy\(([^)]*)\)/', $source, $matches);
	pg_assert_same(2, count($matches[1]), 'two Lazy calls');
	foreach ($matches[1] as $argument) {
		pg_assert_same('pgLazyOptions', trim($argument), 'Lazy call without the shared options');
	}
}

function test_failed_picture_gets_a_still_placeholder()
{
	$source = lazy_picture_failure_source('assets/js/backend.src.js');

	pg_assert_contains('var pgLazyOptions = {', $source, 'shared options defined');
	pg_assert_contains('onError: function (element)', $source, 'onError handler');
	pg_assert_contains("addClass('pg-lazy-failed')", $source, 'marker class on a failed picture');
	pg_assert_contains("attr('src', pgLazyFailedSrc)", $source, 'failed picture shows the still placeholder');
	pg_assert_contains("fill='%23868e96'", $source, 'placeholder in the No Image grey');
	pg_assert_false((bool) preg_match('/pgLazyFailedSrc\s*=[^;]*%3Ctext/i', $source), 'placeholder carries no text');
	pg_assert_false((bool) preg_match('/pg-lazy-failed[^\n]*alt/', $source), 'alt left as it is');
}

function test_reduced_motion_shows_a_still_picture_while_loading()
{
	$source = lazy_picture_failure_source('assets/js/backend.src.js');

	pg_assert_contains("matchMedia('(prefers-reduced-motion: reduce)')", $source, 'reduced motion checked');
	pg_assert_contains('pgLazyPendingSrc', $source, 'still loading placeholder');
}

function test_minified_bundle_carries_the_handler()
{
	$min = lazy_picture_failure_source('assets/js/backend.min.js');

	pg_assert_true(strpos($min, 'pg-lazy-failed') !== false, 'backend.min.js rebuilt');
}
