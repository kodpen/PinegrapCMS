<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/merge_train.php: which PRs may ride a
 * train and in what order, the product-line count, reading the Changelog
 * section of a PR body and writing it into changelog.txt, quoting a
 * command's arguments on Windows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Both tools return before their command line part when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/merge_train.php');
require_once(dirname(__DIR__) . '/tools/check_pr.php');

// The count follows the branch guard's rule, so a PR the guard warns about is
// the PR the train puts in a train of its own.
function test_merge_train_product_lines_match_branch_guard()
{
	$files = array(
		'pinegrap/includes/fn/orders.php' => 120,
		'pinegrap/backend/core/js/panel.min.js' => 900,
		'pinegrap/includes/local/tr/a.json' => 40,
		'pinegrap/changelog.txt' => 2,
		'tests/orders_test.php' => 300,
		'tools/merge_train.php' => 500,
		'docs/memory/commerce/orders.md' => 30,
	);

	pg_assert_same(122, merge_train_product_lines($files));
	pg_assert_same(check_pr_product_lines($files), merge_train_product_lines($files));
	pg_assert_same(0, merge_train_product_lines(array()));
}

// The train's git log --format=%s and a commit message with double quotes
// reach git whole on Windows, quoted as the branch guard quotes its git
// arguments.
function test_merge_train_windows_arg_matches_branch_guard()
{
	pg_assert_same('"--format=%s"', merge_train_windows_arg('--format=%s'));
	pg_assert_same('"Fix the \\"Save\\" button (#12)"', merge_train_windows_arg('Fix the "Save" button (#12)'));
	pg_assert_same('"body=50% off!"', merge_train_windows_arg('body=50% off!'));
	pg_assert_same('""', merge_train_windows_arg(''));

	foreach (array('--format=%s', 'a\\"b', 'C:\\dir\\', "a\\\nb", 'C:\\PHP\\php.exe', '.[]|"#\\(.number)"') as $arg) {
		pg_assert_same(check_pr_windows_arg($arg), merge_train_windows_arg($arg), $arg);
	}
}

function test_merge_train_docs_only()
{
	pg_assert_true(merge_train_docs_only(array('docs/memory/workflow/merge-train.md', 'CLAUDE.md', '.github/workflows/php-checks.yml')));
	pg_assert_false(merge_train_docs_only(array('docs/memory/workflow/merge-train.md', 'tools/merge_train.php')));
	pg_assert_false(merge_train_docs_only(array('pinegrap/index.php')));
	pg_assert_false(merge_train_docs_only(array()));
}

function test_merge_train_checks_green()
{
	pg_assert_true(merge_train_checks_green(array(
		array('status' => 'completed', 'conclusion' => 'success'),
		array('status' => 'completed', 'conclusion' => 'skipped'),
		array('status' => 'completed', 'conclusion' => 'neutral'),
	)));
	pg_assert_false(merge_train_checks_green(array(
		array('status' => 'completed', 'conclusion' => 'success'),
		array('status' => 'in_progress', 'conclusion' => null),
	)), 'a running check');
	pg_assert_false(merge_train_checks_green(array(
		array('status' => 'completed', 'conclusion' => 'failure'),
	)), 'a failed check');
	pg_assert_false(merge_train_checks_green(array(
		array('status' => 'completed', 'conclusion' => 'cancelled'),
	)), 'a cancelled check');
	pg_assert_false(merge_train_checks_green(array()), 'no check has run');
}

// Green and either labelled ready or quiet for an hour since the last push.
function test_merge_train_eligible()
{
	$now = 1761500000;

	pg_assert_true(merge_train_eligible(array('ready'), true, $now - 60, $now));
	pg_assert_true(merge_train_eligible(array(), true, $now - 3600, $now));
	pg_assert_false(merge_train_eligible(array(), true, $now - 3599, $now));
	pg_assert_false(merge_train_eligible(array('ready'), false, $now - 7200, $now));
	pg_assert_false(merge_train_eligible(array('ready-for-review'), true, $now - 60, $now));
}

