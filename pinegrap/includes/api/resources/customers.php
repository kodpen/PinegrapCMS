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
//     decision to pass on. The one group a create files the record in
//     (contact_group_id) is the exception, and it is recorded as not opted in
//     unless opt_in=true is sent.
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
//
// Which records an application reaches follows its owner, as on the contact
// screens: every contact from the manager role up, below that only the ones
// filed in a contact group given to the account (validate_contact_access()).
// A record outside answers as if it did not exist, and a record made by such an
// account is filed in one of its groups, or it could not open it again.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
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

// The condition that keeps a query to the contacts an owner may open; empty
// from the manager role up. Below that a contact is opened only through a
// group given to the account, so a contact in no group, or only in groups
// given to others, is not there for it.
function api_customer_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return "contacts.id IN (SELECT contacts_contact_groups_xref.contact_id FROM contacts_contact_groups_xref"
		. " INNER JOIN users_contact_groups_xref ON users_contact_groups_xref.contact_group_id = contacts_contact_groups_xref.contact_group_id"
		. " WHERE users_contact_groups_xref.user_id = '" . (int)$owner['id'] . "')";

}

// One contact the application's owner may open, with the columns asked for; one
// outside its reach is answered as if there were none.
function api_customer_reachable_or_fail($id, $columns) {

	$app = api_current_app();

	$where = array("contacts.id = '" . (int)$id . "'");

	$owner = api_customer_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	$row = api_row("SELECT " . $columns . " FROM contacts WHERE " . implode(' AND ', $where) . " LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Customer'));

	}

	return $row;

}

