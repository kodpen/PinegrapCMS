<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An application programme's screen (Forms › Application programmes): the
 * head with the name, the status and the button that follows it, the tabs,
 * and the Set-up tab: period, categories, eligibility, rounds and criteria,
 * blind review and decision e-mails; and the Reviewers tab: the people who
 * score the entries, the run that gives the entries out to them and the
 * conflicts they declared; and the Entries tab: the current round's entries
 * ranked per category with their combined scores, the shortlist cut,
 * settling an entry that needs consensus, letting a not eligible entry in,
 * and moving the shortlist into the next round; and the Decisions tab:
 * accepted, rejected or waitlisted one by one or for the ticked rows, a
 * declined place given to the next on the waitlist, and the decision e-mails
 * previewed with their counts and sent on a press; and the Report tab:
 * reviewer progress, the scores' spread by criterion and reviewer, the
 * entries by state, and the same as an Excel workbook. Gated by validate_forms_access() and the folder
 * rule on the programme's form. It also takes the list drawer's add (a name
 * and a form: a new draft) and the row menu's Delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_reviewers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_ranking.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_decisions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_jurors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_report.php');

$user = validate_user();
validate_forms_access($user);

if (!pg_application_program_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_application_program');
$ap_forms = pg_application_program_forms($user);
// The list's row menu posts Delete with the programme's id as its value.
$ap_id = (int) ($_POST['id'] ?? $_POST['submit_delete'] ?? $_GET['id'] ?? 0);
$ap_program = ($ap_id > 0) ? pg_application_program_get($ap_id) : null;
$ap_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_application_program.php';
$ap_view = $_POST['view'] ?? $_GET['view'] ?? '';
$ap_view = (in_array($ap_view, array('reviewers', 'entries', 'decisions', 'report'), true) && ($ap_program !== null)) ? $ap_view : 'setup';
$ap_category = is_string($_POST['category'] ?? $_GET['category'] ?? null) ? mb_substr($_POST['category'] ?? $_GET['category'], 0, 100) : null;
$ap_list = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_application_programs.php';
$ap_filter = $_POST['filter'] ?? $_GET['filter'] ?? '';
$ap_filter = in_array($ap_filter, array('round', 'all'), true) ? $ap_filter : 'shortlist';

if (($ap_id > 0) && ($ap_program === null)) {
    output_error(lang('The programme was not found.'), 404);
}

// The folder rule: only a programme on a form the user may edit.
if (($ap_program !== null) && !isset($ap_forms[$ap_program['page_id']])) {
    log_activity('access denied to application programme ' . $ap_id, $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '.', 403);
}

// The report's round: the programme's current one unless another is asked.
$ap_report_round = (int) ($_GET['round'] ?? 0);
$ap_report_round = ($ap_program !== null) ? ((($ap_report_round >= 1) && ($ap_report_round <= max(1, $ap_program['round']))) ? $ap_report_round : max(1, $ap_program['round'])) : 1;

// The report as an Excel workbook: Reviewers, Scores and Entries.
if (($ap_view === 'report') && (($_GET['export'] ?? '') === 'xlsx')) {
    $ap_sheets = pg_application_report_sheets(pg_application_report($ap_program, $ap_report_round), pg_application_category_choices($ap_program));
    $ap_path = tempnam(sys_get_temp_dir(), 'pga');

    if (($ap_path === false) || !pg_xlsx_write_book($ap_sheets, function ($index) use ($ap_sheets) {
        return $ap_sheets[$index]['rows'];
    }, $ap_path)) {
        if ($ap_path !== false) {
            @unlink($ap_path);
        }
        output_error(lang('The workbook could not be written.'));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="application_programme_' . $ap_id . '_round_' . $ap_report_round . '.xlsx"');
    header('Content-Length: ' . filesize($ap_path));
    header('Cache-Control: private, no-store');
    readfile($ap_path);
    @unlink($ap_path);
    exit();
}

$ap_fields = ($ap_program !== null) ? pg_application_program_fields($ap_program['page_id']) : array();
$ap_field_types = array_map(function ($field) {
    return $field['type'];
}, $ap_fields);
$ap_input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    // A new programme: its name and form, then the programme as a draft.
    if ($ap_program === null) {
        $ap_new_name = mb_substr(trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : ''), 0, 150);
        $ap_new_page = (int) ($_POST['page_id'] ?? 0);

        if (($ap_new_name === '') || !isset($ap_forms[$ap_new_page])) {
            $ap_list_form = new liveform('view_application_programs');
            $ap_list_form->add_error(h(lang('Give the programme a name and choose its form.')));
            go($ap_list . '?add=1');
        }

        $ap_new_id = pg_application_program_create($ap_new_name, $ap_new_page, (int) $user['id']);
        log_activity('application programme "' . $ap_new_name . '" was created');
        go($ap_self . '?id=' . $ap_new_id);
    }

    $ap_back = $ap_self . '?id=' . $ap_id . (($ap_view !== 'setup') ? '&view=' . $ap_view : '') . (($ap_category !== null) ? '&category=' . rawurlencode($ap_category) : '') . (($ap_view === 'decisions') ? '&filter=' . $ap_filter : '');

    // Decisions: a button for the ticked rows, or each row's own choice.
    if (isset($_POST['submit_bulk']) || isset($_POST['submit_decisions'])) {
        $ap_listed = pg_application_decision_rows($ap_program, 'all');
        $ap_changes = array();

        if (isset($_POST['submit_bulk'])) {
            $ap_code = pg_application_decision_code($_POST['submit_bulk']);

            foreach (pg_application_posted_ids($_POST['entries'] ?? null, $ap_listed) as $ap_eid) {
                $ap_changes[$ap_code][] = $ap_eid;
            }
        } else {
            foreach (is_array($_POST['decision'] ?? null) ? $_POST['decision'] : array() as $ap_eid => $ap_word) {
                $ap_code = ($ap_word === 'none') ? 0 : pg_application_decision_code($ap_word);

                if (isset($ap_listed[(int) $ap_eid]) && (($ap_code > 0) || ($ap_word === 'none')) && ($ap_code !== $ap_listed[(int) $ap_eid]['decision'])) {
                    $ap_changes[$ap_code][] = (int) $ap_eid;
                }
            }
        }

        $ap_changed = 0;
        $ap_called = array();

        foreach ($ap_changes as $ap_code => $ap_eids) {
            $ap_result = pg_application_decide($ap_program, $ap_eids, $ap_code, (int) $user['id']);
            $ap_changed += $ap_result['changed'];
            $ap_called = array_merge($ap_called, $ap_result['called']);
        }

        if ($ap_changed > 0) {
            $liveform->add_notice(h(lang(array('string' => '{var:1} decisions were recorded. Their e-mails go when you send them.', 'vars' => array($ap_changed)))));
        } else {
            $liveform->add_error(h(lang('No decision changed. Tick entries first; a declined place cannot be given back.')));
        }

        if ($ap_called) {
            $liveform->add_notice(h(lang(array('string' => '{var:1} entries were called from the waitlist and are accepted.', 'vars' => array(count($ap_called))))));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_call'])) {
        if (pg_application_waitlist_call($ap_program, (int) $_POST['submit_call'], (int) $user['id']) > 0) {
            $liveform->add_notice(h(lang('The next on the waitlist is accepted. Their e-mail goes when you send the decision e-mails.')));
        } else {
            $liveform->add_error(h(lang('Nobody is waiting in this category, or the place was already given.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_send'])) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} decision e-mails were sent.', 'vars' => array(pg_application_mail_send($ap_program))))));
        go($ap_back);
    }

    if (isset($_POST['submit_cut'])) {
        $ap_cut = pg_application_cut_input($_POST['cut_mode'] ?? '', $_POST['cut_value'] ?? '');

        if ($ap_cut === false) {
            $liveform->add_error(h(lang('Write a cut-off score from 1 to 10, or a top number from 1.')));
        } else {
            $liveform->add_notice(h(lang(array('string' => 'The cut was applied: {var:1} entries are shortlisted.', 'vars' => array(pg_application_cut_apply($ap_program, $ap_cut))))));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_settle'])) {
        if (pg_application_consensus_settle($ap_program, (int) ($_POST['entry_id'] ?? 0), is_string($_POST['note'] ?? null) ? $_POST['note'] : '')) {
            $liveform->add_notice(h(lang('The entry was settled. Your note is kept in its history.')));
        } else {
            $liveform->add_error(h(lang('Write a note to settle the entry.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_let_in'])) {
        $ap_entry = _pg_application_rows("SELECT * FROM application_entries WHERE id = ? AND program_id = ?", array((int) ($_POST['entry_id'] ?? 0), $ap_id));
        $ap_entry_note = $ap_entry ? json_decode((string) $ap_entry[0]['eligibility_note'], true) : null;

        if ($ap_entry && pg_application_entry_override(array('note' => is_array($ap_entry_note) ? $ap_entry_note : array()) + $ap_entry[0], is_string($_POST['note'] ?? null) ? $_POST['note'] : '', (int) $user['id'])) {
            $liveform->add_notice(h(lang('The entry was let in. Give out entries to share it with the reviewers.')));
        } else {
            $liveform->add_error(h(lang('Write a note to let the entry in.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_next_round'])) {
        $ap_moved = pg_application_next_round($ap_program);

        if ($ap_moved > 0) {
            $liveform->add_notice(h(lang(array('string' => '{var:1} shortlisted entries moved to round {var:2}. Give out entries to share them with the round\'s reviewers.', 'vars' => array($ap_moved, $ap_program['round'] + 1)))));
            go($ap_self . '?id=' . $ap_id . '&view=reviewers');
        }

        $liveform->add_error(h(lang('The shortlist cannot move on: close the programme, set up the next round and shortlist entries first.')));
        go($ap_back);
    }

    if (isset($_POST['submit_reviewer_add'])) {
        $ap_reviewer = pg_application_reviewer_input($_POST, pg_application_category_choices($ap_program));

        if ($ap_reviewer['error'] !== '') {
            $liveform->add_error(h($ap_reviewer['error']));
            go($ap_back . '&add=1');
        }

        $ap_reviewer_id = pg_application_reviewer_add($ap_program, $ap_reviewer['user_id'], $ap_reviewer['name'], $ap_reviewer['email'], $ap_reviewer['categories']);

        if ($ap_reviewer_id) {
            $liveform->add_notice(h(lang('The reviewer was added. Give out entries to share the entries with them.')));

            // An outside juror gets their link at once; it opens their
            // entries as soon as they are given out.
            $ap_juror = ((int) $ap_reviewer['user_id'] === 0) ? pg_application_reviewer_get($ap_program, $ap_reviewer_id) : null;

            if ($ap_juror !== null) {
                $liveform->add_notice(h(pg_application_juror_send($ap_program, $ap_juror)
                    ? lang(array('string' => 'Their link was sent to {var:1}.', 'vars' => array($ap_juror['email'])))
                    : lang('Their link could not be sent: check the address, or copy the link from the row\'s menu.')));
            }
        } else {
            $liveform->add_error(h(lang('The reviewer could not be added.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_juror_send'])) {
        $ap_juror = pg_application_reviewer_get($ap_program, (int) $_POST['submit_juror_send']);

        if (($ap_juror !== null) && pg_application_juror_send($ap_program, $ap_juror)) {
            $liveform->add_notice(h(lang(array('string' => 'The link was sent again to {var:1}.', 'vars' => array($ap_juror['email'])))));
        } else {
            $liveform->add_error(h(lang('The link could not be sent.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_reviewer_remove'])) {
        if (pg_application_reviewer_remove($ap_program, (int) $_POST['submit_reviewer_remove'])) {
            $liveform->add_notice(h(lang('The reviewer was removed. Give out entries to pass their open entries to others.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_assign'])) {
        $ap_plan = pg_application_assignment_run($ap_program);
        $liveform->add_notice(h(lang(array('string' => 'Entries given out: {var:1} new reviews.', 'vars' => array(count($ap_plan['inserts']))))));

        if ($ap_plan['short']) {
            $liveform->add_error(h(lang(array('string' => '{var:1} entries still lack reviewers: add reviewers to their category.', 'vars' => array(count($ap_plan['short']))))));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_delete'])) {
        if (pg_application_program_delete($ap_program)) {
            log_activity('application programme "' . $ap_program['name'] . '" was deleted');
            $ap_list_form = new liveform('view_application_programs');
            $ap_list_form->add_notice(h(lang(array('string' => 'The programme "{var:1}" was deleted.', 'vars' => array($ap_program['name'])))));
            go($ap_list);
        }

        $ap_list_form = new liveform('view_application_programs');
        $ap_list_form->add_error(h(lang('Only a draft with no entries can be deleted.')));
        go($ap_list);
    }

    if (isset($_POST['submit_open']) && ($ap_program['status'] === 0)) {
        $ap_missing_words = array('period' => lang('Period'), 'criteria' => lang('Rounds and criteria'));
        $ap_missing = array_map(function ($code) use ($ap_missing_words) {
            return $ap_missing_words[$code];
        }, pg_application_program_missing($ap_program));

        if ($ap_missing) {
            $liveform->add_error(h(lang(array('string' => 'Set these first: {var:1}.', 'vars' => array(implode(', ', $ap_missing))))));
        } elseif (pg_application_program_form_taken($ap_program)) {
            $liveform->add_error(h(lang('Another programme on this form is open. Close it first.')));
        } else {
            pg_application_program_set_status($ap_program, 1);
            $liveform->add_notice(h(lang('The programme is open for entries.')));
        }

        go($ap_back);
    }

    if (isset($_POST['submit_close']) && ($ap_program['status'] === 1)) {
        pg_application_program_set_status($ap_program, 2);
        $liveform->add_notice(h(lang('The programme is closed: the form takes no new entries.')));
        go($ap_back);
    }

    $ap_input = pg_application_program_input($_POST, $ap_field_types);

    if (!$ap_input['errors']) {
        pg_application_program_save($ap_program, $ap_input['program'], $ap_fields);
        log_activity('application programme "' . $ap_input['program']['name'] . '" was modified');
        $ap_template_round = $_POST['submit_template'] ?? null;

        if (is_string($ap_template_round) && isset($ap_input['program']['rounds'][(int) $ap_template_round])) {
            pg_application_template_save($ap_input['program']['name'] . ' · ' . lang(array('string' => 'Round {var:1}', 'vars' => array((int) $ap_template_round + 1))), $ap_input['program']['rounds'][(int) $ap_template_round]['criteria']);
            $liveform->add_notice(h(lang('The programme was saved and the round\'s criteria were kept as a template.')));
        } else {
            $liveform->add_notice(h(lang('The programme was saved.')));
        }

        go($ap_back);
    }

    foreach ($ap_input['errors'] as $ap_error) {
        $liveform->add_error(h($ap_error));
    }
}

// A programme is added in the list's drawer.
if ($ap_program === null) {
    go($ap_list . '?add=1');
}

// What the form shows: the posted values after a refused save, else the
// stored programme.
if ($ap_input !== null) {
    $ap_values = $ap_input['program'];
} else {
    $ap_values = $ap_program;
    $ap_values['category_field'] = '';

    foreach ($ap_fields as $name => $field) {
        if ($field['id'] === $ap_program['category_field_id']) {
            $ap_values['category_field'] = $name;
        }
    }
}

$ap_texts = $ap_values['texts'] + array('outside' => '');
$ap_rounds = $ap_values['rounds'] ? $ap_values['rounds'] : array(array('criteria' => array(), 'reviewers' => 2, 'ends_at' => 0));
$ap_entries = (int) db_value("SELECT COUNT(*) FROM application_entries WHERE program_id = '" . $ap_id . "'");
$ap_date = function ($time) {
    return ($time > 0) ? (string) prepare_form_data_for_output(date('Y-m-d H:i:s', $time), 'date and time') : '';
};
$ap_when = function ($time) {
    return h(get_absolute_time(array('timestamp' => $time, 'format' => 'plain_text')));
};
$ap_not_set = '<span class="pg-pill pg-tone-gray">' . h(lang('Not set')) . '</span>';
$ap_label = function ($name) use ($ap_fields) {
    return ($ap_fields[$name]['label'] ?? '') !== '' ? $ap_fields[$name]['label'] : $name;
};
$ap_field_options = function ($chosen, $types = null) use ($ap_fields, $ap_label) {
    $out = '';

    foreach ($ap_fields as $name => $field) {
        if (($types === null) || in_array($field['type'], $types, true)) {
            $out .= '<option value="' . h($name) . '"' . (($name === $chosen) ? ' selected' : '') . '>' . h($ap_label($name)) . '</option>';
        }
    }

    return $out;
};

// A card of the Set-up tab: its name, a "Not set" pill when its values are
// missing, one line saying what is set, then its fields.
$ap_card = function ($key, $title, $line, $missing, $body) {
    return '<section class="pg-card p-5 mb-5" aria-labelledby="aprog_' . $key . '_title">'
        . '<div class="d-flex flex-wrap align-items-center gap-2"><h2 class="pg-title-section mb-0" id="aprog_' . $key . '_title">' . h($title) . '</h2>' . $missing . '</div>'
        . '<p class="pg-card-sub mb-5">' . h($line) . '</p>' . $body . '</section>';
};

// 1. Period.
$ap_period_line = (($ap_values['opens_at'] > 0) || ($ap_values['closes_at'] > 0))
    ? lang(array('string' => 'Opens {var:1}, closes {var:2}.', 'vars' => array(($ap_values['opens_at'] > 0) ? get_absolute_time(array('timestamp' => $ap_values['opens_at'], 'format' => 'plain_text')) : lang('when opened'), ($ap_values['closes_at'] > 0) ? get_absolute_time(array('timestamp' => $ap_values['closes_at'], 'format' => 'plain_text')) : lang('not set'))))
    : lang('No period yet: set when entries open and close.');
$ap_period = $ap_card('period', lang('Period'), $ap_period_line, ($ap_values['closes_at'] > 0) ? '' : $ap_not_set, '<div class="row gy-5">'
    . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_opens">' . h(lang('Opens')) . '</label><input type="text" maxlength="22" data-pg-datetime class="pg-input" id="aprog_opens" name="opens" value="' . $ap_date($ap_values['opens_at']) . '"></div>'
    . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_closes">' . h(lang('Closes')) . '</label><input type="text" maxlength="22" data-pg-datetime class="pg-input" id="aprog_closes" name="closes" value="' . $ap_date($ap_values['closes_at']) . '"></div>'
    . '<div class="col-12"><label class="pg-field-label" for="aprog_outside">' . h(lang('Text outside the period')) . '</label><textarea class="pg-input" id="aprog_outside" name="outside_text" rows="2" aria-describedby="aprog_outside_help">' . h($ap_texts['outside']) . '</textarea>'
    . '<div class="pg-field-help" id="aprog_outside_help">' . h(lang('Shown instead of saving when someone sends the form before it opens or after it closes.')) . '</div></div>'
    . '</div>');

// 2. Categories.
$ap_choice_types = array('pick list', 'radio button', 'check box');
$ap_categories = $ap_card('categories', lang('Categories'), ($ap_values['category_field'] !== '')
    ? lang(array('string' => 'Entries are grouped by the answer to "{var:1}".', 'vars' => array($ap_label($ap_values['category_field']))))
    : lang('No categories: every entry is in one pool.'), '', '<label class="pg-field-label" for="aprog_category">' . h(lang('Choice field')) . '</label>'
    . '<select class="pg-input" id="aprog_category" name="category_field"><option value="">' . h(lang('No categories')) . '</option>' . $ap_field_options($ap_values['category_field'], $ap_choice_types) . '</select>');

// 3. Eligibility: a rule a row.
$ap_rule_row = function (array $rule) use ($ap_field_options) {
    $type = $rule['type'] ?? 'file';
    $types = array('file' => lang('has a file'), 'range' => lang('is a number between'), 'choice' => lang('is one of'));
    $type_options = '';

    foreach ($types as $value => $label) {
        $type_options .= '<option value="' . $value . '"' . (($value === $type) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return '<div class="pg-card-inner-row flex-wrap" data-pg-ap-rule>'
        . '<select class="pg-input w-auto" name="rule_field[]" aria-label="' . h(lang('Field')) . '">' . $ap_field_options($rule['field'] ?? '') . '</select>'
        . '<select class="pg-input w-auto" name="rule_type[]" aria-label="' . h(lang('Condition')) . '">' . $type_options . '</select>'
        . '<input type="text" class="pg-input w-auto flex-grow-1" name="rule_value[]" value="' . h(isset($rule['type']) ? pg_application_rule_value_text($rule) : '') . '" aria-label="' . h(lang('Value')) . '" placeholder="' . h(lang('18-30, or Ankara, İzmir')) . '">'
        . '<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-ap-remove aria-label="' . h(lang('Remove')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>'
        . '</div>';
};
$ap_rules = '';

foreach ($ap_values['eligibility'] as $rule) {
    $ap_rules .= $ap_rule_row($rule);
}

$ap_eligibility = $ap_card('eligibility', lang('Eligibility'), $ap_values['eligibility']
    ? lang(array('string' => '{var:1} rules. An entry that fails one is marked not eligible and gets no reviewers; it is still saved.', 'vars' => array(count($ap_values['eligibility']))))
    : lang('No rules: every entry is eligible.'), '', '<div class="pg-card-inner mb-3" data-pg-ap-list="rule">' . $ap_rules . '</div>'
    . '<template data-pg-ap-template="rule">' . $ap_rule_row(array()) . '</template>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-add="rule"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a rule')) . '</button>');

// 4. Rounds and criteria: a round a card, its criteria as rows.
$ap_criterion_row = function ($r, array $criterion) {
    return '<div class="pg-card-inner-row" data-pg-ap-criterion>'
        . '<input type="hidden" name="rounds[' . $r . '][cid][]" value="' . h($criterion['id'] ?? '') . '">'
        . '<input type="text" class="pg-input flex-grow-1" name="rounds[' . $r . '][cname][]" maxlength="100" value="' . h($criterion['name'] ?? '') . '" aria-label="' . h(lang('Criterion')) . '" placeholder="' . h(lang('Criterion')) . '">'
        . '<span class="pg-input w-auto flex-shrink-0"><input type="number" min="1" max="100" step="1" name="rounds[' . $r . '][cweight][]" value="' . h(isset($criterion['weight']) ? (string) $criterion['weight'] : '') . '" aria-label="' . h(lang('Weight')) . '" data-pg-ap-weight><span class="pg-input-end">%</span></span>'
        . '<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-ap-remove aria-label="' . h(lang('Remove')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>'
        . '</div>';
};
$ap_round_card = function ($r, array $round) use ($ap_criterion_row, $ap_date) {
    $rows = '';

    foreach ($round['criteria'] as $criterion) {
        $rows .= $ap_criterion_row($r, $criterion);
    }

    $sum = pg_application_weights_sum($round['criteria']);
    $number = is_int($r) ? (string) ($r + 1) : '';

    return '<div class="pg-card-inner p-4 mb-4" data-pg-ap-round="' . $r . '">'
        . '<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><strong class="pg-list-row-title flex-grow-1">' . h(lang('Round')) . ' <span data-pg-ap-round-number>' . $number . '</span></strong>'
        . '<span class="pg-pill ' . (($sum === 100) ? 'pg-tone-teal' : 'pg-tone-amber') . '" data-pg-ap-sum>' . h(lang('Weights')) . ' <span class="pg-tabular">' . $sum . '</span> / 100</span>'
        . (($r !== 0) ? '<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-ap-remove-round aria-label="' . h(lang('Remove the round')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>' : '') . '</div>'
        . '<div data-pg-ap-list="criterion">' . $rows . '</div>'
        . '<template data-pg-ap-template="criterion">' . $ap_criterion_row($r, array()) . '</template>'
        . '<div class="d-flex flex-wrap align-items-center gap-2 my-3">'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-add="criterion"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a criterion')) . '</button>'
        . '<select class="pg-input w-auto" data-pg-ap-template-choice aria-label="' . h(lang('Template')) . '"></select>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-copy>' . h(lang('Copy from a template')) . '</button>'
        . (is_int($r) ? '<button type="submit" class="pg-button pg-button-quiet pg-button-sm" name="submit_template" value="' . $r . '">' . h(lang('Save as a template')) . '</button>' : '')
        . '</div><div class="row gy-5">'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_reviewers_' . $r . '">' . h(lang('Reviewers per entry')) . '</label><input type="number" class="pg-input" min="1" max="10" id="aprog_reviewers_' . $r . '" name="rounds[' . $r . '][reviewers]" value="' . (int) $round['reviewers'] . '"></div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_ends_' . $r . '">' . h(lang('Round ends')) . '</label><input type="text" maxlength="22" data-pg-datetime class="pg-input" id="aprog_ends_' . $r . '" name="rounds[' . $r . '][ends]" value="' . $ap_date((int) $round['ends_at']) . '"></div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_spread_' . $r . '">' . h(lang('Needs consensus above')) . '</label><input type="text" inputmode="decimal" maxlength="4" class="pg-input" id="aprog_spread_' . $r . '" name="rounds[' . $r . '][spread]" value="' . h(isset($round['spread']) ? (string) $round['spread'] : '') . '" aria-describedby="aprog_spread_help_' . $r . '">'
        . '<div class="pg-field-help" id="aprog_spread_help_' . $r . '">' . h(lang('Points between the highest and lowest score of an entry; empty: never.')) . '</div></div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="aprog_rank_' . $r . '">' . h(lang('Rank by')) . '</label><select class="pg-input" id="aprog_rank_' . $r . '" name="rounds[' . $r . '][rank_by]" aria-describedby="aprog_rank_help_' . $r . '">'
        . '<option value="raw">' . h(lang('Mean')) . '</option><option value="corrected"' . ((($round['rank_by'] ?? '') === 'corrected') ? ' selected' : '') . '>' . h(lang('Corrected mean')) . '</option></select>'
        . '<div class="pg-field-help" id="aprog_rank_help_' . $r . '">' . h(lang('The corrected mean evens out strict and generous reviewers, from their fifth review.')) . '</div></div>'
        . '</div></div>';
};
$ap_round_cards = '';
$ap_criteria_ok = !in_array('criteria', pg_application_program_missing($ap_values), true);

foreach (array_values($ap_rounds) as $r => $round) {
    $ap_round_cards .= $ap_round_card($r, $round);
}

$ap_templates = array_map(function ($template) {
    return array('name' => $template['name'], 'criteria' => $template['criteria']);
}, pg_application_templates());
$ap_rounds_html = $ap_card('rounds', lang('Rounds and criteria'), $ap_criteria_ok
    ? lang(array('string' => '{var:1} rounds; each entry is scored 1 to 10 on every criterion.', 'vars' => array(count($ap_values['rounds']))))
    : lang('Each round needs criteria whose weights add up to 100.'), $ap_criteria_ok ? '' : $ap_not_set, '<div data-pg-ap-list="round" data-pg-ap-templates="' . h(json_encode($ap_templates, JSON_UNESCAPED_UNICODE)) . '">' . $ap_round_cards . '</div>'
    . '<template data-pg-ap-template="round">' . $ap_round_card('__R__', array('criteria' => array(), 'reviewers' => 2, 'ends_at' => 0)) . '</template>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-add="round"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a round')) . '</button>');

// 5. Blind review.
$ap_blind_boxes = '';

foreach ($ap_fields as $name => $field) {
    $ap_blind_boxes .= '<span class="d-inline-flex align-items-center gap-2 me-4"><input type="checkbox" class="pg-checkbox" id="aprog_blind_' . $field['id'] . '" name="blind_fields[]" value="' . h($name) . '"' . (in_array($name, $ap_values['blind_fields'], true) ? ' checked' : '') . '><label class="pg-text-2" for="aprog_blind_' . $field['id'] . '">' . h($ap_label($name)) . '</label></span>';
}

$ap_blind = $ap_card('blind', lang('Blind review'), $ap_values['blind'] ? lang('On: reviewers do not see who applied.') : lang('Off: reviewers see the whole entry.'), '',
    '<div class="d-flex align-items-center gap-3 mb-4"><input type="checkbox" class="pg-switch" id="aprog_blind" name="blind" value="1"' . ($ap_values['blind'] ? ' checked' : '') . '><label class="pg-text-2" for="aprog_blind">' . h(lang('Hide who applied from reviewers')) . '</label></div>'
    . '<div class="pg-field-label">' . h(lang('Also hide these fields')) . '</div><div class="mb-3">' . $ap_blind_boxes . '</div>'
    . '<div class="pg-field-help">' . h(lang('The name, e-mail, reference code and contact fields are always hidden. Uploaded files show as uploaded: blind review covers fields only.')) . '</div>');

// 6. Decision e-mails: a tab each.
$ap_decisions = array('accepted' => lang('Accepted'), 'rejected' => lang('Rejected'), 'waitlisted' => lang('Waitlisted'));
$ap_decision_tabs = '';
$ap_decision_panes = '';
$ap_written = 0;

foreach ($ap_decisions as $key => $label) {
    $text = $ap_texts[$key] ?? array('subject' => '', 'body' => '');
    $ap_written += (($text['subject'] !== '') && ($text['body'] !== '')) ? 1 : 0;
    $ap_decision_tabs .= '<button type="button" class="pg-seg-item" aria-pressed="' . (($key === 'accepted') ? 'true' : 'false') . '" data-pg-ap-tab="' . $key . '">' . h($label) . '</button>';
    $ap_decision_panes .= '<div class="d-flex flex-column gap-4" data-pg-ap-pane="' . $key . '"' . (($key === 'accepted') ? '' : ' hidden') . '>'
        . '<div><label class="pg-field-label" for="aprog_' . $key . '_subject">' . h(lang('Subject')) . '</label><input type="text" class="pg-input" id="aprog_' . $key . '_subject" name="texts[' . $key . '][subject]" maxlength="200" value="' . h($text['subject']) . '"></div>'
        . '<div><label class="pg-field-label" for="aprog_' . $key . '_body">' . h(lang('Text')) . '</label><textarea class="pg-input" id="aprog_' . $key . '_body" name="texts[' . $key . '][body]" rows="6">' . h($text['body']) . '</textarea></div>'
        . '</div>';
}

$ap_mails = $ap_card('mails', lang('Decision e-mails'), ($ap_written === 3)
    ? lang('All three are written. They go only when you send them, after a preview.')
    : lang(array('string' => '{var:1} of 3 written. They go only when you send them, after a preview.', 'vars' => array($ap_written))), ($ap_written === 3) ? '' : $ap_not_set,
    '<span class="pg-seg mb-4" role="group" aria-label="' . h(lang('Decision')) . '">' . $ap_decision_tabs . '</span>' . $ap_decision_panes
    . '<div class="pg-field-help mt-3">' . h(lang('Write ^^field^^ to put in an answer of the entry, for example its name.')) . '</div>'
    . '<div class="d-flex align-items-center gap-3 mt-5"><input type="checkbox" class="pg-switch" id="aprog_waitlist_auto" name="waitlist_auto" value="1"' . (!empty($ap_values['waitlist_auto']) ? ' checked' : '') . '><label class="pg-text-2" for="aprog_waitlist_auto">' . h(lang('When an accepted applicant declines, accept the next on the waitlist by itself')) . '</label></div>'
    . '<div class="pg-field-help">' . h(lang('Off: the Decisions tab offers "Call the next" on the declined entry.')) . '</div>');

// The head: the status and the one button that follows it.
$ap_status_pill = array(
    0 => '<span class="pg-pill pg-tone-gray">' . h(lang('Draft')) . '</span>',
    1 => '<span class="pg-pill pg-tone-teal">' . h(lang('Open')) . '</span>',
    2 => '<span class="pg-pill pg-tone-gray">' . h(lang('Closed')) . '</span>',
);
$ap_state_button = '';

if ($ap_program['status'] === 0) {
    $ap_state_button = '<button type="submit" form="aprog_state_form" name="submit_open" value="1" class="pg-button pg-button-quiet">' . h(lang('Open for entries')) . '</button>';
} elseif ($ap_program['status'] === 1) {
    $ap_state_button = '<button type="submit" form="aprog_state_form" name="submit_close" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('The form stops taking entries. Drafts saved for later can no longer be finished.')) . '">' . h(lang('Close')) . '</button>';
} elseif (isset($ap_program['rounds'][$ap_program['round']])) {
    // Closed with a next round set up: the round's own button.
    $ap_next_shortlist = (int) db_value("SELECT COUNT(*) FROM application_entries WHERE program_id = '" . $ap_id . "' AND round = '" . max(1, $ap_program['round']) . "' AND shortlisted = 1");
    $ap_state_button = '<button type="submit" form="aprog_state_form" name="submit_next_round" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang(array('string' => 'The {var:1} shortlisted entries move to round {var:2}, to be scored on its criteria by its reviewers. The others stay where they are.', 'vars' => array($ap_next_shortlist, max(1, $ap_program['round']) + 1)))) . '"'
        . (pg_application_next_round_ready($ap_program, $ap_next_shortlist) ? '' : ' disabled') . '>' . h(lang(array('string' => 'Start round {var:1}', 'vars' => array(max(1, $ap_program['round']) + 1)))) . '</button>';
}

// The Reviewers tab: the people as rows, "Add reviewer" in a drawer, the
// run that gives the entries out, the conflicts gathered under the list.
$ap_reviewers = pg_application_reviewers($ap_program);
$ap_choices = pg_application_category_choices($ap_program);
$ap_initials = function ($name) {
    $letters = '';

    foreach (array_slice(preg_split('/[\s(]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY), 0, 2) as $word) {
        $letters .= mb_strtoupper(mb_substr($word, 0, 1));
    }

    return ($letters !== '') ? $letters : '?';
};
$ap_reviewer_rows = '';
// An outside juror's row menu: their link copied, or mailed again.
$ap_juror_items = function (array $reviewer) {
    $url = ((int) $reviewer['user_id'] === 0) ? pg_application_juror_url($reviewer) : '';

    if ($url === '') {
        return '';
    }

    return '<li><button type="button" class="menu-item" data-pg-copy="' . h($url) . '"><i class="bi bi-copy me-3" aria-hidden="true"></i>' . h(lang('Copy the link')) . '</button>'
        . '<span class="visually-hidden" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span></li>'
        . '<li><button type="submit" form="aprog_reviewers_form" name="submit_juror_send" value="' . (int) $reviewer['id'] . '" class="menu-item"><i class="bi bi-envelope me-3" aria-hidden="true"></i>' . h(lang('Send the link again')) . '</button></li>';
};

foreach ($ap_reviewers as $i => $reviewer) {
    $pool = ($reviewer['categories'] === null) ? lang('Every category') : implode(', ', array_map(function ($value) use ($ap_choices) {
        return $ap_choices[$value] ?? $value;
    }, $reviewer['categories']));
    $ap_reviewer_rows .= '<div class="pg-card-inner-row" data-pg-ap-reviewer>'
        . '<span class="pg-initials' . (($i % 3) ? ' pg-initials-' . (($i % 3) + 1) : '') . '" aria-hidden="true">' . h($ap_initials($reviewer['display'])) . '</span>'
        . '<div class="flex-grow-1 min-width-0"><strong class="pg-list-row-title">' . h($reviewer['display']) . '</strong>'
        . '<div class="pg-text-meta">' . h(((int) $reviewer['user_id'] > 0) ? lang('Panel user') : lang('Outside juror') . ' · ' . $reviewer['email']) . ($ap_choices ? ' · ' . h($pool) : '') . '</div></div>'
        . '<span class="pg-text-2 pg-tabular text-nowrap">' . h(lang(array('string' => '{var:1} of {var:2} sent', 'vars' => array((int) $reviewer['done'], (int) $reviewer['assigned'])))) . '</span>'
        . '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
        . '<ul class="menu pg-menu-end">' . $ap_juror_items($reviewer) . '<li><button type="submit" form="aprog_reviewers_form" name="submit_reviewer_remove" value="' . (int) $reviewer['id'] . '" class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('Entries they have not sent go back to be given out again. Reviews they sent stay.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Remove')) . '</button></li></ul></span>'
        . '</div>';
}

$ap_conflict_lines = '';

foreach (pg_application_conflicts($ap_program) as $conflict) {
    $ap_conflict_lines .= '<div class="pg-gather-line"><b>' . h($conflict['display']) . '</b>'
        . '<span><a href="edit_submitted_form.php?id=' . (int) $conflict['form_id'] . '">' . h(((string) $conflict['reference_code'] !== '') ? $conflict['reference_code'] : '#' . (int) $conflict['form_id']) . '</a> · ' . h($conflict['conflict_reason']) . '</span>'
        . '<span class="pg-gather-line-end">' . h(lang(array('string' => 'Round {var:1}', 'vars' => array((int) $conflict['round'])))) . '</span></div>';
}

$ap_plan = pg_application_assignment_read($ap_program, false);
$ap_assign_text = ($ap_plan['entries'] > 0)
    ? lang(array('string' => '{var:1} entries go to {var:2} reviewers. Reviews already started or sent are kept.', 'vars' => array($ap_plan['entries'], $ap_plan['reviewers'])))
    : lang('No entry needs a new reviewer now.');

if ($ap_plan['short']) {
    $ap_assign_text .= ' ' . lang(array('string' => '{var:1} entries cannot get all their reviewers: their category has too few.', 'vars' => array(count($ap_plan['short']))));
}

$ap_category_boxes = '';

foreach ($ap_choices as $value => $label) {
    $ap_category_boxes .= '<span class="d-inline-flex align-items-center gap-2 me-4"><input type="checkbox" class="pg-checkbox" id="aprog_cat_' . md5($value) . '" name="reviewer_categories[]" value="' . h($value) . '"><label class="pg-text-2" for="aprog_cat_' . md5($value) . '">' . h($label) . '</label></span>';
}

// A manager or above finds the account as they type (user_search_action.php);
// a forms manager who cannot list users types the username.
$ap_user_field = pg_user_picker_may_search($user['role'])
    ? '<select class="pg-input" id="aprog_reviewer_user" name="reviewer_user_id" data-pg-combobox data-pg-source="user_search_action.php" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a user')) . '"><option value=""></option></select>'
    : '<input type="text" class="pg-input" id="aprog_reviewer_user" name="reviewer_username" maxlength="100" required>';

$ap_reviewers_html = '<form id="aprog_reviewers_form" action="edit_application_program.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="reviewers"></form>'
    . '<section class="pg-card p-5 mb-5" aria-labelledby="aprog_reviewers_title"><h2 class="pg-title-section mb-0" id="aprog_reviewers_title">' . h(lang('Reviewers')) . '</h2>'
    . '<p class="pg-card-sub mb-5">' . h($ap_reviewers ? lang('Each entry goes to reviewers of its category; the fewest given out first.') : lang('No reviewers yet. Add the people who score the entries.')) . '</p>'
    . ($ap_reviewers ? '<div class="pg-card-inner">' . $ap_reviewer_rows . '</div>' : '') . '</section>'
    . (($ap_conflict_lines !== '') ? '<section class="pg-list-row mb-5" aria-labelledby="aprog_conflicts_title"><span class="pg-icon-tile pg-tone-amber" aria-hidden="true"><span class="bi bi-person-exclamation"></span></span><div class="pg-list-row-main">'
        . '<div class="pg-list-row-head"><h2 class="pg-list-row-title mb-0" id="aprog_conflicts_title">' . h(lang('Conflicts')) . '<span class="pg-count">' . substr_count($ap_conflict_lines, 'pg-gather-line"') . '</span></h2></div>'
        . '<div class="pg-list-row-text">' . h(lang('Reviewers who know an applicant; the entry goes to another reviewer.')) . '</div>'
        . '<div class="pg-gather">' . $ap_conflict_lines . '</div></div></section>' : '')
    . '<dialog class="drawer drawer-end" id="aprog_reviewer_add" aria-labelledby="aprog_reviewer_add_title" style="width:min(480px,100%)"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_application_program.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="reviewers"><input type="hidden" name="reviewer_kind" value="user" data-pg-ap-kind>
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="aprog_reviewer_add_title">' . h(lang('Add reviewer')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('A panel user signs in to score; an outside juror needs no sign-in.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <span class="pg-seg mb-5" role="group" aria-label="' . h(lang('Reviewer')) . '"><button type="button" class="pg-seg-item" aria-pressed="true" data-pg-ap-kind-choice="user">' . h(lang('Panel user')) . '</button><button type="button" class="pg-seg-item" aria-pressed="false" data-pg-ap-kind-choice="juror">' . h(lang('Outside juror')) . '</button></span>
                <div data-pg-ap-kind-pane="user"><div class="form-field mb-5"><label class="form-label pg-field-label" for="aprog_reviewer_user">' . h(lang('User')) . '</label>' . $ap_user_field . '</div></div>
                <div data-pg-ap-kind-pane="juror" hidden>
                    <div class="form-field mb-5"><label class="form-label pg-field-label" for="aprog_reviewer_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="aprog_reviewer_name" name="reviewer_name" maxlength="150" required disabled></div>
                    <div class="form-field mb-5"><label class="form-label pg-field-label" for="aprog_reviewer_email">' . h(lang('E-mail')) . '</label><input type="email" class="pg-input" id="aprog_reviewer_email" name="reviewer_email" maxlength="190" required disabled></div>
                </div>
                ' . (($ap_category_boxes !== '') ? '<div class="pg-field-label">' . h(lang('Categories')) . '</div><div class="mb-3">' . $ap_category_boxes . '</div><div class="pg-field-help">' . h(lang('Leave all unticked to review every category.')) . '</div>' : '') . '
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_reviewer_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';

// The current round's entries, counted on the Entries tab.
$ap_round = max(1, $ap_program['round']);
$ap_round_entries = (int) db_value("SELECT COUNT(*) FROM application_entries WHERE program_id = '" . $ap_id . "' AND round = '" . $ap_round . "'");

$ap_tab = function ($view, $label, $count = null) use ($ap_id, $ap_view) {
    return '<a class="pg-seg-item' . (($ap_view === $view) ? ' is-active" aria-current="page' : '') . '" href="edit_application_program.php?id=' . $ap_id . (($view === 'setup') ? '' : '&amp;view=' . $view) . '">' . h($label) . (($count !== null) ? ' <span class="pg-seg-count">' . (int) $count . '</span>' : '') . '</a>';
};
$ap_toolbar = array(
    'primary' => ($ap_view === 'reviewers')
        ? array('<button type="button" class="pg-button pg-button-fill" data-pg-ap-drawer="aprog_reviewer_add"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add reviewer')) . '</button>', $ap_state_button)
        : $ap_state_button,
    'view' => '<span class="pg-seg" role="group" aria-label="' . h(lang('Programme')) . '">' . $ap_tab('setup', lang('Set-up')) . $ap_tab('reviewers', lang('Reviewers'), count($ap_reviewers)) . $ap_tab('entries', lang('Entries'), $ap_round_entries) . $ap_tab('decisions', lang('Decisions')) . $ap_tab('report', lang('Report')) . '</span>',
);

if ($ap_view === 'reviewers') {
    $ap_toolbar['secondary'] = '<button type="submit" form="aprog_reviewers_form" name="submit_assign" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h($ap_assign_text) . '"' . (($ap_reviewers && $ap_entries) ? '' : ' disabled') . '><i class="bi bi-shuffle" aria-hidden="true"></i>' . h(lang('Give out entries')) . '</button>';
}

$ap_setup_html = '<form id="aprog_form" action="edit_application_program.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . $ap_id . '">
        <section class="pg-card p-5 mb-5" aria-labelledby="aprog_name_title"><h2 class="pg-title-section mb-0" id="aprog_name_title">' . h(lang('Programme')) . '</h2><p class="pg-card-sub mb-5">' . h(lang('The name staff and reviewers see.')) . '</p>
        <label class="pg-field-label" for="aprog_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="aprog_name" name="name" maxlength="150" required value="' . h($ap_values['name']) . '"></section>
        ' . $ap_period . $ap_categories . $ap_eligibility . $ap_rounds_html . $ap_blind . $ap_mails . '
        <div class="pg-floatbar d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 mb-5">
            <span class="d-flex flex-wrap gap-2"><button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($ap_list) . '">' . h(lang('Cancel')) . '</a></span>
        </div>
    </form>';

// The Entries tab: the current round's entries ranked per category, the cut
// line across the list, and the drawers that settle or let an entry in.
$ap_entries_html = '';

if ($ap_view === 'entries') {
    $ap_settings = pg_application_round_settings($ap_program['rounds'][$ap_round - 1] ?? array());
    $ap_ranked_entries = pg_application_scores_refresh($ap_program);
    $ap_ranking = pg_application_ranking($ap_ranked_entries, $ap_settings['rank_by'], $ap_settings['cut_mode'], $ap_settings['cut_value']);

    foreach (array_keys($ap_choices) as $ap_value) {
        $ap_ranking += array((string) $ap_value => array('ranked' => array(), 'shortlist' => array()));
    }

    ksort($ap_ranking, SORT_STRING);
    $ap_category = (($ap_category !== null) && isset($ap_ranking[$ap_category])) ? $ap_category : (string) key($ap_ranking);
    $ap_shortlisted = count(array_filter($ap_ranked_entries, function ($entry) {
        return $entry['shortlisted'] === 1;
    }));
    $ap_score = function ($value) {
        return ($value === null) ? '<span class="pg-text-meta">—</span>' : h(number_format((float) $value, 2));
    };
    $ap_group = $ap_ranking[$ap_category] ?? array('ranked' => array(), 'shortlist' => array());
    $ap_rest = array();

    foreach ($ap_ranked_entries as $ap_eid => $ap_entry) {
        if ((string) $ap_entry['category'] === $ap_category && !in_array($ap_eid, $ap_group['ranked'], true)) {
            $ap_rest[] = $ap_eid;
        }
    }

    $ap_per_entry = (int) ($ap_program['rounds'][$ap_round - 1]['reviewers'] ?? $ap_program['reviewers_per_entry']);
    $ap_cut_line = ($ap_settings['cut_mode'] !== '') ? count($ap_group['shortlist']) : -1;
    $ap_stale = false;
    $ap_rows = '';

    foreach (array_merge($ap_group['ranked'], $ap_rest) as $ap_i => $ap_eid) {
        $ap_entry = $ap_ranked_entries[$ap_eid];
        $ap_ranked = $ap_i < count($ap_group['ranked']);
        $ap_stale = $ap_stale || ($ap_settings['cut_mode'] !== '' && ($ap_entry['shortlisted'] === 1) !== in_array($ap_eid, $ap_group['shortlist'], true));
        $ap_ref = ((string) $ap_entry['reference_code'] !== '') ? (string) $ap_entry['reference_code'] : '#' . (int) $ap_entry['form_id'];
        $ap_pills = (($ap_entry['eligible'] === 2) ? '<span class="pg-pill pg-tone-amber">' . h(lang('Not eligible')) . '</span>' : '')
            . (($ap_entry['consensus'] === 1) ? '<span class="pg-pill pg-tone-amber">' . h(lang('Needs consensus')) . '</span>' : '')
            . (($ap_entry['consensus'] === 2) ? '<span class="pg-pill pg-tone-gray">' . h(lang('Settled')) . '</span>' : '')
            . (($ap_entry['shortlisted'] === 1) ? '<span class="pg-pill pg-tone-teal">' . h(lang('Shortlisted')) . '</span>' : '');
        $ap_actions = '<a class="pg-button pg-button-quiet pg-button-sm" href="edit_submitted_form.php?id=' . (int) $ap_entry['form_id'] . '">' . h(lang('Open')) . '</a>'
            . (($ap_entry['eligible'] === 2) ? '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-note="aprog_let_in" data-pg-ap-entry="' . (int) $ap_eid . '" data-pg-ap-entry-name="' . h($ap_ref) . '">' . h(lang('Let it in')) . '</button>' : '')
            . (($ap_entry['consensus'] === 1) ? '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-ap-note="aprog_settle" data-pg-ap-entry="' . (int) $ap_eid . '" data-pg-ap-entry-name="' . h($ap_ref) . '">' . h(lang('Settle')) . '</button>' : '');

        if ($ap_i === $ap_cut_line) {
            $ap_rows .= '<tr><td colspan="8" class="py-2"><span class="pg-pill pg-tone-teal">' . h(lang('Cut-off')) . '</span></td></tr>';
        }

        $ap_rows .= '<tr><td class="pg-tabular">' . ($ap_ranked ? ($ap_i + 1) : '<span class="pg-text-meta">—</span>') . '</td>'
            . '<td><a href="edit_submitted_form.php?id=' . (int) $ap_entry['form_id'] . '">' . h($ap_ref) . '</a></td>'
            . '<td class="pg-tabular text-nowrap">' . h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($ap_entry['done'], $ap_per_entry)))) . '</td>'
            . '<td class="pg-tabular text-end">' . $ap_score($ap_entry['raw']) . '</td>'
            . '<td class="pg-tabular text-end">' . $ap_score($ap_entry['corrected']) . '</td>'
            . '<td class="pg-tabular text-end">' . (($ap_entry['done'] > 1) ? $ap_score($ap_entry['spread']) : $ap_score(null)) . '</td>'
            . '<td><span class="d-inline-flex flex-wrap gap-1">' . $ap_pills . '</span></td>'
            . '<td class="text-end text-nowrap">' . $ap_actions . '</td></tr>';
    }

    if (($ap_cut_line > 0) && ($ap_cut_line === count($ap_group['ranked'])) && !$ap_rest) {
        $ap_rows .= '<tr><td colspan="8" class="py-2"><span class="pg-pill pg-tone-teal">' . h(lang('Cut-off')) . '</span></td></tr>';
    }

    if ($ap_rows === '') {
        $ap_rows = '<tr><td colspan="8" class="text-center pg-text-meta py-7">' . h(lang('No entries in this round yet.')) . '</td></tr>';
    }

    $ap_category_tabs = '';

    if (count($ap_ranking) > 1 || ($ap_category !== '')) {
        foreach (array_keys($ap_ranking) as $ap_value) {
            $ap_value = (string) $ap_value;
            $ap_category_tabs .= '<a class="pg-seg-item' . (($ap_value === $ap_category) ? ' is-active" aria-current="page' : '') . '" href="edit_application_program.php?id=' . $ap_id . '&amp;view=entries&amp;category=' . h(rawurlencode($ap_value)) . '">' . h($ap_choices[$ap_value] ?? (($ap_value !== '') ? $ap_value : lang('No category'))) . '</a>';
        }

        $ap_category_tabs = '<span class="pg-seg mb-5" role="group" aria-label="' . h(lang('Categories')) . '">' . $ap_category_tabs . '</span>';
    }

    $ap_note_drawer = function ($id, $title, $line, $button, $submit) use ($ap_id, $ap_category) {
        return '<dialog class="drawer drawer-end" id="' . $id . '" aria-labelledby="' . $id . '_title" style="width:min(480px,100%)">
        <form method="post" action="edit_application_program.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="entries"><input type="hidden" name="category" value="' . h($ap_category) . '"><input type="hidden" name="entry_id" value="" data-pg-ap-note-entry>
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="' . $id . '_title">' . h($title) . ' <span data-pg-ap-note-name></span></h5>
                    <p class="small fg-2 mb-0 mt-1">' . h($line) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="' . $id . '_note">' . h(lang('Note')) . '</label><textarea class="pg-input" id="' . $id . '_note" name="note" rows="4" maxlength="1000" required></textarea></div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="' . $submit . '" value="1" class="pg-button pg-button-fill">' . h($button) . '</button>
            </div>
        </form>
    </dialog>';
    };

    $ap_cut_mode = ($ap_settings['cut_mode'] !== '') ? $ap_settings['cut_mode'] : 'cutoff';
    $ap_entries_html = '<section class="pg-card p-5 mb-5" aria-labelledby="aprog_cut_title"><h2 class="pg-title-section mb-0" id="aprog_cut_title">' . h(lang(array('string' => 'Round {var:1}', 'vars' => array($ap_round)))) . '</h2>'
        . '<p class="pg-card-sub mb-5">' . h(($ap_settings['rank_by'] === 'corrected')
            ? lang(array('string' => 'Ranked by the corrected mean in each category. {var:1} entries are shortlisted.', 'vars' => array($ap_shortlisted)))
            : lang(array('string' => 'Ranked by the mean in each category. {var:1} entries are shortlisted.', 'vars' => array($ap_shortlisted)))) . '</p>'
        . '<form method="post" action="edit_application_program.php" class="d-flex flex-wrap align-items-end gap-3">' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="entries"><input type="hidden" name="category" value="' . h($ap_category) . '">'
        . '<div><label class="pg-field-label" for="aprog_cut_mode">' . h(lang('Shortlist by')) . '</label><select class="pg-input" id="aprog_cut_mode" name="cut_mode"><option value="cutoff">' . h(lang('A cut-off score')) . '</option><option value="top"' . (($ap_cut_mode === 'top') ? ' selected' : '') . '>' . h(lang('The top number of each category')) . '</option></select></div>'
        . '<div><label class="pg-field-label" for="aprog_cut_value">' . h(lang('Value')) . '</label><input type="text" inputmode="decimal" maxlength="6" class="pg-input" id="aprog_cut_value" name="cut_value" required value="' . h(($ap_settings['cut_mode'] !== '') ? (string) $ap_settings['cut_value'] : '') . '"></div>'
        . '<button type="submit" name="submit_cut" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('Entries at or above the cut in each category are shortlisted; the others are taken off the shortlist. A tie at the cut is never split.')) . '">' . h(lang('Apply the cut')) . '</button>'
        . '</form>'
        . ($ap_stale ? '<p class="pg-field-help mt-3 mb-0">' . h(lang('Scores changed since the cut was applied: apply it again to update the shortlist.')) . '</p>' : '')
        . '</section>'
        . $ap_category_tabs
        . '<section class="pg-card p-0 mb-5 table-responsive" aria-label="' . h(lang('Entries')) . '"><table class="table table-hover align-middle mb-0">'
        . '<thead><tr><th scope="col">' . h(lang('Rank')) . '</th><th scope="col">' . h(lang('Entry')) . '</th><th scope="col">' . h(lang('Reviews sent')) . '</th><th scope="col" class="text-end">' . h(lang('Mean')) . '</th><th scope="col" class="text-end">' . h(lang('Corrected mean')) . '</th><th scope="col" class="text-end">' . h(lang('Spread')) . '</th><th scope="col">' . h(lang('Status')) . '</th><th scope="col"><span class="visually-hidden">' . h(lang('Actions')) . '</span></th></tr></thead>'
        . '<tbody>' . $ap_rows . '</tbody></table></section>'
        . $ap_note_drawer('aprog_settle', lang('Settle'), lang('The reviewers\' scores lie far apart. Say how it was agreed; the scores stay as sent.'), lang('Settle'), 'submit_settle')
        . $ap_note_drawer('aprog_let_in', lang('Let it in'), lang('The entry failed an eligibility rule. Say why it may be reviewed anyway.'), lang('Let it in'), 'submit_let_in');
}

// The Decisions tab: the list with a decision a row and the buttons for the
// ticked rows, then the drawer that previews and sends the decision e-mails.
$ap_decisions_html = '';

if ($ap_view === 'decisions') {
    $ap_drows = pg_application_decision_rows($ap_program, $ap_filter);
    $ap_queue = pg_application_mail_queue($ap_program);
    $ap_counts = pg_application_mail_counts($ap_queue, $ap_program['texts']);
    $ap_sendable = pg_application_mail_sendable($ap_counts);
    $ap_totals = array_fill(1, 4, 0);

    foreach (pg_application_decision_rows($ap_program, 'all') as $ap_drow) {
        if ($ap_drow['decision'] > 0) {
            $ap_totals[$ap_drow['decision']]++;
        }
    }

    $ap_words = array(1 => lang('Accepted'), 2 => lang('Rejected'), 3 => lang('Waitlisted'), 4 => lang('Declined'));
    $ap_tones = array(1 => 'pg-tone-teal', 2 => 'pg-tone-gray', 3 => 'pg-tone-amber', 4 => 'pg-tone-gray');
    $ap_waiting = array();

    foreach ($ap_drows as $ap_drow) {
        if ($ap_drow['decision'] === 3) {
            $ap_waiting[(string) $ap_drow['category']] = true;
        }
    }

    if (($ap_filter !== 'all') && in_array(4, array_column($ap_drows, 'decision'), true)) {
        // The waitlist may hold entries of other rounds than the ones listed.
        foreach (pg_application_decision_rows($ap_program, 'all') as $ap_drow) {
            if ($ap_drow['decision'] === 3) {
                $ap_waiting[(string) $ap_drow['category']] = true;
            }
        }
    }

    $ap_rows = '';

    foreach ($ap_drows as $ap_eid => $ap_drow) {
        $ap_ref = ((string) $ap_drow['reference_code'] !== '') ? (string) $ap_drow['reference_code'] : '#' . $ap_drow['form_id'];
        $ap_category_name = (string) ($ap_choices[(string) $ap_drow['category']] ?? $ap_drow['category']);
        $ap_options = '<option value="none">' . h(lang('No decision')) . '</option>';

        foreach (array(1, 2, 3) as $ap_code) {
            $ap_options .= '<option value="' . pg_application_decision_key($ap_code) . '"' . (($ap_drow['decision'] === $ap_code) ? ' selected' : '') . '>' . h($ap_words[$ap_code]) . '</option>';
        }

        if (in_array($ap_drow['decision'], array(1, 4), true)) {
            $ap_options .= '<option value="declined"' . (($ap_drow['decision'] === 4) ? ' selected' : '') . '>' . h(lang('Declined the place')) . '</option>';
        }

        $ap_state = '';

        if ($ap_drow['decision'] === 4) {
            $ap_state = '<span class="pg-pill pg-tone-gray">' . h(lang('Declined')) . '</span>';
        } elseif ($ap_drow['decision'] > 0) {
            $ap_state = ($ap_drow['mailed_at'] > 0)
                ? '<span class="pg-pill pg-tone-teal">' . h(lang('E-mail sent')) . '</span>'
                : '<span class="pg-pill pg-tone-amber">' . h(lang('E-mail waits')) . '</span>';
        }

        $ap_call = (($ap_drow['decision'] === 4) && !pg_application_waitlist_called($ap_eid))
            ? '<button type="submit" form="aprog_decisions_form" name="submit_call" value="' . $ap_eid . '" class="pg-button pg-button-quiet pg-button-sm"' . (isset($ap_waiting[(string) $ap_drow['category']]) ? '' : ' disabled')
                . ' data-confirm-content="' . h(lang('The best scored waitlisted entry of this category is accepted. Its e-mail goes when you send the decision e-mails.')) . '">' . h(lang('Call the next')) . '</button>'
            : '';
        $ap_rows .= '<tr><td><input type="checkbox" class="pg-checkbox" form="aprog_decisions_form" name="entries[]" value="' . $ap_eid . '" aria-label="' . h($ap_ref) . '" data-pg-ap-tick></td>'
            . '<td><a href="edit_submitted_form.php?id=' . $ap_drow['form_id'] . '">' . h($ap_ref) . '</a>'
            . (($ap_drow['shortlisted'] === 1) ? ' <span class="pg-pill pg-tone-teal">' . h(lang('Shortlisted')) . '</span>' : '') . '</td>'
            . '<td>' . h(($ap_category_name !== '') ? $ap_category_name : '—') . '</td>'
            . '<td class="pg-tabular text-end">' . (($ap_drow['score'] > 0) ? h(number_format($ap_drow['score'] / 100, 2)) : '<span class="pg-text-meta">—</span>')
            . (($ap_filter === 'all') ? ' <span class="pg-text-meta">' . h(lang(array('string' => 'Round {var:1}', 'vars' => array($ap_drow['round'])))) . '</span>' : '') . '</td>'
            . '<td><select class="pg-input w-auto" form="aprog_decisions_form" name="decision[' . $ap_eid . ']" aria-label="' . h(lang('Decision')) . ' ' . h($ap_ref) . '"' . (($ap_drow['decision'] === 4) ? ' disabled' : '') . '>' . $ap_options . '</select></td>'
            . '<td>' . $ap_state . '</td>'
            . '<td class="text-end text-nowrap">' . $ap_call . '</td></tr>';
    }

    if ($ap_rows === '') {
        $ap_rows = '<tr><td colspan="7" class="text-center pg-text-meta py-7">' . h(($ap_filter === 'shortlist') ? lang('No shortlist yet: apply the cut on the Entries tab, or show the whole round.') : lang('No entries yet.')) . '</td></tr>';
    }

    $ap_filter_link = function ($filter, $label) use ($ap_id, $ap_filter) {
        return '<a class="pg-seg-item' . (($ap_filter === $filter) ? ' is-active" aria-current="page' : '') . '" href="edit_application_program.php?id=' . $ap_id . '&amp;view=decisions&amp;filter=' . $filter . '">' . h($label) . '</a>';
    };

    // The send drawer: 1. who gets which e-mail, 2. a preview per text with
    // the first person's answers, 3. Send.
    $ap_count_lines = '';
    $ap_previews = '';

    foreach (array('accepted' => 1, 'rejected' => 2, 'waitlisted' => 3) as $ap_key => $ap_code) {
        $ap_count = $ap_counts[$ap_key];
        $ap_count_lines .= '<div class="pg-gather-line"><b>' . h($ap_words[$ap_code]) . '</b><span>'
            . h(lang(array('string' => '{var:1} e-mails', 'vars' => array($ap_count['send']))))
            . (($ap_count['no_address'] > 0) ? ' · ' . h(lang(array('string' => '{var:1} with no e-mail address', 'vars' => array($ap_count['no_address'])))) : '') . '</span>'
            . ((($ap_count['send'] > 0) && !$ap_count['written']) ? '<span class="pg-gather-line-end"><span class="pg-pill pg-tone-amber">' . h(lang('No text written')) . '</span></span>' : '') . '</div>';

        if (($ap_count['send'] > 0) && $ap_count['written']) {
            foreach ($ap_queue as $ap_item) {
                if (((int) $ap_item['decision'] === $ap_code) && ($ap_item['email'] !== '')) {
                    $ap_mail = pg_application_mail_render($ap_program, $ap_key, (int) $ap_item['form_id']);
                    $ap_previews .= '<div class="pg-card-inner p-4 mb-4"><div class="pg-text-meta mb-2">' . h($ap_words[$ap_code]) . ' · ' . h($ap_item['email']) . '</div>'
                        . '<strong class="d-block mb-2">' . h($ap_mail['subject']) . '</strong><div class="pg-text-2" style="white-space:pre-wrap">' . h($ap_mail['body']) . '</div></div>';
                    break;
                }
            }
        }
    }

    $ap_decisions_html = '<form id="aprog_decisions_form" action="edit_application_program.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="decisions"><input type="hidden" name="filter" value="' . $ap_filter . '"></form>'
        . '<section class="pg-card p-5 mb-5" aria-labelledby="aprog_decisions_title"><h2 class="pg-title-section mb-0" id="aprog_decisions_title">' . h(lang('Decisions')) . '</h2>'
        . '<p class="pg-card-sub mb-5">' . h(lang(array('string' => '{var:1} accepted, {var:2} rejected, {var:3} waitlisted, {var:4} declined. {var:5} e-mails wait to be sent.', 'vars' => array($ap_totals[1], $ap_totals[2], $ap_totals[3], $ap_totals[4], count($ap_queue))))) . '</p>'
        . '<div class="d-flex flex-wrap align-items-center gap-2">'
        . '<span class="pg-text-2">' . h(lang('The ticked entries:')) . '</span>'
        . '<button type="submit" form="aprog_decisions_form" name="submit_bulk" value="accepted" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Accept')) . '</button>'
        . '<button type="submit" form="aprog_decisions_form" name="submit_bulk" value="rejected" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Reject')) . '</button>'
        . '<button type="submit" form="aprog_decisions_form" name="submit_bulk" value="waitlisted" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Waitlist')) . '</button>'
        . '<span class="flex-grow-1"></span><button type="submit" form="aprog_decisions_form" name="submit_decisions" value="1" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Save the chosen decisions')) . '</button></div>'
        . '<p class="pg-field-help mt-3 mb-0">' . h(!empty($ap_program['waitlist_auto'])
            ? lang('When an accepted applicant declines, the next on the waitlist of the category is accepted by itself.')
            : lang('When an accepted applicant declines, "Call the next" accepts the next on the waitlist of the category.')) . '</p>'
        . '</section>'
        . '<span class="pg-seg mb-5" role="group" aria-label="' . h(lang('Show')) . '">' . $ap_filter_link('shortlist', lang('Shortlist')) . $ap_filter_link('round', lang(array('string' => 'Round {var:1}', 'vars' => array($ap_round)))) . $ap_filter_link('all', lang('All rounds')) . '</span>'
        . '<section class="pg-card p-0 mb-5 table-responsive" aria-label="' . h(lang('Decisions')) . '"><table class="table table-hover align-middle mb-0">'
        . '<thead><tr><th scope="col"><input type="checkbox" class="pg-checkbox" aria-label="' . h(lang('Tick all')) . '" data-pg-ap-tick-all></th><th scope="col">' . h(lang('Entry')) . '</th><th scope="col">' . h(lang('Category')) . '</th><th scope="col" class="text-end">' . h(lang('Score')) . '</th><th scope="col">' . h(lang('Decision')) . '</th><th scope="col">' . h(lang('Status')) . '</th><th scope="col"><span class="visually-hidden">' . h(lang('Actions')) . '</span></th></tr></thead>'
        . '<tbody>' . $ap_rows . '</tbody></table></section>'
        . '<dialog class="drawer drawer-end" id="aprog_send" aria-labelledby="aprog_send_title" style="width:min(560px,100%)">
        <form method="post" action="edit_application_program.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="decisions"><input type="hidden" name="filter" value="' . $ap_filter . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="aprog_send_title">' . h(lang('Send the decision e-mails')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('Each person gets the text of their decision once; the result then shows on their entry.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <h6 class="pg-title-section mb-3">1. ' . h(lang('Who gets which e-mail')) . '</h6>
                <div class="pg-gather mb-6">' . $ap_count_lines . '</div>
                <h6 class="pg-title-section mb-3">2. ' . h(lang('Preview')) . '</h6>
                ' . (($ap_previews !== '') ? $ap_previews : '<p class="pg-text-meta mb-6">' . h(lang('Nothing to preview: no e-mail waits, or its text is not written.')) . '</p>') . '
                <h6 class="pg-title-section mb-3">3. ' . h(lang('Send')) . '</h6>
                <p class="pg-text-2 mb-0">' . h(lang(array('string' => '{var:1} e-mails go now.', 'vars' => array($ap_sendable)))) . '</p>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_send" value="1" class="pg-button pg-button-fill"' . (($ap_sendable > 0) ? '' : ' disabled') . '>' . h(lang('Send')) . '</button>
            </div>
        </form>
    </dialog>';
    $ap_toolbar['primary'] = array('<button type="button" class="pg-button pg-button-fill" data-pg-ap-open="aprog_send"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('Send the decision e-mails')) . '</button>', $ap_state_button);
}

// The Report tab: a round's reviewer progress and the spread of its scores,
// every round's entries by state, and the Excel button for the same.
$ap_report_html = '';

if ($ap_view === 'report') {
    $ap_report = pg_application_report($ap_program, $ap_report_round);
    $ap_figure = function ($value) {
        return ($value === null) ? '<span class="pg-text-meta">—</span>' : h(number_format((float) $value, 2));
    };
    $ap_table = function ($label, array $head, $rows, $empty) {
        $cells = '';

        foreach ($head as $i => $title) {
            $cells .= '<th scope="col"' . (($i > 0) ? ' class="text-end"' : '') . '>' . h($title) . '</th>';
        }

        return '<div class="table-responsive"><table class="table align-middle mb-0" aria-label="' . h($label) . '"><thead><tr>' . $cells . '</tr></thead><tbody>'
            . (($rows !== '') ? $rows : '<tr><td colspan="' . count($head) . '" class="text-center pg-text-meta py-7">' . h($empty) . '</td></tr>') . '</tbody></table></div>';
    };
    $ap_card = function ($id, $title, $line, $body) {
        return '<section class="pg-card p-0 mb-5" aria-labelledby="' . $id . '"><div class="p-5 pb-3"><h2 class="pg-title-section mb-0" id="' . $id . '">' . h($title) . '</h2><p class="pg-card-sub mb-0">' . h($line) . '</p></div>' . $body . '</section>';
    };

    $ap_rows = '';

    foreach ($ap_report['reviewers'] as $ap_line) {
        $ap_percent = ($ap_line['given'] > 0) ? (int) round(100 * $ap_line['sent'] / $ap_line['given']) : 0;
        $ap_progress = lang(array('string' => '{var:1} of {var:2} sent', 'vars' => array($ap_line['sent'], $ap_line['given'])));
        $ap_rows .= '<tr><td>' . h($ap_line['name']) . '</td>'
            . '<td class="text-end"><span class="d-flex align-items-center justify-content-end gap-3"><span class="pg-tabular text-nowrap">' . h($ap_progress) . '</span><span class="progress flex-grow-1" role="progressbar" aria-label="' . h($ap_progress) . '" aria-valuenow="' . $ap_percent . '" aria-valuemin="0" aria-valuemax="100" style="height:0.5rem;min-width:4rem;max-width:8rem"><span class="progress-bar" style="width:' . $ap_percent . '%"></span></span></span></td>'
            . '<td class="pg-tabular text-end">' . $ap_line['started'] . '</td>'
            . '<td class="pg-tabular text-end">' . $ap_line['conflicts'] . '</td>'
            . '<td class="pg-tabular text-end">' . $ap_figure($ap_line['mean']) . '</td>'
            . '<td class="pg-tabular text-end text-nowrap">' . (($ap_line['lowest'] === null) ? $ap_figure(null) : $ap_figure($ap_line['lowest']) . ' – ' . $ap_figure($ap_line['highest'])) . '</td></tr>';
    }

    $ap_report_html .= $ap_card('aprog_report_reviewers', lang('Reviewer progress'), lang('Reviews given to each reviewer in this round and how far they are; the mean and range of the weighted totals they sent.'),
        $ap_table(lang('Reviewer progress'), array(lang('Reviewer'), lang('Sent'), lang('Started'), lang('Conflicts'), lang('Mean'), lang('Range')), $ap_rows, lang('No reviewers yet.')));

    $ap_rows = '';

    foreach ($ap_report['spread']['criteria'] as $ap_line) {
        $ap_rows .= '<tr><td>' . h($ap_line['name']) . '</td><td class="pg-tabular text-end">' . $ap_line['weight'] . '%</td><td class="pg-tabular text-end">' . $ap_line['count'] . '</td>'
            . '<td class="pg-tabular text-end">' . $ap_figure($ap_line['mean']) . '</td><td class="pg-tabular text-end">' . $ap_figure($ap_line['lowest']) . '</td><td class="pg-tabular text-end">' . $ap_figure($ap_line['highest']) . '</td><td class="pg-tabular text-end">' . $ap_figure($ap_line['deviation']) . '</td></tr>';
    }

    $ap_matrix_head = array(lang('Reviewer'));

    foreach ($ap_report['spread']['criteria'] as $ap_line) {
        $ap_matrix_head[] = $ap_line['name'];
    }

    $ap_matrix = '';

    foreach ($ap_report['spread']['reviewers'] as $ap_line) {
        $ap_matrix .= '<tr><td>' . h($ap_line['name']) . '</td>';

        foreach ($ap_line['means'] as $ap_mean) {
            $ap_matrix .= '<td class="pg-tabular text-end">' . $ap_figure($ap_mean) . '</td>';
        }

        $ap_matrix .= '</tr>';
    }

    $ap_report_html .= $ap_card('aprog_report_spread', lang('Scores by criterion'), lang('Every sent score of this round, 1 to 10: a wide deviation shows a criterion reviewers read differently.'),
        $ap_table(lang('Scores by criterion'), array(lang('Criterion'), lang('Weight'), lang('Scores'), lang('Mean'), lang('Lowest'), lang('Highest'), lang('Deviation')), $ap_rows, lang('This round has no criteria.'))
        . (($ap_matrix !== '') ? '<div class="p-5 pb-3 border-top"><h3 class="pg-title-section mb-0">' . h(lang('Each reviewer\'s mean')) . '</h3><p class="pg-card-sub mb-0">' . h(lang('A reviewer far above or below the others on a criterion scores it more generously or strictly.')) . '</p></div>' . $ap_table(lang('Each reviewer\'s mean'), $ap_matrix_head, $ap_matrix, '') : ''));

    $ap_rows = '';

    foreach ($ap_report['states'] as $ap_line) {
        $ap_rows .= '<tr><td class="pg-tabular">' . $ap_line['round'] . '</td><td>' . h($ap_choices[$ap_line['category']] ?? (($ap_line['category'] !== '') ? $ap_line['category'] : lang('No category'))) . '</td>';

        foreach (array('entries', 'not_eligible', 'let_in', 'reviewed', 'shortlisted', 'accepted', 'rejected', 'waitlisted', 'declined') as $ap_key) {
            $ap_rows .= '<td class="pg-tabular text-end">' . $ap_line[$ap_key] . '</td>';
        }

        $ap_rows .= '</tr>';
    }

    $ap_report_html .= $ap_card('aprog_report_states', lang('Entries by state'), lang('Every round, each category: where its entries stand. An entry moved to the next round is counted there.'),
        $ap_table(lang('Entries by state'), array(lang('Round'), lang('Category'), lang('Entries'), lang('Not eligible'), lang('Let in'), lang('Reviewed'), lang('Shortlisted'), lang('Accepted'), lang('Rejected'), lang('Waitlisted'), lang('Declined the place')), $ap_rows, lang('No entries yet.')));

    $ap_rounds_links = '';

    for ($ap_r = 1; $ap_r <= max(1, $ap_program['round']); $ap_r++) {
        $ap_rounds_links .= '<a class="pg-seg-item' . (($ap_r === $ap_report_round) ? ' is-active" aria-current="page' : '') . '" href="edit_application_program.php?id=' . $ap_id . '&amp;view=report&amp;round=' . $ap_r . '">' . h(lang(array('string' => 'Round {var:1}', 'vars' => array($ap_r)))) . '</a>';
    }

    if ($ap_program['round'] > 1) {
        $ap_report_html = '<span class="pg-seg mb-5" role="group" aria-label="' . h(lang('Round')) . '">' . $ap_rounds_links . '</span>' . $ap_report_html;
    }

    $ap_toolbar['secondary'] = array(
        '<a class="pg-button pg-button-quiet" href="edit_application_program.php?id=' . $ap_id . '&amp;view=report&amp;round=' . $ap_report_round . '&amp;export=xlsx"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>',
    );
}

echo pg_page_shell(array(
    'title' => $ap_program['name'],
    'extra classes' => 'form pg-measure-edit',
    'icon' => 'form',
    'help' => 'forms#submitted-forms',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($ap_program['name']) . ' ' . $ap_status_pill[$ap_program['status']],
    'heading_description' => lang(array('string' => 'Form: {var:1}. Entries: {var:2}.', 'vars' => array($ap_forms[$ap_program['page_id']]['name'], $ap_entries))),
    'breadcrumb' => array(
        array('label' => lang('Application programmes'), 'url' => $ap_list),
        array('label' => $ap_program['name']),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . pg_screen_toolbar($ap_toolbar) . '
    <form id="aprog_state_form" action="edit_application_program.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"><input type="hidden" name="view" value="' . $ap_view . '"></form>
    ' . (($ap_view === 'reviewers') ? $ap_reviewers_html : (($ap_view === 'entries') ? $ap_entries_html : (($ap_view === 'decisions') ? $ap_decisions_html : (($ap_view === 'report') ? $ap_report_html : $ap_setup_html)))) . '
</main>
' . get_date_time_picker_format() . pg_fields_script() . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/application_program.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/application_program.js') . '"></script>
' . output_footer();

$liveform->remove_form();
