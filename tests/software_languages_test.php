<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's languages (includes/fn/software_languages.php): the shipped
 * list cut to the wording on disk, the codes config.software_language can
 * store and the migration step that widens its ENUM, and the places that
 * read the list instead of naming English and Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A throwaway includes/local with the given language folders, each holding
// one shard ('' for an empty folder).
function pg_test_software_languages_local($folders)
{
	$dir = sys_get_temp_dir() . '/pg_software_languages_' . uniqid('', true);
	mkdir($dir);

	foreach ($folders as $code => $shard) {
		mkdir($dir . '/' . $code);

		if ($shard !== '') {
			file_put_contents($dir . '/' . $code . '/' . $shard, '{}');
		}
	}

	return $dir;
}

function pg_test_software_languages_cleanup($dir)
{
	foreach (glob($dir . '/*/*.json') as $file) {
		unlink($file);
	}

	foreach (glob($dir . '/*', GLOB_ONLYDIR) as $folder) {
		rmdir($folder);
	}

	rmdir($dir);
}

// The shipped install offers English and Turkish, with their own names.
function test_software_languages_shipped()
{
	$languages = pg_software_languages();

	pg_assert_same(array('en', 'tr'), array_keys($languages));
	pg_assert_same('Türkçe', $languages['tr']['name']);
	pg_assert_same('ltr', $languages['tr']['dir']);
	pg_assert_same(1, $languages['tr']['first_day']);
	pg_assert_same(0, $languages['en']['first_day']);
}

// A language whose wording is not on disk is not offered; English needs none.
function test_software_languages_cut_to_wording()
{
	$dir = pg_test_software_languages_local(array('tr' => ''));
	pg_assert_same(array('en'), array_keys(pg_software_languages($dir)), 'an empty Turkish folder is no wording');
	pg_assert_false(pg_software_language_offered('tr', $dir));
	pg_test_software_languages_cleanup($dir);

	$dir = pg_test_software_languages_local(array('tr' => 'a.json', 'de' => 'a.json'));
	pg_assert_same(array('en', 'tr'), array_keys(pg_software_languages($dir)), 'a folder outside the shipped list is not offered');
	pg_assert_true(pg_software_language_offered('tr', $dir));
	pg_assert_false(pg_software_language_offered('de', $dir));
	pg_test_software_languages_cleanup($dir);
}

function test_software_languages_offered()
{
	pg_assert_true(pg_software_language_offered('en'));
	pg_assert_true(pg_software_language_offered('tr'));
	pg_assert_false(pg_software_language_offered('de'), 'storable, not offered');
	pg_assert_false(pg_software_language_offered('TR'));
	pg_assert_false(pg_software_language_offered(''));
	pg_assert_false(pg_software_language_offered('undefined'));
	pg_assert_false(pg_software_language_offered('../tr'));
	pg_assert_false(pg_software_language_offered(array('tr')));
}

function test_software_languages_from_header()
{
	pg_assert_same('tr', pg_software_language_from_header('tr-TR,tr;q=0.9,en;q=0.8'));
	pg_assert_same('en', pg_software_language_from_header('en-US,en;q=0.9'));
	pg_assert_same('tr', pg_software_language_from_header('de-DE, tr;q=0.5'), 'the first offered tag');
	pg_assert_same('en', pg_software_language_from_header('de-DE,fr'), 'none offered: English');
	pg_assert_same('en', pg_software_language_from_header(''));
}

function test_software_languages_first_day()
{
	pg_assert_same(1, pg_software_language_first_day('tr'));
	pg_assert_same(0, pg_software_language_first_day('en'));
	pg_assert_same(0, pg_software_language_first_day('xx'));
}

function test_software_languages_options_html()
{
	$html = pg_software_language_options_html('tr');

	pg_assert_same('<option value="en" lang="en">English</option><option value="tr" selected lang="tr">Türkçe</option>', $html);
}

// Every offered code can be stored, and the migration step's ENUM is the
// list of storable codes, English first so the default stays.
function test_software_languages_enum_step()
{
	$codes = pg_software_language_codes();

	pg_assert_same(17, count($codes));
	pg_assert_same('en', $codes[0]);
	pg_assert_same(array(), array_values(array_diff(array_keys(pg_software_language_catalog()), $codes)), 'every shipped language is storable');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_software_language_codes();', $source);
	pg_assert_true((bool) preg_match('/install_modify_column\(\'config\', \'software_language\', "ENUM\(([^)]*)\) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'en\'"\)/', $source, $enum), 'the step widens the ENUM');
	pg_assert_same("'" . implode("','", $codes) . "'", isset($enum[1]) ? $enum[1] : '', 'the ENUM lists the storable codes');
	pg_assert_false((bool) preg_match('/software_language[^\n]*VARCHAR/i', $source), 'never a VARCHAR');
}

// The places that named English and Turkish one by one read the list.
function test_software_languages_readers()
{
	$router = (string) file_get_contents(PG_FUNCTIONS_DIR . '/router.php');
	pg_assert_contains('pg_software_language_offered($_GET[\'local\'])', $router);
	pg_assert_contains('pg_software_language_from_header(', $router);

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains('pg_software_language_offered($_GET[\'local\']', $install);
	pg_assert_contains('pg_software_language_from_header(', $install);
	pg_assert_false(strpos($install, "ENUM('en','tr')") !== false, 'the installer adds no two-language ENUM');

	$cli = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/install_cli.php');
	pg_assert_contains('pg_software_language_offered($language', $cli);

	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
	pg_assert_contains('pg_software_language_options_html(', $prep);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/general.save.php');
	pg_assert_contains('pg_software_language_offered(', $save);

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	pg_assert_contains('pg_software_language_first_day(', $core);
	pg_assert_false(strpos($core, "lang(array('info' => '')) == 'tr'") !== false, 'the week start is a property of the language');
}
