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
		'../../etc/Özet'               => 'Ozet',
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

// Every backup step sends the name back and computes it again, so a second
// pass must give the same name.
function test_backup_folder_name_is_stable()
{
	foreach (array('Şubat Yedeği', 'a.[b]', '__x__', 'Özet.v2', 'yedek--1', 'a . b') as $name) {
		$once = pg_backup_folder_name($name);

		pg_assert_same($once, pg_backup_folder_name($once), $name);
		pg_assert_same(1, preg_match('/^[A-Za-z0-9_-]*$/', $once), $name . ' stays in the folder-name class');
	}
}
