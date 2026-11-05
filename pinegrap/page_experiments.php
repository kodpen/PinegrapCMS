<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Experiments sheet of one designed page: set up an A/B experiment with
 * a variant draft, start, pause and end it, and publish the winner
 * (includes/fn/experiments_sheet.php). Reached from the page's row in the
 * pages list and from the visual editor's page settings; designers and
 * administrators.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('page_experiments');

$page_id = isset($_REQUEST['page_id']) && is_scalar($_REQUEST['page_id']) ? (int) $_REQUEST['page_id'] : 0;
$self = 'page_experiments.php?page_id=' . $page_id;

$heading = lang('Experiments');
$crumb = lang('Experiments');

// The frame of the screen, drawn once the page is known.
$shell = function () use (&$heading, &$crumb) {
    return pg_page_shell(array(
        'title' => lang('Experiments'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => $heading,
        'heading_description' => lang('Show part of the visitors a variant of this page and count which one reaches the goal more often. The variant is a draft you edit in the Visual Page Editor; search engines and signed-in staff always see the live page.'),
        'help' => 'visual-page-editor#editor',
        'breadcrumb' => array(
            array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => $crumb),
        ),
    ));
};

$stop = function ($message) use ($shell, $liveform) {
    echo $shell() . '
<main id="content" class="container-fluid">
    <div class="alert theme-warning my-7"><i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . h($message) . '</div>
</main>' . output_footer();

    $liveform->remove_form();
    exit();
};

if (!pg_experiment_sheet_ready()) {
    $stop(lang('Experiments need the software upgrade.'));
}

$live = pg_experiment_sheet_live_page($page_id);
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$action = ($is_post && isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';

// An experiment whose page left the site (Recycle Bin, deleted) can still be
// paused, ended or deleted, so its variant draft is not held for ever; the
// moves that need the live page (start, publishing a variant) refuse with
// their own message.
if (!$live && (!$is_post || ($action === '') || ($action === 'create'))) {
    $stop(lang('Experiments run on a page of a visual design that is on the site. Drafts and pages in the Recycle Bin have none.'));
}

$page_row = $live ? $live : db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . (int) $page_id . "' LIMIT 1");

if (is_array($page_row) && (check_edit_access($page_row['page_folder']) == false)) {
    log_activity(lang('access denied because user does not have access to modify folder'));
    output_error(lang('Access denied.'));
}

$page_label = is_array($page_row) ? (string) $page_row['page_name'] : (string) $page_id;

if ($live) {
    $crumb = $live['page_name'];
    $heading = h(lang(array('string' => 'Experiments: {var:1}', 'vars' => $live['page_name'])));
}

// ---- Actions ----------------------------------------------------------------

if ($is_post) {
    validate_token_field();

    // The results screen (experiments.php) posts its buttons here and is
    // shown again after the change.
    $back = $self;

    if ((($_POST['return'] ?? '') === 'results') && isset($_POST['experiment_id']) && is_scalar($_POST['experiment_id']) && ((int) $_POST['experiment_id'] > 0)) {
        $back = 'experiments.php?id=' . (int) $_POST['experiment_id'];
    }

    if ($action === 'create') {
        $liveform->add_fields_to_session();
        $clean = pg_experiment_sheet_clean($_POST);

        foreach ($clean['errors'] as $code) {
            $liveform->mark_error($code === 'goal' ? 'goal_kind' : ($code === 'weight' ? 'weight_b' : ($code === 'target' ? 'goal_target' : 'name')), pg_experiment_sheet_error_message($code));
        }

        if (!$clean['errors']) {
            $draft_id = isset($_POST['draft_page_id']) && is_scalar($_POST['draft_page_id']) ? (int) $_POST['draft_page_id'] : 0;
            $made = pg_experiment_sheet_create($live, $clean['values'], $user, $draft_id);

            if ($made['ok']) {
                log_activity(lang(array('string' => 'experiment "{var:1}" was set up on page {var:2}', 'vars' => array($clean['values']['name'], $live['page_name']))));
                $liveform->remove_form();
                $liveform = new liveform('page_experiments');
                $liveform->add_notice(lang('The experiment is set up. Edit the variant draft, then start the experiment.'));
            } else {
                $liveform->mark_error((($made['field'] ?? '') !== '') ? $made['field'] : 'name', $made['error']);
            }
        }

        go(PATH . SOFTWARE_DIRECTORY . '/' . $back);
    }

    $experiment = pg_experiment_sheet_get(isset($_POST['experiment_id']) && is_scalar($_POST['experiment_id']) ? (int) $_POST['experiment_id'] : 0);

    if (!$experiment || ((int) $experiment['page_id'] !== (int) $page_id)) {
        $liveform->add_error(lang('The experiment was not found.'));
        go(PATH . SOFTWARE_DIRECTORY . '/' . $back);
    }

    if (!in_array($action, pg_experiment_sheet_actions($experiment['status']), true)) {
        $liveform->add_error(lang('The experiment cannot make that change now.'));
        go(PATH . SOFTWARE_DIRECTORY . '/' . $back);
    }

    if ($action === 'delete') {
        $done = array('ok' => pg_experiment_sheet_delete($experiment), 'error' => lang('The experiment was changed meanwhile; look again.'));
        $notice = lang('The experiment was deleted; its variant stays in Drafts.');
    } elseif ($action === 'publish_winner') {
        $key = isset($_POST['variant_key']) && is_string($_POST['variant_key']) ? $_POST['variant_key'] : '';
        $done = pg_experiment_sheet_publish_winner($experiment, $key, $user);
        $notice = ($key === 'a')
            ? lang('The experiment ended; the live page stays as it is.')
            : lang('The variant is published on the live page and the experiment ended. The draft stays in Drafts.');
    } else {
        $done = pg_experiment_sheet_move($experiment, pg_experiment_sheet_action_status($action));
        $notice = lang('The experiment was updated.');
    }

    if ($done['ok']) {
        log_activity(lang(array('string' => 'experiment "{var:1}" on page {var:2}: {var:3}', 'vars' => array($experiment['name'], $page_label, $action))));
        $liveform->add_notice($notice);
    } else {
        $liveform->add_error($done['error']);
    }

    go(PATH . SOFTWARE_DIRECTORY . '/' . $back);
}

