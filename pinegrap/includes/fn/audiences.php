<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contact groups filled by rules (2026.11.0, 11.0.155): a group of kind
 * 'dynamic' keeps its rules in contact_groups.rules (JSON) and its members
 * are the contacts the rules select. This module holds the rule model: the
 * catalogue of conditions, the one shape a stored rule set is cleaned to,
 * and the pure builder that turns a rule set into one SELECT of contact ids.
 *
 * A rule set is {match: 'all'|'any', conditions: [...]}; a condition is
 * {key, op, value[, field][, days]} or, one level deep only, a nested
 * {match, conditions}. Every condition compiles to one predicate on
 * contacts.id (a column test or an IN / NOT IN sub-query on the table its
 * family reads); every value is a ? placeholder bound by db_q(), never text
 * written into the SQL. A rule set with no condition selects nobody.
 *
 * Visit conditions reach a contact only through the order a visit made
 * (visitors.order_id) and always within a window of days on the indexed
 * start_timestamp, so no per-contact scan of the raw visitor rows is made.
 *
 * Every reader asks pg_audience_ready() first: files land before the
 * upgrade adds the columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Bounds of a cleaned rule set.
define('PG_AUDIENCE_MAX_CONDITIONS', 20);
define('PG_AUDIENCE_MAX_TEXT', 100);
define('PG_AUDIENCE_MAX_DAYS', 3650);
define('PG_AUDIENCE_MAX_NUMBER', 2000000000);

/**
 * Whether the upgrade has added the rule columns.
 *
 * @return bool
 */
function pg_audience_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('contact_groups', 'kind')
        && pg_schema_has('contact_groups', 'rules')
        && pg_schema_has('contacts_contact_groups_xref', 'source');
}

/**
 * The contact columns the "Contact field" condition may read, named as the
 * contact form names them: contacts.title is the job title, member_id
 * carries the site's own member ID label.
 *
 * @return array column => label
 */
function pg_audience_contact_fields()
{
    $member_id_label = (defined('MEMBER_ID_LABEL') && is_string(MEMBER_ID_LABEL)) ? trim(MEMBER_ID_LABEL) : '';

    return array(
        'company' => lang('Company'),
        'title' => lang('Job Title'),
        'department' => lang('Department'),
        'business_state' => lang('Business State'),
        'home_state' => lang('Home State'),
        'business_zip_code' => lang('Business Zip Code'),
        'home_zip_code' => lang('Home Zip Code'),
        'salutation' => lang('Salutation'),
        'website' => lang('Website'),
        'member_id' => ($member_id_label !== '') ? $member_id_label : lang('Member ID'),
    );
}

/**
 * The consent answers a "Consent" condition may ask about, as
 * channel:purpose of consent_log (the cookie channel is a visitor's, not a
 * contact's, and stays out).
 *
 * @return array channel:purpose => label
 */
function pg_audience_consents()
{
    return array(
        'email:marketing' => lang('E-mail marketing'),
        'sms:marketing' => lang('SMS marketing'),
        'call:marketing' => lang('Call marketing'),
        'contract:kvkk' => lang('KVKK consent'),
        'contract:preinfo' => lang('Pre-information form'),
        'contract:contract' => lang('Distance sales contract'),
    );
}

/**
 * The operators, by name.
 *
 * @return array op => label
 */
function pg_audience_operators()
{
    return array(
        'eq' => lang('is'),
        'neq' => lang('is not'),
        'contains' => lang('contains'),
        'empty' => lang('is empty'),
        'not_empty' => lang('is not empty'),
        'gte' => lang('at least'),
        'lte' => lang('at most'),
        'within' => lang('in the last (days)'),
        'not_within' => lang('not in the last (days)'),
        'yes' => lang('yes'),
        'no' => lang('no'),
        'granted' => lang('given'),
        'not_granted' => lang('not given'),
        'member' => lang('is a member'),
        'active' => lang('is an active member'),
        'expired' => lang('membership expired'),
        'expiring' => lang('membership ends within (days)'),
        'not_member' => lang('is not a member'),
        'in' => lang('is in'),
        'not_in' => lang('is not in'),
    );
}

/**
 * The catalogue of conditions: per key its label, family, the operators it
 * takes, the kind of value ('text', 'number', 'money', 'days', 'none',
 * 'product', 'product_group', 'contact_group', 'form', 'form_field',
 * 'consent', 'status') and the table it reads. 'window' marks a condition
 * that also takes days (a visit window, required).
 *
 * @return array
 */
function pg_audience_conditions()
{
    return array(
        'country' => array('label' => lang('Country'), 'family' => 'contact', 'ops' => array('eq', 'neq'), 'value' => 'text', 'table' => 'contacts'),
        'city' => array('label' => lang('City'), 'family' => 'contact', 'ops' => array('eq', 'neq'), 'value' => 'text', 'table' => 'contacts'),
        'lead_source' => array('label' => lang('Lead Source'), 'family' => 'contact', 'ops' => array('eq', 'neq', 'contains', 'empty', 'not_empty'), 'value' => 'text', 'table' => 'contacts'),
        'field' => array('label' => lang('Contact field'), 'family' => 'contact', 'ops' => array('eq', 'neq', 'contains', 'empty', 'not_empty'), 'value' => 'text', 'table' => 'contacts'),

        'email_opt_in' => array('label' => lang('Accepts e-mail'), 'family' => 'consent', 'ops' => array('yes', 'no'), 'value' => 'none', 'table' => 'contacts'),
        'consent' => array('label' => lang('Consent'), 'family' => 'consent', 'ops' => array('granted', 'not_granted'), 'value' => 'consent', 'table' => 'consent_log'),

        'membership' => array('label' => lang('Membership'), 'family' => 'membership', 'ops' => array('member', 'active', 'expired', 'expiring', 'not_member'), 'value' => 'none', 'table' => 'contacts'),

        'customer' => array('label' => lang('Has bought'), 'family' => 'orders', 'ops' => array('yes', 'no'), 'value' => 'none', 'table' => 'orders'),
        'order_count' => array('label' => lang('Number of orders'), 'family' => 'orders', 'ops' => array('gte', 'lte', 'eq'), 'value' => 'number', 'table' => 'orders'),
        'order_total' => array('label' => lang('Total spent'), 'family' => 'orders', 'ops' => array('gte', 'lte'), 'value' => 'money', 'table' => 'orders'),
        'last_order' => array('label' => lang('Last order'), 'family' => 'orders', 'ops' => array('within', 'not_within'), 'value' => 'days', 'table' => 'orders'),
        'bought_product' => array('label' => lang('Bought a product'), 'family' => 'orders', 'ops' => array('yes', 'no'), 'value' => 'product', 'table' => 'order_items'),
        'bought_product_group' => array('label' => lang('Bought from a product group'), 'family' => 'orders', 'ops' => array('yes', 'no'), 'value' => 'product_group', 'table' => 'products_groups_xref'),
        'left_cart' => array('label' => lang('Left a cart'), 'family' => 'orders', 'ops' => array('within'), 'value' => 'days', 'table' => 'orders'),
        'returned' => array('label' => lang('Asked for a return'), 'family' => 'returns', 'ops' => array('within', 'not_within'), 'value' => 'days', 'table' => 'order_returns'),

        'subscription' => array('label' => lang('Subscription'), 'family' => 'subscriptions', 'ops' => array('eq', 'neq'), 'value' => 'status', 'table' => 'subscriptions'),

        'submitted_form' => array('label' => lang('Sent a form'), 'family' => 'forms', 'ops' => array('yes', 'no'), 'value' => 'form', 'table' => 'forms'),
        'form_field' => array('label' => lang('A form answer'), 'family' => 'forms', 'ops' => array('eq', 'contains'), 'value' => 'form_field', 'table' => 'form_data'),

        'visited' => array('label' => lang('Visited the site'), 'family' => 'visits', 'ops' => array('within', 'not_within'), 'value' => 'days', 'table' => 'visitors'),
        'landing_page' => array('label' => lang('Landing page'), 'family' => 'visits', 'ops' => array('eq'), 'value' => 'text', 'window' => true, 'table' => 'visitors'),
        'utm_source' => array('label' => lang('UTM source'), 'family' => 'visits', 'ops' => array('eq'), 'value' => 'text', 'window' => true, 'table' => 'visitors'),
        'utm_campaign' => array('label' => lang('UTM campaign'), 'family' => 'visits', 'ops' => array('eq'), 'value' => 'text', 'window' => true, 'table' => 'visitors'),

        'group' => array('label' => lang('Contact group'), 'family' => 'groups', 'ops' => array('in', 'not_in'), 'value' => 'contact_group', 'table' => 'contacts_contact_groups_xref'),
    );
}

