<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Volunteer programmes as staff run them (Contacts › Volunteers,
 * view_volunteer_programmes.php and edit_volunteer_programme.php): a
 * programme's Set-up read from its form and cleaned, what keeps it from
 * being complete, who sees it, an application made from the programme's
 * form, Approve with the welcome e-mail, Pause and Mark as left, and the
 * reads the lists and the profile drawer need. The cleaning and the checks
 * are pure; the rest needs pg_volunteer_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Roles a programme may have. */
const PG_VOLUNTEER_ROLES_MAX = 20;

/** A document running out within this many days shows "Running out". */
const PG_VOLUNTEER_RUNNING_OUT_DAYS = 30;

/** The ways a call can go out (call_channels). */
const PG_VOLUNTEER_CHANNELS = array('email', 'sms', 'whatsapp');

/** The messages of a programme (texts), in the order of their tabs. */
const PG_VOLUNTEER_MESSAGES = array('welcome', 'call', 'reminder', 'thanks');

/**
 * Whether the volunteer tables are there (the 2026.11.0 migration ran).
 *
 * @return bool
 */
function pg_volunteer_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('volunteer_programmes', 'texts') && pg_schema_has('volunteers', 'left_at');
    }

    return $ready;
}

/**
 * A comma text or a list as unique trimmed values, cut to a count and a
 * length. Pure.
 *
 * @param mixed $value
 * @param int   $max
 * @param int   $length
 * @return array
 */
function pg_volunteer_list_text($value, $max = 30, $length = 60)
{
    $items = is_array($value) ? $value : (is_scalar($value) ? explode(',', (string) $value) : array());
    $out = array();

    foreach ($items as $item) {
        $item = is_scalar($item) ? mb_substr(trim((string) $item), 0, $length) : '';

        if (($item !== '') && !in_array($item, $out, true) && (count($out) < $max)) {
            $out[] = $item;
        }
    }

    return $out;
}

/**
 * Ids from a list, kept only when allowed, each once. Pure.
 *
 * @param mixed $value
 * @param array $allowed
 * @return array list of int
 */
