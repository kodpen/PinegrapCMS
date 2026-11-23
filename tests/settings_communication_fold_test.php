<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Communication as folded rows: each part's line of state,
 * the rows' order, Chat and Chat on the site with no popover or old switch
 * left, the bubble's groups, the anchors that open Chat on the site, and the
 * names that stay (card ids, field names, the save, the search words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/communication_settings_fold.php');

function settings_communication_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of contact.php from one card's id to the end of its markup.
function settings_communication_fold_test_card($id)
{
	$source = settings_communication_fold_test_source('includes/settings/contact.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "') + pg_contact_fold_", $start);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function test_settings_communication_fold_order_and_names()
{
	pg_assert_same(array('chat', 'chat-site', 'sms', 'sms-messages', 'whatsapp', 'whatsapp-messages', 'iys'), pg_contact_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_contact_fold_order() as $key) {
		$face = pg_contact_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Chat'), lang('Chat on the site'), lang('SMS'), lang('SMS messages'), lang('WhatsApp'), lang('WhatsApp messages'), lang('İYS')), $names, 'the rows\' names');
}

function test_settings_communication_fold_chat_lines()
{
	pg_assert_same(lang('Team members chat in the panel'), pg_contact_fold_chat(true)['line'], 'Chat on');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_contact_fold_chat(false)['pill'], 'Chat off');

	$off = pg_contact_fold_chat_site(false, true, 'Ayşe');
	pg_assert_same(lang('Chat is off: nothing shows on the site'), $off['line'], 'with Chat off nothing shows on the site, whatever the bubble says');
	pg_assert_false($off['attention'], 'not a fault');

	pg_assert_same(lang('The bubble is off'), pg_contact_fold_chat_site(true, false, 'Ayşe')['line'], 'the bubble off');

	$nobody = pg_contact_fold_chat_site(true, true, ' ');
	pg_assert_same(lang('Only signed-in members see the bubble: no one answers visitors'), $nobody['line'], 'no operator: visitors see no bubble');
	pg_assert_true($nobody['attention'], 'and the row asks for attention');
	pg_assert_same('pg-tone-amber', $nobody['pill'][1], 'an amber pill');

	$on = pg_contact_fold_chat_site(true, true, 'Ayşe');
	pg_assert_same(lang(array('string' => 'On, answered by {var:1}', 'vars' => 'Ayşe')), $on['line'], 'who answers');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $on['pill'], 'On');
}

function test_settings_communication_fold_channel_lines()
{
	$broken = pg_contact_fold_sms(true, false, 'Netgsm');
	pg_assert_true($broken['attention'], 'switched on without a connection asks for attention');
	pg_assert_same(lang(array('string' => 'On, sent through {var:1}', 'vars' => 'Netgsm')), pg_contact_fold_sms(true, true, 'Netgsm')['line'], 'on');
	pg_assert_same(lang('Not set up'), pg_contact_fold_sms(false, false, 'Netgsm')['line'], 'not set up');
	pg_assert_same(lang(array('string' => '{var:1} is connected; sending is off', 'vars' => 'Netgsm')), pg_contact_fold_sms(false, true, 'Netgsm')['line'], 'connected, off');

	pg_assert_same(lang('Not set up: no message is sent'), pg_contact_fold_messages(false, 3, 5)['line'], 'the channel does not send');
	pg_assert_same(null, pg_contact_fold_messages(false, 3, 5)['pill'], 'no pill then');
	pg_assert_same(lang(array('string' => '{var:1} of {var:2} messages on', 'vars' => array(2, 5))), pg_contact_fold_messages(true, 2, 5)['line'], 'how many are on');
	pg_assert_same(lang('No message is switched on'), pg_contact_fold_messages(true, 0, 5)['line'], 'none on');

	pg_assert_same(array(lang('Connected'), 'pg-tone-teal'), pg_contact_fold_connection(true)['pill'], 'connected');
	pg_assert_same(lang('Not set up'), pg_contact_fold_connection(false)['line'], 'not set up');
}

