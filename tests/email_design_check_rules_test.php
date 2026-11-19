<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's check list on an e-mail: an e-mail design
 * and a page that goes out as an e-mail (a comment e-mail page, a widget's
 * e-mail page) are not checked with the web page rules an e-mail cannot
 * follow (<main>, landmarks, the skip-link line, the SEO score); a web page
 * keeps them. The editor functions in assets/js/style_designer.src.js are
 * cut out and run under Node (skipped on a machine without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function email_check_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function email_check_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut functions; null without Node.
function email_check_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$source = email_check_test_source();
	$script = '';
	foreach (array('_sdCommentsEmailPageOf', '_sdPageIsEmail', '_sdAuditDropsOnEmail') as $fn) {
		$script .= email_check_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgmailchk');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_email_check_which_page_is_an_email()
{
	$res = email_check_test_run('['
		// An e-mail design: its root carries pgEmail.
		. "_sdPageIsEmail({ page_id: 0, key: 'a' }, { type: 'root', props: { pgEmail: 1 } }, [], []),"
		// A web page.
		. "_sdPageIsEmail({ page_id: 7, key: 'a' }, { type: 'root', props: {} }, [{ page_id: 7, key: 'a' }], [{ next_page_id: 7 }]),"
		// The order receipt page a widget names, by id and by tab.
		. "_sdPageIsEmail({ page_id: 7, key: 'a' }, { type: 'root', props: {} }, [], [{ order_receipt_email_page_id: '7' }]),"
		. "_sdPageIsEmail({ page_id: 0, key: 'r' }, { type: 'root', props: {} }, [], [{ welcome_email_page_id: 'tab:r' }]),"
		// A comment e-mail page another page names.
		. "_sdPageIsEmail({ page_id: 9, key: 'c' }, { type: 'root', props: {} }, [{ page_id: 3, key: 'b', pg_comments_email_page: 9 }], []),"
		// A setting with another name does not count.
		. "_sdPageIsEmail({ page_id: 7, key: 'a' }, { type: 'root', props: {} }, [], [{ email_page_count: 7, receipt_page: 7 }])"
		. ']');
	if ($res === null) {
		return;
	}
	pg_assert_same(array(true, false, true, true, true, false), $res, 'e-mail design, web page, receipt by id, welcome by tab, comment e-mail, other setting');
}

function test_email_check_dropped_rules()
{
	$res = email_check_test_run("['main_missing', 'main_multiple', 'landmark_missing', 'a11y_main_missing', 'h1_missing', 'img_alt', 'mail_block', 'a11y_img_alt', '', null].map(_sdAuditDropsOnEmail)");
	if ($res === null) {
		return;
	}
	pg_assert_same(array(true, true, true, true, false, false, false, false, false, false), $res, 'only the web page rules an e-mail cannot follow');
}

function test_email_check_wiring()
{
	$js = email_check_test_source();
	pg_assert_contains("if (email && _sdAuditDropsOnEmail(pi.rule)) return;", $js, 'the page-level rules are left out on an e-mail');
	pg_assert_contains("if (a11y && _sdActivePageIsEmail(tree)) a11y = a11y.filter(function (f) { return !_sdAuditDropsOnEmail(f.code); });", $js, 'the skip-link finding is left out on an e-mail');
	pg_assert_contains("_sdT('An e-mail is not indexed by search engines: it gets no SEO score.')", $js, 'no SEO score on an e-mail');
	pg_assert_contains('_sdMailCheckIssues(root).forEach(function (mi) { issues.push(mi); });', $js, 'the e-mail rules stay');
}