function pg_volunteer_ids($value, array $allowed)
{
    $allowed = array_map('intval', $allowed);
    $out = array();

    foreach (is_array($value) ? $value : array() as $id) {
        $id = is_scalar($id) ? (int) $id : 0;

        if (in_array($id, $allowed, true) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The roles of the Set-up form: a row each (role_row token, role_name,
 * role_skills and role_documents as comma texts, role_trainings[token] as
 * course ids). A stored key stays; a new row takes the next free 'rN'. Pure.
 *
 * @param array $post
 * @param array $course_ids
 * @return array key => array(name, skills, trainings, documents)
 */
function pg_volunteer_roles_input(array $post, array $course_ids)
{
    $rows = array();
    $tokens = is_array($post['role_row'] ?? null) ? array_values($post['role_row']) : array();

    foreach ($tokens as $i => $token) {
        $token = is_scalar($token) ? (string) $token : '';
        $rows[] = array(
            'key'       => $token,
            'name'      => $post['role_name'][$i] ?? '',
            'skills'    => pg_volunteer_list_text($post['role_skills'][$i] ?? ''),
            'trainings' => pg_volunteer_ids($post['role_trainings'][$token] ?? array(), $course_ids),
            'documents' => pg_volunteer_list_text($post['role_documents'][$i] ?? '', 10, 60),
        );
    }

    $roles = array();

    foreach (pg_waiting_keyed_rows($rows, 'r', PG_VOLUNTEER_ROLES_MAX, 60) as $row) {
        $roles[$row['key']] = array('name' => $row['name'], 'skills' => $row['skills'], 'trainings' => $row['trainings'], 'documents' => $row['documents']);
    }

    return $roles;
}

/**
 * A programme's messages cleaned: every kind with its subject and text,
 * empty for the standard one. Pure.
 *
 * @param mixed $texts JSON text or array
 * @return array kind => array(subject, body)
 */
function pg_volunteer_texts_clean($texts)
{
    $texts = is_string($texts) ? json_decode($texts, true) : $texts;
    $clean = array();

    foreach (PG_VOLUNTEER_MESSAGES as $kind) {
        $text = (is_array($texts) && is_array($texts[$kind] ?? null)) ? $texts[$kind] : array();
        $clean[$kind] = array('subject' => pg_waiting_text($text['subject'] ?? '', 150), 'body' => pg_waiting_text($text['body'] ?? '', 2000));
    }

    return $clean;
}

/**
 * The Set-up form read and cleaned, with the errors that refuse the save.
 * Pure.
 *
 * @param array $post
 * @param array $form_ids   the form pages the user may choose
 * @param array $fields     field names of the chosen form
 * @param array $course_ids the site's courses
 * @param array $user_ids   panel users who may coordinate
 * @return array array('programme' => array, 'errors' => list of texts)
 */
function pg_volunteer_programme_input(array $post, array $form_ids, array $fields, array $course_ids, array $user_ids)
{
    $errors = array();
    $name = pg_waiting_text($post['name'] ?? '', 150);
    $page_id = pg_waiting_int($post['page_id'] ?? 0, 0, PHP_INT_MAX, 0);

    if ($name === '') {
        $errors[] = lang('Give the programme a name.');
    }

    if (($page_id > 0) && !in_array($page_id, array_map('intval', $form_ids), true)) {
        $errors[] = lang('Choose one of the forms in the list.');
        $page_id = 0;
    }

    $form_fields = array();

    foreach (array('skills', 'birth_date', 'free_days') as $use) {
        $field = pg_waiting_text($post['field_' . $use] ?? '', 100);
        $form_fields[$use] = in_array($field, $fields, true) ? $field : '';
    }

    $channels = is_array($post['call_channels'] ?? null) ? $post['call_channels'] : array();

    return array(
        'programme' => array(
            'name'          => $name,
            'page_id'       => $page_id,
            'form_fields'   => $form_fields,
            'roles'         => pg_volunteer_roles_input($post, $course_ids),
            'cutoff_hours'  => pg_waiting_int($post['cutoff_hours'] ?? '', 0, 720, PG_VOLUNTEER_CUTOFF_HOURS),
            'min_age'       => pg_waiting_int($post['min_age'] ?? '', 0, 99, 0),
            'swap_approval' => empty($post['swap_approval']) ? 0 : 1,
            'call_days'     => pg_waiting_int($post['call_days'] ?? '', 0, 14, PG_VOLUNTEER_CALL_DAYS),
            'call_channels' => array_values(array_intersect(PG_VOLUNTEER_CHANNELS, $channels)),
            'texts'         => pg_volunteer_texts_clean($post['text'] ?? array()),
            'coordinators'  => pg_volunteer_ids($post['coordinators'] ?? array(), $user_ids),
        ),
        'errors' => $errors,
    );
}

/**
 * What a programme still misses: 'form', 'roles', 'coordinators'. Pure.
 *
 * @param array $programme
 * @return array
 */
function pg_volunteer_programme_missing(array $programme)
{
    $missing = array();

    foreach (array('form' => (int) ($programme['page_id'] ?? 0) > 0, 'roles' => !empty($programme['roles']), 'coordinators' => !empty($programme['coordinators'])) as $code => $set) {
        if (!$set) {
            $missing[] = $code;
        }
    }

    return $missing;
}

/**
 * Whether a panel user sees a programme: roles 0-2 all, anyone else as one
 * of its coordinators. Pure.
 *
 * @param array $programme coordinators
 * @param array $user      id, role
 * @return bool
 */
function pg_volunteer_user_sees(array $programme, array $user)
{
    return ((int) ($user['role'] ?? 3) < 3) || in_array((int) ($user['id'] ?? 0), array_map('intval', (array) ($programme['coordinators'] ?? array())), true);
}

/**
 * An ISO weekday (1 Monday … 7 Sunday) from an answer: a number, or an
 * English or site-language day name or its first three letters; 0 when
 * none. Pure.
 *
 * @param string $text
 * @return int
 */
function pg_volunteer_weekday($text)
{
    $text = mb_strtolower(trim((string) $text));

    if (preg_match('/^[1-7]$/', $text) === 1) {
        return (int) $text;
    }

    if (mb_strlen($text) < 3) {
        return 0;
    }

    $names = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

    foreach ($names as $i => $name) {
        foreach (array($name, mb_strtolower(lang(ucfirst($name)))) as $word) {
            if (($text === $word) || ((mb_strlen($text) === 3) && (mb_substr($word, 0, 3) === $text))) {
                return $i + 1;
            }
        }
    }

    return 0;
}

/**
 * The profile values an application gives through the programme's named
 * fields: skills (a choice field's values), the birth date (YYYY-MM-DD, ''
 * when unreadable) and the free days as the bookings' hours shape, a free
 * day free all day; no free days given means no limit. Pure.
 *
 * @param array $answers     field name => answer (pg_application_entry_answers())
 * @param array $form_fields skills, birth_date, free_days => field name
 * @return array array('skills', 'birth_date', 'hours')
 */
function pg_volunteer_application_values(array $answers, array $form_fields)
{
    $answer = function ($use) use ($answers, $form_fields) {
        $field = (string) ($form_fields[$use] ?? '');

        return (($field !== '') && isset($answers[$field])) ? pg_application_answer_values($answers[$field]) : array();
    };
    $birth = $answer('birth_date');
    $birth = $birth ? $birth[0] : '';
    $hours = array();

    foreach ($answer('free_days') as $day) {
        $day = pg_volunteer_weekday($day);

        if ($day > 0) {
            $hours[$day] = array(array('00:00', '24:00'));
        }
    }

    ksort($hours);

    return array(
        'skills'     => pg_volunteer_list_text($answer('skills')),
        'birth_date' => (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birth, $m) === 1) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $birth : '',
        'hours'      => $hours,
    );
}

/**
 * Whole years from a birth date to a day; null without a date. Pure.
 *
 * @param string $birth YYYY-MM-DD
 * @param string $today YYYY-MM-DD
 * @return int|null
 */
function pg_volunteer_age($birth, $today)
{
    if ((preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $birth) !== 1) || (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) !== 1)) {
        return null;
    }

    $age = (int) substr($today, 0, 4) - (int) substr($birth, 0, 4);

    return (substr($today, 5) < substr($birth, 5)) ? $age - 1 : $age;
}

