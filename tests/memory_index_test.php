<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/memory_index.php: the module field of a
 * memory file's frontmatter, the size a memory file is measured by, the
 * generated list of includes/fn/ modules, the decision marks, the clusters
 * and their start files, and the links to memory files that do not exist.
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
require_once(dirname(__DIR__) . '/tools/memory_index.php');

function test_memory_index_module_names_parsed()
{
	pg_assert_same(array('comments'), memory_index_module_names('comments', $error));
	pg_assert_same('', $error);
	pg_assert_same(array('privacy', 'audit'), memory_index_module_names('privacy, audit', $error));
	pg_assert_same(array('a11y', 'widgets_lists_menu'), memory_index_module_names(' a11y ,widgets_lists_menu,a11y', $error), 'a name given twice is kept once');
}

function test_memory_index_module_names_refused()
{
	pg_assert_same(null, memory_index_module_names('', $error), 'an empty field');
	pg_assert_true($error !== '');
	pg_assert_same(null, memory_index_module_names('comments,', $error), 'an empty name');
	pg_assert_same(null, memory_index_module_names('comments.php', $error), 'a file name, not a module name');
	pg_assert_same(null, memory_index_module_names('fn/comments', $error), 'a path');
	pg_assert_same(null, memory_index_module_names('Comments', $error), 'module files are lower case');
	pg_assert_same(null, memory_index_module_names('[[comments]]', $error));
}

// The module line is index metadata: a file at the size limit can still carry
// it, and a line of the same text in the body still counts.
function test_memory_index_counted_bytes_leave_out_module_line()
{
	$head = "---\nname: a\ndescription: b\n";
	$body = "---\n\n## Q?\n- A.\n";

	pg_assert_same(strlen($head . $body), memory_index_counted_bytes($head . $body));
	pg_assert_same(strlen($head . $body), memory_index_counted_bytes($head . "module: comments, audit\n" . $body));
	pg_assert_same(strlen($head . $body) + 17, memory_index_counted_bytes($head . $body . "module: comments\n"));
	pg_assert_same(5, memory_index_counted_bytes("plain"));
}

function test_memory_index_module_list_sorted()
{
	$list = memory_index_module_list(array(
		'widget-lists' => array('widgets_lists'),
		'comment-replies' => array('comments'),
		'comment-thread-rules' => array('comments'),
		'comments-widget-pages' => array('widgets_comments', 'comments'),
		'accessibility-report' => array('a11y'),
	));

	pg_assert_true(strncmp($list, "---\nname: fn-modules\ndescription: ", 34) === 0, 'starts with its own frontmatter');
	pg_assert_contains("\n- `a11y`: [[accessibility-report]]\n- `comments`: [[comment-replies]], [[comment-thread-rules]], [[comments-widget-pages]]\n- `widgets_comments`: [[comments-widget-pages]]\n- `widgets_lists`: [[widget-lists]]\n", $list);
	pg_assert_contains('[[fn-module-map]]', $list);
	pg_assert_same("\n", substr($list, -1));
	pg_assert_true(substr($list, -2) !== "\n\n", 'one newline at the end');
}

function test_memory_index_module_list_same_for_any_input_order()
{
	$a = memory_index_module_list(array('b' => array('y', 'x'), 'a' => array('x')));
	$b = memory_index_module_list(array('a' => array('x'), 'b' => array('x', 'y')));

	pg_assert_same($a, $b);
	pg_assert_contains("- `x`: [[a]], [[b]]\n- `y`: [[b]]\n", $a);
}

function test_memory_index_module_list_empty()
{
	pg_assert_contains('No memory file names a module yet.', memory_index_module_list(array()));
}

