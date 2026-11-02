<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document a form signature is bound to
 * (includes/fn/signature.php): the answers are read from the stored
 * form_data rows, the same way when the hash is taken and when it is checked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A field with one row is one answer, a field with several rows (a check box
// group, a multiple pick list) is the list of them in row order, and the
// signature's own row is no answer.
function test_signature_document_values_read_the_stored_rows()
{
	$rows = array(
		array('form_field_id' => '3', 'data' => '2026-11-02'),
		array('form_field_id' => '4', 'data' => 'yes'),
		array('form_field_id' => '5', 'data' => 'red'),
		array('form_field_id' => '5', 'data' => 'blue'),
		array('form_field_id' => '9', 'data' => ''),
		array('form_field_id' => '6', 'data' => null),
	);

	pg_assert_same(
		array(3 => '2026-11-02', 4 => 'yes', 5 => array('red', 'blue'), 6 => null),
		pg_signature_document_values($rows, 9));
}

// The check reads the stored rows, so the hash taken when the form is signed
// has to be read from them too: a ticked box is stored as one row, a date in
// its stored form, and either hashes differently from the posted value.
function test_signature_document_hash_from_rows_matches_the_check()
{
	$fields = array(
		array('id' => 3, 'name' => 'day', 'label' => 'Day', 'type' => 'date', 'required' => 0, 'information' => ''),
		array('id' => 4, 'name' => 'agree', 'label' => 'I agree', 'type' => 'check box', 'required' => 1, 'information' => ''),
		array('id' => 9, 'name' => 'sign', 'label' => 'Sign', 'type' => 'signature', 'required' => 1, 'information' => ''),
	);
	$rows = array(
		array('form_field_id' => 3, 'data' => '2026-11-02'),
		array('form_field_id' => 4, 'data' => 'yes'),
		array('form_field_id' => 9, 'data' => ''),
	);

	$stored = pg_signature_document_hash(12, $fields, pg_signature_document_values($rows, 9), 'Sign');
	$posted = pg_signature_document_hash(12, $fields, array(3 => '2/11/2026', 4 => array('yes')), 'Sign');

	pg_assert_true($stored !== $posted, 'the posted values are not what the check reads');
}