// ---- Experiments ------------------------------------------------------------

$status_labels = array(
    'draft' => lang('Being set up'),
    'running' => lang('Running'),
    'paused' => lang('Paused'),
    'ended' => lang('Ended'),
);

$goal_labels = array(
    'form' => lang('A form is sent'),
    'order' => lang('An order is completed'),
    'click' => lang('An element with a goal name is clicked'),
    'page' => lang('Another page is reached'),
);

$action_buttons = array(
    'start' => array('play-fill', 'theme-primary'),
    'pause' => array('pause-fill', 'theme-secondary'),
    'end' => array('stop-fill', 'theme-secondary'),
    'delete' => array('trash', 'theme-danger'),
);

$action_labels = array(
    'start' => lang('Start the experiment'),
    'pause' => lang('Pause'),
    'end' => lang('End without a winner'),
    'delete' => lang('Delete'),
);

$experiments = pg_experiment_sheet_list($live['page_id']);
$has_open = false;
$output_experiments = '';

foreach ($experiments as $experiment) {
    $status = $experiment['status'];
    $has_open = $has_open || ($status !== 'ended');
    $actions = pg_experiment_sheet_actions($status);
    $hidden = get_token_field() . '<input type="hidden" name="experiment_id" value="' . (int) $experiment['id'] . '">';

    $rows = '';

    foreach ($experiment['variants'] as $key => $variant) {
        $is_live = ((int) $variant['draft_page_id'] === 0);
        $views = (int) $variant['views'];
        $goals = (int) $variant['goals'];

        if ($is_live) {
            $name = h(lang('The live page'));
        } elseif ((string) $variant['page_name'] === '') {
            $name = '<span class="fg-danger">' . h(lang('The variant draft was deleted.')) . '</span>';
        } else {
            $name = h($variant['page_name']) . ' <a class="ms-1" href="' . h('edit_system_style.php?id=' . (int) $variant['page_style'] . '&page=' . (int) $variant['draft_page_id']) . '">' . h(lang('Edit')) . '</a>';
        }

        $winner = ((int) $experiment['winner_variant_id'] === (int) $variant['id'])
            ? ' <span class="badge theme-success">' . h(lang('Winner')) . '</span>' : '';

        $publish = '';

        if (in_array('publish_winner', $actions, true)) {
            $publish = '<form method="post" class="d-inline" onsubmit="return confirm(' . h(json_encode($is_live
                    ? lang('End the experiment and keep the live page as it is?')
                    : lang('Publish this variant\'s design on the live page and end the experiment?'))) . ');">'
                . $hidden . '<input type="hidden" name="action" value="publish_winner"><input type="hidden" name="variant_key" value="' . h($key) . '">'
                . '<button type="submit" class="btn btn-sm btn-outline theme-success"><i class="bi bi-trophy me-1" aria-hidden="true"></i>' . h(lang('Publish the winner')) . '</button></form>';
        }

        $rows .= '<tr>
            <td class="align-middle text-uppercase fw-bold">' . h($key) . '</td>
            <td class="align-middle">' . $name . $winner . '</td>
            <td class="align-middle text-end">' . h(pg_experiment_percent((int) $variant['weight'], 0)) . '</td>
            <td class="align-middle text-end">' . $views . '</td>
            <td class="align-middle text-end">' . $goals . '</td>
            <td class="align-middle text-end">' . ($views > 0 ? h(pg_experiment_percent(100 * $goals / $views, 1)) : '&ndash;') . '</td>
            <td class="align-middle text-end">' . $publish . '</td>
        </tr>';
    }

    $buttons = '';

    foreach ($actions as $action) {
        if (!isset($action_buttons[$action])) {
            continue;
        }

        $confirm = ($action === 'delete' || $action === 'end')
            ? ' onsubmit="return confirm(' . h(json_encode($action === 'delete' ? lang('Delete this experiment?') : lang('End the experiment without publishing a winner?'))) . ');"' : '';

        $buttons .= '<form method="post" class="d-inline"' . $confirm . '>' . $hidden . '<input type="hidden" name="action" value="' . h($action) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline ' . $action_buttons[$action][1] . ' me-2"><i class="bi bi-' . $action_buttons[$action][0] . ' me-1" aria-hidden="true"></i>' . h(($action === 'start' && $status === 'paused') ? lang('Resume') : $action_labels[$action]) . '</button></form>';
    }

    $dates = array();

    if ((int) $experiment['started_at'] > 0) {
        $dates[] = lang(array('string' => 'Started {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $experiment['started_at']))));
    }

    if ((int) $experiment['ended_at'] > 0) {
        $dates[] = lang(array('string' => 'Ended {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $experiment['ended_at']))));
    }

    $goal = pg_experiment_goal_text($experiment, $goal_labels);

    $theme = pg_experiment_chip_theme($status);

    $output_experiments .= '
            <div class="card my-7">
                <div class="card-header d-flex flex-wrap align-items-center gap-3">
                    <a class="fw-bold text-break min-w-0" href="' . h('experiments.php?id=' . (int) $experiment['id']) . '">' . h($experiment['name']) . '</a>
                    <span class="badge ' . ($theme !== '' ? 'theme-' . $theme : 'bg-1 fg-body border') . '">' . h($status_labels[$status]) . '</span>
                    <span class="small fg-secondary">' . h(lang(array('string' => 'Goal: {var:1}', 'vars' => $goal))) . '</span>
                    <span class="small fg-secondary">' . implode(' &middot; ', $dates) . '</span>
                    <div class="ms-auto">' . $buttons . '</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>' . h(lang('Variant')) . '</th><th>' . h(lang('Page')) . '</th><th class="text-end">' . h(lang('Share of visitors')) . '</th><th class="text-end">' . h(lang('Views')) . '</th><th class="text-end">' . h(lang('Conversions')) . '</th><th class="text-end">' . h(lang('Rate')) . '</th><th></th></tr></thead>
                            <tbody>' . $rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>';
}

// ---- New experiment ---------------------------------------------------------

$output_form = '';

if (!$has_open) {
    $goal_options = array();

    foreach ($goal_labels as $kind => $label) {
        $goal_options[h($label)] = $kind;
    }

    $draft_options = array(h(lang('A new copy of the live page')) => '0');

    foreach (pg_experiment_sheet_variant_choices($live) as $choice) {
        $draft_options[h($choice['page_name'])] = (string) $choice['page_id'];
    }

    if (!$liveform->get_field_value('weight_b')) {
        $liveform->assign_field_value('weight_b', '50');
    }

    // The form's submit button sits in the sticky save bar below the
    // columns and reaches the form through its form attribute.
    $output_form = '
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('New experiment')) . '</div>
                <div class="card-body pt-0">
                    <form method="post" action="' . h($self) . '" id="page_experiment_new">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" value="create">
                        <div class="row g-5">
                            <div class="col-12 md:col-6">
                                <div class="form-field">
                                    <label for="name" class="form-label">' . h(lang('Name')) . '</label>
                                    ' . $liveform->output_field(array('type' => 'text', 'name' => 'name', 'id' => 'name', 'maxlength' => '100', 'class' => 'form-control', 'required' => 'required')) . '
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="form-field">
                                    <label for="draft_page_id" class="form-label">' . h(lang('Variant B')) . '</label>
                                    ' . $liveform->output_field(array('type' => 'select', 'name' => 'draft_page_id', 'id' => 'draft_page_id', 'options' => $draft_options, 'class' => 'form-control', 'aria-describedby' => 'draft_page_id_help')) . '
                                    <div id="draft_page_id_help" class="form-text">' . h(lang('A draft of the same design. A new copy is made in Drafts; edit it in the Visual Page Editor before you start.')) . '</div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="form-field">
                                    <label for="goal_kind" class="form-label">' . h(lang('Counts as a conversion')) . '</label>
                                    ' . $liveform->output_field(array('type' => 'select', 'name' => 'goal_kind', 'id' => 'goal_kind', 'options' => $goal_options, 'class' => 'form-control')) . '
                                </div>
                            </div>
                            <div class="col-12 md:col-6" id="goal_target_field">
                                <div class="form-field">
                                    <label for="goal_target" class="form-label"><span data-goal-kind="click">' . h(lang('Goal name')) . '</span><span data-goal-kind="page">' . h(lang('Goal page id')) . '</span></label>
                                    ' . $liveform->output_field(array('type' => 'text', 'name' => 'goal_target', 'id' => 'goal_target', 'maxlength' => '64', 'class' => 'form-control', 'aria-describedby' => 'goal_target_help')) . '
                                    <div id="goal_target_help" class="form-text"><span data-goal-kind="click">' . h(lang('The goal name set on the button or link.')) . '</span><span data-goal-kind="page">' . h(lang('The id of a page on the site, as in the pages list.')) . '</span></div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6">
                                <div class="form-field">
                                    <label for="weight_b" class="form-label">' . h(lang('Share of visitors who see variant B (%)')) . '</label>
                                    ' . $liveform->output_field(array('type' => 'number', 'name' => 'weight_b', 'id' => 'weight_b', 'min' => '1', 'max' => '99', 'step' => '1', 'class' => 'form-control', 'required' => 'required')) . '
                                </div>
                            </div>
                        </div>
                    </form>
                    <script>
                        // The goal target only means something for a click
                        // or a page goal; its label and help follow the kind.
                        (function () {
                            var kind = document.getElementById("goal_kind");
                            var field = document.getElementById("goal_target_field");
                            var input = document.getElementById("goal_target");

                            if (!kind || !field || !input) {
                                return;
                            }

                            var sync = function () {
                                var value = kind.value;
                                var used = (value === "click") || (value === "page");

                                field.classList.toggle("d-none", !used);
                                input.disabled = !used;

                                field.querySelectorAll("[data-goal-kind]").forEach(function (el) {
                                    el.classList.toggle("d-none", el.getAttribute("data-goal-kind") !== value);
                                });
                            };

                            kind.addEventListener("change", sync);
                            sync();
                        })();
                    </script>
                </div>
            </div>';
}

// What the status column reads: the page and how many of its experiments
// are in each state.
$summary_total = count($experiments);
$summary_running = 0;
$summary_ended = 0;

foreach ($experiments as $experiment) {
    if ($experiment['status'] === 'running') {
        $summary_running++;
    } elseif ($experiment['status'] === 'ended') {
        $summary_ended++;
    }
}

// The save bar is drawn only with the New experiment form: while an
// experiment is open the screen has nothing to save.
$output_save_bar = ($output_form !== '') ? '
        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="submit" form="page_experiment_new" class="btn my-1 btn-solid theme-primary"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Set up the experiment')) . '</span></button>
                    </div>
                </div>
            </nav>
        </div>' : '';

echo $shell() . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <div class="row">
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
                <a class="btn btn-sm btn-text theme-secondary" href="' . h(pg_page_edit_url_by_id($live['page_id'])) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit the live page')) . '</a>
                <a class="btn btn-sm btn-text theme-secondary" href="' . h(OUTPUT_PATH . encode_url_path($live['page_name'])) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang('View the page')) . '</a>
                <a class="btn btn-sm btn-text theme-secondary" href="experiments.php"><i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>' . h(lang('All experiments')) . '</a>
                <div class="pg-toolbar-grow"></div>
            </nav>
            ' . $output_form . $output_experiments . '
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky my-7" style="top:3.3rem;">
                <div class="card">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                    <div class="card-body pt-0">
                        <div class="fw-semibold text-break mb-5">' . h($live['page_name']) . '</div>
                        <dl class="row small mb-0">
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Experiments')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($summary_total, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Running')) . '</dt>
                            <dd class="col-4 text-end mb-2' . ($summary_running ? ' fg-success fw-semibold' : '') . '">' . pg_format_number($summary_running, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Ended')) . '</dt>
                            <dd class="col-4 text-end mb-0">' . pg_format_number($summary_ended, 0) . '</dd>
                        </dl>
                        <div class="form-text mt-5">' . h($has_open
                            ? lang('A page has one open experiment at a time; end or delete it to set up the next one.')
                            : lang('Search engines and signed-in staff always see the live page.')) . '</div>
                    </div>
                </div>
            </div>
        </div>' . $output_save_bar . '
    </div>
</main>
' . output_footer();

$liveform->remove_form();
