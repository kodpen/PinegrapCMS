<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail: one category with the tabs E-mail sending and
 * Mailboxes, each under the gate its old category had; a reader with one
 * tab's right sees only that tab and no tab control; every old address,
 * anchor and key lands on its tab; each tab's pill; the save writes a tab
 * only when its cards were posted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_settings_tabs.php');

function settings_email_tabs_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function settings_email_tabs_test_readers()
{
	return array(
		'both'    => array('manager' => true, 'workspace' => false, 'mail' => true),
		'manager' => array('manager' => true, 'workspace' => false, 'mail' => false),
		'right'   => array('manager' => false, 'workspace' => false, 'mail' => true),
		'none'    => array('manager' => false, 'workspace' => true, 'mail' => false),
	);
}

function test_settings_email_one_category_two_tabs()
{
	$source = settings_email_tabs_test_source('includes/settings/registry.php');
	$email = pg_settings_email_category();

	pg_assert_contains("'email' => pg_settings_email_category(),", $source, 'one E-mail category');
	pg_assert_false(strpos($source, "\n        'mail_sending' => array(\n            'label'") !== false, 'E-mail sending is no category of its own');
	pg_assert_false(strpos($source, "\n        'mailboxes' => array(\n            'label'") !== false, 'nor Mailboxes');
	pg_assert_same(lang('E-mail'), $email['label'], 'one E-mail entry');
	pg_assert_same('site', $email['group']);
	pg_assert_same(array('sending', 'mailboxes'), array_keys($email['tabs']), 'its two tabs, Sending first');
	pg_assert_same(array('', 'mail'), array_column($email['tabs'], 'gate'), 'each tab keeps today\'s gate');
	pg_assert_same(array('mail_sending', 'mailboxes'), array_column($email['tabs'], 'module'), 'each tab drawn by its old module');

	$listed = array();

	foreach ($email['tabs'] as $tab) {
		$listed = array_merge($listed, $tab['sections']);
	}

	pg_assert_same(array_keys($email['sections']), $listed, 'every card belongs to one tab');
	pg_assert_same(array_keys($email['sections']), array_keys($email['keywords']), 'each card keeps its search words');
	pg_assert_same(3, $email['lists']['ws_mail'], 'a route is still ws_mail[id][route_match][]');
}

function test_settings_email_each_reader_sees_its_tabs()
{
	$email = pg_settings_email_category();
	$readers = settings_email_tabs_test_readers();

	pg_assert_same(array('sending', 'mailboxes'), array_keys(pg_settings_tabs_open($email, $readers['both'])), 'both rights: both tabs');
	pg_assert_same(array('sending'), array_keys(pg_settings_tabs_open($email, $readers['manager'])), 'a manager without the Workspace settings right: Sending alone');
	pg_assert_same(array('mailboxes'), array_keys(pg_settings_tabs_open($email, $readers['right'])), 'the right alone, the Workspace off: Mailboxes alone');
	pg_assert_same(array(), pg_settings_tabs_open($email, $readers['none']), 'neither: no tab');
	pg_assert_false(pg_settings_category_open($email, $readers['none']), 'and no category');

	$right = pg_settings_category_for_reader($email, $readers['right']);
	pg_assert_same(array('pgset-ws-mail'), array_keys($right['sections']), 'the search and the chips name only the open tab\'s cards');
	pg_assert_same(array('pgset-ws-mail'), array_keys($right['keywords']));

	pg_assert_true(pg_settings_category_reads_prep($email, $readers['manager']), 'the Sending tab reads the settings\' preparation');
	pg_assert_false(pg_settings_category_reads_prep($email, $readers['right']), 'the Mailboxes tab alone never does: its reader may be no manager');

	$list = pg_settings_categories_for(array(), $readers['right'], array('general' => array('label' => 'General'), 'email' => $email));
	pg_assert_same(array('email'), array_keys($list), 'the dialog\'s list shows E-mail to the right alone');
	pg_assert_same(array('mailboxes'), array_keys($list['email']['tabs']));
}

