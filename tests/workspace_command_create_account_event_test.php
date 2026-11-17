<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /create account (/oluştur cari) and /create event
 * (/oluştur etkinlik): the kind words, how each line is read, who is
 * offered each kind (the ERP module and its right, the calendars right),
 * the write through the catalogue's writers (erp_account_save(), the event
 * screen's starting values) only on Create, the drawer's new mode opened
 * filled for a line that leaves out what the record needs, the strip.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/command_registry.php');

// The body of a function in a source file, up to the next function.
function workspace_command_create_account_event_test_body($source, $function)
{
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// "cari", "CARİ" and "account" name an ERP account; "etkinlik" and "event"
// a calendar event; the rest is the kind's line.
function test_workspace_command_create_account_event_kind_words()
{
	foreach (array('cari', 'CARİ', 'Cari', 'account', 'ACCOUNT') as $word) {
		$parsed = ws_command_parse_create($word . ' Acme Ltd');
		pg_assert_same('erp_account', $parsed['kind'], $word . ' names an account');
		pg_assert_same('Acme Ltd', $parsed['rest'], $word . ': the rest is its line');
	}

	foreach (array('etkinlik', 'ETKİNLİK', 'event', 'Event') as $word) {
		$parsed = ws_command_parse_create($word . ' yarın 14:00 Açılış');
		pg_assert_same('calendar_event', $parsed['kind'], $word . ' names an event');
		pg_assert_same('yarın 14:00 Açılış', $parsed['rest'], $word . ': the rest is its line');
	}
}

// An account's line: the e-mail anywhere, the name first, a tax number of
// 10 digits (or 11 not starting with 0), a phone; another part stays in
// the name.
function test_workspace_command_create_account_parse()
{
	pg_assert_same(
		array('title' => 'Acme Ltd', 'tax_number' => '1234567890', 'email' => 'info@acme.com', 'phone' => ''),
		ws_command_parse_account('Acme Ltd, 1234567890, info@acme.com'),
		'name, tax number, e-mail'
	);
	pg_assert_same(
		array('title' => 'Ada Lovelace', 'tax_number' => '', 'email' => 'ada@example.com', 'phone' => '0555 123 45 67'),
		ws_command_parse_account('ada@example.com Ada Lovelace, 0555 123 45 67'),
		'the e-mail first, a phone'
	);
	pg_assert_same('10000000146', ws_command_parse_account('Ada Lovelace, 10000000146')['tax_number'], 'an identity number');
	pg_assert_same('', ws_command_parse_account('Ada Lovelace, 05551234567')['tax_number'], 'eleven digits from 0 are a phone');
	pg_assert_same('05551234567', ws_command_parse_account('Ada Lovelace, 05551234567')['phone'], 'and read as one');
	pg_assert_same('Acme, Istanbul', ws_command_parse_account('Acme, Istanbul')['title'], 'another part stays in the name');
	pg_assert_same('1234567890', ws_command_parse_account('1234567890')['title'], 'the first part is always the name');
	pg_assert_same(array('title' => '', 'tax_number' => '', 'email' => '', 'phone' => ''), ws_command_parse_account(''), 'nothing written');
}

// An event's line: a day and a time first, as /remind reads them; a day
// alone is all day; no day leaves the start empty.
function test_workspace_command_create_event_parse()
{
	$now = strtotime('2026-11-17 09:00:00');
	$tomorrow = date('Y-m-d', strtotime('2026-11-18 12:00:00'));

	pg_assert_same(array('name' => 'Açılış', 'start_time' => $tomorrow . ' 14:00', 'all_day' => false), ws_command_parse_event('yarın 14:00 Açılış', $now), 'a day and a time');
	pg_assert_same(array('name' => 'Opening', 'start_time' => $tomorrow . ' 14:00', 'all_day' => false), ws_command_parse_event('tomorrow 14:00 Opening', $now), 'in English');
	pg_assert_same(array('name' => 'Fuar', 'start_time' => $tomorrow . ' 00:00', 'all_day' => true), ws_command_parse_event('yarın Fuar', $now), 'a day alone: all day');
	pg_assert_same(array('name' => 'Toplantı', 'start_time' => '2026-11-17 16:30', 'all_day' => false), ws_command_parse_event('16:30 Toplantı', $now), 'a time alone: today');
	pg_assert_same(array('name' => 'Açılış', 'start_time' => '', 'all_day' => false), ws_command_parse_event('Açılış', $now), 'no day: no start');
	pg_assert_same(array('name' => '', 'start_time' => $tomorrow . ' 14:00', 'all_day' => false), ws_command_parse_event('yarın 14:00', $now), 'no name');
}

// Each kind is the right of its record's screen: an account the ERP right
// (and not the accountant's read-only one), an event the calendars right.
function test_workspace_command_create_account_event_rights()
{
	$rights = function ($allowed) {
		return function ($viewer, $type, $id) use ($allowed) {
			return in_array($type, $allowed, true);
		};
	};

	pg_assert_same(array('erp_account'), ws_command_create_types_for(array('id' => 3), $rights(array('erp_account'))), 'the ERP right: accounts only');
	pg_assert_same(array('calendar_event'), ws_command_create_types_for(array('id' => 3), $rights(array('calendar_event'))), 'the calendars right: events only');
	pg_assert_same(array('product', 'product_group', 'contact'), ws_command_create_types_for(array('id' => 2), $rights(array('product', 'product_group', 'contact'))), 'the other rights open neither');

	$types = pg_test_ws_change_types();
	pg_assert_same('erp', $types['erp_account']['right'], 'an account: the ERP right');
	pg_assert_same('calendars', $types['calendar_event']['right'], 'an event: the calendars right');
	pg_assert_true(in_array('create', $types['erp_account']['actions'], true) && in_array('create', $types['calendar_event']['actions'], true), 'both made through the catalogue');

	// Neither is special-cased: ws_change_allowed() asks the right, the
	// ERP's read-only right and the module.
	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$may = workspace_command_create_account_event_test_body($commands, 'ws_command_create_may');
	pg_assert_false(strpos($may, 'erp_account') !== false || strpos($may, 'calendar_event') !== false, 'the catalogue\'s rule decides');
	pg_assert_contains("if (is_array(\$viewer) && (\$parsed['kind'] !== '') && !in_array(\$parsed['kind'], ws_command_create_types_for(\$viewer), true)) {", workspace_command_create_account_event_test_body($commands, 'ws_command_create'), 'a kind outside the person\'s refused before it is read');

	$reader = array('id' => 4, 'role' => 3, 'erp' => 1, 'erp_readonly' => true);
	pg_assert_true(ws_change_erp_readonly_refusal($reader, 'erp_account') !== '', 'the accountant\'s read-only right makes no account');
}

// With the ERP module off the catalogue has no account, so nobody is
// offered /create account, nor "cari" in the kind popup.
function test_workspace_command_create_account_erp_module_off()
{
	$source = pg_test_function_source('ws_change_types');
	pg_assert_contains("if (!defined('ERP_ENABLED') || !ERP_ENABLED) {\n        unset(\$types['erp_account'], \$types['erp_quote']);", $source, 'the module off drops the account');
	pg_assert_contains("if (defined('CALENDARS') && (CALENDARS !== true)) {\n        unset(\$types['calendar_event']);", $source, 'calendars off drop the event');

	// ws_change_allowed() refuses a kind the catalogue lacks before any
	// right is read; the kinds offered and the popup ask it.
	$allowed = pg_test_function_source('ws_change_allowed');
	pg_assert_contains("if (!\$viewer || !isset(\$types[\$type])) {\n        return false;", $allowed, 'a kind the catalogue lacks is nobody\'s');
	pg_assert_contains('return function_exists(\'ws_change_allowed\') && ws_change_allowed($viewer, $type, $record_id);', pg_test_function_source('ws_command_create_may'), 'the kinds offered ask it');
	pg_assert_contains('$kinds = array_intersect_key($kinds, array_flip(ws_command_create_types_for($viewer)));', pg_test_function_source('ws_command_suggestions'), 'so does the kind popup');

	$offered = function ($types) {
		return function ($viewer, $type, $id) use ($types) {
			return isset($types[$type]);
		};
	};
	$types = pg_test_ws_change_types();
	pg_assert_true(in_array('erp_account', ws_command_create_types_for(array('id' => 1), $offered($types)), true), 'the module on: offered');
	unset($types['erp_account'], $types['erp_quote']);
	pg_assert_false(in_array('erp_account', ws_command_create_types_for(array('id' => 1), $offered($types)), true), 'the module off: not offered');
}

// Only Create writes, through the drawer's create and the catalogue's
// writers: an account by erp_account_save(), an event with the event
// screen's starting values, unpublished.
function test_workspace_command_create_account_event_write()
{
	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	pg_assert_contains("return ws_command_create_card(\$viewer, \$channel, 'erp_account', \$fields, \$options);", workspace_command_create_account_event_test_body($commands, 'ws_command_account'), 'an account through the card');
	pg_assert_contains("return ws_command_create_card(\$viewer, \$channel, 'calendar_event', \$fields, \$options);", workspace_command_create_account_event_test_body($commands, 'ws_command_event'), 'an event through the card');

	$card = workspace_command_create_account_event_test_body($commands, 'ws_command_create_card');
	$confirm = strpos($card, "if (!empty(\$options['confirm'])) {");
	pg_assert_true(($confirm !== false) && ($confirm < strpos($card, 'ws_quick_create_save(')), 'written only when confirmed');
	pg_assert_same(1, substr_count($card, 'ws_quick_create_save('), 'one write');

	pg_assert_true(in_array('erp_account', ws_quick_create_types(), true) && in_array('calendar_event', ws_quick_create_types(), true), 'the drawer\'s create takes both');

	$changes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	$account = workspace_command_create_account_event_test_body($changes, 'ws_change_write_erp_account');
	pg_assert_contains('$saved = erp_account_save($data);', $account, 'the ERP\'s own save');
	pg_assert_contains('erp_tax_number_check(', $account, 'its tax number check');
	pg_assert_contains('An account with this tax number already exists', $account, 'an active account\'s number refused');

	$event = workspace_command_create_account_event_test_body($changes, 'ws_change_write_calendar_event');
	pg_assert_contains("'published' => '0'", $event, 'an event starts unpublished, as the screen\'s form');
	pg_assert_contains('if (ws_change_calendar_access($viewer, $calendar_id)) {', $event, 'only calendars the person may use');
	pg_assert_false(strpos(workspace_command_create_account_event_test_body($commands, 'ws_command_event'), "'published'") !== false, 'the command never publishes');
}

// A line without what the record needs opens the drawer's new mode, filled
// with what was typed: an account's name, an event's name, day and
// calendar.
function test_workspace_command_create_account_event_incomplete()
{
	pg_assert_same(array(), ws_command_create_needs('erp_account', array()), 'an account needs only its name, the required list');
	pg_assert_same(array('start_time', 'calendar_ids'), ws_command_create_needs('calendar_event', array('name' => 'Açılış')), 'an event its day and its calendar');
	pg_assert_same(array('calendar_ids'), ws_command_create_needs('calendar_event', array('name' => 'A', 'start_time' => '2026-11-18 14:00', 'calendar_ids' => array())), 'an empty list is none');
	pg_assert_same(array(), ws_command_create_needs('calendar_event', array('name' => 'A', 'start_time' => '2026-11-18 14:00', 'calendar_ids' => array(2))), 'all there');
	pg_assert_same(array('title'), ws_quick_create_missing(pg_test_ws_change_types()['erp_account']['required'], array('email' => 'a@b.co')), 'an account without a name');

	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$card = workspace_command_create_account_event_test_body($commands, 'ws_command_create_card');
	pg_assert_contains('ws_command_create_needs($type, $given)', $card, 'the card asks both lists');
	pg_assert_contains("'preview' => array('kind' => \$type, 'rows' => array(), 'on_sale' => '', 'missing' => \$names),", $card, 'what is missing goes to the composer');
	pg_assert_contains("'values'  => \$fields,", $card, 'with what was typed');

	$event = workspace_command_create_account_event_test_body($commands, 'ws_command_event');
	pg_assert_contains('if (count($calendars) === 1) {', $event, 'one calendar is taken; several are chosen in the drawer');

	pg_assert_same(array('title', 'kind', 'is_person', 'tax_number', 'email', 'phone'), ws_quick_create_short_list('erp_account'), 'the drawer\'s new account');
	pg_assert_true(in_array('calendar_ids', ws_quick_create_short_list('calendar_event'), true), 'the drawer\'s new event shows its calendars');

	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
	pg_assert_contains("if ((\$meta['kind'] === 'ids') && ((\$meta['tag'] ?? '') === 'calendar')) {", workspace_command_create_account_event_test_body($quick, 'ws_quick_create_choices'), 'calendars offered as choices');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains("} else if ((meta.kind === 'ids') && meta.choices) {", $js, 'the drawer draws the choices');
	pg_assert_contains("var select = el('select', 'pg-input');", $js, 'as a field of the look');
	pg_assert_contains("calendar_event: 'qc_command_title_event'", $js, 'the event card\'s title');
	pg_assert_contains("erp_account: 'qc_command_title_account'", $js, 'the account card\'s title');
}

// The strip shows each kind's own line once its word is typed.
function test_workspace_command_create_account_event_strip()
{
	$lines = array();

	foreach (ws_command_registry()['create']['variants'] as $variant) {
		$lines[$variant['words'][0]] = $variant;
	}

	pg_assert_same(array('cari', 'account'), $lines['cari']['words'] ?? null, 'a line for cari');
	pg_assert_same(array('name', 'tax_number', 'email'), array_column($lines['cari']['args'], 'name'), '<name>, <tax number>, <e-mail>');
	pg_assert_same('/create account Acme Ltd, 1234567890, info@acme.com', $lines['cari']['example'], 'its example');

	pg_assert_same(array('etkinlik', 'event'), $lines['etkinlik']['words'] ?? null, 'a line for etkinlik');
	pg_assert_same(array('day', 'time', 'name'), array_column($lines['etkinlik']['args'], 'name'), '<day> <time> <event name>');
	pg_assert_same('dates', $lines['etkinlik']['args'][0]['suggest'], 'the day offers the dates');
	pg_assert_same('/create event tomorrow 14:00 Opening', $lines['etkinlik']['example'], 'its example');

	foreach (array('cari' => 'erp_account', 'etkinlik' => 'calendar_event') as $word => $type) {
		foreach ($lines[$word]['words'] as $each) {
			pg_assert_same($type, ws_command_create_kinds()[$each] ?? '', $each . ' is a kind the parser reads');
		}
	}

	$parsed = ws_command_parse_event(ws_command_parse_create('etkinlik yarın 14:00 Açılış')['rest']);
	pg_assert_same('Açılış', $parsed['name'], 'the example reads as the strip says');
}
