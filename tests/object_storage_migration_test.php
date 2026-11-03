<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Storage card's migration tool: the switch's modes, what each
 * button makes of them, the state key of each direction, the usage sums and
 * the store's name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_storage.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/object_storage.php');

function test_object_storage_migration_mode()
{
	pg_assert_same(array('direction' => '', 'paused' => false), pg_file_storage_mode(''), 'every site starts off');
	pg_assert_same(array('direction' => '', 'paused' => false), pg_file_storage_mode('0'));
	pg_assert_same(array('direction' => 'to', 'paused' => false), pg_file_storage_mode('1'), 'the switch OS3 wrote');
	pg_assert_same(array('direction' => 'to', 'paused' => true), pg_file_storage_mode('paused'));
	pg_assert_same(array('direction' => 'back', 'paused' => false), pg_file_storage_mode('back'));
	pg_assert_same(array('direction' => 'back', 'paused' => true), pg_file_storage_mode('back_paused'));
	pg_assert_same(array('direction' => '', 'paused' => false), pg_file_storage_mode(null));
}

function test_object_storage_migration_mode_after()
{
	pg_assert_same('1', pg_file_storage_mode_after('', 'move'));
	pg_assert_same('1', pg_file_storage_mode_after('back_paused', 'move'), 'moving again turns the walk round');
	pg_assert_same('back', pg_file_storage_mode_after('1', 'back'));
	pg_assert_same('paused', pg_file_storage_mode_after('1', 'pause'));
	pg_assert_same('back_paused', pg_file_storage_mode_after('back', 'pause'));
	pg_assert_same('1', pg_file_storage_mode_after('paused', 'resume'));
	pg_assert_same('back', pg_file_storage_mode_after('back_paused', 'resume'));
	pg_assert_same(null, pg_file_storage_mode_after('', 'pause'), 'nothing to pause');
	pg_assert_same(null, pg_file_storage_mode_after('', 'resume'));
	pg_assert_same(null, pg_file_storage_mode_after('paused', 'pause'), 'already paused');
	pg_assert_same(null, pg_file_storage_mode_after('1', 'resume'), 'already running');
	pg_assert_same(null, pg_file_storage_mode_after('1', 'delete'), 'an unknown button');
}

function test_object_storage_migration_state_key()
{
	pg_assert_same('object_storage_move', pg_file_move_state_key('to'));
	pg_assert_same('object_storage_back', pg_file_move_state_key('back'));
}

function test_object_storage_migration_usage_sum()
{
	pg_assert_same(
		array('local_files' => 0, 'local_bytes' => 0.0, 'stored_files' => 0, 'stored_bytes' => 0.0),
		pg_file_storage_usage_sum(array()),
		'no files'
	);

	$usage = pg_file_storage_usage_sum(array(
		array('storage' => 'local', 'files' => '4', 'bytes' => '1000'),
		array('storage' => 's3', 'files' => '2', 'bytes' => '5000'),
		array('storage' => null, 'files' => '1', 'bytes' => null),
	));

	pg_assert_same(5, $usage['local_files'], 'an unknown place counts as local');
	pg_assert_same(1000.0, $usage['local_bytes']);
	pg_assert_same(2, $usage['stored_files']);
	pg_assert_same(5000.0, $usage['stored_bytes']);
}

function test_object_storage_migration_store_name()
{
	pg_assert_same('media (s3.example.com)', pg_os_store_name(array('bucket' => 'media', 'endpoint' => 'https://s3.example.com:9000')));
	pg_assert_same('media', pg_os_store_name(array('bucket' => 'media', 'endpoint' => '')));
	pg_assert_same('', pg_os_store_name(array('bucket' => '', 'endpoint' => 'https://s3.example.com')));
}
