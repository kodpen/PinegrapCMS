<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An application programme's screen (Forms › Application programmes): the
 * head with the name, the status and the button that follows it, the tabs,
 * and the Set-up tab: period, categories, eligibility, rounds and criteria,
 * blind review and decision e-mails. Gated by validate_forms_access() and
 * the folder rule on the programme's form. Without an id it asks the name
 * and the form and creates the programme as a draft.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_forms_access($user);

if (!pg_application_program_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_application_program');
$ap_forms = pg_application_program_forms($user);
$ap_id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$ap_program = ($ap_id > 0) ? pg_application_program_get($ap_id) : null;
$ap_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_application_program.php';
$ap_list = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_application_programs.php';

if (($ap_id > 0) && ($ap_program === null)) {
    output_error(lang('The programme was not found.'), 404);
}

// The folder rule: only a programme on a form the user may edit.
if (($ap_program !== null) && !isset($ap_forms[$ap_program['page_id']])) {
    log_activity('access denied to application programme ' . $ap_id, $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '.', 403);
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
            $liveform->add_error(h(lang('Give the programme a name and choose its form.')));
            go($ap_self);
        }

        $ap_new_id = pg_application_program_create($ap_new_name, $ap_new_page, (int) $user['id']);
        log_activity('application programme "' . $ap_new_name . '" was created');
        go($ap_self . '?id=' . $ap_new_id);
    }

    $ap_back = $ap_self . '?id=' . $ap_id;

    if (isset($_POST['submit_delete'])) {
        if (pg_application_program_delete($ap_program)) {
            log_activity('application programme "' . $ap_program['name'] . '" was deleted');
            $ap_list_form = new liveform('view_application_programs');
            $ap_list_form->add_notice(h(lang(array('string' => 'The programme "{var:1}" was deleted.', 'vars' => array($ap_program['name'])))));
            go($ap_list);
        }

        $liveform->add_error(h(lang('Only a draft with no entries can be deleted.')));
        go($ap_back);
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

// The new programme's page: the name and the form only.
if ($ap_program === null) {
    $ap_form_options = '<option value="0">' . h(lang('Choose a form')) . '</option>';

    foreach ($ap_forms as $page_id => $form) {
        $ap_form_options .= '<option value="' . $page_id . '">' . h($form['name']) . '</option>';
    }

    echo pg_page_shell(array(
        'title' => lang('Add programme'),
        'extra classes' => 'form pg-measure-edit',
        'icon' => 'form',
        'heading' => lang('Add programme'),
        'heading_description' => lang('Name the programme and choose the form people apply with. It starts as a draft.'),
        'breadcrumb' => array(
            array('label' => lang('Application programmes'), 'url' => $ap_list),
            array('label' => lang('Add programme')),
        ),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    <form action="edit_application_program.php" method="post" class="pg-card p-5 d-flex flex-column gap-5">
        ' . get_token_field() . '
        <div><label class="pg-field-label" for="ap_new_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="ap_new_name" name="name" maxlength="150" required></div>
        <div><label class="pg-field-label" for="ap_new_form">' . h(lang('Form')) . '</label><select class="pg-input" id="ap_new_form" name="page_id" required aria-describedby="ap_new_form_help">' . $ap_form_options . '</select>
        <div class="pg-field-help" id="ap_new_form_help">' . h(lang('Entries are the form\'s submissions.')) . '</div></div>
        <div class="d-flex flex-wrap gap-2"><button type="submit" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($ap_list) . '">' . h(lang('Cancel')) . '</a></div>
    </form>
</main>
' . output_footer();

    $liveform->remove_form();
    exit();
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
    return ($time > 0) ? date('Y-m-d\TH:i', $time) : '';
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
    return '<section class="pg-card p-5 mb-5" aria-labelledby="ap_' . $key . '_title">'
        . '<div class="d-flex flex-wrap align-items-center gap-2"><h2 class="pg-title-section mb-0" id="ap_' . $key . '_title">' . h($title) . '</h2>' . $missing . '</div>'
        . '<p class="pg-card-sub mb-5">' . h($line) . '</p>' . $body . '</section>';
};

// 1. Period.
$ap_period_line = (($ap_values['opens_at'] > 0) || ($ap_values['closes_at'] > 0))
    ? lang(array('string' => 'Opens {var:1}, closes {var:2}.', 'vars' => array(($ap_values['opens_at'] > 0) ? get_absolute_time(array('timestamp' => $ap_values['opens_at'], 'format' => 'plain_text')) : lang('when opened'), ($ap_values['closes_at'] > 0) ? get_absolute_time(array('timestamp' => $ap_values['closes_at'], 'format' => 'plain_text')) : lang('not set'))))
    : lang('No period yet: set when entries open and close.');
$ap_period = $ap_card('period', lang('Period'), $ap_period_line, ($ap_values['closes_at'] > 0) ? '' : $ap_not_set, '<div class="row gy-5">'
    . '<div class="col-12 md:col-6"><label class="pg-field-label" for="ap_opens">' . h(lang('Opens')) . '</label><input type="datetime-local" class="pg-input" id="ap_opens" name="opens" value="' . $ap_date($ap_values['opens_at']) . '"></div>'
    . '<div class="col-12 md:col-6"><label class="pg-field-label" for="ap_closes">' . h(lang('Closes')) . '</label><input type="datetime-local" class="pg-input" id="ap_closes" name="closes" value="' . $ap_date($ap_values['closes_at']) . '"></div>'
    . '<div class="col-12"><label class="pg-field-label" for="ap_outside">' . h(lang('Text outside the period')) . '</label><textarea class="pg-input" id="ap_outside" name="outside_text" rows="2" aria-describedby="ap_outside_help">' . h($ap_texts['outside']) . '</textarea>'
    . '<div class="pg-field-help" id="ap_outside_help">' . h(lang('Shown instead of saving when someone sends the form before it opens or after it closes.')) . '</div></div>'
    . '</div>');

// 2. Categories.
$ap_choice_types = array('pick list', 'radio button', 'check box');
$ap_categories = $ap_card('categories', lang('Categories'), ($ap_values['category_field'] !== '')
    ? lang(array('string' => 'Entries are grouped by the answer to "{var:1}".', 'vars' => array($ap_label($ap_values['category_field']))))
    : lang('No categories: every entry is in one pool.'), '', '<label class="pg-field-label" for="ap_category">' . h(lang('Choice field')) . '</label>'
    . '<select class="pg-input" id="ap_category" name="category_field"><option value="">' . h(lang('No categories')) . '</option>' . $ap_field_options($ap_values['category_field'], $ap_choice_types) . '</select>');

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
        . '<span class="pg-input w-auto"><input type="number" min="1" max="100" step="1" name="rounds[' . $r . '][cweight][]" value="' . h(isset($criterion['weight']) ? (string) $criterion['weight'] : '') . '" aria-label="' . h(lang('Weight')) . '" data-pg-ap-weight><span class="pg-input-end">%</span></span>'
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
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="ap_reviewers_' . $r . '">' . h(lang('Reviewers per entry')) . '</label><input type="number" class="pg-input" min="1" max="10" id="ap_reviewers_' . $r . '" name="rounds[' . $r . '][reviewers]" value="' . (int) $round['reviewers'] . '"></div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="ap_ends_' . $r . '">' . h(lang('Round ends')) . '</label><input type="datetime-local" class="pg-input" id="ap_ends_' . $r . '" name="rounds[' . $r . '][ends]" value="' . $ap_date((int) $round['ends_at']) . '"></div>'
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
    $ap_blind_boxes .= '<span class="d-inline-flex align-items-center gap-2 me-4"><input type="checkbox" class="pg-checkbox" id="ap_blind_' . $field['id'] . '" name="blind_fields[]" value="' . h($name) . '"' . (in_array($name, $ap_values['blind_fields'], true) ? ' checked' : '') . '><label class="pg-text-2" for="ap_blind_' . $field['id'] . '">' . h($ap_label($name)) . '</label></span>';
}

