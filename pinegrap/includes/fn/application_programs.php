<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: an application programme's settings on a form
// (application_programs), the period check of the form's submit path, the
// entry row written after the save with its eligibility, and the override.
// The pure rules it uses are in application_review.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The config_text row that keeps the criteria templates, as JSON. */
if (!defined('PG_APPLICATION_TEMPLATES_KEY')) {
    define('PG_APPLICATION_TEMPLATES_KEY', 'application_criteria_templates');
}

/**
 * Whether the programme tables are there (the 2026.11.0 migration ran).
 *
 * @return bool
 */
function pg_application_program_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('application_programs') && pg_schema_has('application_entries');
}

/**
 * Where a programme stands in its period at a time: 'none' for a draft (the
 * form works as it does with no programme), 'before', 'open' or 'closed'.
 *
 * @param array $program status, opens_at, closes_at
 * @param int   $now
 * @return string
 */
function pg_application_period_state(array $program, $now)
{
    $status = (int) ($program['status'] ?? 0);

    if ($status === 0) {
        return 'none';
    }

    if ($status !== 1) {
        return 'closed';
    }

    if (((int) $program['opens_at'] > 0) && ($now < (int) $program['opens_at'])) {
        return 'before';
    }

    if (((int) $program['closes_at'] > 0) && ($now >= (int) $program['closes_at'])) {
        return 'closed';
    }

    return 'open';
}

/**
 * Whether the period refuses a write: a new entry only inside the period,
 * a draft saved for later finished until the close.
 *
 * @param string $state pg_application_period_state()
 * @param bool   $finishing_draft
 * @return bool
 */
function pg_application_period_refuses($state, $finishing_draft)
{
    if ($finishing_draft) {
        return ($state === 'closed');
    }

    return in_array($state, array('before', 'closed'), true);
}

/**
 * One eligibility rule from a row of the screen: the field, the condition
 * and the value as typed ("18-30" for a range, "A, B" for a list).
 *
 * @param string $type  file, range or choice
 * @param string $field the form field's name
 * @param string $value
 * @return array|null|false the rule; null for an empty or unknown row;
 *                          false for a value that cannot be read
 */
function pg_application_rule_from_input($type, $field, $value)
{
    $field = trim((string) $field);
    $value = trim((string) $value);

    if (($field === '') || !in_array($type, array('file', 'range', 'choice'), true)) {
        return null;
    }

    if ($type === 'file') {
        return array('type' => 'file', 'field' => $field);
    }

    if ($type === 'choice') {
        $values = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));

        return $values ? array('type' => 'choice', 'field' => $field, 'values' => $values) : false;
    }

    // A range: "min-max", either end may be left out; a comma is a decimal
    // point, as people type it in Turkish.
    if (!preg_match('/^(-?[0-9]+(?:[.,][0-9]+)?)?\s*-\s*([0-9]+(?:[.,][0-9]+)?)?$/u', $value, $match) || (($match[1] ?? '') === '' && ($match[2] ?? '') === '')) {
        return false;
    }

    $number = function ($text) {
        return ($text === '') ? null : (float) str_replace(',', '.', $text);
    };

    return array('type' => 'range', 'field' => $field, 'min' => $number($match[1] ?? ''), 'max' => $number($match[2] ?? ''));
}

/**
 * A rule's value as the screen shows it in its row.
 *
 * @param array $rule
 * @return string
 */
function pg_application_rule_value_text(array $rule)
{
    if ($rule['type'] === 'choice') {
        return implode(', ', $rule['values']);
    }

    if ($rule['type'] === 'range') {
        $text = function ($number) {
            return ($number === null) ? '' : rtrim(rtrim(number_format((float) $number, 4, '.', ''), '0'), '.');
        };

        return $text($rule['min']) . '-' . $text($rule['max']);
    }

    return '';
}

/**
 * The criteria's weights added up.
 *
 * @param array $criteria list of array('weight' => int)
 * @return int
 */
