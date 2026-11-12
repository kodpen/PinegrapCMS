<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes the index of the project's memory files from the frontmatter at
 * the top of each file:
 *
 *     ---
 *     name: invoice-allocation
 *     description: One line that says what the file answers
 *     ---
 *
 * A memory file that describes modules under pinegrap/includes/fn/ names
 * them in an optional field, several separated by commas:
 *
 *     module: comments, widgets_comments
 *
 * The list of those modules, each with the files that describe it, is
 * generated into docs/memory/core/fn-modules.md (MODULE_LIST), so a new
 * module is one line in its own memory file rather than a line appended to
 * a shared hand-written file. The module line does not count toward the
 * size limit: a file at the limit can still carry it.
 *
 * The index has two levels so a session reads little: docs/memory/README.md
 * lists the memory folders (FOLDERS below) and the other documents. Each
 * folder has two files: README.md, the folder's key rules, which a session
 * reads whole, and INDEX.md, that folder's files one line each (file name
 * and description), which a session searches with grep rather than reads:
 * the larger folders list several hundred files, about 30,000 tokens when
 * read whole. A session opens the top index, one folder README, the lines
 * of its INDEX.md that match the task and then only those files. The
 * indexes are generated rather than edited so that parallel branches that
 * each add a memory file never conflict on them.
 *
 * A folder index also carries the folder's key rules, the few rules every
 * task in that area follows. They are written by hand between two marker
 * lines and kept as they are on every run:
 *
 *     <!-- key-rules:start -->
 *     - one rule per line, with the memory file that explains it
 *     <!-- key-rules:end -->
 *
 * The decisions list is generated into docs/memory/workflow/DECISIONS.md
 * (DECISION_LIST), searched like an INDEX.md: one line per entry heading of
 * the decisions parts (workflow/decisions-made*.md) and one per topic file
 * whose own text records a dated owner or architect decision. A topic file's
 * index line ends with a mark, so a session that lands on the file sees the
 * decision before it changes the behaviour:
 *
 *     [decision: decisions-made-product-15.md]   a part names the file, or
 *                                                the file cites the issue
 *                                                number of a part's heading
 *     [decision: here]                           only the file's own text
 *                                                records one ("Owner
 *                                                (2026-11-10): ...")
 *
 * The marks are generated from the text; no field is added by hand. Plans and
 * the parts themselves carry no mark.
 *
 * A folder README also lists the folder's clusters (MIN_CLUSTER_FILES or
 * more files sharing their first two name tokens, a plural s aside) with the
 * count and the start file, <prefix>.md or <prefix>-overview.md, when the
 * folder has one, so a session sees where a large topic begins. A cluster of
 * MIN_OVERVIEW_FILES or more with no start file stops the run (plans aside).
 *
 * A folder index that exists without the two markers (an older copy taken
 * during a merge) stops the run, so the rules are never dropped in silence;
 * a new folder starts with an empty block. After a merge conflict in a
 * folder index, running this tool again settles it, unless the conflict is
 * inside the key rules.
 *
 * What is read:
 *   docs/memory/**.md   frontmatter required; name equals the file name;
 *                       at most MAX_MEMORY_BYTES bytes per file
 *                       (the module line not counted); module names a
 *                       file under pinegrap/includes/fn/
 *   other docs/**.md    indexed when they carry frontmatter, else skipped
 *
 * A new memory folder needs a line in FOLDERS; files directly in docs/memory/
 * are not allowed. Unfinished plans are memory files too, in docs/memory/plans/.
 *
 * Usage:  php tools/memory_index.php           write the index files
 *         php tools/memory_index.php --check   compare only; nothing is
 *                                              written (CI)
 * Exit:   0 when the indexes were written or are current; 1 when a file has
 *         missing or invalid frontmatter, a memory file is too large or sits
 *         outside a known folder, a module field names no module file, a
 *         folder index has no key rules block or a merge conflict inside it,
 *         a topic file or decisions part links a memory file that does not
 *         exist ([[name]] or folder/file.md; plans are exempt), a cluster of
 *         MIN_OVERVIEW_FILES files or more has no start file, or, with
 *         --check, an index differs from what the files produce.
 *
 * Line endings do not count: files are read with CR removed and the indexes
 * are written with LF endings, so a checkout that turned them into CRLF passes.
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

// One topic per memory file; a larger file is split, not grown.
const MAX_MEMORY_BYTES = 4096;
const MAX_DESCRIPTION_CHARS = 200;

// The longest memory line, in characters: the search tool shows a longer one
// as "[Omitted long matching line]" (workflow/memory-writing.md). Only a line
// the branch adds or rewrites is refused; one already at the base passes.
const MAX_LINE_CHARS = 500;

// The lines around the hand-written key rules of a folder index.
const KEY_RULES_START = '<!-- key-rules:start -->';
const KEY_RULES_END = '<!-- key-rules:end -->';

// The generated module list, relative to docs/, and its line in the core index.
const MODULE_LIST = 'memory/core/fn-modules.md';
const MODULE_LIST_DESCRIPTION = 'Generated from the module field of the memory files: each includes/fn/ module so named and the memory files that describe it';

// The generated decisions list, relative to docs/, and the file name of its parts.
const DECISION_LIST = 'memory/workflow/DECISIONS.md';
const DECISION_PART_PREFIX = 'decisions-made';

// The fewest files sharing their first two name tokens that a README lists as a cluster.
const MIN_CLUSTER_FILES = 6;

// The fewest files of a cluster that must have a start file: read file by
// file, a large cluster costs a session more calls than its start file costs
// to keep (workflow/memory-writing.md).
const MIN_OVERVIEW_FILES = 10;

// Memory folders and what they hold, as listed in the top index.
const FOLDERS = array(
	'api' => 'External API (integration.php, includes/api/): endpoints, scopes, device and OAuth sessions, MCP server, webhooks, outbound connectors, connected accounts',
	'commerce' => 'Orders, cart, checkout, shipping, products and variants, express order',
	'core' => 'Code rules, file layout, translation, JavaScript twins and bundles, roles and access, security, api.php, request parameters',
	'data' => 'Table structures and SQL pitfalls',
	'designer' => 'Visual designer, system widgets, data binding, designer forms, member widgets, content roles, HTML import',
	'erp' => 'ERP (accounting) module: invoices, payments, waybills, cash, e-documents, reports',
	'plans' => 'Unfinished and shelved plans: remaining work, decisions made, open questions, where to start',
	'platform' => 'Server config, router, redirects, updates and TLS, system status, images and uploads, setup templates',
	'release' => 'Versions, migrations and upgrade steps, change log rules, release status',
	'ui' => 'Panel screens, settings modal, dashboard, panel JavaScript pitfalls',
	'visitors' => 'Visitor statistics, page and article views, search engine index flags',
	'waf' => 'Firewall (waf.php): rules, bans, rate limits, bots, logs, security headers',
	'workflow' => 'Project overview, owner rules, decisions already made, git and branches, sandbox and dev machine',
	'workspace' => 'Workspace module: channels, tasks, plan board, rights, Claude in channels',
);

// The test runner loads this file for the functions below.
if (defined('PG_TEST_RUNNER')) {
	return;
}

$check = in_array('--check', array_slice($argv, 1), true);

$repository = dirname(__DIR__);
$docs = $repository . '/docs';

$errors = array();

// The base the long-line rule compares with: --base=<commit> (tools/pre_push.sh
// passes where the pushed branch left development), else where HEAD left
// origin/development. Only the memory files changed since then are read.
$base_given = '';
foreach (array_slice($argv, 1) as $arg) {
	if (strncmp($arg, '--base=', 7) === 0) {
		$base_given = substr($arg, 7);
	}
}
$base = memory_index_base($repository, $base_given, $base_error);
$changed = ($base === null) ? array() : memory_index_changed_files($repository, $base);

if ($base === null) {
	$base_note = "the long-line check cannot read the base ($base_error): fetch origin/development"
		. ' (git fetch origin development) or pass --base=<commit>';
	if ($check) {
		$errors[] = $base_note;
	} else {
		fwrite(STDERR, "Warning: $base_note; long lines were not checked.\n");
	}
}
$folders = array();
$others = array();
$names = array();
$modules = array();
$decision_parts = array();
$topics = array();

foreach (memory_index_files($docs) as $relative) {

	$path = $docs . '/' . $relative;
	$in_memory = strncmp($relative, 'memory/', 7) === 0;

	$content = str_replace("\r", '', (string) file_get_contents($path));
	$meta = memory_index_frontmatter($content);

	if ($meta === null) {
		if ($in_memory) {
			$errors[] = "docs/$relative: no frontmatter (---, name:, description:, ---)";
		}
		continue;
	}

	$stem = basename($relative, '.md');
	$name = isset($meta['name']) ? $meta['name'] : '';
	$description = isset($meta['description']) ? $meta['description'] : '';

	if ($name === '') {
		$errors[] = "docs/$relative: frontmatter has no name";
	} elseif ($name !== $stem) {
		$errors[] = "docs/$relative: name \"$name\" does not match the file name \"$stem\"";
	}

	if ($description === '') {
		$errors[] = "docs/$relative: frontmatter has no description";
	} elseif (mb_strlen($description, 'UTF-8') > MAX_DESCRIPTION_CHARS) {
		$errors[] = "docs/$relative: description is longer than " . MAX_DESCRIPTION_CHARS . ' characters';
	}

	if (isset($meta['module'])) {
		$listed = memory_index_module_names($meta['module'], $error);
		if (!$in_memory) {
			$errors[] = "docs/$relative: a module field belongs in a memory file";
		} elseif ($listed === null) {
			$errors[] = "docs/$relative: module field: $error";
		} else {
			foreach ($listed as $module) {
				if (!is_file($repository . '/pinegrap/includes/fn/' . $module . '.php')) {
					$errors[] = "docs/$relative: module \"$module\" has no file pinegrap/includes/fn/$module.php";
				}
			}
			$modules[$stem] = $listed;
		}
	}

	if ($in_memory && isset($changed['docs/' . $relative])) {
		$errors = array_merge($errors, memory_index_long_line_errors('docs/' . $relative,
			memory_index_long_lines($content, memory_index_git_file($repository, $base, 'docs/' . $relative))));
	}

	if ($in_memory) {
		$bytes = memory_index_counted_bytes($content);
		if ($bytes > MAX_MEMORY_BYTES) {
			$errors[] = "docs/$relative: $bytes bytes, more than " . MAX_MEMORY_BYTES . '; split the topic';
		}
		if ($name !== '' && isset($names[$name])) {
			$errors[] = "docs/$relative: name \"$name\" is also used by docs/" . $names[$name];
		}
		$names[$name] = $relative;
	}

	if ($name === basename(MODULE_LIST, '.md')) {
		$errors[] = "docs/$relative: name \"$name\" is kept for the generated " . MODULE_LIST;
	}

	if ($in_memory) {
		$parts = explode('/', $relative);
		if (count($parts) !== 3 || !isset(FOLDERS[$parts[1]])) {
			$errors[] = "docs/$relative: memory files go one level deep into a folder listed in FOLDERS (tools/memory_index.php)";
			continue;
		}
		$folders[$parts[1]][$parts[2]] = $description;
		if ($parts[1] === 'workflow' && strncmp($stem, DECISION_PART_PREFIX, strlen(DECISION_PART_PREFIX)) === 0) {
			$decision_parts[$parts[2]] = $content;
		} elseif ($parts[1] !== 'plans') {
			$topics[$parts[1] . '/' . $parts[2]] = $content;
		}
	} else {
		$others[$relative] = $description;
	}
}

// Links to memory files that do not exist; plans are exempt, they name files to come.
$existing = array(substr(MODULE_LIST, 7), substr(DECISION_LIST, 7));

foreach ($folders as $folder => $entries) {
	$existing[] = $folder . '/README.md';
	$existing[] = $folder . '/INDEX.md';
	foreach (array_keys($entries) as $entry) {
		$existing[] = $folder . '/' . $entry;
	}
}

foreach ($topics + memory_index_prefixed($decision_parts, 'workflow/') as $relative => $content) {
	foreach (memory_index_broken_links($content, $existing) as $link) {
		$errors[] = "docs/memory/$relative: $link names no memory file; correct or remove the link";
	}
}

if ($errors) {
	foreach ($errors as $error) {
		fwrite(STDERR, $error . "\n");
	}
	fwrite(STDERR, count($errors) . " problem(s); the memory index was not written.\n");
	exit(1);
}

// The generated module list is listed in its folder like a memory file.
$folders['core'][basename(MODULE_LIST)] = MODULE_LIST_DESCRIPTION;

// Decision marks on the topic files' index lines, and the decisions list.
$decisions = memory_index_decision_parts($decision_parts);
$recorded = array();

foreach ($topics as $relative => $content) {

	list($folder, $entry) = explode('/', $relative);
	$mark = memory_index_decision_mark(basename($entry, '.md'), $content, $decisions);

	if (memory_index_decision_recorded($content)) {
		$recorded[$relative] = $folders[$folder][$entry];
	}

	if ($mark !== '') {
		$folders[$folder][$entry] .= ' [decision: ' . $mark . ']';
	}
}

// Index path relative to docs/ => expected content.
$indexes = array(
	'memory/README.md' => memory_index_top($folders, $others),
	MODULE_LIST => memory_index_module_list($modules),
	DECISION_LIST => memory_index_decision_list($decisions['headings'], $recorded),
);

foreach ($folders as $folder => $entries) {

	$relative = "memory/$folder/README.md";
	$rules = memory_index_key_rules($docs . '/' . $relative, $error);

	// Its hand-written key rules follow the long-line rule; the generated
	// part does not.
	if (isset($changed['docs/' . $relative]) && is_file($docs . '/' . $relative)) {
		$errors = array_merge($errors, memory_index_long_line_errors('docs/' . $relative, memory_index_readme_long_lines(
			(string) file_get_contents($docs . '/' . $relative), memory_index_git_file($repository, $base, 'docs/' . $relative))));
	}

	if ($rules === null) {
		$errors[] = "docs/$relative: $error";
		continue;
	}

	$clusters = memory_index_clusters(array_keys($entries));
	$errors = array_merge($errors, memory_index_cluster_errors($folder, $clusters));

	$indexes[$relative] = memory_index_folder($folder, $rules, $clusters);
	$indexes["memory/$folder/INDEX.md"] = memory_index_folder_files($folder, $entries);
}

if ($errors) {
	foreach ($errors as $error) {
		fwrite(STDERR, $error . "\n");
	}
	fwrite(STDERR, count($errors) . " problem(s); the memory index was not written.\n");
	exit(1);
}

$stale = 0;

foreach ($indexes as $relative => $index) {

	$path = $docs . '/' . $relative;
	$current = is_file($path) ? str_replace("\r", '', (string) file_get_contents($path)) : null;

	if ($current === $index) {
		continue;
	}

	if ($check) {
		fwrite(STDERR, "docs/$relative is out of date\n");
		$stale++;
		continue;
	}

	if (file_put_contents($path, $index) === false) {
		fwrite(STDERR, "Cannot write docs/$relative\n");
		exit(1);
	}

	echo "docs/$relative written.\n";
}

// A folder index left behind in a folder that has no memory files any more.
foreach (array_merge(glob($docs . '/memory/*/README.md'), glob($docs . '/memory/*/INDEX.md')) as $path) {

	$relative = substr($path, strlen($docs) + 1);

	if (isset($indexes[$relative])) {
		continue;
	}

	if ($check) {
		fwrite(STDERR, "docs/$relative belongs to a folder with no memory files; remove it\n");
		$stale++;
	} elseif (unlink($path)) {
		echo "docs/$relative removed.\n";
	}
}

if ($stale) {
	fwrite(STDERR, "Run php tools/memory_index.php and commit the result.\n");
	exit(1);
}

echo $check ? "The memory index is current.\n" : "The memory index is up to date.\n";
exit(0);

/**
 * Every .md file under docs/ that may be indexed, relative to docs/, sorted.
 * The generated indexes and the generated module list are left out.
 */
