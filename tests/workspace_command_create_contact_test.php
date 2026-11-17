<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /create contact (/oluştur kişi): how the line is read into the
 * contact's first fields, which kinds of /create a person is offered and let
 * through (each kind the right of its own screen), the contact's required
 * list and the drawer's new mode for a line that leaves one out, nothing
 * written before Create, and the card's "All fields" without an on-sale
 * tick.
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
function workspace_command_create_contact_test_body($source, $function)
{
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// The body of one method of the composer's object literal in workspace.js.
function workspace_command_create_contact_test_js($source, $name)
{
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = preg_match('/\n        [A-Za-z]+: function \(/', $source, $match, PREG_OFFSET_CAPTURE, $start + 1) ? $match[0][1] : strlen($source);

	return substr($source, $start, $end - $start);
}

// "kişi", "KISI" and "contact" name the contact kind; the rest is its line.
function test_workspace_command_create_contact_kind_word()
{
	foreach (array('kişi', 'KİŞİ', 'kisi', 'contact', 'Contact') as $word) {
		$parsed = ws_command_parse_create($word . ' Ada Lovelace, Acme');
		pg_assert_same('contact', $parsed['kind'], $word . ' names a contact');
		pg_assert_same('Ada Lovelace, Acme', $parsed['rest'], $word . ': the rest is the contact\'s line');
	}
}

// The name, then the company after a comma; an e-mail address anywhere.
function test_workspace_command_parse_contact()
{
	$full = array('first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => 'Acme Ltd', 'email' => 'ada@example.com');

	pg_assert_same($full, ws_command_parse_contact('Ada Lovelace, Acme Ltd, ada@example.com'), 'name, company, e-mail');
	pg_assert_same($full, ws_command_parse_contact('ada@example.com Ada Lovelace, Acme Ltd'), 'the e-mail first');
	pg_assert_same($full, ws_command_parse_contact('Ada   Lovelace ,  Acme Ltd , ada@example.com'), 'spaces around the commas');

	pg_assert_same(array('first_name' => 'Ayşe', 'last_name' => 'Nur Yılmaz', 'company' => '', 'email' => ''), ws_command_parse_contact('Ayşe Nur Yılmaz'), 'the first word is the first name, the rest the last name');
	pg_assert_same(array('first_name' => 'Ada', 'last_name' => '', 'company' => 'Acme, Inc.', 'email' => ''), ws_command_parse_contact('Ada, Acme, Inc.'), 'every part after the name is the company');
	pg_assert_same(array('first_name' => '', 'last_name' => '', 'company' => '', 'email' => 'ada@example.com'), ws_command_parse_contact('ada@example.com'), 'an e-mail alone');
	pg_assert_same(array('first_name' => '', 'last_name' => '', 'company' => '', 'email' => ''), ws_command_parse_contact(''), 'nothing at all');
	pg_assert_same('', ws_command_parse_contact('Ada @home, Acme')['email'], 'a word with @ and no domain is no e-mail');
}

// The contact's required list is the catalogue's and read as it reads it
// (ws_change_create_check()): at least one of first name, last name, company
// and e-mail. A product needs its name, as before.
function test_workspace_command_create_contact_required_list()
{
	$kind = pg_test_ws_change_types()['contact'];
	$required = (array) $kind['required'];
	$check = function ($type, $fields) {
		return ws_quick_create_missing((array) pg_test_ws_change_types()[$type]['required'], ws_quick_create_change($type, $fields)['fields']);
	};

	pg_assert_same(array('first_name', 'last_name', 'company', 'email'), $required, 'the catalogue\'s required list');
	pg_assert_same(array('first_name', 'last_name', 'company', 'email'), ws_quick_create_short_list('contact'), 'the card and the drawer show the four first');

	pg_assert_same(array(), $check('contact', array('first_name' => 'Grace', 'last_name' => 'Hopper')), 'only a name is accepted');
	pg_assert_same(array(), $check('contact', array('company' => 'Acme')), 'only a company is accepted');
	pg_assert_same(array(), $check('contact', array('email' => 'grace@example.com')), 'only an e-mail is accepted');
	pg_assert_same(array(), $check('contact', array_filter(ws_command_parse_contact('Grace Hopper, grace@example.com'), 'strlen')), 'a line without a company answers with the card');
	pg_assert_same($required, $check('contact', array('first_name' => '  ', 'description' => 'x')), 'none of the four is refused');
	pg_assert_same($required, $check('contact', array()), 'nothing at all is refused');
	pg_assert_same('A customer needs at least a name, a company or an e-mail address.', ws_quick_create_missing_error('contact', 'First name'), 'with the catalogue\'s sentence');

	pg_assert_same(array('name'), $check('product', array('price' => 100)), 'a product without a name is refused, as before');
	pg_assert_same('Name is required', ws_quick_create_missing_error('product', 'Name'), 'naming the field');
	pg_assert_same(array(), $check('product', array('name' => 'KUPA-01')), 'a product with its name passes');

	// The drawer stars a field that must be filled on its own only.
	pg_assert_same(array(), ws_quick_create_starred($required), 'none of a contact\'s four is starred');
	pg_assert_same(array('name'), ws_quick_create_starred(pg_test_ws_change_types()['product']['required']), 'a product\'s name is');

	// The card and the save ask the same helper.
	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	pg_assert_contains('ws_quick_create_missing(', workspace_command_create_contact_test_body($quick, 'ws_quick_create_input'), 'the save');
	pg_assert_contains('ws_quick_create_missing(', workspace_command_create_contact_test_body($commands, 'ws_command_create_card'), 'the card');
	pg_assert_contains("'required' => ws_quick_create_starred(", workspace_command_create_contact_test_body($quick, 'ws_quick_create_fields'), 'the drawer\'s stars');
}

// Once "kişi" or "contact" is typed after /create, the strip shows the
// contact's own arguments and example; before a kind, the command's own.
function test_workspace_command_create_contact_strip()
{
	$entry = ws_command_registry()['create'];
	$variant = $entry['variants'][0];

	pg_assert_same(array('kisi', 'contact'), $variant['words'], 'the contact\'s words, folded');
	pg_assert_same(array('name', 'company', 'email'), array_column($variant['args'], 'name'), 'name, company, e-mail');
	pg_assert_same(array(',', ',', null), array_map(function ($arg) { return $arg['after'] ?? null; }, $variant['args']), 'with the commas the line takes');
	pg_assert_same('/create contact Ada Lovelace, Acme Ltd, ada@example.com', $variant['example'], 'its example');
	pg_assert_same('record_kind', $entry['args'][0]['kind'], 'before a kind, <kind> as today');

	foreach (ws_command_registry() as $key => $item) {
		pg_assert_true(is_array($item['variants']), $key . ' has a list of kind lines');
	}

	foreach ($variant['words'] as $word) {
		pg_assert_same('contact', ws_command_create_kinds()[$word] ?? '', $word . ' is a kind the parser reads');
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$argument = workspace_command_create_contact_test_js($js, 'commandArgument');
	pg_assert_contains('return item.words.indexOf(foldText(done[0])) !== -1;', $argument, 'the typed kind word picks its line');
	pg_assert_contains("entry = Object.assign({}, entry, { args: variant.args, example: variant.example });", $argument, 'with its arguments and example');
	pg_assert_contains("if (arg.after) {", workspace_command_create_contact_test_js($js, 'drawCommandStrip'), 'the strip writes the commas');
}

// A contact has no on-sale switch: nothing adds "enabled" to its create, the
// card draws no tick and "All fields" sends none.
function test_workspace_command_create_contact_has_no_switch()
{
	pg_assert_true(ws_quick_create_has_switch('product'), 'a product starts off sale');
	pg_assert_true(ws_quick_create_has_switch('product_group'), 'a group starts switched off');
	pg_assert_false(ws_quick_create_has_switch('contact'), 'a contact has no switch');
	pg_assert_false(array_key_exists('enabled', ws_quick_create_change('contact', array('first_name' => 'Ada'))['fields']), 'no enabled in a contact\'s create');
	pg_assert_false(isset(pg_test_ws_change_types()['contact']['fields']['enabled']), 'the catalogue has none for it');

	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$card = workspace_command_create_contact_test_body($commands, 'ws_command_create_card');
	pg_assert_contains("'on_sale' => \$switch ? (string) \$types[\$type]['fields']['enabled'][2] : ''", $card, 'the card names no tick for a contact');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$draw = workspace_command_create_contact_test_js($js, 'drawCreatePreview');
	pg_assert_contains("if (preview.on_sale) {\n                onSale = tick(", $draw, 'the tick only with a label');
	pg_assert_contains("if (onSale) {\n                    values.enabled = onSale.checked;", $draw, '"All fields" sends no enabled without the tick');
	pg_assert_contains('on_sale: (onSale && onSale.checked) ? 1 : 0', $draw, 'Create sends no on-sale without the tick');
}

// Each kind is the right of its own screen: the product right never opens a
// contact, the contacts right never a product; a person with neither is not
// offered /create.
function test_workspace_command_create_kinds_by_right()
{
	$rights = function ($allowed) {
		return function ($viewer, $type, $id) use ($allowed) {
			return ($id === 0) && in_array($type, $allowed, true);
		};
	};

	pg_assert_same(array('product', 'product_group'), ws_command_create_types_for(array('id' => 2), $rights(array('product', 'product_group'))), 'the e-commerce right: products and groups, no contact');
	pg_assert_same(array('contact'), ws_command_create_types_for(array('id' => 3), $rights(array('contact'))), 'the contacts right: contacts only');
	pg_assert_same(array(), ws_command_create_types_for(array('id' => 4), $rights(array())), 'neither: nothing');
	pg_assert_same(array('product', 'product_group', 'contact'), ws_command_create_types_for(array('id' => 1), $rights(array('product', 'product_group', 'contact'))), 'both: every kind, in order');

	pg_assert_true(ws_command_gate_create(null, 'create'), 'listed without a person');
	pg_assert_same('ws_command_gate_create', ws_command_registry()['create']['gate'], '/create is gated per kind');
	pg_assert_same('ws_command_gate_product', ws_command_registry()['product']['gate'], '/product keeps the product right');

	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$gate = workspace_command_create_contact_test_body($commands, 'ws_command_gate_create');
	pg_assert_contains('ws_command_create_types_for($viewer) !== array()', $gate, 'offered when one kind is the person\'s');

	// Let through only for a kind the person may make, before the line is read.
	$create = workspace_command_create_contact_test_body($commands, 'ws_command_create');
	$refusal = strpos($create, "!in_array(\$parsed['kind'], ws_command_create_types_for(\$viewer), true)");
	pg_assert_true($refusal !== false, 'a kind outside the person\'s rights is refused');
	pg_assert_true($refusal < strpos($create, 'ws_command_contact('), 'before a contact is read');
	pg_assert_true($refusal < strpos($create, 'ws_command_product('), 'before a product is read');

	// The popup's kinds: only what the person may make.
	$suggest = workspace_command_create_contact_test_body($commands, 'ws_command_suggestions');
	pg_assert_contains('array_intersect_key($kinds, array_flip(ws_command_create_types_for($viewer)))', $suggest, 'the kind popup leaves out the others');
	pg_assert_contains("ws_command_suggestions(null, \$viewer)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'), 'the screen gives the person');
}

// Nothing is written before Create: the card is checked, never saved, and
// a line missing a required field answers with what was typed and what is
// missing, after the right is checked, and the composer opens the drawer.
function test_workspace_command_create_contact_nothing_before_create()
{
	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$card = workspace_command_create_contact_test_body($commands, 'ws_command_create_card');
	$save = strpos($card, 'ws_quick_create_save(');

	pg_assert_true($save !== false, 'the card saves through the drawer\'s create');
	pg_assert_same(1, substr_count($card, 'ws_quick_create_save('), 'in one place');
	pg_assert_true(strpos($card, "if (!empty(\$options['confirm'])) {") < $save, 'only when confirmed');

	$missing = substr($card, strpos($card, 'if ($missing !== array()) {'));
	pg_assert_true(strpos($missing, 'ws_quick_create_check($viewer, $type)') < strpos($missing, "'missing' => \$names"), 'the right before the drawer is asked for');

	$contact = workspace_command_create_contact_test_body($commands, 'ws_command_contact');
	pg_assert_contains("ws_command_create_card(\$viewer, \$channel, 'contact', \$fields, \$options)", $contact, 'a contact through the card');
	pg_assert_false(strpos($contact, 'ws_change_write') !== false, 'and never written from the line');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$draw = workspace_command_create_contact_test_js($js, 'drawCreatePreview');
	pg_assert_contains("if (preview.missing && preview.missing.length) {\n                self.pending = null;\n                openRecord(preview.kind, 0, '', { values: command.values || {}", $draw, 'a line missing a field opens the drawer\'s new mode, filled');

	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
	$input = workspace_command_create_contact_test_body($quick, 'ws_quick_create_input');
	pg_assert_contains('ws_quick_create_missing(', $input, 'the save checks the same required list');
}

// A contact's decision in the channel names it by its tag alone: its lines
// would show the e-mail address to members without the contacts right.
function test_workspace_command_create_contact_decision_is_the_tag()
{
	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
	$save = workspace_command_create_contact_test_body($quick, 'ws_quick_create_save');

	pg_assert_contains("(\$type === 'contact')\n            ? lang(array('string' => '{var:1} was added.', 'vars' => array(\$tag)))", $save, 'the tag alone for a contact');
	pg_assert_same('New contact', ws_quick_create_title('contact'), 'the drawer\'s title');
}
