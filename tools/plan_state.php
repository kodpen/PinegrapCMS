<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The state of a plan's steps as GitHub holds it: every PR titled
 * "[plan:<plan>] <steps> — <topic>" gives its steps a state. A draft is
 * held, an open PR ready, a merged one done; a PR closed without a merge
 * gives none. --tasks writes the same states as tasks of an agent channel,
 * so the channel's Tasks tab and Board show the plan.
 *
 * Usage:  php tools/plan_state.php <plan file> [--tasks] [--channel=ID]
 *
 *   <plan file>  docs/memory/plans/<plan>.md, or the plan's name alone
 *   (prints)     one line a step: "<step> <state> #<n> <PR address>"; a step
 *                of the plan file with no PR reads "<step> todo"
 *   --tasks      one task a step in the channel, titled "<plan> <step>":
 *                status todo (no PR), doing (held), waiting (ready) or done,
 *                the PR's address as its description. A missing task is
 *                created; an existing one changes only in what differs. A
 *                step with no PR never changes a task that is there, and a
 *                cancelled task is left alone unless its step is done.
 *
 * Exit:   0 printed (and written); 1 GitHub or the site refused, or gave no
 *         answer; 2 usage or missing settings.
 *
 * GitHub is read through `gh api` (REST and the issue search). The site's
 * settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET and PG_WS_CHANNEL (or --channel) for --tasks.
 * Nothing secret is printed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

require_once(__DIR__ . '/ws_client.php');

define('PLAN_STATE_REPO', 'repos/kodpen/pinegrapcms');

// Pages of 100 read from the search of merged PRs.
define('PLAN_STATE_SEARCH_PAGES', 3);

// The tasks a channel lists in one call (the route's limit).
define('PLAN_STATE_TASK_LIMIT', 250);

/**
 * The usage text.
 *
 * @return string
 */
function plan_state_usage()
{
	return "Usage: php tools/plan_state.php <plan file> [--tasks] [--channel=ID]\n"
		. "Prints each step of the plan that has a PR with its state (held, ready, done); --tasks writes them as tasks of the agent channel.\n";
}

/**
 * The plan's name from its file path or name: the base name without .md.
 * '' when it is not a plain file name.
 *
 * @param string $arg
 * @return string
 */
function plan_state_plan_name($arg)
{
	$name = basename(str_replace('\\', '/', trim((string) $arg)));

	if (substr($name, -3) === '.md') {
		$name = substr($name, 0, -3);
	}

	return preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name) ? $name : '';
}

/**
 * Whether a word is a step id such as PS2, A4b2 or AG13a.
 *
 * @param string $word
 * @return bool
 */
function plan_state_is_step($word)
{
	return (bool) preg_match('/^[A-Z][A-Za-z]*\d+[a-z0-9]*$/', (string) $word);
}

/**
 * The steps a PR title names, when it is a PR of the plan: the words
 * between "[plan:<plan>]" (with or without .md) and the dash before the
 * topic. "PS1-PS3" is the steps between, when both share their letters and
 * end in a number. An empty list for a PR of another plan.
 *
 * @param string $title
 * @param string $plan
 * @return string[]
 */
function plan_state_title_steps($title, $plan)
{
	$title = trim((string) $title);

	if (!preg_match('/^\[plan:\s*([^\]]+?)\s*\]\s*(.*)$/u', $title, $m) || plan_state_plan_name($m[1]) !== $plan) {
		return array();
	}

	// The topic follows an em or en dash, or " - ".
	$head = preg_split('/\s+(?:\x{2014}|\x{2013}|-)\s+|\s*(?:\x{2014}|\x{2013})\s*/u', $m[2], 2);
	$words = preg_split('/[\s,+\/&]+|\band\b/', (string) $head[0], -1, PREG_SPLIT_NO_EMPTY);
	$steps = array();

	foreach ($words as $word) {
		$word = trim($word, " \t.;:()");

		if (preg_match('/^([A-Z][A-Za-z]*)(\d+)-(?:\1)?(\d+)$/', $word, $range) && (int) $range[3] >= (int) $range[2] && (int) $range[3] - (int) $range[2] < 50) {
			for ($i = (int) $range[2]; $i <= (int) $range[3]; $i++) {
				$steps[] = $range[1] . $i;
			}

			continue;
		}

		if (plan_state_is_step($word)) {
			$steps[] = $word;
		}
	}

	return array_values(array_unique($steps));
}