function memory_index_files($docs) {

	$files = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($docs, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {

		if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
			continue;
		}

		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($docs) + 1));

		if ($relative === 'memory/README.md' || $relative === MODULE_LIST || $relative === DECISION_LIST || (strncmp($relative, 'memory/', 7) === 0 && in_array(basename($relative), array('README.md', 'INDEX.md'), true))) {
			continue;
		}

		$files[] = $relative;
	}

	usort($files, 'strcmp');

	return $files;
}

/**
 * The key: value lines between a leading "---" line and the next "---" line,
 * or null when the file does not start with frontmatter.
 */
function memory_index_frontmatter($content) {

	if (strncmp($content, "---\n", 4) !== 0) {
		return null;
	}

	$end = strpos($content, "\n---", 3);

	if ($end === false) {
		return null;
	}

	$meta = array();

	foreach (explode("\n", substr($content, 4, $end - 4)) as $line) {
		$colon = strpos($line, ':');
		if ($colon === false) {
			continue;
		}
		$meta[trim(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
	}

	return $meta;
}

/**
 * The top index: one line per memory folder (its index and what it holds),
 * then the other documents.
 *
 * A folder's line carries no file count: two branches that each add a file
 * to the same folder would merge cleanly into a count that matches neither,
 * and the --check of the merged tree (CI on every pull request) would fail.
 */
function memory_index_top($folders, $others) {

	$lines = array(
		'# Memory index',
		'',
		'<!-- Generated by php tools/memory_index.php from the frontmatter of each file. Do not edit by hand. -->',
		'',
		'Read the README.md of the folder the task needs and follow its key rules; find the files in its INDEX.md with grep, then open only those.',
		'',
		'## Memory folders (docs/memory/)',
		'',
	);

	foreach (FOLDERS as $folder => $about) {
		if (isset($folders[$folder])) {
			$lines[] = '- ' . $folder . '/README.md: ' . $about;
		}
	}

	if ($others) {

		$lines[] = '';
		$lines[] = '## Other documents (docs/)';
		$lines[] = '';

		foreach ($others as $entry => $description) {
			$lines[] = '- ' . $entry . ': ' . $description;
		}
	}

	return implode("\n", $lines) . "\n";
}

/**
 * A folder's README.md: the folder's key rules as written by hand (a heading
 * only when there are some), its clusters as memory_index_clusters() returns
 * them, then how to search its INDEX.md. A cluster's count changes when a
 * branch adds a file to it; running the tool again after a merge settles it.
 */
function memory_index_folder($folder, $rules, $clusters = array()) {

	$lines = array(
		'# docs/memory/' . $folder . '/',
		'',
		'<!-- Generated by php tools/memory_index.php, except the key rules between the two marker lines, which are written by hand. -->',
		'',
		FOLDERS[$folder] . '.',
		'',
	);

	if ($rules !== '') {
		$lines[] = '## Key rules';
		$lines[] = '';
		$lines[] = KEY_RULES_START;
		$lines[] = $rules;
	} else {
		$lines[] = KEY_RULES_START;
	}

	$lines[] = KEY_RULES_END;
	$lines[] = '';

	if ($clusters) {
		$lines[] = '## Clusters';
		$lines[] = '';
		foreach ($clusters as $cluster) {
			$lines[] = '- `' . $cluster[0] . '-*`: ' . $cluster[1] . ' files' . ($cluster[2] !== null ? '; start at `' . $cluster[2] . '`' : '') . '.';
		}
		$lines[] = '';
	}

	$lines[] = '## Files';
	$lines[] = '';
	$lines[] = '- `INDEX.md` lists the files of this folder, one line each: search it rather than read it whole, then open only the files that match.';
	$lines[] = "- `grep -i 'word' docs/memory/" . $folder . "/INDEX.md`; across every folder: `grep -i 'word' docs/memory/*/INDEX.md`.";

	if ('memory/' . $folder . '/' . basename(DECISION_LIST) === DECISION_LIST) {
		$lines[] = '- `' . basename(DECISION_LIST) . '` lists every decision, one line each: the entry headings of the `' . DECISION_PART_PREFIX . '*.md` parts and the topic files that record a dated owner or architect decision. Search it like an index: `grep -i \'word\' docs/' . DECISION_LIST . '`. A topic file\'s index line ending in `[decision: …]` is bound by the part it names, or by its own text (`here`).';
	}

	return implode("\n", $lines) . "\n";
}

/**
 * A folder's INDEX.md: each memory file of the folder with its description,
 * one line each, sorted by file name.
 */
function memory_index_folder_files($folder, $entries) {

	uksort($entries, 'strcmp');

	$lines = array(
		'# docs/memory/' . $folder . '/INDEX.md',
		'',
		'<!-- Generated by php tools/memory_index.php from the frontmatter of each file. Do not edit by hand. -->',
		'',
	);

	foreach ($entries as $entry => $description) {
		$lines[] = '- ' . $entry . ': ' . $description;
	}

	return implode("\n", $lines) . "\n";
}

/**
 * The hand-written key rules of a folder index, without the blank lines
 * around them: '' for a folder index that does not exist yet, null (and
 * $error set) when the file has no single pair of marker lines or the block
 * holds a merge conflict.
 */
function memory_index_key_rules($path, &$error) {

	$error = '';

	if (!is_file($path)) {
		return '';
	}

	$lines = explode("\n", str_replace("\r", '', (string) file_get_contents($path)));
	$starts = array_keys(array_map('trim', $lines), KEY_RULES_START, true);
	$ends = array_keys(array_map('trim', $lines), KEY_RULES_END, true);

	if (count($starts) !== 1 || count($ends) !== 1 || $ends[0] < $starts[0]) {
		$error = 'no single key rules block (one ' . KEY_RULES_START . ' line, then one ' . KEY_RULES_END
			. ' line); take the file from development (git checkout origin/development -- <file>) and run again,'
			. ' or delete it if the folder has no key rules yet';
		return null;
	}

	$block = array_slice($lines, $starts[0] + 1, $ends[0] - $starts[0] - 1);

	foreach ($block as $line) {
		if (preg_match('/^(<{7}|={7}|>{7}|\|{7})( |$)/', $line)) {
			$error = 'a merge conflict inside the key rules; settle it by hand, then run again';
			return null;
		}
	}

	while ($block && trim($block[0]) === '') {
		array_shift($block);
	}

	while ($block && trim($block[count($block) - 1]) === '') {
		array_pop($block);
	}

	return implode("\n", $block);
}

/**
 * The module names of a module field, in the order given, each once; null
 * (and $error set) when a name is empty or is not a bare module file name
 * (lower-case letters, digits and underscores, no path, no .php).
 */
function memory_index_module_names($value, &$error) {

	$error = '';
	$names = array();

	foreach (explode(',', $value) as $part) {

		$part = trim($part);

		if ($part === '') {
			$error = 'an empty module name; give names separated by commas';
			return null;
		}

		if (!preg_match('/^[a-z0-9_]+$/', $part)) {
			$error = "\"$part\" is not a module name (the file name under includes/fn/ without .php)";
			return null;
		}

		if (!in_array($part, $names, true)) {
			$names[] = $part;
		}
	}

	return $names;
}

/**
 * The size a memory file is held to: its bytes without the module line of
 * its frontmatter, which is index metadata rather than content.
 */
function memory_index_counted_bytes($content) {

	$bytes = strlen($content);

	if (strncmp($content, "---\n", 4) !== 0) {
		return $bytes;
	}

	$end = strpos($content, "\n---", 3);

	if ($end === false) {
		return $bytes;
	}

	foreach (explode("\n", substr($content, 4, $end - 4)) as $line) {
		$colon = strpos($line, ':');
		if ($colon !== false && trim(substr($line, 0, $colon)) === 'module') {
			$bytes -= strlen($line) + 1;
		}
	}

	return $bytes;
}

/**
 * The generated module list: one line per module named in a module field,
 * sorted by module name, with the memory files that name it, sorted by
 * file name. $modules is memory file name => its module names.
 */
function memory_index_module_list($modules) {

	$files = array();

	foreach ($modules as $file => $names) {
		foreach ($names as $module) {
			$files[$module][] = $file;
		}
	}

	uksort($files, 'strcmp');

	$lines = array(
		'---',
		'name: ' . basename(MODULE_LIST, '.md'),
		'description: ' . MODULE_LIST_DESCRIPTION,
		'---',
		'',
		'<!-- Generated by php tools/memory_index.php from the module field in the frontmatter of the memory files. Do not edit by hand. -->',
		'',
		'## Which memory file describes an `includes/fn/` module?',
	);

	if (!$files) {
		$lines[] = '- No memory file names a module yet.';
	}

	foreach ($files as $module => $names) {
		usort($names, 'strcmp');
		$lines[] = '- `' . $module . '`: [[' . implode(']], [[', $names) . ']]';
	}

	$lines[] = '';
	$lines[] = '## How does a module get on this list?';
	$lines[] = '- The memory file that describes it names it in its frontmatter (`module: comments, widgets_comments`) and `php tools/memory_index.php` is run. The kinds of function the older modules hold are in [[fn-module-map]].';

	return implode("\n", $lines) . "\n";
}

/**
 * What the decisions parts hold: 'headings', each entry heading as
 * array(part, heading) in part and file order; 'issues', each issue number
 * cited in a heading => the parts citing it; 'named', each memory file name
 * a part refers to (folder/name.md, name.md or [[name]]) => those parts.
 * $parts is part file name => content. The lists of parts are sorted.
 */
function memory_index_decision_parts($parts) {

	uksort($parts, 'strcmp');

	$result = array('headings' => array(), 'issues' => array(), 'named' => array());

	foreach ($parts as $part => $content) {

		foreach (explode("\n", str_replace("\r", '', $content)) as $line) {

			if (strncmp($line, '## ', 3) !== 0) {
				continue;
			}

			$heading = trim(substr($line, 3));
			$result['headings'][] = array($part, $heading);

			preg_match_all('/#(\d+)\b/', $heading, $numbers);
			foreach ($numbers[1] as $number) {
				$result['issues'][$number][$part] = true;
			}
		}

		preg_match_all('/(?<![\w.-])([a-z0-9][a-z0-9-]*)\.md\b|\[\[([a-z0-9-]+)\]\]/', $content, $references, PREG_SET_ORDER);
		foreach ($references as $reference) {
			$name = isset($reference[2]) && $reference[2] !== '' ? $reference[2] : $reference[1];
			$result['named'][$name][$part] = true;
		}
	}

	foreach (array('issues', 'named') as $key) {
		foreach ($result[$key] as $item => $listed) {
			$result[$key][$item] = array_keys($listed);
		}
	}

	return $result;
}

/**
 * Whether a file's own text records a dated owner or architect decision:
 * "Owner (2026-11-10)", "owner, 2026-10-26", "architect 2026-11-02",
 * "Owner decision (2026-10-30)". A request or a report by the owner is not
 * one, nor an undated mention.
 */
function memory_index_decision_recorded($content) {

	return (bool) preg_match('/\b(?:owner|architect)(?: decision| decided| approved)?(?: on)?,? \(?\d{4}-\d{2}-\d{2}\b/i', $content);
}

/**
 * The decision mark of a topic file: the parts that name it or the issue
 * number of whose headings it cites, sorted and separated by commas; 'here'
 * when no part does and its own text records a decision; '' otherwise.
 * $decisions is what memory_index_decision_parts() returned.
 */
function memory_index_decision_mark($name, $content, $decisions) {

	$parts = isset($decisions['named'][$name]) ? $decisions['named'][$name] : array();

	preg_match_all('/#(\d+)\b/', $content, $numbers);
	foreach (array_unique($numbers[1]) as $number) {
		if (isset($decisions['issues'][$number])) {
			$parts = array_merge($parts, $decisions['issues'][$number]);
		}
	}

	if ($parts) {
		$parts = array_unique($parts);
		usort($parts, 'strcmp');
		return implode(', ', $parts);
	}

	return memory_index_decision_recorded($content) ? 'here' : '';
}

/**
 * The generated decisions list: the entry headings of the parts, grouped by
 * part in file name order and in their own order within a part, then the
 * topic files that record a decision in their own text, sorted, with their
 * description. $headings is a list of array(part, heading); $recorded is
 * folder/file => description.
 */
function memory_index_decision_list($headings, $recorded) {

	$by_part = array();

	foreach ($headings as $heading) {
		$by_part[$heading[0]][] = $heading[1];
	}

	uksort($by_part, 'strcmp');
	uksort($recorded, 'strcmp');

	$lines = array(
		'# docs/' . DECISION_LIST,
		'',
		'<!-- Generated by php tools/memory_index.php from the decisions parts and the topic files. Do not edit by hand. -->',
		'',
		'Search it rather than read it whole, then open the part or file that matches: `grep -i \'word\' docs/' . DECISION_LIST . '`.',
		'',
		'## Entries of the decisions parts (workflow/' . DECISION_PART_PREFIX . '*.md)',
		'',
	);

	foreach ($by_part as $part => $list) {
		foreach ($list as $heading) {
			$lines[] = '- ' . $part . ': ' . $heading;
		}
	}

	$lines[] = '';
	$lines[] = '## Topic files that record a dated owner or architect decision';
	$lines[] = '';

	foreach ($recorded as $file => $description) {
		$lines[] = '- ' . $file . ': ' . $description;
	}

	return implode("\n", $lines) . "\n";
}

/**
 * The clusters of a folder: groups of at least MIN_CLUSTER_FILES files whose
 * names share their first two tokens (split at hyphens), a trailing s of a
 * token set aside, so workspace-flow-x.md and workspace-flows-y.md group
 * together. Each is array(prefix, count, start file or null), sorted by
 * prefix. The prefix is the form most files use (the shorter on a tie); the
 * start file is <prefix>.md or <prefix>-overview.md in either form.
 * $files are file names with .md.
 */
function memory_index_clusters($files) {

	$groups = array();
	$present = array_flip($files);

	foreach ($files as $file) {

		$tokens = explode('-', basename($file, '.md'));

		if (count($tokens) < 2) {
			continue;
		}

		$form = $tokens[0] . '-' . $tokens[1];
		$key = rtrim($tokens[0], 's') . '-' . rtrim($tokens[1], 's');
		$groups[$key][$form] = isset($groups[$key][$form]) ? $groups[$key][$form] + 1 : 1;
	}

	$clusters = array();

	foreach ($groups as $forms) {

		$count = array_sum($forms);

		if ($count < MIN_CLUSTER_FILES) {
			continue;
		}

		uksort($forms, function ($a, $b) use ($forms) {
			if ($forms[$a] !== $forms[$b]) {
				return $forms[$b] - $forms[$a];
			}
			return strlen($a) !== strlen($b) ? strlen($a) - strlen($b) : strcmp($a, $b);
		});

		$names = array_keys($forms);
		$start = null;

		foreach (array('', '-overview') as $suffix) {
			foreach ($names as $name) {
				if ($start === null && isset($present[$name . $suffix . '.md'])) {
					$start = $name . $suffix . '.md';
				}
			}
		}

		$clusters[$names[0]] = array($names[0], $count, $start);
	}

	uksort($clusters, 'strcmp');

	return array_values($clusters);
}

/**
 * The errors for the clusters of a folder that have MIN_OVERVIEW_FILES files
 * or more and no start file (<prefix>-overview.md, or <prefix>.md as the
 * older clusters have it), one per cluster. $clusters is what
 * memory_index_clusters() returns. Plans are left out: a plan is deleted when
 * it is finished, so its files are no lasting topic.
 */
function memory_index_cluster_errors($folder, $clusters) {

	$errors = array();

	if ($folder === 'plans') {
		return $errors;
	}

	foreach ($clusters as $cluster) {
		if ($cluster[1] >= MIN_OVERVIEW_FILES && $cluster[2] === null) {
			$errors[] = 'docs/memory/' . $folder . '/' . $cluster[0] . '-*: ' . $cluster[1] . ' files and no start file; add '
				. $cluster[0] . '-overview.md (workflow/memory-writing.md, "When does a cluster get an overview?")';
		}
	}

	return $errors;
}

/**
 * The references in a memory file's text to memory files that do not exist,
 * each once, in the order met: a [[name]] link (a file name without .md, in
 * any folder) and a folder/file.md path whose folder is in FOLDERS, with or
 * without docs/memory/ in front. A path inside another path (assets/ui/x.md),
 * a wildcard or placeholder (erp/<name>.md), an Obsidian embed (![[x]])
 * and a [[name]] quoted alone in backticks as an example are not references.
 * $existing lists folder/file.md for every file in the memory folders,
 * generated indexes included.
 */
function memory_index_broken_links($content, $existing) {

	$paths = array_flip($existing);
	$names = array();

	foreach ($existing as $path) {
		$names[basename($path, '.md')] = true;
	}

	$folders = implode('|', array_map('preg_quote', array_keys(FOLDERS)));
	$pattern = '~(?<![\w./<*-])(?:docs/memory/)?((?:' . $folders . ')/[A-Za-z0-9_.-]+\.md)(?![\w*<>-])|(?<![!`])\[\[([a-z0-9-]+)\]\](?!`)~';

	preg_match_all($pattern, $content, $references, PREG_SET_ORDER);

	$broken = array();

	foreach ($references as $reference) {
		if (isset($reference[2]) && $reference[2] !== '') {
			if (!isset($names[$reference[2]])) {
				$broken['[[' . $reference[2] . ']]'] = true;
			}
		} elseif (!isset($paths[$reference[1]])) {
			$broken[$reference[1]] = true;
		}
	}

	return array_keys($broken);
}

/**
 * The array with $prefix put before each key.
 */
function memory_index_prefixed($items, $prefix) {

	$result = array();

	foreach ($items as $key => $value) {
		$result[$prefix . $key] = $value;
	}

	return $result;
}

/**
 * The lines of $content over MAX_LINE_CHARS characters that do not stand
 * word for word in $base, the same file where the branch left development
 * (null: a new or renamed file, every long line counts). A line written
 * more often than at the base counts for each extra copy. Returns
 * array(array(line number, line), ...), in file order.
 */
function memory_index_long_lines($content, $base) {

	$known = array();

	if ($base !== null) {
		foreach (explode("\n", str_replace("\r", '', (string) $base)) as $line) {
			$known[$line] = isset($known[$line]) ? $known[$line] + 1 : 1;
		}
	}

	$long = array();

	foreach (explode("\n", str_replace("\r", '', (string) $content)) as $i => $line) {
		if (mb_strlen($line, 'UTF-8') <= MAX_LINE_CHARS) {
			continue;
		}
		if (!empty($known[$line])) {
			$known[$line]--;
			continue;
		}
		$long[] = array($i + 1, $line);
	}

	return $long;
}

/**
 * The long lines of a folder README's hand-written key rules block, as
 * memory_index_long_lines() finds them, numbered in the README; the
 * generated part is not counted. $base is the README at the base, null
 * when there was none.
 */
function memory_index_readme_long_lines($content, $base) {

	$block = function ($text) {
		$lines = explode("\n", str_replace("\r", '', (string) $text));
		$starts = array_keys(array_map('trim', $lines), KEY_RULES_START, true);
		$ends = array_keys(array_map('trim', $lines), KEY_RULES_END, true);
		if (count($starts) !== 1 || count($ends) !== 1 || $ends[0] < $starts[0]) {
			return null;
		}
		return array($starts[0] + 1, array_slice($lines, $starts[0] + 1, $ends[0] - $starts[0] - 1));
	};

	$now = $block($content);

	if ($now === null) {
		return array();
	}

	$then = ($base === null) ? null : $block($base);
	$long = array();

	foreach (memory_index_long_lines(implode("\n", $now[1]), ($then === null) ? null : implode("\n", $then[1])) as $found) {
		$long[] = array($found[0] + $now[0], $found[1]);
	}

	return $long;
}

/**
 * The error lines for the long lines of one file: its path, the line
 * number, the length and the line's start, so the line is found.
 */
function memory_index_long_line_errors($path, $long) {

	$errors = array();

	foreach ($long as $found) {
		$errors[] = $path . ':' . $found[0] . ': a line of ' . mb_strlen($found[1], 'UTF-8') . ' characters, more than '
			. MAX_LINE_CHARS . '; split it ("' . mb_substr($found[1], 0, 60, 'UTF-8') . '...")';
	}

	return $errors;
}

/**
 * Runs git in $repository; its standard output (null when git is missing or
 * fails), with every byte kept.
 */
function memory_index_git($repository, $args) {

	$command = 'git -C ' . escapeshellarg($repository);
	foreach ($args as $arg) {
		$command .= ' ' . escapeshellarg($arg);
	}

	if (!function_exists('proc_open')) {
		return null;
	}

	$process = @proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (!is_resource($process)) {
		return null;
	}

	$out = stream_get_contents($pipes[1]);
	stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return (proc_close($process) === 0) ? (string) $out : null;
}

/**
 * The commit the long-line rule compares with: $given when set, else where
 * HEAD left origin/development. null, with $error set, when there is none
 * (no git, no origin/development, a shallow clone without the fork point).
 */
function memory_index_base($repository, $given, &$error) {

	$error = '';

	if ($given !== '') {
		$sha = memory_index_git($repository, array('rev-parse', '--verify', '--quiet', $given . '^{commit}'));
		if ($sha === null || trim($sha) === '') {
			$error = "no commit $given here";
			return null;
		}
		return trim($sha);
	}

	$sha = memory_index_git($repository, array('merge-base', 'HEAD', 'origin/development'));

	if ($sha === null || trim($sha) === '') {
		$error = 'no git, or no common commit with origin/development';
		return null;
	}

	return trim($sha);
}

/**
 * The files changed under docs/memory since $base, committed or not, new
 * ones included, as path => true (paths from the repository root).
 */
function memory_index_changed_files($repository, $base) {

	$changed = array();
	$lists = array(
		memory_index_git($repository, array('-c', 'core.quotePath=false', 'diff', '-z', '--name-only', '--no-renames', $base, '--', 'docs/memory')),
		memory_index_git($repository, array('-c', 'core.quotePath=false', 'ls-files', '-z', '--others', '--exclude-standard', '--', 'docs/memory')),
	);

	foreach ($lists as $list) {
		foreach (explode("\0", (string) $list) as $path) {
			if ($path !== '') {
				$changed[$path] = true;
			}
		}
	}

	return $changed;
}

/**
 * A file as it stood at $base, null when it did not exist there (a new or
 * renamed file).
 */
function memory_index_git_file($repository, $base, $path) {

	return memory_index_git($repository, array('show', $base . ':' . $path));
}
