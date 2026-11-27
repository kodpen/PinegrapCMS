<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Mirrors docs/memory into the general memory the agent channels share,
 * through a site's external API with an application key. Each file of a
 * memory folder becomes one record: project the folder, title the file name
 * without .md, text the body without the frontmatter, tags the frontmatter's
 * module: names. The mirror is one way: git stays the record.
 *
 * Usage:  php tools/memory_mirror.php [--dry-run] [--full] [--root=PATH]
 *
 *   --dry-run  lists what would be created, changed and deleted; writes
 *              nothing.
 *   --full     sends every mirrored record again (the site writes only what
 *              differs), to put back a record changed by hand on the site.
 *   --root     the memory folder, docs/memory of this checkout by default.
 *
 * Read: <folder>/*.md, one level deep, but the generated INDEX.md and
 * DECISIONS.md; the root's README.md and pictures are not mirrored. A body
 * over 4000 characters is cut with a closing line naming the file.
 *
 * Which records are the mirror's: those carrying the tag mirror-<hash>, the
 * first ten hex digits of the SHA-1 of what was mirrored. A run lists them
 * (a search for the word "mirror-", which pages past the plain list's 500),
 * then creates the files with no record, updates the records whose hash
 * differs, and deletes the records whose file is gone and any second record
 * of one file. A record without that tag is never touched. Nothing is
 * written when the listing fails.
 *
 * A 429 is waited out (Retry-After, else 30 seconds) and asked again.
 *
 * Exit:   0 mirrored; 1 the listing or a write failed (stderr names each);
 *         2 usage, missing settings or no memory file found.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY and PG_WS_SECRET of an application whose owner may post in an
 * agent channel. Nothing secret is printed.
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

// The site's limits of a memory record (WS_MEMORY_TEXT_MAX, WS_MEMORY_TAGS_MAX,
// WS_MEMORY_TAG_MAX in includes/workspace/channel_memory.php).
define('MEMORY_MIRROR_TEXT_MAX', 4000);
define('MEMORY_MIRROR_TAGS_MAX', 10);
define('MEMORY_MIRROR_TAG_MAX', 32);

// The tag that marks a record as the mirror's, before its hash.
define('MEMORY_MIRROR_TAG', 'mirror-');

/**
 * The usage text.
 *
 * @return string
 */
function memory_mirror_usage()
{
	return "Usage: php tools/memory_mirror.php [--dry-run] [--full] [--root=PATH]\n"
		. "Settings: PG_WS_SITE, PG_WS_KEY, PG_WS_SECRET in the environment.\n";
}

/**
 * A memory file's body without its frontmatter, line ends as \n and blank
 * lines around it trimmed, and the module: names of its frontmatter in
 * lower case. Pure.
 *
 * @param string $content
 * @return array{body: string, modules: string[]}
 */
function memory_mirror_split($content)
{
	$content = str_replace(array("\r\n", "\r"), "\n", (string) $content);
	$modules = array();

	if (preg_match('/\A---\n(.*?)\n---(?:\n|\z)/s', $content, $m)) {
		$content = (string) substr($content, strlen($m[0]));

		if (preg_match('/^module:[ \t]*(.*)$/m', $m[1], $line)) {
			foreach (preg_split('/[\s,]+/', strtolower(trim($line[1]))) as $module) {
				if ($module !== '' && !in_array($module, $modules, true)) {
					$modules[] = $module;
				}
			}
		}
	}

	return array('body' => trim($content, "\n"), 'modules' => $modules);
}

/**
 * The record's text: the body, or over the record's limit the body cut with
 * a closing line naming the file. Pure.
 *
 * @param string $body
 * @param string $rel  the file under docs/memory (ui/a.md)
 * @return string
 */
function memory_mirror_text($body, $rel)
{
	$body = (string) $body;

	if (mb_strlen($body, 'UTF-8') <= MEMORY_MIRROR_TEXT_MAX) {
		return $body;
	}

	$close = "\n\n[Cut; the whole file: docs/memory/" . $rel . ']';

	return rtrim(mb_substr($body, 0, MEMORY_MIRROR_TEXT_MAX - mb_strlen($close, 'UTF-8'), 'UTF-8')) . $close;
}

/**
 * The record a memory file is mirrored as: project, title, text and tags,
 * the mirror's tag last. Pure.
 *
 * @param string $rel     the file under docs/memory (ui/a.md)
 * @param string $content the file's content
 * @return array{project: string, title: string, text: string, tags: string[]}
 */
