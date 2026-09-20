<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Customers.
//
// This replaces the old users endpoint, which was a different thing wearing the
// same word: it listed the site's own operator accounts, with their email
// addresses and their roles, to any application that held the permission. An
// integration has no use for the people who run the site; what it needs is the
// people who buy, which is the contacts table.
//
// Writing is deliberately narrow: the plain fields of the address book record -
// who they are, how to reach them, where they are. What it does NOT touch, and
// why:
//
//   contact groups and opt-in rows  Consent. The panel's own screen ticks a
//     group and writes an opt_in row because an operator is looking at a person
//     and deciding; an endpoint importing a marketplace's buyer list has no such
//     decision to pass on.
//   member_id and expiration_date   Membership identity, issued by the
//     membership system and looked up by it. A machine assigning one would be
//     writing somebody a key.
//   affiliate fields                They carry a commission rate and a code
//     that must stay unique; an affiliate is approved by a person.
//   the ERP columns                 They belong to the module that owns them.
//
// Nothing here de-duplicates by e-mail either: the contacts table does not, the
// panel does not, and two people at one address is a real thing. An integration
// that wants an upsert looks first with GET /customers?email=.

if (!defined('PG_API_ENTRY')) {
	exit;
}

function api_customer_present($row) {

	return array(
		'id'         => (int)$row['id'],
		'member_id'  => $row['member_id'],
		'first_name' => $row['first_name'],
		'last_name'  => $row['last_name'],
		'email'      => $row['email_address'],
		'company'    => $row['company'],
		'phone'      => api_customer_phone($row),
		'address' => array(
			'line_1'  => $row['business_address_1'],
			'city'    => $row['business_city'],
			'state'   => $row['business_state'],
			'zip'     => $row['business_zip_code'],
			'country' => $row['business_country']
		)
	);

}

// One phone number, from the three columns a contact may carry. Mobile first:
// it is the one a courier and a marketplace both want.
function api_customer_phone($row) {

	foreach (array('mobile_phone', 'business_phone', 'home_phone') as $column) {

		if (!empty($row[$column])) {

			return $row[$column];

		}

	}

	return null;

}

function api_customer_select() {

	return "SELECT contacts.id, contacts.member_id, contacts.first_name, contacts.last_name,
			contacts.email_address, contacts.company,
			contacts.mobile_phone, contacts.business_phone, contacts.home_phone,
			contacts.business_address_1, contacts.business_city,
			contacts.business_state, contacts.business_zip_code, contacts.business_country
		FROM contacts";

}

function api_customers_list($params) {

	$where = array();

	if (isset($params['email']) && $params['email'] !== '') {

		$where[] = "contacts.email_address = '" . escape($params['email']) . "'";

	}

	if (isset($params['search']) && $params['search'] !== '') {

		$search = escape($params['search']);

		$where[] = "(contacts.first_name LIKE '%" . $search . "%'
			OR contacts.last_name LIKE '%" . $search . "%'
			OR contacts.email_address LIKE '%" . $search . "%'
			OR contacts.member_id LIKE '%" . $search . "%')";

	}

	// Contacts carry no change timestamp, so the cursor walks the id alone. That
	// is a stable order and it resumes correctly; what it cannot do is offer an
	// updated_since filter, which is why this endpoint does not pretend to.
	$cursor_clause = '';

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$cursor_clause = "contacts.id > '" . $cursor['i'] . "'";

	}

	$paged_where = $where;

	if ($cursor_clause !== '') {

		$paged_where[] = $cursor_clause;

	}

	$where_sql = empty($paged_where) ? '' : ' WHERE ' . implode(' AND ', $paged_where);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows(api_customer_select() . $where_sql . "
		ORDER BY contacts.id ASC
		LIMIT " . ($limit + 1));

	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$next_cursor = null;

	if ($has_more && !empty($rows)) {

		$last = $rows[count($rows) - 1];

		$next_cursor = api_cursor_encode(0, $last['id']);

	}

	$total = null;

	if (!empty($params['include_count'])) {

		$total = (int)api_value("SELECT COUNT(*) FROM contacts"
			. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)));

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_customer_present($row);

	}

	api_ok_list($out, $limit, $next_cursor, $total);

}

function api_customers_get($params) {

	$row = api_row(api_customer_select() . " WHERE contacts.id = '" . (int)$params['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Customer'));

	}

	api_ok(api_customer_present($row));

}