/**
 * The subscription states a "Subscription" condition may name.
 *
 * @return string[]
 */
function pg_audience_subscription_statuses()
{
    return function_exists('pg_subscription_statuses')
        ? pg_subscription_statuses()
        : array('active', 'past_due', 'paused', 'cancelled', 'ended');
}

/**
 * A rule set brought to its one shape: {match, conditions}; unknown keys,
 * operators and fields dropped, values cut to their kind and bounds, a
 * nested set kept one level deep only and dropped when it is left empty, at
 * most PG_AUDIENCE_MAX_CONDITIONS per level. Takes the decoded JSON or the
 * JSON text.
 *
 * @param mixed $rules
 * @return array
 */
function pg_audience_rules_clean($rules)
{
    if (is_string($rules)) {
        $rules = json_decode($rules, true);
    }

    return pg_audience_rules_clean_level(is_array($rules) ? $rules : array(), 0);
}

/**
 * One level of pg_audience_rules_clean().
 *
 * @param array $rules
 * @param int   $depth 0 for the top, 1 for a nested set
 * @return array
 */
function pg_audience_rules_clean_level(array $rules, $depth)
{
    $clean = array(
        'match' => (isset($rules['match']) && ($rules['match'] === 'any')) ? 'any' : 'all',
        'conditions' => array(),
    );

    $conditions = (isset($rules['conditions']) && is_array($rules['conditions'])) ? $rules['conditions'] : array();

    foreach ($conditions as $condition) {
        if (count($clean['conditions']) >= PG_AUDIENCE_MAX_CONDITIONS) {
            break;
        }

        if (!is_array($condition)) {
            continue;
        }

        if (isset($condition['conditions'])) {
            if ($depth > 0) {
                continue;
            }

            $nested = pg_audience_rules_clean_level($condition, 1);

            if ($nested['conditions']) {
                $clean['conditions'][] = $nested;
            }

            continue;
        }

        $condition = pg_audience_condition_clean($condition);

        if ($condition !== null) {
            $clean['conditions'][] = $condition;
        }
    }

    return $clean;
}

/**
 * One condition in its shape, or null when it cannot be one.
 *
 * @param array $condition
 * @return array|null
 */
function pg_audience_condition_clean(array $condition)
{
    $catalogue = pg_audience_conditions();
    $key = isset($condition['key']) && is_string($condition['key']) ? $condition['key'] : '';

    if (!isset($catalogue[$key])) {
        return null;
    }

    $entry = $catalogue[$key];
    $op = isset($condition['op']) && is_string($condition['op']) ? $condition['op'] : '';

    if (!in_array($op, $entry['ops'], true)) {
        return null;
    }

    $clean = array('key' => $key, 'op' => $op);
    $value = isset($condition['value']) ? $condition['value'] : null;

    if ($key === 'field') {
        $field = isset($condition['field']) && is_string($condition['field']) ? $condition['field'] : '';

        if (!array_key_exists($field, pg_audience_contact_fields())) {
            return null;
        }

        $clean['field'] = $field;
    }

    if ($key === 'membership') {
        if ($op !== 'expiring') {
            return $clean;
        }

        $days = pg_audience_int($value, 1, PG_AUDIENCE_MAX_DAYS);

        if ($days === null) {
            return null;
        }

        $clean['value'] = $days;

        return $clean;
    }

    switch ($entry['value']) {
        case 'none':
            return $clean;

        case 'text':
            if (($op === 'empty') || ($op === 'not_empty')) {
                return $clean;
            }

            $text = pg_audience_text($value);

            if ($text === '') {
                return null;
            }

            $clean['value'] = $text;
            break;

        case 'number':
            $number = pg_audience_int($value, 0, PG_AUDIENCE_MAX_NUMBER);

            if ($number === null) {
                return null;
            }

            $clean['value'] = $number;
            break;

        case 'money':
            // Integer minor units, as orders.total.
            $number = pg_audience_int($value, 0, PG_AUDIENCE_MAX_NUMBER);

            if ($number === null) {
                return null;
            }

            $clean['value'] = $number;
            break;

        case 'days':
            $days = pg_audience_int($value, 1, PG_AUDIENCE_MAX_DAYS);

            if ($days === null) {
                return null;
            }

            $clean['value'] = $days;
            break;

        case 'product':
        case 'product_group':
        case 'contact_group':
        case 'form':
            $id = pg_audience_int($value, 1, 4294967295);

            if ($id === null) {
                return null;
            }

            $clean['value'] = $id;
            break;

        case 'form_field':
            $field_id = pg_audience_int(isset($condition['field']) ? $condition['field'] : null, 1, 4294967295);
            $text = pg_audience_text($value);

            if (($field_id === null) || ($text === '')) {
                return null;
            }

            $clean['field'] = $field_id;
            $clean['value'] = $text;
            break;

        case 'consent':
            if (!is_string($value) || !array_key_exists($value, pg_audience_consents())) {
                return null;
            }

            $clean['value'] = $value;
            break;

        case 'status':
            if (!is_string($value) || !in_array($value, pg_audience_subscription_statuses(), true)) {
                return null;
            }

            $clean['value'] = $value;
            break;

        default:
            return null;
    }

    if (!empty($entry['window'])) {
        $days = pg_audience_int(isset($condition['days']) ? $condition['days'] : null, 1, PG_AUDIENCE_MAX_DAYS);

        if ($days === null) {
            return null;
        }

        $clean['days'] = $days;
    }

    return $clean;
}

