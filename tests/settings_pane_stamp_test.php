<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel script's stamp a page carries to the settings pane
 * (pg_js_urls_stamp(), pg_js_stamp_stale(), includes/fn/output.php): a page
 * left open across an update gets one line with a Reload button instead of a
 * pane its script cannot serve (settings_pane.php, includes/settings/modal.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The stamp is the ?v= of each address, in load order.
function test_settings_stamp_of_urls()
{
	pg_assert_same('1762900000', pg_js_urls_stamp(array('/pinegrap/assets/js/backend.min.js?v=1762900000')));
	pg_assert_same('11.22', pg_js_urls_stamp(array('/a.js?v=11', '/b.js?x=1&v=22')), 'sources one by one, joined');
	pg_assert_same('5', pg_js_urls_stamp(array('//cdn.example.com/p/backend.min.js?v=5#top')), 'an address that starts with two slashes');
	pg_assert_same('', pg_js_urls_stamp(array('/a.js', '/b.js?version=3', '/c.js?v=abc')), 'no address carries a number stamp');
	pg_assert_same('', pg_js_urls_stamp(array()));
	pg_assert_same('', pg_js_urls_stamp('not a list'));
}

// A page is stale only when both stamps are known and differ.
function test_settings_stamp_compare()
{
	pg_assert_false(pg_js_stamp_stale('11.22', '11.22'), 'the same script');
	pg_assert_true(pg_js_stamp_stale('11.22', '11.23'), 'a file rebuilt since the page was drawn');
	pg_assert_true(pg_js_stamp_stale('11', '11.22'), 'a page in another mode');
	pg_assert_false(pg_js_stamp_stale('', '11'), 'a page drawn before pages carried a stamp');
	pg_assert_false(pg_js_stamp_stale('11', ''), 'the server cannot read its own files');
	pg_assert_false(pg_js_stamp_stale(array('11'), '12'), 'a stamp that is not text');
	pg_assert_false(pg_js_stamp_stale(' 11 ', '11'), 'spaces around it');
}

// The panel bundle's stamp is the one its <script> tags carry.
function test_settings_stamp_of_the_panel_bundle()
{
	$stamp = pg_js_bundle_stamp('assets/js/backend.min.js');

	pg_assert_true((bool) preg_match('/^[0-9]+(\.[0-9]+)*$/', $stamp), 'the panel bundle has a stamp: ' . $stamp);
	pg_assert_same(pg_js_urls_stamp(pg_js_bundle_urls('assets/js/backend.min.js')), $stamp);
	pg_assert_same('', pg_js_bundle_stamp('assets/js/no_such_bundle.min.js'));
}

// The dialog sends the stamp it was drawn with, and the pane answers a stale
// page with the Reload line before it draws any card.
function test_settings_stamp_wiring()
{
	$modal = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/modal.php');
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/settings_pane.php');

	pg_assert_contains("pg_js_bundle_stamp('assets/js/backend.min.js')", $modal, 'the dialog reads the panel bundle stamp');
	pg_assert_contains('data-pgurl="\' . h(pg_settings_pane_url())', $modal, 'and puts it in the address the dialog asks');

	pg_assert_contains("pg_js_stamp_stale(", $pane, 'the pane compares the stamps');
	pg_assert_contains('window.location.reload()', $pane, 'the line reloads the page');

	$stale = strpos($pane, 'pg_js_stamp_stale(');
	$fragment = strpos($pane, '$fragment = pg_settings_fragment(');
	$gate = strpos($pane, 'pg_settings_may_open($pane, $user, $reader)');

	pg_assert_true(($stale !== false) && ($fragment !== false) && ($stale < $fragment), 'no card is drawn for a stale page');
	pg_assert_true(($gate !== false) && ($gate < $stale), 'the gate still runs first');
}
