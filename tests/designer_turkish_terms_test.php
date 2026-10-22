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

// 'Shipped' reads 'Sevk edildi', so its partners in the order filters and
// the dashboard order list use the same verb; 'Documents' reads 'Belgeler'
// and the file manager's filter follows it ('Döküman' with ö is a
// misspelling; 'Doküman' is a correct word and stays where it is used).
function test_designer_turkish_terms_shipping_and_documents()
{
	$language = _test_tt_language();
	pg_assert_same('Sevk edildi', $language['Shipped']);
	pg_assert_same('Sevk edilmemiş', $language['Unshipped']);
	pg_assert_same('Henüz sevk edilmedi', $language['Not shipped yet']);
	pg_assert_same('Belgeler', $language['Documents']);
	pg_assert_same('Belgelerim', $language['My Documents']);
	$misspelt = array();
	foreach ($language as $key => $value) {
		if (preg_match('/döküman/iu', $value)) {
			$misspelt[] = $key . ' => ' . $value;
		}
	}
	pg_assert_same(array(), $misspelt);
}

// The news blocks name a headline and its containers 'Haber Başlığı …'.
// 'Haber …' alone is a story (Story List) and 'Manşet' a lead story, so a
// headline part must not read like either, and it must not share a value
// with another short key ('Başlık Satırı' is the table Header Row). The
// link sits directly under a node named 'Haber Başlığı …' and the carousel
// in a row with badges, both deep or narrow in the 250 px layer tree, so
// they drop 'Haber' to stay uncut: 'Başlık Bağlantısı', 'Başlık Karuseli'.
function test_designer_turkish_terms_headline_layer_names()
{
	$language = _test_tt_language();
	$keys = _test_tt_designer_short_keys();
	$owners = array();
	foreach ($keys as $key) {
		if (isset($language[$key])) {
			$owners[$language[$key]][] = $key;
		}
	}
	$headline = array();
	$found = array();
	foreach ($keys as $key) {
		if (!preg_match('/^Headline\b/', $key) || !isset($language[$key])) {
			continue;
		}
		$headline[] = $key;
		$value = $language[$key];
		if (!preg_match('/^(Haber Başlığı|Başlık )/u', $value)) {
			$found[] = $key . ' => ' . $value;
		}
		if (count($owners[$value]) > 1) {
			$found[] = $key . ' shares ' . $value . ' with ' . implode(', ', array_diff($owners[$value], array($key)));
		}
	}
	pg_assert_same(6, count($headline));
	pg_assert_same(array(), $found);
	pg_assert_same('Haber Başlığı', $language['Headline']);
	pg_assert_same('Başlık Bağlantısı', $language['Headline Link']);
	pg_assert_same('Başlık Karuseli', $language['Headline Carousel']);
}