$ap_blind = $ap_card('blind', lang('Blind review'), $ap_values['blind'] ? lang('On: reviewers do not see who applied.') : lang('Off: reviewers see the whole entry.'), '',
    '<div class="d-flex align-items-center gap-3 mb-4"><input type="checkbox" class="pg-switch" id="ap_blind" name="blind" value="1"' . ($ap_values['blind'] ? ' checked' : '') . '><label class="pg-text-2" for="ap_blind">' . h(lang('Hide who applied from reviewers')) . '</label></div>'
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
        . '<div><label class="pg-field-label" for="ap_' . $key . '_subject">' . h(lang('Subject')) . '</label><input type="text" class="pg-input" id="ap_' . $key . '_subject" name="texts[' . $key . '][subject]" maxlength="200" value="' . h($text['subject']) . '"></div>'
        . '<div><label class="pg-field-label" for="ap_' . $key . '_body">' . h(lang('Text')) . '</label><textarea class="pg-input" id="ap_' . $key . '_body" name="texts[' . $key . '][body]" rows="6">' . h($text['body']) . '</textarea></div>'
        . '</div>';
}

$ap_mails = $ap_card('mails', lang('Decision e-mails'), ($ap_written === 3)
    ? lang('All three are written. They go only when you send them, after a preview.')
    : lang(array('string' => '{var:1} of 3 written. They go only when you send them, after a preview.', 'vars' => array($ap_written))), ($ap_written === 3) ? '' : $ap_not_set,
    '<span class="pg-seg mb-4" role="group" aria-label="' . h(lang('Decision')) . '">' . $ap_decision_tabs . '</span>' . $ap_decision_panes);

