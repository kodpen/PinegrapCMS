<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Builds and lands merge trains: the open PRs to development that are ready
 * are merged onto one merge-train/* branch, one --no-ff merge commit per PR
 * titled "<PR title> (#n)", tested once by CI and landed on development by
 * one fast-forward push.
 *
 * Usage:  php tools/merge_train.php --start [--stack]
 *             Refuses while an open merge-train/* PR exists (the lock);
 *             with --stack it starts on the tip of the newest open train
 *             instead. Creates merge-train/<yyyymmdd-hhmm> from
 *             origin/development and turns rerere on.
 *         php tools/merge_train.php --list
 *             The PRs that may ride a train (CI green on their head, and the
 *             ready label or no push for an hour), with their product lines,
 *             in train order: the docs-only PRs that land alone, the train,
 *             and the PRs left for a later train.
 *         php tools/merge_train.php --build [<n> ...] [--no-push]
 *             On the current merge-train/* branch: fetches refs/pull/<n>/head
 *             of each PR (the --list train when none is named), merges it
 *             with --no-ff --no-commit, settles the mechanical conflicts
 *             (language files, the generated memory indexes and module
 *             list, .min.js bundles), writes the PR's changelog.txt
 *             lines from the Changelog section of its body and commits.
 *             A PR already in the branch is skipped, so after a stop the
 *             same command goes on. It stops, with the merge left open, on a
 *             conflict it cannot settle or a [SCHEME] item, which is worded
 *             by hand. Then pushes the branch and opens or retitles its
 *             draft PR, unless --no-push.
 *         php tools/merge_train.php --land
 *             Pushes the current merge-train/* branch to development as a
 *             fast-forward, once it is pushed, origin/development is its
 *             ancestor and CI is green on its tip.
 * Exit:   0 on success; 1 when a check refused or a step stopped.
 *
 * GitHub is read and written through `gh api` (REST): gh pr, gh issue,
 * GraphQL and the search API are blocked in cloud sessions. The tool pushes
 * only its own merge-train/* branch and, in --land, development.
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

define('MERGE_TRAIN_REPO', 'repos/kodpen/pinegrapcms');
define('MERGE_TRAIN_IDENTITY_NAME', 'Erdal Güral');
define('MERGE_TRAIN_IDENTITY_EMAIL', 'erdaltyy@gmail.com');
define('MERGE_TRAIN_SIZE_LIMIT', 1000);
define('MERGE_TRAIN_MAX_PRS', 12);
define('MERGE_TRAIN_QUIET_SECONDS', 3600);
define('MERGE_TRAIN_FIX_LINE', '[FIX] Miscellaneous bug fixes and new features.');
define('MERGE_TRAIN_SECURITY_LINE', '[SECURITY] Implemented various security fixes.');
define('MERGE_TRAIN_MODULE_LIST', 'docs/memory/core/fn-modules.md');

// The product lines of a change, path => changed lines: lines in files under
// pinegrap/, minified bundles and the language files under includes/local/
// not counted. The same rule as check_pr_product_lines() in
// tools/check_pr.php, which the tests hold this to.
function merge_train_product_lines(array $files)
{
	$lines = 0;

	foreach ($files as $path => $changed) {
		$path = (string) $path;

		if ((strpos($path, 'pinegrap/') !== 0) || (strpos($path, 'pinegrap/includes/local/') === 0) || (substr($path, -7) === '.min.js')) {
			continue;
		}

		$lines += (int) $changed;
	}

	return $lines;
}

// Whether a PR changes only docs/, CLAUDE.md or .github/ and so lands alone
// as soon as it is green.
function merge_train_docs_only(array $paths)
{
	if (!$paths) {
		return false;
	}

	foreach ($paths as $path) {
		$path = (string) $path;

		if (($path !== 'CLAUDE.md') && (strpos($path, 'docs/') !== 0) && (strpos($path, '.github/') !== 0)) {
			return false;
		}
	}

	return true;
}

// Whether the check runs of a commit, as the check-runs endpoint lists them,
// all finished without a failure; no run at all is not green.
function merge_train_checks_green(array $runs)
{
	if (!$runs) {
		return false;
	}

	foreach ($runs as $run) {
		if (!isset($run['status']) || ($run['status'] !== 'completed')) {
			return false;
		}

		if (!in_array(isset($run['conclusion']) ? $run['conclusion'] : '', array('success', 'neutral', 'skipped'), true)) {
			return false;
		}
	}

	return true;
}

// Whether a green PR may ride a train: it carries the ready label, or its
// head was last pushed an hour ago or more.
function merge_train_eligible(array $labels, $green, $pushed_at, $now)
{
	if (!$green) {
		return false;
	}

	return in_array('ready', $labels, true) || (($now - $pushed_at) >= MERGE_TRAIN_QUIET_SECONDS);
}

// The PR numbers a train PR's title names ("Merge train <time>: #a #b").
function merge_train_numbers_in_title($title)
{
	preg_match_all('/#(\d+)/', (string) $title, $matches);

	return array_map('intval', $matches[1]);
}

function merge_train_branch_name($time)
{
	return 'merge-train/' . gmdate('Ymd-Hi', $time);
}

// The train order of eligible PRs, each array('number', 'product_lines',
// 'docs_only'): 'alone' the docs-only PRs, 'train' the others smaller first
// up to MERGE_TRAIN_MAX_PRS within the size limit, 'later' the rest. A PR
// over the limit rides only a train of its own, so a red train is easy to
// blame: it is the train when no PR within the limit waits.
function merge_train_order(array $prs)
{
	$alone = array();
	$small = array();
	$large = array();

	foreach ($prs as $pr) {
		if ($pr['docs_only']) {
			$alone[] = $pr;
		} elseif ($pr['product_lines'] > MERGE_TRAIN_SIZE_LIMIT) {
			$large[] = $pr;
		} else {
			$small[] = $pr;
		}
	}

	$by_size = function ($a, $b) {
		if ($a['product_lines'] !== $b['product_lines']) {
			return ($a['product_lines'] < $b['product_lines']) ? -1 : 1;
		}

		return ($a['number'] < $b['number']) ? -1 : (($a['number'] > $b['number']) ? 1 : 0);
	};
	$numbers = function (array $list) {
		$out = array();

		foreach ($list as $pr) {
			$out[] = (int) $pr['number'];
		}

		return $out;
	};

	usort($alone, $by_size);
	usort($small, $by_size);
	usort($large, $by_size);

	if ($small) {
		$train = array_slice($small, 0, MERGE_TRAIN_MAX_PRS);
		$later = array_merge(array_slice($small, MERGE_TRAIN_MAX_PRS), $large);
	} else {
		$train = array_slice($large, 0, 1);
		$later = array_slice($large, 1);
	}

	return array('alone' => $numbers($alone), 'train' => $numbers($train), 'later' => $numbers($later));
}

// The text under the "Changelog" heading of a PR body, up to the next
// heading, trimmed; '' when the body has none.
function merge_train_changelog_section($body)
{
	$lines = explode("\n", str_replace("\r\n", "\n", (string) $body));
	$section = null;

	foreach ($lines as $line) {
		if (preg_match('/^#{1,6}\s/', $line)) {
			if ($section !== null) {
				break;
			}

			if (preg_match('/^#{2,6}\s*Changelog\s*$/i', rtrim($line))) {
				$section = array();
			}

			continue;
		}

		if ($section !== null) {
			$section[] = $line;
		}
	}

	return ($section === null) ? '' : trim(implode("\n", $section));
}

// What a Changelog section asks of changelog.txt: 'new' the [NEW] Added …
// lines, 'fix' and 'security' whether it names the [FIX] or [SECURITY]
// line, 'scheme' the item it names for the [SCHEME] line ('' for none).
// "none" and a mention of another PR's [NEW] line add no line.
function merge_train_changelog_entry($section)
{
	$section = (string) $section;
	$entry = array('new' => array(), 'fix' => false, 'security' => false, 'scheme' => '');

	$entry['fix'] = (stripos($section, '[FIX]') !== false);
	$entry['security'] = (stripos($section, '[SECURITY]') !== false);

	if (preg_match('/^none\b/i', $section)) {
		return $entry;
	}

	if (preg_match_all('/\[NEW\][ \t]+(Added\b[^`\r\n]*)/', $section, $matches)) {
		foreach ($matches[1] as $text) {
			$line = '[NEW] ' . rtrim($text);

			if (!in_array($line, $entry['new'], true)) {
				$entry['new'][] = $line;
			}
		}
	}

	if (preg_match('/\[SCHEME\]`?(?:[ \t]+(?:line|item)\b)?[ \t]*[,:]?[ \t]*([^\r\n]*)/i', $section, $match) && preg_match('/[A-Za-z0-9]/', $match[1])) {
		$entry['scheme'] = trim($match[1]);
	}

	return $entry;
}

// changelog.txt with an entry's [NEW], [FIX] and [SECURITY] lines written
// into the open version (the first one, newest on top): [NEW] lines after
// the version's own, then [FIX], [SECURITY], [SCHEME]; a line already there
// is not added again. false when the file names no version. The [SCHEME]
// item is not written: it widens the version's one sentence by hand.
function merge_train_changelog_apply($contents, array $entry)
{
	$contents = (string) $contents;
	$eol = (strpos($contents, "\r\n") !== false) ? "\r\n" : "\n";
	$lines = explode("\n", str_replace("\r\n", "\n", $contents));
	$version = null;

	for ($i = 0; $i < count($lines) - 1; $i++) {
		if (preg_match('/^={10,}\s*$/', $lines[$i]) && preg_match('/^\d{4}\.\d{1,2}\.\d+/', $lines[$i + 1])) {
			$version = $i + 1;
			break;
		}
	}

	if ($version === null) {
		return false;
	}

	$end = $version + 1;

	while (($end < count($lines)) && !preg_match('/^={10,}\s*$/', $lines[$end]) && ($lines[$end] !== '')) {
		$end++;
	}

	$tags = array('[NEW]' => array(), '[FIX]' => array(), '[SECURITY]' => array(), '[SCHEME]' => array());
	$other = array();

	foreach (array_slice($lines, $version + 1, $end - $version - 1) as $line) {
		$tag = preg_match('/^(\[[A-Z]+\])/', $line, $match) ? $match[1] : '';

		if (isset($tags[$tag])) {
			$tags[$tag][] = $line;
		} else {
			$other[] = $line;
		}
	}

	$changed = false;

	foreach ($entry['new'] as $line) {
		if (!in_array($line, $tags['[NEW]'], true)) {
			$tags['[NEW]'][] = $line;
			$changed = true;
		}
	}

	if (!empty($entry['fix']) && !$tags['[FIX]']) {
		$tags['[FIX]'][] = MERGE_TRAIN_FIX_LINE;
		$changed = true;
	}

	if (!empty($entry['security']) && !$tags['[SECURITY]']) {
		$tags['[SECURITY]'][] = MERGE_TRAIN_SECURITY_LINE;
		$changed = true;
	}

	if (!$changed) {
		return $contents;
	}

	$body = array_merge($tags['[NEW]'], $tags['[FIX]'], $tags['[SECURITY]'], $tags['[SCHEME]'], $other);
	array_splice($lines, $version + 1, $end - $version - 1, $body);

	return implode($eol, $lines);
}

// The test runner loads this file for the functions above.
if (defined('PG_TEST_RUNNER')) {
	return;
}

$repository = dirname(__DIR__);
chdir($repository);

// Runs a command (a list of arguments, without a shell) and returns its exit
// code; stdout goes to $out, stderr to ours. On Windows the command starts
// without cmd.exe, which would expand the % signs of an argument.
function merge_train_run(array $args, &$out = null)
{
	$command = implode(' ', array_map('escapeshellarg', $args));
	$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => STDERR), $pipes, null, null, array('bypass_shell' => true));

	if (!is_resource($process)) {
		$out = '';

		return -1;
	}

	$out = (string) stream_get_contents($pipes[1]);
	fclose($pipes[1]);

	return proc_close($process);
}

// git's stdout trimmed, or false when it fails.
function merge_train_git(array $args)
{
	return (merge_train_run(array_merge(array('git'), $args), $out) === 0) ? trim($out) : false;
}

// Runs git with its output shown; true when it succeeds.
function merge_train_git_shown(array $args)
{
	$code = merge_train_run(array_merge(array('git'), $args), $out);
	fwrite(STDOUT, $out);

	return $code === 0;
}

// A REST call through `gh api`, decoded; exits when it fails.
function merge_train_gh($method, $path, array $fields = array())
{
	$args = array('gh', 'api', '-X', $method, ($path === '') ? MERGE_TRAIN_REPO : MERGE_TRAIN_REPO . '/' . $path);

	foreach ($fields as $name => $value) {
		$args[] = is_bool($value) ? '-F' : '-f';
		$args[] = $name . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
	}

	if (merge_train_run($args, $out) !== 0) {
		merge_train_fail("gh api $method $path failed");
	}

	$data = json_decode($out, true);

	return is_array($data) ? $data : array();
}

// Every page of a list endpoint.
function merge_train_gh_all($path)
{
	$items = array();
	$separator = (strpos($path, '?') === false) ? '?' : '&';

	for ($page = 1; $page <= 30; $page++) {
		$batch = merge_train_gh('GET', $path . $separator . 'per_page=100&page=' . $page);
		$items = array_merge($items, $batch);

		if (count($batch) < 100) {
			break;
		}
	}

	return $items;
}

function merge_train_fail($message)
{
	fwrite(STDERR, $message . "\n");
	exit(1);
}

// Every commit of a train is written as Erdal Güral (CLAUDE.md, Fixed rules 2).
function merge_train_require_identity()
{
	$name = merge_train_git(array('config', 'user.name'));
	$email = merge_train_git(array('config', 'user.email'));

	if (($name !== MERGE_TRAIN_IDENTITY_NAME) || ($email !== MERGE_TRAIN_IDENTITY_EMAIL)) {
		merge_train_fail('git user.name/user.email must be ' . MERGE_TRAIN_IDENTITY_NAME . ' <' . MERGE_TRAIN_IDENTITY_EMAIL . '>');
	}
}

// The current branch, which must be a merge train with a clean tree and no
// merge in progress.
function merge_train_require_train_branch()
{
	$branch = merge_train_git(array('symbolic-ref', '--short', 'HEAD'));

	if (($branch === false) || (strpos($branch, 'merge-train/') !== 0)) {
		merge_train_fail('Check out a merge-train/* branch first (php tools/merge_train.php --start).');
	}

	if (merge_train_git(array('rev-parse', '-q', '--verify', 'MERGE_HEAD')) !== false) {
		merge_train_fail('A merge is in progress: settle it and commit, or git merge --abort, then run the command again.');
	}

	if (merge_train_git(array('status', '--porcelain', '--untracked-files=no')) !== '') {
		merge_train_fail('The working tree has changes; commit or stash them first.');
	}

	return $branch;
}

// The open PRs to development whose head is a merge train.
function merge_train_open_trains(array $open)
{
	$trains = array();

	foreach ($open as $pr) {
		if (strpos($pr['head']['ref'], 'merge-train/') === 0) {
			$trains[] = $pr;
		}
	}

	return $trains;
}

// The eligible PRs with their product lines, as merge_train_order() takes
// them, plus 'title' and 'why' for the list.
function merge_train_candidates()
{
	$open = merge_train_gh_all('pulls?state=open&base=development');
	$in_trains = array();

	// A train's title may not name its PRs; its merge commits do.
	foreach (merge_train_open_trains($open) as $train) {
		$in_trains = array_merge($in_trains, merge_train_numbers_in_title($train['title']));

		foreach (merge_train_gh_all('pulls/' . $train['number'] . '/commits') as $commit) {
			$subject = strtok($commit['commit']['message'], "\n");

			if (preg_match('/\(#(\d+)\)$/', rtrim((string) $subject), $match)) {
				$in_trains[] = (int) $match[1];
			}
		}
	}

	$candidates = array();
	$now = time();

	foreach ($open as $pr) {
		if (!empty($pr['draft']) || (strpos($pr['head']['ref'], 'merge-train/') === 0) || in_array((int) $pr['number'], $in_trains, true)) {
			continue;
		}

		$runs = merge_train_gh('GET', 'commits/' . $pr['head']['sha'] . '/check-runs?per_page=100');
		$runs = isset($runs['check_runs']) ? $runs['check_runs'] : array();
		$labels = array();

		foreach ($pr['labels'] as $label) {
			$labels[] = $label['name'];
		}

		// The first check run on the head starts when it is pushed (or marked
		// ready), unlike the commit date, which a rebase or an old commit
		// keeps from earlier.
		$pushed_at = $now;

		foreach ($runs as $run) {
			if (!empty($run['started_at'])) {
				$pushed_at = min($pushed_at, strtotime($run['started_at']));
			}
		}

		if (!merge_train_eligible($labels, merge_train_checks_green($runs), $pushed_at, $now)) {
			continue;
		}

		$files = array();

		foreach (merge_train_gh_all('pulls/' . $pr['number'] . '/files') as $file) {
			$files[$file['filename']] = (int) $file['additions'] + (int) $file['deletions'];
		}

		$candidates[] = array(
			'number' => (int) $pr['number'],
			'product_lines' => merge_train_product_lines($files),
			'docs_only' => merge_train_docs_only(array_keys($files)),
			'title' => $pr['title'],
			'why' => in_array('ready', $labels, true) ? 'ready' : 'quiet',
		);
	}

	return $candidates;
}

function merge_train_start(array $options)
{
	merge_train_require_identity();

	$trains = merge_train_open_trains(merge_train_gh_all('pulls?state=open&base=development'));

	if ($trains && !in_array('--stack', $options, true)) {
		$names = array();

		foreach ($trains as $train) {
			$names[] = '#' . $train['number'] . ' ' . $train['head']['ref'];
		}

		merge_train_fail('An open merge train holds the lock: ' . implode(', ', $names) . '. Land it first, or start on its tip with --stack.');
	}

	if ($trains) {
		usort($trains, function ($a, $b) {
			return $b['number'] - $a['number'];
		});
		$ref = $trains[0]['head']['ref'];
	} else {
		$ref = 'development';
	}

	if (!merge_train_git_shown(array('fetch', 'origin', $ref))) {
		merge_train_fail("git fetch origin $ref failed");
	}

	$base = merge_train_git(array('rev-parse', 'origin/' . $ref));
	$branch = merge_train_branch_name(time());

	if (($base === false) || !merge_train_git_shown(array('checkout', '--no-track', '-b', $branch, $base))) {
		merge_train_fail("Could not create $branch from origin/$ref");
	}

	merge_train_git(array('config', 'rerere.enabled', 'true'));
	// --build names in the train PR's title only the PRs merged after this point.
	merge_train_git(array('config', 'branch.' . $branch . '.mergeTrainBase', $base));
	fwrite(STDOUT, "$branch started on origin/$ref ($base).\n");
}

function merge_train_list()
{
	$candidates = merge_train_candidates();
	$by_number = array();

	foreach ($candidates as $pr) {
		$by_number[$pr['number']] = $pr;
	}

	$order = merge_train_order($candidates);
	$headings = array('alone' => 'Land alone (docs, CLAUDE.md, .github only)', 'train' => 'Train', 'later' => 'A later train');

	foreach ($headings as $group => $heading) {
		fwrite(STDOUT, $heading . ":\n");

		foreach ($order[$group] as $number) {
			$pr = $by_number[$number];
			fwrite(STDOUT, sprintf("  #%d  %5d product lines  %-5s  %s\n", $number, $pr['product_lines'], $pr['why'], $pr['title']));
		}

		if (!$order[$group]) {
			fwrite(STDOUT, "  none\n");
		}
	}
}

// The conflicted paths of the merge in progress.
function merge_train_unmerged()
{
	$out = merge_train_git(array('-c', 'core.quotePath=false', 'diff', '--name-only', '--diff-filter=U'));

	return ($out === false || $out === '') ? array() : explode("\n", $out);
}

// Stages a path once no conflict marker is left in it.
function merge_train_stage_if_clean($path)
{
	$contents = is_file($path) ? (string) file_get_contents($path) : '';

	if (!preg_match('/^(<<<<<<<|>>>>>>>)( |$)/m', $contents)) {
		merge_train_git(array('add', '--', $path));
	}
}

// Settles the mechanical conflicts of the merge in progress and returns the
// paths still in conflict.
function merge_train_settle()
{
	$unmerged = merge_train_unmerged();

	foreach ($unmerged as $path) {
		if (strpos($path, 'pinegrap/includes/local/') === 0) {
			merge_train_run(array(PHP_BINARY, 'tools/tr_resolve.php', '--development=ours'), $out);
			fwrite(STDOUT, $out);
			break;
		}
	}

	$unmerged = merge_train_unmerged();
	$readmes = array();
	$bundles = array();
	$rest = array();

	foreach ($unmerged as $path) {
		// The memory indexes and the module list are all written by memory_index.php.
		if (((strpos($path, 'docs/memory/') === 0) && (basename($path) === 'README.md')) || ($path === MERGE_TRAIN_MODULE_LIST)) {
			$readmes[] = $path;
		} elseif (substr($path, -7) === '.min.js') {
			$bundles[] = $path;
		} else {
			$rest[] = $path;
		}
	}

	// The generators read the merged sources, so they run only when those
	// are settled: a memory file or a script still in conflict is left too.
	$memory_open = false;
	$script_open = false;

	foreach ($rest as $path) {
		$memory_open = $memory_open || (strpos($path, 'docs/memory/') === 0);
		$script_open = $script_open || (substr($path, -3) === '.js');
	}

	if ($readmes && !$memory_open) {
		merge_train_run(array(PHP_BINARY, 'tools/memory_index.php'), $out);
		fwrite(STDOUT, $out);

		foreach ($readmes as $path) {
			merge_train_stage_if_clean($path);
		}
	}

	if ($bundles && !$script_open) {
		merge_train_run(array(PHP_BINARY, 'tools/build_js.php'), $out);
		fwrite(STDOUT, $out);

		foreach ($bundles as $path) {
			merge_train_stage_if_clean($path);
		}
	}

	return merge_train_unmerged();
}

// The PRs a train branch carries since its start, from its merge commits'
// "(#n)" subjects.
function merge_train_carried($branch)
{
	$base = merge_train_git(array('config', 'branch.' . $branch . '.mergeTrainBase'));
	$log = merge_train_git(array('log', '--first-parent', '--format=%s', (($base !== false) && ($base !== '')) ? $base . '..HEAD' : 'origin/development..HEAD'));
	$numbers = array();

	foreach (array_reverse(explode("\n", (string) $log)) as $subject) {
		if (preg_match('/\(#(\d+)\)$/', $subject, $match)) {
			$numbers[] = (int) $match[1];
		}
	}

	return $numbers;
}

function merge_train_build(array $numbers, array $options)
{
	merge_train_require_identity();
	$branch = merge_train_require_train_branch();

	if (!$numbers) {
		$order = merge_train_order(merge_train_candidates());
		$numbers = $order['train'];

		if (!$numbers) {
			fwrite(STDOUT, "No PR is ready for a train.\n");

			return;
		}
	}

	foreach ($numbers as $index => $number) {
		$pr = merge_train_gh('GET', 'pulls/' . $number);
		$message = $pr['title'] . ' (#' . $number . ')';
		$left = implode(' ', array_slice($numbers, $index + 1));

		if (($pr['state'] !== 'open') || !empty($pr['draft'])) {
			fwrite(STDOUT, "#$number is not open and ready; left out.\n");
			continue;
		}

		if (!merge_train_git_shown(array('fetch', 'origin', 'refs/pull/' . $number . '/head'))) {
			merge_train_fail("git fetch of #$number failed");
		}

		$head = merge_train_git(array('rev-parse', 'FETCH_HEAD'));

		if ($head !== $pr['head']['sha']) {
			merge_train_fail("#$number moved while it was read ($head, not {$pr['head']['sha']}); run the command again.");
		}

		if (merge_train_run(array('git', 'merge-base', '--is-ancestor', $head, 'HEAD')) === 0) {
			fwrite(STDOUT, "#$number is already in the train.\n");
			continue;
		}

		fwrite(STDOUT, "Merging #$number {$pr['title']}\n");

		if (!merge_train_git_shown(array('merge', '--no-ff', '--no-commit', $head))) {
			$open = merge_train_settle();

			if ($open) {
				fwrite(STDOUT, "#$number conflicts in:\n  " . implode("\n  ", $open) . "\n"
					. "Settle them and commit with: git commit -m " . escapeshellarg($message) . "\n"
					. "or take the PR out with: git merge --abort\n"
					. "then go on with: php tools/merge_train.php --build $left\n");
				exit(1);
			}
		}

		$entry = merge_train_changelog_entry(merge_train_changelog_section(isset($pr['body']) ? $pr['body'] : ''));
		$changelog = 'pinegrap/changelog.txt';
		$contents = (string) file_get_contents($changelog);
		$written = merge_train_changelog_apply($contents, $entry);

		if ($written === false) {
			merge_train_fail("$changelog names no version; the merge of #$number is left open.");
		}

		if ($written !== $contents) {
			file_put_contents($changelog, $written);
			merge_train_git(array('add', '--', $changelog));
		}

		if ($entry['scheme'] !== '') {
			fwrite(STDOUT, "#$number names a [SCHEME] item: {$entry['scheme']}\n"
				. "Widen the open version's [SCHEME] sentence in $changelog (release/upgrade-changelog-rules.md), then:\n"
				. "  git add $changelog && git commit -m " . escapeshellarg($message) . "\n"
				. "  php tools/merge_train.php --build $left\n");
			exit(1);
		}

		if (!merge_train_git_shown(array('commit', '-m', $message))) {
			merge_train_fail("The merge commit of #$number failed; the merge is left open.");
		}
	}

	$carried = merge_train_carried($branch);

	if (in_array('--no-push', $options, true) || !$carried) {
		fwrite(STDOUT, "$branch carries: #" . implode(' #', $carried) . "\n");

		return;
	}

	if (!merge_train_git_shown(array('push', '-u', 'origin', $branch))) {
		merge_train_fail("git push of $branch failed");
	}

	$title = 'Merge train ' . substr($branch, strlen('merge-train/')) . ': #' . implode(' #', $carried);
	$existing = merge_train_gh('GET', 'pulls?state=open&head=kodpen:' . $branch);

	if ($existing) {
		merge_train_gh('PATCH', 'pulls/' . $existing[0]['number'], array('title' => $title));
		fwrite(STDOUT, "Train PR #{$existing[0]['number']}: $title\n");
	} else {
		$created = merge_train_gh('POST', 'pulls', array('title' => $title, 'head' => $branch, 'base' => 'development', 'draft' => true, 'body' => 'One merge commit per PR: ' . '#' . implode(', #', $carried) . '.'));
		fwrite(STDOUT, "Train PR #{$created['number']}: $title\n");
	}
}

function merge_train_land()
{
	merge_train_require_identity();
	$branch = merge_train_require_train_branch();

	if (!merge_train_git_shown(array('fetch', 'origin', 'development', $branch))) {
		merge_train_fail('git fetch failed');
	}

	$head = merge_train_git(array('rev-parse', 'HEAD'));

	if ($head !== merge_train_git(array('rev-parse', 'origin/' . $branch))) {
		merge_train_fail("HEAD is not the pushed origin/$branch: CI tested another commit.");
	}

	if (merge_train_run(array('git', 'merge-base', '--is-ancestor', 'origin/development', 'HEAD')) !== 0) {
		merge_train_fail("origin/development moved past the train's base: merge it into $branch, push and let CI run again.");
	}

	$runs = merge_train_gh('GET', 'commits/' . $head . '/check-runs?per_page=100');

	if (!merge_train_checks_green(isset($runs['check_runs']) ? $runs['check_runs'] : array())) {
		merge_train_fail("CI is not green on $head.");
	}

	if (!merge_train_git_shown(array('push', 'origin', $head . ':refs/heads/development'))) {
		merge_train_fail('The fast-forward push to development failed.');
	}

	fwrite(STDOUT, "Landed $branch ($head) on development.\n");
}

$mode = '';
$options = array();
$numbers = array();

foreach (array_slice($argv, 1) as $argument) {
	if (in_array($argument, array('--start', '--list', '--build', '--land'), true) && ($mode === '')) {
		$mode = $argument;
	} elseif (in_array($argument, array('--stack', '--no-push'), true)) {
		$options[] = $argument;
	} elseif (ctype_digit(ltrim($argument, '#')) && ($mode === '--build')) {
		$numbers[] = (int) ltrim($argument, '#');
	} else {
		merge_train_fail("Unknown argument: $argument\nUsage: php tools/merge_train.php --start [--stack] | --list | --build [<n> ...] [--no-push] | --land");
	}
}

if ($mode === '--start') {
	merge_train_start($options);
} elseif ($mode === '--list') {
	merge_train_list();
} elseif ($mode === '--build') {
	merge_train_build($numbers, $options);
} elseif ($mode === '--land') {
	merge_train_land();
} else {
	merge_train_fail('Usage: php tools/merge_train.php --start [--stack] | --list | --build [<n> ...] [--no-push] | --land');
}
