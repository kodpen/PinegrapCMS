<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/memory_mirror.php: a memory file as a general memory
 * record, the cut of a long body, the mirror's tag, which files are read and
 * the plan of creates, changes and deletes against the records listed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/memory_mirror.php');

// The frontmatter is left out of the text; its module: line gives the tags.
function test_memory_mirror_split()
{
	$split = memory_mirror_split("---\nname: a\ndescription: d\nmodule: seo, Output  image_editor\n---\n\n## Why?\n- Because.\n\n");
	pg_assert_same("## Why?\n- Because.", $split['body']);
	pg_assert_same(array('seo', 'output', 'image_editor'), $split['modules']);

	$plain = memory_mirror_split("# Title\r\n\r\nText\r\n");
	pg_assert_same("# Title\n\nText", $plain['body']);
	pg_assert_same(array(), $plain['modules']);

	// An unclosed frontmatter is body.
	pg_assert_same("---\nname: a", memory_mirror_split("---\nname: a\n")['body']);
}

// A body over 4000 characters is cut with a closing line naming the file;
// the whole text stays inside the record's limit.
function test_memory_mirror_text_cut()
{
	pg_assert_same('short', memory_mirror_text('short', 'ui/a.md'));

	$long = str_repeat('ğ', 4100);
	$text = memory_mirror_text($long, 'ui/a.md');
	pg_assert_same(4000, mb_strlen($text));
	pg_assert_true(substr($text, -strlen('[Cut; the whole file: docs/memory/ui/a.md]')) === '[Cut; the whole file: docs/memory/ui/a.md]');
	pg_assert_same(str_repeat('ğ', 10), mb_substr($text, 0, 10));

	pg_assert_same(str_repeat('a', 4000), memory_mirror_text(str_repeat('a', 4000), 'ui/a.md'));
}

// Project the folder, title the file name without .md, tags the modules and
// the mirror's tag last, holding the hash of what was mirrored.
function test_memory_mirror_record()
{
	$record = memory_mirror_record('workspace/channel-memory.md', "---\nname: channel-memory\nmodule: workspace_memory\n---\nBody");
	pg_assert_same('workspace', $record['project']);
	pg_assert_same('channel-memory', $record['title']);
	pg_assert_same('Body', $record['text']);
	pg_assert_same(2, count($record['tags']));
	pg_assert_same('workspace_memory', $record['tags'][0]);
	pg_assert_same($record['tags'][1], memory_mirror_owned_tag($record['tags']));
	pg_assert_true((bool) preg_match('/^mirror-[0-9a-f]{10}$/', $record['tags'][1]));

	// Another text, another tag; the same text, the same tag.
	$again = memory_mirror_record('workspace/channel-memory.md', "---\nname: channel-memory\nmodule: workspace_memory\n---\nBody");
	$other = memory_mirror_record('workspace/channel-memory.md', "---\nname: channel-memory\nmodule: workspace_memory\n---\nBody 2");
	pg_assert_same($record['tags'][1], $again['tags'][1]);
	pg_assert_true($record['tags'][1] !== $other['tags'][1]);

	// A module the record cannot take as a tag is left out; nine at most.
	$many = memory_mirror_record('ui/a.md', "---\nmodule: a1, a2, a3, a4, a5, a6, a7, a8, a9, a10, " . str_repeat('x', 33) . "\n---\nT");
	pg_assert_same(10, count($many['tags']));
	pg_assert_same('a9', $many['tags'][8]);
}

// Only a record carrying the mirror's tag is the mirror's.
function test_memory_mirror_owned_tag()
{
	pg_assert_same('mirror-0123456789', memory_mirror_owned_tag(array('seo', 'mirror-0123456789')));
	pg_assert_same('', memory_mirror_owned_tag(array('seo', 'mirror')));
	pg_assert_same('', memory_mirror_owned_tag(array('mirror-xyz')));
	pg_assert_same('', memory_mirror_owned_tag('mirror-0123456789'));
}