// The contact group a new customer is filed in: 0 for none, a group id, or the
// name of the reason it cannot be made. From the manager role up it is the one
// sent, if any (the caller checks that it exists). Below that the add-contact
// screen asks for one of the account's own groups, so the one sent has to be
// among them, and with none sent the account's only group is taken; with
// several the caller has to say which. Pure, so the rule can be tested
// without a database.
function api_customer_create_group($role, $own_group_ids, $sent) {

	$sent = (int)$sent;

	if ((int)$role < 3) {

		return max(0, $sent);

	}

	$own = array();

	foreach ((array)$own_group_ids as $group_id) {

		if ((int)$group_id > 0) {

			$own[(int)$group_id] = (int)$group_id;

		}

	}

	if ($sent > 0) {

		return isset($own[$sent]) ? $sent : 'not_found';

	}

	if (empty($own)) {

		return 'no_group';

	}

	if (count($own) > 1) {

		return 'choose';

	}

	return reset($own);

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

	$app = api_current_app();

	$where = array();

	$owner = api_customer_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	if (isset($params['email']) && $params['email'] !== '') {

		$where[] = "contacts.email_address = '" . escape($params['email']) . "'";

	}

	if (isset($params['search']) && $params['search'] !== '') {

		$search = escape(escape_like($params['search']));

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

	$app = api_current_app();

	$where = array("contacts.id = '" . (int)$params['id'] . "'");

	$owner = api_customer_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	$row = api_row(api_customer_select() . " WHERE " . implode(' AND ', $where) . " LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Customer'));

	}

	api_ok(api_customer_present($row));

}

// What api_customer_present() returns, declared for the OpenAPI document.
//
// Every column but the id may be null: a contact is created with what is known
// of the person - an e-mail address alone from the e-mail preferences page, a
// name alone from POST /customers - and the columns nobody wrote stay NULL.
// phone is null when none of the three is filled in.
function api_customer_schema() {

	return array(
		'id'         => 'integer',
		'member_id'  => 'string?',
		'first_name' => 'string?',
		'last_name'  => 'string?',
		'email'      => 'string?',
		'company'    => 'string?',
		'phone'      => 'string?',
		'address'    => array(
			'line_1'  => 'string?',
			'city'    => 'string?',
			'state'   => 'string?',
			'zip'     => 'string?',
			'country' => 'string?'
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

	$group = api_customer_create_group_or_fail($app['owner'], isset($params['contact_group_id']) ? $params['contact_group_id'] : 0);

	// Who made it and when, the same stamp the panel's own screen leaves.
	$set[] = "user = '" . (int)$app['owner']['id'] . "'";

	$set[] = "timestamp = '" . time() . "'";

	api_dry_run_stop('created', 'customer', array('fields' => count($set)));

	api_exec("INSERT INTO contacts SET " . implode(', ', $set));

	$id = (int)api_insert_id();

	if ($group !== null) {

		api_exec("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id) VALUES ('" . $id . "', '" . (int)$group['id'] . "')");

		// A contact with no opt-in row counts as opted in to a group's e-mail,
		// so the row is written, and it says yes only when opt_in=true was sent.
		if ((int)$group['email_subscription'] === 1) {

			api_exec("INSERT INTO opt_in (contact_id, contact_group_id, opt_in)
				VALUES ('" . $id . "', '" . (int)$group['id'] . "', '" . (!empty($params['opt_in']) ? '1' : '0') . "')");

		}

		pg_announce_contact_group_members((int)$group['id'], array($id), array(), $group['name']);

	}

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

// The group a create files the customer in, loaded, or null for none; a create
// that cannot be filed where its owner reaches it stops here, before a dry run
// reports success.
function api_customer_create_group_or_fail($owner, $sent) {

	$own = array();

	if ((int)$owner['role'] >= 3) {

		foreach (api_rows("SELECT users_contact_groups_xref.contact_group_id FROM users_contact_groups_xref
			INNER JOIN contact_groups ON contact_groups.id = users_contact_groups_xref.contact_group_id
			WHERE users_contact_groups_xref.user_id = '" . (int)$owner['id'] . "'") as $row) {

			$own[] = (int)$row['contact_group_id'];

		}

	}

	$choice = api_customer_create_group($owner['role'], $own, $sent);

	if ($choice === 'choose') {

		api_fail_validation(lang('Send contact_group_id: the owner of this application reaches customers only through the contact groups given to it, and it has more than one.'), 'contact_group_id');

	}

	if ($choice === 'no_group') {

		api_fail_validation(lang('The owner of this application has no contact group to file a customer in, so it could not open the customer again.'), 'contact_group_id');

	}

	if ($choice === 0) {

		return null;

	}

	$group = ($choice === 'not_found') ? null : api_row("SELECT id, name, email_subscription FROM contact_groups WHERE id = '" . (int)$choice . "' LIMIT 1");

	if ($group === null) {

		api_fail_validation(lang(array(
			'string' => 'There is no contact group with the id {var:1}.',
			'vars'   => array((int)$sent)
		)), 'contact_group_id');

	}

	return $group;

}

function api_customers_update($params) {

	$id = (int)$params['id'];

	api_customer_reachable_or_fail($id, 'contacts.id');

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

// Removing a customer record.
//
// The address-book record and what hangs off it - its contact groups and its
// opt-in rows - the same three deletes the contact screen performs. The case
// this exists for is an erasure request: a person asks a shop to forget them,
// and the system that received the request is not the panel.
//
// Two records are refused where the panel would go ahead. A contact that is a
// user account's own is the name and address of somebody who signs in, and the
// account would be left without them. A contact linked to an ERP account
// carries the ledger's name for that account. Both are decisions an operator
// makes while looking at the person, so the API says which one it is and stops.
//
// Orders keep their own copy of the billing and shipping details, so an order
// placed by this customer stays complete; its contact_id points at nothing
// afterwards, which is what the panel's delete leaves as well.
function api_customers_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$existing = api_customer_reachable_or_fail($id, 'contacts.id, contacts.email_address');

	if ((int)api_value("SELECT COUNT(*) FROM user WHERE user_contact = '" . $id . "'") > 0) {

		api_fail(409, 'customer_has_account', lang('This contact belongs to a user account and is not deleted through the API.'));

	}

	if ((api_row("SHOW TABLES LIKE 'erp_accounts'") !== null)
		&& ((int)api_value("SELECT COUNT(*) FROM erp_accounts WHERE contact_id = '" . $id . "'") > 0)) {

		api_fail(409, 'customer_has_erp_account', lang('This contact is linked to an ERP account and is not deleted through the API.'));

	}

	api_dry_run_stop('deleted', 'customer', array('id' => $id));

	api_exec("DELETE FROM contacts WHERE id = '" . $id . "' LIMIT 1");

	api_exec("DELETE FROM contacts_contact_groups_xref WHERE contact_id = '" . $id . "'");

	api_exec("DELETE FROM opt_in WHERE contact_id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Customer ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($id, $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_webhook_enqueue('customer.deleted', array(
		'id'    => $id,
		'email' => (string)$existing['email_address']
	));

	api_ok(array('id' => $id, 'deleted' => true));

}
