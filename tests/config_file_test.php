<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for writing data/config.php (includes/fn/core.php): a value goes into
 * the file as a PHP string that reads back exactly as it was given, the
 * installation's config.php runs with any database or SMTP password, and a
 * define added to the file later lands before its closing tag only.
 *
 * The files are run in a separate PHP process, because a config.php defines
 * constants and this process cannot define them twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Values that broke config.php, or were read back changed, when they were
// written between single quotes as they are.
function config_file_test_values()
{
	return array(
		"it's",
		'ends with a backslash\\',
		"a\\'b",
		'x?>y',
		'$1 \\1 {$x} \\\\',
		"line\nbreak\r\nand\ttab",
		'üñí€ şğı',
		'',
	);
}

// Runs $content as a config.php in a separate PHP process. Returns
// array('output' => what the file printed, 'values' => constant => value, or
// null for one it did not define) for each name in $constants, or a string
// with what PHP said when the file did not run cleanly (a parse error, a
// warning such as a constant defined twice).
//
// The code that includes the file and reports back is a script file of its
// own, not a -r argument: on Windows escapeshellarg() turns every " in an
// argument into a space, and the code would no longer parse.
function config_file_test_run($content, $constants)
{
	$file = tempnam(sys_get_temp_dir(), 'pg_config_test_');
	$runner = tempnam(sys_get_temp_dir(), 'pg_config_run_');

	file_put_contents($file, $content);

	file_put_contents($runner, '<?php ob_start(); include $argv[1]; $output = ob_get_clean(); $values = array();'
		. ' foreach (array_slice($argv, 2) as $name) { $values[$name] = defined($name) ? constant($name) : null; }'
		. ' echo json_encode(array("output" => $output, "values" => $values));');

	$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($runner) . ' ' . escapeshellarg($file);

	foreach ($constants as $name) {
		$command .= ' ' . escapeshellarg($name);
	}

	$result = (string) shell_exec($command . ' 2>&1');

	unlink($file);
	unlink($runner);

	$decoded = json_decode($result, true);

	return is_array($decoded) ? $decoded : $result;
}

// The values a file defines, or the failure as the assertion's message.
function config_file_test_values_of($content, $constants, $label)
{
	$run = config_file_test_run($content, $constants);

	if (!is_array($run)) {
		pg_assert_same('the file runs', trim($run), $label);
		return array();
	}

	pg_assert_same('', $run['output'], $label . ': prints nothing');

	return $run['values'];
}

// Every value comes back from the file byte for byte, whatever it holds.
function test_config_file_define_line_reads_back_every_value()
{
	$content = '<?php' . "\n";
	$expected = array();

	foreach (config_file_test_values() as $index => $value) {
		$content .= pg_config_define_line('PG_TEST_VALUE_' . $index, $value) . "\n";
		$expected['PG_TEST_VALUE_' . $index] = $value;
	}

	$content .= '?>';

	pg_assert_same($expected, config_file_test_values_of($content, array_keys($expected), 'define lines'));

	pg_assert_same("'it\\'s'", pg_config_php_string("it's"), 'the quote is escaped');
	pg_assert_same("'a\\\\'", pg_config_php_string('a\\'), 'the backslash is escaped');
	pg_assert_same("'12'", pg_config_php_string(12), 'a number is written as text');
}

// A line goes before the closing tag at the end, not into a value that holds a closing tag.
function test_config_file_insert_line_uses_the_last_closing_tag()
{
	$content = "<?php\n" . pg_config_define_line('DB_PASSWORD', 'x?>y') . "\n?>\n";

	$inserted = pg_config_insert_line($content, pg_config_define_line('PG_TEST_ADDED', 'a?>b'));

	pg_assert_same("<?php\ndefine('DB_PASSWORD', 'x?>y');\ndefine('PG_TEST_ADDED', 'a?>b');\r\n?>\n", $inserted, 'text');

	pg_assert_same(
		array('DB_PASSWORD' => 'x?>y', 'PG_TEST_ADDED' => 'a?>b'),
		config_file_test_values_of($inserted, array('DB_PASSWORD', 'PG_TEST_ADDED'), 'inserted'));

	// "$1" and "\1" in the line are not back-references.
	$literal = pg_config_insert_line("<?php ?>", pg_config_define_line('PG_TEST_ADDED', '$1 \\1'));

	pg_assert_same("<?php define('PG_TEST_ADDED', '$1 \\\\1');\r\n?>", $literal, 'line with $1');
}