// The head: the status and the one button that follows it.
$ap_status_pill = array(
    0 => '<span class="pg-pill pg-tone-gray">' . h(lang('Draft')) . '</span>',
    1 => '<span class="pg-pill pg-tone-teal">' . h(lang('Open')) . '</span>',
    2 => '<span class="pg-pill pg-tone-gray">' . h(lang('Closed')) . '</span>',
);
$ap_state_button = '';

if ($ap_program['status'] === 0) {
    $ap_state_button = '<button type="submit" form="ap_state_form" name="submit_open" value="1" class="pg-button pg-button-quiet">' . h(lang('Open for entries')) . '</button>';
} elseif ($ap_program['status'] === 1) {
    $ap_state_button = '<button type="submit" form="ap_state_form" name="submit_close" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('The form stops taking entries. Drafts saved for later can no longer be finished.')) . '">' . h(lang('Close')) . '</button>';
}

$ap_deletable = ($ap_program['status'] === 0) && ($ap_entries === 0);

echo pg_page_shell(array(
    'title' => $ap_program['name'],
    'extra classes' => 'form pg-measure-edit',
    'icon' => 'form',
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
    ' . pg_screen_toolbar(array(
        'primary' => $ap_state_button,
        'view' => '<span class="pg-seg" role="group" aria-label="' . h(lang('Programme')) . '"><button type="button" class="pg-seg-item" aria-pressed="true">' . h(lang('Set-up')) . '</button></span>',
    )) . '
    <form id="ap_state_form" action="edit_application_program.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $ap_id . '"></form>
    <form id="ap_form" action="edit_application_program.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . $ap_id . '">
        <section class="pg-card p-5 mb-5" aria-labelledby="ap_name_title"><h2 class="pg-title-section mb-0" id="ap_name_title">' . h(lang('Programme')) . '</h2><p class="pg-card-sub mb-5">' . h(lang('The name staff and reviewers see.')) . '</p>
        <label class="pg-field-label" for="ap_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="ap_name" name="name" maxlength="150" required value="' . h($ap_values['name']) . '"></section>
        ' . $ap_period . $ap_categories . $ap_eligibility . $ap_rounds_html . $ap_blind . $ap_mails . '
        <div class="pg-floatbar d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 mb-5">
            <span class="d-flex flex-wrap gap-2"><button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($ap_list) . '">' . h(lang('Cancel')) . '</a></span>
            ' . ($ap_deletable ? '<button type="submit" name="submit_delete" value="1" formnovalidate class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The programme and its settings are deleted. The form and its submissions stay.')) . '">' . h(lang('Delete')) . '</button>' : '') . '
        </div>
    </form>
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/application_program.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/application_program.js') . '"></script>
' . output_footer();

$liveform->remove_form();
