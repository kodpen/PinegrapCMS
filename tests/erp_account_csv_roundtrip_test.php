<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an ERP Accounts (CSV) export loads back through the account
 * import (includes/erp/import.php). The export writes 'both' as "Customer
 * and supplier", which the import refused as an unrecognised Type, and its
 * headings are written in the panel language while the import only knew the
 * current translation: an English "Payment Term (days)", "Reminder
 * threshold (days)" or "Remind the customer" heading on a Turkish site, or
 * a Turkish "Hatırlatma eşiği (gün)" or "Eyalet / İl" on an English one,
 * stayed unmapped and an update kept the stored values.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/import.php');

/**
 * The English source keys of the lang() calls between two markers of the
 * export source.
 *
 * @param string $from
 * @param string $to
 * @return array
 */
function erp_account_csv_roundtrip_keys($from, $to)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');
	$start = strpos($src, $from);
	pg_assert_true($start !== false, 'the export source holds ' . $from);
	$end = strpos($src, $to, $start + strlen($from));
	pg_assert_true($end !== false, 'the export source holds ' . $to . ' after ' . $from);

	preg_match_all("/lang\\('([^']+)'\\)/", substr($src, $start, $end - $start), $matches);

	return $matches[1];
}

/**
 * A key and its Turkish wording.
 *
 * @param string $key
 * @return array
 */
function erp_account_csv_roundtrip_wordings($key)
{
	$tr = pg_test_language_map('tr');
	pg_assert_true(isset($tr[$key]), 'the Turkish shards hold ' . $key);

	return array($key, $tr[$key]);
}

// Every heading the accounts file writes, but the balance and the record
// number, names its field in English and in Turkish, whatever the language
// of the importing site.
function test_erp_account_csv_headings_load_back_in_both_languages()
{
	$expected = array(
		'Name' => 'title', 'Type' => 'kind', 'Taxpayer' => 'is_person', 'Tax Office' => 'tax_office',
		'E-mail Address' => 'email', 'Phone Number' => 'phone', 'Address' => 'address', 'District' => 'district',
		'City' => 'city', 'State / Province' => 'state', 'Postal Code' => 'postcode', 'Country' => 'country_code',
		'Currency' => 'currency', 'Status' => 'status', 'Notes' => 'notes', 'Payment Term (days)' => 'payment_days',
		'Reminder threshold (days)' => 'overdue_notify_days', 'Remind the customer' => 'overdue_notify_customer',
	);

	$keys = array_values(array_diff(erp_account_csv_roundtrip_keys("case 'csv_accounts':", 'case '), array('Balance', 'Account ID')));
	pg_assert_same(array_keys($expected), $keys, 'the test knows every heading the accounts file writes');

	// The tax id heading comes from erp_tax_id_label(), by country.
	$expected['VKN / TCKN'] = 'tax_number';
	$expected['Tax number'] = 'tax_number';

	// The same first-claim lookup erp_import_guess_field() builds, without
	// the current label it adds on a site.
	$lookup = array();
	foreach (erp_import_field_synonyms() as $field => $synonyms) {
		foreach ($synonyms as $synonym) {
			$key = erp_import_normalize_header($synonym);
			if (($key !== '') && !isset($lookup[$key])) {
				$lookup[$key] = $field;
			}
		}
	}

	foreach ($expected as $key => $field) {
		foreach (erp_account_csv_roundtrip_wordings($key) as $heading) {
			$normalized = erp_import_normalize_header($heading);
			pg_assert_same($field, isset($lookup[$normalized]) ? $lookup[$normalized] : '', 'the heading "' . $heading . '" maps onto ' . $field);
		}
	}
}

// The kind, taxpayer and status words the file writes parse back, in
// English and in Turkish.
function test_erp_account_csv_words_load_back_in_both_languages()
{
	$keys = erp_account_csv_roundtrip_keys('function erp_export_account_words()', "\n}\n");
	pg_assert_same(array('Customer', 'Supplier', 'Customer and supplier', 'Person', 'Company', 'Active', 'Passive'), $keys, 'the test knows every word the accounts file writes');

	$cases = array(
		array('erp_import_parse_kind', 'Customer', 'customer'),
		array('erp_import_parse_kind', 'Supplier', 'supplier'),
		array('erp_import_parse_kind', 'Customer and supplier', 'both'),
		array('erp_import_parse_status', 'Active', 'active'),
		array('erp_import_parse_status', 'Passive', 'passive'),
	);

	foreach ($cases as $case) {
		foreach (erp_account_csv_roundtrip_wordings($case[1]) as $word) {
			pg_assert_same($case[2], $case[0]($word), $case[0] . '("' . $word . '")');
		}
	}

	foreach (array('Person' => 1, 'Company' => 0) as $key => $value) {
		foreach (erp_account_csv_roundtrip_wordings($key) as $word) {
			pg_assert_same($value, erp_import_parse_is_person($word), 'erp_import_parse_is_person("' . $word . '")');
		}
	}

	foreach (array('Yes' => 1, 'No' => 0) as $key => $value) {
		foreach (erp_account_csv_roundtrip_wordings($key) as $word) {
			pg_assert_same($value, erp_import_parse_yes_no($word), 'erp_import_parse_yes_no("' . $word . '")');
		}
	}

	pg_assert_same('both', erp_import_parse_kind('Supplier and customer'), 'the reversed English pair is both');
}