// A file without a closing tag gets the line at its end; text that is not PHP
// is left as it is rather than given a line it would print.
function test_config_file_insert_line_without_a_closing_tag()
{
	$content = "<?php\ndefine('DB_HOST', 'db');";

	$inserted = pg_config_insert_line($content, "define('PG_TEST_ADDED', true);");

	pg_assert_same("<?php\ndefine('DB_HOST', 'db');\r\ndefine('PG_TEST_ADDED', true);\r\n", $inserted, 'appended');

	pg_assert_same(
		array('DB_HOST' => 'db', 'PG_TEST_ADDED' => true),
		config_file_test_values_of($inserted, array('DB_HOST', 'PG_TEST_ADDED'), 'appended'));

	pg_assert_same('', pg_config_insert_line('', "define('PG_TEST_ADDED', true);"), 'empty text');
	pg_assert_same('not php', pg_config_insert_line('not php', "define('PG_TEST_ADDED', true);"), 'not php');
}

// The settings screens' update_config_define() keeps a password that holds
// a closing tag when it adds a define, and writes a new value with a quote or a
// backslash so that it reads back.
function test_config_file_update_define_keeps_values_with_a_closing_tag()
{
	$content = pg_config_install_content(function ($name) {
		$fields = array('db_host' => 'db', 'db_username' => 'site', 'db_password' => 'p?>w', 'db_database' => 'site');
		return isset($fields[$name]) ? $fields[$name] : '';
	}, '0123456789abcdef0123456789abcdef');

	$content = update_config_define($content, 'SYSTEM_SMTP_HOSTNAME', 'smtp.example.test');
	$content = update_config_define($content, 'SYSTEM_SMTP_PASSWORD', "o'neil\\");
	$content = update_config_define($content, 'EMAIL_CAMPAIGN_JOB', 'true', 'boolean');

	// A second save replaces the line instead of adding another one.
	$content = update_config_define($content, 'SYSTEM_SMTP_PASSWORD', "it's?>new\\");

	pg_assert_same(1, substr_count($content, "define('SYSTEM_SMTP_PASSWORD'"), 'one password line');

	pg_assert_same(array(
		'DB_PASSWORD' => 'p?>w',
		'SYSTEM_SMTP_HOSTNAME' => 'smtp.example.test',
		'SYSTEM_SMTP_PASSWORD' => "it's?>new\\",
		'EMAIL_CAMPAIGN_JOB' => true,
	), config_file_test_values_of($content, array('DB_PASSWORD', 'SYSTEM_SMTP_HOSTNAME', 'SYSTEM_SMTP_PASSWORD', 'EMAIL_CAMPAIGN_JOB'), 'updated'));
}

