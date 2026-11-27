<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pure rules of the Page Design Studio home's script
 * (assets/js/designer_home.js, window.PgDesignerHome): which page chips stay
 * drafts after "Put on the site", what leaving the rename box does after a
 * refused name, which design takes the focus after a delete under a search,
 * and the address left once ?start= has opened the New design window. Runs
 * in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PgDesignerHome loaded as H (the page part
// of the script stops at once: there is no #sdh-config); its `out` comes
// back decoded. Null when Node cannot run.
function designer_home_js_test_node($code)
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

	$file = PG_FUNCTIONS_DIR . '/assets/js/designer_home.js';
	pg_assert_true(is_file($file), 'designer_home.js exists');

	if (!is_file($file)) {
		return null;
	}

	$script = "var window = {}, document = { getElementById: function () { return null; } };\n"
		. file_get_contents($file) . "\n"
		. "var H = window.PgDesignerHome || {}, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$tmp = @tempnam(sys_get_temp_dir(), 'pgdh');

	if ($tmp === false) {
		return null;
	}

	file_put_contents($tmp, $script);
	$out = array();
	@exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $status);
	@unlink($tmp);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// "Put on the site": a draft a prototype link holds stays a draft, so its
// chip keeps the dashed outline; the answer lists the drafts left.
function test_designer_home_js_chip_draft_after_publish()
{
	$res = designer_home_js_test_node('out = typeof H.chipDraft !== "function" ? "missing" : ['
		. ' H.chipDraft("12", false, [12]), H.chipDraft("13", false, [12]),'
		. ' H.chipDraft("12", true, [13]), H.chipDraft("13", true, [13]),'
		. ' H.chipDraft("12", true, null), H.chipDraft("12", false, undefined)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('1', '0', '0', '1', '1', '0'), $res, 'held draft stays; a page the move left keeps its state; an older answer paints as before');
}

// After a refused name, leaving the box with that name puts the old one
// back instead of sending it again and taking the focus back (a phone has
// no Escape); Enter still tries again, a changed name is sent.
function test_designer_home_js_rename_step_after_refusal()
{
	$res = designer_home_js_test_node('out = typeof H.renameStep !== "function" ? "missing" : ['
		. ' H.renameStep("  Kafe  ", "Kafe", "", true), H.renameStep("", "Kafe", "", false),'
		. ' H.renameStep("Yeni", "Kafe", "", true), H.renameStep("Yeni", "Kafe", "Yeni", true),'
		. ' H.renameStep("Yeni ", "Kafe", "Yeni", false), H.renameStep("Yeni 2", "Kafe", "Yeni", true)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		array('act' => 'finish', 'value' => 'Kafe'),
		array('act' => 'finish', 'value' => ''),
		array('act' => 'save', 'value' => 'Yeni'),
		array('act' => 'finish', 'value' => 'Yeni'),
		array('act' => 'save', 'value' => 'Yeni'),
		array('act' => 'save', 'value' => 'Yeni 2'),
	), $res);
}

// A design deleted while a search is typed hands the focus to the next
// design the search shows, else the one before it; none when none shows.
function test_designer_home_js_next_shown_after_delete()
{
	$res = designer_home_js_test_node('out = typeof H.nextShown !== "function" ? "missing" : ['
		. ' H.nextShown([false, false, false], 1), H.nextShown([false, false, true, false], 1),'
		. ' H.nextShown([false, true, false, true], 2), H.nextShown([true, false, true], 1),'
		. ' H.nextShown([false], 0)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(2, 3, 0, -1, -1), $res);
}

// ?start= opens the New design window once: the address keeps everything
// else, so a reload (the one after deleting the large design among them)
// does not open it again.
function test_designer_home_js_address_without_start()
{
	$res = designer_home_js_test_node('out = typeof H.withoutStart !== "function" ? "missing" : ['
		. ' H.withoutStart("https://x.test/pg/view_system_styles.php?start=template&from=pages"),'
		. ' H.withoutStart("https://x.test/pg/view_system_styles.php?start=blank"),'
		. ' H.withoutStart("https://x.test/pg/view_system_styles.php?from=pages#top"),'
		. ' H.withoutStart("https://x.test/pg/view_system_styles.php?a=1&start=zip&b=2")];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(
		'https://x.test/pg/view_system_styles.php?from=pages',
		'https://x.test/pg/view_system_styles.php',
		'https://x.test/pg/view_system_styles.php?from=pages#top',
		'https://x.test/pg/view_system_styles.php?a=1&b=2',
	), $res);
}
