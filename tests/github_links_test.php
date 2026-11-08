<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the repository link rules that need no database
 * (includes/fn/github_links.php): the repository name, the link events and
 * the hook events they need, the hook request, the X-Hub-Signature-256 check,
 * the delivery id and the channel lines.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_github_repo_parse_reads_owner_and_name()
{
	pg_assert_same('kodpen/pinegrapcms', pg_github_repo_parse('kodpen/pinegrapcms'), 'plain');
	pg_assert_same('kodpen/pinegrapcms', pg_github_repo_parse('  kodpen/pinegrapcms.git '), '.git dropped');
	pg_assert_same('kodpen/pinegrapcms', pg_github_repo_parse('https://github.com/kodpen/pinegrapcms/pull/12'), 'an address');
	pg_assert_same('a-b/x.y_z', pg_github_repo_parse('a-b/x.y_z'), 'allowed characters');
	pg_assert_same('', pg_github_repo_parse('-ab/x'), 'owner starts with a hyphen');
	pg_assert_same('', pg_github_repo_parse('a--b/x'), 'double hyphen');
	pg_assert_same('', pg_github_repo_parse('owner/..'), 'dot dot');
	pg_assert_same('', pg_github_repo_parse('owner/name/extra'), 'three parts');
	pg_assert_same('', pg_github_repo_parse('owner'), 'no name');
	pg_assert_same('', pg_github_repo_parse('own er/name'), 'space');
	pg_assert_same('', pg_github_repo_parse(str_repeat('a', 40) . '/x'), 'owner too long');
}

function test_github_link_events_and_hooks()
{
	pg_assert_same(array('ci', 'label'), pg_github_link_events_clean('label, ci,nope,ci'), 'catalogue order, known only');
	pg_assert_same(array(), pg_github_link_events_clean(null), 'nothing');
	pg_assert_same(array('pull_request', 'workflow_run'), pg_github_hook_events(array('label', 'pull_request', 'ci')), 'one pull_request hook');
	pg_assert_same(array(), pg_github_hook_events(array()), 'no events, no hooks');

	$request = pg_github_hook_request('https://site.test/github_webhook.php?link=3', 's3cret', array('ci'));
	pg_assert_same('web', $request['name'], 'web hook');
	pg_assert_same(array('workflow_run'), $request['events'], 'events');
	pg_assert_same('json', $request['config']['content_type'], 'json body');
	pg_assert_same('0', $request['config']['insecure_ssl'], 'TLS checked');
	pg_assert_same('s3cret', $request['config']['secret'], 'secret');
}

function test_github_signature_check()
{
	$body = '{"zen":"Keep it logically awesome."}';
	$good = 'sha256=' . hash_hmac('sha256', $body, 'secret');

	pg_assert_true(pg_github_signature_valid('secret', $body, $good), 'valid');
	pg_assert_true(pg_github_signature_valid('secret', $body, strtoupper($good)), 'hex case does not matter');
	pg_assert_true(!pg_github_signature_valid('other', $body, $good), 'wrong secret');
	pg_assert_true(!pg_github_signature_valid('secret', $body . ' ', $good), 'changed body');
	pg_assert_true(!pg_github_signature_valid('secret', $body, 'sha1=' . hash_hmac('sha1', $body, 'secret')), 'sha1 header refused');
	pg_assert_true(!pg_github_signature_valid('secret', $body, ''), 'no header');
	pg_assert_true(!pg_github_signature_valid('', $body, 'sha256=' . hash_hmac('sha256', $body, '')), 'empty secret never matches');

	// GitHub's documented example.
	pg_assert_true(pg_github_signature_valid("It's a Secret to Everybody", 'Hello, World!', 'sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17'), 'GitHub example');
}

function test_github_delivery_id()
{
	pg_assert_same('72d3162e-cc78-11e3-81ab-4c9367dc0958', pg_github_delivery_id(' 72D3162E-CC78-11E3-81AB-4C9367DC0958 '), 'GUID');
	pg_assert_same('', pg_github_delivery_id('72d3162e'), 'too short');
	pg_assert_same('', pg_github_delivery_id("72d3162e-cc78-11e3-81ab-4c9367dc0958'"), 'quote');
}

