<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact importer (import_contacts.php and its cell reader
 * pg_import_contact_value() in includes/fn/ecommerce.php): yes/no, dates,
 * lengths, the export's formula guard undone, repeated headings and
 * Turkish headings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('DATE_FORMAT')) {
	define('DATE_FORMAT', 'day_month');
}

// import_contacts.php is a screen, so its convert_column_name() is read out
// of the source and loaded under another name.
function contact_import_test_convert($heading)
{
	if (!function_exists('contact_import_test_convert_column_name')) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_contacts.php');
		preg_match('/^function convert_column_name\(.*?^\}/ms', $source, $match);
		eval(str_replace('function convert_column_name(', 'function contact_import_test_convert_column_name(', $match[0]));
	}

	return contact_import_test_convert_column_name($heading);
}

function test_contact_import_yes_no()
{
	foreach (array('1', 'Evet', 'EVET', 'yes', 'True', 'E', 'y') as $yes) {
		$read = pg_import_contact_value('opt_in', $yes);
		pg_assert_same('1', $read['value'], $yes);
		pg_assert_same('', $read['problem'], $yes . ' has no problem');
	}

	foreach (array('0', 'Hayır', 'HAYIR', 'no', 'false', 'h') as $no) {
		pg_assert_same('0', pg_import_contact_value('affiliate_approved', $no)['value'], $no);
	}

	$read = pg_import_contact_value('opt_in', '', 0, 'Opt In');
	pg_assert_same(array('value' => '0', 'problem' => ''), $read, 'empty is no, without a problem');

	$read = pg_import_contact_value('opt_in', 'belki', 0, 'Opt In');
	pg_assert_same('0', $read['value'], 'unknown is no');
	pg_assert_contains('belki', $read['problem'], 'unknown is named');
	pg_assert_contains('Opt In', $read['problem'], 'with its heading');
}

function test_contact_import_dates()
{
	pg_assert_same('2026-12-31', pg_import_contact_value('expiration_date', '31.12.2026')['value'], 'dotted');
	pg_assert_same('2026-12-31', pg_import_contact_value('expiration_date', '31/12/2026')['value'], 'site order');
	pg_assert_same('2026-12-31', pg_import_contact_value('expiration_date', '2026-12-31')['value'], 'ISO as exported');
	pg_assert_same(array('value' => '0000-00-00', 'problem' => ''), pg_import_contact_value('expiration_date', '0000-00-00'), 'no end as exported');
	pg_assert_same(array('value' => '0000-00-00', 'problem' => ''), pg_import_contact_value('expiration_date', ''), 'empty');

	$read = pg_import_contact_value('expiration_date', '31/12/27', 0, 'Expiration Date');
	pg_assert_same('0000-00-00', $read['value'], 'a two-digit year is no date');
	pg_assert_contains('31/12/27', $read['problem']);
}

function test_contact_import_lengths_and_guard()
{
	$read = pg_import_contact_value('business_zip_code', '34710-12345', 10, 'Business Zip Code');
	pg_assert_same('34710-1234', $read['value'], 'cut to the column');
	pg_assert_contains('10', $read['problem']);

	$read = pg_import_contact_value('company', str_repeat('Ş', 50), 50);
	pg_assert_same(array('value' => str_repeat('Ş', 50), 'problem' => ''), $read, 'characters, not bytes');

	pg_assert_same('=HYPERLINK("x")', pg_import_contact_value('company', "'=HYPERLINK(\"x\")")['value'], 'the export guard is undone');
	pg_assert_same('+90 532', pg_import_contact_value('mobile_phone', "'+90 532")['value'], 'a guarded phone');
	pg_assert_same("'quoted", pg_import_contact_value('company', "'quoted")['value'], 'an apostrophe before text stays');
	pg_assert_same('12.50', pg_import_contact_cell('affiliate_commission_rate', '12,5'), 'the rate cell as before');

	pg_assert_same(array('company' => 50, 'business_zip_code' => 10), pg_import_column_lengths(array(
		array('Field' => 'id', 'Type' => 'int(10) unsigned'),
		array('Field' => 'company', 'Type' => 'varchar(50)'),
		array('Field' => 'business_zip_code', 'Type' => 'VARCHAR(10)'),
		array('Field' => 'expiration_date', 'Type' => 'date'),
	)));
}

