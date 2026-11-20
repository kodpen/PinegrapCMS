<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A page tab's states (assets/js/style_designer.src.js, _pgTabState()):
 * the marks before and after the name, the end mark, and the states the
 * tab's tip names. Run in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with _pgTabState() loaded; its `out` comes back
// decoded. Null when Node cannot run.
function designer_tab_states_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, '    // ── Tab states ──');
	$b = ($a !== false) ? strpos($js, '    // One tab. A <div>', $a) : false;
	pg_assert_true($b !== false, 'the tab states are in style_designer.src.js');

	if ($b === false) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtabstate');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, substr($js, $a, $b - $a) . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// One call per case: [page, states].
function designer_tab_states($cases)
{
	return designer_tab_states_node('out = ' . json_encode($cases) . '.map(function (c) { return _pgTabState(c[0], c[1]); });');
}

function test_designer_tab_states_plain_and_open_tab()
{
	$out = designer_tab_states(array(
		array(array('page_id' => 4), array('menus' => true)),
		array(array('page_id' => 4), array('active' => true, 'menus' => true)),
		array(array('page_id' => 0), array('menus' => true)),
		array(array('page_id' => 4), array('active' => true, 'menus' => false)),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same(array('before' => array(), 'after' => array(), 'end' => '', 'tip' => array()), $out[0], 'a plain tab shows its name only');
	pg_assert_same('menu', $out[1]['end'], 'the open tab carries the three-dot menu');
	pg_assert_same('close', $out[2]['end'], 'a page never saved carries its ×');
	pg_assert_same('', $out[3]['end'], 'no menu where the editor has no page menus');
}

function test_designer_tab_states_marks_before_the_name_in_order()
{
	$out = designer_tab_states(array(
		array(array('page_id' => 1, 'page_home' => 1), array('active' => true, 'holder' => 'Erdal', 'menus' => true)),
		array(array('page_id' => 1, 'page_home' => 1), array('menus' => true)),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same(array('busy', 'home'), $out[0]['before'], 'the lock of a page somebody else edits, then the house');
	pg_assert_same(array('busy', 'home'), $out[0]['tip'], 'the tip names both');
	pg_assert_same('menu', $out[0]['end'], 'the open tab keeps its menu while somebody else holds it');
	pg_assert_same(array('home'), $out[1]['before'], 'the home page\'s house');
}

function test_designer_tab_states_draft_pill_and_schedule_badge()
{
	$out = designer_tab_states(array(
		array(array('page_id' => 2, 'page_draft' => 1), array('drafts' => true, 'menus' => true)),
		array(array('page_id' => 2, 'page_draft' => 1), array('drafts' => false, 'menus' => true)),
		array(array('page_id' => 2, 'page_draft' => 1), array('drafts' => true, 'sched' => 'Goes on the site at 10:00', 'menus' => true)),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same(array('draft'), $out[0]['after'], 'a draft carries its pill after the name');
	pg_assert_same(array(), $out[1]['after'], 'no pill without the draft feature');
	pg_assert_same(array('sched'), $out[2]['after'], 'the schedule badge takes the pill\'s place');
	pg_assert_same(array('sched'), $out[2]['tip'], 'the tip names the schedule, not the draft');
}

function test_designer_tab_states_page_without_content_rights()
{
	$out = designer_tab_states(array(
		array(array('page_id' => 3, 'access' => 'locked', 'page_home' => 1), array('active' => true, 'holder' => 'Erdal', 'menus' => true)),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same('rights', $out[0]['end'], 'the folder lock takes the menu\'s place');
	pg_assert_same(array('home'), $out[0]['before'], 'nobody\'s lock is shown on a page that does not open');
	pg_assert_same(array('home', 'rights'), $out[0]['tip'], 'the tip names the folder note');
}
