<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates › WhatsApp template: the template designer. A
 * template is written here with its name, type, language, message with
 * variables and their sample values and footer, previewed as a phone shows
 * it, and kept as a draft. A template WhatsApp already holds is shown
 * read-only; an approved one can be sent to the signed-in person's own
 * WhatsApp with its sample values. Settings and templates of WhatsApp are a
 * manager's (role <= 2), as the WhatsApp settings card is.
 *
 * The functions are includes/fn/whatsapp_design.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_design.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_whatsapp_templates_ready()) {
    output_error(lang('The WhatsApp templates are not available yet; run the upgrade.'));
}

pg_request_single_values(array('id'));

$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$self = PATH . SOFTWARE_DIRECTORY . '/whatsapp_template.php' . ($id ? '?id=' . $id : '');
$list_url = 'messages_templates.php';

$liveform = new liveform('whatsapp_template');

if ($id) {
    $design = pg_whatsapp_design_load($id);

    if (!$design) {
        output_error(lang('The WhatsApp template could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Go back') . '</a>.');
    }
} else {
    $design = pg_whatsapp_design_new();
}

$editable = pg_whatsapp_design_editable($design);
$errors = array();

if (pg_post_body_discarded()) {
    $liveform->add_error(lang('The request was too large for this server and arrived empty.'));
    go($self);
}

if ($_POST) {
    validate_token_field();

    if (isset($_POST['submit_test'])) {
        $result = pg_whatsapp_design_test($design, pg_whatsapp_design_own_number());

        if ($result['ok']) {
            log_activity(lang(array('string' => 'WhatsApp template test message sent: {var:1}', 'vars' => array($design['name']))), $_SESSION['sessionusername']);
            $liveform->add_notice($result['message']);
        } else {
            $liveform->add_error($result['message']);
        }

        go($self);
    }

    if (isset($_POST['submit_save']) && $editable) {
        $design = pg_whatsapp_design_posted($_POST, $design);
        $errors = pg_whatsapp_design_check($design);

        if (!$errors) {
            $saved = pg_whatsapp_design_save($design, $user['id']);

            if ($saved['ok']) {
                log_activity(lang(array('string' => 'WhatsApp template draft saved: {var:1}', 'vars' => array($design['name']))), $_SESSION['sessionusername']);
                $liveform->add_notice(lang('The draft was saved. WhatsApp sees it once it is sent for approval.'));
                go(PATH . SOFTWARE_DIRECTORY . '/whatsapp_template.php?id=' . (int) $saved['id']);
            }

            $errors[($saved['field'] !== '') ? $saved['field'] : '_form'] = $saved['error'];
        }
    }
}

$categories = pg_whatsapp_design_categories();
$languages = pg_whatsapp_design_languages();

// A template read from Meta may be in a language the designer does not
// offer: it is shown by its code.
if (($design['language'] !== '') && !isset($languages[$design['language']])) {
    $languages[$design['language']] = $design['language'];
}

$category_help = array(
    'UTILITY'        => lang('Updates the customer expects: an order received, shipped or delivered, a form answered.'),
    'MARKETING'      => lang('Offers and news. Sent only to contacts who gave marketing consent.'),
    'AUTHENTICATION' => lang('One-time codes only. WhatsApp writes the text of a code template itself when it approves it.'),
);

$status_tones = array('DRAFT' => 'amber', 'APPROVED' => 'teal', 'PENDING' => 'blue', 'IN_APPEAL' => 'blue', 'REJECTED' => 'rose', 'PAUSED' => 'amber', 'FLAGGED' => 'amber');
$status_tone = isset($status_tones[$design['status']]) ? $status_tones[$design['status']] : 'gray';

$field_error = function ($field) use ($errors) {
    return isset($errors[$field]) ? '<div class="pg-field-help pg-field-error" id="' . h($field) . '_error">' . h($errors[$field]) . '</div>' : '';
};
$invalid = function ($field) use ($errors) {
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . h($field) . '_error"' : '';
};
$disabled = $editable ? '' : ' disabled';