/**
 * A whole number within bounds, or null (a numeric string counts; a
 * fraction, a negative below the floor or anything else does not).
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int|null
 */
function pg_audience_int($value, $min, $max)
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && (preg_match('/^\s*[0-9]{1,10}\s*$/', $value) === 1)) {
        $number = (int) trim($value);
    } elseif (is_float($value) && is_finite($value) && (floor($value) === $value) && (abs($value) < 1e10)) {
        $number = (int) $value;
    } else {
        return null;
    }

    if (($number < $min) || ($number > $max)) {
        return null;
    }

    return $number;
}

/**
 * A text value trimmed and cut to PG_AUDIENCE_MAX_TEXT characters, '' when
 * it is not text.
 *
 * @param mixed $value
 * @return string
 */
function pg_audience_text($value)
{
    if (!is_string($value) && !is_int($value)) {
        return '';
    }

    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, PG_AUDIENCE_MAX_TEXT, 'UTF-8');
    }

    return substr($text, 0, PG_AUDIENCE_MAX_TEXT);
}

/**
 * The SELECT of the ids of the contacts a rule set selects, with its values
 * as ? placeholders: array('sql' => ..., 'params' => array(...)), for
 * db_q($sql, $params). Pure: no query is run. A rule set with no condition
 * selects nobody.
 *
 * @param mixed    $rules a rule set (cleaned here)
 * @param int|null $now   the time the day windows count back from
 * @return array
 */
function pg_audience_sql($rules, $now = null)
{
    $rules = pg_audience_rules_clean($rules);
    $now = ($now === null) ? time() : (int) $now;
    $params = array();

    $where = pg_audience_sql_level($rules, $now, $params);

    if ($where === '') {
        return array('sql' => 'SELECT contacts.id FROM contacts WHERE 1 = 0', 'params' => array());
    }

    return array('sql' => 'SELECT contacts.id FROM contacts WHERE ' . $where, 'params' => $params);
}

/**
 * The predicate of one level, '' when it has no condition.
 *
 * @param array $rules  a cleaned level
 * @param int   $now
 * @param array $params the placeholders' values, added to in order
 * @return string
 */
function pg_audience_sql_level(array $rules, $now, array &$params)
{
    $parts = array();

    foreach ($rules['conditions'] as $condition) {
        if (isset($condition['conditions'])) {
            $part = pg_audience_sql_level($condition, $now, $params);
        } else {
            $part = pg_audience_sql_condition($condition, $now, $params);
        }

        if ($part !== '') {
            $parts[] = $part;
        }
    }

    if (!$parts) {
        return '';
    }

    return '(' . implode(($rules['match'] === 'any') ? ' OR ' : ' AND ', $parts) . ')';
}

/**
 * The predicate of one cleaned condition on contacts.id.
 *
 * @param array $c
 * @param int   $now
 * @param array $params
 * @return string
 */
