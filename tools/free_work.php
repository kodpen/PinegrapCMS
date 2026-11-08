<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a coordinator can give its workers, in one short listing: the plan
 * steps that are not done and that no open PR holds, and the queue count.
 * Bug issues and the bug-hunt plan files are not listed: workers are not
 * given them. It stands in for reading docs/memory/plans/README.md and the
 * plan files one by one, which filled a new coordinator's context in its
 * first turn.
 *
 * Usage:  php tools/free_work.php [--worktree]
 *         --worktree reads the plans from this checkout instead, to see what
 *         a plan edit not yet pushed would list.
 * Exit:   0; 1 when git or gh fails.
 *
 * The plans are read from origin/development after a fetch, so a stale clone
 * does not matter. GitHub is read through `gh api` (REST): gh pr, gh issue,
 * GraphQL and the search API are blocked in cloud sessions.
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

define('FREE_WORK_REPO', 'repos/kodpen/pinegrapcms');
define('FREE_WORK_QUEUE_LIMIT', 25);
define('FREE_WORK_LINE', 110);

// The plan a file belongs to: the X of "[plan:X]" in its description (the
// steps files say "claim as [plan:X] <step>"), else the file name without
// .md and without a -steps or -steps-<n> ending.
function free_work_plan_name($file, $description)
{
	if (preg_match('/\[plan:([a-z0-9-]+)\]/', (string) $description, $match)) {
		return $match[1];
	}

	return preg_replace('/-steps(-\d+)?$/', '', basename((string) $file, '.md'));
}

// A plan the coordinators leave alone: shelved (in its description or its
// file name), a draft not to be started, on a watch list or waiting for a
// release.
function free_work_shelved($description, $file = '')
{
	return (bool) preg_match('/not to be started|\bshelved\b|watch list|not before/i', (string) $description)
		|| (strpos(basename((string) $file), 'shelved') !== false);
}

// The step lines of a plan file: "- <ID> …" or "<n>. <ID> …" with an ID
// such as AP3, BK10, X12a or N10d, bold or not, under a "## Which steps …?"
// heading; a file with no such heading has them in any section. Lines under
// the order, side-by-side and "beside" headings name steps without being
// one, and so do lines whose ID is followed by a comma, a colon or "alone",
// "first", "is done".
// A bare number (the design templates' "- 4 Topic: …", "- 4: …",
// "- 4 (done, #n): …", "- 4 Done (#n). …") is a step only under a steps
// heading, where "- 4: …" is one too (its colon is dropped); a number
// followed by "-" or "×" is a date, a range or a size, not a step.
// An indented "  - <ID> …" line is a step of its own, or more of the step
// above when it names the same ID; an indented line that is no list item
// ("   Cut in two: …") is more of the step above. "- Done: N1 #n, V2 #n"
// marks the steps it names done. A step done in several PRs whose line says
// "Next: part <n>" is open as "<ID> part <n>".
// Returns a list of array(id, rest of the line).
function free_work_steps($text)
{
	$lines = preg_split('/\R/', (string) $text);
	$aside = 'side by side|\brun\b|order|\bown\b|start|taken|\bbeside\b';
	$headed = (bool) preg_grep('/^## (?=.*step)(?!.*(?:' . $aside . '))/i', $lines);
	$steps = array();
	$done = array();
	$take = !$headed;
	$last = -1;

	foreach ($lines as $line) {
		if (strpos($line, '## ') === 0) {
			$take = !preg_match('/' . $aside . '/i', $line) && (!$headed || preg_match('/step/i', $line));
			$last = -1;

			continue;
		}

		if (!$take || (trim($line) === '')) {
			continue;
		}

		if (preg_match('/^- \**Done:\**\s*(.*)$/', $line, $match)) {
			preg_match_all('/\b([A-Z]{1,3}\d+[a-z]?)\s+#\d+/', $match[1], $ids);
			$done += array_flip($ids[1]);
			$last = -1;

			continue;
		}

		$nested = (bool) preg_match('/^\s/', $line);

		if (!preg_match('/^(\s*)(?:-|(\d+)\.) \**([A-Z]{1,3})?(\d+[a-z]?)\b\**(?![-\x{2013}\x{00D7}\d])\s*(.*)$/u', $line, $match)) {
			if ($nested && ($last >= 0) && !preg_match('/^\s*-\s/', $line)) {
				$steps[$last][1] .= ' ' . trim($line);
			} elseif (!$nested) {
				$last = -1;
			}

			continue;
		}

		$numbered = ($match[3] === '');
		$id = $match[3] . $match[4];
		$rest = $match[5];

		if ($numbered && ($nested || ($match[2] !== '') || !$headed)) {
			if (!$nested) {
				$last = -1;
			}

			continue;
		}

		if ($numbered) {
			$rest = preg_replace('/^:\s*/', '', $rest);
		}

		if (preg_match('/^(?:[,:]|alone\b|first\b|is done\b)/i', $rest)) {
			continue;
		}

		if ($nested && ($last >= 0) && ($steps[$last][0] === $id)) {
			$steps[$last][1] .= ' ' . trim($rest);

			continue;
		}

		$steps[] = array($id, trim($rest));
		$last = count($steps) - 1;
	}

	$open = array();

	foreach ($steps as $step) {
		if (isset($done[$step[0]])) {
			continue;
		}

		if (preg_match('/\bNext:\s*(part \d+)\W*(.*)$/', $step[1], $match)) {
			$step = array($step[0] . ' ' . $match[1], $match[2]);
		}

		$open[] = $step;
	}

	return $open;
}

