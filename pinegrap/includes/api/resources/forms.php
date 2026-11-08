<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Forms, and what people filled in.
//
// The tables are named from the inside and read backwards from the outside, so
// it is worth saying once, here, rather than being worked out again at every
// query below:
//
//   form_fields   the form itself - one row per field, hanging off a PAGE.
//                 A form is therefore a page, and the id of a form is a page id.
//   forms         one SUBMISSION. The name is historical.
//   form_data     the values - one row per field per submission, carrying a
//                 copy of the field's name as it was at the time, so a
//                 submission keeps its meaning after the field is renamed or
//                 deleted.
//
// The forms themselves are read-only. A form is a thing an operator draws in
// the page designer, with its own validation, upload folders, quiz answers and
// contact mapping; writing one through this API would mean reproducing that
// screen. What an integration needs is the other half - what came in - and
// that is what this offers, with the one write the submission screen makes
// that a CRM or a help desk also needs: marking a submission complete, and
// filling in its text answers, the office-use fields among them.
//
// Uploaded files are reported as an id and nothing else. The URL would hand a
// file to an application that holds forms:read and not files:read, and the file
// behind a submission is often the most private thing on the site.
//
// An account with the forms right but the user role reaches only the forms on
// pages in folders it may edit, as on the submitted-form screens; a form
// outside them, and every submission made on it, is answered 404.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

// The condition that keeps a query to the forms an owner may open, on the
// column that holds the form's page id; empty from the manager role up, which
// opens every form. Below that the submitted-form screens open a form only
// where check_edit_access() passes for its page's folder: a folder the account
// holds edit rights on, or one below it - the set
// get_folders_that_user_has_access_to() returns.
function api_form_owner_filter($owner, $page_id_column) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return api_form_folder_filter(get_folders_that_user_has_access_to($owner['id']), $page_id_column);

}

// The same condition for a given set of folders: the form's page is filed in
// one of them, and no row passes when there is none. Pure, so the rule can be
// tested without a database.
function api_form_folder_filter($folder_ids, $page_id_column) {

	$ids = array();

	foreach ((array)$folder_ids as $folder_id) {

		if ((int)$folder_id > 0) {

			$ids[(int)$folder_id] = (int)$folder_id;

		}

	}

	if (empty($ids)) {

		return '0';

	}

	sort($ids);

	return $page_id_column . " IN (SELECT page_id FROM page WHERE page_folder IN (" . implode(',', $ids) . "))";

}

// A stored value, as something outside the site can use.
//
// A rich text field keeps its links and images with the site's own {path}
// token, which the page expands when it renders. Handed over unexpanded it is
// a broken link in somebody else's system, so it is expanded here the same way
// - to an absolute address, because the reader is not on this site.
function api_form_value_out($data) {

	$data = (string)$data;

	if (strpos($data, '{path}') === false) {

		return $data;

	}

	return str_replace('{path}', URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH, $data);

}

// A form: the page, with how much has been filled in on it.
function api_form_present($row, $fields = null, $facts = null) {

	$form = array(
		'id'      => (int)$row['page_id'],
		'name'    => $row['page_name'],
		'url'     => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($row['page_name']),
		'title'   => isset($row['page_title']) ? (string)$row['page_title'] : '',
		'field_count'      => (int)$row['field_count'],
		'submission_count' => isset($row['submission_count']) ? (int)$row['submission_count'] : 0,
		'last_submission_at' => (isset($row['last_submission']) && (int)$row['last_submission'] > 0)
			? api_time($row['last_submission'])
			: null
	);

	if ($fields !== null) {

		$form['fields'] = $fields;

	}

	// What a client needs before it writes a record, carried by the
	// single-form endpoint only (api_form_facts()).
	if ($facts !== null) {

		$form['framework']          = $facts['framework'];
		$form['is_collection']      = $facts['is_collection'];
		$form['record_url_pattern'] = $facts['record_url_pattern'];

	}

	return $form;

}

// The facts GET /forms/{id} adds about a form's page:
//
//   framework           the framework of the form page's design
//                       (pg_design_frameworks(): bootstrap5 or custom). A page
//                       without a design of its own takes its folder's, as the
//                       page itself does. Rich text cannot carry classes: the
//                       visitor profile removes them (html_allowed).
//   is_collection       the form is marked as a collection
//                       (custom_form_pages.collection, pg_coll_ready()).
//   record_url_pattern  where a record of the form is read on the site, from
//                       the first public page with a designed record view of
//                       the form (pg_ap_record_page()), else the first public
//                       classic form item view page; null when no public page
//                       shows them.
function api_form_facts($page_id, $page_folder, $page_style) {

	$page_id = (int)$page_id;

	$style_id = (int)$page_style;

	if (($style_id <= 0) && function_exists('get_style')) {

		$style_id = (int)get_style((int)$page_folder);

	}

	$framework = '';

	if (($style_id > 0) && function_exists('pg_style_framework_ready') && pg_style_framework_ready()) {

		$style = api_row("SELECT style_framework FROM style WHERE style_id = '" . $style_id . "'");

		$framework = ($style !== null) ? (string)$style['style_framework'] : '';

	}

	$is_collection = false;

	if (function_exists('pg_coll_ready') && pg_coll_ready()) {

		$custom = api_row("SELECT collection FROM custom_form_pages WHERE page_id = '" . $page_id . "'");

		$is_collection = ($custom !== null) && ((int)$custom['collection'] === 1);

	}

	$pattern = null;

	$record_page = api_form_record_page($page_id);

	if ($record_page !== null) {

		$pattern = api_form_record_url_pattern(URL_SCHEME . HOSTNAME_SETTING . PATH, $record_page['page_name'], $record_page['pretty']);

	}

	return array(
		'framework'          => function_exists('pg_design_framework_key') ? pg_design_framework_key($framework) : 'bootstrap5',
		'is_collection'      => $is_collection,
		'record_url_pattern' => $pattern
	);

}