/**
 * The documents pill of an application or a volunteer: '' when the
 * programme's roles need none, 'missing' when a needed kind has no copy
 * valid today, 'running_out' when one ends within 30 days, else 'valid'.
 * Pure.
 *
 * @param array  $documents list of array(kind, valid_until)
 * @param array  $kinds     the kinds the programme's roles need
 * @param string $today     YYYY-MM-DD
 * @return string
 */
function pg_volunteer_documents_state(array $documents, array $kinds, $today)
{
    if (!$kinds) {
        return '';
    }

    $soon = date('Y-m-d', strtotime($today . ' +' . PG_VOLUNTEER_RUNNING_OUT_DAYS . ' days'));
    $state = 'valid';

    foreach ($kinds as $kind) {
        $best = '';

        foreach ($documents as $document) {
            $until = (string) ($document['valid_until'] ?? '');

            if ((trim((string) ($document['kind'] ?? '')) === $kind) && ($until >= $today) && ($until > $best)) {
                $best = $until;
            }
        }

        if ($best === '') {
            return 'missing';
        }

        if ($best <= $soon) {
            $state = 'running_out';
        }
    }

    return $state;
}

/**
 * Whether a volunteer may move from one state to another: Approve (applied
 * to active), Pause and back, Mark as left from active or paused. Pure.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function pg_volunteer_state_move_allowed($from, $to)
{
    $moves = array('applied' => array('active'), 'active' => array('paused', 'left'), 'paused' => array('active', 'left'));

    return in_array((string) $to, $moves[(string) $from] ?? array(), true);
}

/**
 * A programme message with {name} and {programme} filled in, the standard
 * subject or text where the programme left one empty. Pure.
 *
 * @param mixed  $texts the programme's texts
 * @param string $kind  welcome, call, reminder or thanks
 * @param array  $vars  name, programme
 * @return array array('subject', 'body')
 */