function test_memory_index_decision_parts_collected()
{
	$parts = memory_index_decision_parts(array(
		'decisions-made-product-15.md' => "---\nname: x\n---\n\n## May a till go below zero? (#4460)\n- Owner: no (`erp/erp-till-below-zero.md`).\n\n## Which rounding? (#12, PR #13)\n- See [[offer-percent-rounding]] and erp-cheques.md, not other-decisions-made.md.\n",
		'decisions-made.md' => "## What is this list?\n- Parts: `decisions-made-data.md`.\n",
	));

	pg_assert_same(array(
		array('decisions-made-product-15.md', 'May a till go below zero? (#4460)'),
		array('decisions-made-product-15.md', 'Which rounding? (#12, PR #13)'),
		array('decisions-made.md', 'What is this list?'),
	), $parts['headings']);
	pg_assert_same(array('decisions-made-product-15.md'), $parts['issues']['4460']);
	pg_assert_same(array('decisions-made-product-15.md'), $parts['issues']['13']);
	pg_assert_same(array('decisions-made-product-15.md'), $parts['named']['erp-till-below-zero']);
	pg_assert_same(array('decisions-made-product-15.md'), $parts['named']['offer-percent-rounding']);
	pg_assert_same(array('decisions-made-product-15.md'), $parts['named']['erp-cheques']);
	pg_assert_true(!isset($parts['named']['decisions']), 'a hyphenated name is taken whole');
	pg_assert_true(!isset($parts['issues']['1']), 'only headings give issue numbers');
}

function test_memory_index_decision_recorded_in_own_text()
{
	pg_assert_true(memory_index_decision_recorded("- Owner (2026-11-10): the box stays."));
	pg_assert_true(memory_index_decision_recorded("- Kept (owner, 2026-10-26, #1773)."));
	pg_assert_true(memory_index_decision_recorded("- architect, 2026-11-02: one path."));
	pg_assert_true(memory_index_decision_recorded("- Owner decision (2026-10-30): no."));
	pg_assert_true(memory_index_decision_recorded("- owner decision 2026-10-30: no."));
	pg_assert_true(!memory_index_decision_recorded("- The owner reported it on 2026-10-30."), 'a report is not a decision');
	pg_assert_true(!memory_index_decision_recorded("- owner request 2026-10-30."));
	pg_assert_true(!memory_index_decision_recorded("- Decided by the owner."), 'undated');
	pg_assert_true(!memory_index_decision_recorded("- The site owner sees it (2026-10-30)."));
}

function test_memory_index_decision_mark()
{
	$parts = memory_index_decision_parts(array(
		'decisions-made-b.md' => "## Q? (#4460)\n- `erp/erp-till-below-zero.md`.\n",
		'decisions-made-a.md' => "## R?\n- [[erp-till-below-zero]], [[other]].\n",
	));

	pg_assert_same('decisions-made-a.md, decisions-made-b.md', memory_index_decision_mark('erp-till-below-zero', '- Plain.', $parts), 'parts sorted, each once');
	pg_assert_same('decisions-made-b.md', memory_index_decision_mark('erp-x', "## Which? (#4460, PR #4666)\n- Plain.", $parts), 'an issue number of an entry heading');
	pg_assert_same('decisions-made-a.md', memory_index_decision_mark('other', '- Owner (2026-11-10): kept.', $parts), 'a part wins over the own text');
	pg_assert_same('here', memory_index_decision_mark('erp-y', '- Owner (2026-11-10): kept.', $parts));
	pg_assert_same('', memory_index_decision_mark('erp-z', '- Plain (#44600).', $parts), 'a longer number is another issue');
}

function test_memory_index_decision_list()
{
	$list = memory_index_decision_list(
		array(array('decisions-made-b.md', 'Q? (#4460)'), array('decisions-made-a.md', 'R?')),
		array('erp/erp-y.md' => 'Y description', 'core/a-x.md' => 'X description')
	);

	pg_assert_true(strncmp($list, "# docs/memory/workflow/DECISIONS.md\n", 36) === 0);
	pg_assert_contains("\n- decisions-made-a.md: R?\n- decisions-made-b.md: Q? (#4460)\n", $list);
	pg_assert_contains("\n- core/a-x.md: X description\n- erp/erp-y.md: Y description\n", $list);
	pg_assert_same("\n", substr($list, -1));
	pg_assert_true(substr($list, -2) !== "\n\n", 'one newline at the end');
}