function pg_application_weights_sum(array $criteria)
{
    return array_sum(array_map('intval', array_column($criteria, 'weight')));
}

/**
 * A date and time from the screen's date and time field as a Unix time in
 * the site's time zone; 0 when empty or unreadable.
 *
 * @param mixed $value
 * @return int
 */
function pg_application_time_from_input($value)
{
    $value = is_string($value) ? trim($value) : '';

    // The panel's date and time field posts the site's own format; read it
    // to ISO the way every date field of the panel is read.
    if (($value !== '') && !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value) && function_exists('prepare_form_data_for_input')) {
        $value = (string) prepare_form_data_for_input($value, 'date and time');
    }

    $time = ($value === '') ? false : strtotime(str_replace('T', ' ', $value));

    return ($time === false) ? 0 : (int) $time;
}

/**
 * The Set-up tab's post read into a programme's settings, with the errors
 * that stop a save. Unknown fields are dropped; weights that do not add up
 * to 100 are kept (the screen shows them amber) and only keep it from
 * opening (pg_application_program_missing()).
 *
 * @param array $post
 * @param array $fields the form's fields, name => type
 * @return array array('program' => settings, 'errors' => list of text)
 */
function pg_application_program_input(array $post, array $fields)
{
    $errors = array();
    $text = function ($value, $max) {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    };
    $list = function ($value) {
        return is_array($value) ? array_values($value) : array();
    };

    $program = array('name' => $text($post['name'] ?? '', 150));

    if ($program['name'] === '') {
        $errors[] = lang('Give the programme a name.');
    }

    $program['opens_at'] = pg_application_time_from_input($post['opens'] ?? '');
    $program['closes_at'] = pg_application_time_from_input($post['closes'] ?? '');

    if ($program['opens_at'] && $program['closes_at'] && ($program['closes_at'] <= $program['opens_at'])) {
        $errors[] = lang('The period closes before it opens.');
    }

    $category = $text($post['category_field'] ?? '', 100);
    $program['category_field'] = (isset($fields[$category]) && in_array($fields[$category], array('pick list', 'radio button', 'check box'), true)) ? $category : '';

    $program['eligibility'] = array();
    $rule_fields = $list($post['rule_field'] ?? null);
    $rule_types = $list($post['rule_type'] ?? null);
    $rule_values = $list($post['rule_value'] ?? null);
    $range_error = false;

    foreach ($rule_fields as $i => $rule_field) {
        $rule_field = is_string($rule_field) ? $rule_field : '';

        if (!isset($fields[$rule_field])) {
            continue;
        }

        $rule = pg_application_rule_from_input(is_string($rule_types[$i] ?? null) ? $rule_types[$i] : '', $rule_field, is_string($rule_values[$i] ?? null) ? $rule_values[$i] : '');

        if ($rule === false) {
            $range_error = true;
        } elseif ($rule !== null) {
            $program['eligibility'][] = $rule;
        }
    }

    if ($range_error) {
        $errors[] = lang('Write a number range as 18-30 and a list of choices with commas.');
    }

    $program['rounds'] = array();
    $weight_error = false;
    $next_id = 1;

    foreach ($list($post['rounds'] ?? null) as $round) {
        if (!is_array($round)) {
            continue;
        }

        $criteria = array();
        $used = array();
        $names = $list($round['cname'] ?? null);
        $weights = $list($round['cweight'] ?? null);
        $ids = $list($round['cid'] ?? null);

        foreach ($names as $i => $name) {
            $name = $text($name, 100);

            if ($name === '') {
                continue;
            }

            $weight = is_string($weights[$i] ?? null) ? trim($weights[$i]) : '';

            if (!preg_match('/^[0-9]{1,3}$/', $weight) || ((int) $weight < 1) || ((int) $weight > 100)) {
                $weight_error = true;
            }

            $id = (is_string($ids[$i] ?? null) && preg_match('/^c[0-9]{1,6}$/', $ids[$i])) ? $ids[$i] : '';

            if (($id === '') || isset($used[$id])) {
                do {
                    $id = 'c' . $next_id++;
                } while (isset($used[$id]) || in_array($id, $ids, true));
            }

            $used[$id] = true;
            $criteria[] = array('id' => $id, 'name' => $name, 'weight' => (int) $weight);
        }

        if (!$criteria) {
            continue;
        }

        $program['rounds'][] = array(
            'criteria' => $criteria,
            'reviewers' => max(1, min(10, (int) (is_string($round['reviewers'] ?? null) ? $round['reviewers'] : 2))),
            'ends_at' => pg_application_time_from_input($round['ends'] ?? ''),
        );
    }

    if ($weight_error) {
        $errors[] = lang('Each criterion needs a weight from 1 to 100.');
    }

    $program['reviewers_per_entry'] = $program['rounds'] ? $program['rounds'][0]['reviewers'] : 2;
    $program['blind'] = (($post['blind'] ?? '') === '1') ? 1 : 0;
    $program['blind_fields'] = array_values(array_intersect(array_filter($list($post['blind_fields'] ?? null), 'is_string'), array_keys($fields)));

    $texts = is_array($post['texts'] ?? null) ? $post['texts'] : array();
    $program['texts'] = array('outside' => $text($post['outside_text'] ?? '', 2000));

    foreach (array('accepted', 'rejected', 'waitlisted') as $decision) {
        $program['texts'][$decision] = array(
            'subject' => $text($texts[$decision]['subject'] ?? '', 200),
            'body' => $text($texts[$decision]['body'] ?? '', 20000),
        );
    }

    return array('program' => $program, 'errors' => $errors);
}

