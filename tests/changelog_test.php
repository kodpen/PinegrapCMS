<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading changelog.txt (pg_changelog_sections() and
 * pg_changelog_preamble() in includes/fn/system_status.php), and for the shape
 * of the changelog.txt that ships with the product: English tags only, and the
 * catch-all fix, security and database lines at most once per version.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Writes $contents to a scratch file, reads it back as sections and removes it.
function pg_changelog_test_sections($contents)
{
	$file_path = sys_get_temp_dir() . '/pg_changelog_test_' . bin2hex(random_bytes(4)) . '.txt';

	file_put_contents($file_path, $contents);

	$sections = pg_changelog_sections($file_path);

	@unlink($file_path);

	return $sections;
}

// The current layout: a rule of equal signs above each version, one line per
// entry, and a note under the closing rule that belongs to no entry.
function test_changelog_reads_rule_above_version()
{
	$sections = pg_changelog_test_sections(
		"  TITLE\n\n    [NEW]   new feature\n\n"
		. "==========\n2026.9.2\n[NEW] Added notes.\n[FIX] Miscellaneous bug fixes and new features.\n"
		. "==========\n2026.9.1\n[SCHEME] A field is added.\n"
		. "==========\n\n  Older versions are not listed.\n");

	pg_assert_same(2, count($sections), 'two versions');
	pg_assert_same('2026.9.2', $sections[0]['version'], 'newest first');
	pg_assert_same(array('NEW'), $sections[0]['entries'][0]['tags'], 'tag of the first entry');
	pg_assert_same('Added notes.', $sections[0]['entries'][0]['text'], 'text of the first entry');
	pg_assert_same(2, count($sections[0]['entries']), 'the next rule ends the section');
	pg_assert_same('A field is added.', $sections[1]['entries'][0]['text'], 'the closing note is not glued on');
}

// Files written before the layout changed keep their rule under the version.
function test_changelog_reads_rule_below_version()
{
	$sections = pg_changelog_test_sections(
		"  2026.9.2\n  ==========\n\n  [YENİ] Notlar eklendi.\n\n"
		. "  2026.9.1\n  ==========\n\n  [ŞEMA] Bir alan eklenir.\n");

	pg_assert_same(2, count($sections), 'two versions');
	pg_assert_same('2026.9.1', $sections[1]['version'], 'second version');
	pg_assert_same(array('ŞEMA'), $sections[1]['entries'][0]['tags'], 'tag of the second version');
}

// Versions released together share one heading, which is kept as written.
function test_changelog_reads_shared_heading()
{
	$sections = pg_changelog_test_sections(
		"==========\n2026.9.3\n[FIX] Miscellaneous bug fixes and new features.\n"
		. "==========\n2026.9.1  /  2026.9.2\n[NEW] Added a report.\n");

	pg_assert_same(2, count($sections), 'two headings');
	pg_assert_same('2026.9.1 / 2026.9.2', $sections[1]['version'], 'shared heading');
	pg_assert_same(1, count($sections[0]['entries']), 'the shared heading does not land in the version above');
}

// The head of the file stops at the rule that opens the first version.
function test_changelog_preamble_stops_at_first_version()
{
	$file_path = sys_get_temp_dir() . '/pg_changelog_test_' . bin2hex(random_bytes(4)) . '.txt';

	file_put_contents($file_path,
		"  TITLE\n\n    [NEW]   new feature\n    [FIX]   bug fixes\n\n"
		. "==========\n2026.9.1\n[NEW] Added notes.\n");

	$preamble = pg_changelog_preamble($file_path);

	@unlink($file_path);

	pg_assert_same('  TITLE', $preamble['banner'], 'banner without the rule');
	pg_assert_same(2, count($preamble['legend']), 'legend rows only');
}

// The shipped file: English tags only, every version readable, and the
// catch-all lines written once per version rather than one line per fix.
function test_changelog_shipped_file_format()
{
	$sections = pg_changelog_sections(PG_FUNCTIONS_DIR . '/changelog.txt');

	pg_assert_true(count($sections) > 0, 'the shipped file has versions');

	$allowed = array('NEW', 'FIX', 'SECURITY', 'SCHEME');

	foreach ($sections as $section) {

		$counts = array('FIX' => 0, 'SECURITY' => 0, 'SCHEME' => 0);

		foreach ($section['entries'] as $entry) {

			foreach ($entry['tags'] as $tag) {

				pg_assert_true(in_array($tag, $allowed, true), $section['version'] . ': tag [' . $tag . ']');

				if (isset($counts[$tag])) {
					$counts[$tag]++;
				}
			}
		}

		foreach ($counts as $tag => $count) {
			pg_assert_true($count <= 1, $section['version'] . ': one [' . $tag . '] line at most');
		}
	}

	$legend = array();

	foreach (pg_changelog_preamble(PG_FUNCTIONS_DIR . '/changelog.txt')['legend'] as $row) {
		$legend[] = $row['tag'];
	}

	pg_assert_same($allowed, $legend, 'the legend lists the four tags');
}