// The three stages of a template; the first is the designer's.
$stage = ($design['status'] === 'APPROVED') ? 3 : (($design['status'] === 'DRAFT') ? 1 : 2);
$output_stages = '';

foreach (array(1 => lang('Design'), 2 => lang('WhatsApp approval'), 3 => lang('Use in orders, forms and flows')) as $number => $label) {
    $output_stages .= ($number > 1 ? '<span class="pg-wa-stage-line" aria-hidden="true"></span>' : '')
        . '<li class="pg-wa-stage' . (($number === $stage) ? ' is-active" aria-current="step' : '') . '"><span class="pg-wa-stage-number">' . $number . '</span>' . h($label) . '</li>';
}

// Basics: name, type, language.
$output_types = '';

foreach ($categories as $value => $label) {
    $output_types .= '<button type="button" class="pg-seg-item" data-pg-wa-category="' . h($value) . '" aria-pressed="' . (($design['category'] === $value) ? 'true' : 'false') . '"' . $disabled . '>' . h($label) . '</button>';
}

$output_languages = '';

foreach ($languages as $code => $name) {
    $output_languages .= '<option value="' . h($code) . '"' . (($design['language'] === $code) ? ' selected' : '') . '>' . h($name) . '</option>';
}

$output_basics =
    '<section class="card pg-wa-card">
        <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Basics')) . '</h2>
        <div class="card-body pt-0"><div class="pg-wa-basics">
            <div>
                <label class="pg-field-label" for="name">' . h(lang('Name')) . '</label>
                <input type="text" class="pg-input pg-wa-mono" id="name" name="name" maxlength="' . PG_WHATSAPP_DESIGN_NAME_MAX . '" value="' . h($design['name']) . '" autocomplete="off" spellcheck="false" pattern="[a-z0-9_]+"' . $invalid('name') . $disabled . '>
                ' . ($field_error('name') ?: '<div class="pg-field-help">' . h(lang('Lower-case letters, digits and _, as WhatsApp asks.')) . '</div>') . '
            </div>
            <div>
                <span class="pg-field-label" id="category_label">' . h(lang('Type')) . '</span>
                <span class="pg-seg pg-wa-seg" role="group" aria-labelledby="category_label">' . $output_types . '</span>
                <input type="hidden" name="category" id="category" value="' . h($design['category']) . '">
                ' . ($field_error('category') ?: '<div class="pg-field-help" id="category_help">' . h(isset($category_help[$design['category']]) ? $category_help[$design['category']] : '') . '</div>') . '
            </div>
            <div>
                <label class="pg-field-label" for="language">' . h(lang('Language')) . '</label>
                <select class="pg-input" id="language" name="language"' . $invalid('language') . $disabled . '>' . $output_languages . '</select>
                ' . $field_error('language') . '
            </div>
        </div></div>
    </section>';

// Message: the formatting buttons, the variables to drag in, the text
// (an editor of text and chips over the body field), the footer.
$chip_labels = pg_whatsapp_design_chip_labels($design);
$output_palette = '';

foreach (array_unique(array_merge(pg_whatsapp_design_variable_presets(), array_values($chip_labels))) as $preset) {
    $output_palette .= pg_var_chip_html($preset, true);
}

