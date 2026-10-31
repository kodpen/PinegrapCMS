<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Links into Site Settings after the categories were regrouped: every link the
 * code writes to a card names a card its category lists, the texts that send
 * people to a place name it as the dialog shows it, and the helpers no screen
 * calls are gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/helpers/language_map.php';

function settings_deep_links_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Category key => the card ids its 'sections' list, read from the source:
// pg_settings_categories() asks the database.
function settings_deep_links_test_cards()
{
	$source = settings_deep_links_test_source('includes/settings/registry.php');
	$cards = array();

	preg_match_all("/\n        '([a-z_]+)' => array\(\n            'label'.*?'sections' => array\((.*?)\n            \),/s", $source, $match);

	foreach ($match[1] as $i => $key) {
		preg_match_all("/'(pgset-[a-z0-9-]+)'/", $match[2][$i], $ids);
		$cards[$key] = $ids[1];
	}

	return $cards;
}

// Every link to a card in the product's PHP and script sources, as
// array(file, category, card): pg_settings_link() and
// pg_settings_return_url() calls, settings_<category>.php#pgset- addresses
// and #settings/<category>/<card> fragments.
function settings_deep_links_test_links()
{
	$skip = '~/(data|assets/lib|includes/(phpmailer|stripe|iyzipay-php|phpexcel|boxpacker|dompdf))/|\.min\.js$~';
	$patterns = array(
		"/pg_settings_(?:link|return_url)\(\s*'([a-z_]+)'\s*,\s*'(pgset-[a-z0-9-]+)'/",
		'/settings_([a-z_]+)\.php#(pgset-[a-z0-9-]+)/',
		'/#settings\/([a-z_]+)\/(pgset-[a-z0-9-]+)/',
	);

	$links = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {

		$path = str_replace('\\', '/', $file->getPathname());

		if (!preg_match('/\.(php|js)$/', $path) || preg_match($skip, $path)) {
			continue;
		}

		$source = (string) file_get_contents($path);

		foreach ($patterns as $pattern) {
			if (preg_match_all($pattern, $source, $match, PREG_SET_ORDER)) {
				foreach ($match as $hit) {
					$links[] = array(substr($path, strlen(PG_FUNCTIONS_DIR) + 1), $hit[1], $hit[2]);
				}
			}
		}
	}

	return $links;
}

function test_settings_deep_links_name_a_listed_card()
{
	$cards = settings_deep_links_test_cards();
	$links = settings_deep_links_test_links();

	pg_assert_same(13, count($cards), 'the registry\'s categories are read');
	pg_assert_true(count($links) >= 30, 'the links are found (' . count($links) . ')');

	foreach ($links as $link) {
		list($file, $category, $card) = $link;

		pg_assert_true(
			isset($cards[$category]) && in_array($card, $cards[$category], true),
			$file . ' links ' . $category . ' › ' . $card . ', a card that category lists');
	}
}

function test_settings_deep_links_licence_texts_name_the_software_card()
{
	$source = settings_deep_links_test_source('includes/workspace/ai.php');
	$map = pg_test_language_map('tr');

	pg_assert_false(strpos($source, 'under Settings › General') !== false, 'no text names the old place');
	pg_assert_contains("lang('Site Settings › General › Software')", $source);

	foreach (array(
		'Site Settings › General › Software',
		'The subscription key under Site Settings › General › Software is not entered.',
		'Pinegrap AI refused the subscription key under Site Settings › General › Software: it does not match this site or it is not valid.',
	) as $key) {
		pg_assert_contains('Site Ayarları › Genel › Yazılım', isset($map[$key]) ? $map[$key] : '', 'Turkish: ' . $key);
	}
}

function test_settings_deep_links_old_places_left_comments_and_helpers()
{
	pg_assert_contains('Site Settings › Updates and maintenance › Backups', settings_deep_links_test_source('includes/connect/purposes/backup.php'));
	pg_assert_false(strpos(settings_deep_links_test_source('includes/fn/maintenance_mode.php'), 'Settings › General saves') !== false, 'the maintenance comment no longer puts the card in General');

	$registry = settings_deep_links_test_source('includes/settings/registry.php');

	pg_assert_false(strpos($registry, 'function pg_settings_status(') !== false, 'pg_settings_status() had no caller');
	pg_assert_false(strpos($registry, 'function pg_settings_facts(') !== false, 'pg_settings_facts() had no caller');
	pg_assert_false(strpos(settings_deep_links_test_source('get_help_url.php'), "'private_label'") !== false, 'the redirect screen has no help row');
}