// Outlook writes Title and Job Title, Location and Office Location: the
// second of each is left out instead of naming the column twice.
function test_contact_import_repeated_headings()
{
	$names = array('first_name', 'title', 'title', false, 'office_location', 'office_location', 'email_address');
	$unique = pg_import_unique_columns($names);

	pg_assert_same(array(0 => 'first_name', 1 => 'title', 4 => 'office_location', 6 => 'email_address'), $unique['columns']);
	pg_assert_same(array(2, 5), $unique['repeated']);

	pg_assert_same('title', contact_import_test_convert('Title'));
	pg_assert_same('title', contact_import_test_convert('Job Title'));
}

function test_contact_import_turkish_headings()
{
	$expected = array(
		'Ad' => 'first_name', 'Adı' => 'first_name', 'Soyad' => 'last_name', 'SOYADI' => 'last_name',
		'E-posta' => 'email_address', 'E-Posta Adresi' => 'email_address', 'Şirket' => 'company',
		'Unvan' => 'title', 'Cep Telefonu' => 'mobile_phone', 'İş Telefonu' => 'business_phone',
		'Adres' => 'business_address_1', 'Şehir' => 'business_city', 'İl' => 'business_state', 'IL' => 'business_state',
		'Posta Kodu' => 'business_zip_code', 'Ülke' => 'business_country', 'Açıklama' => 'description',
		'AÇIKLAMA' => 'description', 'Not' => 'description', 'İleti İzni' => 'opt_in', 'Üye Kimliği' => 'member_id',
		'Bitiş Tarihi' => 'expiration_date', 'Hitap' => 'salutation',
	);

	foreach ($expected as $heading => $column) {
		pg_assert_same($column, contact_import_test_convert($heading), $heading);
	}

	// The export's own headings and the English ones still match.
	pg_assert_same('member_id', contact_import_test_convert('Member ID'), 'an English I stays i');
	pg_assert_same('email_address', contact_import_test_convert('email_address'));
	pg_assert_same('business_city', contact_import_test_convert('City'));
	pg_assert_same(false, contact_import_test_convert('Doğum Günü'), 'an unknown heading');
}

function test_contact_import_screen()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_contacts.php');

	pg_assert_contains('accept=".csv,.txt,text/csv"', $source, 'the file box suggests CSV');
	pg_assert_contains('A file exported from the contacts list can be imported as it is.', $source, 'the screen names the export');
	pg_assert_contains('pg_import_unique_columns($column_names)', $source, 'repeated headings are left out');
	pg_assert_same(2, substr_count($source, '$contact_values($row, $line)'), 'both import modes read the cells with their problems');
}

function test_contact_import_affiliate_code_taken()
{
	$taken = function ($code) {
		return (mb_strtolower($code) === 'abc123');
	};

	$read = pg_import_affiliate_code('ABC123', 'Affiliate Code', $taken);
	pg_assert_same('', $read['value'], 'a code another contact holds is left empty');
	pg_assert_true($read['problem'] !== '', 'the row names the code it left out');

	pg_assert_same(array('value' => 'NEW1', 'problem' => ''), pg_import_affiliate_code('NEW1', 'Affiliate Code', $taken), 'a free code is kept');

	$asked = false;
	$spy = function ($code) use (&$asked) {
		$asked = true;
		return true;
	};
	pg_assert_same(array('value' => '', 'problem' => ''), pg_import_affiliate_code('', 'Affiliate Code', $spy), 'an empty cell stays empty');
	pg_assert_same(false, $asked, 'an empty code is not looked up');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_contacts.php');
	pg_assert_contains('pg_import_affiliate_code(', $source, 'the contact importer checks the code');
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');
	pg_assert_contains('pg_import_affiliate_code(', $source, 'the user importer checks the code');
}