// The page a form's records are read on (page_id, page_name) and whether the
// form names them in their addresses (pretty): the first public page with a
// designed record view of the form (pg_ap_record_page()), else the first
// public classic form item view page. Null when no public page shows them.
// Asked once per form per request.
function api_form_record_page($form_page_id) {

	static $pages = array();

	$form_page_id = (int)$form_page_id;

	if (!array_key_exists($form_page_id, $pages)) {

		require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');

		$page = pg_ap_record_page($form_page_id);

		if ($page === null) {

			$page = api_form_classic_record_page($form_page_id);

		}

		$pages[$form_page_id] = ($page === null) ? null : array(
			'page_id'   => (int)$page['page_id'],
			'page_name' => (string)$page['page_name'],
			'pretty'    => pg_sw_form_pretty_urls($form_page_id)
		);

	}

	return $pages[$form_page_id];

}

// The address a record is read at on the site, null when no public page shows
// the form's records or the record is not complete (only complete records
// are shown). $row: forms row with page_id, complete, reference_code,
// address_name.
function api_form_submission_url($row) {

	if (((int)$row['complete'] !== 1) || ((string)$row['reference_code'] === '')) {

		return null;

	}

	$page = api_form_record_page($row['page_id']);

	if ($page === null) {

		return null;

	}

	return pg_sw_record_url($page['page_name'], $row['reference_code'], isset($row['address_name']) ? $row['address_name'] : '', $page['pretty'], URL_SCHEME . HOSTNAME_SETTING . PATH);

}

