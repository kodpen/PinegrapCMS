<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish of the designer's short texts: layer names, palette
 * labels and panel rows. One English key is shared by every place that
 * draws it (_sdT() in the designer, lang() in the panel and the design
 * templates), so a wrong Turkish value shows up everywhere at once. These
 * tests pin the shared values that were corrected and keep the designer's
 * own terms from slipping back to English halves (Carousel, Pane, Input).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_tt_language()
{
	static $language = null;
	if ($language === null) {
		$language = json_decode(file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);
	}
	return $language;
}

// The short texts the designer passes to _sdT(): at most four words and no
// sentence punctuation, which is what a layer name, label or row title is.
function _test_tt_designer_short_keys()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	preg_match_all("/_sdT\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $matches);
	$keys = array();
	foreach ($matches[1] as $key) {
		$key = str_replace(array("\\'", '\\\\'), array("'", '\\'), $key);
		if ((str_word_count($key) <= 4) && !preg_match('/[.:!?<]/', $key)) {
			$keys[$key] = true;
		}
	}
	return array_keys($keys);
}

// Shared keys whose Turkish read wrong in one of their places: each value
// now fits every place the key is drawn.
function test_designer_turkish_terms_shared_values()
{
	$language = _test_tt_language();
	$expected = array(
		'Previous control' => 'Önceki Düğmesi',
		'Next control'     => 'Sonraki Düğmesi',
		'Previous'         => 'Önceki',
		'Caption'          => 'Açıklama Yazısı',
		'Figure'           => 'Görsel Bloğu',
		'Carousel Inner'   => 'Karusel İçi',
		'Pause'            => 'Duraklat',
		'Latest'           => 'En son',
		'Maintenance'      => 'Bakım',
		'Language'         => 'Dil',
		'Residential'      => 'Konut',
		'Actions'          => 'Eylemler',
		'Documents'        => 'Belgeler',
		'Disabled'         => 'Devre dışı',
		'Success'          => 'Başarılı',
		'Footer'           => 'Alt Bilgi',
	);
	foreach ($expected as $key => $turkish) {
		pg_assert_same($turkish, isset($language[$key]) ? $language[$key] : null, $key);
	}
}

// 'Done' stays 'Bitti': it is a button that closes a panel and a short state
// beside 'Sürüyor' and 'Sırada'; 'Bitti' reads right in both.
function test_designer_turkish_terms_done_fits_buttons_and_states()
{
	$language = _test_tt_language();
	pg_assert_same('Bitti', $language['Done']);
	pg_assert_same('Sürüyor', $language['In progress']);
	pg_assert_same('Sırada', $language['Up next']);
}

// A carousel is 'Karusel', a tab pane is 'Panel', a form control is
// 'Girdi' and an accordion is 'Akordeon' in every short designer text.
function test_designer_turkish_terms_no_english_halves()
{
	$language = _test_tt_language();
	$rules = array(
		'/\bCarousel\b/' => '/Carousel/',
		'/\bPane\b/'     => '/\bPane\b/',
		'/\bInput\b/i'   => '/\binput/i',
		'/\bAccordion\b/' => '/Akordiyon/',
	);
	$found = array();
	foreach (_test_tt_designer_short_keys() as $key) {
		if (!isset($language[$key])) {
			continue;
		}
		foreach ($rules as $english => $leftover) {
			if (preg_match($english, $key) && preg_match($leftover, $language[$key])) {
				$found[] = $key . ' => ' . $language[$key];
			}
		}
	}
	pg_assert_same(array(), $found);
}

// The flex-wrap select and the carousel's wrap switch both said 'Wrap',
// which is 'Döngü' (loop) for the carousel; the flex value has a key of
// its own so it reads 'Satır Kaydırma' next to 'Satır Kaydırma Yok'.
function test_designer_turkish_terms_flex_wrap_has_own_key()
{
	$language = _test_tt_language();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("['wrap',_sdT('Wrap Lines')]", $source);
	pg_assert_contains("row(_sdT('Wrap'),", $source);
	pg_assert_same('Satır Kaydırma', $language['Wrap Lines']);
	pg_assert_same('Döngü', $language['Wrap']);
}
