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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/github_links.php');
require_once(__DIR__ . '/helpers/source_reader.php');

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

function test_github_labels_clean_folds_and_caps()
{
	pg_assert_same(array('ready', 'needs-review'), pg_github_labels_clean('Ready, needs review,READY,,'), 'folded, no repeats');
	pg_assert_same(10, count(pg_github_labels_clean(implode(',', range(1, 15)))), 'at most 10');
	pg_assert_same(array(), pg_github_labels_clean(null), 'nothing');
}

function test_github_hook_url_names_the_link()
{
	pg_assert_same('https://example.com/pinegrap/github_webhook.php?link=7', pg_github_hook_url('https://example.com/pinegrap/', '7'), 'one slash, the id');
}

function test_github_api_problem_reads_the_status()
{
	pg_assert_true(strpos(pg_github_api_problem(422, array('message' => 'Hook already exists')), 'Hook already exists') !== false, 'GitHub\'s message on 422');
	pg_assert_same(pg_github_api_problem(403, null), pg_github_api_problem(404, null), 'no access reads as not found');
	pg_assert_same('timeout', pg_github_api_problem(0, null, 'timeout'), 'the transport\'s words');
}

function test_github_link_channels_check_the_full_row()
{
	// The sidebar's short form has no archived_at, which the post check reads.
	$source = pg_test_function_source('ws_github_link_channels');

	pg_assert_contains("ws_can_post_channel(\$viewer, ws_channel(\$brief['id']))", $source, 'the channel\'s own row is checked');
	pg_assert_false(strpos($source, "ws_can_post_channel(\$viewer, \$brief)") !== false, 'never the short form');
}

function test_github_line_read_round_trips_every_written_shape()
{
	$link = array('events' => array('ci', 'pull_request', 'label'), 'labels' => array('ready', 'ci', 'pr'));
	$run = array(
		'conclusion'    => 'failure',
		'head_branch'   => 'plan/x',
		'head_sha'      => 'abcdef1234567890',
		'name'          => 'PHP checks',
		'html_url'      => 'https://github.com/o/r/actions/runs/9',
		'display_title' => 'Fix the @thing now',
		'pull_requests' => array(array('number' => 42)),
	);
	$pull = array('number' => 7, 'head' => array('ref' => 'feature', 'sha' => '1234567abc'), 'title' => 'Add a thing', 'html_url' => 'https://github.com/o/r/pull/7');

	$cases = array(
		'ci red' => array(
			pg_github_line('workflow_run', array('action' => 'completed', 'workflow_run' => $run), $link),
			array('kind' => 'ci', 'state' => 'red', 'number' => 42, 'branch' => 'plan/x', 'sha' => 'abcdef1', 'check' => 'php-checks=failure', 'url' => 'https://github.com/o/r/actions/runs/9', 'title' => 'Fix the @thing now'),
		),
		'ci green, no pull request' => array(
			pg_github_line('workflow_run', array('action' => 'completed', 'workflow_run' => array('conclusion' => 'success', 'pull_requests' => array()) + $run), $link),
			array('kind' => 'ci', 'state' => 'green', 'number' => null, 'branch' => 'plan/x', 'sha' => 'abcdef1', 'check' => 'php-checks=success', 'url' => 'https://github.com/o/r/actions/runs/9', 'title' => 'Fix the @thing now'),
		),
		'ci with no address and no title' => array(
			pg_github_line('workflow_run', array('action' => 'completed', 'workflow_run' => array('html_url' => '', 'display_title' => '') + $run), $link),
			array('kind' => 'ci', 'state' => 'red', 'number' => 42, 'branch' => 'plan/x', 'sha' => 'abcdef1', 'check' => 'php-checks=failure', 'url' => '', 'title' => ''),
		),
		'ci of a branch named like a check' => array(
			pg_github_line('workflow_run', array('action' => 'completed', 'workflow_run' => array('head_branch' => 'a=success') + $run), $link),
			array('kind' => 'ci', 'state' => 'red', 'number' => 42, 'branch' => 'a=success', 'sha' => 'abcdef1', 'check' => 'php-checks=failure', 'url' => 'https://github.com/o/r/actions/runs/9', 'title' => 'Fix the @thing now'),
		),
		'pr opened' => array(
			pg_github_line('pull_request', array('action' => 'opened', 'pull_request' => $pull), $link),
			array('kind' => 'pr', 'state' => 'opened', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => 'https://github.com/o/r/pull/7', 'title' => 'Add a thing'),
		),
		'pr reopened' => array(
			pg_github_line('pull_request', array('action' => 'reopened', 'pull_request' => $pull), $link),
			array('kind' => 'pr', 'state' => 'reopened', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => 'https://github.com/o/r/pull/7', 'title' => 'Add a thing'),
		),
		'pr closed' => array(
			pg_github_line('pull_request', array('action' => 'closed', 'pull_request' => $pull), $link),
			array('kind' => 'pr', 'state' => 'closed', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => 'https://github.com/o/r/pull/7', 'title' => 'Add a thing'),
		),
		'pr merged' => array(
			pg_github_line('pull_request', array('action' => 'closed', 'pull_request' => array('merged' => true) + $pull), $link),
			array('kind' => 'pr', 'state' => 'merged', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => 'https://github.com/o/r/pull/7', 'title' => 'Add a thing'),
		),
		'pr with no address' => array(
			pg_github_line('pull_request', array('action' => 'opened', 'pull_request' => array('html_url' => '') + $pull), $link),
			array('kind' => 'pr', 'state' => 'opened', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => '', 'title' => 'Add a thing'),
		),
		'label' => array(
			pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'Ready'), 'pull_request' => $pull), $link),
			array('kind' => 'label', 'state' => 'ready', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => '', 'title' => 'Add a thing'),
		),
		'label named ci' => array(
			pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'ci'), 'pull_request' => $pull), $link),
			array('kind' => 'label', 'state' => 'ci', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => '', 'title' => 'Add a thing'),
		),
		'label named pr, no title' => array(
			pg_github_line('pull_request', array('action' => 'labeled', 'label' => array('name' => 'pr'), 'pull_request' => array('title' => '') + $pull), $link),
			array('kind' => 'label', 'state' => 'pr', 'number' => 7, 'branch' => 'feature', 'sha' => '1234567', 'check' => '', 'url' => '', 'title' => ''),
		),
	);

	foreach ($cases as $name => $case) {
		pg_assert_true($case[0] !== '', $name . ': a line was written');
		pg_assert_same($case[1], pg_github_line_read($case[0]), $name);
	}
}

