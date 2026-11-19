<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two plan drawers in one look: "Add to the plan"
 * (eventForm() in workspace.js) and "New plan" (planDrawer() in
 * workspace_my_calendar.js with its roadmap) built from the kit's form parts
 * in look A, with no Bootstrap form class, one order and one wording of the
 * fields they share, and the names "Add to the plan" posts kept as they were.
 * The runner has no browser, so the scripts and the stylesheet are read as
 * source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_plan_drawers_look_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// eventForm()'s own source: from its line to the next function of the kit.
function workspace_plan_drawers_look_test_event_form()
{
	$kit = workspace_plan_drawers_look_test_source('assets/js/workspace.js');
	$start = strpos($kit, '    function eventForm(event, defaults, onDone) {');
	$end = strpos($kit, '    function eventCustomerPicker(', (int) $start);

	return (($start !== false) && ($end !== false)) ? substr($kit, $start, $end - $start) : '';
}

// The kit's formParts block.
function workspace_plan_drawers_look_test_parts()
{
	$kit = workspace_plan_drawers_look_test_source('assets/js/workspace.js');
	$start = strpos($kit, '    var formParts = {');
	$end = strpos($kit, "\n    };", (int) $start);

	return (($start !== false) && ($end !== false)) ? substr($kit, $start, $end - $start) : '';
}

function test_workspace_plan_drawers_hold_no_bootstrap_form_class()
{
	$sources = array(
		'eventForm()' => workspace_plan_drawers_look_test_event_form(),
		'the form parts' => workspace_plan_drawers_look_test_parts(),
		'planDrawer()' => workspace_plan_drawers_look_test_source('assets/js/workspace_my_calendar.js'),
		'the roadmap' => workspace_plan_drawers_look_test_source('assets/js/workspace_my_calendar_roadmap.js'),
	);

	foreach ($sources as $name => $source) {
		pg_assert_true(strlen($source) > 1000, $name . ' is found');

		foreach (array('form-control', 'form-label', 'form-text', 'input-group', 'btn btn-', "'btn ", "'switch'", "'row", 'col-6', 'col-12', 'theme-primary', 'theme-secondary', 'theme-warning') as $class) {
			pg_assert_same(false, strpos($source, $class), $name . ' holds no ' . $class);
		}
	}

	// Look A's select is .pg-input on the select itself.
	pg_assert_contains("el('select', 'pg-input' + (small ? ' pg-input-sm' : ''))", workspace_plan_drawers_look_test_parts(), 'the select part draws in look A');
}

function test_workspace_event_form_posts_the_names_of_before()
{
	$form = workspace_plan_drawers_look_test_event_form();

	pg_assert_same(1, preg_match("/api\\('ws_event_save', \\{(.*?)\\n                \\}\\)/s", $form, $match), 'one save post');
	preg_match_all('/^\s*([a-z_]+):/m', $match[1], $names);
	pg_assert_same(array('id', 'kind', 'title', 'scope', 'people', 'department_id', 'start', 'end', 'all_day', 'start_time', 'end_time', 'note', 'meeting_url', 'contact_id'), $names[1], 'the names posted before the step, in order');
	pg_assert_contains("api('ws_event_delete', { event_id: event.id })", $form, 'the delete post');
	pg_assert_contains("ask(t('delete_item_confirm'), t('delete'), true)", $form, 'the confirmation');
	pg_assert_contains("toast(t('plan_saved'), 'success');", $form, 'the toast');
	pg_assert_same(2, substr_count($form, 'onDone();'), 'onDone after a save and a delete');

	// Defaults and limits of before.
	pg_assert_contains("(defaults.kind || 'meeting')", $form, 'the kind defaults to a meeting');
	pg_assert_contains("event ? event.start_time : '09:00'", $form, 'starts at nine');
	pg_assert_contains("event ? event.end_time : '10:00'", $form, 'ends at ten');
	pg_assert_contains('title.maxLength = 255;', $form, 'title limit');
	pg_assert_contains('note.maxLength = 500;', $form, 'note limit');
	pg_assert_contains("allDay.checked || kind.value === 'leave' || kind.value === 'holiday'", $form, 'no times when all day, for leave and holiday');
	pg_assert_contains("kind.value !== 'visit'", $form, 'the customer only for a visit');
	pg_assert_contains("(kind.value === 'leave' || kind.value === 'holiday') && !meeting.value", $form, 'the meeting link hidden for leave and holiday without a link');
	pg_assert_contains("scopeValue = 'company';", $form, 'a holiday is the company\'s for a settings holder');
}