$output_tools = $editable
    ? '<div class="pg-wa-tools" role="toolbar" aria-label="' . h(lang('Formatting')) . '" aria-controls="body">
            <button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-wa-wrap="*" title="' . h(lang('Bold')) . '" aria-label="' . h(lang('Bold')) . '"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
            <button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-wa-wrap="_" title="' . h(lang('Italic')) . '" aria-label="' . h(lang('Italic')) . '"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
            <button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-wa-wrap="~" title="' . h(lang('Strikethrough')) . '" aria-label="' . h(lang('Strikethrough')) . '"><i class="bi bi-type-strikethrough" aria-hidden="true"></i></button>
        </div>
        <div class="pg-wa-palette-box">
            <span class="pg-text-meta" id="pg_wa_palette_label">' . h(lang('Drag a variable into the message, or click it to add it where the cursor is:')) . '</span>
            <div class="pg-wa-palette" id="pg_wa_palette" role="group" aria-labelledby="pg_wa_palette_label">'
                . $output_palette . '
                <span class="pg-wa-own">
                    <input type="text" class="pg-input pg-input-sm" id="pg_wa_own" maxlength="' . PG_WHATSAPP_DESIGN_SAMPLE_MAX . '" placeholder="' . h(lang('Another variable')) . '" aria-label="' . h(lang('Name of another variable')) . '">
                    <button type="button" class="pg-button pg-button-quiet pg-button-sm" id="pg_wa_own_add"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add')) . '</button>
                </span>
            </div>
        </div>'
    : '';

$output_message =
    '<section class="card pg-wa-card">
        <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Message')) . '</h2>
        <div class="card-body pt-0">
        <div class="pg-wa-editor">
            ' . $output_tools . '
            <label class="visually-hidden" for="body">' . h(lang('Message')) . '</label>
            <textarea class="pg-input pg-wa-body" id="body" name="body" rows="7" aria-label="' . h(lang('Message')) . '" data-pg-wa-max="' . PG_WHATSAPP_DESIGN_BODY_MAX . '"' . $invalid('body') . $disabled . '>' . h($design['body']) . '</textarea>
        </div>
        ' . $field_error('body') . '
        <div class="pg-wa-count pg-text-meta"><span>' . h(lang('Variables are filled for each person when the message is sent. *bold*, _italic_ and ~strikethrough~ are shown as WhatsApp shows them.')) . '</span><span class="pg-tabular" id="body_count" aria-live="polite"></span></div>
        <div class="pg-wa-footer-field">
            <label class="pg-field-label" for="footer">' . h(lang('Footer')) . ' <span class="pg-field-label-note">· ' . h(lang('optional')) . '</span></label>
            <input type="text" class="pg-input" id="footer" name="footer" maxlength="' . PG_WHATSAPP_DESIGN_FOOTER_MAX . '" value="' . h($design['footer']) . '"' . $invalid('footer') . $disabled . '>
            ' . ($field_error('footer') ?: '<div class="pg-field-help">' . h(lang('A short line under the message, such as your shop\'s name; no variables.')) . '</div>') . '
        </div>
        </div>
    </section>';

// A template WhatsApp holds: why it is read-only, and a rejection's reason.
$output_state = '';

if (!$editable) {
    $output_state = '<div class="alert theme-' . (($design['status'] === 'REJECTED') ? 'danger' : 'secondary') . '" role="status"><p class="mb-0">'
        . h(($design['status'] === 'APPROVED')
            ? lang('WhatsApp approved this template. It cannot be changed; send yourself a test to see it on your phone.')
            : lang('This template is with WhatsApp, so it is shown here as WhatsApp holds it.'))
        . (($design['reason'] !== '') ? '<br>' . h(lang(array('string' => 'Reason given by WhatsApp: {var:1}', 'vars' => array($design['reason'])))) : '')
        . '</p></div>';
}

// The sample values: one row per variable, built again by the script as
// the message changes.
$output_samples = '';

foreach (pg_whatsapp_design_variables($design['body']) as $key) {
    $output_samples .= pg_whatsapp_design_sample_row($key, $chip_labels[$key], isset($design['samples'][$key]) ? $design['samples'][$key] : '', $editable, $errors);
}

// The phone: the header, the message, the footer and the buttons as
// WhatsApp draws them.
$output_header = '';