function test_github_line_read_refuses_typed_text()
{
	foreach (array('', 'hello', '[pr] opened', '[pr] waiting #7 x 1234567 t', '[ci] green', '[ci] blue #3 b 1234567 x=success', '[ready] seven', '[Bad Label] #7 x', 'pr opened #7 b 1234567 t', '[ci] green #0 b 1234567 x=success') as $text) {
		pg_assert_same(null, pg_github_line_read($text), 'typed: ' . $text);
	}

	pg_assert_same(null, pg_github_line_read(array('[pr]')), 'not text');
}

function test_github_pull_url()
{
	pg_assert_same('https://github.com/o/r/pull/7', pg_github_pull_url('o/r', 7), 'pull request address');
	pg_assert_same('', pg_github_pull_url('o/r', 0), 'no number');
	pg_assert_same('', pg_github_pull_url('nope', 7), 'no repository');
}

function test_github_day_counts()
{
	$rows = array(
		array('id' => 1, 'created_at' => 100, 'body' => '[pr] opened #7 feature 1234567 https://github.com/o/r/pull/7 Add a thing'),
		array('id' => 2, 'created_at' => 110, 'body' => 'a person typed this'),
		array('id' => 3, 'created_at' => 120, 'body' => '[ci] red #7 feature 1234567 checks=failure https://github.com/o/r/actions/runs/1 Add a thing'),
		array('id' => 4, 'created_at' => 130, 'body' => '[ci] green #7 feature 1234567 checks=success https://github.com/o/r/actions/runs/2 Add a thing'),
		array('id' => 5, 'created_at' => 140, 'body' => '[ci] green main 89abcde checks=success https://github.com/o/r/actions/runs/3 Merge'),
		array('id' => 6, 'created_at' => 150, 'body' => '[ready] #7 feature 1234567 Add a thing'),
		array('id' => 7, 'created_at' => 160, 'body' => '[pr] merged #7 feature 1234567 https://github.com/o/r/pull/7 Add a thing'),
		array('id' => 8, 'created_at' => 170, 'body' => '[pr] closed #8 other 7654321 https://github.com/o/r/pull/8 Drop it'),
		array('id' => 9, 'created_at' => 180, 'body' => '[pr] reopened #8 other 7654321 https://github.com/o/r/pull/8 Drop it'),
	);

	$day = pg_github_day_counts(array_reverse($rows), 'o/r', 5);

	pg_assert_same(array('opened' => 2, 'merged' => 1, 'closed' => 1), $day['pull_requests'], 'pull requests: a reopened one counts as opened');
	pg_assert_same(array('green' => 2, 'red' => 1), $day['checks'], 'checks');
	pg_assert_same(array(9, 8, 7, 6, 5), array_map(function ($line) { return $line['message_id']; }, $day['lines']), 'the newest five lines, newest first');
	pg_assert_same('https://github.com/o/r/pull/7', $day['lines'][2]['url'], 'a label line links to its pull request');
	pg_assert_same('https://github.com/o/r/actions/runs/3', $day['lines'][4]['url'], 'a check links to its run');
	pg_assert_same(180, $day['lines'][0]['created_at'], 'when');
	pg_assert_same(array('message_id', 'kind', 'state', 'number', 'branch', 'title', 'url', 'created_at'), array_keys($day['lines'][0]), 'a line\'s fields');

	$empty = pg_github_day_counts(array(), 'o/r', 5);
	pg_assert_same(array('opened' => 0, 'merged' => 0, 'closed' => 0), $empty['pull_requests'], 'nothing today');
	pg_assert_same(array(), $empty['lines'], 'no lines');
}

function test_channel_activity_repo_counts_only_the_application_lines_of_today()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_activity_repo.php');
	$source = pg_test_function_source('ws_channel_activity_repo');

	pg_assert_true(strpos($source, 'ws_channel_activity_link_name($viewer, $channel)') !== false, 'only a link the reader may see');
	pg_assert_true(strpos($source, "pg_connect_service_get('github_channel_app')") !== false, 'the application is read');
	pg_assert_true(strpos($source, "sender_kind = 'app' AND sender_id = '\" . \$app_id . \"'") !== false, 'by the application\'s id');
	pg_assert_true(strpos($source, "strtotime('today'") !== false, 'since local midnight');
	pg_assert_true(strpos($source, 'deleted_at = 0') !== false, 'a deleted line counts nothing');
}
