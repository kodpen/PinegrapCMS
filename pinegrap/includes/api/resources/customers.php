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
// Read only in this release. Writing a customer touches membership, contact
// groups and the affiliate record, and none of that should be worked out from
// inside an endpoint that is only meant to answer "who placed this order".

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
