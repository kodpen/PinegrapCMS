<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the ids of the Site Settings cards. The settings dialog is
 * printed in the page header, ahead of the screen, and its pane stays in the
 * document once opened; getElementById, jQuery '#id' and <label for> resolve
 * to the first element, so a card field whose id a panel screen also uses
 * takes that screen's lookups. Screen fields also keep off the ids of the
 * panel chrome (top bar, search box).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_card_ids_root()
{
	return dirname(__DIR__) . '/pinegrap';
}

// Ids of form controls, labels' targets and the SEO counters in a source.
function settings_card_ids_controls($source)
{
	preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="([A-Za-z][\w-]*)"/', $source, $controls);
	preg_match_all('/<label\b[^>]*\bfor="([A-Za-z][\w-]*)"/', $source, $labels);
	preg_match_all('/\bid="(seo_c_[\w-]*)"/', $source, $counters);

	return array_values(array_unique(array_merge($controls[1], $labels[1], $counters[1])));
}

function settings_card_ids_screens()
{
	$root = settings_card_ids_root();

	return array_merge(glob($root . '/*.php'), glob($root . '/includes/forms/*.php'));
}

function test_settings_card_ids_unique_on_screens()
{
	// settings/screen.php is drawn only on its own standalone settings pages,
	// never inside the dialog.
	$skip = array('screen.php', 'modal.php', 'fragment.php', 'prep.php', 'registry.php');
	$card_ids = array();

	foreach (glob(settings_card_ids_root() . '/includes/settings/*.php') as $path) {
		$file = basename($path);
		if (substr($file, -9) === '.save.php' || in_array($file, $skip, true)) {
			continue;
		}
		foreach (settings_card_ids_controls((string) file_get_contents($path)) as $id) {
			$card_ids[$id] = $file;
		}
	}

	pg_assert_true(isset($card_ids['setting_title']), 'seo.php: the Web Browser Title field is not found');

	foreach (settings_card_ids_screens() as $path) {
		$source = (string) file_get_contents($path);
		foreach ($card_ids as $id => $file) {
			pg_assert_true(
				!preg_match('/\bid="' . preg_quote($id, '/') . '"/', $source),
				'includes/settings/' . $file . ' id="' . $id . '" is also used by ' . basename($path)
			);
		}
	}
}

function test_settings_card_ids_seo_counters_follow_fields()
{
	$source = (string) file_get_contents(settings_card_ids_root() . '/includes/settings/seo.php');

	foreach (array('setting_title', 'setting_meta_description') as $id) {
		pg_assert_true((bool) preg_match('/id="' . $id . '"/', $source), 'seo.php: no field ' . $id);
		pg_assert_true((bool) preg_match('/<label for="' . $id . '"/', $source), 'seo.php: no label for ' . $id);
		pg_assert_true((bool) preg_match('/id="setting_seo_c_' . substr($id, 8) . '"/', $source), 'seo.php: no counter for ' . $id);
		pg_assert_true(
			(bool) preg_match('/sel:\s*"#' . $id . '",\s*counterId:\s*"setting_seo_c_' . substr($id, 8) . '"/', $source),
			'seo.php: initSeoCounters does not point at ' . $id
		);
	}
	pg_assert_true(strpos($source, 'name="title"') !== false && strpos($source, 'name="meta_description"') !== false, 'seo.php: the field names must stay');
}

function test_settings_card_ids_screens_keep_off_chrome_ids()
{
	foreach (settings_card_ids_screens() as $path) {
		$ids = settings_card_ids_controls((string) file_get_contents($path));
		foreach (array('header', 'query', 'search_results') as $chrome) {
			pg_assert_true(!in_array($chrome, $ids, true), basename($path) . ': a field uses the panel chrome id "' . $chrome . '"');
		}
	}
}