function test_memory_index_clusters_found()
{
	$files = array();
	foreach (array('a', 'b', 'c', 'd', 'e') as $x) {
		$files[] = 'workspace-flows-' . $x . '.md';
	}
	$files[] = 'workspace-flow-f.md';
	$files[] = 'workspace-flows-overview.md';
	foreach (array('a', 'b', 'c', 'd', 'e') as $x) {
		$files[] = 'list-table-' . $x . '.md';
	}
	$files[] = 'erp-till.md';
	$files[] = 'readme.md';

	pg_assert_same(array(
		array('workspace-flows', 7, 'workspace-flows-overview.md'),
	), memory_index_clusters($files), 'five files are no cluster; a plural s is set aside');

	$files[] = 'list-table.md';
	pg_assert_same(array(
		array('list-table', 6, 'list-table.md'),
		array('workspace-flows', 7, 'workspace-flows-overview.md'),
	), memory_index_clusters($files), 'the prefix file itself counts and is the start file');
}

function test_memory_index_clusters_name_and_start()
{
	$files = array('custom-forms-a.md', 'custom-form-b.md', 'custom-form-c.md', 'custom-form-d.md', 'custom-form-e.md', 'custom-form-f.md', 'custom-forms-overview.md');
	pg_assert_same(array(array('custom-form', 7, 'custom-forms-overview.md')), memory_index_clusters($files), 'named by the most used form; the start file in either form');

	$files = array('ab-cd-1.md', 'ab-cd-2.md', 'ab-cd-3.md', 'ab-cds-4.md', 'ab-cds-5.md', 'ab-cds-6.md');
	pg_assert_same(array(array('ab-cd', 6, null)), memory_index_clusters($files), 'a tie goes to the shorter form; no start file');
}

// A cluster of ten or more needs a start file in either form; nine do not,
// and a plan's files are never held to it.
function test_memory_index_cluster_errors()
{
	pg_assert_same(array(), memory_index_cluster_errors('ui', array(
		array('list-table', 44, 'list-table-overview.md'),
		array('bootstrap-6', 12, 'bootstrap-6.md'),
		array('main-menu', 9, null),
	)), 'a start file in either form, or fewer than ten files');

	$errors = memory_index_cluster_errors('ui', array(array('main-menu', 9, null), array('picker-field', 10, null), array('image-editor', 67, null)));
	pg_assert_same(2, count($errors), 'one error per cluster of ten or more');
	pg_assert_contains('docs/memory/ui/picker-field-*: 10 files and no start file; add picker-field-overview.md', $errors[0]);
	pg_assert_contains('docs/memory/ui/image-editor-*: 67 files', $errors[1]);

	pg_assert_same(array(), memory_index_cluster_errors('plans', array(array('design-template', 12, null))), 'plans are left out');
}

function test_memory_index_folder_lists_clusters()
{
	$readme = memory_index_folder('ui', '- A rule.', array(array('list-table', 44, 'list-table-overview.md'), array('image-editor', 9, null)));

	pg_assert_contains("\n## Clusters\n\n", $readme);
	pg_assert_contains("\n- `list-table-*`: 44 files; start at `list-table-overview.md`.\n- `image-editor-*`: 9 files.\n", $readme);
	pg_assert_true(strpos($readme, '## Clusters') > strpos($readme, KEY_RULES_END), 'after the key rules');
	pg_assert_true(strpos(memory_index_folder('ui', '', array()), '## Clusters') === false, 'no heading without clusters');
}

function test_memory_index_broken_links()
{
	$existing = array('core/a-x.md', 'core/README.md', 'erp/erp-y.md', 'plans/p.md', 'workflow/DECISIONS.md');

	pg_assert_same(array(), memory_index_broken_links("See [[a-x]], `erp/erp-y.md`, core/README.md and docs/memory/plans/p.md.\n", $existing));
	pg_assert_same(
		array('erp/erp-z.md', '[[gone]]', 'plans/done.md'),
		memory_index_broken_links("[[a-x]] erp/erp-z.md [[gone]]\nRelated: docs/memory/plans/done.md, docs/memory/erp/erp-z.md, [[gone]]\n", $existing),
		'each missing file once, in the order met'
	);
}

