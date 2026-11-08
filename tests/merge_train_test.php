<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/merge_train.php: which PRs may ride a
 * train and in what order, an approval the owner has not given yet, the product-line count, reading the Changelog
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

// A generated file is settled by the tool that writes it, once no source
// that tool reads is still in conflict.
function test_merge_train_generated_and_sources()
{
	pg_assert_same('index', merge_train_generated('docs/memory/ui/README.md'));
	pg_assert_same('index', merge_train_generated('docs/memory/core/fn-modules.md'));
	pg_assert_same('script', merge_train_generated('pinegrap/assets/js/pg_image_editor.min.js'));
	pg_assert_same('style', merge_train_generated('pinegrap/assets/css/pg_image_editor.min.css'));
	pg_assert_same('', merge_train_generated('pinegrap/assets/css/pg_image_editor.src.css'));
	pg_assert_same('', merge_train_generated('docs/memory/ui/image-editor-layers.md'));
	pg_assert_same('index', merge_train_source_of('docs/memory/ui/image-editor-layers.md'));
	pg_assert_same('script', merge_train_source_of('pinegrap/assets/js/pg_image_editor_draw.js'));
	pg_assert_same('style', merge_train_source_of('pinegrap/assets/css/pg_image_editor.src.css'));
	pg_assert_same('', merge_train_source_of('pinegrap/includes/fn/image_editor.php'));
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

// A PR naming an approval issue waits for the owner's decision comment.
function test_merge_train_approval_issue_and_answer()
{
	pg_assert_same(8229, merge_train_approval_issue("Approval: #8229 (the owner approves the screenshots before merge).\n\nStep V3"));
	pg_assert_same(6565, merge_train_approval_issue("Step B11.\n\napproval: #6565"));
	pg_assert_same(0, merge_train_approval_issue('Fixes the approval screen (#6565).'));

	$analysis = "## Mimar analizi\n**Türkçe özet:** Onay sorusu, karar sahibin.";

	pg_assert_false(merge_train_approval_given(array()));
	pg_assert_false(merge_train_approval_given(array($analysis)));
	pg_assert_true(merge_train_approval_given(array($analysis, "**Karar (sahip):** Onaylıyorum — #6562 indiği gibi kalır.")));
	pg_assert_true(merge_train_approval_given(array("## Karar (sahip)\nOnaylandı. #6384'ün görünümü")));
	pg_assert_true(merge_train_approval_given(array("Decision (owner): Approved.")));
	pg_assert_false(merge_train_approval_given(array("## Karar (sahip)\nDeğişiklik istiyorum: başlık daha küçük.")));
	pg_assert_false(merge_train_approval_given(array("Onaylıyorum seçeneği listede.")));
}

// Only a branch of this repository rides a train: a fork's branch, a deleted
// fork's (no head repository) or a PR read without its repositories does not.
function test_merge_train_same_repo()
{
	$repo = array('id' => 1001, 'full_name' => 'kodpen/PinegrapCMS');
	$fork = array('id' => 2002, 'full_name' => 'someone/PinegrapCMS');

	pg_assert_true(merge_train_same_repo(array('head' => array('repo' => $repo), 'base' => array('repo' => $repo))));
	pg_assert_false(merge_train_same_repo(array('head' => array('repo' => $fork), 'base' => array('repo' => $repo))), 'a fork');
	pg_assert_false(merge_train_same_repo(array('head' => array('repo' => null), 'base' => array('repo' => $repo))), 'a deleted fork');
	pg_assert_false(merge_train_same_repo(array('head' => array('repo' => $repo), 'base' => array())), 'no base repository');
	pg_assert_false(merge_train_same_repo(array()), 'no repositories');
}

// A fork's PR from a branch named merge-train/* is no train and holds no lock.
function test_merge_train_open_trains_from_this_repository_only()
{
	$repo = array('id' => 1001);
	$fork = array('id' => 2002);
	$open = array(
		array('number' => 10, 'head' => array('ref' => 'merge-train/20261104-1544', 'repo' => $repo), 'base' => array('repo' => $repo)),
		array('number' => 11, 'head' => array('ref' => 'merge-train/20261104-1600', 'repo' => $fork), 'base' => array('repo' => $repo)),
		array('number' => 12, 'head' => array('ref' => 'fix/dash-country-names', 'repo' => $repo), 'base' => array('repo' => $repo)),
	);
	$numbers = array();

	foreach (merge_train_open_trains($open) as $train) {
		$numbers[] = $train['number'];
	}

	pg_assert_same(array(10), $numbers);
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

// Docs-only PRs land alone; the rest (no wait known) smaller first, up to twelve; a PR over
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

// PRs within the limit ride longest-waiting first, so a large one under the
// limit is not starved by smaller ones that keep arriving; equal waits
// smaller first, an unknown wait after the known ones.
function test_merge_train_order_small_longest_waiting_first()
{
	$now = strtotime('2026-11-07T17:00:00Z');
	$prs = array(
		array('number' => 1930, 'product_lines' => 20, 'docs_only' => false, 'eligible_since' => $now - 600),
		array('number' => 1931, 'product_lines' => 979, 'docs_only' => false, 'eligible_since' => $now - 10800),
		array('number' => 1932, 'product_lines' => 5, 'docs_only' => false, 'eligible_since' => null),
		array('number' => 1933, 'product_lines' => 818, 'docs_only' => false, 'eligible_since' => $now - 9000),
		array('number' => 1934, 'product_lines' => 100, 'docs_only' => false, 'eligible_since' => $now - 600),
		array('number' => 1935, 'product_lines' => 1500, 'docs_only' => false, 'eligible_since' => $now - 60),
	);

	$expected = array('alone' => array(), 'train' => array(1931, 1933, 1930, 1934, 1932), 'later' => array(1935));
	pg_assert_same($expected, merge_train_order($prs, $now));
	pg_assert_same($expected, merge_train_order($prs));

	// The cap keeps the longest-waiting twelve.
	$prs = array();

	for ($i = 1; $i <= 14; $i++) {
		$prs[] = array('number' => 2100 + $i, 'product_lines' => 15 - $i, 'docs_only' => false, 'eligible_since' => $now - (1000 * $i));
	}

	$order = merge_train_order($prs, $now);
	pg_assert_same(array(2114, 2113, 2112), array_slice($order['train'], 0, 3));
	pg_assert_same(12, count($order['train']));
	pg_assert_same(array(2102, 2101), $order['later']);
}

// Together the train stays within MERGE_TRAIN_TOTAL_LIMIT product lines: a
// PR that does not fit waits at the head of the next train, while a smaller
// one behind it still rides.
function test_merge_train_order_total_limit()
{
	$now = strtotime('2026-11-07T22:00:00Z');
	$prs = array(
		array('number' => 2201, 'product_lines' => 660, 'docs_only' => false, 'eligible_since' => $now - 5000),
		array('number' => 2202, 'product_lines' => 522, 'docs_only' => false, 'eligible_since' => $now - 4000),
		array('number' => 2203, 'product_lines' => 499, 'docs_only' => false, 'eligible_since' => $now - 3000),
		array('number' => 2204, 'product_lines' => 781, 'docs_only' => false, 'eligible_since' => $now - 2000),
		array('number' => 2205, 'product_lines' => 246, 'docs_only' => false, 'eligible_since' => $now - 1000),
	);

	pg_assert_same(2000, MERGE_TRAIN_TOTAL_LIMIT);
	pg_assert_same(array('alone' => array(), 'train' => array(2201, 2202, 2203, 2205), 'later' => array(2204)), merge_train_order($prs, $now));

	// The next train starts with the one left behind.
	$prs = array($prs[3], array('number' => 2206, 'product_lines' => 990, 'docs_only' => false, 'eligible_since' => $now - 500),
		array('number' => 2207, 'product_lines' => 300, 'docs_only' => false, 'eligible_since' => $now - 100));
	pg_assert_same(array('alone' => array(), 'train' => array(2204, 2206), 'later' => array(2207)), merge_train_order($prs, $now));
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

// Small PRs arrive all day, so an oversized PR that has waited an hour or
// more since it became eligible takes the next train, alone, ahead of them;
// the longest-waiting one first.
function test_merge_train_order_oversized_after_an_hour()
{
	$now = 1700000000;
	$prs = array(
		array('number' => 1910, 'product_lines' => 40, 'docs_only' => false, 'eligible_since' => $now - 60),
		array('number' => 1911, 'product_lines' => 1500, 'docs_only' => false, 'eligible_since' => $now - 3600),
		array('number' => 1912, 'product_lines' => 1100, 'docs_only' => false, 'eligible_since' => $now - 1800),
		array('number' => 1913, 'product_lines' => 1300, 'docs_only' => false, 'eligible_since' => $now - 7200),
		array('number' => 1914, 'product_lines' => 0, 'docs_only' => true, 'eligible_since' => $now - 60),
	);

	pg_assert_same(array(
		'alone' => array(1914),
		'train' => array(1913),
		'later' => array(1910, 1912, 1911),
	), merge_train_order($prs, $now));
}

// Under an hour, or without the time, small PRs still go first.
function test_merge_train_order_oversized_waits_under_an_hour()
{
	$now = 1700000000;
	$prs = array(
		array('number' => 1920, 'product_lines' => 40, 'docs_only' => false, 'eligible_since' => $now),
		array('number' => 1921, 'product_lines' => 1500, 'docs_only' => false, 'eligible_since' => $now - 3599),
	);
	$expected = array('alone' => array(), 'train' => array(1920), 'later' => array(1921));

	pg_assert_same($expected, merge_train_order($prs, $now));
	pg_assert_same($expected, merge_train_order($prs));

	// Equal waits: the smaller PR first.
	$prs[] = array('number' => 1922, 'product_lines' => 1200, 'docs_only' => false, 'eligible_since' => $now - 3599);
	$prs[1]['eligible_since'] = $now - 4000;
	$prs[2]['eligible_since'] = $now - 4000;
	pg_assert_same(array('alone' => array(), 'train' => array(1922), 'later' => array(1920, 1921)), merge_train_order($prs, $now));
}

// A ready PR is eligible from its last green check run, a quiet one from an
// hour after its first check run started.
function test_merge_train_eligible_since()
{
	$runs = array(
		array('started_at' => '2026-11-07T00:00:00Z', 'completed_at' => '2026-11-07T00:03:00Z'),
		array('started_at' => '2026-11-07T00:00:05Z', 'completed_at' => '2026-11-07T00:08:00Z'),
	);

	pg_assert_same(strtotime('2026-11-07T00:08:00Z'), merge_train_eligible_since($runs, true));
	pg_assert_same(strtotime('2026-11-07T01:00:00Z'), merge_train_eligible_since($runs, false));
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

