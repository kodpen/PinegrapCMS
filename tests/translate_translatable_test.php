<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_translatable() in includes/translate/store.php: which
 * values count as text to translate. A value that does not is neither
 * listed nor sent to an engine, and it does not count against a page when
 * the page decides whether the search engines may index it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');

// Words in a language stay translatable, whatever they carry.
function test_translate_translatable_keeps_sentences()
{
	pg_assert_true(pg_tr_translatable('Siparişiniz yarın kargoya verilecek.'), 'a Turkish sentence');
	pg_assert_true(pg_tr_translatable('Free shipping on every order'), 'an English sentence');
	pg_assert_true(pg_tr_translatable('Fiyat: 1.250,00 TL, 3 taksit'), 'numbers and a price');
	pg_assert_true(pg_tr_translatable('Ayrıntılar için https://kodpen.com adresine bakın.'), 'a sentence with an address in it');
	pg_assert_true(pg_tr_translatable('Bize info@kodpen.com adresinden yazın'), 'a sentence with an e-mail address in it');
	pg_assert_true(pg_tr_translatable('Read the <a href="https://kodpen.com/docs">documentation</a> first'), 'inline markup with a link');
	pg_assert_true(pg_tr_translatable('Merhaba ^^ad^^, siparişiniz hazır'), 'a sentence around a field token');
	pg_assert_true(pg_tr_translatable('Data: 2024 yılı raporu'), 'a label that starts with Data:');
	pg_assert_true(pg_tr_translatable('[Yeni] Kış koleksiyonu'), 'brackets that are not JSON');
	pg_assert_true(pg_tr_translatable('The function (shown below) adds two numbers'), 'the word function in a sentence');
	pg_assert_true(pg_tr_translatable('Ankara => İstanbul seferleri'), 'an arrow in a sentence');
}