function pg_audience_sql_condition(array $c, $now, array &$params)
{
    $op = $c['op'];
    $value = isset($c['value']) ? $c['value'] : null;
    $since = isset($c['value']) && is_int($c['value']) ? $now - ($c['value'] * 86400) : 0;
    $complete = "orders.status <> 'incomplete' AND orders.contact_id <> 0";

    switch ($c['key']) {
        case 'country':
        case 'city':
            $column = $c['key'];
            $params[] = $value;
            $params[] = $value;
            $match = "(COALESCE(contacts.business_" . $column . ", '') = ? OR COALESCE(contacts.home_" . $column . ", '') = ?)";

            return ($op === 'eq') ? $match : '(NOT ' . $match . ')';

        case 'lead_source':
        case 'field':
            $column = 'contacts.' . (($c['key'] === 'field') ? $c['field'] : 'lead_source');

            return pg_audience_sql_text("COALESCE(" . $column . ", '')", $op, $value, $params);

        case 'email_opt_in':
            $match = "(contacts.opt_in = 1 AND COALESCE(contacts.email_address, '') <> '')";

            return ($op === 'yes') ? $match : '(NOT ' . $match . ')';

        case 'consent':
            list($channel, $purpose) = explode(':', $value, 2);
            $params[] = $channel;
            $params[] = $purpose;
            // The latest answered row of the channel and purpose decides;
            // a pending row is no answer.
            $sub = "SELECT consent_log.contact_id FROM consent_log"
                . " WHERE consent_log.contact_id <> 0 AND consent_log.channel = ? AND consent_log.purpose = ?"
                . " AND consent_log.status = 'granted'"
                . " AND consent_log.id = (SELECT MAX(later.id) FROM consent_log AS later"
                . " WHERE later.contact_id = consent_log.contact_id AND later.channel = consent_log.channel"
                . " AND later.purpose = consent_log.purpose AND later.status <> 'pending')";

            return pg_audience_sql_in($sub, $op === 'granted');

        case 'membership':
            $member = "COALESCE(contacts.member_id, '') <> ''";
            // expiration_date is a day in the site's zone, which init.php
            // gives PHP: UTC's day is another one for hours each day.
            $today = date('Y-m-d', $now);

            if ($op === 'member') {
                return '(' . $member . ')';
            }

            if ($op === 'not_member') {
                return '(NOT (' . $member . '))';
            }

            if ($op === 'active') {
                $params[] = $today;

                return "(" . $member . " AND (contacts.expiration_date >= ? OR contacts.expiration_date = '0000-00-00'))";
            }

            if ($op === 'expired') {
                $params[] = $today;

                return "(" . $member . " AND contacts.expiration_date < ? AND contacts.expiration_date <> '0000-00-00')";
            }

            $params[] = $today;
            // Calendar days: n times 86400 seconds from midnight ends a day
            // short after the 25-hour day of a clock change.
            $params[] = date('Y-m-d', strtotime($today . ' +' . (int) $value . ' days'));

            return "(" . $member . " AND contacts.expiration_date >= ? AND contacts.expiration_date <= ?)";

        case 'customer':
            return pg_audience_sql_in("SELECT orders.contact_id FROM orders WHERE " . $complete, $op === 'yes');

        case 'order_count':
        case 'order_total':
            $measure = ($c['key'] === 'order_count') ? 'COUNT(*)' : 'SUM(orders.total)';
            $grouped = "SELECT orders.contact_id FROM orders WHERE " . $complete . " GROUP BY orders.contact_id HAVING " . $measure;

            if ($op === 'gte') {
                if ($value === 0) {
                    return '(1 = 1)';
                }

                $params[] = $value;

                return pg_audience_sql_in($grouped . ' >= ?', true);
            }

            if (($op === 'eq') && ($value > 0)) {
                $params[] = $value;

                return pg_audience_sql_in($grouped . ' = ?', true);
            }

            // At most n (or exactly none): every contact but those above n,
            // so a contact with no order counts as 0.
            $params[] = $value;

            return pg_audience_sql_in($grouped . ' > ?', false);

        case 'last_order':
            $params[] = $since;

            return pg_audience_sql_in("SELECT orders.contact_id FROM orders WHERE " . $complete . " AND orders.order_date >= ?", $op === 'within');

        case 'bought_product':
            $params[] = $value;

            return pg_audience_sql_in("SELECT orders.contact_id FROM orders"
                . " INNER JOIN order_items ON order_items.order_id = orders.id"
                . " WHERE " . $complete . " AND order_items.product_id = ?", $op === 'yes');

        case 'bought_product_group':
            $params[] = $value;

            return pg_audience_sql_in("SELECT orders.contact_id FROM orders"
                . " INNER JOIN order_items ON order_items.order_id = orders.id"
                . " INNER JOIN products_groups_xref ON products_groups_xref.product = order_items.product_id"
                . " WHERE " . $complete . " AND products_groups_xref.product_group = ?", $op === 'yes');

        case 'left_cart':
            $params[] = $since;

            return pg_audience_sql_in("SELECT orders.contact_id FROM orders"
                . " INNER JOIN order_items ON order_items.order_id = orders.id"
                . " WHERE orders.status = 'incomplete' AND orders.contact_id <> 0 AND orders.last_modified_timestamp >= ?", true);

        case 'returned':
            $params[] = $since;

            return pg_audience_sql_in("SELECT orders.contact_id FROM order_returns"
                . " INNER JOIN orders ON orders.id = order_returns.order_id"
                . " WHERE orders.contact_id <> 0 AND order_returns.requested_at >= ?", $op === 'within');

        case 'subscription':
            $params[] = $value;

            return pg_audience_sql_in("SELECT user.user_contact FROM subscriptions"
                . " INNER JOIN user ON user.user_id = subscriptions.user_id"
                . " WHERE user.user_contact IS NOT NULL AND subscriptions.status = ?", $op === 'eq');

        case 'submitted_form':
            $params[] = $value;

            return pg_audience_sql_in("SELECT forms.contact_id FROM forms"
                . " WHERE forms.contact_id <> 0 AND forms.complete = 1 AND forms.page_id = ?", $op === 'yes');

        case 'form_field':
            $params[] = $c['field'];
            $sub = "SELECT forms.contact_id FROM form_data"
                . " INNER JOIN forms ON forms.id = form_data.form_id"
                . " WHERE forms.contact_id <> 0 AND forms.complete = 1 AND form_data.form_field_id = ? AND ";

            if ($op === 'eq') {
                $params[] = $value;

                return pg_audience_sql_in($sub . 'form_data.data = ?', true);
            }

            $params[] = pg_list_like_pattern($value);

            return pg_audience_sql_in($sub . 'form_data.data LIKE ?', true);

        case 'visited':
            $params[] = $since;

            return pg_audience_sql_in(pg_audience_sql_visits(''), $op === 'within');

        case 'landing_page':
        case 'utm_source':
        case 'utm_campaign':
            $column = ($c['key'] === 'landing_page') ? 'landing_page_name' : $c['key'];
            $params[] = $now - ($c['days'] * 86400);
            $params[] = $value;

            return pg_audience_sql_in(pg_audience_sql_visits(' AND visitors.' . $column . ' = ?'), true);

        case 'group':
            $params[] = $value;

            return pg_audience_sql_in("SELECT contacts_contact_groups_xref.contact_id FROM contacts_contact_groups_xref"
                . " WHERE contacts_contact_groups_xref.contact_group_id = ?", $op === 'in');
    }

    return '';
}

/**
 * The contacts whose orders came from a visit since a time (the first
 * placeholder), with an extra test on the visit.
 *
 * @param string $extra ' AND ...' or ''
 * @return string
 */
function pg_audience_sql_visits($extra)
{
    return "SELECT orders.contact_id FROM visitors"
        . " INNER JOIN orders ON orders.id = visitors.order_id"
        . " WHERE visitors.start_timestamp >= ? AND visitors.order_id <> 0 AND orders.contact_id <> 0" . $extra;
}

/**
 * contacts.id IN / NOT IN a sub-query.
 *
 * @param string $sub
 * @param bool   $in
 * @return string
 */
function pg_audience_sql_in($sub, $in)
{
    return '(contacts.id ' . ($in ? 'IN' : 'NOT IN') . ' (' . $sub . '))';
}

/**
 * A text test on a column expression.
 *
 * @param string $column
 * @param string $op
 * @param mixed  $value
 * @param array  $params
 * @return string
 */
function pg_audience_sql_text($column, $op, $value, array &$params)
{
    switch ($op) {
        case 'empty':
            return '(' . $column . " = '')";

        case 'not_empty':
            return '(' . $column . " <> '')";

        case 'contains':
            $params[] = pg_list_like_pattern($value);

            return '(' . $column . ' LIKE ?)';

        case 'neq':
            $params[] = $value;

            return '(' . $column . ' <> ?)';
    }

    $params[] = $value;

    return '(' . $column . ' = ?)';
}

/**
 * How many contacts a rule set selects now, null when it cannot be counted
 * (before the upgrade, or a failed query).
 *
 * @param mixed $rules
 * @return int|null
 */
function pg_audience_count($rules)
{
    if (!pg_audience_ready()) {
        return null;
    }

    $query = pg_audience_sql($rules);
    $result = db_q('SELECT COUNT(*) FROM (' . $query['sql'] . ') AS audience', $query['params']);

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_row($result);

    return $row ? (int) $row[0] : 0;
}

