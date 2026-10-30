<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Turkish letter case in the panel: the Turkish title case the
 * Paraşüt export writes customer names in (includes/fn/contacts.php), and
 * the screens that change the case of a letter through the panel
 * language's rules instead of a case mapping that does not know Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_turkish_title_case_keeps_dotted_and_dotless_i()
{
	pg_assert_same('Ayşe Yıldız Işık', pg_turkish_title_case('AYŞE YILDIZ IŞIK'), 'I lowers to ı');
	pg_assert_same('İlker İnce', pg_turkish_title_case('İLKER İNCE'), 'İ lowers to i, no combining dot');
	pg_assert_same('İrem Işıl', pg_turkish_title_case('irem ışıl'), 'i rises to İ, ı to I');
	pg_assert_same('Ümit Çağlar-Öz', pg_turkish_title_case('ümit ÇAĞLAR-ÖZ'), 'each part of a joined name');
	pg_assert_same('Ali  Veli', pg_turkish_title_case('ali  veli'), 'spaces stay as typed');
	pg_assert_same('', pg_turkish_title_case(''), 'empty stays empty');
}

function test_upper_words_raises_each_first_letter()
{
	pg_assert_same('Product Form', pg_upper_words('product form'), 'each word');
	pg_assert_same('ÜRün Formu', pg_upper_words('üRün formu'), 'a multibyte first letter, the rest as it is');
}

function test_turkish_letter_case_screens_use_the_language_rules()
{
	$root = PG_FUNCTIONS_DIR . '/';

	$widget = file_get_contents($root . 'includes/dashboard/widgets/widget_7.php');
	pg_assert_true(strpos($widget, 'mb_strtoupper(') === false, 'activity card letters go through pg_initials_upper()');

	$edit_user = file_get_contents($root . 'edit_user.php');
	pg_assert_contains("pg_initials_upper(mb_substr(\$identity_name, 0, 1, 'UTF-8'))", $edit_user, 'identity letter goes through pg_initials_upper()');

	$chat = file_get_contents($root . 'assets/js/chat_backend.src.js');
	pg_assert_true(strpos($chat, '.charAt(0).toUpperCase()') === false, 'chat fallback letter uses the page language');
	pg_assert_contains('toLocaleUpperCase(document.documentElement.lang || undefined)', $chat, 'chat fallback letter uses the page language');
	pg_assert_contains('toLocaleUpperCase(document.documentElement.lang', file_get_contents($root . 'assets/js/chat_backend.min.js'), 'the served twin too');

	$edit_product = file_get_contents($root . 'edit_product.php');
	pg_assert_true(strpos($edit_product, '{var:1|c} is required') === false, 'Product ID / SKU error keeps its own case');

	$add_field = file_get_contents($root . 'add_field.php');
	pg_assert_true(strpos($add_field, 'ucwords($form_type_name)') === false, 'Product Form line is not cased byte by byte');

	$orders = file_get_contents($root . 'edit_orders.php');
	pg_assert_contains('pg_turkish_title_case(', $orders, 'Paraşüt names in Turkish title case');
}
