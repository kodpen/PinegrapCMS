<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a signature under an ERP document
 * (includes/erp/signatures.php): the hash of what a quote's signature binds,
 * the seal over the record and the two checks built on them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/signatures.php');

function erp_signature_test_quote()
{
	return array(
		'id' => 7,
		'full_number' => 'PGFT2026000000007',
		'account_id' => 3,
		'currency' => 'TRY',
		'valid_until' => '2026-10-12',
		'subtotal' => 10000,
		'discount_total' => 0,
		'tax_total' => 2000,
		'withholding_total' => 0,
		'grand_total' => 12000,
		'status' => 'open',
		'notes' => 'A note',
		'updated_at' => 1700000000,
		'line_data' => json_encode(array(array('line_no' => 1, 'description' => 'Çalışma', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 20, 'tax_total' => 2000, 'line_total' => 10000))),
	);
}

function erp_signature_test_record()
{
	$record = array(
		'doc_type' => 'quote',
		'doc_id' => 7,
		'file_id' => 41,
		'image_hash' => str_repeat('a', 64),
		'document_hash' => erp_signature_document_hash_quote(erp_signature_test_quote()),
		'signer_name' => 'Ayşe Yılmaz',
		'signed_at' => 1760000000,
		'ip_address' => '203.0.113.9',
		'user_agent' => 'Tablet',
		'user_id' => 1,
	);
	$record['seal'] = erp_signature_seal($record);

	return $record;
}

// The hash is a SHA-256 in hex and the same quote always hashes the same.
function test_erp_signature_document_hash_is_stable()
{
	$hash = erp_signature_document_hash_quote(erp_signature_test_quote());

	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/', $hash));
	pg_assert_same($hash, erp_signature_document_hash_quote(erp_signature_test_quote()));
}

// A line, a figure, the account, the date or the number changed: another hash.
function test_erp_signature_document_hash_follows_what_was_signed()
{
	$hash = erp_signature_document_hash_quote(erp_signature_test_quote());

	$changes = array(
		'grand_total' => 12001,
		'account_id' => 4,
		'valid_until' => '2026-10-13',
		'full_number' => 'PGFT2026000000008',
		'currency' => 'EUR',
		'line_data' => json_encode(array(array('line_no' => 1, 'description' => 'Çalışma', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 20, 'tax_total' => 2000, 'line_total' => 10000))),
	);

	foreach ($changes as $column => $value) {
		$quote = erp_signature_test_quote();
		$quote[$column] = $value;
		pg_assert_true(erp_signature_document_hash_quote($quote) !== $hash, $column . ' changes the hash');
	}
}

// What the signature does not bind - status, notes, the moment it was saved -
// leaves the hash alone, and so does a figure stored as a string.
function test_erp_signature_document_hash_ignores_the_rest()
{
	$hash = erp_signature_document_hash_quote(erp_signature_test_quote());
	$quote = erp_signature_test_quote();
	$quote['status'] = 'accepted';
	$quote['notes'] = 'Another note';
	$quote['updated_at'] = 1800000000;
	$quote['grand_total'] = '12000';

	pg_assert_same($hash, erp_signature_document_hash_quote($quote));
}

// A record as written verifies; any field changed afterwards does not.
function test_erp_signature_verify_detects_an_edited_record()
{
	$record = erp_signature_test_record();

	pg_assert_true(erp_signature_verify($record));

	foreach (array('doc_id' => 8, 'file_id' => 42, 'signer_name' => 'Someone else', 'signed_at' => 1760000001, 'document_hash' => str_repeat('b', 64), 'image_hash' => str_repeat('c', 64), 'ip_address' => '198.51.100.1', 'user_id' => 2) as $column => $value) {
		$edited = $record;
		$edited[$column] = $value;
		pg_assert_false(erp_signature_verify($edited), $column . ' breaks the seal');
	}
}

// No seal, no record: nothing verifies.
function test_erp_signature_verify_without_seal()
{
	$record = erp_signature_test_record();
	$record['seal'] = '';

	pg_assert_false(erp_signature_verify($record));
	pg_assert_false(erp_signature_verify(null));
}

// The document check: matches, differs, or cannot be answered.
function test_erp_signature_document_matches()
{
	$record = erp_signature_test_record();
	$quote = erp_signature_test_quote();

	pg_assert_same(true, erp_signature_document_matches($record, erp_signature_document_hash_quote($quote)));

	$quote['grand_total'] = 1;
	pg_assert_same(false, erp_signature_document_matches($record, erp_signature_document_hash_quote($quote)));

	$record['document_hash'] = '';
	pg_assert_same(null, erp_signature_document_matches($record, erp_signature_document_hash_quote($quote)));
}