/**
 * What keeps a programme from opening: 'period' (no closing time) and
 * 'criteria' (no round, or a round whose weights are not 100).
 *
 * @param array $program closes_at, rounds
 * @return array list of codes
 */
function pg_application_program_missing(array $program)
{
    $missing = array();

    if ((int) ($program['closes_at'] ?? 0) <= 0) {
        $missing[] = 'period';
    }

    $rounds = $program['rounds'] ?? array();
    $criteria_ok = (bool) $rounds;

    foreach ($rounds as $round) {
        if (!pg_application_criteria_valid($round['criteria'] ?? array())) {
            $criteria_ok = false;
        }
    }

    if (!$criteria_ok) {
        $missing[] = 'criteria';
    }

    return $missing;
}

/**
 * An entry's answers from its form_data rows: field name => answer, a list
 * when the field kept several rows (a check box group); an upload answers
 * with its file id, so a rule on it sees it as given.
 *
 * @param array $rows list of array('name', 'data', 'file_id')
 * @return array
 */
function pg_application_entry_answers(array $rows)
{
    $answers = array();

    foreach ($rows as $row) {
        $name = (string) $row['name'];
        $value = ((int) ($row['file_id'] ?? 0) > 0) ? (string) (int) $row['file_id'] : (string) $row['data'];

        if (!array_key_exists($name, $answers)) {
            $answers[$name] = $value;
        } else {
            $answers[$name] = array_merge((array) $answers[$name], array($value));
        }
    }

    return $answers;
}

/**
 * One reason an entry is not eligible, in the words the panel shows.
 *
 * @param array  $reason array('code', 'field') from pg_application_eligibility()
 * @param string $label  the field's label; its name when empty
 * @return string
 */
function pg_application_reason_text(array $reason, $label)
{
    $words = array(
        'missing' => lang('no answer'),
        'not_number' => lang('not a number'),
        'below' => lang('below the range'),
        'above' => lang('above the range'),
        'not_listed' => lang('not one of the choices allowed'),
    );

    return (($label !== '') ? $label : (string) $reason['field']) . ': ' . ($words[$reason['code']] ?? (string) $reason['code']);
}

/**
 * A programme row with its JSON columns read.
 *
 * @param array $row
 * @return array
 */
