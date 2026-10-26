<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's Attributes panel and Styles cards at content
 * level (manager, user): outside a marked area they are read-only, since
 * the save takes only text and image addresses there, and User Styles are
 * the design's. The reason helpers are cut out of
 * assets/js/style_designer.js and run under Node with the permission
 * questions stubbed; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $steps after the panel reason helpers; null without Node. $level is
// the designer access level, nodes with `own` are editable, nodes with
// `closed` sit in a shared component not opened to the operator.
function designer_content_panels_test_run($level, $steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdContentReasonText(');
	$to = ($from !== false) ? strpos($source, '// Draws a panel or one of its cards', $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the panel reason helpers');

	if (($from === false) || ($to === false)) {
		return null;
	}

	$script = 'function _sdT(s) { return s; }'
		. 'function _sdIsContentLevel() { return ' . json_encode($level === 'content') . '; }'
		. 'function _sdCanEditNode(n) { return !!n.own; }'
		. 'function _sdInClosedShared(n) { return !!n.closed; }'
		. substr($source, $from, $to - $from)
		. $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgcontentpanels');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Content level: a node outside a marked area locks the panel, a node of
// a closed shared component says so, a selection is all or none.
function test_designer_content_panels_lock_reason()
{
	$answer = designer_content_panels_test_run('content',
		'var own = { own: true }, page = {}, shared = { closed: true };'
		. 'console.log(JSON.stringify([_sdPanelLockReason(own), _sdPanelLockReason(page), _sdPanelLockReason(shared),'
		. ' _sdPanelLockReason([own, own]), _sdPanelLockReason([own, page]), _sdPanelLockReason(null),'
		. ' _sdContentReasonText("design"), _sdContentReasonText("area")]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same('', $answer[0], 'a node inside a marked area');
	pg_assert_same('area', $answer[1], 'a node outside one');
	pg_assert_same('shared', $answer[2], 'a node of a closed shared component');
	pg_assert_same('', $answer[3]);
	pg_assert_same('area', $answer[4], 'one node of the selection is not the operator\'s');
	pg_assert_same('', $answer[5], 'nothing selected');
	pg_assert_same('User Styles belong to the whole design and are managed by designers.', $answer[6]);
	pg_assert_same('You can edit text, and anything inside an area marked as editable.', $answer[7]);
}

// A designer's panels are never locked by this rule.
function test_designer_content_panels_full_level()
{
	$answer = designer_content_panels_test_run('full',
		'console.log(JSON.stringify([_sdPanelLockReason({}), _sdPanelLockReason({ closed: true })]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('', ''), $answer);
}