// The first classic form item view page that shows a form's records to
// everyone: in a public folder (not archived, not the drafts, not in the
// bin) and not limited to the record's own submitter. The same rule
// pg_ap_record_page() applies to a designed record view. Null when none.
function api_form_classic_record_page($form_page_id) {

	$binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';

	$pages = api_rows("SELECT DISTINCT page.page_id, page.page_name, page.page_folder
		FROM form_item_view_pages
		INNER JOIN page ON page.page_id = form_item_view_pages.page_id
		INNER JOIN folder ON page.page_folder = folder.folder_id
		WHERE form_item_view_pages.custom_form_page_id = '" . (int)$form_page_id . "'
			AND form_item_view_pages.submitter_security = '0'
			AND page.page_type = 'form item view'
			AND folder.folder_archived = '0'" . $binned . "
		ORDER BY page.page_id ASC");

	foreach ($pages as $page) {

		$folder = (int)$page['page_folder'];

		if ((function_exists('pg_page_is_draft') && pg_page_is_draft($folder)) || (get_access_control_type($folder) !== 'public')) {

			continue;

		}

		return array('page_id' => (int)$page['page_id'], 'page_name' => (string)$page['page_name']);

	}

	return null;

}

// A record's address on the page that shows it, with the record's part as a
// placeholder: {address_name} when the form names its records in their
// addresses, {reference_code} otherwise. The same shape pg_sw_record_url()
// builds; a record with no address name is read at ?r={reference_code} even
// on a form with pretty addresses. Pure.
function api_form_record_url_pattern($base, $page_name, $pretty) {

	$url = $base . encode_url_path((string)$page_name);

	return $pretty ? $url . '/{address_name}' : $url . '?r={reference_code}';

}

// One field of a form's definition.
function api_form_field_present($row, $options = array()) {

	$field = array(
		'id'            => (int)$row['id'],
		'name'          => $row['name'],
		'label'         => $row['label'],
		'type'          => $row['type'],
		'required'      => ((int)$row['required'] === 1),
		'sort_order'    => (int)$row['sort_order'],
		'multiple'      => ((int)$row['multiple'] === 1),
		// A field the visitor never sees: the operator fills it in afterwards on
		// the submission screen. An integration reading its own site's records
		// is entitled to it, but it is told which kind it is looking at.
		'office_use_only' => ((int)$row['office_use_only'] === 1),
		// Where the value lands on the contact record, when the form feeds the
		// address book. Empty when it feeds nothing.
		'contact_field' => (string)$row['contact_field'],
		'default_value' => (string)$row['default_value'],
		'options'       => $options
	);

	// A text area drawn with the rich-text editor: its answer is HTML (stored
	// with the form data type html), reduced on the way in to what
	// pg_sanitize_rich_text() keeps. The other fields carry null.
	$rich = api_form_field_is_rich_text($row);

	$field['rich_text']    = $rich;
	$field['html_allowed'] = $rich ? api_form_html_allowed(defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '') : null;
	$field['max_length']   = $rich ? api_form_html_max_length() : null;

	return $field;

}

// Whether a field row is a rich text area: a text area with the editor on.
function api_form_field_is_rich_text($row) {

	return ((string)$row['type'] === 'text area') && !empty($row['wysiwyg']);

}

// The longest HTML answer a rich text field takes, in characters. The column
// (form_data.data) is a longtext; this is the ceiling the API holds a long
// text body to elsewhere (a message, a campaign body).
function api_form_html_max_length() {

	return 200000;

}

// The HTML a rich text answer keeps, from the lists the sanitiser reads
// (pg_rich_text_allow_list()), under the visitor profile the site prints with:
// class is removed, style keeps the listed properties, an image must be on
// one of image_hosts or relative, and a link's rel is set by the site.
// Anything outside is removed (dropped_tags with their content) or unwrapped.
// Pure: $host is the site's host name.
function api_form_html_allowed($host) {

	$rules = pg_rich_text_allow_list();

	$attributes = $rules['attributes'];

	unset($attributes['class']);

	// rel is not listed: the site writes its own on every link.
	$tag_attributes = array_map(function ($tag, $names) {

		unset($names['rel']);

		return array('tag' => (string)$tag, 'attributes' => array_map('strval', array_keys($names)));

	}, array_keys($rules['tag_attributes']), array_values($rules['tag_attributes']));

	return array(
		'tags'             => array_map('strval', array_keys($rules['tags'])),
		'attributes'       => array_map('strval', array_keys($attributes)),
		'tag_attributes'   => $tag_attributes,
		'style_properties' => array_map('strval', array_keys($rules['visitor_style_properties'])),
		'url_schemes'      => array_map('strval', array_keys($rules['url_schemes'])),
		'image_hosts'      => ((string)$host !== '') ? array((string)$host) : array(),
		'dropped_tags'     => array_map('strval', array_keys($rules['dropped_tags']))
	);

}

// One submission, with its values.
function api_form_submission_present($row, $values) {

	$state = pg_form_payment_state($row);

	return array(
		'id'                => (int)$row['id'],
		'form_id'           => (int)$row['page_id'],
		'reference_code'    => (string)$row['reference_code'],
		// A saved-but-not-sent submission is 0. The form can be set up to let a
		// visitor come back and finish it, so an integration that treats every
		// row as a completed enquiry would answer half-written ones.
		'complete'          => ((int)$row['complete'] === 1),
		'contact_id'        => ((int)$row['contact_id'] > 0) ? (int)$row['contact_id'] : null,
		'quiz_score'        => ((int)$row['quiz_score'] > 0) ? (int)$row['quiz_score'] : null,
		'submitted_at'      => api_time($row['submitted_timestamp']),
		'submitted_at_unix' => (int)$row['submitted_timestamp'],
		// Last changed, by the visitor finishing it or by staff editing it in
		// the panel - what updated_since filters on.
		'updated_at'        => api_time(isset($row['last_modified_timestamp']) ? $row['last_modified_timestamp'] : 0),
		// The fee of a designed form (includes/fn/form_payments.php): null
		// for a record without one; the number once the order is completed.
		'order_number'      => api_form_submission_order_number($row),
		'paid'              => ($state === 'none') ? null : ($state === 'paid'),
		// Where the record is read on the site (api_form_submission_url()),
		// set by the handler beside the row; null when no page shows it.
		'url'               => isset($row['record_url']) ? (string)$row['record_url'] : null,
		'values'            => $values
	);

}

// The pages that carry a form.
function api_forms_list($params) {

	$app = api_current_app();

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$where = array();

	$reach = api_form_owner_filter($app['owner'], 'form_fields.page_id');

	if ($reach !== '') {

		$where[] = $reach;

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "page.page_id > '" . $cursor['i'] . "'";

	}

	$where_sql = empty($where) ? '' : ' AND ' . implode(' AND ', $where);

	// One row per page that has fields. The counts come from the two tables that
	// hang off it, and a page with a form nobody has filled in yet still belongs
	// in the list - it is a form, it is just empty.
	$rows = api_rows("SELECT page.page_id, page.page_name, page.page_title,
			COUNT(form_fields.id) AS field_count
		FROM form_fields
		INNER JOIN page ON page.page_id = form_fields.page_id
		WHERE (form_fields.page_id > 0)" . $where_sql . "
		GROUP BY page.page_id, page.page_name, page.page_title
		ORDER BY page.page_id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	if (empty($rows)) {

		api_ok_list(array(), $limit, null);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['page_id'];

	}

	$counts = array();

	foreach (api_rows("SELECT page_id, COUNT(*) AS submission_count,
			MAX(submitted_timestamp) AS last_submission
		FROM forms
		WHERE page_id IN (" . implode(',', $ids) . ")
		GROUP BY page_id") as $count_row) {

		$counts[(int)$count_row['page_id']] = $count_row;

	}

	$out = array();

	foreach ($rows as $row) {

		$key = (int)$row['page_id'];

		if (isset($counts[$key])) {

			$row['submission_count'] = $counts[$key]['submission_count'];
			$row['last_submission']  = $counts[$key]['last_submission'];

		}

		$out[] = api_form_present($row);

	}

	$next_cursor = null;

	if ($has_more) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)$last['page_id'], (int)$last['page_id']);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// One form, with its fields.
function api_forms_get($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$reach = api_form_owner_filter($app['owner'], 'form_fields.page_id');

	$row = api_row("SELECT page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style,
			COUNT(form_fields.id) AS field_count
		FROM form_fields
		INNER JOIN page ON page.page_id = form_fields.page_id
		WHERE form_fields.page_id = '" . $id . "'" . (($reach !== '') ? " AND " . $reach : '') . "
		GROUP BY page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style");

	if (($row === null) || ((int)$row['field_count'] === 0)) {

		api_fail_not_found(lang('Form'));

	}

	$counts = api_row("SELECT COUNT(*) AS submission_count, MAX(submitted_timestamp) AS last_submission
		FROM forms WHERE page_id = '" . $id . "'");

	if ($counts !== null) {

		$row['submission_count'] = $counts['submission_count'];
		$row['last_submission']  = $counts['last_submission'];

	}

	$fields = api_form_fields_present($id);

	api_ok(api_form_present($row, $fields, api_form_facts($id, $row['page_folder'], $row['page_style'])));

}

// The fields of one form as api_form_field_present() gives them, in form
// order, with the choices of the three types that have them.
function api_form_fields_present($id) {

	$id = (int)$id;

	$field_rows = api_rows("SELECT id, name, label, type, required, sort_order, multiple,
			office_use_only, contact_field, default_value, wysiwyg
		FROM form_fields
		WHERE page_id = '" . $id . "'
		ORDER BY sort_order ASC, id ASC");

	// The choices, for the three types that have them, in one read rather than
	// one per field.
	$options = array();

	foreach (api_rows("SELECT form_field_id, id, label, value, default_selected, sort_order
		FROM form_field_options
		WHERE page_id = '" . $id . "'
		ORDER BY form_field_id ASC, sort_order ASC, id ASC") as $option) {

		$key = (int)$option['form_field_id'];

		if (!isset($options[$key])) { $options[$key] = array(); }

		$options[$key][] = array(
			'id'       => (int)$option['id'],
			'label'    => (string)$option['label'],
			'value'    => (string)$option['value'],
			'selected' => ((int)$option['default_selected'] === 1)
		);

	}

	$fields = array();

	foreach ($field_rows as $field_row) {

		$key = (int)$field_row['id'];

		$fields[] = api_form_field_present($field_row, isset($options[$key]) ? $options[$key] : array());

	}

	return $fields;

}

// The values of a set of submissions, keyed by submission id, in one read.
function api_form_submission_values($ids) {

	$ids = array_values(array_unique(array_map('intval', $ids)));

	// The values for the whole page of submissions in one read. The label comes
	// from the field when it is still there; form_data keeps the name it had, so
	// a submission whose field was deleted still says what it was answering.
	$values = array();

	if (empty($ids)) {

		return $values;

	}

	foreach (api_rows("SELECT form_data.form_id, form_data.form_field_id, form_data.name,
			form_data.data, form_data.file_id,
			form_fields.label, form_fields.type
		FROM form_data
		LEFT JOIN form_fields ON form_fields.id = form_data.form_field_id
		WHERE form_data.form_id IN (" . implode(',', $ids) . ")
		ORDER BY form_data.form_id ASC, form_fields.sort_order ASC, form_data.id ASC") as $value) {

		$key = (int)$value['form_id'];

		if (!isset($values[$key])) { $values[$key] = array(); }

		$values[$key][] = array(
			'field_id' => (int)$value['form_field_id'],
			'name'     => (string)$value['name'],
			'label'    => ($value['label'] === null) ? null : (string)$value['label'],
			'type'     => ($value['type'] === null) ? null : (string)$value['type'],
			'value'    => api_form_value_out($value['data']),
			// The file itself is fetched from /files/{id} with files:read.
			'file_id'  => ((int)$value['file_id'] > 0) ? (int)$value['file_id'] : null
		);

	}

	return $values;

}

// What came in on one form.
function api_form_submissions($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$reach = api_form_owner_filter($app['owner'], 'form_fields.page_id');

	$exists = api_row("SELECT form_fields.page_id FROM form_fields
		WHERE form_fields.page_id = '" . $id . "'" . (($reach !== '') ? " AND " . $reach : '') . "
		LIMIT 1");

	if ($exists === null) {

		api_fail_not_found(lang('Form'));

	}

	$where = array("forms.page_id = '" . $id . "'");

	// Saved-but-not-sent rows are out of the way by default: an integration
	// asking for "the enquiries" means the ones that were sent.
	if (isset($params['complete'])) {

		$where[] = "forms.complete = '" . ((int)$params['complete'] === 1 ? '1' : '0') . "'";

	} else {

		$where[] = "forms.complete = '1'";

	}

	if (isset($params['submitted_since'])) {

		$where[] = "forms.submitted_timestamp >= '" . (int)$params['submitted_since'] . "'";

	}

	if (isset($params['updated_since'])) {

		$where[] = "forms.last_modified_timestamp >= '" . (int)$params['updated_since'] . "'";

	}

	if (isset($params['reference_code']) && $params['reference_code'] !== '') {

		$where[] = "forms.reference_code = '" . escape($params['reference_code']) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "forms.id > '" . $cursor['i'] . "'";

	}

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_form_submission_select() . "
		WHERE " . implode(' AND ', $where) . "
		ORDER BY forms.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	if (empty($rows)) {

		api_ok_list(array(), $limit, null);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	$values = api_form_submission_values($ids);

	$out = array();

	foreach ($rows as $row) {

		$key = (int)$row['id'];

		$row['record_url'] = api_form_submission_url($row);

		$out[] = api_form_submission_present($row, isset($values[$key]) ? $values[$key] : array());

	}

	$next_cursor = null;

	if ($has_more) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// The fee order's number, null without a fee order or before the order is
// completed (an incomplete order's number is 0).
function api_form_submission_order_number($row) {

	if (pg_form_payment_state($row) === 'none') {

		return null;

	}

	$number = isset($row['order_number']) ? (string)$row['order_number'] : '';

	return (($number !== '') && ($number !== '0')) ? $number : null;

}

// The submission columns every reader of one submission selects; the fee
// columns only once the upgrade added them.
function api_form_submission_select() {

	if (pg_form_payment_ready()) {

		return "SELECT forms.id, forms.page_id, forms.reference_code, forms.address_name, forms.complete,
				forms.contact_id, forms.quiz_score, forms.submitted_timestamp, forms.last_modified_timestamp,
				forms.order_id, forms.paid_at, orders.order_number
			FROM forms
			LEFT JOIN orders ON orders.id = forms.order_id";

	}

	return "SELECT forms.id, forms.page_id, forms.reference_code, forms.address_name, forms.complete,
			forms.contact_id, forms.quiz_score, forms.submitted_timestamp, forms.last_modified_timestamp
		FROM forms";

}

// One submission of one form, or a 404. The form is part of the address so a
// submission id cannot be read through a form it was not made on, and a form
// the owner may not open hides its submissions the same way.
function api_form_submission_owned($form_id, $submission_id) {

	$app = api_current_app();

	$reach = api_form_owner_filter($app['owner'], 'forms.page_id');

	$row = api_row(api_form_submission_select() . "
		WHERE forms.id = '" . (int)$submission_id . "' AND forms.page_id = '" . (int)$form_id . "'" . (($reach !== '') ? " AND " . $reach : '') . "
		LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Form submission'));

	}

	$row['record_url'] = api_form_submission_url($row);

	return $row;

}

function api_form_submission_get($params) {

	$row = api_form_submission_owned($params['id'], $params['submission_id']);

	$values = api_form_submission_values(array($row['id']));

	api_ok(api_form_submission_present($row, isset($values[(int)$row['id']]) ? $values[(int)$row['id']] : array()));

}

// The field types whose answer is plain text, the ones the submission screen
// lets staff retype. A choice is tied to the options the form offers and an
// upload is a file; neither is something to overwrite with a string.
function api_form_text_field_types() {

	return array('text box', 'text area', 'email address');

}

// Whether an update may write a field: a text box, a text area (a rich one
// takes HTML), an e-mail address, or a choice field that takes one answer
// (a pick list without multiple picks, a radio button group) - a collection's
// Status field is one, written as sent among its choices. A check box group,
// a multiple pick list, an upload, a signature and the date and time fields
// are not.
function api_form_field_updatable($field) {

	if (in_array($field['type'], api_form_text_field_types(), true)) {

		return true;

	}

	return in_array($field['type'], array('pick list', 'radio button'), true) && !api_form_field_takes_many($field);

}

// Marking a submission complete, or filling in its text answers.
//
// The write the submitted-form screen makes, for the system that handles the
// enquiry outside the panel: a help desk closing it, a CRM writing the outcome
// into the office-use fields the visitor never sees. Answers are addressed by
// the field's name, as they are read, and only the plain-text kinds can be
// written. A field the visitor left empty gets its row now; one that has a row
// keeps it, so a submission never ends up with two answers to one field.
function api_form_submission_update($params) {

	$app = api_current_app();

	$row = api_form_submission_owned($params['id'], $params['submission_id']);

	$id = (int)$row['id'];

	$writes = array();

	if (array_key_exists('values', $params)) {

		$fields = array();

		foreach (api_rows("SELECT id, name, type, multiple, wysiwyg FROM form_fields
			WHERE page_id = '" . (int)$row['page_id'] . "' AND name <> ''
			ORDER BY sort_order ASC, id ASC") as $field) {

			if (!isset($fields[$field['name']])) {

				$fields[$field['name']] = $field;

			}

		}

		$choices = array();

		foreach (api_rows("SELECT form_field_id, value FROM form_field_options WHERE page_id = '" . (int)$row['page_id'] . "'") as $option) {

			$choices[(int)$option['form_field_id']][] = (string)$option['value'];

		}

		foreach ($params['values'] as $item) {

			if (!is_array($item) || !isset($item['name']) || !is_scalar($item['name'])
				|| (array_key_exists('value', $item) && !is_scalar($item['value']) && ($item['value'] !== null))) {

				api_fail_validation(lang('Each value must be an object of {name, value}.'), 'values');

			}

			$name = trim((string)$item['name']);

			if (!isset($fields[$name])) {

				api_fail_validation(lang(array('string' => 'This form has no field called {var:1}.', 'vars' => mb_substr($name, 0, 60))), 'values');

			}

			if (!api_form_field_updatable($fields[$name])) {

				api_fail_validation(lang(array('string' => 'The field {var:1} cannot be changed through the API: only text, e-mail address and single-choice fields can.', 'vars' => $name)), 'values');

			}

			$field_id = (int)$fields[$name]['id'];

			$checked = api_form_answer_check($fields[$name], isset($item['value']) ? (string)$item['value'] : '', isset($choices[$field_id]) ? $choices[$field_id] : array());

			if (is_string($checked)) {

				api_fail_validation($checked, 'values');

			}

			$value = $checked['value'];

			if (($fields[$name]['type'] === 'email address') && ($value !== '') && !validate_email_address($value)) {

				api_fail_validation(lang(array('string' => 'The answer to {var:1} is not an e-mail address.', 'vars' => $name)), 'values');

			}

			$writes[$field_id] = array('name' => $name, 'value' => $value, 'type' => api_form_field_is_rich_text($fields[$name]) ? 'html' : 'standard');

		}

	}

	if (empty($writes) && !array_key_exists('complete', $params)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'form_submission', array('id' => $id, 'values' => count($writes)));

	// The item as it was, for its revision (collection items only;
	// includes/fn/record_revisions.php).
	$revision_before = pg_record_revision_before($id);

	foreach ($writes as $field_id => $write) {

		$kept = (int)api_value("SELECT id FROM form_data
			WHERE form_id = '" . $id . "' AND form_field_id = '" . $field_id . "'
			ORDER BY id ASC LIMIT 1");

		if ($kept > 0) {

			api_exec("UPDATE form_data SET data = '" . escape($write['value']) . "', type = '" . $write['type'] . "' WHERE id = '" . $kept . "' LIMIT 1");

		} else {

			api_exec("INSERT INTO form_data (form_id, form_field_id, data, name, type)
				VALUES ('" . $id . "', '" . $field_id . "', '" . escape($write['value']) . "', '" . escape($write['name']) . "', '" . $write['type'] . "')");

		}

	}

	$set = array();

	if (array_key_exists('complete', $params)) {

		$set[] = "complete = '" . ($params['complete'] ? '1' : '0') . "'";

	}

	$set[] = "last_modified_user_id = '" . (int)$app['owner']['id'] . "'";

	$set[] = "last_modified_timestamp = UNIX_TIMESTAMP()";

	api_exec("UPDATE forms SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");

	// A form with pretty URLs on names its records after their Title field;
	// a retyped title renames the record, as the submission screen does.
	if ($writes && check_if_pretty_urls_are_enabled($row['page_id'])) {

		update_submitted_form_address_name($id);

	}

	pg_record_revision_after($id, $revision_before, 'api', (int)$app['owner']['id']);

	log_activity(lang(array(
		'string' => 'The submitted form ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['reference_code'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_form_submission_owned($params['id'], $id);

	$values = api_form_submission_values(array($id));

	api_ok(api_form_submission_present($row, isset($values[$id]) ? $values[$id] : array()));

}

// The fields of one form, on their own: what a client reads before it sends
// a submission. The same field objects GET /forms/{id} carries, one page.
function api_form_fields($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$reach = api_form_owner_filter($app['owner'], 'form_fields.page_id');

	$exists = api_row("SELECT form_fields.page_id FROM form_fields
		WHERE form_fields.page_id = '" . $id . "'" . (($reach !== '') ? " AND " . $reach : '') . "
		LIMIT 1");

	if ($exists === null) {

		api_fail_not_found(lang('Form'));

	}

	$fields = api_form_fields_present($id);

	api_ok_list($fields, max(1, count($fields)), null);

}

// The field types an answer cannot be sent for: a file is uploaded and a
// signature is drawn, neither is a string an integration can make up.
function api_form_unsendable_field_types() {

	return array('file upload', 'signature');

}

// Whether a field takes several answers: a check box group, or a pick list
// that lets more than one choice be picked.
function api_form_field_takes_many($field) {

	return ($field['type'] === 'check box')
		|| (($field['type'] === 'pick list') && ((int)$field['multiple'] === 1));

}

// The longest answer a field takes, in characters: a rich text answer is HTML
// and takes api_form_html_max_length(), any other 10000.
function api_form_answer_max_length($field) {

	return api_form_field_is_rich_text($field) ? api_form_html_max_length() : 10000;

}

// The site's own addresses, which a rich text answer folds back to {path}:
// the absolute address of the site path under the configured scheme and the
// other one, and the site path itself.
function api_form_site() {

	$host = defined('HOSTNAME_SETTING') ? (string)HOSTNAME_SETTING : '';

	$path = defined('PATH') ? (string)PATH : '/';

	$bases = array();

	if ($host !== '') {

		$bases[] = (defined('URL_SCHEME') ? URL_SCHEME : 'https://') . $host . $path;
		$bases[] = 'https://' . $host . $path;
		$bases[] = 'http://' . $host . $path;

	}

	return array('bases' => array_values(array_unique($bases)), 'path' => $path);

}

// A rich text answer as the site stores it: reduced by the sanitiser a
// visitor's answer goes through (the visitor profile, as custom_form.php
// does), then every link and image on the site's own address written with the
// {path} placeholder, so the record keeps working when the site moves. Pure:
// $site is api_form_site().
function api_form_html_answer_in($html, $site) {

	$html = pg_sanitize_rich_text((string)$html);

	foreach ($site['bases'] as $base) {

		$html = str_replace(array('href="' . $base, 'src="' . $base), array('href="{path}', 'src="{path}'), $html);

	}

	return prepare_rich_text_editor_content_for_input($html, $site['path']);

}

// One answer to one field, checked and made ready to store: array('value' =>
// the string to store), or the refusal as a string. The length of the sent
// text (api_form_answer_max_length()), a choice field's option values, and a
// rich text answer through api_form_html_answer_in(). Pure apart from reading
// the site's addresses when $site is null.
function api_form_answer_check($field, $value, $choices, $site = null) {

	$name = (string)$field['name'];

	$value = trim(str_replace("\r\n", "\n", (string)$value));

	$max = api_form_answer_max_length($field);

	if (mb_strlen($value) > $max) {

		return lang(array('string' => 'The answer to {var:1} is longer than the {var:2} characters accepted.', 'vars' => array($name, $max)));

	}

	if (($value !== '') && pg_cf_type_has_options($field['type']) && !in_array($value, (array)$choices, true)) {

		return lang(array('string' => 'The answer to {var:1} is not one of the choices the field offers.', 'vars' => $name));

	}

	if (($value !== '') && api_form_field_is_rich_text($field)) {

		$value = trim(api_form_html_answer_in($value, ($site === null) ? api_form_site() : $site));

	}

	return array('value' => $value);

}

// The answers of a new submission, keyed by field id, from the {name, value}
// objects sent: a string per field, a list for a field that takes several
// (its name sent once per choice). Returns the refusal as a string instead
// when one is not acceptable. Pure: the fields and their choices are given.
//
// $fields   field rows (id, name, type, multiple, wysiwyg), in form order
// $choices  field id => the option values the field offers
// $site     the site's addresses for a rich text answer (api_form_site());
//           read from the site when not given
function api_form_submission_answers($items, $fields, $choices, $site = null) {

	$by_name = array();

	foreach ($fields as $field) {

		$name = (string)$field['name'];

		if (($name !== '') && !isset($by_name[$name])) {

			$by_name[$name] = $field;

		}

	}

	$answers = array();

	foreach ((array)$items as $item) {

		if (!is_array($item) || !isset($item['name']) || !is_scalar($item['name'])
			|| (array_key_exists('value', $item) && !is_scalar($item['value']) && ($item['value'] !== null))) {

			return lang('Each value must be an object of {name, value}.');

		}

		$name = trim((string)$item['name']);

		if (!isset($by_name[$name])) {

			return lang(array('string' => 'This form has no field called {var:1}.', 'vars' => mb_substr($name, 0, 60)));

		}

		$field = $by_name[$name];

		if (in_array($field['type'], api_form_unsendable_field_types(), true)) {

			return lang(array('string' => 'The field {var:1} takes a file or a drawn signature and cannot be sent through the API.', 'vars' => $name));

		}

		$key = (int)$field['id'];

		$value = api_form_answer_check($field, isset($item['value']) ? (string)$item['value'] : '', isset($choices[$key]) ? $choices[$key] : array(), $site);

		if (is_string($value)) {

			return $value;

		}

		$value = $value['value'];

		if (api_form_field_takes_many($field)) {

			if (!isset($answers[$key])) { $answers[$key] = array(); }

			if ($value !== '') { $answers[$key][] = $value; }

			continue;

		}

		if (isset($answers[$key])) {

			return lang(array('string' => 'The field {var:1} takes one answer and was sent more than once.', 'vars' => $name));

		}

		$answers[$key] = $value;

	}

	return $answers;

}

// Whether a user row (user_id, user_role, user_manage_forms; null when there
// is no enabled user) may be named as a record's author: a member of staff
// who may work with forms, the rule the forms scope itself follows - a role
// below the user role, or the forms right. Pure.
function api_form_author_is_staff($user) {

	if (!is_array($user) || ((int)$user['user_id'] <= 0)) {

		return false;

	}

	// user_manage_forms is enum('no','yes'); '1' and 'true' are read as yes too,
	// as api_load_owner() reads the right, because some releases wrote them.
	return ((int)$user['user_role'] < 3)
		|| in_array(strtolower(trim((string)$user['user_manage_forms'])), array('yes', '1', 'true'), true);

}

// A new submission, as a member of staff makes one on the Add Submitted Form
// screen: through the same checks and the same write
// (includes/fn/submitted_form_writes.php), with the owner as the submitter.
// The answers are held in a live form for the length of the call, because the
// screen's checks and its value writer read them from one.
function api_form_submission_create($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$reach = api_form_owner_filter($app['owner'], 'page.page_id');

	$form = api_row("SELECT page.page_id, page.page_name, custom_form_pages.form_name
		FROM page
		LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id
		WHERE page.page_id = '" . $id . "' AND " . pg_form_page_sql('page') . (($reach !== '') ? " AND " . $reach : '') . "
		LIMIT 1");

	if ($form === null) {

		api_fail_not_found(lang('Form'));

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
	include_once(PG_FUNCTIONS_DIR . '/liveform.class.php');

	$fields = pg_submitted_form_fields($id);

	$choices = array();

	foreach (api_rows("SELECT form_field_id, value FROM form_field_options WHERE page_id = '" . $id . "'") as $option) {

		$choices[(int)$option['form_field_id']][] = (string)$option['value'];

	}

	$answers = api_form_submission_answers(isset($params['values']) ? $params['values'] : array(), $fields, $choices);

	if (!is_array($answers)) {

		api_fail_validation($answers, 'values');

	}

	$complete = array_key_exists('complete', $params) ? (bool)$params['complete'] : true;

	// The record's author: the owner of the key, or a member of staff named
	// by author_user_id (a byline shows forms.user_id as the submitter).
	$author_id = (int)$app['owner']['id'];

	if (isset($params['author_user_id']) && ((int)$params['author_user_id'] !== $author_id)) {

		$author = api_row("SELECT user_id, user_role, user_manage_forms FROM user
			WHERE user_id = '" . (int)$params['author_user_id'] . "'" . pg_user_enabled_sql() . "
			LIMIT 1");

		if (!api_form_author_is_staff($author)) {

			api_fail_validation(lang('The author must be an enabled member of staff who may work with forms.'), 'author_user_id');

		}

		$author_id = (int)$author['user_id'];

	}

	$pretty_urls = check_if_pretty_urls_are_enabled($id);

	// The forms scope is held by roles below the user role and by a user with
	// the forms right, and the row rule above has checked the folder: the
	// people the screen shows the office use only fields to.
	$office_use_only_access = true;

	$liveform = new liveform('api_form_submission', $id);

	$liveform->remove_form();

	foreach ($answers as $field_id => $value) {

		$liveform->assign_field_value($field_id, $value);

	}

	pg_submitted_form_create_check($liveform, $fields, $id, $complete, $office_use_only_access, $pretty_urls, array());

	$errors = $liveform->field_error_texts();

	if ($errors) {

		$liveform->remove_form();

		$field_id = key($errors);

		$message = (string)current($errors);

		foreach ($fields as $field) {

			if (((string)$field['id'] === (string)$field_id) && ($message === '')) {

				$message = lang(array('string' => '{var:1} is required.', 'vars' => (string)$field['name']));

			}

		}

		api_fail_validation($message, 'values');

	}

	api_dry_run_stop('created', 'form_submission', array('form_id' => $id, 'values' => count($answers), 'complete' => $complete));

	$created = pg_submitted_form_create($liveform, $fields, $id, $complete, $author_id, $office_use_only_access, $pretty_urls, array(), !empty($params['send_notifications']));

	$liveform->remove_form();

	if ($created === false) {

		api_fail(500, 'server_error', lang('The submission could not be saved.'));

	}

	// The author is the submitter; the change is the key owner's.
	if ($author_id !== (int)$app['owner']['id']) {

		api_exec("UPDATE forms SET last_modified_user_id = '" . (int)$app['owner']['id'] . "' WHERE id = '" . (int)$created['id'] . "' LIMIT 1");

	}

	$form_name = ((string)$form['form_name'] !== '') ? (string)$form['form_name'] : (string)$form['page_name'];

	log_activity(lang(array(
		'string' => 'The submitted form ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($created['reference_code'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	// A completed record tells whoever listens, as a visitor's submission
	// does; a record saved for later is not an enquiry yet.
	if ($complete) {

		pg_announce('form.submitted', array(
			'id'             => (int)$created['id'],
			'form_id'        => $id,
			'form_name'      => $form_name,
			'reference_code' => $created['reference_code'],
			'contact_id'     => null
		));

	}

	$row = api_form_submission_owned($id, $created['id']);

	$values = api_form_submission_values(array($created['id']));

	api_ok(api_form_submission_present($row, isset($values[(int)$created['id']]) ? $values[(int)$created['id']] : array()), 201);

}

// Deleting a submission through the delete the submitted form screens share,
// with its files, signatures, views and comment thread.
function api_form_submission_delete($params) {

	$app = api_current_app();

	$row = api_form_submission_owned($params['id'], $params['submission_id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'form_submission', array('id' => $id, 'form_id' => (int)$row['page_id']));

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');

	if (pg_submitted_form_delete($id) === false) {

		api_fail(500, 'server_error', lang('The submission could not be deleted.'));

	}

	log_activity(lang(array(
		'string' => 'The submitted form ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['reference_code'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}

// What api_form_present() returns, declared for the OpenAPI document. fields,
// framework, is_collection and record_url_pattern are carried by the
// single-form endpoint only. name is the page's name, a DEFAULT
// NULL column that every create path writes, so not nullable
// (api_openapi_type_spec() says when a field is).
function api_form_schema() {

	return array(
		'id'                 => 'integer',
		'name'               => 'string',
		'url'                => 'string',
		'title'              => 'string',
		'field_count'        => 'integer',
		'submission_count'   => 'integer',
		'last_submission_at' => 'string?',
		'fields'             => 'FormField[]',
		'framework'          => 'string',
		'is_collection'      => 'boolean',
		'record_url_pattern' => 'string?'
	);

}

// What api_form_field_present() returns.
function api_form_field_schema() {

	return array(
		'id'              => 'integer',
		'name'            => 'string',
		'label'           => 'string',
		'type'            => 'string',
		'required'        => 'boolean',
		'sort_order'      => 'integer',
		'multiple'        => 'boolean',
		'office_use_only' => 'boolean',
		'contact_field'   => 'string',
		'default_value'   => 'string',
		'options'         => array(array(
			'id'       => 'integer',
			'label'    => 'string',
			'value'    => 'string',
			'selected' => 'boolean'
		)),
		'rich_text'       => 'boolean',
		'html_allowed'    => 'FormHtmlAllowed?',
		'max_length'      => 'integer?'
	);

}

// What api_form_html_allowed() returns.
function api_form_html_allowed_schema() {

	return array(
		'tags'             => 'string[]',
		'attributes'       => 'string[]',
		'tag_attributes'   => array(array(
			'tag'        => 'string',
			'attributes' => 'string[]'
		)),
		'style_properties' => 'string[]',
		'url_schemes'      => 'string[]',
		'image_hosts'      => 'string[]',
		'dropped_tags'     => 'string[]'
	);

}

// What api_form_submission_present() returns.
function api_form_submission_schema() {

	return array(
		'id'                => 'integer',
		'form_id'           => 'integer',
		'reference_code'    => 'string',
		'complete'          => 'boolean',
		'contact_id'        => 'integer?',
		'quiz_score'        => 'integer?',
		'submitted_at'      => 'string?',
		'submitted_at_unix' => 'integer',
		'updated_at'        => 'string?',
		'order_number'      => 'string?',
		'paid'              => 'boolean?',
		'url'               => 'string?',
		'values'            => array(array(
			'field_id' => 'integer',
			'name'     => 'string',
			'label'    => 'string?',
			'type'     => 'string?',
			'value'    => 'string',
			'file_id'  => 'integer?'
		))
	);

}
