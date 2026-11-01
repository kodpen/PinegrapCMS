<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Turkish starter dump: the four e-gift card products mail a Turkish subject
 * and body with the lira sign (^^amount^^ is the bare figure), and the
 * Office Chair variant set (product group 48) lists its colour attribute
 * once, as the English dump does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function starter_gift_card_dump($dump)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/data/backups/' . $dump . '/sql.sql');
}

// The dump line that starts with $prefix, or ''.
function starter_gift_card_line($dump, $prefix)
{
	foreach (explode("\n", starter_gift_card_dump($dump)) as $line) {
		if (strpos($line, $prefix) === 0) {
			return $line;
		}
	}

	return '';
}

// The gift card products' e-mail fields: list of array(subject, format, body),
// read from the gift_card flag through gift_card_email_page_id.
function starter_gift_card_emails($dump)
{
	$string = "'((?:[^'\\\\]|\\\\.)*)'";

	preg_match_all('/,1,' . $string . ",'(plain_text|html)'," . $string . ',\d+,/', starter_gift_card_line($dump, 'INSERT INTO `products` VALUES'), $rows, PREG_SET_ORDER);

	$emails = array();

	foreach ($rows as $row) {
		$emails[] = array($row[1], $row[2], $row[3]);
	}

	return $emails;
}

// Rows of product_groups_attributes_xref as "group,attribute,sort,default".
function starter_gift_card_xref_rows($dump)
{
	preg_match_all('/\((\d+,\d+,\d+,\d+)\)/', starter_gift_card_line($dump, 'INSERT INTO `product_groups_attributes_xref` VALUES'), $rows);

	return $rows[1];
}

function test_starter_gift_card_turkish_subject_and_body()
{
	$emails = starter_gift_card_emails('turkish_default');

	pg_assert_same(4, count($emails), 'four gift card products in the Turkish dump');

	foreach ($emails as $i => $email) {
		list($subject, $format, $body) = $email;

		pg_assert_same('₺^^amount^^ Hediye Kartı – [[^^from_name^^||Bir dostunuzdan]]', $subject, 'gift card ' . $i . ': subject');
		pg_assert_same(0, strpos($body, '₺^^amount^^ e-Hediye Kartı\r\n'), 'gift card ' . $i . ': body heading');
		pg_assert_contains('\r\nTeslimat Tarihi: ^^delivery_date^^\r\n', $body, 'gift card ' . $i . ': delivery date line');
		pg_assert_contains('\r\nKimden: [[^^from_name^^||Bir dostunuzdan]]\r\n', $body, 'gift card ' . $i . ': from line');
		pg_assert_contains('Hediye Kartı Kodunuz: ^^code^^', $body, 'gift card ' . $i . ': code line');
	}

	$sql = starter_gift_card_dump('turkish_default');

	pg_assert_false(strpos($sql, 'Anonymous Giver') !== false, 'no English giver fallback in the Turkish dump');
	pg_assert_false(strpos($sql, '$^^amount^^') !== false, 'no dollar sign before ^^amount^^ in the Turkish dump');
}

function test_starter_gift_card_amount_is_a_bare_figure()
{
	// The texts carry the currency sign themselves because the mail fills
	// ^^amount^^ with the number only.
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/order_completion.php');
	$start = strpos($source, "'name' => 'amount',");

	pg_assert_true($start !== false, 'the gift card mail fills ^^amount^^');
	pg_assert_contains("pg_format_number(\$line['price'] / 100, 2)", substr($source, (int) $start, 300), '^^amount^^ is the formatted number without a currency');
}

function test_starter_gift_card_english_texts_unchanged()
{
	$emails = starter_gift_card_emails('english_default');

	pg_assert_same(4, count($emails), 'four gift card products in the English dump');

	foreach ($emails as $i => $email) {
		pg_assert_same('$^^amount^^ Gift Card from [[^^from_name^^|| an Anonymous Giver]]', $email[0], 'English gift card ' . $i . ': subject');
	}
}

function test_starter_gift_card_attribute_rows_once()
{
	$turkish = starter_gift_card_xref_rows('turkish_default');
	$english = starter_gift_card_xref_rows('english_default');

	pg_assert_same(array('43,1,1,0', '48,4,1,0', '56,1,1,0'), $turkish, 'Turkish variant set attributes');
	pg_assert_same($english, $turkish, 'the Turkish rows match the English dump');
	pg_assert_true(strpos(starter_gift_card_dump('turkish_default'), "-- Dumped table `product_groups_attributes_xref` with 3 row(s)\n") !== false, 'the row count note says 3');
}