/**
 * The steps a plan file lists: lines "- <step> (" or "- <step>:", bold or
 * not, in the order of the file.
 *
 * @param string $text
 * @return string[]
 */
function plan_state_file_steps($text)
{
	preg_match_all('/^\s*-\s+\**([A-Z][A-Za-z]*\d+[a-z0-9]*)\**\s*[(:]/m', (string) $text, $m);

	return array_values(array_unique($m[1]));
}

/**
 * A PR's state: held (draft), ready (open), done (merged), or '' (closed
 * without a merge).
 *
 * @param array<string,mixed> $pr with state, draft, merged_at
 * @return string
 */
function plan_state_pr_state($pr)
{
	if (!empty($pr['merged_at'])) {
		return 'done';
	}

	if (($pr['state'] ?? '') !== 'open') {
		return '';
	}

	return !empty($pr['draft']) ? 'held' : 'ready';
}

/**
 * Each step's state from the PRs: done before ready before held, and of
 * PRs in the same state the newest (highest number). Keyed by step in the
 * order of the plan file's steps, then the others by name.
 *
 * @param array<int,array<string,mixed>> $prs number, title, state, draft, merged_at, html_url
 * @param string $plan
 * @param string[] $file_steps
 * @return array<string,array{step: string, state: string, number: int, url: string}>
 */
function plan_state_steps($prs, $plan, $file_steps = array())
{
	$rank = array('held' => 1, 'ready' => 2, 'done' => 3);
	$found = array();

	foreach ((array) $prs as $pr) {
		$state = plan_state_pr_state($pr);

		if ($state === '') {
			continue;
		}

		$number = (int) ($pr['number'] ?? 0);

		foreach (plan_state_title_steps((string) ($pr['title'] ?? ''), $plan) as $step) {
			if (isset($found[$step]) && ($rank[$found[$step]['state']] > $rank[$state] || ($found[$step]['state'] === $state && $found[$step]['number'] > $number))) {
				continue;
			}

			$found[$step] = array('step' => $step, 'state' => $state, 'number' => $number, 'url' => (string) ($pr['html_url'] ?? ''));
		}
	}

	$out = array();

	foreach ($file_steps as $step) {
		$out[$step] = isset($found[$step]) ? $found[$step] : array('step' => $step, 'state' => 'todo', 'number' => 0, 'url' => '');
		unset($found[$step]);
	}

	ksort($found, SORT_NATURAL);

	return $out + $found;
}

/**
 * The printed line of a step.
 *
 * @param array{step: string, state: string, number: int, url: string} $step
 * @return string
 */
function plan_state_line($step)
{
	return $step['state'] === 'todo' ? $step['step'] . ' todo' : $step['step'] . ' ' . $step['state'] . ' #' . $step['number'] . ($step['url'] !== '' ? ' ' . $step['url'] : '');
}

/**
 * The task status of a step's state.
 *
 * @param string $state
 * @return string
 */
function plan_state_task_status($state)
{
	$map = array('todo' => 'todo', 'held' => 'doing', 'ready' => 'waiting', 'done' => 'done');

	return isset($map[$state]) ? $map[$state] : 'todo';
}

/**
 * What --tasks writes: a create for each step with no task, and an update
 * with only the fields that differ for each task that is not as GitHub
 * says. A task is found by its exact title "<plan> <step>"; of two, the
 * lower id. A step with no PR only creates; a cancelled task changes only
 * when its step is done.
 *
 * @param array<string,array{step: string, state: string, number: int, url: string}> $steps
 * @param array<int,array<string,mixed>> $tasks id, title, status, description
 * @param string $plan
 * @return array<int,array{action: string, step: string, task_id: int, fields: array<string,string>}>
 */