function test_settings_email_one_right_shows_no_tab_control()
{
	$tabs = pg_settings_email_category()['tabs'];

	pg_assert_same('', pg_email_tabs_html('email', array('mailboxes' => $tabs['mailboxes']), 'mailboxes', array()), 'one tab: no control');

	$panel = pg_email_tab_panel_html('email', 'mailboxes', '<div id="pgset-ws-mail"></div>', false, true);
	pg_assert_same(false, strpos($panel, 'role="tabpanel"'), 'nor a tab panel');
	pg_assert_same(false, strpos($panel, ' hidden'), 'its cards in view');
	pg_assert_contains('<input type="hidden" name="email_mailboxes_tab" value="1">', $panel, 'the tab says it was posted');

	$bar = pg_email_tabs_html('email', $tabs, 'mailboxes', array(
		'sending'   => array('words' => lang('Ready'), 'tone' => 'pg-tone-teal'),
		'mailboxes' => array('words' => lang('No box'), 'tone' => 'pg-tone-gray'),
	));

	pg_assert_contains('<div class="pg-seg pg-seg-tall" role="tablist" aria-label="' . h(lang('E-mail')) . '" data-pg-set-pane="email">', $bar, 'the Design examples\' tabs with a state');
	pg_assert_same(2, substr_count($bar, 'role="tab"'), 'two tabs');
	pg_assert_contains('id="pgset-email-sending-tab" aria-selected="false" aria-controls="pgset-email-sending" tabindex="-1" data-pg-set-tab>' . h(lang('E-mail sending')), $bar);
	pg_assert_contains('id="pgset-email-mailboxes-tab" aria-selected="true" aria-controls="pgset-email-mailboxes" data-pg-set-tab>' . h(lang('Mailboxes')), $bar, 'the tab the address named is chosen');
	pg_assert_contains('<span class="pg-pill pg-tone-teal" title="' . h(lang('Ready')) . '"><span>' . h(lang('Ready')) . '</span></span>', $bar, 'the pill\'s word in its title for the phone');

	$hidden = pg_email_tab_panel_html('email', 'sending', '', true, false);
	pg_assert_contains('id="pgset-email-sending" data-pg-set-tabpanel role="tabpanel" aria-labelledby="pgset-email-sending-tab" hidden>', $hidden, 'the other tab\'s panel waits hidden');
	pg_assert_contains('class="pg-settings-body"', $hidden, 'its cards keep the pane\'s gap');
}

function test_settings_email_tab_states()
{
	pg_assert_same(lang('Not set up'), pg_email_tab_sending_state(false, array())['words'], 'no way to send');
	pg_assert_same('pg-tone-gray', pg_email_tab_sending_state(false, array())['tone']);
	pg_assert_same(lang('Ready'), pg_email_tab_sending_state(true, array('spf' => array('state' => 'ok'), 'dkim' => array('state' => 'ok'), 'dmarc' => array('state' => 'unknown')))['words'], 'a lookup that could not run says nothing against the domain');
	pg_assert_same(lang('Check the domain'), pg_email_tab_sending_state(true, array('spf' => array('state' => 'ok'), 'dkim' => array('state' => 'error')))['words'], 'a DKIM record that is wrong');
	pg_assert_same('pg-tone-amber', pg_email_tab_sending_state(true, array('dmarc' => array('state' => 'warning')))['tone']);

	pg_assert_same(array('words' => lang('No box'), 'tone' => 'pg-tone-gray'), pg_email_tab_mailboxes_state(array()));
	pg_assert_same(lang(array('string' => '{var:1} reading', 'vars' => 2)), pg_email_tab_mailboxes_state(array('reading', 'off', 'reading'))['words'], 'how many are read');
	pg_assert_same('pg-tone-teal', pg_email_tab_mailboxes_state(array('reading'))['tone']);
	pg_assert_same(lang('Sign-in needed'), pg_email_tab_mailboxes_state(array('reading', 'sign_in'))['words'], 'a sign-in needed before the count');
	pg_assert_same(lang('Error'), pg_email_tab_mailboxes_state(array('sign_in', 'error'))['words'], 'an error first');
	pg_assert_same(lang('Off'), pg_email_tab_mailboxes_state(array('off', 'none'))['words'], 'none read');
}