// Only memory paths are references: a code path that ends in a folder name,
// a wildcard, a placeholder, an Obsidian embed and a [[name]] quoted alone
// as an example are not.
function test_memory_index_broken_links_ignore_other_paths()
{
	$text = "pinegrap/includes/api/x.md, assets/ui/y.md, `designer/designer-blocks-*.md`, "
		. "`designer-blocks-<prefix>.md`, `erp/<name>.md`, `![[file]]`, `[[name]]`, [[Name]], tools/z.md, a.md\n";

	pg_assert_same(array(), memory_index_broken_links($text, array()));
}

// A line of $n characters, made of $char.
function memory_index_test_line($n, $char = 'a')
{
	return '- ' . str_repeat($char, $n - 2);
}

// The long-line rule: a line over MAX_LINE_CHARS characters the branch
// adds or rewrites is refused; one that stands word for word in the file at
// the base is let through, wherever it now stands.
function test_memory_index_long_lines_compare()
{
	$long = memory_index_test_line(501);
	$old = memory_index_test_line(600, 'b');
	$base = "---\nname: x\n---\n\n## Q?\n$old\n- short\n";

	pg_assert_same(500, MAX_LINE_CHARS, 'the limit of workflow/memory-writing.md');
	pg_assert_same(array(), memory_index_long_lines($base, $base), 'unchanged');
	pg_assert_same(array(), memory_index_long_lines("---\nname: x\n---\n\n## New?\n- added\n\n## Q?\n- short\n$old\n", $base), 'an old line moved is let through');
	pg_assert_same(array(array(7, $long)), memory_index_long_lines("---\nname: x\n---\n\n## Q?\n$old\n$long\n- short\n", $base), 'an added line');
	$rewritten = $old . ' And more.';
	pg_assert_same(array(array(6, $rewritten)), memory_index_long_lines("---\nname: x\n---\n\n## Q?\n$rewritten\n- short\n", $base), 'a rewritten line');
	pg_assert_same(array(array(6, $old)), memory_index_long_lines($base, null), 'a new or renamed file has no base');
	pg_assert_same(array(array(7, $old)), memory_index_long_lines("---\nname: x\n---\n\n## Q?\n$old\n$old\n", $base), 'an old line written twice: the copy is new');
}

function test_memory_index_long_lines_counts_characters()
{
	pg_assert_same(array(), memory_index_long_lines(memory_index_test_line(500) . "\n", null), 'exactly 500 passes');
	pg_assert_same(array(array(1, memory_index_test_line(501))), memory_index_long_lines(memory_index_test_line(501) . "\n", null), '501 is refused');
	$turkish = memory_index_test_line(500, 'ş');
	pg_assert_true(strlen($turkish) > 500, 'more than 500 bytes');
	pg_assert_same(array(), memory_index_long_lines($turkish . "\n", null), 'characters are counted, not bytes');
	pg_assert_same(array(), memory_index_long_lines(memory_index_test_line(500) . "\r\n", null), 'a carriage return is not a character of the line');
}

// A folder README: only the hand-written key rules block counts; the
// generated part under it does not.
function test_memory_index_long_lines_readme_key_rules()
{
	$long = memory_index_test_line(520);
	$generated = memory_index_test_line(900, 'g');
	$readme = function ($rules) use ($generated) {
		return "# x\n\n" . KEY_RULES_START . "\n$rules\n" . KEY_RULES_END . "\n\n## Clusters\n$generated\n";
	};

	pg_assert_same(array(), memory_index_readme_long_lines($readme('- short'), null), 'the generated part is not counted');
	pg_assert_same(array(array(5, $long)), memory_index_readme_long_lines($readme("- short\n$long"), $readme('- short')), 'a long key rule');
	pg_assert_same(array(), memory_index_readme_long_lines($readme("- short\n$long"), $readme($long)), 'a key rule already at the base');
	pg_assert_same(array(array(4, $long)), memory_index_readme_long_lines($readme($long), null), 'a new README');
	pg_assert_same(array(), memory_index_readme_long_lines("# x\n$long\n", null), 'no key rules block: nothing hand-written');
}
