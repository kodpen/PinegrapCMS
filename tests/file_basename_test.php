<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_basename() and pg_file_name_stem() (includes/fn/files.php),
 * the last segment of a name a person gave and that segment without its
 * extension, and the doors that take such a name. Before PHP 8.0 basename()
 * and pathinfo() drop the non-ASCII letters a segment starts with in the "C"
 * locale: "Özet.pdf" came in as "zet.pdf", and "отчет.xlsx" became ".xlsx", a
 * hidden file, so it was refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

// The last segment, the same on every PHP version and in every locale: what
// basename() gives on Linux from PHP 8.0.
function test_pg_basename_keeps_the_letters_a_segment_starts_with()
{
	$cases = array(
		'Özet.pdf'             => 'Özet.pdf',
		'Şubat raporu.docx'    => 'Şubat raporu.docx',
		'klasör/Ürünler.xlsx'  => 'Ürünler.xlsx',
		'a/b/отчет.xlsx'       => 'отчет.xlsx',
		'报告.pdf'             => '报告.pdf',
		'report.pdf'           => 'report.pdf',
		'a/b/'                 => 'b',
		'a//b//'               => 'b',
		'/'                    => '',
		''                     => '',
		'dir/.htaccess'        => '.htaccess',
		'X:Özet.pdf'           => 'X:Özet.pdf',
		// "\" is not a separator; a caller that takes it as one turns it into "/".
		'C:\\temp\\x.txt'      => 'C:\\temp\\x.txt',
	);

	foreach ($cases as $path => $expected) {
		$label = json_encode($path, JSON_UNESCAPED_UNICODE);

		pg_assert_same($expected, pg_basename($path), $label);

		if ((PHP_VERSION_ID >= 80000) && (DIRECTORY_SEPARATOR === '/')) {
			pg_assert_same(basename($path), pg_basename($path), $label . ' as basename() on PHP 8');
		}
	}
}

// The segment without its last extension, as pathinfo($path,
// PATHINFO_FILENAME) gives it on Linux from PHP 8.0.
function test_pg_file_name_stem_keeps_the_letters_a_name_starts_with()
{
	$cases = array(
		'Özel tasarım.zip'     => 'Özel tasarım',
		'site/Ürünler.html'    => 'Ürünler',
		'отчет.xlsx'           => 'отчет',
		'Özet.tar.gz'          => 'Özet.tar',
		'README'               => 'README',
		'a/b/'                 => 'b',
		'.htaccess'            => '',
		'name.'                => 'name',
		''                     => '',
	);

	foreach ($cases as $path => $expected) {
		$label = json_encode($path, JSON_UNESCAPED_UNICODE);

		pg_assert_same($expected, pg_file_name_stem($path), $label);

		if ((PHP_VERSION_ID >= 80000) && (DIRECTORY_SEPARATOR === '/')) {
			pg_assert_same(pathinfo($path, PATHINFO_FILENAME), pg_file_name_stem($path), $label . ' as pathinfo() on PHP 8');
		}
	}
}

// A name written entirely in another script is not a hidden file, and a name
// that starts with a non-ASCII letter keeps its extension; refused names stay
// refused.
function test_pg_upload_name_blocked_reads_non_ascii_names()
{
	foreach (array('отчет.xlsx', '报告.pdf', 'Özet.pdf', 'klasör/İzin formu.pdf') as $name) {
		pg_assert_false(pg_upload_name_blocked($name), $name);
	}

	foreach (array('Ö.php', 'отчет.phtml', 'dir/.htaccess', '.env', 'Şablon.php.jpg') as $name) {
		pg_assert_true(pg_upload_name_blocked($name), $name);
	}
}

// A channel takes such a file under the name it was sent with.
function test_ws_upload_inspect_keeps_a_non_ascii_name()
{
	$cyrillic = ws_upload_inspect('отчет.xlsx', "PK\x03\x04 a workbook");
	$turkish = ws_upload_inspect('klasör\\Özet.pdf', '%PDF-1.4 a document');

	pg_assert_true($cyrillic['ok'], 'отчет.xlsx is taken');
	pg_assert_same('отчет.xlsx', $cyrillic['ok'] ? $cyrillic['name'] : '', 'отчет.xlsx keeps its name');
	pg_assert_true($turkish['ok'], 'Özet.pdf is taken');
	pg_assert_same('Özet.pdf', $turkish['ok'] ? $turkish['name'] : '', 'Özet.pdf keeps its first letter');
}

// The doors that read a name a person gave cut it with pg_basename(): a ZIP
// entry, a note attachment, a channel upload, the notes import, an image link
// in a note, the API's file address and the SEO structure check; the
// designer's imports and the installer take its stem with
// pg_file_name_stem().
function test_file_basename_doors_use_pg_basename()
{
	$lines = array(
		array('import_zip.php', "pg_upload_name_blocked(pg_basename(\$archive_item['filename']))", 1),
		array('import_zip.php', "\$name = pg_basename(\$archive_item['filename']);", 1),
		array('includes/workspace/files.php', "\$base = pg_basename(str_replace('\\\\', '/', (string) \$name));", 1),
		array('includes/workspace/files.php', "pg_basename(str_replace('\\\\', '/', (string) \$original_name))", 1),
		array('includes/workspace/notes.php', "pg_basename(str_replace('\\\\', '/', (string) \$original_name))", 1),
		array('includes/workspace/notes_import.php', 'pg_basename(', 7),
		array('includes/workspace/notes_io.php', '$label = pg_basename(rawurldecode(', 1),
		array('includes/api/resources/files.php', '$url_basename = trim(pg_basename($path_part));', 1),
		array('seo_structure.php', '$file_name = pg_basename(', 1),
		array('designer_import.php', "if (\$project === '') \$project = pg_file_name_stem(\$orig_name);", 1),
		array('import_design_zip.php', "if (\$project === '') \$project = pg_file_name_stem(\$orig_name);", 1),
		array('includes/designer_import.php', 'pg_file_name_stem(', 4),
		array('install/index.php', "\$folder_name = pg_file_name_stem(\$upload['name']);", 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}

	// No plain basename() is left in the notes import, whose names are all a
	// person's.
	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');

	pg_assert_same(0, preg_match_all('/(?<![\w>])basename\(/', $import), 'notes_import.php: basename(');

	// Nor a PATHINFO_FILENAME in the designer's import, whose paths are all
	// from the uploaded file.
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

	pg_assert_same(0, substr_count($designer, 'PATHINFO_FILENAME'), 'includes/designer_import.php: PATHINFO_FILENAME');
}