function test_github_line_ci()
{
	$link = array('events' => array('ci'), 'labels' => array());
	$payload = array(
		'action'       => 'completed',
		'workflow_run' => array(
			'name'          => 'PHP checks',
			'conclusion'    => 'failure',
			'head_branch'   => 'plan/x',
			'head_sha'      => 'abcdef1234567890',
			'html_url'      => 'https://github.com/o/r/actions/runs/9',
			'display_title' => "Fix\nthe thing",
			'pull_requests' => array(array('number' => 42)),
		),
	);

	pg_assert_same('[ci] red #42 plan/x abcdef1 php-checks=failure https://github.com/o/r/actions/runs/9 Fix the thing', pg_github_line('workflow_run', $payload, $link), 'red with a pull request');

	$payload['workflow_run']['conclusion'] = 'success';
	$payload['workflow_run']['pull_requests'] = array();
	pg_assert_same('[ci] green plan/x abcdef1 php-checks=success https://github.com/o/r/actions/runs/9 Fix the thing', pg_github_line('workflow_run', $payload, $link), 'green without a pull request');

	$payload['workflow_run']['conclusion'] = 'cancelled';
	pg_assert_same('', pg_github_line('workflow_run', $payload, $link), 'cancelled writes nothing');

	$payload['workflow_run']['conclusion'] = 'success';
	$payload['action'] = 'requested';
	pg_assert_same('', pg_github_line('workflow_run', $payload, $link), 'only completed runs');

	$payload['action'] = 'completed';
	pg_assert_same('', pg_github_line('workflow_run', $payload, array('events' => array('pull_request'))), 'link does not follow CI');
}

function test_github_line_pull_requests_and_labels()
{
	$pull = array('number' => 7, 'title' => 'Add a thing', 'html_url' => 'https://github.com/o/r/pull/7', 'merged' => false,
		'head' => array('ref' => 'feature', 'sha' => '1234567abcdef'));
	$link = array('events' => array('pull_request', 'label'), 'labels' => array('Ready'));

	pg_assert_same('[pr] opened #7 feature 1234567 https://github.com/o/r/pull/7 Add a thing', pg_github_line('pull_request', array('action' => 'opened', 'pull_request' => $pull), $link), 'opened');
	pg_assert_same('[pr] closed #7 feature 1234567 https://github.com/o/r/pull/7 Add a thing', pg_github_line('pull_request', array('action' => 'closed', 'pull_request' => $pull), $link), 'closed');

	$pull['merged'] = true;
	pg_assert_same('[pr] merged #7 feature 1234567 https://github.com/o/r/pull/7 Add a thing', pg_github_line('pull_request', array('action' => 'closed', 'pull_request' => $pull), $link), 'merged');
	pg_assert_same('', pg_github_line('pull_request', array('action' => 'synchronize', 'pull_request' => $pull), $link), 'a push to the branch writes nothing');

	pg_assert_same('[ready] #7 feature 1234567 Add a thing', pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'ready'), 'pull_request' => $pull), $link), 'followed label');
	pg_assert_same('', pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'bug'), 'pull_request' => $pull), $link), 'label not followed');
	pg_assert_same('', pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'ready'), 'pull_request' => $pull), array('events' => array('pull_request'), 'labels' => array('ready'))), 'labels not followed');
	pg_assert_same('', pg_github_line('pull_request', array('action' => 'opened', 'pull_request' => $pull), array('events' => array('label'), 'labels' => array('ready'))), 'pull requests not followed');
	pg_assert_same('', pg_github_line('ping', array('zen' => 'x'), $link), 'ping writes nothing');
	pg_assert_same('', pg_github_line('pull_request', 'nope', $link), 'not a payload');
}

function test_github_label_prefix()
{
	pg_assert_same('needs-review', pg_github_label_prefix(' Needs Review! '), 'folded');
	pg_assert_same('', pg_github_label_prefix('!!!'), 'nothing left');
	pg_assert_same(40, strlen(pg_github_label_prefix(str_repeat('a', 60))), 'clipped');
}