function pg_application_program_decode(array $row)
{
    foreach (array('rounds', 'eligibility', 'blind_fields', 'texts') as $column) {
        $value = json_decode((string) ($row[$column] ?? ''), true);
        $row[$column] = is_array($value) ? $value : array();
    }

    foreach (array('id', 'page_id', 'status', 'opens_at', 'closes_at', 'category_field_id', 'reviewers_per_entry', 'round', 'blind') as $column) {
        $row[$column] = (int) ($row[$column] ?? 0);
    }

    return $row;
}

/**
 * Rows of a query with bound values.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function _pg_application_rows($sql, array $params = array())
{
    $result = db_q($sql, $params);
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * A programme by id, decoded; null when there is none.
 *
 * @param int $id
 * @return array|null
 */
function pg_application_program_get($id)
{
    $rows = _pg_application_rows("SELECT * FROM application_programs WHERE id = ?", array((int) $id));

    return $rows ? pg_application_program_decode($rows[0]) : null;
}

/**
 * The form pages a user may run a programme on: page id => form name with
 * the page's folder, by the folder rule (check_edit_access()).
 *
 * @param array $user validate_user()
 * @return array page id => array('name', 'folder_id')
 */
function pg_application_program_forms(array $user)
{
    $forms = array();

    foreach (_pg_application_rows("SELECT page.page_id, page.page_folder, custom_form_pages.form_name FROM custom_form_pages INNER JOIN page ON page.page_id = custom_form_pages.page_id ORDER BY custom_form_pages.form_name") as $row) {
        if (((int) $user['role'] < 3) || check_edit_access($row['page_folder'])) {
            $forms[(int) $row['page_id']] = array('name' => (string) $row['form_name'], 'folder_id' => (int) $row['page_folder']);
        }
    }

    return $forms;
}

/**
 * The programmes on the forms a user may run, newest first, each with its
 * form's name and its entry count.
 *
 * @param array $user
 * @return array
 */
