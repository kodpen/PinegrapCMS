<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the accessibility checks in includes/fn/a11y.php: each rule over
 * small pieces of markup, the document-only rules on output that is not a
 * page, and the finding shape pg_a11y_check_html() stores in seo_issue.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

// A whole page that passes every document rule, around $body.
function a11y_test_page($body)
{
	return '<!DOCTYPE html><html lang="tr"><head><title>Sayfa</title></head><body><main>' . $body . '</main></body></html>';
}

// The codes found in a piece of markup.
function a11y_test_codes($html)
{
	return array_keys(pg_a11y_check_html($html));
}

function test_a11y_clean_page_has_no_findings()
{
	$html = a11y_test_page('<h1>Başlık</h1><h2>Alt</h2><p>Metin <a href="/iletisim">İletişim sayfası</a></p>'
		. '<img src="a.jpg" alt="Kırmızı kapı"><img src="b.jpg" alt="">'
		. '<label for="ad">Ad</label><input id="ad" name="ad"><button>Gönder</button>');

	pg_assert_same(array(), pg_a11y_check_html($html));
}

function test_a11y_every_rule_has_a_function_and_a_known_level()
{
	foreach (pg_a11y_rules() as $code => $rule) {
		pg_assert_same('a11y_', substr($code, 0, 5), $code);
		pg_assert_true(strlen($code) <= 48, $code . ' fits seo_issue.code');
		pg_assert_true(function_exists('pg_a11y_rule_' . substr($code, 5)), $code);
		pg_assert_true(in_array($rule['level'], array('A', 'AA'), true), $code);
		pg_assert_true(in_array($rule['severity'], array('error', 'warning', 'notice'), true), $code);
	}
}

function test_a11y_document_rules()
{
	$codes = a11y_test_codes('<html><head></head><body><p>x</p></body></html>');

	pg_assert_true(in_array('a11y_page_title', $codes, true), 'no title');
	pg_assert_true(in_array('a11y_html_lang', $codes, true), 'no lang');
	pg_assert_true(in_array('a11y_main_missing', $codes, true), 'no main');

	$findings = pg_a11y_check_html('<html lang="turkce dili"><head><title>T</title></head><body><div role="main">x</div></body></html>');
	pg_assert_same(array('a11y_html_lang'), array_keys($findings), 'a lang that is no language tag');
	pg_assert_same('turkce dili', $findings['a11y_html_lang']['detail']);

	pg_assert_same(array(), a11y_test_codes('<html lang="pt-BR"><head><title>T</title></head><body><main>x</main></body></html>'), 'region subtag');
}

// A fragment, or JSON carrying markup, is not a page: no title, lang or main asked.
function test_a11y_document_rules_skip_output_that_is_no_page()
{
	pg_assert_same(array(), a11y_test_codes('<p>Parça</p>'));
	pg_assert_same(array(), a11y_test_codes('{"html":"<b>kalın</b>"}'));
	pg_assert_same(array(), a11y_test_codes('{"ok":true}'));
	pg_assert_same(array('a11y_img_alt'), a11y_test_codes('<div><img src="x.png"></div>'), 'the other rules still run');
}

function test_a11y_heading_order()
{
	$findings = pg_a11y_check_html(a11y_test_page('<h1>A</h1><h2>B</h2><h4>Çok derin</h4><h2>C</h2><h3>D</h3>'));

	pg_assert_same(1, $findings['a11y_heading_order']['occurrences']);
	pg_assert_same('h2 → h4: Çok derin', $findings['a11y_heading_order']['detail']);

	pg_assert_same(array('a11y_heading_order'), a11y_test_codes(a11y_test_page('<h2>B</h2><h3>C</h3><h1>A</h1><h3>yeni bölüm</h3>')), 'h1 then h3 skips');
	pg_assert_same(array(), a11y_test_codes(a11y_test_page('<h2>B</h2><h3>C</h3><h4>D</h4><h2>E</h2>')), 'going back up is fine');
	pg_assert_same(array('a11y_heading_order'), a11y_test_codes(a11y_test_page('<h1>A</h1><div role="heading" aria-level="3">B</div>')), 'aria level');
}

function test_a11y_img_alt()
{
	$findings = pg_a11y_check_html(a11y_test_page('<img src="/files/kapi.jpg"><img src="b.jpg" alt=""><img src="c.jpg" role="presentation">'
		. '<img src="d.jpg" aria-hidden="true"><input type="image" src="git.png" alt=""><map><area href="/a"></map>'));

	pg_assert_same(3, $findings['a11y_img_alt']['occurrences'], 'img without alt, image button with empty alt, area');
	pg_assert_same('/files/kapi.jpg', $findings['a11y_img_alt']['detail']);
}

function test_a11y_form_label()
{
	$html = a11y_test_page('<label for="e">E-posta</label><input id="e" name="email">'
		. '<label>Telefon <input name="tel"></label>'
		. '<input name="ara" aria-label="Ara"><input type="hidden" name="t"><input type="submit">'
		. '<input name="soyad" placeholder="Soyadınız"><select name="il"></select>');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(2, $findings['a11y_form_label']['occurrences'], 'a placeholder is no label');
	pg_assert_same('soyad', $findings['a11y_form_label']['detail']);
}