// Whether a step is not for a worker: the owner's work ("(S, owner's
// machine)", "(S, the owner's public keys)"), or a step without `free` in a
// plan whose holder marks the free lines `free`.
function free_work_step_blocked($rest, $plan_text)
{
	if (preg_match("/^\\([^)]*\\bowner's\\b/", (string) $rest)) {
		return true;
	}

	return (strpos((string) $plan_text, 'marks lines `free`') !== false)
		&& (strpos((string) $rest, '`free`') === false);
}

// Whether a plan's steps run one after another ("each waits for the one
// before it"): then only its first step not done is work.
function free_work_sequential($text)
{
	return (bool) preg_match('/each waits for the one before it/i', (string) $text);
}

// The polishing round's next step per template (design-template-own-looks-
// order.md): `<name>-1`, the blocks, then `<name>-2`, the pages. Templates
// with a "- Name: `-1` …" status line come first, in their order, then the
// names of the numbered order lists. A step is done when "done" follows its
// `-1` or `-2`; "needs no `-2`" ends a template at `-1`. Returns a list of
// array(id, started), started when its `-1` is done; empty for any other
// plan file.
function free_work_own_looks($text)
{
	if (strpos((string) $text, 'Step `<id>-1`') === false) {
		return array();
	}

	$lines = preg_split('/\R/', (string) $text);
	$state = array();

	foreach ($lines as $line) {
		if (preg_match('/^- ([A-Z][a-z]+): (.*`-1`.*)$/', $line, $match)) {
			$after_one = substr($match[2], strpos($match[2], '`-1`'));
			$two_at = strpos($match[2], '`-2`');
			$after_two = ($two_at === false) ? '' : substr($match[2], $two_at + 4);
			$state[strtolower($match[1])] = array(
				(bool) preg_match('/\bdone\b/', $after_one),
				(bool) preg_match('/\bdone\b/', $after_two) || (strpos($match[2], 'needs no `-2`') !== false),
			);
		}
	}

	foreach ($lines as $line) {
		if (preg_match('/^\d+\. (.*)$/', $line, $match) && !preg_match('/\bDone\b/', $line)) {
			$names = preg_replace('/\s*\([^()]*\)/', '', $match[1]);
			$names = substr($names, strrpos($names, ': ') ?: 0);

			foreach (explode(',', trim($names, " :.")) as $name) {
				$name = trim($name);

				if (preg_match('/^[a-z]+$/', $name) && !isset($state[$name])) {
					$state[$name] = array(false, false);
				}
			}
		}
	}

	$steps = array();

	foreach ($state as $name => $done) {
		if (!$done[0]) {
			$steps[] = array($name . '-1', false);
		} elseif (!$done[1]) {
			$steps[] = array($name . '-2', true);
		}
	}

	return $steps;
}