function pg_volunteer_message($texts, $kind, array $vars)
{
    $hello = lang('Hello {name},') . "\n\n";
    $standard = array(
        'welcome'  => array(lang('Welcome to {programme}'), $hello . lang('Your application to {programme} was approved. Thank you for volunteering with us.')),
        'call'     => array(lang('{programme}: a shift needs you'), $hello . lang('A shift of {programme} still needs volunteers. Sign up if you can come.')),
        'reminder' => array(lang('{programme}: your shift is tomorrow'), $hello . lang('This is a reminder of your shift tomorrow with {programme}.')),
        'thanks'   => array(lang('{programme}: thank you'), $hello . lang('Thank you for the hours you gave to {programme}.')),
    );
    $text = pg_volunteer_texts_clean($texts)[$kind] ?? array('subject' => '', 'body' => '');
    $fill = function ($value) use ($vars) {
        return strtr($value, array('{name}' => (string) ($vars['name'] ?? ''), '{programme}' => (string) ($vars['programme'] ?? '')));
    };

    return array(
        'subject' => $fill(($text['subject'] !== '') ? $text['subject'] : $standard[$kind][0]),
        'body'    => $fill(($text['body'] !== '') ? $text['body'] : $standard[$kind][1]),
    );
}

/**
 * A programme's state as a pill: Open or Paused.
 *
 * @param string $state
 * @return string HTML
 */
function pg_volunteer_programme_pill($state)
{
    return ($state === 'paused') ? '<span class="pg-pill pg-tone-amber">' . h(lang('Paused')) . '</span>' : '<span class="pg-pill pg-tone-teal">' . h(lang('Open')) . '</span>';
}

/**
 * A volunteer's state, or an application's documents, as a pill.
 *
 * @param string $value applied, active, paused, left; valid, missing, running_out
 * @return string HTML, '' for an unknown value
 */
function pg_volunteer_pill($value)
{
    $pills = array(
        'applied'     => array(lang('Applicant'), 'pg-tone-blue'),
        'active'      => array(lang('Active'), 'pg-tone-teal'),
        'paused'      => array(lang('Paused'), 'pg-tone-amber'),
        'left'        => array(lang('Left'), 'pg-tone-gray'),
        'valid'       => array(lang('Valid'), 'pg-tone-teal'),
        'missing'     => array(lang('Missing'), 'pg-tone-rose'),
        'running_out' => array(lang('Running out'), 'pg-tone-amber'),
    );

    return isset($pills[$value]) ? '<span class="pg-pill ' . $pills[$value][1] . '">' . h($pills[$value][0]) . '</span>' : '';
}

/**
 * A programme row with its JSON decoded and its numbers typed.
 *
 * @param array $row volunteer_programmes row
 * @return array
 */
function pg_volunteer_programme_decode(array $row)
{
    $json = function ($value) {
        $value = json_decode((string) $value, true);

        return is_array($value) ? $value : array();
    };
    $fields = $json($row['form_fields'] ?? '');

    return array(
        'id'            => (int) $row['id'],
        'name'          => (string) $row['name'],
        'state'         => ((string) $row['state'] === 'paused') ? 'paused' : 'open',
        'page_id'       => (int) $row['page_id'],
        'form_fields'   => array('skills' => (string) ($fields['skills'] ?? ''), 'birth_date' => (string) ($fields['birth_date'] ?? ''), 'free_days' => (string) ($fields['free_days'] ?? '')),
        'roles'         => $json($row['roles'] ?? ''),
        'coordinators'  => array_map('intval', $json($row['coordinators'] ?? '')),
        'cutoff_hours'  => (int) $row['cutoff_hours'],
        'min_age'       => (int) $row['min_age'],
        'swap_approval' => (int) $row['swap_approval'],
        'call_days'     => (int) $row['call_days'],
        'call_channels' => array_values(array_intersect(PG_VOLUNTEER_CHANNELS, explode(',', (string) $row['call_channels']))),
        'texts'         => pg_volunteer_texts_clean($row['texts'] ?? ''),
    );
}