function test_workspace_event_form_order_and_its_own_fields()
{
	$form = workspace_plan_drawers_look_test_event_form();
	$order = array(
		'kind' => "part.field(t('kind'), kind)",
		'title' => "part.field(t('title'), title,",
		'days' => "part.pair(part.field(t('first_day'), start), part.field(t('end_date'), end))",
		'all day' => "part.switchRow(t('all_day')",
		'times' => "part.pair(part.field(t('start_time'), startTime), part.field(t('end_time'), endTime))",
		'who' => "part.group(t('who'), scopeSeg.node)",
		'note' => "part.field(t('note'), note)",
		'customer' => "part.group(t('event_customer'), customer.node)",
		'meeting link' => "part.field(t('meeting_link'), meeting, t('meeting_link_help'))",
		'meeting note' => "t('meeting_note')",
	);
	$last = -1;

	foreach ($order as $name => $needle) {
		$at = strpos($form, $needle);
		pg_assert_true($at !== false, $name . ' is built from the parts');
		pg_assert_true($at > $last, $name . ' comes in its place');
		$last = (int) $at;
	}

	// Who is a segmented choice, offered as before.
	pg_assert_contains("part.seg(t('who'), scopes,", $form, 'Who as a pg-seg');
	pg_assert_contains("'ws-plan-who'", $form, 'its segments share the width as Who can see');
	pg_assert_contains("if (rights.leads.length || rights.settings) {\n            scopes.push(['department', t('scope_dept')]);", $form, 'Department for a lead or a settings holder');
	pg_assert_contains("if (rights.settings) {\n            scopes.push(['company', t('scope_company')]);", $form, 'Company for a settings holder');
	pg_assert_contains('h.peoplePicker', workspace_plan_drawers_look_test_source('assets/js/workspace_my_calendar.js'), 'New plan keeps the shared picker');
	pg_assert_contains('peoplePicker(event ? event.people', $form, 'the shared people picker, not rebuilt');
	pg_assert_contains('eventCustomerPicker(event ? event.contact', $form, 'the shared customer picker, not rebuilt');

	// The line under the header and the footer.
	pg_assert_contains("el('p', 'ws-plan-private pg-text-meta')", $form, 'the line under the header, as New plan');
	pg_assert_contains("icon('bi-people', 'me-1')", $form, 'with the people icon');
	pg_assert_contains("part.remove(t('delete'))", $form, 'Delete at the left');
	pg_assert_contains("part.save(t('save'))", $form, 'Save at the right');
	pg_assert_same(false, strpos($form, "t('cancel')"), 'no Cancel: the cross or Esc closes it');
	pg_assert_contains("'ws-plan-meeting'", $form, 'the meeting link, Jitsi and Join on one row');
}

function test_workspace_plan_drawers_share_the_wording()
{
	$screen = workspace_plan_drawers_look_test_source('includes/workspace/screen.php');
	$plans = workspace_plan_drawers_look_test_source('includes/workspace/personal_plans.php');
	$contact = workspace_plan_drawers_look_test_source('includes/workspace/personal_plan_contact.php');
	$pairs = array(
		'title' => array('plan_title', 'Title'),
		'first_day' => array('plan_first_day', 'First day'),
		'end_date' => array('plan_last_day', 'Last day'),
		'start_time' => array('plan_from', 'Start time'),
		'end_time' => array('plan_until', 'End time'),
		'note' => array('plan_note', 'Note'),
	);

	foreach ($pairs as $event_key => $plan) {
		pg_assert_same(1, preg_match("/'" . $event_key . "'\\s+=> lang\\('" . preg_quote($plan[1], '/') . "'\\)/", $screen), 'Add to the plan says ' . $plan[1]);
		pg_assert_same(1, preg_match("/'" . $plan[0] . "'\\s+=> lang\\('" . preg_quote($plan[1], '/') . "'\\)/", $plans), 'New plan says ' . $plan[1]);
	}

	pg_assert_same(1, preg_match("/'event_customer'\\s+=> lang\\('Customer \\(optional\\)'\\)/", $screen), 'Add to the plan: Customer (optional)');
	pg_assert_same(1, preg_match("/'plan_customer'\\s+=> lang\\('Customer \\(optional\\)'\\)/", $contact), 'New plan: Customer (optional)');
	pg_assert_contains("h.t('all_day')", workspace_plan_drawers_look_test_source('assets/js/workspace_my_calendar.js'), 'All day: the same key in both');
	pg_assert_contains("part.switchRow(t('all_day')", workspace_plan_drawers_look_test_event_form(), 'All day in Add to the plan');
	// The task form keeps its own "Starts".
	pg_assert_same(1, preg_match("/'start_date'\\s+=> lang\\('Starts'\\)/", $screen), 'a key another form uses keeps its text');
}