function test_a11y_idref_broken()
{
	$findings = pg_a11y_check_html(a11y_test_page('<label for="yok">Ad</label><input id="ad" aria-label="Ad" aria-describedby="ipucu gone"><p id="ipucu">İpucu</p>'));

	pg_assert_same(2, $findings['a11y_idref_broken']['occurrences']);
	pg_assert_same('for="yok"', $findings['a11y_idref_broken']['detail']);
}

function test_a11y_error_association()
{
	$html = a11y_test_page('<label for="a">A</label><input id="a" name="a" aria-invalid="true" aria-describedby="a-err"><span id="a-err">Zorunlu alan</span>'
		. '<label for="b">B</label><input id="b" name="b" aria-invalid="true">'
		. '<label for="c">C</label><input id="c" name="c" aria-invalid="false">');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(1, $findings['a11y_error_association']['occurrences']);
	pg_assert_same('b', $findings['a11y_error_association']['detail']);
}

function test_a11y_link_name_and_generic_text()
{
	$html = a11y_test_page('<a href="/sepet"><i class="bi bi-cart"></i></a><a href="/x"><img src="l.png" alt="Ana sayfa"></a>'
		. '<a href="/y" aria-label="Instagram hesabımız"><svg></svg></a><a href="/z">Devamını oku…</a><a href="/w" aria-label="Kargo koşulları">Tıklayın</a>');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(1, $findings['a11y_link_name']['occurrences']);
	pg_assert_same('/sepet', $findings['a11y_link_name']['detail']);
	pg_assert_same(1, $findings['a11y_link_generic']['occurrences'], 'a label that says where wins over the text');
	pg_assert_same('devamını oku', $findings['a11y_link_generic']['detail']);
}

function test_a11y_button_name()
{
	$html = a11y_test_page('<button class="btn btn-close"></button><button aria-label="Kapat"><i class="bi bi-x"></i></button>'
		. '<button><svg><title>Ara</title></svg></button><input type="button" value="Tamam"><input type="button"><div role="button">Aç</div>');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(2, $findings['a11y_button_name']['occurrences']);
	pg_assert_same('button.btn.btn-close', $findings['a11y_button_name']['detail']);
}

function test_a11y_frame_title()
{
	$findings = pg_a11y_check_html(a11y_test_page('<iframe src="https://www.youtube.com/embed/x">Video</iframe><iframe src="/m" title="Harita"></iframe>'));

	pg_assert_same(1, $findings['a11y_frame_title']['occurrences']);
	pg_assert_same('https://www.youtube.com/embed/x', $findings['a11y_frame_title']['detail']);
}

function test_a11y_media_autoplay()
{
	$findings = pg_a11y_check_html(a11y_test_page('<video autoplay muted src="a.mp4"></video><video autoplay><source src="b.mp4"></video><audio autoplay src="c.mp3"></audio><audio src="d.mp3"></audio>'));

	pg_assert_same(2, $findings['a11y_media_autoplay']['occurrences']);
	pg_assert_same('b.mp4', $findings['a11y_media_autoplay']['detail']);
}

function test_a11y_contrast_from_inline_styles()
{
	$html = a11y_test_page('<p style="color:#777;background-color:#fff">Soluk</p>'
		. '<div style="background:#ffffff"><span style="color: #767676">Yeterli</span></div>'
		. '<p style="color:#949494;background-color:#fff;font-size:24px">Büyük yazı</p>'
		. '<p style="color:#949494;background-color:#fff;font-size:18px;font-weight:700">Kalın ama küçük</p>'
		. '<p style="color:#777">Arka planı bilinmiyor</p>'
		. '<p style="color:#777;background:url(a.jpg) #fff">Resim üstünde</p>'
		. '<p style="color:rgba(0,0,0,.4);background-color:#fff">Saydam</p>'
		. '<p style="color:rgb(255, 255, 255);background-color:white">Görünmez</p>');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(3, $findings['a11y_contrast']['occurrences']);
	pg_assert_same('4.47:1 #777777 / #ffffff', $findings['a11y_contrast']['detail']);
}

function test_a11y_target_size()
{
	$html = a11y_test_page('<a href="/a" aria-label="A" style="display:inline-block;width:16px;height:16px"></a>'
		. '<button aria-label="B" style="width:24px;height:24px"></button><a href="/c" style="width:2rem;height:10px">C</a>');
	$findings = pg_a11y_check_html($html);

	pg_assert_same(1, $findings['a11y_target_size']['occurrences']);
	pg_assert_same('16×16', $findings['a11y_target_size']['detail']);
}

// Markup hidden from everyone is not reported.
function test_a11y_hidden_markup_is_left_out()
{
	pg_assert_same(array(), a11y_test_codes(a11y_test_page('<div hidden><img src="a.png"><a href="/x"></a></div><div style="display: none"><button></button></div>')));
}

// What the job stores: one row per code with the first example.
function test_a11y_finding_shape()
{
	$findings = pg_a11y_check_html(a11y_test_page('<img src="a.png"><img src="b.png">'));

	pg_assert_same(array('code' => 'a11y_img_alt', 'severity' => 'error', 'occurrences' => 2, 'detail' => 'a.png'), $findings['a11y_img_alt']);
}
