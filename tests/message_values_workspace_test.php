<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A liveform message is HTML: the values the messages of the Workspace
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

function test_message_values_workspace_are_escaped()
{
	$lines = array(
		array('includes/workspace/ai.php', '$liveform->add_error(h($error));', 2),
		array('includes/workspace/claude.php', '$liveform->add_error(h($result[\'error\']));', 1),
		array('includes/workspace/mcp.php', '$liveform->add_error(h($flash[\'text\']));', 1),
		array('includes/workspace/mcp.php', '$liveform->add_error(lang(array(\'string\' => \'@{var:1} could not be reached: {var:2}\', \'vars\' => array($server[\'alias\'], h($refresh[\'error\'])))));', 2),
		array('includes/workspace/mcp.php', '$liveform->add_error(lang(array(\'string\' => \'@{var:1} was saved but could not be reached: {var:2}\', \'vars\' => array($alias, h($refresh[\'error\'])))));', 1),
		array('includes/workspace/mcp.php', '$liveform->add_error(lang(array(\'string\' => \'@{var:1} was saved, but the sign-in to it could not be set up: {var:2}\', \'vars\' => array($alias, h($setup[\'error\'])))));', 1),
		array('includes/workspace/mcp.php', '$liveform->add_error(lang(array(\'string\' => \'The sign-in to @{var:1} could not be set up: {var:2}\', \'vars\' => array($server[\'alias\'], h($setup[\'error\'])))));', 1),
		array('includes/workspace/mcp.php', '$liveform->add_notice(h($flash[\'text\']));', 1),
		array('includes/workspace/mcp_agent.php', ': \'The tool {var:1} cannot take the messages: it must take a text and be turned on as one that reads only. Pinegrap AI answers @{var:2} meanwhile.\', \'vars\' => array(h($wanted), $server[\'alias\']))));', 1),
		array('includes/workspace/templates.php', ': lang(array(\'string\' => \'The template is back: {var:1}.\', \'vars\' => h($result[\'name\']))));', 1),
		array('includes/workspace/templates.php', '? lang(array(\'string\' => \'The template was archived: {var:1}. The channels made from it keep what they were given.\', \'vars\' => h($result[\'name\'])))', 1),
		array('includes/workspace/workdays.php', '$liveform->add_error(lang(array(\'string\' => \'The calendar could not be read: {var:1}\', \'vars\' => h($read[\'error\']))));', 1),
		array('includes/workspace/workdays.php', '$liveform->add_notice(lang(array(\'string\' => \'The day counts as a day off again: {var:1}.\', \'vars\' => h($when))));', 1),
		array('includes/workspace/workdays.php', '$liveform->add_notice(lang(array(\'string\' => \'The day is left out of the working calendar: {var:1}.\', \'vars\' => h($when))));', 1),
		array('includes/workspace/workdays.php', '$liveform->add_notice(lang(array(\'string\' => \'The holiday was added: {var:1}.\', \'vars\' => h(trim((string) ($_POST[\'name\'] ?? \'\'))))));', 1),
		array('includes/workspace/workdays.php', '$liveform->add_warning(lang(array(\'string\' => \'The calendar was added, but it could not be read: {var:1}\', \'vars\' => h($added[\'error\']))));', 1),
		array('workspace_mcp.php', '$liveform->add_error(h($flash[\'text\']));', 1),
		array('workspace_mcp.php', '$liveform->add_error(h($run[\'error\']));', 1),
		array('workspace_mcp.php', '$liveform->add_notice(h($flash[\'text\']));', 1),
		array('workspace_mcp.php', '$liveform->add_notice(lang(array(\'string\' => \'The answer was posted in {var:1}.\', \'vars\' => \'#\' . h($channel[\'name\']))));', 1),
		array('workspace_settings.php', '$liveform->add_notice(lang(array(\'string\' => \'The department was saved: {var:1}.\', \'vars\' => h($name))));', 1),
		array('workspace_settings.php', ': lang(array(\'string\' => \'The department is back: {var:1}.\', \'vars\' => h($department[\'name\']))));', 1),
		array('workspace_settings.php', '? lang(array(\'string\' => \'The department was archived: {var:1}. Its tasks keep it; new ones cannot choose it.\', \'vars\' => h($department[\'name\'])))', 1),
		array('workspace_template.php', '$liveform->add_notice(lang(array(\'string\' => \'The template was saved: {var:1}.\', \'vars\' => h(trim($data[\'name\'])))));', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