function test_settings_communication_fold_pane_is_rows()
{
	$pane = settings_communication_fold_test_source('includes/settings/contact.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($contact_rows);', $pane, 'the pane is one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');
	pg_assert_false(strpos($pane, 'popover') !== false, 'no popover left');
	pg_assert_false(strpos($pane, 'collapse-switcher') !== false, 'no switch opens a fold of its own');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing in the pane');

	foreach (array('Enable Live Chat', 'Enable Site Chat Bubble', 'Site Chat Operator', 'Require puzzle captcha for visitors', 'Visitor Image Limit') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old . "')") !== false, 'no "' . $old . '" label');
	}
}

function test_settings_communication_fold_chat_cards()
{
	$chat = settings_communication_fold_test_card('pgset-chat');
	pg_assert_contains("\$contact_switch('chat_enabled', \$chat_enabled_checked, lang('Chat')", $chat, 'the switch named Chat');
	pg_assert_contains('Team members chat with each other in the panel, and visitors on the site when the bubble is on.', $chat, 'its line says whom it binds');
	pg_assert_contains('name="chat_upload_folder_id"', $chat, 'the attachments folder');
	pg_assert_false(strpos($chat, 'chat_site_enabled') !== false, 'the bubble is not in Chat');

	$site = settings_communication_fold_test_card('pgset-chat-site');
	$order = array(
		strpos($site, "'chat_site_enabled'"),
		strpos($site, 'name="chat_operator_user_id"'),
		strpos($site, "'chat_offline_email'"),
		strpos($site, "lang('The bubble')"),
		strpos($site, 'name="chat_widget_icon"'),
		strpos($site, "lang('Visitors who are not signed in')"),
		strpos($site, "'chat_allow_images'"),
		strpos($site, 'name="chat_visitor_image_limit"'),
		strpos($site, "'chat_captcha'"),
	);
	pg_assert_false(in_array(false, $order, true), 'every field of the bubble is in Chat on the site');
	$sorted = $order;
	sort($sorted);
	pg_assert_same($sorted, $order, 'in the description\'s order');

	pg_assert_contains("lang('Who answers visitors')", $site, 'the operator\'s name');
	pg_assert_contains('Visitors who are not signed in see the bubble only while someone is chosen here.', $site, 'its line on whom it serves');
	pg_assert_contains('<a href="#settings/security/pgset-captcha">', $site, 'the puzzle names the site\'s other abuse settings');
	pg_assert_contains('Team members and signed-in members may always send files.', $site, 'the attachments bind visitors only');
	pg_assert_same(3, substr_count($site, 'col-12 md:col-4'), 'Theme, Colour and Icon side by side from 768 px');
}

function test_settings_communication_fold_names_stay()
{
	$pane = settings_communication_fold_test_source('includes/settings/contact.php');
	$switches = array('chat_enabled', 'chat_site_enabled', 'chat_offline_email', 'chat_allow_images', 'chat_allow_files', 'chat_allow_audio', 'chat_captcha');

	foreach ($switches as $name) {
		pg_assert_contains("\$contact_switch('" . $name . "', \$" . $name . "_checked,", $pane, $name . ' is a switch posting under its name');
	}

	pg_assert_contains('name="\' . $name . \'"', $pane, 'a switch posts its name');

	foreach (array('chat_operator_user_id', 'chat_widget_title', 'chat_welcome_message', 'chat_widget_theme', 'chat_widget_color', 'chat_widget_icon', 'chat_visitor_image_limit', 'chat_upload_folder_id') as $name) {
		pg_assert_contains('name="' . $name . '"', $pane, $name . ' posts under its name');
	}

	foreach (array('pgset-chat', 'pgset-chat-site', 'pgset-sms', 'pgset-sms-messages', 'pgset-whatsapp', 'pgset-whatsapp-messages', 'pgset-iys') as $id) {
		pg_assert_same(1, substr_count($pane, '<div id="' . $id . '" class="pg-set-card">'), $id . ' is drawn once, inside its row');
	}

	$save = settings_communication_fold_test_source('includes/settings/contact.save.php');
	pg_assert_false(strpos($save, 'pg_settings_fold') !== false || strpos($save, 'chat-site') !== false, 'the save knows nothing of rows');

	foreach (array_merge($switches, array('chat_operator_user_id', 'chat_widget_title', 'chat_welcome_message', 'chat_widget_theme', 'chat_widget_color', 'chat_widget_icon', 'chat_visitor_image_limit', 'chat_upload_folder_id')) as $name) {
		pg_assert_contains("post_value('" . $name . "')", $save, 'the save reads ' . $name);
	}
}