// What api_customer_present() returns, declared for the OpenAPI document.
function api_customer_schema() {

	return array(
		'id'         => 'integer',
		'member_id'  => 'string?',
		'first_name' => 'string',
		'last_name'  => 'string',
		'email'      => 'string',
		'company'    => 'string',
		'phone'      => 'string',
		'address'    => array(
			'line_1'  => 'string',
			'city'    => 'string',
			'state'   => 'string',
			'zip'     => 'string',
			'country' => 'string'
		)
	);

}


// The columns a write may set, by the name the endpoint uses for them. The read
// side presents one phone from three columns and the business address as "the"
// address, so the write side puts them back where they came from.
function api_customer_writable() {

	return array(
		'salutation'  => 'salutation',
		'first_name'  => 'first_name',
		'last_name'   => 'last_name',
		'company'     => 'company',
		'title'       => 'title',
		'email'       => 'email_address',
		'phone'       => 'mobile_phone',
		'address_1'   => 'business_address_1',
		'address_2'   => 'business_address_2',
		'city'        => 'business_city',
		'state'       => 'business_state',
		'zip'         => 'business_zip_code',
		'country'     => 'business_country',
		'description' => 'description'
	);

}

// The SET list both writes are built from, plus the e-mail check.
function api_customer_set_clauses($params) {

	$set = array();

	foreach (api_customer_writable() as $name => $column) {

		if (!isset($params[$name])) {

			continue;

		}

		$value = trim((string)$params[$name]);

		if (($name === 'email') && ($value !== '') && !validate_email_address($value)) {

			api_fail_validation(lang('That is not an e-mail address.'), 'email');

		}

		$set[] = $column . " = '" . escape($value) . "'";

	}

	// Consent, and only when the caller says so. It is stored as the person's
	// own flag rather than as a subscription to a group, because a group is a
	// decision an operator makes in front of the record.
	if (isset($params['opt_in'])) {

		$set[] = "opt_in = '" . ((int)$params['opt_in'] === 1 ? '1' : '0') . "'";

	}

	return $set;

}

function api_customers_create($params) {

	$app = api_current_app();

	$set = api_customer_set_clauses($params);

	// A record with no name, no company and no address to write to is not a
	// customer, it is an empty row somebody has to clean up later.
	$identifies = false;

	foreach (array('first_name', 'last_name', 'company', 'email') as $name) {

		if (isset($params[$name]) && (trim((string)$params[$name]) !== '')) {

			$identifies = true;

		}

	}

	if (!$identifies) {

		api_fail_validation(lang('A customer needs at least a name, a company or an e-mail address.'));

	}

	// Who made it and when, the same stamp the panel's own screen leaves.
	$set[] = "user = '" . (int)$app['owner']['id'] . "'";

	$set[] = "timestamp = '" . time() . "'";

	api_dry_run_stop('created', 'customer', array('fields' => count($set)));

	api_exec("INSERT INTO contacts SET " . implode(', ', $set));

	$id = (int)api_insert_id();

	log_activity(lang(array(
		'string' => 'Customer ({var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($id, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_customer_select() . " WHERE contacts.id = '" . $id . "' LIMIT 1");

	api_webhook_enqueue('customer.created', array(
		'id'    => $id,
		'email' => isset($params['email']) ? trim((string)$params['email']) : ''
	));

	api_ok(api_customer_present($row), 201);

}

function api_customers_update($params) {

	$id = (int)$params['id'];

	$existing = api_row("SELECT id FROM contacts WHERE id = '" . $id . "' LIMIT 1");

	if ($existing === null) {

		api_fail_not_found(lang('Customer'));

	}

	$app = api_current_app();

	$set = api_customer_set_clauses($params);

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	$set[] = "timestamp = '" . time() . "'";

	api_dry_run_stop('updated', 'customer', array('id' => $id, 'fields' => count($set)));

	api_exec("UPDATE contacts SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");

	log_activity(lang(array(
		'string' => 'Customer ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($id, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$row = api_row(api_customer_select() . " WHERE contacts.id = '" . $id . "' LIMIT 1");

	api_webhook_enqueue('customer.updated', array(
		'id'    => $id,
		'email' => (string)$row['email_address']
	));

	api_ok(api_customer_present($row));

}
