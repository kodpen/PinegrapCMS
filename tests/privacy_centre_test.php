<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Privacy centre's rows and downloads (includes/fn/privacy_centre.php):
 * every catalogue line becomes a row with the VERBİS columns, recipients and
 * transfers abroad follow the services' data categories, unknown counts stay
 * unknown, the destruction records add up an erasure's counts, and the PDF
 * names no person and says the two sentences once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_centre.php');

function privacy_centre_test_same($text)
{
	return is_array($text) ? $text['string'] : $text;
}

function privacy_centre_test_transfers()
{
	return array(
		'stripe'  => array('name' => 'Stripe', 'country' => 'US', 'data' => array('identity', 'order_payment'), 'personal' => true, 'abroad' => true, 'unknown' => false),
		'aras'    => array('name' => 'Aras Kargo', 'country' => 'TR', 'data' => array('address'), 'personal' => true, 'abroad' => false, 'unknown' => true),
		'weather' => array('name' => 'Weather', 'country' => 'US', 'data' => array(), 'personal' => false, 'abroad' => false, 'unknown' => false),
	);
}

function test_privacy_centre_columns_hold_the_verbis_ones()
{
	$columns = pg_privacy_centre_columns();

	foreach (array('categories', 'purpose', 'ground', 'subjects', 'recipients', 'retention', 'abroad', 'measures') as $key) {
		pg_assert_true(isset($columns[$key]), $key . ' is a column');
	}
}

function test_privacy_centre_rows_cover_every_catalogue_line()
{
	$inventory = pg_privacy_inventory();
	$rows = pg_privacy_centre_rows($inventory, array(), array(), 'privacy_centre_test_same');

	pg_assert_same(count($inventory), count($rows), 'one row a line');

	foreach ($rows as $row) {
		pg_assert_same(array_keys(pg_privacy_centre_columns()), array_keys($row), $row['table'] . ': the columns in order');
		pg_assert_true($row['purpose'] !== '' && $row['ground'] !== '' && $row['retention'] !== '', $row['table'] . ': whole');
		pg_assert_same(null, $row['records'], $row['table'] . ': no count is not 0');
	}
}

function test_privacy_centre_rows_name_recipients_by_their_data()
{
	$inventory = array(
		'orders'   => array('categories' => array('identity', 'address'), 'subjects' => array('customer'), 'purpose' => 'Orders', 'ground' => 'contract', 'retention' => array('orders', 'incomplete_carts')),
		'opt_in'   => array('categories' => array('contact'), 'subjects' => array('visitor'), 'purpose' => 'Lists', 'ground' => 'consent', 'retention' => 'passive_contact'),
	);
	$counts = array('orders' => array('records' => 12, 'people' => 4));
	$rows = pg_privacy_centre_rows($inventory, $counts, privacy_centre_test_transfers(), 'privacy_centre_test_same');

	pg_assert_same('Stripe, Aras Kargo', $rows[0]['recipients'], 'both services meet the order categories');
	pg_assert_same('Stripe (US)', $rows[0]['abroad'], 'only the one abroad');
	pg_assert_same('Rule: orders, Rule: incomplete carts', $rows[0]['retention'], 'a retention list');
	pg_assert_same('Identity, Address', $rows[0]['categories'], 'labels');
	pg_assert_same(12, $rows[0]['records'], 'records');
	pg_assert_same(4, $rows[0]['people'], 'people');
	pg_assert_same('', $rows[1]['recipients'], 'no service receives contact data');
	pg_assert_same('No', $rows[1]['abroad'], 'no transfer abroad');
}

function test_privacy_centre_transfer_rows_skip_no_personal_data_and_put_abroad_first()
{
	$rows = pg_privacy_centre_transfer_rows(privacy_centre_test_transfers(), 'privacy_centre_test_same');

	pg_assert_same(2, count($rows), 'the weather service receives nothing personal');
	pg_assert_same('Stripe', $rows[0]['name'], 'abroad first');
	pg_assert_same('Identity, Order and payment', $rows[0]['data'], 'data labels');
	pg_assert_true($rows[1]['unknown'], 'a state that cannot be told is kept and marked');

	$many = pg_privacy_centre_transfer_rows(array('cdn' => array('name' => 'CDN', 'country' => '*', 'data' => array('ip_cookie'), 'personal' => true, 'abroad' => true, 'unknown' => false)), 'privacy_centre_test_same');
	pg_assert_same('Several countries', $many[0]['country'], 'a network of many countries');
}

function test_privacy_centre_record_counts_add_up_deleted_and_anonymised()
{
	pg_assert_same(array('deleted' => 5, 'anonymised' => 3), pg_privacy_centre_record_counts('{"token":"x","deleted":{"contacts":2,"opt_in":3},"anonymised":{"orders":3}}'), 'an erasure');
	pg_assert_same(array('deleted' => 7, 'anonymised' => 0), pg_privacy_centre_record_counts('{"deleted":{"forms":{"forms":4,"form_data":3}}}'), 'nested a level deeper');
	pg_assert_same(array('deleted' => 0, 'anonymised' => 0), pg_privacy_centre_record_counts(null), 'no after');
	pg_assert_same(array('deleted' => 0, 'anonymised' => 0), pg_privacy_centre_record_counts('not json'), 'broken after');
}

function test_privacy_centre_filename()
{
	pg_assert_same('personal-data-inventory-2026-11-20.xlsx', pg_privacy_centre_filename('xlsx', mktime(12, 0, 0, 11, 20, 2026)), 'xlsx');
	pg_assert_same('personal-data-inventory-2026-11-20.pdf', pg_privacy_centre_filename('PDF"', mktime(12, 0, 0, 11, 20, 2026)), 'only letters');
}

function test_privacy_centre_sheet_has_a_heading_a_column()
{
	$rows = pg_privacy_centre_rows(pg_privacy_inventory(), array(), array(), 'privacy_centre_test_same');
	$sheet = pg_privacy_centre_sheet($rows, 'privacy_centre_test_same');

	pg_assert_same(array_values(pg_privacy_centre_columns()), $sheet['headings'], 'headings');
	pg_assert_same(count($sheet['headings']), count($sheet['rows'][0]), 'a cell a heading');
}

function test_privacy_centre_pdf_says_the_two_sentences_and_escapes()
{
	$inventory = array('contacts' => array('categories' => array('identity'), 'subjects' => array('customer'), 'purpose' => 'People <b>', 'ground' => 'contract', 'retention' => 'until_erased'));
	$rows = pg_privacy_centre_rows($inventory, array(), privacy_centre_test_transfers(), 'privacy_centre_test_same');
	$records = array(array('created_at' => 1700000000, 'username' => 'admin', 'kind' => 'erase', 'label' => '', 'deleted' => 5, 'anonymised' => 3));
	$html = pg_privacy_centre_pdf_html($rows, pg_privacy_centre_transfer_rows(privacy_centre_test_transfers(), 'privacy_centre_test_same'), $records, array('site' => 'example.com', 'prepared_by' => 'admin', 'generated_at' => 1700000000), 'privacy_centre_test_same');

	pg_assert_contains('People &lt;b&gt;', $html, 'escaped');
	pg_assert_contains('not legal advice', $html, 'no legal advice, once');
	pg_assert_same(1, substr_count($html, 'stays in backups'), 'older backups, once');
	pg_assert_contains('One person erased on request', $html, 'the erasure record');
	pg_assert_contains('Stripe', $html, 'the transfers');

	$empty = pg_privacy_centre_pdf_html($rows, array(), array(), array(), 'privacy_centre_test_same');
	pg_assert_contains('No personal data has been erased', $empty, 'no records');
}