function test_settings_communication_fold_registry_anchors_and_search()
{
	$source = settings_communication_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'contact' => array(");
	$contact = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("'pgset-chat-site' => lang('Chat on the site')", $contact, 'Chat on the site is a section');
	pg_assert_true(strpos($contact, "'pgset-chat'      => lang(") < strpos($contact, "'pgset-chat-site' => lang(") && strpos($contact, "'pgset-chat-site' => lang(") < strpos($contact, "'pgset-sms'       => lang("), 'after Chat, before SMS');

	$keywords = array();
	preg_match_all("/'(pgset-chat(?:-site)?)'\s*=> pg_settings_keywords\(lang\('([^']*)'\)\)/", $contact, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$keywords[$match[1]] = $match[2];
	}

	pg_assert_true(isset($keywords['pgset-chat'], $keywords['pgset-chat-site']), 'both rows have search words');
	pg_assert_contains('attachments', $keywords['pgset-chat'], 'the search finds the attachments folder');

	foreach (array('operator', 'bubble', 'captcha', 'attachments') as $word) {
		pg_assert_contains($word, $keywords['pgset-chat-site'], 'the search finds "' . $word . '" in Chat on the site');
	}

	pg_assert_false(strpos($keywords['pgset-chat'], 'operator') !== false || strpos($keywords['pgset-chat'], 'bubble') !== false, 'the bubble\'s words left Chat');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('contact', 'pgset-chat'), $anchors['pgset-chat'], 'an old Chat link still opens Chat');

	foreach (array('chat_site_enabled', 'chat_operator_user_id', 'chat_captcha') as $field) {
		pg_assert_same(array('contact', 'pgset-chat-site'), $anchors[$field], 'an old link to ' . $field . ' opens Chat on the site');
	}

	// A row an anchor names arrives open: the client jumps to the card and
	// opens the row it sits in.
	$js = settings_communication_fold_test_source('assets/js/backend.src.js');
	pg_assert_contains('window.pgSettingsFoldReveal = function (el)', $js, 'the client opens the row an element sits in');

	// Inside the rows the search index still reads each card's labels apart.
	$html = pg_settings_fold(array(
		array('key' => 'chat', 'name' => 'Chat', 'icon' => 'bi-chat-dots', 'tone' => '', 'line' => 'x', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-chat" class="pg-set-card"><div class="card"><div class="card-body"><label for="a">Folder for chat attachments</label><select id="a"></select></div></div></div>'),
		array('key' => 'chat-site', 'name' => 'Chat on the site', 'icon' => 'bi-chat-square-heart', 'tone' => '', 'line' => 'y', 'pill' => null, 'attention' => true,
			'html' => '<div id="pgset-chat-site" class="pg-set-card"><div class="card"><div class="card-body"><label for="b">Who answers visitors</label><select id="b"></select></div></div></div>'),
	));
	$labels = pg_settings_card_labels($html, array('pgset-chat', 'pgset-chat-site'));

	pg_assert_same(array('Folder for chat attachments'), $labels['pgset-chat'], 'Chat\'s labels, none of the bubble\'s');
	pg_assert_same(array('Who answers visitors'), $labels['pgset-chat-site'], 'the bubble\'s label');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-chat-site-body"', $html, 'the row asking for attention arrives open');
}

// The SMS, SMS messages and WhatsApp messages rows draw their switches as
// pg-switch, with the names each function posted before.
function test_settings_communication_fold_message_rows_switches()
{
	$files = array(
		'includes/fn/sms.php'               => array('name="sms_enabled"'),
		'includes/fn/sms_messages.php'      => array('name="\' . h($name) . \'on"'),
		'includes/fn/whatsapp_messages.php' => array('name="\' . h($name) . \'on"'),
	);

	foreach ($files as $file => $names) {
		$source = settings_communication_fold_test_source($file);

		pg_assert_same(false, strpos($source, '<div class="switch">'), $file . ' draws no div.switch');

		foreach ($names as $name) {
			pg_assert_true(strpos($source, '<input type="checkbox" class="pg-switch" ' . $name) !== false, $file . ' posts ' . $name . ' from a pg-switch');
		}
	}
}
