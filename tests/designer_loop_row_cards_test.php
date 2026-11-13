<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer canvas drawing a loop as the published page prints
 * it: as many cards as one line of its grid holds at the canvas's width (the
 * card and hatched copies), three rows when the cards stack, never more than
 * the widget prints; a listing with no record yet draws them too; a bound
 * picture is a sample photo cut to its box, never a grey box with the token's
 * name. _sdLoopRowCards() and _sdLoopCopyCount() are cut out of
 * assets/js/style_designer.src.js and run under Node (skipped on a machine
 * without Node); the rest is read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_loop_row_cards_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function designer_loop_row_cards_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut functions; null without Node.
function designer_loop_row_cards_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_loop_row_cards_test_source();
	$script = "var _SD_BS_BREAKPOINTS = [['', 0], ['-sm', 576], ['-md', 768], ['-lg', 992], ['-xl', 1200], ['-xxl', 1400]];\n"
		. designer_loop_row_cards_test_function($source, '_sdLoopRowCards') . "\n"
		. designer_loop_row_cards_test_function($source, '_sdLoopCopyCount') . "\n"
		. 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pglooprow');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The test's breakpoint list is the designer's.
function test_designer_loop_row_cards_breakpoints_match()
{
	pg_assert_true(strpos(designer_loop_row_cards_test_source(),
		"var _SD_BS_BREAKPOINTS = [['', 0], ['-sm', 576], ['-md', 768], ['-lg', 992], ['-xl', 1200], ['-xxl', 1400]];") !== false,
		'the breakpoints the test runs with are the designer\'s');
}

// The Blogdan card (col-12 col-md-6 col-lg-4) and the Google reviews card
// hold three on a desktop, two on a tablet, one on a phone; the widest
// breakpoint that applies wins; row-cols counts when the card has no number.
function test_designer_loop_row_cards_per_line()
{
	$cases = array(
		array('col-12 col-md-6 col-lg-4', '', 1200, 3),
		array('col-12 col-md-6 col-lg-4', '', 0, 3),
		array('col-12 col-md-6 col-lg-4', '', 800, 2),
		array('col-12 col-md-6 col-lg-4', '', 375, 1),
		array('col-sm-6 col-xl-3', '', 1300, 4),
		array('col-sm-6 col-xl-3', '', 1000, 2),
		array('col-12', '', 1200, 1),
		array('col-lg-5', '', 1200, 2),
		array('col-auto', 'row-cols-3', 1200, 1),
		array('col', 'row-cols-2 row-cols-lg-3', 1200, 3),
		array('col', 'row-cols-2 row-cols-lg-3', 600, 2),
		array('col', 'g-3', 1200, 1),
		array('card h-100', 'row-cols-md-4', 1200, 4),
		array('card h-100', 'g-4', 1200, 1),
		array('col-md-4 order-2', '', 1200, 3),
		array('my-col-lg-4', '', 1200, 1),
	);
	$args = array();
	foreach ($cases as $c) {
		$args[] = array($c[0], $c[1], $c[2]);
	}
	$answer = designer_loop_row_cards_test_run(json_encode($args) . '.map(function (c) { return _sdLoopRowCards(c[0], c[1], c[2]); })');

	if ($answer === null) {
		return;
	}

	foreach ($cases as $i => $c) {
		pg_assert_same($c[3], $answer[$i], $c[0] . ' / ' . $c[1] . ' at ' . $c[2] . 'px');
	}
}

// Copies after the card: a line less the card; three rows when they stack;
// never more records than the widget prints.
function test_designer_loop_row_cards_copy_count()
{
	$cases = array(
		array(3, 0, 2),
		array(4, 0, 3),
		array(2, 0, 1),
		array(1, 0, 2),
		array(3, 3, 2),
		array(4, 3, 2),
		array(3, 1, 0),
		array(1, 2, 1),
	);
	$args = array();
	foreach ($cases as $c) {
		$args[] = array($c[0], $c[1]);
	}
	$answer = designer_loop_row_cards_test_run(json_encode($args) . '.map(function (c) { return _sdLoopCopyCount(c[0], c[1]); })');

	if ($answer === null) {
		return;
	}

	foreach ($cases as $i => $c) {
		pg_assert_same($c[2], $answer[$i], $c[0] . ' per line, limit ' . $c[1]);
	}
}

// Sample rows of the main loop take the grid's line; a listing with no
// record from the server draws its copies too.
function test_designer_loop_row_cards_wired()
{
	$source = designer_loop_row_cards_test_source();
	pg_assert_true(strpos(designer_loop_row_cards_test_function($source, '_sdListGhostEls'), '_sdMainLoopRowsOfGrid(node, _sdListSampleRows(node))') !== false,
		'the main loop\'s sample rows fill one line of its grid');
	pg_assert_true(strpos(designer_loop_row_cards_test_function($source, '_sdAppendGhosts'), '_sdAppendLoopCopies(loopEl, cached, cfg)') !== false,
		'a listing with no record draws the card\'s copies');
}

// A bound picture is a sample photo cut to the node's box, its field in the
// title: no token name, no grey box.
function test_designer_loop_row_cards_bound_picture()
{
	$source = designer_loop_row_cards_test_source();
	foreach (array('_sdImgPlaceholderForToken', '_sdImgPlaceholderSized') as $name) {
		$body = designer_loop_row_cards_test_function($source, $name);
		pg_assert_true(strpos($body, '_sdSamplePictureUri(') !== false, $name . '() draws the sample photo');
		pg_assert_true(strpos($body, 'tokenName.replace') === false && strpos($body, '#e5e7eb') === false, $name . '() draws no token name or grey box');
	}
	pg_assert_true(strpos(designer_loop_row_cards_test_function($source, '_sdSamplePictureUri'), 'preserveAspectRatio="xMidYMid slice"') !== false,
		'the sample photo is cut to the node\'s box, not stretched');
	pg_assert_true(strpos($source, "_bImg.setAttribute('title', _sdBoundPictureTitle(") !== false,
		'the sample photo names its field in its title');
}