/**
 * The document kinds a programme's roles need, each once. Pure.
 *
 * @param array $programme
 * @return array
 */
function pg_volunteer_programme_kinds(array $programme)
{
    $kinds = array();

    foreach ((array) $programme['roles'] as $role) {
        $kinds = array_merge($kinds, (array) ($role['documents'] ?? array()));
    }

    return array_values(array_unique($kinds));
}

/**
 * A programme by id; null when there is none.
 *
 * @param int $id
 * @return array|null
 */
function pg_volunteer_programme_get($id)
{
    $row = db_item("SELECT * FROM volunteer_programmes WHERE id = '" . (int) $id . "'");

    return $row ? pg_volunteer_programme_decode($row) : null;
}

/**
 * Whether a panel user coordinates a programme: what opens Contacts ›
 * Volunteers to someone below role 2.
 *
 * @param int $user_id
 * @return bool
 */
function pg_volunteer_coordinator($user_id)
{
    if (((int) $user_id <= 0) || !pg_volunteer_ready()) {
        return false;
    }

    foreach ((array) db_values("SELECT coordinators FROM volunteer_programmes WHERE coordinators LIKE '%" . (int) $user_id . "%'") as $list) {
        if (in_array((int) $user_id, array_map('intval', (array) json_decode((string) $list, true)), true)) {
            return true;
        }
    }

    return false;
}

/**
 * The programmes a user sees with their figures: active volunteers,
 * applications waiting, and this week's places filled of needed.
 *
 * @param array $user validate_user()
 * @return array decoded programmes with active, applied, needed, filled
 */
function pg_volunteer_programme_list(array $user)
{
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime($week_start . ' +7 days'));
    $counts = array();

    foreach ((array) db_items("SELECT programme_id, state, COUNT(*) AS n FROM volunteers WHERE state IN ('active', 'applied') GROUP BY programme_id, state") as $row) {
        $counts[(int) $row['programme_id']][$row['state']] = (int) $row['n'];
    }

    $needed = array();

    foreach ((array) db_items("SELECT programme_id, roles FROM volunteer_shifts WHERE start_time >= '" . $week_start . "' AND start_time < '" . $week_end . "'") as $row) {
        $needed[(int) $row['programme_id']] = ($needed[(int) $row['programme_id']] ?? 0) + array_sum(array_map('intval', (array) json_decode((string) $row['roles'], true)));
    }

    $filled = array();

    foreach ((array) db_items("SELECT volunteer_shifts.programme_id, COUNT(*) AS n FROM volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.state = 'signed' AND volunteer_shifts.start_time >= '" . $week_start . "' AND volunteer_shifts.start_time < '" . $week_end . "' GROUP BY volunteer_shifts.programme_id") as $row) {
        $filled[(int) $row['programme_id']] = (int) $row['n'];
    }

    $list = array();

    foreach ((array) db_items("SELECT * FROM volunteer_programmes ORDER BY name, id") as $row) {
        $programme = pg_volunteer_programme_decode($row);

        if (pg_volunteer_user_sees($programme, $user)) {
            $id = $programme['id'];
            $list[] = $programme + array('active' => $counts[$id]['active'] ?? 0, 'applied' => $counts[$id]['applied'] ?? 0, 'needed' => $needed[$id] ?? 0, 'filled' => $filled[$id] ?? 0);
        }
    }

    return $list;
}

/**
 * Adds a programme, open, on a form.
 *
 * @param string $name
 * @param int    $page_id
 * @param int    $user_id
 * @return int the new id, 0 on failure
 */
