<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rich text editor's Turkish texts (assets/lib/ckeditor_4_20/lang/tr.js,
 * #9120): the media embed dialog in Turkish, an anchor named as an anchor
 * (Çapa) and not as a link (Bağlantı), no closed services in the embed
 * hint, and the stamp that makes a browser fetch a corrected file again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A CKEditor language file (CKEDITOR.lang['xx']={…}; after a header comment)
// as an array, or null when it cannot be read.
function ckeditor_turkish_texts_read($language)
{
	$js = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/ckeditor_4_20/lang/' . $language . '.js');
	$start = strpos($js, "CKEDITOR.lang['" . $language . "']=");

	if ($start === false) {
		return null;
	}

	$json = rtrim(substr($js, $start + strlen("CKEDITOR.lang['" . $language . "']=")));
	$json = rtrim($json, ';');
	$texts = json_decode($json, true);

	return is_array($texts) ? $texts : null;
}

function test_ckeditor_turkish_media_embed_dialog()
{
	$tr = ckeditor_turkish_texts_read('tr');

	pg_assert_true(is_array($tr), 'tr.js reads as one object');
	pg_assert_same("Başlık (ekran okuyucular için) <span class='oembed-required'>[Zorunlu]</span>:", $tr['oembed']['embedTitle']);
	pg_assert_same("URL <span class='oembed-required'>[Zorunlu]</span>:", $tr['oembed']['url']);
	pg_assert_same('Yok', $tr['oembed']['none']);
	pg_assert_same('Boyutlandırma yapma (varsayılanı kullan)', $tr['oembed']['noresize']);
	pg_assert_same('Erişilebilirlik için medya başlığı zorunludur.', $tr['oembed']['titleError']);

	foreach (array('tr', 'en') as $language) {
		$texts = ckeditor_turkish_texts_read($language);

		foreach (array('Qik', 'Hulu', 'Viddler', 'MyOpera') as $closed) {
			pg_assert_false(strpos($texts['oembed']['pasteUrl'], $closed) !== false, $language . ': ' . $closed . ' is not offered');
		}

		pg_assert_contains('YouTube', $texts['oembed']['pasteUrl'], $language . ': the services that still embed');
	}
}

// The flag button and its dialog say Çapa; Link stays Link.
function test_ckeditor_turkish_anchor_is_not_a_link()
{
	$tr = ckeditor_turkish_texts_read('tr');

	pg_assert_same('Çapa Ekle/Düzenle', $tr['link']['anchor']['toolbar']);
	pg_assert_same('Çapa Özellikleri', $tr['link']['anchor']['title']);
	pg_assert_same('Çapa Adı', $tr['link']['anchor']['name']);
	pg_assert_same('Çapayı Kaldır', $tr['link']['anchor']['remove']);
	pg_assert_same('Çapa', $tr['fakeobjects']['anchor']);
	pg_assert_same('Çapa Seç', $tr['link']['selectAnchor']);
	pg_assert_same('Link Ekle/Düzenle', $tr['link']['toolbar'], 'the Link button keeps its name');
	pg_assert_true($tr['link']['anchor']['toolbar'] !== $tr['link']['toolbar'], 'two neighbouring buttons, two names');
}

// The texts that were left in English, and the typo of every shortcut tip.
function test_ckeditor_turkish_leaves_no_english_text()
{
	$tr = ckeditor_turkish_texts_read('tr');
	$en = ckeditor_turkish_texts_read('en');

	pg_assert_same('Üst Karakter Tuşu', $tr['common']['keyboard']['16']);
	pg_assert_same('Sekme sırasından çıkar', $tr['iframe']['tabindex']);
	pg_assert_same('Zengin Metin Editörü', $tr['application']);

	// Texts a Turkish page may show as they are in English: words both
	// languages write the same way.
	$same = array('common.url', 'common.form', 'format.tag_p', 'fakeobjects.iframe', 'iframe.toolbar', 'link.title', 'link.toUrl');
	$left = array();

	$walk = function ($a, $b, $prefix) use (&$walk, &$left, $same) {
		foreach ($a as $key => $value) {
			$name = $prefix . $key;

			if (is_array($value)) {
				$walk($value, isset($b[$key]) && is_array($b[$key]) ? $b[$key] : array(), $name . '.');
			} elseif (isset($b[$key]) && ($b[$key] === $value) && (preg_match('/[a-z]{3}/i', (string) $value) === 1) && !in_array($name, $same, true)) {
				$left[] = $name;
			}
		}
	};

	$walk($en, $tr, '');

	pg_assert_same(array(), $left, 'English texts left in tr.js');
}

// CKEditor loads its language file with its own stamp, which the bundle
// fixes once; the page sets one from the language files' times.
function test_ckeditor_timestamp_follows_the_language_files()
{
	$stamp = pg_ckeditor_timestamp();
	$newest = max((int) filemtime(PG_FUNCTIONS_DIR . '/assets/lib/ckeditor_4_20/lang/en.js'), (int) filemtime(PG_FUNCTIONS_DIR . '/assets/lib/ckeditor_4_20/lang/tr.js'));

	pg_assert_same(strtoupper(base_convert((string) $newest, 10, 36)), $stamp);
	pg_assert_true(preg_match('/^[0-9A-Z]+$/', $stamp) === 1, 'letters and digits only');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/editor.php');
	$at = strpos($src, 'CKEDITOR.timestamp = "\' . pg_ckeditor_timestamp() . \'";');

	pg_assert_true($at !== false, 'the page sets the stamp');
	pg_assert_true($at > strpos($src, '/assets/lib/ckeditor_4_20/ckeditor.js"></script>'), 'after ckeditor.js');
	pg_assert_true($at < strpos($src, 'var software_ckeditor_config = {'), 'before any editor starts');
}