// The recompute (AU2): at most once an hour a group, at most this many
// seconds of a general job tick, pages of this many contact ids.
define('PG_AUDIENCE_INTERVAL', 3600);
define('PG_AUDIENCE_TICK_SECONDS', 20);
define('PG_AUDIENCE_PAGE', 10000);
define('PG_AUDIENCE_WRITE_CHUNK', 500);

/**
 * What a page of a recompute changes: the contacts the rule selects that
 * are not members yet are added; the rule-made rows whose contact the rule
 * no longer selects are removed. A contact that is a member by another
 * source (a hand, form or product row) is never added a second time, and
 * its row is never touched. Pure; each list sorted, without repeats.
 *
 * @param int[] $selected the contacts the rule selects
 * @param int[] $by_rule  the contacts with a rule-made row
 * @param int[] $by_other the contacts with a row of another source
 * @return array array('add' => int[], 'remove' => int[])
 */
function pg_audience_diff(array $selected, array $by_rule, array $by_other)
{
    $selected = pg_audience_id_set($selected);
    $by_rule = pg_audience_id_set($by_rule);
    $by_other = pg_audience_id_set($by_other);

    $add = array_keys(array_diff_key($selected, $by_rule, $by_other));
    $remove = array_keys(array_diff_key($by_rule, $selected));

    sort($add);
    sort($remove);

    return array('add' => $add, 'remove' => $remove);
}

/**
 * Positive ids as the keys of a set.
 *
 * @param array $ids
 * @return array id => true
 */
function pg_audience_id_set(array $ids)
{
    $set = array();

    foreach ($ids as $id) {
        $id = (int) $id;

        if ($id > 0) {
            $set[$id] = true;
        }
    }

    return $set;
}

/**
 * The SELECT of a rule set narrowed to one page of contact ids: after
 * $after, up to and with $upto. Pure.
 *
 * @param mixed    $rules
 * @param int      $after
 * @param int      $upto
 * @param int|null $now
 * @return array array('sql', 'params')
 */
function pg_audience_sql_page($rules, $after, $upto, $now = null)
{
    $query = pg_audience_sql($rules, $now);
    $query['sql'] .= ' AND contacts.id > ? AND contacts.id <= ?';
    $query['params'][] = (int) $after;
    $query['params'][] = (int) $upto;

    return $query;
}

/**
 * The pass's state between ticks: the ids added and removed so far, kept
 * until the whole pass is announced once.
 *
 * @param int $group_id
 * @return string
 */
function pg_audience_state_path($group_id)
{
    return PG_FUNCTIONS_DIR . '/data/temp/audience_' . (int) $group_id . '.json';
}

/**
 * @param int $group_id
 * @return array array('added' => int[], 'removed' => int[])
 */
function pg_audience_state_read($group_id)
{
    $state = array('added' => array(), 'removed' => array());
    $path = pg_audience_state_path($group_id);
    $data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

    foreach (array('added', 'removed') as $key) {
        if (is_array($data) && isset($data[$key]) && is_array($data[$key])) {
            $state[$key] = array_keys(pg_audience_id_set($data[$key]));
        }
    }

    return $state;
}

/**
 * @param int        $group_id
 * @param array|null $state null removes the file
 * @return void
 */
function pg_audience_state_write($group_id, $state)
{
    $path = pg_audience_state_path($group_id);

    if ($state === null) {
        if (is_file($path)) {
            @unlink($path);
        }

        return;
    }

    @file_put_contents($path, json_encode($state), LOCK_EX);
}

/**
 * The ids of one SELECT of a single id column, or null on a failed query.
 *
 * @param string $sql
 * @param array  $params
 * @return int[]|null
 */
function pg_audience_ids($sql, array $params)
{
    $result = db_q($sql, $params);

    if (!$result) {
        return null;
    }

    $ids = array();

    while ($row = mysqli_fetch_row($result)) {
        $ids[] = (int) $row[0];
    }

    return $ids;
}

/**
 * Where the page after $after ends: the PG_AUDIENCE_PAGE-th contact id
 * after it, or the top of the id range when fewer are left (so rule-made
 * rows of contacts that are gone are removed on the last page). Null on a
 * failed query.
 *
 * @param int $after
 * @return array|null array('upto' => int, 'last' => bool)
 */
function pg_audience_page_end($after)
{
    $result = db_q('SELECT id FROM contacts WHERE id > ? ORDER BY id LIMIT 1 OFFSET ' . (PG_AUDIENCE_PAGE - 1), array((int) $after));

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_row($result);

    if ($row) {
        return array('upto' => (int) $row[0], 'last' => false);
    }

    return array('upto' => 4294967295, 'last' => true);
}

/**
 * The statements that write one page's change into the xref, in chunks of
 * PG_AUDIENCE_WRITE_CHUNK: inserts as 'rule' rows, deletes of 'rule' rows
 * only. Pure.
 *
 * @param int   $group_id
 * @param array $diff pg_audience_diff()
 * @return array list of array('sql', 'params')
 */
function pg_audience_write_queries($group_id, array $diff)
{
    $queries = array();

    foreach (array_chunk($diff['add'], PG_AUDIENCE_WRITE_CHUNK) as $chunk) {
        $params = array();

        foreach ($chunk as $id) {
            $params[] = (int) $id;
            $params[] = (int) $group_id;
        }

        $queries[] = array(
            'sql' => 'INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id, source) VALUES '
                . implode(', ', array_fill(0, count($chunk), "(?, ?, 'rule')")),
            'params' => $params,
        );
    }

    foreach (array_chunk($diff['remove'], PG_AUDIENCE_WRITE_CHUNK) as $chunk) {
        $queries[] = array(
            'sql' => "DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source = 'rule' AND contact_id IN ("
                . implode(', ', array_fill(0, count($chunk), '?')) . ')',
            'params' => array_merge(array((int) $group_id), array_map('intval', $chunk)),
        );
    }

    return $queries;
}

/**
 * The review shown when a group is switched to dynamic: the recompute never
 * touches its hand, form and product rows, so the user chooses at the
 * switch whether to keep them (the default), remove them, or turn them into
 * rule rows that the next recompute drops when the rule does not select
 * the contact. Pure: the count query, the counts by source from its rows,
 * and the statements of the two actions.
 *
 * @param int   $group_id
 * @param array $rows the count query's rows (array('source', 'n'))
 * @return array array('count' => query, 'counts' => array('hand', 'form',
 *               'product' => int), 'total' => int, 'remove' => query[],
 *               'to_rule' => query[]); each query array('sql', 'params')
 */