function test_settings_email_old_addresses_land_on_their_tab()
{
	$moved = pg_settings_moved_categories();

	pg_assert_same(array('email', 'sending'), $moved['mail_sending'], 'E-mail sending is the Sending tab');
	pg_assert_same(array('email', 'mailboxes'), $moved['mailboxes'], 'Mailboxes is the Mailboxes tab');
	pg_assert_same('pgset-email-mailboxes', pg_settings_tab_section('email', 'mailboxes'), 'a tab is opened by its panel\'s id');

	// Old anchors.
	$anchors = pg_settings_legacy_anchors();
	foreach (array('pgset-campaigns' => 'pgset-mail-campaign', 'pgset-mailchimp' => 'pgset-mailchimp', 'mail_transport_card' => 'pgset-mail-transport', 'mail_domain_card' => 'pgset-mail-domain', 'ws-mail' => 'pgset-ws-mail') as $old => $card) {
		pg_assert_same(array('email', $card), $anchors[$old], $old . ' lands on its card in E-mail');
	}

	foreach ($anchors as $old => $target) {
		pg_assert_false(isset($moved[$target[0]]), $old . ' names no old category');
	}

	// Old pages: the wrappers keep their keys; the screen turns them into E-mail on their tab.
	pg_assert_contains("define('PG_SETTINGS_KEY', 'mail_sending');", settings_email_tabs_test_source('settings_mail_sending.php'));
	pg_assert_contains("define('PG_SETTINGS_KEY', 'mailboxes');", settings_email_tabs_test_source('settings_mailboxes.php'));
	pg_assert_contains("define('PG_SETTINGS_KEY', 'email');", settings_email_tabs_test_source('settings_email.php'), 'E-mail\'s own page');

	$screen = settings_email_tabs_test_source('includes/settings/screen.php');
	pg_assert_contains('$pg_settings_tab = $pg_settings_moved[$pg_settings_key][1];', $screen, 'the old page names its tab');
	pg_assert_true(strpos($screen, '$pg_settings_self = pg_settings_url($pg_settings_key);') < strpos($screen, '$pg_settings_key = $pg_settings_moved'), 'and posts back to itself, so the save returns to that tab');
	pg_assert_contains('$email_first = (isset($pg_settings_tab) && isset($email_tabs[$pg_settings_tab])) ? $pg_settings_tab', settings_email_tabs_test_source('includes/settings/email.php'), 'E-mail opens on it');

	// Old keys: each asks its tab's gate.
	$registry = settings_email_tabs_test_source('includes/settings/registry.php');
	pg_assert_contains("return (\$tab !== null) && pg_settings_gate_open((string) (\$tab['gate'] ?? ''), \$reader);", $registry, 'pg_settings_may_open() asks an old key\'s tab gate');

	// The dialog: old keys, #settings/<old>/... and settings_<old>.php open the tab.
	$modal = settings_email_tabs_test_source('includes/settings/modal.php');
	pg_assert_contains('data-pgmoved="\' . h(json_encode($output_moved)) . \'"', $modal, 'the dialog is told where the old keys went');
	pg_assert_contains("\$output_moved[\$old] = \$target[0] . '|' . pg_settings_tab_section(\$target[0], \$target[1]);", $modal);

	$js = settings_email_tabs_test_source('assets/js/backend.src.js');
	pg_assert_contains("var shift = moved[String(key || '')] ? String(moved[String(key)]).split('|') : null;", $js, 'open() turns an old key into E-mail on its tab');
	pg_assert_contains('if (file && moved[file]) {', $js, 'fromUrl() turns an old page into E-mail on its tab');
	pg_assert_contains("String(moved.mail_sending || 'email|').split('|')", $js, 'and SMTP Settings\' old address too');
	pg_assert_contains('window.pgSettingsTabReveal(el);', $js, 'a card of the other tab opens its tab first');
	pg_assert_contains("'#settings/' + list.getAttribute('data-pg-set-pane') + '/' + panel.id", $js, 'the tab is kept in the dialog\'s address');
	pg_assert_contains("form.setAttribute('action', String(form.getAttribute('action') || '').split('#')[0] + '#' + panel.id);", $js, 'and in the page\'s, where the form posts back to it');
}

function test_settings_email_save_writes_only_the_posted_tab()
{
	$save = settings_email_tabs_test_source('includes/settings/email.save.php');

	pg_assert_contains("isset(\$email_save_tabs['sending']) && (post_value('email_sending_tab') !== null)", $save, 'Sending only when its cards were posted and the reader may open it');
	pg_assert_contains("isset(\$email_save_tabs['mailboxes']) && !\$liveform->check_form_errors()", $save, 'Mailboxes under its own gate, after a Sending save that was not refused');
	pg_assert_contains('pg_settings_tabs_open(pg_settings_category(\'email\'), pg_settings_reader($user))', $save, 'each tab under its gate');
	pg_assert_true(strpos($save, 'mail_sending.save.php') < strpos($save, 'mailboxes.save.php'), 'Sending first');

	// Bring in an .eml file posts its own form to the old page: no Sending field, so no Sending save.
	$eml = ws_mail_eml_pane_form_test_source();
	pg_assert_contains("action=\"' . h(pg_settings_url('mailboxes')) . '\"", $eml, 'the .eml form posts to the Mailboxes tab\'s page');
	pg_assert_false(strpos($eml, 'email_sending_tab') !== false, 'without the Sending tab\'s field');
}

function ws_mail_eml_pane_form_test_source()
{
	$source = settings_email_tabs_test_source('includes/workspace/mail_settings.php');
	$start = strpos($source, 'function ws_mail_eml_pane_form()');

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

function test_settings_email_no_tab_sends_to_the_other()
{
	pg_assert_false(strpos(settings_email_tabs_test_source('includes/settings/mail_sending.php'), 'For incoming e-mail') !== false, 'Sending names no Mailboxes link: it is the tab beside');
	pg_assert_false(strpos(settings_email_tabs_test_source('includes/workspace/mail_settings.php'), "'For sending: {var:1}'") !== false, 'nor Mailboxes a Sending link');
}