function plan_state_task_changes($steps, $tasks, $plan)
{
	$by_title = array();

	foreach ((array) $tasks as $task) {
		$title = (string) ($task['title'] ?? '');
		$id = (int) ($task['id'] ?? 0);

		if ($id > 0 && (!isset($by_title[$title]) || (int) $by_title[$title]['id'] > $id)) {
			$by_title[$title] = $task;
		}
	}

	$out = array();

	foreach ($steps as $step) {
		$title = $plan . ' ' . $step['step'];
		$want = array('status' => plan_state_task_status($step['state']));

		if ($step['url'] !== '') {
			$want['description'] = $step['url'];
		}

		if (!isset($by_title[$title])) {
			$out[] = array('action' => 'create', 'step' => $step['step'], 'task_id' => 0, 'fields' => array('title' => $title) + $want);
			continue;
		}

		$task = $by_title[$title];

		if ($step['state'] === 'todo' || ((string) ($task['status'] ?? '') === 'cancelled' && $step['state'] !== 'done')) {
			continue;
		}

		$fields = array();

		foreach ($want as $name => $value) {
			if ((string) ($task[$name] ?? '') !== $value) {
				$fields[$name] = $value;
			}
		}

		if ($fields) {
			$out[] = array('action' => 'update', 'step' => $step['step'], 'task_id' => (int) $task['id'], 'fields' => $fields);
		}
	}

	return $out;
}

/**
 * Runs a command (a list of arguments, without a shell); its stdout into
 * $out, stderr to ours.
 *
 * @param string[] $args
 * @param string|null $out
 * @return int the exit code
 */
function plan_state_run(array $args, &$out = null)
{
	$process = proc_open(implode(' ', array_map('escapeshellarg', $args)), array(1 => array('pipe', 'w'), 2 => STDERR), $pipes);

	if (!is_resource($process)) {
		$out = '';
		return -1;
	}

	$out = (string) stream_get_contents($pipes[1]);
	fclose($pipes[1]);

	return proc_close($process);
}

/**
 * A GET through `gh api`, decoded; null when it fails.
 *
 * @param string $path
 * @return array<mixed>|null
 */
function plan_state_gh($path)
{
	if (plan_state_run(array('gh', 'api', '-X', 'GET', $path), $out) !== 0) {
		return null;
	}

	$data = json_decode((string) $out, true);

	return is_array($data) ? $data : null;
}

/**
 * The plan's PRs from GitHub: every open PR, and the merged ones the issue
 * search finds by the plan's name in the title. null when GitHub fails.
 *
 * @param string $plan
 * @return array<int,array<string,mixed>>|null
 */
function plan_state_fetch_prs($plan)
{
	$open = plan_state_gh(PLAN_STATE_REPO . '/pulls?state=open&per_page=100');

	if ($open === null) {
		return null;
	}

	$prs = array();

	foreach ($open as $pr) {
		$prs[] = array(
			'number'    => (int) ($pr['number'] ?? 0),
			'title'     => (string) ($pr['title'] ?? ''),
			'state'     => 'open',
			'draft'     => !empty($pr['draft']),
			'merged_at' => null,
			'html_url'  => (string) ($pr['html_url'] ?? ''),
		);
	}

	$query = rawurlencode('repo:kodpen/pinegrapcms is:pr is:merged in:title "plan:' . $plan . '"');

	for ($page = 1; $page <= PLAN_STATE_SEARCH_PAGES; $page++) {
		$found = plan_state_gh('search/issues?q=' . $query . '&per_page=100&page=' . $page);

		if ($found === null) {
			return null;
		}

		$items = isset($found['items']) && is_array($found['items']) ? $found['items'] : array();

		foreach ($items as $item) {
			$prs[] = array(
				'number'    => (int) ($item['number'] ?? 0),
				'title'     => (string) ($item['title'] ?? ''),
				'state'     => 'closed',
				'draft'     => false,
				'merged_at' => $item['pull_request']['merged_at'] ?? null,
				'html_url'  => (string) ($item['html_url'] ?? ''),
			);
		}

		if (count($items) < 100) {
			break;
		}
	}

	return $prs;
}