function pg_audience_switch_review($group_id, array $rows = array())
{
    $params = array((int) $group_id);
    $counts = array('hand' => 0, 'form' => 0, 'product' => 0);

    foreach ($rows as $row) {
        $source = isset($row['source']) ? (string) $row['source'] : '';

        if (isset($counts[$source])) {
            $counts[$source] += max(0, (int) (isset($row['n']) ? $row['n'] : 0));
        }
    }

    return array(
        'count' => array(
            'sql' => "SELECT source, COUNT(*) AS n FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule' GROUP BY source",
            'params' => $params,
        ),
        'counts' => $counts,
        'total' => array_sum($counts),
        'remove' => array(
            array('sql' => "DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule'", 'params' => $params),
        ),
        // The xref has no unique key: a contact that already has a rule row
        // loses its other row first, so the update leaves one row each.
        'to_rule' => array(
            array(
                'sql' => 'DELETE other FROM contacts_contact_groups_xref AS other'
                    . ' INNER JOIN contacts_contact_groups_xref AS by_rule'
                    . " ON by_rule.contact_group_id = other.contact_group_id AND by_rule.contact_id = other.contact_id AND by_rule.source = 'rule'"
                    . " WHERE other.contact_group_id = ? AND other.source <> 'rule'",
                'params' => $params,
            ),
            array('sql' => "UPDATE contacts_contact_groups_xref SET source = 'rule' WHERE contact_group_id = ? AND source <> 'rule'", 'params' => $params),
        ),
    );
}

/**
 * The choice posted with a switch to rules for the group's hand, form and
 * product rows: 'remove', 'to_rule' (keys of pg_audience_switch_review()),
 * else 'keep', which touches nothing. Pure.
 *
 * @param array $post
 * @return string
 */
function pg_audience_switch_action(array $post)
{
    $value = isset($post['switch_rows']) ? $post['switch_rows'] : '';

    return (is_string($value) && in_array($value, array('remove', 'to_rule'), true)) ? $value : 'keep';
}

/**
 * The count line of the switch review, sources with no rows left out:
 * "1,240 by hand, 30 from forms, 12 from products"; '' with no rows.
 *
 * @param array $counts pg_audience_switch_review() counts
 * @return string
 */
function pg_audience_switch_review_text(array $counts)
{
    $texts = array(
        'hand' => '{var:1} by hand',
        'form' => '{var:1} from forms',
        'product' => '{var:1} from products',
    );
    $parts = array();

    foreach ($texts as $source => $text) {
        $count = isset($counts[$source]) ? (int) $counts[$source] : 0;

        if ($count > 0) {
            $parts[] = lang(array('string' => $text, 'vars' => array(pg_format_number($count, 0))));
        }
    }

    return implode(', ', $parts);
}

/**
 * The switch review of a group with its counts read: the group's rows that
 * are not rule-made, by source.
 *
 * @param int $group_id
 * @return array|null pg_audience_switch_review(); null on a failed query
 */
