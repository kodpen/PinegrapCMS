<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the screen a channel template is written on: what the pickers
 * show (name, description, icon, colour), the channel it suggests (who can
 * see it, the department), the summary, the pinned message and the welcome,
 * and its tasks and notes. ?id= opens one of the site's templates, ?copy= a
 * copy of any template (the built-in ones are changed that way), ?channel= a
 * draft taken from a channel, which is kept only once it is saved. The tasks
 * and notes are drawn by assets/js/workspace_templates.js and posted as JSON
 * in the body field; includes/workspace/templates.php checks and keeps them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_templates_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

if (!ws_can_write_templates($viewer)) {
    log_activity(lang('access denied to the workspace templates'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

include_once('liveform.class.php');
$liveform = new liveform('workspace_template');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_template.php';
$settings_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_settings.php#ws-templates';

$template = null;
$body_json = '';
$draft_from = '';

if ($_POST) {
    validate_token_field();

    $data = array(
        'id'            => (int) ($_POST['id'] ?? 0),
        'name'          => (string) ($_POST['name'] ?? ''),
        'description'   => (string) ($_POST['description'] ?? ''),
        'icon'          => (string) ($_POST['icon'] ?? ''),
        'color'         => (int) ($_POST['color'] ?? 0),
        'kind'          => (string) ($_POST['kind'] ?? 'public'),
        'department_id' => (int) ($_POST['department_id'] ?? 0),
        'summary'       => (string) ($_POST['summary'] ?? ''),
        'pinned'        => (string) ($_POST['pinned'] ?? ''),
        'welcome'       => (string) ($_POST['welcome'] ?? ''),
        'body'          => (string) ($_POST['body'] ?? ''),
    );

    $saved = ws_template_save($viewer, $data);

    if ($saved['ok']) {
        $liveform->add_notice(lang(array('string' => 'The template was saved: {var:1}.', 'vars' => trim($data['name']))));
        go($self_url . '?id=' . (int) $saved['id']);
    }

    $liveform->add_error($saved['error']);

    // Shown again as it was sent, the tasks and notes included, so nothing
    // typed is lost to a refusal.
    $current = ($data['id'] > 0) ? ws_template($data['id']) : null;
    $template = ws_template_normalise(array_merge($data, array(
        'id'   => $current ? (int) $current['id'] : 0,
        'uses' => $current ? (int) $current['uses'] : 0,
        'body' => '',
    )));
    $body_json = $data['body'];
} elseif ((int) ($_GET['id'] ?? 0) > 0) {
    $template = ws_template((int) $_GET['id']);

    if (!$template) {
        output_error(lang('That template could not be found.') . ' <a href="' . h($settings_url) . '">' . lang('Back') . '</a>');
    }
} elseif ((string) ($_GET['copy'] ?? '') !== '') {
    $source = ws_template((string) $_GET['copy']);

    if (!$source) {
        output_error(lang('That template could not be found.') . ' <a href="' . h($settings_url) . '">' . lang('Back') . '</a>');
    }

    $template = array_merge($source, array(
        'id'       => 0,
        'builtin'  => false,
        'name'     => mb_substr(lang(array('string' => '{var:1} (copy)', 'vars' => $source['name'])), 0, 100),
        'uses'     => 0,
        'archived' => false,
    ));
} elseif ((int) ($_GET['channel'] ?? 0) > 0) {
    $channel = ws_channel((int) $_GET['channel']);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        output_error(lang('That channel could not be found.') . ' <a href="' . h($settings_url) . '">' . lang('Back') . '</a>');
    }

    $template = ws_template_from_channel($viewer, $channel);
    $draft_from = (string) $channel['name'];
}

if ($template === null) {
    $template = ws_template_normalise(array('id' => 0, 'kind' => 'public'));
}

if ($body_json === '') {
    $body_json = json_encode($template['body'], JSON_UNESCAPED_UNICODE);
}

$is_new = ((int) $template['id'] === 0);

// The choices of the form.
$color_options = '<option value="0">' . h(lang('No colour')) . '</option>';

foreach (ws_palette() as $place => $color) {
    $color_options .= '<option value="' . (int) $place . '"' . (((int) $template['color'] === (int) $place) ? ' selected' : '') . '>' . h($color[1]) . '</option>';
}

$department_options = '<option value="0">' . h(lang('No department')) . '</option>';
$departments_js = array();

foreach (ws_departments() as $department) {
    $department_options .= '<option value="' . (int) $department['id'] . '"' . (((int) $template['department_id'] === (int) $department['id']) ? ' selected' : '') . '>' . h($department['name']) . '</option>';
    $departments_js[] = array('id' => (int) $department['id'], 'name' => (string) $department['name']);
}

$people_js = array();

foreach (ws_team_members() as $person) {
    $people_js[] = array('id' => (int) $person['id'], 'name' => (string) $person['name']);
}

$kind_radio = function ($value, $label, $help) use ($template) {
    return '
        <div class="form-check">
            <input class="form-check-input" type="radio" name="kind" id="ws_tpl_kind_' . h($value) . '" value="' . h($value) . '"' . (($template['kind'] === $value) ? ' checked' : '') . '>
            <label class="form-check-label" for="ws_tpl_kind_' . h($value) . '">' . h($label) . '</label>
            <div class="form-text mt-0">' . h($help) . '</div>
        </div>';
};

// The names of the tags in the three texts, for the writing box to show
// them as chips (the channel summary does the same, ws_channel_detail()).
$labels = array();
$tokens = ws_tokens($template['summary'] . "\n" . $template['pinned'] . "\n" . $template['welcome']);
$refs = ws_refs_resolve($viewer, $tokens);

foreach ($tokens as $token) {
    $key = $token['type'] . ':' . $token['id'];

    if (isset($refs[$key])) {
        $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
    }
}

// What the script draws the tasks and notes from.
$editor_data = json_encode(array(
    'departments' => $departments_js,
    'people'      => $people_js,
    'priorities'  => ws_task_priorities(),
    'labels'      => (object) $labels,
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$counts = lang(array('string' => '{var:1} tasks · {var:2} notes', 'vars' => array(count($template['body']['tasks']), count($template['body']['notes']))));
$icon = ($template['icon'] !== '') ? $template['icon'] : 'bi-layout-text-window';
$hex = ws_palette_hex($template['color']);

echo
pg_page_shell([
        'title' => $is_new ? lang('New channel template') : lang('Channel template'),
        'extra classes' => 'workspace workspace_template',
        'icon' => 'workspace',
        'heading' => $is_new ? lang('New channel template') : lang('Channel template'),
        'heading_description' => lang('The tasks, notes and texts a channel is set up with.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid p-0">
<div class="ws-shell">
    ' . ws_screen_rail($viewer, 'settings') . '
<div class="ws-shell-main">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . (($draft_from !== '') ? '<div class="alert alert-info small">' . h(lang(array('string' => 'A draft made from #{var:1}: its open tasks, the notes shared in it, its summary and its pinned message. Nothing is kept until it is saved.', 'vars' => $draft_from))) . '</div>' : '') . '
    <nav id="button_bar" class="pg-toolbar navigation mb-3" aria-label="' . h(lang('Button Bar')) . '">
        <a class="btn btn-sm btn-ghost" href="' . h($settings_url) . '"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>' . h(lang('Channel templates')) . '</a>
    </nav>
    <form method="post" action="' . h($self_url) . '" id="ws-template-form" class="row g-4">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . (int) $template['id'] . '">
        <input type="hidden" name="body" id="ws-template-body" value="' . h($body_json) . '">

        <div class="col-12 col-lg-8 col-xxl-9 order-1">
            <div class="card mb-4">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-card-heading me-1" aria-hidden="true"></i>' . h(lang('The template')) . '</h2></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="ws_tpl_name">' . h(lang('Name')) . '</label>
                            <input class="form-control" type="text" name="name" id="ws_tpl_name" maxlength="100" required value="' . h($template['name']) . '">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="ws_tpl_description">' . h(lang('Description')) . '</label>
                            <input class="form-control" type="text" name="description" id="ws_tpl_description" maxlength="255" value="' . h($template['description']) . '">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ws_tpl_icon">' . h(lang('Icon')) . '</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi ' . h($icon) . '" id="ws_tpl_icon_preview" aria-hidden="true"></i></span>
                                <input class="form-control" type="text" name="icon" id="ws_tpl_icon" maxlength="40" placeholder="bi-briefcase" value="' . h($template['icon']) . '">
                            </div>
                            <div class="form-text">' . h(lang('A Bootstrap Icons class, such as bi-briefcase.')) . '</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ws_tpl_color">' . h(lang('Colour')) . '</label>
                            <select class="form-select" name="color" id="ws_tpl_color">' . $color_options . '</select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ws_tpl_department">' . h(lang('Department')) . '</label>
                            <select class="form-select" name="department_id" id="ws_tpl_department">' . $department_options . '</select>
                            <div class="form-text">' . h(lang('Suggested for the channel; a task given to the lead of the department goes to its lead.')) . '</div>
                        </div>
                        <div class="col-12">
                            <div class="form-label">' . h(lang('Who can see it')) . '</div>
                            ' . $kind_radio('public', lang('Everyone in the team'), lang('Everyone in the team can find it, read it and join it.')) . '
                            ' . $kind_radio('private', lang('Private'), lang('Only the people invited see it. The owner can open it to everyone later.')) . '
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>' . h(lang('Texts of the channel')) . '</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="ws_tpl_summary">' . h(lang('Summary')) . '</label>
                        <textarea class="form-control" name="summary" id="ws_tpl_summary" rows="4" data-ws-rich>' . h($template['summary']) . '</textarea>
                        <div class="form-text">' . h(lang('Written into the channel\'s summary when it has none yet.')) . '</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ws_tpl_pinned">' . h(lang('Pinned message')) . '</label>
                        <textarea class="form-control" name="pinned" id="ws_tpl_pinned" rows="3" data-ws-rich>' . h($template['pinned']) . '</textarea>
                        <div class="form-text">' . h(lang('Posted and pinned to the top of the channel when nothing is pinned there yet.')) . '</div>
                    </div>
                    <div>
                        <label class="form-label" for="ws_tpl_welcome">' . h(lang('Welcome message')) . '</label>
                        <textarea class="form-control" name="welcome" id="ws_tpl_welcome" rows="3" data-ws-rich>' . h($template['welcome']) . '</textarea>
                        <div class="form-text">' . h(lang('The first message of a channel made from the template; left empty, there is none.')) . '</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-check2-square me-1" aria-hidden="true"></i>' . h(lang('Tasks')) . '</h2>
                </div>
                <div class="card-body">
                    <p class="small text-body-secondary">' . h(lang('Days are counted in calendar days from the day the template is applied; 0 is that day.')) . '</p>
                    <div id="ws-template-tasks" class="ws-tpl-list"><div class="ws-empty">' . h(lang('Loading…')) . '</div></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>' . h(lang('Notes')) . '</h2>
                </div>
                <div class="card-body">
                    <p class="small text-body-secondary">' . h(lang('Each note is written as the notes of whoever applies the template and shared in the channel.')) . '</p>
                    <div id="ws-template-notes" class="ws-tpl-list"><div class="ws-empty">' . h(lang('Loading…')) . '</div></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4 col-xxl-3 order-2">
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="ws-tpl-row border-0 p-0">
                            <span class="ws-tpl-icon"' . (($hex !== '') ? ' style="background:' . h($hex) . '"' : '') . '><i class="bi ' . h($icon) . '" aria-hidden="true"></i></span>
                            <div class="ws-grow">
                                <div class="fw-semibold">' . h(($template['name'] !== '') ? $template['name'] : lang('New channel template')) . '</div>
                                <div class="small text-body-secondary">' . h($counts) . '</div>
                            </div>
                        </div>
                        ' . (!$is_new ? '<hr><div class="small text-body-secondary">' . h(lang(array('string' => 'used {var:1} times', 'vars' => (int) $template['uses']))) . '</div>' : '') . '
                        ' . (!empty($template['archived']) ? '<div class="mt-2"><span class="badge text-bg-secondary">' . h(lang('In the archive')) . '</span></div>' : '') . '
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 order-3">
            <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                <a class="btn btn-sm btn-ghost" href="' . h($settings_url) . '">' . h(lang('Cancel')) . '</a>
                <button type="submit" name="submit_save" value="Save" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save the template')) . '</button>
            </nav>
        </div>
    </form>
    <script type="application/json" id="ws-template-data">' . $editor_data . '</script>
</div>
</div>
</main>
' . ws_screen_assets($viewer, 'template') .
output_footer();

$liveform->remove_form();