// A data: URI is a file written out as text: an inline SVG icon, an image.
function test_translate_translatable_skips_data_uris()
{
	pg_assert_false(pg_tr_translatable('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z" fill="none" stroke="currentColor"/></svg>'), 'an SVG icon');
	pg_assert_false(pg_tr_translatable('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3C%2Fsvg%3E'), 'an encoded SVG icon');
	pg_assert_false(pg_tr_translatable(' DATA:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'a PNG, upper case, a space before');
	pg_assert_false(pg_tr_translatable('data:,Hello%2C%20World'), 'no media type');
	pg_assert_false(pg_tr_translatable(pg_tr_normalize('data:image/svg+xml,<svg viewBox="0 0 24 24"><title>Araç</title></svg>', 'inline')), 'an SVG icon read as inline markup');
}

// A JSON object or array is a setting, a package, a list: not words.
function test_translate_translatable_skips_json()
{
	pg_assert_false(pg_tr_translatable('{"v":1,"uses":["pdf","zip"],"items":[{"name":"Birleştir","url":"https://kodpen.com/araclar"}]}'), 'a package');
	pg_assert_false(pg_tr_translatable('["Kırmızı", "Mavi", "Yeşil"]'), 'an array of words');
	pg_assert_false(pg_tr_translatable("{\n  \"title\": \"Merhaba\"\n}"), 'spread over lines');
	pg_assert_true(pg_tr_translatable('{Merhaba} dünya'), 'braces that are not JSON');
}

// A lone address stays translatable: a site can give each language its own
// address (a support mailbox, a localized page), and a reviewed translation
// of it must keep being drawn and stay editable.
function test_translate_translatable_keeps_lone_addresses()
{
	pg_assert_true(pg_tr_translatable('https://kodpen.com/araclar/pdf-birlestir?kaynak=ana-sayfa'), 'a URL');
	pg_assert_true(pg_tr_translatable('http://example.com'), 'an http URL');
	pg_assert_true(pg_tr_translatable('//cdn.example.com/icons/tool.svg'), 'a protocol-relative URL');
	pg_assert_true(pg_tr_translatable('www.kodpen.com'), 'a www address');
	pg_assert_true(pg_tr_translatable('mailto:info@kodpen.com'), 'a mailto link');
	pg_assert_true(pg_tr_translatable('destek@firma.com.tr'), 'an e-mail address');
	pg_assert_true(pg_tr_translatable('<a href="https://kodpen.com">https://kodpen.com</a>'), 'a link whose text is its address');
	pg_assert_true(pg_tr_translatable('www sitemiz yenilendi'), 'www as a word');
}

// Code inside a sentence, or a sentence that names a tag, is still a
// sentence: only code that makes up the whole value is data.
function test_translate_translatable_keeps_sentences_with_code()
{
	pg_assert_true(pg_tr_translatable(pg_tr_normalize('Rengi değiştirmek için <code>.btn { color: red; }</code> kuralını sayfanın stiline ekleyin.', 'inline')), 'inline code with a CSS rule');
	pg_assert_true(pg_tr_translatable(pg_tr_normalize('Butona tıklanınca çalışması için <code>function init() { start(); }</code> yazın ve kaydedin.', 'inline')), 'inline code with a function');
	pg_assert_true(pg_tr_translatable(pg_tr_normalize('Diziyi dolaşmak için <code>items.forEach((x) =&gt; { log(x); })</code> kullanın.', 'inline')), 'inline code with an arrow function');
	pg_assert_true(pg_tr_translatable(pg_tr_normalize('Kısayol: <kbd>Ctrl</kbd> + <kbd>S</kbd> ile kaydedin; {var:1} kez deneyin.', 'inline')), 'keys in a sentence');
	pg_assert_true(pg_tr_translatable('HTML <script> etiketi nedir, nasıl kullanılır?'), 'a heading that names the script tag');
	pg_assert_true(pg_tr_translatable('CSS <style> etiketiyle sayfaya stil ekleme'), 'a heading that names the style tag');
	pg_assert_true(pg_tr_translatable('Toplam => {var:1} TL'), 'an arrow before a placeholder');
	pg_assert_true(pg_tr_translatable('Seçenekler { renk: kırmızı; beden: M }'), 'braces around words');
	pg_assert_true(pg_tr_translatable('Showing {var:1} of {var:2}'), 'an English sentence with placeholders');
	pg_assert_true(pg_tr_translatable('Function keys {var:1}'), 'a placeholder after the word function');
}

// A custom HTML paragraph that names an escaped tag still gives its text.
function test_translate_translatable_html_segment_naming_a_tag()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

	$segments = pg_tr_html_segments('<p>HTML &lt;script&gt; etiketi sayfaya kod ekler.</p>');

	pg_assert_same(1, count($segments), 'one segment');
}

// Code: a script, a style sheet, a function.
function test_translate_translatable_skips_code()
{
	pg_assert_false(pg_tr_translatable('<script>window.dataLayer = window.dataLayer || [];</script>'), 'a script element');
	pg_assert_false(pg_tr_translatable('<style>.tool-card { color: red; }</style>'), 'a style element');
	pg_assert_false(pg_tr_translatable('function (a, b) { return a + b; }'), 'an anonymous function');
	pg_assert_false(pg_tr_translatable('document.addEventListener("click", function(event) { event.preventDefault(); });'), 'a listener');
	pg_assert_false(pg_tr_translatable('items.map((item) => { return item.name; })'), 'an arrow function');
	pg_assert_false(pg_tr_translatable('const total = prices.reduce((sum, price) => sum + price, 0);'), 'an arrow function ending a statement');
	pg_assert_false(pg_tr_translatable('.tool-card .title { font-weight: 600; color: #333; }'), 'a CSS rule');
	pg_assert_false(pg_tr_translatable(pg_tr_normalize('items.map((item) => { return item.name; })', 'inline')), 'an arrow function read as inline markup');
}

// The rules that were there before: tokens, codes, no letters, Lorem ipsum.
function test_translate_translatable_keeps_earlier_rules()
{
	pg_assert_false(pg_tr_translatable(''), 'empty');
	pg_assert_false(pg_tr_translatable('^^ad^^'), 'a field token alone');
	pg_assert_false(pg_tr_translatable('{var:1}'), 'a placeholder alone');
	pg_assert_false(pg_tr_translatable('KT-1'), 'a stock code');
	pg_assert_false(pg_tr_translatable('M20/B'), 'a model number');
	pg_assert_false(pg_tr_translatable('1.250,00'), 'no letters');
	pg_assert_false(pg_tr_translatable('Lorem ipsum dolor sit amet, consectetur adipiscing elit.'), 'Lorem ipsum');
	pg_assert_true(pg_tr_translatable('SEO'), 'a word of capitals without a digit');
}
