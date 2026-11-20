<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's forms topic (includes/fn/agent_guide.php): forms, their
 * submissions and collections - the records, the tool for each job, what a
 * write needs first and the frequent mistakes. Tools are named as
 * {tool:route.id}; the field types an update writes are the ones
 * api_form_field_updatable() takes, which tests/agent_guide_areas_test.php
 * compares.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_forms_sections() {

	return array(

		array(
			'id'      => 'records',
			'heading' => 'The records',
			'lines'   => array(
				'Form: a page that carries fields, so a form id is a page id. Its fields are made on the page by a person; no tool adds a form or a field.',
				'Field: name (what an answer is addressed by), label, type, required, options for a choice field, office_use_only for one the visitor never sees. A rich text area says rich_text, html_allowed and max_length.',
				'Submission: one filling-in of a form, with its answers, reference_code and complete. A half-written one (complete false) is not listed unless asked for.',
				'Collection: a form whose submissions are shown as pages of their own, such as blog posts: is_collection on the form, record_url_pattern for where a record is read, url on each record. A record keeps revisions.'
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tool for which job',
			'lines'   => array(
				'Find the form: {tool:forms.list}; read its facts with {tool:forms.get} (is_collection, record_url_pattern, framework) and its fields with {tool:forms.fields}.',
				'Read what came in: {tool:forms.submissions} (cursor paged, oldest first), one with {tool:forms.submission_get}.',
				'A new submission or collection record: {tool:forms.submission_create}.',
				'Change one: {tool:forms.submission_update} writes text box, text area and email address answers, and a single-answer pick list or radio button (a collection\'s Status field) with one of its option values.',
				'A picture for a record: {tool:files.create} with form_id, then its url in an <img> of the rich text answer.',
				'A record\'s history: {tool:forms.submission_revisions}, {tool:forms.submission_revision_get}; put one back with {tool:forms.submission_revision_restore}.',
				'Remove one: {tool:forms.submission_delete}; it cannot be undone.'
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Read {tool:forms.fields} first: an answer is sent as {name, value} by the field name.',
				'Send with dry_run true first: the required fields, dates, e-mail addresses and option values are checked and nothing is written.',
				'Publish a collection record (its Status field) only when the person asked to publish.',
				'send_notifications e-mails the people the form tells; leave it off unless the person asked.'
			)
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'Addressing an answer by its label, or a choice by its text instead of its option value.',
				'Sending a check box group as one joined value: send its name once per choice.',
				'Rich text with class attributes, framework classes or pictures from another site: html_allowed drops them.',
				'Sending a file upload or signature answer: refused; dates, check box groups and uploads are also refused on an update.'
			)
		)

	);

}
