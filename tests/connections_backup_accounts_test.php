<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the accounts the automatic backup sends its remote copy
 * through: Google Drive's, OneDrive's and Dropbox's pages on Connections
 * carry "Connect a … account for backups" under the Updates and maintenance
 * gate, the connection started from the Backups card returns to it, and
 * the card leads to the page without a Connect button of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_backup_accounts_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_backup_accounts_service_of_each_destination()
{
	$registry = pg_connections_registry();

	foreach (pg_backup_cloud_types() as $type => $cloud) {
		pg_assert_true(isset($registry[$cloud['service']]), $type . ': its service is a registry row');
		pg_assert_same('backup', $registry[$cloud['service']]['state']['purpose'], $type . ': the row reads the backup accounts');
		pg_assert_same($cloud['provider'], $registry[$cloud['service']]['state']['provider'], $type . ': of the same provider');
		pg_assert_same($type, pg_backup_cloud_type_of_service($cloud['service']), $type . ': found from its page');
	}

	pg_assert_same('', pg_backup_cloud_type_of_service('google'), 'Google\'s own page is no destination');
	pg_assert_same('', pg_backup_cloud_type_of_service(array('google_drive')), 'not a word');
	pg_assert_same('', pg_backup_cloud_type_of_service(null), 'nothing asked');
}

function test_connections_backup_accounts_addresses()
{
	pg_assert_same('connections.php?service=google_drive&backup_return=1', pg_backup_cloud_connections_url('gdrive'), 'Google Drive\'s page, back to the card');
	pg_assert_same('connections.php?service=dropbox&backup_return=1', pg_backup_cloud_connections_url('dropbox'), 'Dropbox\'s page');
	pg_assert_same('', pg_backup_cloud_connections_url('ftp'), 'FTP has no page');
	pg_assert_same('settings_maintenance.php?backup_connect=onedrive#pgset-backup', pg_backup_cloud_connect_return('onedrive'), 'back to the Backups card');
	pg_assert_same('', pg_backup_cloud_connect_return('s3'), 'no return for another type');

	foreach (array_keys(pg_backup_cloud_types()) as $type) {
		$return = pg_backup_cloud_connect_return($type);
		pg_assert_same($return, pg_connect_return_path($return), $type . ': connect.php keeps the address');
	}
}

function test_connections_backup_accounts_keep_the_maintenance_gate()
{
	foreach (array('google_drive', 'onedrive', 'dropbox') as $id) {
		pg_assert_same(array('status', 'backups', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'maintenance' => true)), $id . ': who may open Updates and maintenance connects an account for backups');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'maintenance' => false)), $id . ': not without the category');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => false, 'maintenance' => true)), $id . ': connect.php connects the site\'s accounts for a manager only');
	}

	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('slack', array('manager' => true, 'maintenance' => true)), 'only the backup destinations have it');
}

function test_connections_backup_accounts_page_and_card()
{
	$page = connections_backup_accounts_test_source('connections.php');

	pg_assert_contains('data-purpose="backup"', $page, 'the page connects for the backup purpose');
	pg_assert_contains("pg_backup_cloud_connect_return(\$backup_type)", $page, 'back to the card when started from it');
	pg_assert_contains("pg_settings_may_open('maintenance', \$user)", $page, 'under the category\'s gate');

	$card = connections_backup_accounts_test_source('includes/settings/maintenance.php');

	pg_assert_false(strpos($card, "pg_connect_start_fields(\$backup_cloud['provider'], 'backup'") !== false, 'the card starts no connection');
	pg_assert_false(strpos($card, 'pgset_backup_connect_') !== false, 'and has no hidden Connect form');
	pg_assert_contains('name="backup_\' . $backup_cloud_type . \'_connection"', $card, 'which account a destination uses stays on the card');
	pg_assert_contains("lang('Connect in Connections')", $card, 'it leads to the page');
}