// D8, the twin of test_workspace_personal_plan_drawer_fits_a_phone for
// "Add to the plan": the days and the hours are pairs that stay side by side,
// the meeting row wraps, Repeat every keeps its unit on its line.
function test_workspace_event_form_fits_a_phone()
{
	$css = workspace_plan_drawers_look_test_source('assets/css/backend.src.css');
	$form = workspace_plan_drawers_look_test_event_form();
	$drawer = workspace_plan_drawers_look_test_source('assets/js/workspace_my_calendar.js');

	pg_assert_same(1, preg_match('/\.ws-plan-pair \{[^}]*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/', $css), 'pairs share the width on every screen');
	pg_assert_same(1, preg_match('/\.ws-plan-meeting \{[^}]*flex-wrap: wrap;/', $css), 'the meeting row wraps at 390');
	pg_assert_same(1, preg_match('/\.ws-plan-meeting \.pg-input \{[^}]*min-width: 0;/', $css), 'its address box may shrink');
	pg_assert_same(1, preg_match('/\.ws-plan-every \{[^}]*display: flex;/', $css), 'Repeat every and its unit on one line');
	pg_assert_same(1, preg_match('/\.ws-plan-every \.pg-input \{[^}]*min-width: 0;/', $css), 'the number box may shrink beside its unit');
	pg_assert_same(0, preg_match('/\.ws-plan-(meeting|every)[^{]*\{[^}]*white-space: nowrap;[^}]*\}/', $css), 'nothing pinned on one line past the drawer');
	pg_assert_contains("el('span', 'pg-text-meta')", $drawer, 'the unit as meta text');
	pg_assert_contains("el('div', 'ws-plan-every')", $drawer, 'beside the number');
	pg_assert_same(2, substr_count($form, 'part.pair('), 'the days and the hours in pairs');
	pg_assert_same(false, strpos($form, "el('div', 'row"), 'no Bootstrap grid');
}

// D6: the line under the header says where the item goes (into the team
// plan, on whose rows), never who may see it: a row is seen by whoever may
// see that row on the Planning Board. One sentence for each value of Who.
function test_workspace_event_form_line_says_where_the_item_goes()
{
	$form = workspace_plan_drawers_look_test_event_form();
	$screen = workspace_plan_drawers_look_test_source('includes/workspace/screen.php');

	pg_assert_contains("t({ people: 'event_where_people', department: 'event_where_dept', company: 'event_where_company' }[scopeValue] || 'event_where_people')", $form, 'the line follows the three values of Who');
	pg_assert_true(strpos($form, 'whereText.textContent') > strpos($form, 'function scopeView()'), 'redrawn when Who changes');

	$sentences = array(
		'event_where_people' => 'Added to the team plan; it shows on the rows of the people you choose.',
		'event_where_dept' => 'Added to the team plan; it shows on the row of everyone in the department you choose.',
		'event_where_company' => 'Added to the team plan; it shows on everyone\\\'s row.',
	);

	foreach ($sentences as $key => $text) {
		pg_assert_contains("'" . $key . "'", $screen, $key . ' is a string of the screen');
		pg_assert_contains("lang('" . $text . "')", $screen, $key . ' says where the item goes');
	}

	pg_assert_same(0, preg_match("/'event_where_[a-z]+'\\s+=> lang\\('[^']*\\b(see|sees|only)\\b/i", $screen), 'no sentence promises who sees it');
}
