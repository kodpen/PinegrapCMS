<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Contact groups: the lists a campaign is sent to, and who is on them.
//
// Read, and members put in or taken out, as on the contact screen; a group
// itself is made, renamed and removed on its own screen. An account with the
// contacts right but the user role reaches only the groups given to it, as on
// those screens.
//
// Being on a list and agreeing to its e-mail are two records. A group with
// e-mail subscription switched on keeps a contact's choice in opt_in, one row
// per contact and group, and a contact with no row counts as opted in, as the
// campaign screen reads it. A member is never opted back in by being added
// again: only opt_in=true sent with it writes that choice.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The groups an owner may reach: all of them from the manager role up, below
// that the ones given to it.
function api_contact_group_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return "contact_groups.id IN (SELECT contact_group_id FROM users_contact_groups_xref WHERE user_id = '" . (int)$owner['id'] . "')";

}

function api_contact_group_select() {

	return "SELECT contact_groups.*, created_user.user_username AS created_username,
			last_modified_user.user_username AS last_modified_username
		FROM contact_groups
		LEFT JOIN user AS created_user ON created_user.user_id = contact_groups.created_user_id
		LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = contact_groups.last_modified_user_id";

}

// How many contacts each group holds, for many groups in one query. A link left
// behind by a contact that no longer exists is not a member.
function api_contact_group_member_counts($ids) {

	$counts = array();

	foreach ($ids as $id) {

		$counts[(int)$id] = 0;

	}

	if (empty($counts)) {

		return $counts;

	}

	foreach (api_rows("SELECT contacts_contact_groups_xref.contact_group_id, COUNT(DISTINCT contacts.id) AS members
		FROM contacts_contact_groups_xref
		INNER JOIN contacts ON contacts.id = contacts_contact_groups_xref.contact_id
		WHERE contacts_contact_groups_xref.contact_group_id IN (" . implode(',', array_map('intval', array_keys($counts))) . ")
		GROUP BY contacts_contact_groups_xref.contact_group_id") as $row) {

		$counts[(int)$row['contact_group_id']] = (int)$row['members'];

	}

	return $counts;

}

function api_contact_group_present($row, $members) {

	$subscription = ((int)$row['email_subscription'] === 1);

	return array(
		'id'                => (int)$row['id'],
		'name'              => (string)$row['name'],
		// Shown to the contact on the e-mail preferences page when the group
		// takes subscriptions.
		'description'       => (string)$row['description'],
		// Campaigns may be sent to it, and its members choose whether they get
		// them (opt_in on each member).
		'email_subscription' => $subscription,
		// open: offered to every contact on the e-mail preferences page;
		// closed: shown there only to its members.
		'subscription_type' => $subscription ? (string)$row['email_subscription_type'] : null,
		'members'           => (int)$members,
		'created_by'        => ((string)$row['created_username'] !== '') ? (string)$row['created_username'] : null,
		'created_at'        => api_time($row['created_timestamp']),
		'updated_by'        => ((string)$row['last_modified_username'] !== '') ? (string)$row['last_modified_username'] : null,
		'updated_at'        => api_time($row['last_modified_timestamp']),
		'updated_at_unix'   => (int)$row['last_modified_timestamp']
	);

}

function api_contact_group_schema() {

	return array(
		'id'                 => 'integer',
		'name'               => 'string',
		'description'        => 'string',
		'email_subscription' => 'boolean',
		'subscription_type'  => 'string?',
		'members'            => 'integer',
		'created_by'         => 'string?',
		'created_at'         => 'string?',
		'updated_by'         => 'string?',
		'updated_at'         => 'string?',
		'updated_at_unix'    => 'integer',
		// Only in the answer to a members change: how many contacts were put
		// in, taken out, and had their choice for this group's e-mail written.
		'added'              => 'integer',
		'removed'            => 'integer',
		'opt_in_updated'     => 'integer'
	);

}

function api_contact_group_load_or_fail($id) {

	$where = array("contact_groups.id = '" . (int)$id . "'");

	$app = api_current_app();

	$owner = api_contact_group_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	$row = api_row(api_contact_group_select() . " WHERE " . implode(' AND ', $where) . " LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Contact group'));

	}

	return $row;

}

// By id, so a page boundary never moves when a group is renamed.
function api_contact_groups_list($params) {

	$where = array();

	$app = api_current_app();

	$owner = api_contact_group_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	if (isset($params['email_subscription'])) {

		$where[] = "contact_groups.email_subscription = '" . ($params['email_subscription'] ? '1' : '0') . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$like = escape(escape_like($params['q']));

		$where[] = "(contact_groups.name LIKE '%" . $like . "%' OR contact_groups.description LIKE '%" . $like . "%')";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "contact_groups.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_contact_group_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY contact_groups.id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	$counts = api_contact_group_member_counts($ids);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_contact_group_present($row, $counts[(int)$row['id']]);

	}

	api_ok_list($out, $limit, $next);

}

function api_contact_groups_get($params) {

	$row = api_contact_group_load_or_fail($params['id']);

	$counts = api_contact_group_member_counts(array($row['id']));

	api_ok(api_contact_group_present($row, $counts[(int)$row['id']]));

}

// A member's choice for this group's e-mail: null where the group takes no
// subscriptions, and no row at all is opted in, as the campaign screen picks
// its recipients.
function api_contact_group_member_opt_in($value, $subscription) {

	if (!$subscription) {

		return null;

	}

	return ($value === null) || ((string)$value === '') || ((int)$value === 1);

}

function api_contact_group_member_present($row, $subscription) {

	return array(
		'contact_id'   => (int)$row['id'],
		'first_name'   => (string)$row['first_name'],
		'last_name'    => (string)$row['last_name'],
		'email'        => (string)$row['email_address'],
		'company'      => (string)$row['company'],
		// The contact's own switch for all e-mail campaigns; off, no campaign
		// reaches it whatever the group says.
		'email_opt_in' => ((int)$row['contact_opt_in'] === 1),
		'opt_in'       => api_contact_group_member_opt_in(array_key_exists('group_opt_in', $row) ? $row['group_opt_in'] : null, $subscription)
	);

}

function api_contact_group_member_schema() {

	return array(
		'contact_id'   => 'integer',
		'first_name'   => 'string',
		'last_name'    => 'string',
		'email'        => 'string',
		'company'      => 'string',
		'email_opt_in' => 'boolean',
		'opt_in'       => 'boolean?'
	);

}

// By contact id, the order a sync resumes in.
function api_contact_groups_members($params) {

	$group = api_contact_group_load_or_fail($params['id']);

	$id = (int)$group['id'];

	$where = array("contacts_contact_groups_xref.contact_group_id = '" . $id . "'");

	if (isset($params['email']) && $params['email'] !== '') {

		$where[] = "contacts.email_address = '" . escape($params['email']) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "contacts.id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT DISTINCT contacts.id, contacts.first_name, contacts.last_name, contacts.email_address,
			contacts.company, contacts.opt_in AS contact_opt_in, opt_in.opt_in AS group_opt_in
		FROM contacts_contact_groups_xref
		INNER JOIN contacts ON contacts.id = contacts_contact_groups_xref.contact_id
		LEFT JOIN opt_in ON opt_in.contact_id = contacts_contact_groups_xref.contact_id
			AND opt_in.contact_group_id = contacts_contact_groups_xref.contact_group_id
		WHERE " . implode(' AND ', $where) . "
		ORDER BY contacts.id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$subscription = ((int)$group['email_subscription'] === 1);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_contact_group_member_present($row, $subscription);

	}

	api_ok_list($out, $limit, $next);

}

// The positive, distinct ids out of a list as it arrived.
function api_contact_group_ids_from($raw) {

	$ids = array();

	foreach ((array)$raw as $value) {

		$value = (int)$value;

		if (($value > 0) && !in_array($value, $ids, true)) {

			$ids[] = $value;

		}

	}

	return $ids;

}

// What a members change actually does: the contacts not yet in the group are
// put in, the ones in it are taken out, and anything else is already as asked.
function api_contact_group_member_changes($add, $remove, $members) {

	return array(
		'add'    => array_values(array_diff($add, $members)),
		'remove' => array_values(array_intersect($remove, $members))
	);

}

// Contacts to put in have to exist. Any contact may be put in, as on the screen
// where a contact is added: an integration that has just made one through
// POST /customers files it here next.
function api_contact_group_contacts_or_fail($ids, $field) {

	if (empty($ids)) {

		return;

	}

	$found = array();

	foreach (api_rows("SELECT id FROM contacts WHERE id IN (" . implode(',', $ids) . ")") as $row) {

		$found[] = (int)$row['id'];

	}

	foreach ($ids as $contact_id) {

		if (!in_array($contact_id, $found, true)) {

			api_fail_validation(lang(array(
				'string' => 'There is no contact with the id {var:1}.',
				'vars'   => array($contact_id)
			)), $field);

		}

	}

}

// Members in and out, as the contact screen ticks a group. Taking a contact
// out keeps its opt-in row, as that screen does, so a contact who opted out
// and is put back stays opted out unless opt_in=true is sent.
function api_contact_groups_members_update($params) {

	$app = api_current_app();

	$group = api_contact_group_load_or_fail($params['id']);

	$id = (int)$group['id'];

	$add = api_contact_group_ids_from(array_key_exists('add', $params) ? $params['add'] : array());

	$remove = api_contact_group_ids_from(array_key_exists('remove', $params) ? $params['remove'] : array());

	if (empty($add) && empty($remove)) {

		api_fail_validation(lang('Send the contact ids to add, to remove, or both.'));

	}

	if (array_intersect($add, $remove)) {

		api_fail_validation(lang('A contact cannot be added to and removed from a group in the same call.'), 'remove');

	}

	api_contact_group_contacts_or_fail($add, 'add');

	$members = array();

	foreach (api_rows("SELECT DISTINCT contact_id FROM contacts_contact_groups_xref
		WHERE contact_group_id = '" . $id . "'
			AND contact_id IN (" . implode(',', array_merge($add, $remove)) . ")") as $row) {

		$members[] = (int)$row['contact_id'];

	}

	$changes = api_contact_group_member_changes($add, $remove, $members);

	// The choice is the group's own e-mail; a group without subscription has
	// no such choice to record.
	$opt_in = null;

	if (isset($params['opt_in']) && ((int)$group['email_subscription'] === 1)) {

		$opt_in = $params['opt_in'] ? 1 : 0;

	}

	$opt_in_updated = ($opt_in === null) ? 0 : count($add);

	api_dry_run_stop('updated', 'contact_group', array(
		'id'             => $id,
		'added'          => count($changes['add']),
		'removed'        => count($changes['remove']),
		'opt_in_updated' => $opt_in_updated
	));

	if (!empty($changes['add'])) {

		$values = array();

		foreach ($changes['add'] as $contact_id) {

			$values[] = "('" . $contact_id . "', '" . $id . "')";

		}

		api_exec("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id) VALUES " . implode(', ', $values));

	}

	if (!empty($changes['remove'])) {

		api_exec("DELETE FROM contacts_contact_groups_xref
			WHERE contact_group_id = '" . $id . "' AND contact_id IN (" . implode(',', $changes['remove']) . ")");

	}

	if ($opt_in !== null) {

		api_exec("DELETE FROM opt_in
			WHERE contact_group_id = '" . $id . "' AND contact_id IN (" . implode(',', $add) . ")");

		$values = array();

		foreach ($add as $contact_id) {

			$values[] = "('" . $contact_id . "', '" . $id . "', '" . $opt_in . "')";

		}

		api_exec("INSERT INTO opt_in (contact_id, contact_group_id, opt_in) VALUES " . implode(', ', $values));

	}

	if (!empty($changes['add']) || !empty($changes['remove']) || ($opt_in !== null)) {

		log_activity(lang(array(
			'string' => 'Members of contact group ({var:1}) were changed through the API by the application {var:2} (key {var:3}).',
			'vars'   => array($group['name'], $app['name'], $app['api_key'])
		)), $app['owner']['username']);

	}

	$counts = api_contact_group_member_counts(array($id));

	$out = api_contact_group_present($group, $counts[$id]);

	$out['added'] = count($changes['add']);

	$out['removed'] = count($changes['remove']);

	$out['opt_in_updated'] = $opt_in_updated;

	api_ok($out);

}
