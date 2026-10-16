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
 * The index has two levels so a session reads little: docs/memory/README.md
 * lists the memory folders (FOLDERS below) and the other documents;
 * docs/memory/<folder>/README.md lists that folder's files, one
 * line each (file name and description). A session opens the top index, one
 * folder index and then only the files the task needs. The indexes are
 * generated rather than edited so that parallel branches that each add a
 * memory file never conflict on them.
 *
 * What is read:
 *   docs/memory/**.md   frontmatter required; name equals the file name;
 *                       at most MAX_MEMORY_BYTES bytes per file
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
 *         outside a known folder, or, with --check, an index differs from
 *         what the files produce.
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

// Memory folders and what they hold, as listed in the top index.
const FOLDERS = array(
	'commerce' => 'Orders, cart, checkout, shipping, products and variants, express order',
	'core' => 'Code rules, file layout, translation, roles and access, security, api.php, request parameters',
	'data' => 'Table structures and SQL pitfalls',
	'designer' => 'Visual designer, system widgets, data binding, designer forms, member widgets, content roles, HTML import',
	'erp' => 'ERP (accounting) module: invoices, payments, waybills, cash, e-documents, reports',
	'plans' => 'Unfinished and shelved plans: remaining work, decisions made, open questions, where to start',
	'platform' => 'Server config, router, redirects, updates and TLS, system status, images and uploads, setup templates',
	'release' => 'Versions, migrations and upgrade steps, change log rules, release status',
	'ui' => 'Panel screens, settings modal, dashboard, panel JavaScript pitfalls',
	'visitors' => 'Visitor statistics, page and article views, search engine index flags',
	'waf' => 'Firewall (waf.php): rules, bans, rate limits, bots, logs, security headers',
	'workflow' => 'Project overview, owner rules, git and branches, sandbox and dev machine',
	'workspace' => 'Workspace module: channels, tasks, plan board, rights, Claude in channels',
);

$check = in_array('--check', array_slice($argv, 1), true);

$repository = dirname(__DIR__);
$docs = $repository . '/docs';

$errors = array();
$folders = array();
$others = array();
$names = array();

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

	if ($in_memory) {
		$bytes = strlen($content);
		if ($bytes > MAX_MEMORY_BYTES) {
			$errors[] = "docs/$relative: $bytes bytes, more than " . MAX_MEMORY_BYTES . '; split the topic';
		}
		if ($name !== '' && isset($names[$name])) {
			$errors[] = "docs/$relative: name \"$name\" is also used by docs/" . $names[$name];
		}
		$names[$name] = $relative;
	}

	if ($in_memory) {
		$parts = explode('/', $relative);
		if (count($parts) !== 3 || !isset(FOLDERS[$parts[1]])) {
			$errors[] = "docs/$relative: memory files go one level deep into a folder listed in FOLDERS (tools/memory_index.php)";
			continue;
		}
		$folders[$parts[1]][$parts[2]] = $description;
	} else {
		$others[$relative] = $description;
	}
}

if ($errors) {
	foreach ($errors as $error) {
		fwrite(STDERR, $error . "\n");
	}
	fwrite(STDERR, count($errors) . " problem(s); the memory index was not written.\n");
	exit(1);
}

// Index path relative to docs/ => expected content.
$indexes = array('memory/README.md' => memory_index_top($folders, $others));

foreach ($folders as $folder => $entries) {
	$indexes["memory/$folder/README.md"] = memory_index_folder($folder, $entries);
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
foreach (glob($docs . '/memory/*/README.md') as $path) {

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
 * The generated indexes themselves are left out.
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

		if ($relative === 'memory/README.md' || (strncmp($relative, 'memory/', 7) === 0 && basename($relative) === 'README.md')) {
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
 */
function memory_index_top($folders, $others) {

	$lines = array(
		'# Memory index',
		'',
		'<!-- Generated by php tools/memory_index.php from the frontmatter of each file. Do not edit by hand. -->',
		'',
		'Open the index of the folder the task needs, then only the files you need.',
		'',
		'## Memory folders (docs/memory/)',
		'',
	);

	foreach (FOLDERS as $folder => $about) {
		if (isset($folders[$folder])) {
			$lines[] = '- ' . $folder . '/README.md: ' . $about . ' (' . count($folders[$folder]) . ')';
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
 * A folder index: one line per file, sorted by file name.
 */
function memory_index_folder($folder, $entries) {

	uksort($entries, 'strcmp');

	$lines = array(
		'# docs/memory/' . $folder . '/',
		'',
		'<!-- Generated by php tools/memory_index.php from the frontmatter of each file. Do not edit by hand. -->',
		'',
		FOLDERS[$folder] . '.',
		'',
	);

	foreach ($entries as $entry => $description) {
		$lines[] = '- ' . $entry . ': ' . $description;
	}

	return implode("\n", $lines) . "\n";
}