function pg_application_program_list(array $user)
{
    $forms = pg_application_program_forms($user);

    if (!$forms) {
        return array();
    }

    $programs = array();
    $rows = _pg_application_rows("SELECT application_programs.*, (SELECT COUNT(*) FROM application_entries WHERE application_entries.program_id = application_programs.id) AS entries
        FROM application_programs WHERE page_id IN (" . implode(', ', array_fill(0, count($forms), '?')) . ") ORDER BY id DESC", array_keys($forms));

    foreach ($rows as $row) {
        $program = pg_application_program_decode($row);
        $program['entries'] = (int) $row['entries'];
        $program['form_name'] = $forms[$program['page_id']]['name'];
        $programs[] = $program;
    }

    return $programs;
}

/**
 * A form's fields a programme can name: name => array('id', 'label', 'type').
 *
 * @param int $page_id
 * @return array
 */
function pg_application_program_fields($page_id)
{
    $fields = array();

    foreach (_pg_application_rows("SELECT id, name, label, type FROM form_fields WHERE (page_id = ?) AND (type NOT IN ('information')) AND (name != '') ORDER BY sort_order", array((int) $page_id)) as $row) {
        $fields[(string) $row['name']] = array('id' => (int) $row['id'], 'label' => (string) $row['label'], 'type' => (string) $row['type']);
    }

    return $fields;
}

/**
 * Creates a draft programme on a form.
 *
 * @param string $name
 * @param int    $page_id
 * @param int    $user_id
 * @return int the new id, 0 on failure
 */
function pg_application_program_create($name, $page_id, $user_id)
{
    $ok = db_q("INSERT INTO application_programs (page_id, name, status, created_by, created_at, updated_at) VALUES (?, ?, 0, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array((int) $page_id, mb_substr((string) $name, 0, 150), (int) $user_id));
    $id = $ok ? (int) mysqli_insert_id(db::$con) : 0;

    if ($id) {
        pg_audit('create', 'application_program', $id, array(), array('name' => $name, 'page_id' => (int) $page_id), $name);
    }

    return $id;
}

/**
 * Saves the Set-up tab's settings (pg_application_program_input()) on a
 * programme.
 *
 * @param array $program the stored programme
 * @param array $input   the settings
 * @param array $fields  pg_application_program_fields()
 * @return bool
 */
function pg_application_program_save(array $program, array $input, array $fields)
{
    $json = function ($value) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    };
    $category_id = ($input['category_field'] !== '') ? $fields[$input['category_field']]['id'] : 0;

    $ok = (bool) db_q("UPDATE application_programs SET name = ?, opens_at = ?, closes_at = ?, category_field_id = ?, reviewers_per_entry = ?, blind = ?,
        rounds = ?, eligibility = ?, blind_fields = ?, texts = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(
        $input['name'], $input['opens_at'], $input['closes_at'], $category_id, $input['reviewers_per_entry'], $input['blind'],
        $json($input['rounds']), $json($input['eligibility']), $json($input['blind_fields']), $json($input['texts']), (int) $program['id'],
    ));

    if ($ok) {
        pg_audit('update', 'application_program', (int) $program['id'], array(), array('name' => $input['name']), $input['name']);
    }

    return $ok;
}

/**
 * Moves a programme to another status (0 draft, 1 open, 2 closed).
 *
 * @param array $program
 * @param int   $status
 * @return bool
 */
function pg_application_program_set_status(array $program, $status)
{
    $ok = (bool) db_q("UPDATE application_programs SET status = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array((int) $status, (int) $program['id']));

    if ($ok) {
        pg_audit('update', 'application_program', (int) $program['id'], array('status' => (int) $program['status']), array('status' => (int) $status), $program['name']);
    }

    return $ok;
}

/**
 * Deletes a draft programme that has no entries.
 *
 * @param array $program
 * @return bool false when it is not a draft or has entries
 */
function pg_application_program_delete(array $program)
{
    if (((int) $program['status'] !== 0) || (int) db_value("SELECT COUNT(*) FROM application_entries WHERE program_id = '" . (int) $program['id'] . "'")) {
        return false;
    }

    db_q("DELETE FROM application_reviewers WHERE program_id = ?", array((int) $program['id']));
    $ok = (bool) db_q("DELETE FROM application_programs WHERE id = ?", array((int) $program['id']));

    if ($ok) {
        pg_audit('delete', 'application_program', (int) $program['id'], array('name' => $program['name']), array(), $program['name']);
    }

    return $ok;
}

/**
 * Another open programme on the same form, which keeps this one from
 * opening: a form takes entries for one programme at a time.
 *
 * @param array $program
 * @return bool
 */
function pg_application_program_form_taken(array $program)
{
    return (int) db_value("SELECT COUNT(*) FROM application_programs WHERE (page_id = '" . (int) $program['page_id'] . "') AND (status = 1) AND (id != '" . (int) $program['id'] . "')") > 0;
}

/**
 * The programme a form's entries go to: the open one, else the latest closed
 * one (which refuses); null when the form has none or the tables are not
 * there. Kept per request.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_application_program_for_form($page_id)
{
    static $cache = array();
    $page_id = (int) $page_id;

    if (!array_key_exists($page_id, $cache)) {
        $rows = pg_application_program_ready()
            ? _pg_application_rows("SELECT * FROM application_programs WHERE (page_id = ?) AND (status IN (1, 2)) ORDER BY status ASC, id DESC LIMIT 1", array($page_id))
            : array();
        $cache[$page_id] = $rows ? pg_application_program_decode($rows[0]) : null;
    }

    return $cache[$page_id];
}

/**
 * The submit path's period check: the programme's own text when its period
 * refuses this write, '' when the form takes it.
 *
 * @param int  $page_id
 * @param bool $finishing_draft a draft saved for later is being completed
 * @return string
 */
function pg_application_submit_refusal($page_id, $finishing_draft = false)
{
    $program = pg_application_program_for_form($page_id);

    if (($program === null) || !pg_application_period_refuses(pg_application_period_state($program, time()), $finishing_draft)) {
        return '';
    }

    $text = trim((string) ($program['texts']['outside'] ?? ''));

    return ($text !== '') ? $text : lang('This form does not take entries at the moment.');
}

/**
 * Writes the entry row of a completed submission to the form's open
 * programme, with its category and its eligibility checked against the
 * programme's rules. A failing entry is saved too, as not eligible with its
 * reasons; nothing is refused here. A second call for the same submission
 * changes nothing.
 *
 * @param int $page_id
 * @param int $form_id forms.id
 * @return int the entry id, 0 when the form has no open programme
 */
function pg_application_entry_record($page_id, $form_id)
{
    $program = pg_application_program_for_form($page_id);

    if (($program === null) || ((int) $program['status'] !== 1) || ((int) $form_id <= 0)) {
        return 0;
    }

    $answers = pg_application_entry_answers(_pg_application_rows("SELECT name, data, file_id FROM form_data WHERE form_id = ? ORDER BY id", array((int) $form_id)));
    $check = pg_application_eligibility($program['eligibility'], $answers);
    $category = '';

    if ($program['category_field_id'] > 0) {
        $category_name = (string) db_value("SELECT name FROM form_fields WHERE id = '" . (int) $program['category_field_id'] . "'");
        $category_values = pg_application_answer_values($answers[$category_name] ?? null);
        $category = $category_values ? mb_substr($category_values[0], 0, 100) : '';
    }

    db_q("INSERT IGNORE INTO application_entries (program_id, form_id, category, round, eligible, eligibility_note, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", array(
        (int) $program['id'], (int) $form_id, $category, $check['eligible'] ? 1 : 2,
        $check['eligible'] ? null : json_encode(array('reasons' => $check['reasons']), JSON_UNESCAPED_UNICODE),
    ));

    return (int) db_value("SELECT id FROM application_entries WHERE form_id = '" . (int) $form_id . "'");
}

/**
 * A submission's entry with its programme, for the submitted form's screen;
 * null when it is in no programme.
 *
 * @param int $form_id
 * @return array|null array('entry' => row with 'note' decoded, 'program' => decoded)
 */
function pg_application_entry_for_form($form_id)
{
    if (!pg_application_program_ready()) {
        return null;
    }

    $rows = _pg_application_rows("SELECT * FROM application_entries WHERE form_id = ?", array((int) $form_id));
    $program = $rows ? pg_application_program_get($rows[0]['program_id']) : null;

    if ($program === null) {
        return null;
    }

    $note = json_decode((string) $rows[0]['eligibility_note'], true);
    $rows[0]['note'] = is_array($note) ? $note : array();

    return array('entry' => $rows[0], 'program' => $program);
}

/**
 * Lets a not eligible entry in, with the person's note; the reasons stay
 * beside it.
 *
 * @param array  $entry   application_entries row with 'note'
 * @param string $note
 * @param int    $user_id
 * @return bool false for an entry that is not "not eligible" or an empty note
 */
function pg_application_entry_override(array $entry, $note, $user_id)
{
    $note = mb_substr(trim((string) $note), 0, 1000);

    if (((int) $entry['eligible'] !== 2) || ($note === '')) {
        return false;
    }

    $stored = $entry['note'];
    $stored['override'] = array('note' => $note, 'by' => (int) $user_id, 'at' => time());
    $ok = (bool) db_q("UPDATE application_entries SET eligible = 3, eligibility_note = ?, updated_at = UNIX_TIMESTAMP() WHERE (id = ?) AND (eligible = 2)", array(json_encode($stored, JSON_UNESCAPED_UNICODE), (int) $entry['id']));

    if ($ok) {
        pg_audit('update', 'application_entry', (int) $entry['id'], array('eligible' => 2), array('eligible' => 3, 'note' => $note));
    }

    return $ok;
}

/**
 * The kept criteria templates: list of array('name', 'criteria').
 *
 * @return array
 */
function pg_application_templates()
{
    $templates = json_decode(pg_config_text(PG_APPLICATION_TEMPLATES_KEY), true);

    return is_array($templates) ? array_values(array_filter($templates, function ($template) {
        return is_array($template) && isset($template['name'], $template['criteria']) && is_array($template['criteria']);
    })) : array();
}

/**
 * Keeps a round's criteria as a template; one of the same name is replaced.
 *
 * @param string $name
 * @param array  $criteria list of array('name', 'weight')
 * @return bool
 */
function pg_application_template_save($name, array $criteria)
{
    $name = mb_substr(trim((string) $name), 0, 150);

    if (($name === '') || !$criteria) {
        return false;
    }

    $templates = array_values(array_filter(pg_application_templates(), function ($template) use ($name) {
        return $template['name'] !== $name;
    }));
    $templates[] = array('name' => $name, 'criteria' => array_map(function ($criterion) {
        return array('name' => (string) $criterion['name'], 'weight' => (int) $criterion['weight']);
    }, $criteria));

    return pg_config_text_save(PG_APPLICATION_TEMPLATES_KEY, json_encode(array_slice($templates, -50), JSON_UNESCAPED_UNICODE));
}

/**
 * The submitted form screen's "Application" card for a submission in a
 * programme: its eligibility, the reasons when it is not eligible, and the
 * note that lets it in anyway. The button posts a separate form (returned as
 * 'form') through its form attribute, as the card sits inside the screen's
 * own form.
 *
 * @param int $form_id
 * @return array array('card' => HTML, 'form' => HTML); both '' outside a programme
 */
function pg_application_entry_card($form_id)
{
    $application = pg_application_entry_for_form($form_id);

    if ($application === null) {
        return array('card' => '', 'form' => '');
    }

    $entry = $application['entry'];
    $fields = pg_application_program_fields($application['program']['page_id']);
    $states = array(
        0 => array('gray', lang('Not checked')),
        1 => array('teal', lang('Eligible')),
        2 => array('amber', lang('Not eligible')),
        3 => array('teal', lang('Let in')),
    );
    $state = $states[(int) $entry['eligible']] ?? $states[0];
    $body = '<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="pg-text-2 flex-grow-1">' . h($application['program']['name']) . '</span><span class="pg-pill pg-tone-' . $state[0] . '">' . h($state[1]) . '</span></div>';

    foreach ($entry['note']['reasons'] ?? array() as $reason) {
        $body .= '<div class="pg-text-meta">' . h(pg_application_reason_text($reason, $fields[$reason['field']]['label'] ?? '')) . '</div>';
    }

    if (isset($entry['note']['override']['note'])) {
        $body .= '<div class="pg-text-2 mt-3">' . h($entry['note']['override']['note']) . '</div>';
    }

    $form = '';

    if ((int) $entry['eligible'] === 2) {
        $body .= '<div class="mt-4"><label class="pg-field-label" for="application_note">' . h(lang('Why it is let in')) . '</label>'
            . '<textarea class="pg-input" id="application_note" name="application_note" form="pg_application_override" rows="2" maxlength="1000" aria-describedby="application_note_help"></textarea>'
            . '<div class="pg-field-help" id="application_note_help">' . h(lang('The entry then gets reviewers as an eligible one; the reasons stay beside your note.')) . '</div>'
            . '<button type="submit" form="pg_application_override" name="application_override" value="1" class="pg-button pg-button-quiet pg-button-sm mt-3">' . h(lang('Let it in')) . '</button></div>';
        $form = '<form id="pg_application_override" action="edit_submitted_form.php?id=' . (int) $form_id . '" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . (int) $form_id . '"></form>';
    }

    return array('card' => pg_submitted_form_status_card(lang('Application'), $body), 'form' => $form);
}
