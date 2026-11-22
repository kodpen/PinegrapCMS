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

// The retention rules card: a rule's state in the words of How long we keep
// data, the dry run's effect lines, where a sample row opens, and the card
// drawn through the rules' own functions.
function test_privacy_centre_rule_state()
{
	$rules = pg_privacy_rules_normalise(array());

	$off = pg_privacy_centre_rule_state($rules['contacts'], 1, '');
	pg_assert_same('off', $off['state'], 'every rule starts off');
	pg_assert_same(lang('Off: kept until deleted'), $off['line'], 'off line');
	pg_assert_same(null, $off['pill'], 'no pill when off');

	$orders = pg_privacy_centre_rule_state(array('on' => true, 'period' => null, 'approved_at' => 0), 1, '');
	pg_assert_same('off', $orders['state'], 'on without a period does nothing');

	$waiting = pg_privacy_centre_rule_state(array('on' => true, 'period' => 3, 'approved_at' => 0), 1, '');
	pg_assert_same('waiting', $waiting['state'], 'on, without the yes');
	pg_assert_same('pg-tone-amber', $waiting['pill'][0], 'amber while it waits');

	$running = pg_privacy_centre_rule_state(array('on' => true, 'period' => 3, 'approved_at' => 1700000000), 1, '');
	pg_assert_same('running', $running['state'], 'on, with the yes');
	pg_assert_same(lang('Runs every night') . ' · ' . lang('not run yet'), $running['line'], 'every night, not run yet');

	$weekly = pg_privacy_centre_rule_state(array('on' => true, 'period' => 3, 'approved_at' => 1700000000), 7, '1 Nov 2026');
	pg_assert_same(lang(array('string' => 'Runs at night every {var:1} days', 'vars' => array(7))) . ' · ' . lang(array('string' => 'last run {var:1}', 'vars' => array('1 Nov 2026'))), $weekly['line'], 'the interval and the last run');
}

function test_privacy_centre_dry_run_effects()
{
	$skips = pg_privacy_rules_labels()['skips'];

	$none = pg_privacy_centre_dry_run_effects(array('total' => 0, 'counts' => pg_privacy_rule_counts(), 'sample' => array()), $skips, array());
	pg_assert_same(array(array('kind' => 'info', 'text' => lang('Nothing reaches the period now.'), 'line' => '')), $none, 'nothing due');

	$counts = pg_privacy_rule_counts();
	$counts['checked'] = 200;
	$counts['changed'] = 150;
	$counts['deleted'] = array('forms' => 150);
	$counts['anonymised'] = array('orders' => 4);
	$counts['skipped'] = array('open_order' => 30, 'mystery' => 1);
	$effects = pg_privacy_centre_dry_run_effects(array('total' => 450, 'counts' => $counts, 'sample' => array(1, 2)), $skips, array('forms' => 'Form submissions'));

	pg_assert_same('info', $effects[0]['kind'], 'the total first');
	pg_assert_true($effects[0]['line'] !== '', 'says only the first rows were checked');
	pg_assert_same(array('go', 'go', 'keep', 'keep'), array_map(function ($e) { return $e['kind']; }, array_slice($effects, 1)), 'what goes, then who stays');
	pg_assert_same(lang(array('string' => '{var:1}: {var:2} deleted', 'vars' => array('Form submissions', pg_format_number(150, 0)))), $effects[1]['text'], 'by the erase entry\'s label');
	pg_assert_same(lang(array('string' => '{var:1}: {var:2} anonymised', 'vars' => array('orders', pg_format_number(4, 0)))), $effects[2]['text'], 'an entry without a label keeps its key');
	pg_assert_same(lang(array('string' => '{var:1}: {var:2} left alone', 'vars' => array($skips['open_order'], pg_format_number(30, 0)))), $effects[3]['text'], 'skips by their label');

	$all = pg_privacy_rule_counts();
	$all['checked'] = 3;
	$same = pg_privacy_centre_dry_run_effects(array('total' => 3, 'counts' => $all), $skips, array());
	pg_assert_same('', $same[0]['line'], 'every row checked');
	pg_assert_same(lang('None of them would change.'), $same[1]['text'], 'rows found, nothing to do');
}