/**
 * Sends a write once more after a call without an answer, with the same
 * Idempotency-Key, so it is written at most once.
 *
 * @param callable $send
 * @return array{status: int, headers: string, body: string, error: string}
 */
function plan_state_send($send)
{
	$answer = $send();

	if (in_array((int) $answer['status'], array(0, 502, 504), true)) {
		$answer = $send();
	}

	return $answer;
}

/**
 * Writes the steps as tasks of the channel.
 *
 * @param array<string,array{step: string, state: string, number: int, url: string}> $steps
 * @param string $plan
 * @param array{site: string, key: string, secret: string, channel: int} $settings
 * @return int exit code
 */
function plan_state_write_tasks($steps, $plan, $settings)
{
	$answer = ws_client_request('GET', $settings['site'] . '/workspace/tasks?scope=channel&status=all&agent_channels=include&channel_id=' . $settings['channel'] . '&limit=' . PLAN_STATE_TASK_LIMIT, $settings);

	if ($answer['status'] !== 200) {
		fwrite(STDERR, 'plan_state: reading the tasks: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$json = json_decode($answer['body'], true);
	$tasks = is_array($json) && isset($json['data']) && is_array($json['data']) ? $json['data'] : array();
	$failed = false;

	foreach (plan_state_task_changes($steps, $tasks, $plan) as $change) {
		$headers = ws_client_idempotency_headers(array('idempotency-key' => 'plan-state-' . bin2hex(random_bytes(12))));
		$body = $change['fields'];
		$url = $settings['site'] . '/workspace/tasks';

		if ($change['action'] === 'create') {
			$body = array('channel_id' => $settings['channel']) + $body;
		} else {
			$url .= '/' . $change['task_id'];
		}

		$answer = plan_state_send(function () use ($url, $settings, $body, $headers) {
			return ws_client_request('POST', $url, $settings, $body, $headers);
		});

		if ($answer['status'] < 200 || $answer['status'] > 299) {
			fwrite(STDERR, 'plan_state: ' . $change['action'] . ' ' . $plan . ' ' . $change['step'] . ': ' . ws_client_failure($answer) . "\n");
			$failed = true;
			continue;
		}

		fwrite(STDOUT, 'task ' . $change['action'] . 'd: ' . $plan . ' ' . $change['step'] . ' (' . implode(', ', array_keys($change['fields'])) . ")\n");
	}

	return $failed ? WS_CLIENT_EXIT_HTTP : WS_CLIENT_EXIT_OK;
}

function plan_state_main($argv)
{
	list($options, $rest) = ws_client_options($argv);
	$plan = count($rest) === 1 ? plan_state_plan_name($rest[0]) : '';

	if ($plan === '') {
		fwrite(STDERR, plan_state_usage());
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = null;

	if (!empty($options['tasks'])) {
		$settings = ws_client_settings($options, getenv());

		if ($settings['missing']) {
			fwrite(STDERR, 'plan_state: not set: ' . implode(', ', $settings['missing']) . "\n");
			return WS_CLIENT_EXIT_USAGE;
		}
	}

	$file = is_file($rest[0]) ? $rest[0] : dirname(__DIR__) . '/docs/memory/plans/' . $plan . '.md';
	$file_steps = is_file($file) ? plan_state_file_steps((string) file_get_contents($file)) : array();
	$prs = plan_state_fetch_prs($plan);

	if ($prs === null) {
		fwrite(STDERR, "plan_state: gh api failed\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	$steps = plan_state_steps($prs, $plan, $file_steps);

	foreach ($steps as $step) {
		fwrite(STDOUT, plan_state_line($step) . "\n");
	}

	return $settings === null ? WS_CLIENT_EXIT_OK : plan_state_write_tasks($steps, $plan, $settings);
}

if (!defined('PG_TEST_RUNNER')) {
	exit(plan_state_main(array_slice($argv, 1)));
}
