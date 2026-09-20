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
// Everything here is read-only. A form is a thing an operator draws in the page
// designer, with its own validation, upload folders, quiz answers and contact
// mapping; writing one through this API would mean reproducing that screen.
// What an integration needs is the other half - what came in - and that is what
// this offers.
//
// Uploaded files are reported as an id and nothing else. The URL would hand a
// file to an application that holds forms:read and not files:read, and the file
// behind a submission is often the most private thing on the site.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

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
function api_form_present($row, $fields = null) {

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

	return $form;

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

	return $field;

}

// One submission, with its values.
function api_form_submission_present($row, $values) {

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
		'values'            => $values
	);

}

// The pages that carry a form.
function api_forms_list($params) {

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$where = array();

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

	$id = (int)$params['id'];

	$row = api_row("SELECT page.page_id, page.page_name, page.page_title,
			COUNT(form_fields.id) AS field_count
		FROM form_fields
		INNER JOIN page ON page.page_id = form_fields.page_id
		WHERE form_fields.page_id = '" . $id . "'
		GROUP BY page.page_id, page.page_name, page.page_title");

	if (($row === null) || ((int)$row['field_count'] === 0)) {

		api_fail_not_found(lang('Form'));

	}

	$counts = api_row("SELECT COUNT(*) AS submission_count, MAX(submitted_timestamp) AS last_submission
		FROM forms WHERE page_id = '" . $id . "'");

	if ($counts !== null) {

		$row['submission_count'] = $counts['submission_count'];
		$row['last_submission']  = $counts['last_submission'];

	}

	$field_rows = api_rows("SELECT id, name, label, type, required, sort_order, multiple,
			office_use_only, contact_field, default_value
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

	api_ok(api_form_present($row, $fields));

}

// What came in on one form.
function api_form_submissions($params) {

	$id = (int)$params['id'];

	$exists = api_row("SELECT page_id FROM form_fields WHERE page_id = '" . $id . "' LIMIT 1");

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

	$rows = api_rows("SELECT forms.id, forms.page_id, forms.reference_code, forms.complete,
			forms.contact_id, forms.quiz_score, forms.submitted_timestamp
		FROM forms
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

	// The values for the whole page of submissions in one read. The label comes
	// from the field when it is still there; form_data keeps the name it had, so
	// a submission whose field was deleted still says what it was answering.
	$values = array();

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

	$out = array();

	foreach ($rows as $row) {

		$key = (int)$row['id'];

		$out[] = api_form_submission_present($row, isset($values[$key]) ? $values[$key] : array());

	}

	$next_cursor = null;

	if ($has_more) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// What api_form_present() returns, declared for the OpenAPI document. fields is
// carried by the single-form endpoint only.
function api_form_schema() {

	return array(
		'id'                 => 'integer',
		'name'               => 'string',
		'url'                => 'string',
		'title'              => 'string',
		'field_count'        => 'integer',
		'submission_count'   => 'integer',
		'last_submission_at' => 'string?',
		'fields'             => 'FormField[]'
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
		))
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