function test_privacy_centre_rule_sample_url()
{
	pg_assert_same('edit_contact.php?id=7', pg_privacy_centre_rule_sample_url('contacts', '7'), 'a contact');
	pg_assert_same('edit_submitted_form.php?id=8', pg_privacy_centre_rule_sample_url('forms', 8), 'a submission');
	pg_assert_same('view_order.php?id=9', pg_privacy_centre_rule_sample_url('carts', 9), 'a cart');
	pg_assert_same('view_order.php?id=9', pg_privacy_centre_rule_sample_url('orders', 9), 'an order');
	pg_assert_same('privacy_centre.php?view=requests&request=3', pg_privacy_centre_rule_sample_url('requests', 3), 'a request');
	pg_assert_same('', pg_privacy_centre_rule_sample_url('unknown', 3), 'no screen');
}

function privacy_centre_test_card($may_change, $dry = null)
{
	$rules = pg_privacy_rules_normalise(array('contacts' => array('on' => true, 'period' => 3), 'forms' => array('pages' => array(12 => 0))));

	return pg_privacy_centre_rules_card(array(
		'rules'      => $rules,
		'state'      => array(),
		'labels'     => pg_privacy_rules_labels(),
		'forms'      => array(array('id' => 12, 'name' => 'Contact <form>', 'submissions' => 40)),
		'may_change' => $may_change,
		'dry'        => $dry,
		'entries'    => array(),
	));
}

function test_privacy_centre_rules_card_fields()
{
	$html = privacy_centre_test_card(true);

	pg_assert_contains('id="privacy-rules"', $html, 'the anchor Site Settings links to');
	pg_assert_contains('name="action" value="rules_save"', $html, 'saves through rules_save');

	foreach (array_keys(pg_privacy_rules_catalogue()) as $key) {
		pg_assert_contains('name="rules[' . $key . '][on]"', $html, $key . ' switch');
		pg_assert_contains('name="rules[' . $key . '][period]"', $html, $key . ' period');
	}

	pg_assert_contains('name="rules[forms][pages][12]" id="privacy_rule_form_12" value="0"', $html, 'a form\'s own period');
	pg_assert_contains('Contact &lt;form&gt;', $html, 'the form\'s name escaped');
	pg_assert_contains('name="interval"', $html, 'the interval');
	pg_assert_contains('href="privacy_centre.php?dry_run=contacts#privacy_rules_dry_run"', $html, 'a rule switched on has Dry run');
	pg_assert_false(strpos($html, 'dry_run=carts') !== false, 'a rule off has none');
	pg_assert_false(preg_match('/\bbtn\b|nav-tabs/', $html) === 1, 'look A parts only');

	$read = privacy_centre_test_card(false);
	pg_assert_false(strpos($read, 'type="submit"') !== false, 'no Save for a role that only reads');
	pg_assert_contains(' disabled', $read, 'fields read-only');
}

function test_privacy_centre_rules_card_dry_run_and_yes()
{
	$dry = array('key' => 'contacts', 'result' => array('total' => 2, 'counts' => array_merge(pg_privacy_rule_counts(), array('checked' => 2, 'changed' => 1, 'deleted' => array('contacts' => 1), 'skipped' => array('staff' => 1))), 'sample' => array(5)));
	$html = privacy_centre_test_card(true, $dry);

	pg_assert_contains('id="privacy_rules_dry_run"', $html, 'the dry run under the form');
	pg_assert_contains('href="edit_contact.php?id=5"', $html, 'a sample row opens');
	pg_assert_contains('name="action" value="rules_approve"', $html, 'the yes');
	pg_assert_contains('name="rule" value="contacts"', $html, 'for that rule');
	pg_assert_contains('pg-button pg-button-danger', $html, 'the yes is a danger button');

	pg_assert_false(strpos(privacy_centre_test_card(false, $dry), 'rules_approve') !== false, 'no yes for a role that only reads');
}

function test_privacy_centre_screen_calls_the_rules_functions()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/privacy_centre.php');

	foreach (array('validate_token_field();', 'pg_privacy_rules_save(', 'pg_privacy_rule_approve(', 'pg_privacy_rule_dry_run(', 'pg_privacy_rules_labels()', 'pg_privacy_centre_rules_card(') as $call) {
		pg_assert_contains($call, $source, $call);
	}

	pg_assert_true(strpos($source, 'validate_token_field();') < strpos($source, 'pg_privacy_rules_save('), 'the token is checked before the save');
}