// Each folder's .md files are read, one level deep; the generated lists and
// the root's README are not, nor pictures.
function test_memory_mirror_files()
{
	$root = sys_get_temp_dir() . '/pg_memory_mirror_' . getmypid();
	@mkdir($root . '/ui/drawing', 0777, true);
	@mkdir($root . '/api', 0777, true);
	file_put_contents($root . '/README.md', 'x');
	file_put_contents($root . '/ui/README.md', 'x');
	file_put_contents($root . '/ui/INDEX.md', 'x');
	file_put_contents($root . '/ui/b.md', 'x');
	file_put_contents($root . '/ui/a.md', 'x');
	file_put_contents($root . '/ui/drawing/c.md', 'x');
	file_put_contents($root . '/ui/drawing/c.png', 'x');
	file_put_contents($root . '/api/DECISIONS.md', 'x');
	file_put_contents($root . '/api/k.md', 'x');

	pg_assert_same(array('api/k.md', 'ui/README.md', 'ui/a.md', 'ui/b.md'), memory_mirror_files($root));

	foreach (array('README.md', 'ui/README.md', 'ui/INDEX.md', 'ui/b.md', 'ui/a.md', 'ui/drawing/c.md', 'ui/drawing/c.png', 'api/DECISIONS.md', 'api/k.md') as $file) {
		@unlink($root . '/' . $file);
	}

	@rmdir($root . '/ui/drawing');
	@rmdir($root . '/ui');
	@rmdir($root . '/api');
	@rmdir($root);
}

// The plan: a new file is created, a changed one updated, a gone one
// deleted, a second record of one file deleted; records without the mirror's
// tag are never touched.
function test_memory_mirror_plan()
{
	$same = memory_mirror_record('ui/same.md', 'Same');
	$changed = memory_mirror_record('ui/changed.md', 'New text');
	$new = memory_mirror_record('api/new.md', 'New');
	$old = memory_mirror_record('ui/changed.md', 'Old text');
	$gone = memory_mirror_record('ui/gone.md', 'Gone');

	$records = array(
		array('id' => 1, 'project' => 'ui', 'title' => 'same', 'tags' => $same['tags']),
		array('id' => 2, 'project' => 'ui', 'title' => 'changed', 'tags' => $old['tags']),
		array('id' => 3, 'project' => 'ui', 'title' => 'gone', 'tags' => $gone['tags']),
		array('id' => 4, 'project' => 'ui', 'title' => 'same', 'tags' => $same['tags']),
		array('id' => 5, 'project' => 'ui', 'title' => 'gone', 'tags' => array('notes')),
		array('id' => 6, 'project' => 'ui', 'title' => 'mine', 'tags' => array()),
	);

	$plan = memory_mirror_plan(array('ui/same.md' => $same, 'ui/changed.md' => $changed, 'api/new.md' => $new), $records);
	pg_assert_same(array('api/new.md'), array_keys($plan['create']));
	pg_assert_same(array(2), array_keys($plan['update']));
	pg_assert_same('New text', $plan['update'][2]['text']);
	pg_assert_same(array(3, 4), array_keys($plan['delete']));
	pg_assert_same(1, $plan['unchanged']);

	// --full sends every record again; the site writes only what differs.
	$full = memory_mirror_plan(array('ui/same.md' => $same), array($records[0]), true);
	pg_assert_same(array(1), array_keys($full['update']));
	pg_assert_same(0, $full['unchanged']);
}

// A busy site (429) is waited out and asked again; another answer is
// returned as it is.
function test_memory_mirror_call_retries()
{
	$answers = array(
		array('status' => 429, 'headers' => "Retry-After: 7\r\n", 'body' => '', 'error' => ''),
		array('status' => 429, 'headers' => '', 'body' => '', 'error' => ''),
		array('status' => 200, 'headers' => '', 'body' => '{}', 'error' => ''),
	);
	$waits = array();
	$send = function () use (&$answers) {
		return array_shift($answers);
	};
	$sleep = function ($seconds) use (&$waits) {
		$waits[] = $seconds;
	};

	pg_assert_same(200, memory_mirror_call($send, $sleep)['status']);
	pg_assert_same(array(7, 30), $waits);

	$always = function () {
		return array('status' => 429, 'headers' => '', 'body' => '', 'error' => '');
	};
	pg_assert_same(429, memory_mirror_call($always, function () {
	}, 2)['status']);
}