function memory_mirror_record($rel, $content)
{
	$split = memory_mirror_split($content);
	$project = (string) dirname($rel);
	$title = (string) preg_replace('/\.md$/', '', basename($rel));
	$text = memory_mirror_text($split['body'], $rel);
	$tags = array();

	foreach ($split['modules'] as $module) {
		// The site's rule of a tag: one word, at most 32 characters.
		if (strlen($module) <= MEMORY_MIRROR_TAG_MAX && preg_match('/^[a-z0-9][a-z0-9_.\-]*$/', $module) && count($tags) < MEMORY_MIRROR_TAGS_MAX - 1) {
			$tags[] = $module;
		}
	}

	$hash = substr(sha1(json_encode(array($project, $title, $text, $tags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 10);
	$tags[] = MEMORY_MIRROR_TAG . $hash;

	return array('project' => $project, 'title' => $title, 'text' => $text, 'tags' => $tags);
}

/**
 * The mirror's tag among a record's tags, '' when the record is not the
 * mirror's. Pure.
 *
 * @param mixed $tags
 * @return string
 */
function memory_mirror_owned_tag($tags)
{
	if (!is_array($tags)) {
		return '';
	}

	foreach ($tags as $tag) {
		if (is_string($tag) && preg_match('/^' . preg_quote(MEMORY_MIRROR_TAG, '/') . '[0-9a-f]{10}$/', $tag)) {
			return $tag;
		}
	}

	return '';
}

/**
 * The files mirrored under $root, sorted: <folder>/*.md, but the generated
 * INDEX.md and DECISIONS.md.
 *
 * @param string $root
 * @return string[] paths under $root (ui/a.md)
 */
function memory_mirror_files($root)
{
	$out = array();

	foreach ((array) glob(rtrim($root, '/') . '/*/*.md') as $path) {
		$name = basename((string) $path);

		if ($name === 'INDEX.md' || $name === 'DECISIONS.md' || !is_file((string) $path)) {
			continue;
		}

		$out[] = basename(dirname((string) $path)) . '/' . $name;
	}

	sort($out, SORT_STRING);

	return $out;
}

/**
 * What a run does: the files to create, the records to update (by id) and
 * to delete (by id), and the count left as they are. Pure.
 *
 * @param array<string,array> $wanted  memory_mirror_record() by file
 * @param array<int,array>    $records the listed records (id, project, title, tags)
 * @param bool                $full    update every mirrored record
 * @return array{create: array<string,array>, update: array<int,array>, delete: array<int,string>, unchanged: int}
 */
function memory_mirror_plan($wanted, $records, $full = false)
{
	$by_key = array();

	foreach ($wanted as $rel => $record) {
		$by_key[$record['project'] . "\n" . $record['title']] = $rel;
	}

	$plan = array('create' => array(), 'update' => array(), 'delete' => array(), 'unchanged' => 0);
	$seen = array();

	foreach ($records as $row) {
		$tag = memory_mirror_owned_tag($row['tags'] ?? null);
		$id = (int) ($row['id'] ?? 0);

		if ($tag === '' || $id < 1) {
			continue;
		}

		$key = (string) ($row['project'] ?? '') . "\n" . (string) ($row['title'] ?? '');

		if (!isset($by_key[$key]) || isset($seen[$key])) {
			$plan['delete'][$id] = str_replace("\n", '/', $key);
			continue;
		}

		$seen[$key] = true;
		$record = $wanted[$by_key[$key]];

		if ($full || $tag !== end($record['tags'])) {
			$plan['update'][$id] = $record;
		} else {
			$plan['unchanged']++;
		}
	}

	foreach ($by_key as $key => $rel) {
		if (!isset($seen[$key])) {
			$plan['create'][$rel] = $wanted[$rel];
		}
	}

	ksort($plan['delete']);

	return $plan;
}

/**
 * One call, asked again while the site answers 429: after its Retry-After,
 * else 30 seconds, at most $tries times in all.
 *
 * @param callable $send  returns a ws_client_request() answer
 * @param callable $sleep takes seconds
 * @param int      $tries
 * @return array{status: int, headers: string, body: string, error: string}
 */
function memory_mirror_call($send, $sleep, $tries = 10)
{
	for ($i = 1; ; $i++) {
		$answer = $send();

		if ((int) $answer['status'] !== 429 || $i >= $tries) {
			return $answer;
		}

		$wait = preg_match('/^retry-after:\s*(\d+)\s*$/mi', (string) $answer['headers'], $m) ? max(1, min(300, (int) $m[1])) : 30;
		$sleep($wait);
	}
}

/**
 * Every record of the general memory carrying the mirror's tag word, page
 * by page; null when a page failed (the reason on stderr).
 *
 * @param array $settings ws_client_settings()
 * @return array<int,array>|null
 */
function memory_mirror_list($settings)
{
	$records = array();
	$cursor = '';

	do {
		$url = $settings['site'] . '/workspace/memory/general?expired=true&search=' . rawurlencode(MEMORY_MIRROR_TAG) . ($cursor !== '' ? '&cursor=' . rawurlencode($cursor) : '');
		$answer = memory_mirror_call(function () use ($url, $settings) {
			return ws_client_request('GET', $url, $settings);
		}, 'sleep');
		$json = json_decode($answer['body'], true);

		if ($answer['status'] !== 200 || !is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
			fwrite(STDERR, 'memory_mirror: listing failed: ' . ws_client_failure($answer) . "\n");
			return null;
		}

		foreach ($json['data'] as $row) {
			if (is_array($row)) {
				$records[] = $row;
			}
		}

		$cursor = isset($json['page']['next_cursor']) && is_string($json['page']['next_cursor']) ? $json['page']['next_cursor'] : '';
	} while ($cursor !== '');

	return $records;
}

/**
 * The command.
 *
 * @param string[] $argv without the script name
 * @return int exit code
 */
function memory_mirror_main($argv)
{
	list($options, $rest) = ws_client_options($argv);

	foreach (array_keys($options) as $name) {
		if (!in_array($name, array('dry-run', 'full', 'root'), true)) {
			$rest[] = '--' . $name;
		}
	}

	if ($rest || (isset($options['root']) && !is_string($options['root']))) {
		fwrite(STDERR, memory_mirror_usage());
		return WS_CLIENT_EXIT_USAGE;
	}

	$settings = ws_client_settings($options, getenv(), false);

	if ($settings['missing']) {
		fwrite(STDERR, 'memory_mirror: missing settings: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$root = isset($options['root']) ? (string) $options['root'] : dirname(__DIR__) . '/docs/memory';
	$wanted = array();

	foreach (memory_mirror_files($root) as $rel) {
		$wanted[$rel] = memory_mirror_record($rel, (string) file_get_contents($root . '/' . $rel));
	}

	// No file would delete every mirrored record: a wrong root, never an
	// empty memory.
	if (!$wanted) {
		fwrite(STDERR, 'memory_mirror: no memory file under ' . $root . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$records = memory_mirror_list($settings);

	if ($records === null) {
		return WS_CLIENT_EXIT_HTTP;
	}

	$plan = memory_mirror_plan($wanted, $records, !empty($options['full']));

	if (!empty($options['dry-run'])) {
		foreach ($plan['create'] as $rel => $record) {
			fwrite(STDOUT, 'create ' . $rel . "\n");
		}

		foreach ($plan['update'] as $id => $record) {
			fwrite(STDOUT, 'update #' . $id . ' ' . $record['project'] . '/' . $record['title'] . "\n");
		}

		foreach ($plan['delete'] as $id => $key) {
			fwrite(STDOUT, 'delete #' . $id . ' ' . $key . "\n");
		}

		fwrite(STDOUT, 'dry run: create=' . count($plan['create']) . ' update=' . count($plan['update']) . ' delete=' . count($plan['delete']) . ' unchanged=' . $plan['unchanged'] . "\n");

		return WS_CLIENT_EXIT_OK;
	}

	$done = array('create' => 0, 'update' => 0, 'delete' => 0);
	$failed = 0;
	$write = function ($kind, $label, $url, $body, $headers) use ($settings, &$done, &$failed) {
		$answer = memory_mirror_call(function () use ($url, $settings, $body, $headers) {
			return ws_client_request('POST', $url, $settings, $body, $headers);
		}, 'sleep');

		if ($answer['status'] >= 200 && $answer['status'] < 300) {
			$done[$kind]++;
		} else {
			$failed++;
			fwrite(STDERR, 'memory_mirror: ' . $kind . ' ' . $label . ' failed: ' . ws_client_failure($answer) . "\n");
		}
	};

	foreach ($plan['create'] as $rel => $record) {
		// A create asked again after a lost answer is not written twice.
		$key = 'mirror-' . substr(sha1($rel . "\n" . end($record['tags'])), 0, 40);
		$write('create', $rel, $settings['site'] . '/workspace/memory/general', array(
			'project' => $record['project'],
			'title'   => $record['title'],
			'text'    => $record['text'],
			'tags'    => implode(',', $record['tags']),
		), array('Idempotency-Key: ' . $key));
	}

	foreach ($plan['update'] as $id => $record) {
		$write('update', '#' . $id, $settings['site'] . '/workspace/memory/' . (int) $id, array(
			'project' => $record['project'],
			'title'   => $record['title'],
			'text'    => $record['text'],
			'tags'    => implode(',', $record['tags']),
		), array());
	}

	foreach ($plan['delete'] as $id => $key) {
		$write('delete', '#' . $id . ' ' . $key, $settings['site'] . '/workspace/memory/' . (int) $id . '/delete', null, array());
	}

	fwrite(STDOUT, 'created=' . $done['create'] . ' updated=' . $done['update'] . ' deleted=' . $done['delete'] . ' unchanged=' . $plan['unchanged'] . ' failed=' . $failed . "\n");

	return $failed ? WS_CLIENT_EXIT_HTTP : WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(memory_mirror_main(array_slice($argv, 1)));
}