function test_merge_train_numbers_in_title()
{
	pg_assert_same(array(1981, 1977, 1984), merge_train_numbers_in_title('Merge train 20261026-2027o: #1981 #1977 #1984'));
	pg_assert_same(array(), merge_train_numbers_in_title('Merge train 20261026-2027o'));
}

function test_merge_train_branch_name()
{
	pg_assert_same('merge-train/20261026-2105', merge_train_branch_name(gmmktime(21, 5, 0, 10, 26, 2026)));
}

// Docs-only PRs land alone; the rest smaller first, up to twelve; a PR over
// the size limit rides only a train of its own.
function test_merge_train_order()
{
	$prs = array(
		array('number' => 1900, 'product_lines' => 400, 'docs_only' => false),
		array('number' => 1901, 'product_lines' => 0, 'docs_only' => true),
		array('number' => 1902, 'product_lines' => 1800, 'docs_only' => false),
		array('number' => 1903, 'product_lines' => 20, 'docs_only' => false),
		array('number' => 1904, 'product_lines' => 400, 'docs_only' => false),
		array('number' => 1905, 'product_lines' => 1200, 'docs_only' => false),
	);

	pg_assert_same(array(
		'alone' => array(1901),
		'train' => array(1903, 1900, 1904),
		'later' => array(1905, 1902),
	), merge_train_order($prs));
}

function test_merge_train_order_caps_the_train()
{
	$prs = array();

	for ($i = 1; $i <= 14; $i++) {
		$prs[] = array('number' => 2000 + $i, 'product_lines' => $i, 'docs_only' => false);
	}

	$order = merge_train_order($prs);
	pg_assert_same(12, count($order['train']));
	pg_assert_same(array(2013, 2014), $order['later']);
}

// With only oversized PRs waiting, the smallest of them is a train alone.
function test_merge_train_order_oversized_alone()
{
	$prs = array(
		array('number' => 1905, 'product_lines' => 1200, 'docs_only' => false),
		array('number' => 1902, 'product_lines' => 1800, 'docs_only' => false),
	);

	pg_assert_same(array('alone' => array(), 'train' => array(1905), 'later' => array(1902)), merge_train_order($prs));
}

function test_merge_train_changelog_section()
{
	$body = "## Summary\nAdds X.\n\n## Changelog\n\n`[NEW] Added bulk matching (n11).` and, for the `[SCHEME]` line, the `x` table.\n\n## Verified by running\nphp tools/test.php\n";
	pg_assert_same("`[NEW] Added bulk matching (n11).` and, for the `[SCHEME]` line, the `x` table.", merge_train_changelog_section($body));

	pg_assert_same('none', merge_train_changelog_section("## Changelog\r\nnone\r\n"), 'last section, CRLF');
	pg_assert_same('', merge_train_changelog_section("## Summary\nNo changelog heading.\n"));
	pg_assert_same('none', merge_train_changelog_section("### Changelog\nnone\n### Overlaps\nnone\n"), 'a deeper heading');
}

function test_merge_train_changelog_entry_new_and_scheme()
{
	$entry = merge_train_changelog_entry("`[NEW] Added bulk matching of marketplace products (n11).` and, for the `[SCHEME]` line, the `marketplace_remote_products` table.");

	pg_assert_same(array('[NEW] Added bulk matching of marketplace products (n11).'), $entry['new']);
	pg_assert_same('the `marketplace_remote_products` table.', $entry['scheme']);
	pg_assert_false($entry['fix']);
	pg_assert_false($entry['security']);
}