// Whether a step line says the step is done: "`done` (#n)", "Done in #n",
// "Done (#n)", "done in PR #n", ": PR #n" or ": PRs #a, #b", "merged",
// "… are done", "<ID> done", or a capitalised "Done" anywhere.
function free_work_step_done($line)
{
	$line = (string) $line;

	return (bool) (preg_match('/`done`|\bdone\b\W{0,3}(?:in\s+)?(?:PRs?\s+)?#\d+|:\s*PRs?\s+#\d+|\bmerged\b|\bare done\b/i', $line)
		|| preg_match('/\b[A-Z]{1,3}\d+[a-z]?\s+done\b/', $line)
		|| preg_match('/\bDone\b/', $line));
}

// The open PR that holds a step: its title names "[plan:<plan>]" and the
// step's ID as a word. A numbered step is looked for only between the tag
// and the " — " before the topic, so a number in the topic holds nothing.
// $titles is number => title. Returns the number or 0.
function free_work_holder($plan, $id, array $titles)
{
	$tag = '[plan:' . $plan . ']';
	$pattern = '/(?<![A-Za-z0-9.])' . preg_quote((string) $id, '/') . '(?![A-Za-z0-9])/';

	foreach ($titles as $number => $title) {
		$at = strpos((string) $title, $tag);

		if ($at === false) {
			continue;
		}

		$named = (string) $title;

		if (ctype_digit(substr((string) $id, 0, 1))) {
			$named = substr($named, $at + strlen($tag));
			$named = preg_split('/\s+(?:\x{2014}|--?)\s+/u', $named, 2)[0];
		}

		if (preg_match($pattern, $named)) {
			return (int) $number;
		}
	}

	return 0;
}

// A line cut to the listing's width, on a character boundary.
function free_work_cut($text, $width = FREE_WORK_LINE)
{
	$text = trim(preg_replace('/\s+/u', ' ', (string) $text));

	if (preg_match('/^.{' . ($width + 1) . ',}$/us', $text)) {
		preg_match('/^.{' . ($width - 1) . '}/us', $text, $cut);

		return rtrim($cut[0]) . '…';
	}

	return $text;
}

// The test runner loads this file for the functions above.
if (defined('PG_TEST_RUNNER')) {
	return;
}

chdir(dirname(__DIR__));

// A command's stdout, or exit 1 when it fails.
function free_work_run($command)
{
	exec($command . ' 2>&1', $out, $code);

	if ($code !== 0) {
		fwrite(STDERR, "free_work: failed: $command\n" . implode("\n", $out) . "\n");
		exit(1);
	}

	return $out;
}

// One JSON value per line, as `gh api --paginate --jq '…|@json'` prints them.
function free_work_gh_lines($path, $jq)
{
	$rows = array();

	foreach (free_work_run('gh api --paginate ' . escapeshellarg(FREE_WORK_REPO . '/' . $path) . ' --jq ' . escapeshellarg($jq)) as $line) {
		$row = json_decode($line, true);

		if (is_array($row)) {
			$rows[] = $row;
		}
	}

	return $rows;
}

free_work_run('git fetch -q origin development');

$titles = array();
$queue = 0;
$drafts = 0;