if ($design['header']) {
    $format = isset($design['header']['format']) ? strtoupper((string) $design['header']['format']) : '';
    $icons = array('IMAGE' => 'bi-image', 'VIDEO' => 'bi-play-btn', 'DOCUMENT' => 'bi-file-earmark', 'LOCATION' => 'bi-geo-alt');

    $output_header = ($format === 'TEXT')
        ? '<div class="pg-wa-msg-head">' . pg_whatsapp_design_format_html(isset($design['header']['text']) ? (string) $design['header']['text'] : '', array()) . '</div>'
        : '<div class="pg-wa-msg-media" aria-hidden="true"><i class="bi ' . (isset($icons[$format]) ? $icons[$format] : 'bi-image') . '"></i></div>';
}

$output_buttons_preview = '';
$button_icons = array('URL' => 'bi-box-arrow-up-right', 'PHONE_NUMBER' => 'bi-telephone', 'COPY_CODE' => 'bi-copy', 'QUICK_REPLY' => 'bi-reply', 'OTP' => 'bi-copy');

foreach ($design['buttons'] as $button) {
    $type = isset($button['type']) ? strtoupper((string) $button['type']) : '';
    $output_buttons_preview .= '<div class="pg-wa-msg-button"><i class="bi ' . (isset($button_icons[$type]) ? $button_icons[$type] : 'bi-reply') . '" aria-hidden="true"></i>' . h(isset($button['text']) ? (string) $button['text'] : '') . '</div>';
}

$app = pg_whatsapp_app();
$business = ($app['verified_name'] ?? '') !== '' ? (string) $app['verified_name'] : (string) (defined('ORGANIZATION_NAME') && ORGANIZATION_NAME !== '' ? ORGANIZATION_NAME : HOSTNAME_SETTING);

$output_phone =
    '<div class="pg-wa-phone" aria-hidden="true">
        <div class="pg-wa-screen">
            <div class="pg-wa-bar"><span>' . h(date('H:i')) . '</span><span class="pg-wa-bar-icons"><i class="bi bi-reception-4"></i><i class="bi bi-wifi"></i><i class="bi bi-battery-full"></i></span></div>
            <div class="pg-wa-head"><i class="bi bi-arrow-left"></i><span class="pg-wa-avatar"><i class="bi bi-shop"></i></span><div class="pg-wa-head-name"><b>' . h($business) . '<i class="bi bi-patch-check-fill"></i></b><small>' . h(lang('Business account')) . '</small></div><span class="pg-wa-head-icons"><i class="bi bi-camera-video"></i><i class="bi bi-telephone"></i><i class="bi bi-three-dots-vertical"></i></span></div>
            <div class="pg-wa-chat">
                <div class="pg-wa-day">' . h(lang('Today')) . '</div>
                <div class="pg-wa-msg">
                    ' . $output_header . '
                    <div class="pg-wa-msg-body" id="pg_wa_preview_body">' . pg_whatsapp_design_format_html($design['body'], $design['samples']) . '</div>
                    <div class="pg-wa-msg-footer" id="pg_wa_preview_footer"' . (($design['footer'] === '') ? ' hidden' : '') . '>' . h($design['footer']) . '</div>
                    <time>' . h(date('H:i')) . '</time>
                    ' . $output_buttons_preview . '
                </div>
            </div>
            <div class="pg-wa-input"><span></span><i class="bi bi-mic-fill"></i></div>
        </div>
    </div>';

$output_side =
    '<div class="position-sticky pg-wa-side">
        <div class="pg-wa-side-cap"><h2 class="pg-title-status">' . h(lang('As it shows on the phone')) . '</h2><p class="pg-text-meta">' . h(lang('Updated as you type, filled with the sample values.')) . '</p></div>
        ' . $output_phone . '
        <section class="card pg-wa-samples">
            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Sample values')) . ' <span class="pg-field-label-note">· ' . h(lang('for the preview and WhatsApp\'s review')) . '</span></h2>
            <div class="card-body pt-0">
                <div id="pg_wa_samples">' . $output_samples . '</div>
                <p class="pg-text-meta mb-0" id="pg_wa_samples_none"' . ($output_samples !== '' ? ' hidden' : '') . '>' . h(lang('The message has no variables.')) . '</p>
            </div>
        </section>
    </div>';

