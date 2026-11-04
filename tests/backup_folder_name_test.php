<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_backup_folder_name() (includes/fn/backup.php): the folder a
 * Backup Manager backup is written to keeps the letters of the name typed
 * for it, Turkish ones mapped to ASCII, and the name stays the same when a
 * later backup step sends it back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_backup_folder_name_cases()
{
	$cases = array(
		'Şubat Yedeği'                 => 'Subat_Yedegi',
		'İzmir_yedek-1'                => 'Izmir_yedek-1',
		'../../etc/Özet'               => 'etc_Ozet',
		'yedek 03/11/2026'             => 'yedek_03_11_2026',
		'pg-test Yedek ğüşıöçİ "q" <b>x</b>' => 'pg-test_Yedek_gusiocI_q_b_x_b',
		'a\\b'                         => 'a_b',
		'Özet.v2'                      => 'Ozet_v2',
		'..'                           => '',
		'.'                            => '',
		'отчет'                        => '',
		'a.[b]'                        => 'a_b',
		''                             => '',
		'example.com_2026-10-30@12-13' => 'example_com_2026-10-30_12-13',
	);

	foreach ($cases as $name => $expected) {
		pg_assert_same($expected, pg_backup_folder_name($name), $name);
	}
}

// A name past PG_BACKUP_NAME_MAX is cut, and the cut leaves no "_" at the end.
function test_backup_folder_name_length()
{
	pg_assert_same(PG_BACKUP_NAME_MAX, strlen(pg_backup_folder_name('pg-test-' . str_repeat('a', 292))), '300 letters');
	pg_assert_same(str_repeat('a', 99), pg_backup_folder_name(str_repeat('a', 99) . ' bbbb'), 'cut at a separator');
}

// The install dumps, the automatic backups and the pre-upgrade dumps are
// the software's; a hand-made backup may not take their names.
function test_backup_name_reserved()
{
	foreach (array('english_default', 'Turkish_Default', 'auto_backup_2026-11-45', 'pre_upgrade_2026_11_0_x', 'pre-update-2026.11.0-1') as $name) {
		pg_assert_same(true, pg_backup_name_reserved($name), $name);
	}
	foreach (array('b', 'yedek_03_11_2026', 'my_english_default', 'auto_backup') as $name) {
		pg_assert_same(false, pg_backup_name_reserved($name), $name);
	}
}

// Only the two install dumps take the delete confirmation that names the
// lost language choice; other reserved names and archives do not.
function test_backup_is_starter_dump()
{
	foreach (array('english_default', 'Turkish_Default', 'TURKISH_DEFAULT') as $name) {
		pg_assert_same(true, pg_backup_is_starter_dump($name), $name);
	}
	foreach (array('english_default.zip', 'my_english_default', 'auto_backup_2026-11-45', 'pre_upgrade_2026_11_0_x', '', 'b') as $name) {
		pg_assert_same(false, pg_backup_is_starter_dump($name), $name);
	}
}

// Newest first; the same time falls back to the name.
function test_backup_list_order()
{
	pg_assert_same(
		array('new', 'a_same', 'b_same', 'old'),
		pg_backup_list_order(array('old' => 100, 'b_same' => 200, 'new' => 300, 'a_same' => 200))
	);
	pg_assert_same(array('7', '10'), pg_backup_list_order(array('10' => 1, '7' => 2)));
}

// Every backup step sends the name back and computes it again, so a second
// pass must give the same name.
function test_backup_folder_name_is_stable()
{
	foreach (array('Şubat Yedeği', 'x/y\\z', str_repeat('ab ', 60), 'a.[b]', '__x__', 'Özet.v2', 'yedek--1', 'a . b') as $name) {
		$once = pg_backup_folder_name($name);

		pg_assert_same($once, pg_backup_folder_name($once), $name);
		pg_assert_same(1, preg_match('/^[A-Za-z0-9_-]*$/', $once), $name . ' stays in the folder-name class');
	}
}