// "none" and a mention of another step's line add nothing but the [FIX]
// line a fix needs.
function test_merge_train_changelog_entry_none()
{
	foreach (array('none', 'none (covered by the open version\'s `[FIX]` line)', "Covered by the open version's `[FIX]` line; no `[NEW]` or `[SCHEME]` item.", 'none (covered by the `[NEW]` line of step 1, #1880)') as $section) {
		$entry = merge_train_changelog_entry($section);
		pg_assert_same(array(), $entry['new'], $section);
		pg_assert_same('', $entry['scheme'], $section);
		pg_assert_false($entry['security'], $section);
	}

	pg_assert_true(merge_train_changelog_entry("Covered by the open version's `[FIX]` line.")['fix']);
	pg_assert_true(merge_train_changelog_entry('`[SECURITY]` line')['security']);
	pg_assert_false(merge_train_changelog_entry('')['fix']);
}

function test_merge_train_changelog_entry_plain_new_line()
{
	$entry = merge_train_changelog_entry("[NEW] Added a merge queue.\n[SCHEME] the `order` table");

	pg_assert_same(array('[NEW] Added a merge queue.'), $entry['new']);
	pg_assert_same('the `order` table', $entry['scheme']);
}

function merge_train_test_changelog()
{
	return "  R E L E A S E   N O T E S\n\n==========================================================\n2026.11.0\n[NEW] Added one.\n==========================================================\n2026.10.4\n[FIX] Miscellaneous bug fixes and new features.\n";
}

// [NEW] lines go after the open version's own, then [FIX] and [SECURITY];
// older versions are left alone.
function test_merge_train_changelog_apply()
{
	$entry = array('new' => array('[NEW] Added two.'), 'fix' => true, 'security' => true, 'scheme' => '');

	pg_assert_same(
		"  R E L E A S E   N O T E S\n\n==========================================================\n2026.11.0\n[NEW] Added one.\n[NEW] Added two.\n[FIX] Miscellaneous bug fixes and new features.\n[SECURITY] Implemented various security fixes.\n==========================================================\n2026.10.4\n[FIX] Miscellaneous bug fixes and new features.\n",
		merge_train_changelog_apply(merge_train_test_changelog(), $entry)
	);
}

function test_merge_train_changelog_apply_is_idempotent()
{
	$entry = array('new' => array('[NEW] Added one.'), 'fix' => false, 'security' => false, 'scheme' => '');
	pg_assert_same(merge_train_test_changelog(), merge_train_changelog_apply(merge_train_test_changelog(), $entry));

	$entry = array('new' => array('[NEW] Added two.'), 'fix' => true, 'security' => false, 'scheme' => '');
	$once = merge_train_changelog_apply(merge_train_test_changelog(), $entry);
	pg_assert_same($once, merge_train_changelog_apply($once, $entry));
}

// A new [NEW] line goes before the catch-all lines already there, and the
// [SCHEME] line keeps its place at the end.
function test_merge_train_changelog_apply_keeps_tag_order()
{
	$contents = "==========================================================\r\n2026.11.0\r\n[NEW] Added one.\r\n[FIX] Miscellaneous bug fixes and new features.\r\n[SCHEME] The `x` table is added.\r\n==========================================================\r\n2026.10.4\r\n";
	$entry = array('new' => array('[NEW] Added two.'), 'fix' => true, 'security' => true, 'scheme' => '');

	pg_assert_same(
		"==========================================================\r\n2026.11.0\r\n[NEW] Added one.\r\n[NEW] Added two.\r\n[FIX] Miscellaneous bug fixes and new features.\r\n[SECURITY] Implemented various security fixes.\r\n[SCHEME] The `x` table is added.\r\n==========================================================\r\n2026.10.4\r\n",
		merge_train_changelog_apply($contents, $entry)
	);
}

function test_merge_train_changelog_apply_without_version()
{
	$entry = array('new' => array('[NEW] Added two.'), 'fix' => false, 'security' => false, 'scheme' => '');
	pg_assert_same(false, merge_train_changelog_apply("No versions here.\n", $entry));
}