foreach (free_work_gh_lines('pulls?state=open&per_page=100', '.[]|[.number,.draft,.head.ref,.title]|@json') as $pr) {
	list($number, $draft, $branch, $title) = $pr;

	if (strpos((string) $branch, 'merge-train/') === 0) {
		continue;
	}

	$titles[(int) $number] = (string) $title;

	if ($draft) {
		$drafts++;
	} else {
		$queue++;
	}

}

$files = array();

$worktree = in_array('--worktree', $argv, true);
$paths = $worktree ? glob('docs/memory/plans/*.md') : free_work_run('git ls-tree --name-only origin/development docs/memory/plans/');

foreach ($paths as $path) {
	if ((substr($path, -3) === '.md') && (basename($path) !== 'README.md')) {
		$text = $worktree ? (string) file_get_contents($path) : implode("\n", free_work_run('git show ' . escapeshellarg('origin/development:' . $path)));
		$description = preg_match('/^description:\s*(.*)$/m', $text, $match) ? $match[1] : '';
		$files[basename($path)] = array(free_work_plan_name($path, $description), $description, $text);
	}
}

// A plan is shelved by its own file's description; its steps files follow it.
$shelved = array();

foreach ($files as $file => $plan) {
	if (free_work_shelved($plan[1], $file)) {
		$shelved[$plan[0]] = true;
	}
}

$free = array();
$held = array();

foreach ($files as $file => $plan) {
	list($name, , $text) = $plan;

	if (isset($shelved[$name])) {
		continue;
	}

	// The bug hunt stopped: its module lists are no work to give out.
	if (strpos($file, 'bug-hunt') === 0) {
		continue;
	}

	$sequential = free_work_sequential($text);
	$waiting = false;

	foreach (free_work_steps($text) as $step) {
		list($id, $rest) = $step;

		if (free_work_step_done($rest)) {
			continue;
		}

		// "D5 part 4" is held by a PR on D5.
		$holder = free_work_holder($name, strtok($id, ' '), $titles);

		if ($holder) {
			$held[] = "$name $id #$holder";
		} elseif (!$waiting && !free_work_step_blocked($rest, $text)) {
			$free[] = '  ' . free_work_cut("$name $id $rest");
		}

		// The later steps wait for this one.
		$waiting = $sequential;
	}

	// The polishing round: up to four templates at a time; a started one
	// (its `-1` done) or one with an open PR takes a place.
	$looks = array();
	$busy = 0;

	foreach (free_work_own_looks($text) as $look) {
		list($id, $started) = $look;
		$holder = free_work_holder($name, $id, $titles);

		if ($holder) {
			$held[] = "$name $id #$holder";
		}

		if ($holder || $started) {
			$busy++;
		}

		if (!$holder) {
			$looks[] = $look;
		}
	}

	foreach ($looks as $look) {
		if ($look[1] || ($busy < 4)) {
			$free[] = "  $name {$look[0]}" . ($look[1] ? ' pages on its blocks' : ' blocks');
			$busy += $look[1] ? 0 : 1;
		}
	}
}

$architect = array();

foreach (free_work_gh_lines('issues?state=open&labels=architect&per_page=100', '.[]|select(.pull_request|not)|[.number]|@json') as $issue) {
	$architect[] = '#' . $issue[0];
}

echo "Queue: $queue of " . FREE_WORK_QUEUE_LIMIT . " open PRs (drafts and merge trains not counted); $drafts drafts.\n";
echo "Free plan steps (not done, no open PR; read the step's \"After\" before giving it out):\n";
echo ($free ? implode("\n", $free) : '  none') . "\n";
echo 'Architect issues open: ' . ($architect ? implode(' ', $architect) : 'none') . "\n";
echo 'Held by an open PR: ' . ($held ? implode(', ', $held) : 'none') . "\n";
echo 'Shelved plans skipped: ' . ($shelved ? implode(', ', array_keys($shelved)) : 'none') . "\n";
