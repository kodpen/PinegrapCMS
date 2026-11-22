<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The correction page of a not eligible application entry, opened from the
 * signed link of its e-mail with no account (includes/fn/application_jurors.php):
 * the form's name, and only the answers the eligibility reasons name, open
 * to change. Saving writes them, checks the entry again against the
 * programme's rules and says where it stands; a still failing entry can be
 * corrected again until the programme closes.
 *
 *   GET  application_fix.php?t=<token>
 *   POST application_fix.php   t, the answers (field id => value), files
 *
 * Counted on the firewall's sensitive allowance (waf.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_jurors.php');

// A personal page: never kept by a cache, never indexed, never framed, and
// the signed address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$af_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$af_input = $af_post ? $_POST : $_GET;
$af_token = is_string($af_input['t'] ?? null) ? $af_input['t'] : '';
$af_fix = pg_application_fix_find($af_token);
$af_state = pg_application_fix_link_state($af_fix['entry'] ?? null, $af_fix['program']['status'] ?? 0, $af_fix['program']['closes_at'] ?? 0, time());
$af_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/application_fix.php';
$af_form_name = $af_fix ? (string) db_value("SELECT form_name FROM custom_form_pages WHERE page_id = '" . (int) $af_fix['program']['page_id'] . "'") : '';
$af_title = ($af_form_name !== '') ? $af_form_name : ($af_fix['program']['name'] ?? lang('Your entry'));

if ($af_state !== 'open') {
    $af_words = array(
        'invalid' => array(404, lang('This link is not valid. A newer correction link may have replaced it.')),
        'expired' => array(410, lang('This link has expired: the programme no longer takes entries.')),
        'done'    => array(200, lang('Your entry meets the programme\'s rules. Thank you, nothing more is needed.')),
    );
    set_response_code($af_words[$af_state][0]);
    print output_header_secure(array('title' => $af_title))
        . pg_secure_card(($af_state === 'done') ? 'bi-check2-circle' : 'bi-pencil-square', $af_title, '<p class="mb-0" role="status">' . h($af_words[$af_state][1]) . '</p>')
        . output_footer_secure();
    exit;
}

$af_fields = pg_application_fix_form_fields($af_fix);
$af_error = '';

if ($af_post) {
    validate_token_field();
    $af_saved = pg_application_fix_save($af_fix, $af_fields, pg_application_fix_input($af_fields, is_array($_POST['answer'] ?? null) ? $_POST['answer'] : array()), pg_application_fix_files($_FILES['answer'] ?? null));

    if ($af_saved['ok']) {
        go($af_page . '?t=' . $af_token . ($af_saved['eligible'] ? '' : '&again=1'));
    }

    $af_error = $af_saved['error'];
}

$af_reasons = '';
$af_labels = array();

foreach ($af_fields as $field) {
    $af_labels[(string) $field['name']] = ((string) $field['label'] !== '') ? (string) $field['label'] : (string) $field['name'];
}

foreach ($af_fix['entry']['eligibility_note'] ? (json_decode((string) $af_fix['entry']['eligibility_note'], true)['reasons'] ?? array()) : array() as $reason) {
    $af_reasons .= '<li>' . h(pg_application_reason_text($reason, $af_labels[$reason['field']] ?? '')) . '</li>';
}

$af_inputs = '';

foreach ($af_fields as $field) {
    $id = (int) $field['id'];
    $label = $af_labels[(string) $field['name']];
    $name = 'answer[' . $id . ']';

    if ((string) $field['type'] === 'file upload') {
        $control = (($field['file'] !== '') ? '<div class="form-text mb-2">' . h(lang(array('string' => 'Sent now: {var:1}', 'vars' => array($field['file'])))) . '</div>' : '')
            . '<input type="file" class="form-control" id="af_' . $id . '" name="' . $name . '">';
    } elseif ($field['options'] && ((string) $field['type'] === 'check box')) {
        $control = '';

        foreach ($field['options'] as $value => $option) {
            $control .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $name . '[]" value="' . h($value) . '" id="af_' . $id . '_' . md5($value) . '"' . (in_array((string) $value, $field['values'], true) ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="af_' . $id . '_' . md5($value) . '">' . h($option) . '</label></div>';
        }
    } elseif ($field['options']) {
        $control = '<select class="form-select" id="af_' . $id . '" name="' . $name . '"><option value=""></option>';

        foreach ($field['options'] as $value => $option) {
            $control .= '<option value="' . h($value) . '"' . (in_array((string) $value, $field['values'], true) ? ' selected' : '') . '>' . h($option) . '</option>';
        }

        $control .= '</select>';
    } else {
        $control = '<input type="text" class="form-control" id="af_' . $id . '" name="' . $name . '" value="' . h($field['values'][0] ?? '') . '" maxlength="1000">';
    }

    $af_inputs .= '<div class="mb-5">' . ((string) $field['type'] === 'check box' && $field['options'] ? '<div class="form-label">' . h($label) . '</div>' : '<label class="form-label" for="af_' . $id . '">' . h($label) . '</label>') . $control . '</div>';
}

$af_content = (($af_error !== '') ? '<div class="alert theme-danger" role="alert">' . h($af_error) . '</div>' : '')
    . (!empty($_GET['again']) ? '<div class="alert theme-warning" role="status">' . h(lang('Your answers were saved, but the entry does not meet the programme\'s rules yet.')) . '</div>' : '')
    . '<p>' . h(lang(array('string' => 'Your entry to "{var:1}" does not meet the programme\'s rules yet:', 'vars' => array($af_fix['program']['name'])))) . '</p>'
    . (($af_reasons !== '') ? '<ul class="mb-5">' . $af_reasons . '</ul>' : '')
    . '<form method="post" action="' . h($af_page) . '" enctype="multipart/form-data">' . get_token_field()
    . '<input type="hidden" name="t" value="' . h($af_token) . '">'
    . $af_inputs
    . '<p class="form-text">' . h(lang('Only these answers can be changed; the rest of your entry stays as you sent it.')) . '</p>'
    . '<button type="submit" class="pg-button pg-button-fill">' . h(lang('Save the corrections')) . '</button></form>';

print output_header_secure(array('title' => $af_title))
    . pg_secure_card('bi-pencil-square', $af_title, $af_content)
    . output_footer_secure();
