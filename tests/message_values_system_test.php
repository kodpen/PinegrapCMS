<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A liveform message is HTML: the values the messages of the settings and system screens
 * carry from stored records, panel input and remote services go in through
 * h(). Each row is a file, one line of it and how many times that line
 * occurs, so an escape that is taken out again is noticed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_message_values_system_are_escaped()
{
	$lines = array(
		array('api_settings.php', ': lang(array(\'string\' => \'Deliveries to {var:1} were stopped.\', \'vars\' => h($hook[\'url\']))));', 1),
		array('api_settings.php', '? lang(array(\'string\' => \'Deliveries to {var:1} were switched back on.\', \'vars\' => h($hook[\'url\'])))', 1),
		array('backups.php', '\'vars\'=>array(h($dir), filter_var(ini_get(\'html_errors\'), FILTER_VALIDATE_BOOLEAN) ? $e->getMessage() : h($e->getMessage()))', 1),
		array('backups.php', 'lang(array(\'string\'=>\'File ({var:1}) delete is unsuccess\',\'vars\'=>h($dir)))', 1),
		array('backups.php', 'lang(array(\'string\'=>\'File ({var:1}) delete is unsuccess\',\'vars\'=>h($path)))', 1),
		array('backups.php', 'lang(array(\'string\'=>\'Folder ({var:1}) delete is unsuccess\',\'vars\'=>h($dir)))', 1),
		array('backups.php', 'lang(array(\'string\'=>\'Folder ({var:1}) delete is unsuccess\',\'vars\'=>h($path)))', 1),
		array('cloudflare.php', '$msg .= \': \' . h($resp[\'json\'][\'errors\'][0][\'message\']);', 3),
		array('cloudflare.php', '$permission_errors[] = lang(array(\'string\' => \'DNS records request failed: {var:1}\', \'vars\' => h($err)));', 1),
		array('connections.php', '$liveform->add_error(h($flash[\'text\']));', 1),
		array('connections.php', '$liveform->add_error(lang(array(\'string\' => \'{var:1} does not work: {var:2}\', \'vars\' => array(h($account[\'label\']), h($check[\'error\'])))));', 1),
		array('connections.php', '$liveform->add_notice(h($flash[\'text\']));', 1),
		array('connections.php', '$liveform->add_notice(lang(array(\'string\' => \'{var:1} was disconnected.\', \'vars\' => h($account[\'label\']))));', 1),
		array('connections.php', '$liveform->add_notice(lang(array(\'string\' => \'{var:1} works.\', \'vars\' => h($account[\'label\']))));', 1),
		array('database_engine.php', '$liveform->add_error(h($engine_text));', 1),
		array('includes/fn/geo.php', '\'vars\'   => array(($refresh[\'reason\'] !== \'\') ? h($refresh[\'reason\']) : lang(\'unknown error\')))));', 1),
		array('includes/settings/features.save.php', '\'vars\' => h($test_reason),', 1),
		array('includes/settings/firewall.save.php', '$secure_mode_test_link = \'<a class="alert-link" href="https://\' . h(HOSTNAME_SETTING) . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . \'/test_secure_mode.php" target="_blank">\' . lang(\'Secure Mode test page\') . \'</a>\';', 1),
		array('includes/settings/firewall.save.php', '\'vars\'   => array(h(implode(\', \', $waf_country_post_wrong))))));', 1),
		array('includes/settings/firewall.save.php', '\'vars\'   => h($waf_ranges_provider . \' — \' . $waf_ranges_result[\'reason\']),', 1),
		array('includes/settings/general.php', '$liveform->add_error(h($backup_connect_flash[\'text\']));', 1),
		array('includes/settings/general.php', '$liveform->add_notice(h($backup_connect_flash[\'text\']) . \' \' . lang(\'Save the settings to start sending the backups there.\'));', 1),
		array('includes/settings/general.save.php', '\'vars\'   => array(h(implode(\', \', $maintenance_allow_invalid))),', 1),
		array('includes/settings/general.save.php', '\'vars\'   => array(h(implode(\', \', $ops_alert_recipients_invalid))),', 1),
		array('install/index.php', '$liveform->add_notice(lang(array(\'string\' => \'The unfinished restore of the backup {var:1} was discarded.\', \'vars\' => array(h($state[\'folder\'])))));', 1),
		array('install/index.php', '$liveform->mark_error(\'\', h($copy_error));', 1),
		array('install/index.php', '\'vars\' => h($database_version)', 2),
		array('search_console.php', '$liveform->add_error(h($flash[\'text\']));', 1),
		array('search_console.php', '$liveform->add_notice(h($flash[\'text\']));', 1),
		array('smtp_settings.php', '$liveform->add_error(h($connect_flash[\'text\']));', 1),
		array('smtp_settings.php', '$liveform->add_notice(h($connect_flash[\'text\']));', 1),
		array('smtp_settings.php', '$liveform_settings->add_notice(lang([\'string\'=>\'Test email accepted by SMTP server. Please check mailbox: {var:1}\', \'vars\'=>[h(EMAIL_ADDRESS)]]));', 1),
		array('view_waf_log.php', '$liveform->add_notice(lang(array(\'string\' => \'The ban on {var:1} has been released.\', \'vars\' => h($release_ip))));', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