// The bar: a draft is saved; an approved template is tested.
$output_actions = '';

if ($editable) {
    $output_actions .= '<button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill"><i class="bi bi-check-circle" aria-hidden="true"></i>' . h(lang('Save draft')) . '</button>';
}

if ($design['status'] === 'APPROVED') {
    $own = pg_whatsapp_design_own_number();
    $output_actions .= '<button type="submit" name="submit_test" value="1" class="pg-button pg-button-quiet"'
        . (($own === '') ? ' disabled title="' . h(lang('Add your mobile number to your contact details in My Account first.')) . '"' : ' data-confirm-content="' . h(lang(array('string' => 'The template goes at once to {var:1}, filled with the sample values. Meta charges it.', 'vars' => array('+' . $own)))) . '"')
        . '><i class="bi bi-send" aria-hidden="true"></i>' . h(lang('Send myself a test')) . '</button>';
}

$output_form_error = isset($errors['_form']) ? '<div class="alert theme-danger" role="alert"><p class="mb-0">' . h($errors['_form']) . '</p></div>' : '';

if ($errors && !isset($errors['_form'])) {
    $output_form_error = '<div class="alert theme-danger" role="alert"><p class="mb-0">' . h(lang('The draft was not saved. Correct the marked fields.')) . '</p></div>';
}

$heading = ($design['name'] !== '') && $id ? $design['name'] : lang('New WhatsApp template');

// Texts and limits the script needs.
$script_data = array(
    'max'      => PG_WHATSAPP_DESIGN_BODY_MAX,
    'variables' => PG_WHATSAPP_DESIGN_VARIABLES_MAX,
    'help'     => $category_help,
    'sample'   => lang('Sample value'),
    'sampleOf' => lang('Sample value of {var:1}'),
    'remove'   => lang('Remove {var:1}'),
    'fallback' => lang('Value {var:1}'),
    'editable' => $editable,
);

echo
pg_page_shell(
    array(
        'title'               => lang('WhatsApp template'),
        'help'                => 'campaigns',
        'extra classes'       => 'campaign pg-measure-edit pg-wa-designer',
        'icon'                => 'campaign',
        'heading'             => lang('WhatsApp template'),
        'heading_description' => lang('Designed here, previewed as a phone shows it, then sent to WhatsApp for approval. Only an approved template can be sent.'),
        'cancel'              => array('enable' => 'true', 'url' => $list_url),
        'breadcrumb'          => array(
            array('label' => lang('Templates'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $list_url),
            array('label' => $heading),
        ),
    )
) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . $liveform->output_notices() . $output_form_error . $output_state . '
    <div class="pg-wa-title"><span class="pg-title-screen pg-wa-mono-title">' . h($heading) . '</span><span class="pg-pill pg-tone-' . $status_tone . '">' . h(pg_whatsapp_template_status_label($design['status'])) . '</span></div>
    <ol class="pg-wa-stages" aria-label="' . h(lang('Stages of a template')) . '">' . $output_stages . '</ol>
    <form name="form" action="' . h($self) . '" method="post" id="pg_wa_form" data-pg-wa="' . h(json_encode($script_data, JSON_UNESCAPED_UNICODE)) . '">
        ' . get_token_field() . '
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $output_basics . $output_message . '</div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">' . $output_side . '</div>
            ' . ($output_actions !== '' ? '<div class="col-12 order-3 position-sticky"><nav class="buttons navigation" aria-label="' . h(lang('Actions')) . '">' . $output_actions . '</nav></div>' : '') . '
        </div>
    </form>
</main>
' . pg_var_chips_script() . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/whatsapp_template.js?v=' . (int) @filemtime(dirname(__FILE__) . '/assets/js/whatsapp_template.js') . '"></script>' .
output_footer();

$liveform->remove_form();