function pg_volunteer_programme_create($name, $page_id, $user_id)
{
    $ok = db_q("INSERT INTO volunteer_programmes (name, state, page_id, form_fields, roles, coordinators, texts, created_by, created_at, updated_at)
        VALUES (?, 'open', ?, '{}', '{}', '[]', '{}', ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array(mb_substr((string) $name, 0, 150), (int) $page_id, (int) $user_id));

    return $ok ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * Saves the Set-up form (pg_volunteer_programme_input()) on a programme.
 *
 * @param int   $id
 * @param array $values
 * @return bool
 */
function pg_volunteer_programme_save($id, array $values)
{
    $json = function ($value) {
        return json_encode($value ?: new stdClass(), JSON_UNESCAPED_UNICODE);
    };

    return (bool) db_q("UPDATE volunteer_programmes SET name = ?, page_id = ?, form_fields = ?, roles = ?, coordinators = ?, cutoff_hours = ?, min_age = ?, swap_approval = ?,
        call_days = ?, call_channels = ?, texts = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(
        $values['name'], $values['page_id'], $json($values['form_fields']), $json($values['roles']), json_encode(array_values($values['coordinators'])),
        $values['cutoff_hours'], $values['min_age'], $values['swap_approval'], $values['call_days'], implode(',', $values['call_channels']), $json($values['texts']), (int) $id,
    ));
}

/**
 * Opens or pauses a programme. A paused one takes no new application.
 *
 * @param int    $id
 * @param string $state open or paused
 * @return bool
 */
function pg_volunteer_programme_set_state($id, $state)
{
    return (bool) db_q("UPDATE volunteer_programmes SET state = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(($state === 'paused') ? 'paused' : 'open', (int) $id));
}

/**
 * A completed submission of an open programme's form becomes an
 * application: a profile in state applied with the skills and free days its
 * answers give. A contact keeps one profile in a programme: a second
 * submission, or one from a volunteer who left, changes nothing here.
 *
 * @param int $page_id
 * @param int $form_id
 * @param int $contact_id
 * @return int profiles made
 */
function pg_volunteer_application_record($page_id, $form_id, $contact_id)
{
    if (((int) $contact_id <= 0) || ((int) $form_id <= 0) || !pg_volunteer_ready()) {
        return 0;
    }

    $made = 0;

    foreach ((array) db_items("SELECT * FROM volunteer_programmes WHERE page_id = '" . (int) $page_id . "' AND state = 'open'") as $row) {
        $programme = pg_volunteer_programme_decode($row);

        if ((int) db_value("SELECT COUNT(*) FROM volunteers WHERE programme_id = '" . $programme['id'] . "' AND contact_id = '" . (int) $contact_id . "'") > 0) {
            continue;
        }

        $answers = pg_application_entry_answers((array) db_items("SELECT name, data, file_id FROM form_data WHERE form_id = '" . (int) $form_id . "' ORDER BY id"));
        $values = pg_volunteer_application_values($answers, $programme['form_fields']);

        if (db_q("INSERT INTO volunteers (programme_id, contact_id, form_id, state, skills, hours, applied_at, updated_at) VALUES (?, ?, ?, 'applied', ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array(
            $programme['id'], (int) $contact_id, (int) $form_id, json_encode($values['skills'], JSON_UNESCAPED_UNICODE), json_encode((object) $values['hours']),
        ))) {
            $made++;
        }
    }

    return $made;
}

/**
 * A programme's volunteers in some states, with the contact's name and
 * e-mail, their documents, approved minutes this year and show-up rate.
 *
 * @param array $programme
 * @param array $states
 * @param int   $volunteer_id one profile only when above 0
 * @return array id => profile
 */
function pg_volunteer_people(array $programme, array $states, $volunteer_id = 0)
{
    $in = implode(', ', array_map(function ($state) {
        return "'" . escape((string) $state) . "'";
    }, $states));
    $people = array();

    foreach ((array) db_items("SELECT volunteers.*, contacts.first_name, contacts.last_name, contacts.email_address, contacts.mobile_phone FROM volunteers
        LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteers.programme_id = '" . $programme['id'] . "' AND volunteers.state IN (" . $in . ")" . (($volunteer_id > 0) ? " AND volunteers.id = '" . (int) $volunteer_id . "'" : '') . "
        ORDER BY volunteers.applied_at DESC, volunteers.id DESC") as $row) {
        $name = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['name'] = ($name !== '') ? $name : (string) $row['email_address'];
        $row['skills'] = pg_volunteer_list_text((array) json_decode((string) $row['skills'], true));
        $row['hours'] = pg_booking_hours_clean($row['hours']);
        $row['documents'] = array();
        $row['signups'] = array();
        $people[(int) $row['id']] = $row;
    }

    if (!$people) {
        return array();
    }

    $ids = implode(', ', array_keys($people));

    foreach ((array) db_items("SELECT id, volunteer_id, kind, valid_until, seen_at FROM volunteer_documents WHERE volunteer_id IN (" . $ids . ") ORDER BY valid_until DESC") as $row) {
        $people[(int) $row['volunteer_id']]['documents'][] = $row;
    }

    foreach ((array) db_items("SELECT volunteer_signups.*, volunteer_shifts.place, volunteer_shifts.start_time AS shift_start, volunteer_shifts.end_time AS shift_end FROM volunteer_signups
        LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.volunteer_id IN (" . $ids . ") ORDER BY COALESCE(volunteer_shifts.start_time, volunteer_signups.worked_on) DESC") as $row) {
        $people[(int) $row['volunteer_id']]['signups'][] = $row;
    }

    $year = date('Y') . '-01-01';

    foreach ($people as $id => $person) {
        $rows = array();

        foreach ($person['signups'] as $signup) {
            $shift = ($signup['shift_start'] !== null) ? array('start' => $signup['shift_start'], 'end' => $signup['shift_end']) : null;
            $rows[] = $signup + array('minutes_worked' => pg_volunteer_signup_minutes($signup, $shift), 'day' => substr((string) ($signup['shift_start'] ?? $signup['worked_on']), 0, 10));
        }

        $people[$id]['year_minutes'] = pg_volunteer_hours_total(array_map(function ($row) {
            return array('minutes' => $row['minutes_worked'], 'approved' => ((int) $row['approved_at'] > 0) && ($row['state'] !== 'cancelled'), 'day' => $row['day']);
        }, $rows), $year, date('Y-12-31'));
        $people[$id]['show_up'] = pg_volunteer_show_up_rate(array_map(function ($row) {
            return array('outcome' => $row['outcome'], 'start' => (string) $row['shift_start']);
        }, $person['signups']));
    }

    return $people;
}

/**
 * Moves a volunteer to a state when the move is allowed and nobody moved
 * them first; Approve also stamps who and when and sends the welcome
 * e-mail from the programme's text.
 *
 * @param array  $programme
 * @param array  $volunteer pg_volunteer_people() row
 * @param string $to
 * @param int    $user_id
 * @return bool
 */
function pg_volunteer_move(array $programme, array $volunteer, $to, $user_id)
{
    if (!pg_volunteer_state_move_allowed($volunteer['state'], $to)) {
        return false;
    }

    $stamps = ($to === 'active') && ($volunteer['state'] === 'applied') ? ", approved_at = UNIX_TIMESTAMP(), approved_by = '" . (int) $user_id . "'" : (($to === 'left') ? ', left_at = UNIX_TIMESTAMP()' : '');
    $ok = db_q("UPDATE volunteers SET state = ?" . $stamps . ", updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = ?", array($to, (int) $volunteer['id'], $volunteer['state']));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    if (($volunteer['state'] === 'applied') && validate_email_address((string) $volunteer['email_address'])) {
        $message = pg_volunteer_message($programme['texts'], 'welcome', array('name' => (string) $volunteer['first_name'], 'programme' => $programme['name']));
        email(array(
            'to'                 => (string) $volunteer['email_address'],
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $message['subject'],
            'format'             => 'plain_text',
            'body'               => $message['body'],
            'type'               => 'system',
            'queue'              => true,
        ));
    }

    return true;
}

/**
 * The volunteer profiles of a signed-in member's contact that are not left.
 *
 * @param int $contact_id
 * @return array volunteer ids
 */
function pg_volunteer_account_ids($contact_id)
{
    if (((int) $contact_id <= 0) || !pg_volunteer_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT id FROM volunteers WHERE contact_id = '" . (int) $contact_id . "' AND state <> 'left' ORDER BY applied_at, id"));
}