function pg_audience_switch_counts($group_id)
{
    $review = pg_audience_switch_review($group_id);
    $result = db_q($review['count']['sql'], $review['count']['params']);

    if (!$result) {
        return null;
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return pg_audience_switch_review($group_id, $rows);
}

/**
 * Runs the chosen action of the switch review once the switch is saved.
 * 'remove' announces the contacts that leave the group (those with no rule
 * row beside the removed one); 'to_rule' changes no membership, so it
 * announces nothing; the next recompute takes the turned rows as its own.
 *
 * @param int    $group_id
 * @param string $action pg_audience_switch_action()
 * @return bool false on a failed query
 */
function pg_audience_switch_apply($group_id, $action)
{
    if (!in_array($action, array('remove', 'to_rule'), true)) {
        return true;
    }

    $group_id = (int) $group_id;
    $leavers = array();

    if ($action === 'remove') {
        $result = db_q(
            "SELECT DISTINCT other.contact_id FROM contacts_contact_groups_xref AS other"
            . " LEFT JOIN contacts_contact_groups_xref AS by_rule"
            . " ON by_rule.contact_group_id = other.contact_group_id AND by_rule.contact_id = other.contact_id AND by_rule.source = 'rule'"
            . " WHERE other.contact_group_id = ? AND other.source <> 'rule' AND by_rule.contact_id IS NULL",
            array($group_id)
        );

        if (!$result) {
            return false;
        }

        while ($row = mysqli_fetch_row($result)) {
            $leavers[] = (int) $row[0];
        }
    }

    $review = pg_audience_switch_review($group_id);

    foreach ($review[$action] as $query) {
        if (!db_q($query['sql'], $query['params'])) {
            return false;
        }
    }

    if ($leavers) {
        pg_announce_contact_group_members($group_id, array(), $leavers);
    }

    return true;
}

/**
 * Whether the group is still dynamic: read again before each page's write,
 * since a pass can run for its whole budget while the group is switched
 * back to static.
 *
 * @param int $group_id
 * @return bool|null null on a failed query
 */
function pg_audience_still_dynamic($group_id)
{
    $result = db_q("SELECT id FROM contact_groups WHERE id = ? AND kind = 'dynamic'", array((int) $group_id));

    if (!$result) {
        return null;
    }

    return (bool) mysqli_fetch_assoc($result);
}

/**
 * Writes one page's change into the xref.
 *
 * @param int   $group_id
 * @param array $diff pg_audience_diff()
 * @return bool false on a failed write
 */
function pg_audience_write($group_id, array $diff)
{
    foreach (pg_audience_write_queries($group_id, $diff) as $query) {
        if (!db_q($query['sql'], $query['params'])) {
            return false;
        }
    }

    return true;
}

/**
 * After a page: 'done' when it was the last, 'stop' when the budget is
 * spent (the cursor keeps the place), else 'next'. Pure.
 *
 * @param bool  $last
 * @param float $elapsed seconds
 * @param int   $budget  seconds
 * @return string
 */
function pg_audience_next_step($last, $elapsed, $budget)
{
    if ($last) {
        return 'done';
    }

    return ((float) $elapsed >= (float) $budget) ? 'stop' : 'next';
}

/**
 * Recomputes one dynamic group from its cursor, page by page, until the
 * pass ends or $budget seconds are spent. A pass that ends announces its
 * whole change once (contact_group.members_changed), stamps computed_at and
 * puts the cursor back to 0; one that stops keeps its cursor and the ids
 * changed so far for the next call.
 *
 * @param int $group_id
 * @param int $budget seconds
 * @return array|null array('done' => bool, 'added' => int, 'removed' => int,
 *               'cursor' => int); null when the group is not (or stops being) a dynamic one
 *               or a query failed
 */
function pg_audience_recompute($group_id, $budget)
{
    if (!pg_audience_ready()) {
        return null;
    }

    $group_id = (int) $group_id;
    $result = db_q("SELECT name, rules, compute_cursor FROM contact_groups WHERE id = ? AND kind = 'dynamic'", array($group_id));
    $group = $result ? mysqli_fetch_assoc($result) : null;

    if (!$group) {
        return null;
    }

    $rules = pg_audience_rules_clean((string) $group['rules']);
    $cursor = (int) $group['compute_cursor'];
    $state = ($cursor > 0) ? pg_audience_state_read($group_id) : array('added' => array(), 'removed' => array());
    $started = microtime(true);
    $now = time();
    $done = false;

    while (true) {
        $page = pg_audience_page_end($cursor);

        if ($page === null) {
            return null;
        }

        $range = array($group_id, $cursor, $page['upto']);
        $select = pg_audience_sql_page($rules, $cursor, $page['upto'], $now);
        $selected = pg_audience_ids($select['sql'], $select['params']);
        $by_rule = pg_audience_ids("SELECT contact_id FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source = 'rule' AND contact_id > ? AND contact_id <= ?", $range);
        $by_other = pg_audience_ids("SELECT contact_id FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND source <> 'rule' AND contact_id > ? AND contact_id <= ?", $range);

        if (($selected === null) || ($by_rule === null) || ($by_other === null)) {
            return null;
        }

        $diff = pg_audience_diff($selected, $by_rule, $by_other);
        $dynamic = pg_audience_still_dynamic($group_id);

        if ($dynamic === false) {
            // Switched back to static mid-pass: end without writing or
            // announcing, and leave no cursor or state for a later switch.
            db_q('UPDATE contact_groups SET compute_cursor = 0 WHERE id = ?', array($group_id));
            pg_audience_state_write($group_id, null);
            return null;
        }

        if (($dynamic === null) || !pg_audience_write($group_id, $diff)) {
            return null;
        }

        $state['added'] = array_merge($state['added'], $diff['add']);
        $state['removed'] = array_merge($state['removed'], $diff['remove']);

        $next = pg_audience_next_step($page['last'], microtime(true) - $started, $budget);

        if ($next === 'done') {
            $done = true;
            break;
        }

        $cursor = $page['upto'];

        if ($next === 'stop') {
            break;
        }
    }

    if ($done) {
        db_q('UPDATE contact_groups SET computed_at = ?, compute_cursor = 0 WHERE id = ?', array($now, $group_id));
        pg_audience_state_write($group_id, null);

        if ($state['added'] || $state['removed']) {
            pg_announce_contact_group_members($group_id, $state['added'], $state['removed'], (string) $group['name']);
        }
    } else {
        db_q('UPDATE contact_groups SET compute_cursor = ? WHERE id = ?', array($cursor, $group_id));
        pg_audience_state_write($group_id, $state);
    }

    return array('done' => $done, 'added' => count($state['added']), 'removed' => count($state['removed']), 'cursor' => $done ? 0 : $cursor);
}

/**
 * The dynamic groups due, the one waiting longest first: a pass under way
 * (a cursor) before the others, then by computed_at. Pure: the WHERE and
 * ORDER BY with their values.
 *
 * @param int $now
 * @return array array('sql', 'params')
 */
function pg_audience_due_sql($now)
{
    return array(
        'sql' => "SELECT id FROM contact_groups WHERE kind = 'dynamic' AND (compute_cursor > 0 OR computed_at <= ?)"
            . ' ORDER BY (compute_cursor > 0) DESC, computed_at, id',
        'params' => array((int) $now - PG_AUDIENCE_INTERVAL),
    );
}

/**
 * The general job's part: recomputes the due dynamic groups, the oldest
 * run first, within $budget seconds of the tick (and what is left of it).
 *
 * @param int $budget
 * @return array array('groups' => int, 'added' => int, 'removed' => int)
 */
function pg_audience_job_run($budget = PG_AUDIENCE_TICK_SECONDS)
{
    $totals = array('groups' => 0, 'added' => 0, 'removed' => 0);

    if (!pg_audience_ready()) {
        return $totals;
    }

    if (function_exists('pg_cron_time_left')) {
        $budget = min((int) $budget, pg_cron_time_left((int) $budget));
    }

    $started = microtime(true);
    $due = pg_audience_due_sql(time());
    $ids = pg_audience_ids($due['sql'], $due['params']);

    foreach ((array) $ids as $group_id) {
        $left = (int) floor($budget - (microtime(true) - $started));

        if ($left < 1) {
            break;
        }

        $run = pg_audience_recompute($group_id, $left);

        if ($run === null) {
            // A failed run waits its hour like a finished one, so one broken
            // group does not take the head of every tick.
            db_q('UPDATE contact_groups SET computed_at = ?, compute_cursor = 0 WHERE id = ?', array(time(), (int) $group_id));
            pg_audience_state_write($group_id, null);
            continue;
        }

        if ($run['done']) {
            $totals['groups']++;
            $totals['added'] += $run['added'];
            $totals['removed'] += $run['removed'];
        } else {
            break;
        }
    }

    return $totals;
}

// The rule editor (AU3): edit_contact_group.php draws the rules from this
// catalogue, posts them as JSON and asks for a count while they change.

/**
 * What the rule editor draws from: every condition with its family, value
 * kind, operators and labels, the families, contact fields, consents and
 * subscription states, and the condition cap of a level.
 *
 * @return array
 */
function pg_audience_editor_data()
{
    $operators = pg_audience_operators();
    $conditions = array();

    foreach (pg_audience_conditions() as $key => $entry) {
        $ops = array();

        foreach ($entry['ops'] as $op) {
            $ops[] = array('op' => $op, 'label' => $operators[$op]);
        }

        $conditions[] = array(
            'key' => $key,
            'label' => $entry['label'],
            'family' => $entry['family'],
            'value' => $entry['value'],
            'window' => !empty($entry['window']),
            'ops' => $ops,
        );
    }

    $options = function (array $list) {
        $out = array();

        foreach ($list as $id => $label) {
            $out[] = array('id' => (string) $id, 'text' => (string) $label);
        }

        return $out;
    };

    $statuses = array();

    foreach (pg_audience_subscription_statuses() as $status) {
        $statuses[] = array('id' => $status, 'text' => function_exists('pg_subscription_customer_status_label') ? pg_subscription_customer_status_label($status) : $status);
    }

    return array(
        'conditions' => $conditions,
        'families' => array(
            'contact' => lang('Contact'),
            'consent' => lang('Consent'),
            'membership' => lang('Membership'),
            'orders' => lang('Orders'),
            'returns' => lang('Returns'),
            'subscriptions' => lang('Subscriptions'),
            'forms' => lang('Forms'),
            'visits' => lang('Visits'),
            'groups' => lang('Contact Groups'),
        ),
        'fields' => $options(pg_audience_contact_fields()),
        'consents' => $options(pg_audience_consents()),
        'statuses' => $statuses,
        'max' => PG_AUDIENCE_MAX_CONDITIONS,
    );
}

/**
 * The record ids a rule set names, by picker kind, each once in the order
 * met (nested sets included): a form answer names its form field.
 *
 * @param mixed $rules
 * @return array kind => int[]
 */
function pg_audience_rules_refs($rules)
{
    $refs = array('product' => array(), 'product_group' => array(), 'contact_group' => array(), 'form' => array(), 'form_field' => array());
    $catalogue = pg_audience_conditions();
    $walk = function (array $level) use (&$walk, &$refs, $catalogue) {
        foreach ($level['conditions'] as $condition) {
            if (isset($condition['conditions'])) {
                $walk($condition);
                continue;
            }

            $kind = $catalogue[$condition['key']]['value'];
            $id = ($kind === 'form_field') ? $condition['field'] : ($condition['value'] ?? null);

            if (isset($refs[$kind]) && !in_array($id, $refs[$kind], true)) {
                $refs[$kind][] = $id;
            }
        }
    };

    $walk(pg_audience_rules_clean($rules));

    return $refs;
}

/**
 * The kind and rules a save of the contact group form writes: 'dynamic'
 * only when the switch is on; the rules are cleaned and kept either way, so
 * a group switched back to static keeps them for a later switch. 'count' is
 * the number of conditions left at the top level.
 *
 * @param array $post
 * @return array array('kind', 'rules' => JSON text, 'count')
 */
function pg_audience_editor_posted(array $post)
{
    $kind = (isset($post['kind']) && ($post['kind'] === 'dynamic')) ? 'dynamic' : 'static';
    $rules = pg_audience_rules_clean(isset($post['rules']) && is_string($post['rules']) ? $post['rules'] : array());

    return array(
        'kind' => $kind,
        'rules' => json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'count' => count($rules['conditions']),
    );
}

/**
 * Whether two stored rule sets select differently, compared in their
 * cleaned shape (key order and dropped keys do not count).
 *
 * @param mixed $old
 * @param mixed $new
 * @return bool
 */
function pg_audience_rules_changed($old, $new)
{
    return pg_audience_rules_clean((string) $old) !== pg_audience_rules_clean((string) $new);
}

/**
 * The count line of the editor: exact under 100, rounded above ("about
 * 1,240 contacts"), '' when there is no count.
 *
 * @param int|null $count
 * @return string
 */
function pg_audience_count_text($count)
{
    if ($count === null) {
        return '';
    }

    $count = max(0, (int) $count);

    if ($count === 0) {
        return lang('No contacts');
    }

    if ($count === 1) {
        return lang('1 contact');
    }

    if ($count < 100) {
        return lang(array('string' => '{var:1} contacts', 'vars' => array(pg_format_number($count, 0))));
    }

    $step = ($count < 10000) ? 10 : (($count < 100000) ? 100 : 1000);

    return lang(array('string' => 'about {var:1} contacts', 'vars' => array(pg_format_number(round($count / $step) * $step, 0))));
}

/**
 * The names of the records a rule set names, for the editor's pickers to
 * show a stored choice: kind => array(id => text).
 *
 * @param mixed $rules
 * @return array
 */
function pg_audience_ref_labels($rules)
{
    $labels = array();

    foreach (pg_audience_rules_refs($rules) as $kind => $ids) {
        $labels[$kind] = array();

        if (!$ids) {
            continue;
        }

        $marks = implode(', ', array_fill(0, count($ids), '?'));

        foreach (pg_audience_picker_rows($kind, 'id IN (' . $marks . ')', $ids, count($ids)) as $row) {
            $labels[$kind][(string) $row['id']] = $row['text'];
        }
    }

    return $labels;
}

/**
 * The editor's picker search: up to 20 records of one kind whose name
 * contains the text, as {id, text}.
 *
 * @param string $kind product, product_group, contact_group, form or form_field
 * @param string $q
 * @return array
 */
function pg_audience_picker_search($kind, $q)
{
    $q = pg_audience_text($q);

    if ($q === '') {
        return array();
    }

    return pg_audience_picker_rows($kind, 'name LIKE ?', array(pg_list_like_pattern($q)), 20);
}

/**
 * Rows {id, text} of one picker kind matching a condition on the kind's
 * `id` and `name` columns (both given by a derived table, so the condition
 * reads the same for every kind).
 *
 * @param string $kind
 * @param string $where  on id and name, with ? placeholders
 * @param array  $params
 * @param int    $limit
 * @return array
 */
function pg_audience_picker_rows($kind, $where, array $params, $limit)
{
    switch ($kind) {
        case 'product':
            $from = "SELECT id, TRIM(CONCAT(short_description, ' (', name, ')')) AS name FROM products";
            break;
        case 'product_group':
            $from = "SELECT id, TRIM(CONCAT(name, IF(short_description <> '', CONCAT(' - ', short_description), ''))) AS name FROM product_groups";
            break;
        case 'contact_group':
            $from = 'SELECT id, name FROM contact_groups';
            break;
        case 'form':
            $from = "SELECT custom_form_pages.page_id AS id, IF(custom_form_pages.form_name <> '', custom_form_pages.form_name, page.page_name) AS name"
                . ' FROM custom_form_pages INNER JOIN page ON page.page_id = custom_form_pages.page_id';
            break;
        case 'form_field':
            $from = "SELECT form_fields.id, CONCAT(IF(custom_form_pages.form_name <> '', custom_form_pages.form_name, page.page_name), ': ', IF(form_fields.label <> '', form_fields.label, form_fields.name)) AS name"
                . ' FROM form_fields INNER JOIN custom_form_pages ON custom_form_pages.page_id = form_fields.page_id'
                . ' INNER JOIN page ON page.page_id = form_fields.page_id'
                . " WHERE form_fields.type NOT IN ('information', 'file upload')";
            break;
        default:
            return array();
    }

    $result = db_q('SELECT id, name FROM (' . $from . ') AS pick WHERE ' . $where . ' ORDER BY name LIMIT ' . (int) $limit, $params);
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = array('id' => (int) $row['id'], 'text' => (string) $row['name']);
    }

    return $rows;
}