// The installation's config.php reads back what the form held: database and
// SMTP values with quotes, backslashes and closing tags, and the switches as booleans.
function test_config_file_install_content_reads_back_the_form()
{
	$fields = array(
		'db_host' => "db'host",
		'db_username' => 'user\\',
		'db_password' => "p'a\\ss?>\\",
		'db_database' => '$site',
		'default_software_language' => 'tr',
		'system_smtp_hostname' => 'smtp.example.test',
		'system_smtp_port' => '587',
		'system_smtp_username' => "o'neil",
		'system_smtp_password' => "s'm\\tp",
		'logo_url' => "https://example.test/l'o.png",
		'software_update_check' => 'false',
		'email_campaign_job' => 'true',
		'campaign_smtp_hostname' => 'campaign.example.test',
		'campaign_smtp_number_of_emails' => '50',
		'campaign_smtp_port' => '465',
		'campaign_smtp_username' => 'campaign',
		'campaign_smtp_password' => "c'p\\",
	);

	$content = pg_config_install_content(function ($name) use ($fields) {
		return isset($fields[$name]) ? $fields[$name] : '';
	}, '0123456789abcdef0123456789abcdef');

	pg_assert_same(array(
		'DB_HOST' => "db'host",
		'DB_USERNAME' => 'user\\',
		'DB_PASSWORD' => "p'a\\ss?>\\",
		'DB_DATABASE' => '$site',
		'ENCRYPTION_KEY' => '0123456789abcdef0123456789abcdef',
		'DYNAMIC_REGIONS' => true,
		'PHP_REGIONS' => true,
		'DEFAULT_SOFTWARE_LANGUAGE' => 'tr',
		'SYSTEM_SMTP_HOSTNAME' => 'smtp.example.test',
		'SYSTEM_SMTP_PORT' => '587',
		'SYSTEM_SMTP_USERNAME' => "o'neil",
		'SYSTEM_SMTP_PASSWORD' => "s'm\\tp",
		'LOGO_URL' => "https://example.test/l'o.png",
		'SOFTWARE_UPDATE_CHECK' => false,
		'EMAIL_CAMPAIGN_JOB' => true,
		'CAMPAIGN_SMTP_HOSTNAME' => 'campaign.example.test',
		'EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS' => '50',
		'CAMPAIGN_SMTP_PORT' => '465',
		'CAMPAIGN_SMTP_USERNAME' => 'campaign',
		'CAMPAIGN_SMTP_PASSWORD' => "c'p\\",
		'AUTOMATED_UPGRADE_SECRET' => null,
	), config_file_test_values_of($content, array(
		'DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'ENCRYPTION_KEY', 'DYNAMIC_REGIONS', 'PHP_REGIONS',
		'DEFAULT_SOFTWARE_LANGUAGE', 'SYSTEM_SMTP_HOSTNAME', 'SYSTEM_SMTP_PORT', 'SYSTEM_SMTP_USERNAME', 'SYSTEM_SMTP_PASSWORD',
		'LOGO_URL', 'SOFTWARE_UPDATE_CHECK', 'EMAIL_CAMPAIGN_JOB', 'CAMPAIGN_SMTP_HOSTNAME', 'EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS',
		'CAMPAIGN_SMTP_PORT', 'CAMPAIGN_SMTP_USERNAME', 'CAMPAIGN_SMTP_PASSWORD', 'AUTOMATED_UPGRADE_SECRET',
	), 'installation'));

	// The file ends with its closing tag, where later saves add their lines.
	pg_assert_same('?>', substr($content, -2), 'closing tag at the end');
	pg_assert_contains("// define('AUTOMATED_UPGRADE_SECRET', 'change-this-to-a-long-random-string');", $content, 'secret example');
}

// Settings the form left empty are not written; the campaign job only when
// it was switched on.
function test_config_file_install_content_leaves_out_empty_settings()
{
	$fields = array('db_host' => 'db', 'db_username' => 'site', 'db_password' => '', 'db_database' => 'site', 'email_campaign_job' => 'false');

	$content = pg_config_install_content(function ($name) use ($fields) {
		return isset($fields[$name]) ? $fields[$name] : null;
	}, 'key');

	pg_assert_same(array(
		'DB_PASSWORD' => '',
		'DEFAULT_SOFTWARE_LANGUAGE' => null,
		'SYSTEM_SMTP_HOSTNAME' => null,
		'SYSTEM_SMTP_PASSWORD' => null,
		'LOGO_URL' => null,
		'SOFTWARE_UPDATE_CHECK' => null,
		'EMAIL_CAMPAIGN_JOB' => null,
		'CAMPAIGN_SMTP_PASSWORD' => null,
	), config_file_test_values_of($content, array(
		'DB_PASSWORD', 'DEFAULT_SOFTWARE_LANGUAGE', 'SYSTEM_SMTP_HOSTNAME', 'SYSTEM_SMTP_PASSWORD', 'LOGO_URL',
		'SOFTWARE_UPDATE_CHECK', 'EMAIL_CAMPAIGN_JOB', 'CAMPAIGN_SMTP_PASSWORD',
	), 'empty settings'));

	// The installer's CA line, added where the settings screens add theirs.
	$with_ca = pg_config_insert_line($content, "define('CURL_CA_BUNDLE', dirname(__FILE__) . '/cacert.pem');");

	$tail = "define('CURL_CA_BUNDLE', dirname(__FILE__) . '/cacert.pem');\r\n?>";

	pg_assert_same($tail, substr($with_ca, -strlen($tail)), 'CA line before the closing tag');
}
