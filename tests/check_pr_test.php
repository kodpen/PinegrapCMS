<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the branch guards of tools/check_pr.php: reading git's log and
 * numstat output, the trailer and author checks, the branches that skip
 * them and the product-line count.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The tool returns before its command line part when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/check_pr.php');

function check_pr_test_commit($author, $committer, $message)
{
	return array('id' => str_repeat('a', 40), 'author' => $author, 'committer' => $committer, 'message' => $message);
}

// One record per commit, fields split by 0x1f and records ended by 0x1e and
// git's newline; the message keeps its own newlines.
function test_check_pr_parse_log()
{
	$id1 = str_repeat('1', 40);
	$id2 = str_repeat('2', 40);
	$output = "$id1\x1fErdal Güral\x1ferdaltyy@gmail.com\x1fGitHub\x1fnoreply@github.com\x1fMerge branch 'development'\n\nBody\n\x1e\n"
		. "$id2\x1fClaude\x1fnoreply@anthropic.com\x1fClaude\x1fnoreply@anthropic.com\x1fFix\x1e\n";

	pg_assert_same(array(
		array('id' => $id1, 'author' => 'Erdal Güral <erdaltyy@gmail.com>', 'committer' => 'GitHub <noreply@github.com>', 'message' => "Merge branch 'development'\n\nBody\n"),
		array('id' => $id2, 'author' => 'Claude <noreply@anthropic.com>', 'committer' => 'Claude <noreply@anthropic.com>', 'message' => 'Fix'),
	), check_pr_parse_log($output));

	pg_assert_same(array(), check_pr_parse_log(''));
}

function test_check_pr_commit_by_erdal_passes()
{
	$commit = check_pr_test_commit('Erdal Güral <erdaltyy@gmail.com>', 'Erdal Güral <erdaltyy@gmail.com>', "Add the guard\n\nWhy it is needed.\n");
	pg_assert_same(array(), check_pr_commit_problems($commit, true));
}

// GitHub commits a web UI merge or "Update branch" for the signed-in account.
function test_check_pr_github_committer_passes_with_erdal_as_author()
{
	$commit = check_pr_test_commit('Erdal Güral <erdaltyy@gmail.com>', 'GitHub <noreply@github.com>', "Merge branch 'development' into feature/x\n");
	pg_assert_same(array(), check_pr_commit_problems($commit, true));

	$commit = check_pr_test_commit('claude1 <claude1@kodpen.com>', 'GitHub <noreply@github.com>', "Merge branch 'development' into feature/x\n");
	pg_assert_same(array('author is claude1 <claude1@kodpen.com>, not Erdal Güral <erdaltyy@gmail.com>'), check_pr_commit_problems($commit, true));
}

function test_check_pr_wrong_author_and_committer_fail()
{
	$commit = check_pr_test_commit('Claude <noreply@anthropic.com>', 'Claude <noreply@anthropic.com>', "Merge branch 'development'\n");

	pg_assert_same(array(
		'author is Claude <noreply@anthropic.com>, not Erdal Güral <erdaltyy@gmail.com>',
		'committer is Claude <noreply@anthropic.com>, not Erdal Güral <erdaltyy@gmail.com>',
	), check_pr_commit_problems($commit, true));

	// The name and the e-mail address both count.
	$commit = check_pr_test_commit('Erdal Güral <erdal@example.com>', 'Erdal Güral <erdaltyy@gmail.com>', 'Fix');
	pg_assert_same(1, count(check_pr_commit_problems($commit, true)));

	$commit = check_pr_test_commit('Erdal Gural <erdaltyy@gmail.com>', 'Erdal Güral <erdaltyy@gmail.com>', 'Fix');
	pg_assert_same(1, count(check_pr_commit_problems($commit, true)));

	// Without the identity check only trailers count.
	$commit = check_pr_test_commit('dependabot[bot] <49699333+dependabot[bot]@users.noreply.github.com>', 'GitHub <noreply@github.com>', "CI: bump\n\nSigned-off-by: dependabot[bot]\n");
	pg_assert_same(array(), check_pr_commit_problems($commit, false));
}

function test_check_pr_trailers_fail_in_any_case()
{
	$identity = 'Erdal Güral <erdaltyy@gmail.com>';

	$commit = check_pr_test_commit($identity, $identity, "Fix\n\nCo-Authored-By: Claude <noreply@anthropic.com>\n");
	pg_assert_same(array('carries a Co-Authored-By: trailer'), check_pr_commit_problems($commit, true));

	$commit = check_pr_test_commit($identity, $identity, "Fix\n\nco-authored-by: Someone <a@b.c>\nClaude-Session: https://example.com/x\n");
	pg_assert_same(array('carries a Co-Authored-By: trailer', 'carries a Claude-Session: trailer'), check_pr_commit_problems($commit, true));

	// Checked on every branch, the exempt ones included.
	pg_assert_same(array('carries a Co-Authored-By: trailer', 'carries a Claude-Session: trailer'), check_pr_commit_problems($commit, false));

	// The words inside a sentence are not a trailer.
	$commit = check_pr_test_commit($identity, $identity, "Fix\n\nNo Co-Authored-By: lines here.\n");
	pg_assert_same(array(), check_pr_commit_problems($commit, true));
}

function test_check_pr_branch_exempt()
{
	pg_assert_true(check_pr_branch_exempt('merge-train/2026-10-26-1'));
	pg_assert_true(check_pr_branch_exempt('development'));
	pg_assert_true(check_pr_branch_exempt('main'));
	pg_assert_false(check_pr_branch_exempt('feature/ci-guards'));
	pg_assert_false(check_pr_branch_exempt('feature/merge-train/x'));
	pg_assert_false(check_pr_branch_exempt('dependabot/github_actions/actions/checkout-8'));
	pg_assert_false(check_pr_branch_exempt(''));
}

function test_check_pr_parse_numstat()
{
	$output = "12\t3\tpinegrap/a file.php\0-\t-\tpinegrap/images/x.png\0" . "0\t5\ttools/check_pr.php\0";

	pg_assert_same(array(
		'pinegrap/a file.php' => 15,
		'pinegrap/images/x.png' => 0,
		'tools/check_pr.php' => 5,
	), check_pr_parse_numstat($output));

	pg_assert_same(array(), check_pr_parse_numstat(''));
}

function test_check_pr_product_lines()
{
	pg_assert_same(110, check_pr_product_lines(array(
		'pinegrap/includes/fn/output.php' => 100,
		'pinegrap/assets/js/backend.src.js' => 10,
		'pinegrap/assets/js/backend.min.js' => 900,
		'pinegrap/includes/local/tr/a.json' => 40,
		'pinegrap/includes/local/en.json' => 4,
		'tests/check_pr_test.php' => 300,
		'tools/check_pr.php' => 200,
		'docs/memory/workflow/ci-workflows.md' => 20,
	)));

	pg_assert_same(0, check_pr_product_lines(array()));
}
