<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The results of the site's page experiments: the list of every experiment
 * with its page, status and dates, and per experiment the views,
 * conversions and rate of each variant, the lift against the live page, a
 * plain sentence from a two-proportion test and the daily rates
 * (includes/fn/experiments_results.php). Start, pause, end and "Publish the
 * winner" post to the page's Experiments sheet (page_experiments.php), which
 * returns here. Designers and administrators.
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

$experiment_id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;

$heading = lang('Experiments');
$crumbs = array(array('label' => lang('Experiments')));

$shell = function () use (&$heading, &$crumbs) {
    return pg_page_shell(array(
        'title' => lang('Experiments'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => $heading,
        'heading_description' => lang('Every A/B experiment of the site\'s pages: how often each version reached the goal, and whether the difference is more than chance.'),
        'help' => 'visual-page-editor#editor',
        'breadcrumb' => $crumbs,
    ));
};

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

$badge = function ($status) use ($status_labels) {
    $theme = pg_experiment_chip_theme($status);

    return '<span class="badge ' . ($theme !== '' ? 'theme-' . $theme : 'bg-1 fg-body border') . '">' . h(isset($status_labels[$status]) ? $status_labels[$status] : $status) . '</span>';
};

$percent = function ($rate) {
    return ($rate === null) ? '&ndash;' : h(pg_experiment_percent($rate * 100, 1));
};

// A page in the Recycle Bin carries an internal name (pgbin_…); say where it
// is instead.
$bin_folders = function_exists('pg_recycle_bin_folder_ids') ? array_map('intval', (array) pg_recycle_bin_folder_ids()) : array();

$page_text = function ($page_name, $page_folder) use ($bin_folders) {
    if ((string) $page_name === '') {
        return '<span class="fg-secondary">' . h(lang('The page was deleted.')) . '</span>';
    }

    if (in_array((int) $page_folder, $bin_folders, true)) {
        return '<span class="fg-secondary">' . h(lang('The page is in the Recycle Bin.')) . '</span>';
    }

    return h($page_name);
};

$output = '';

if (!pg_experiment_sheet_ready()) {
    $output = '<div class="alert theme-warning my-7"><i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . h(lang('Experiments need the software upgrade.')) . '</div>';

} elseif ($experiment_id <= 0) {

    // ---- The list -----------------------------------------------------------

    $rows = '';

    foreach (pg_experiment_results_list() as $row) {
        if (((string) $row['page_name'] !== '') && !check_edit_access($row['page_folder'])) {
            continue;
        }

        $dates = array();

        if ((int) $row['started_at'] > 0) {
            // get_relative_time() gives a <time> element, printed as markup.
            $dates[] = lang(array('string' => 'Started {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $row['started_at']))));
        }

        if ((int) $row['ended_at'] > 0) {
            $dates[] = lang(array('string' => 'Ended {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $row['ended_at']))));
        }

        $rows .= '<tr>
            <td class="align-middle text-break" style="min-width: 10rem"><a href="' . h('experiments.php?id=' . (int) $row['id']) . '">' . h($row['name']) . '</a></td>
            <td class="align-middle text-break">' . $page_text($row['page_name'], $row['page_folder']) . '</td>
            <td class="align-middle">' . $badge($row['status']) . '</td>
            <td class="align-middle small fg-secondary">' . implode(' &middot; ', $dates) . '</td>
            <td class="align-middle text-end">' . (int) $row['views'] . '</td>
            <td class="align-middle text-end">' . (int) $row['goals'] . '</td>
        </tr>';
    }

    $output = ($rows === '')
        ? '<div class="alert theme-info my-7"><i class="bi bi-info-circle me-3" aria-hidden="true"></i>' . h(lang('No experiments yet. Open a design page in the pages list and choose Experiments to set one up.')) . '</div>'
        : '<div class="card my-7"><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>' . h(lang('Name')) . '</th><th>' . h(lang('Page')) . '</th><th>' . h(lang('Status')) . '</th><th>' . h(lang('Dates')) . '</th><th class="text-end">' . h(lang('Views')) . '</th><th class="text-end">' . h(lang('Conversions')) . '</th></tr></thead>
            <tbody>' . $rows . '</tbody></table></div></div></div>';

} else {

    // ---- One experiment -----------------------------------------------------

    $experiment = pg_experiment_sheet_get($experiment_id);
    $live = $experiment ? db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . (int) $experiment['page_id'] . "' LIMIT 1") : null;
    // Start, publishing a variant and the sheet need the page on the site;
    // pause, end and keeping the live page work without it.
    $is_live = $experiment ? (bool) pg_experiment_sheet_live_page($experiment['page_id']) : false;

    if (!$experiment) {
        $output = '<div class="alert theme-warning my-7">' . h(lang('The experiment was not found.')) . '</div>';
    } elseif (is_array($live) && !check_edit_access($live['page_folder'])) {
        log_activity(lang('access denied because user does not have access to modify folder'));
        output_error(lang('Access denied.'));
    } else {
        $heading = h($experiment['name']);
        $crumbs = array(
            array('label' => lang('Experiments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/experiments.php'),
            array('label' => $experiment['name']),
        );

        $variants = array();

        foreach (pg_experiment_sheet_list($experiment['page_id']) as $item) {
            if ((int) $item['id'] === $experiment_id) {
                $variants = $item['variants'];
            }
        }

        $results = pg_experiment_results_rows($variants);
        $status = $experiment['status'];
        $actions = pg_experiment_sheet_actions($status);
        $sheet = 'page_experiments.php?page_id=' . (int) $experiment['page_id'];
        $hidden = get_token_field() . '<input type="hidden" name="experiment_id" value="' . (int) $experiment_id . '"><input type="hidden" name="return" value="results">';
        $colors = array();
        $palette = array('var(--pg-secondary)', 'var(--pg-primary)', 'var(--pg-success)', 'var(--pg-warning)', 'var(--pg-info)');
        $rows = '';
        $sentences = '';
        $i = 0;

        foreach ($results as $key => $variant) {
            $colors[(int) $variant['id']] = $palette[$i % count($palette)];
            $i++;

            $name = $variant['control']
                ? h(lang('The live page'))
                : (((string) ($variant['page_name'] ?? '') !== '') ? h($variant['page_name']) : '<span class="fg-danger">' . h(lang('The variant draft was deleted.')) . '</span>');

            $winner = ((int) $experiment['winner_variant_id'] === (int) $variant['id'])
                ? ' <span class="badge theme-success">' . h(lang('Winner')) . '</span>' : '';

            $lift = '&ndash;';

            if ($variant['lift'] !== null) {
                $lift = h(lang(array('string' => '{var:1}%', 'vars' => ($variant['lift'] >= 0 ? '+' : '') . pg_experiment_number($variant['lift'] * 100, 1))));
            }

            $publish = '';

            if (in_array('publish_winner', $actions, true) && ($is_live || $variant['control'])) {
                $publish = '<form method="post" action="' . h($sheet) . '" class="d-inline" onsubmit="return confirm(' . h(json_encode($variant['control']
                        ? lang('End the experiment and keep the live page as it is?')
                        : lang('Publish this variant\'s design on the live page and end the experiment?'))) . ');">'
                    . $hidden . '<input type="hidden" name="action" value="publish_winner"><input type="hidden" name="variant_key" value="' . h($key) . '">'
                    . '<button type="submit" class="btn btn-sm btn-outline theme-success"><i class="bi bi-trophy me-1" aria-hidden="true"></i>' . h(lang('Publish the winner')) . '</button></form>';
            }

            $rows .= '<tr>
                <td class="align-middle text-uppercase fw-bold"><span class="d-inline-block rounded-circle me-2" style="width:.7rem;height:.7rem;background:' . h($colors[(int) $variant['id']]) . '" aria-hidden="true"></span>' . h($key) . '</td>
                <td class="align-middle">' . $name . $winner . '</td>
                <td class="align-middle text-end">' . (int) $variant['views'] . '</td>
                <td class="align-middle text-end">' . (int) $variant['goals'] . '</td>
                <td class="align-middle text-end">' . $percent($variant['rate']) . '</td>
                <td class="align-middle text-end">' . ($variant['control'] ? '<span class="fg-secondary small">' . h(lang('Control')) . '</span>' : $lift) . '</td>
                <td class="align-middle text-end">' . $publish . '</td>
            </tr>';

            if ($variant['result'] !== null) {
                $kind = pg_experiment_verdict_kind($variant['result']);
                $icon = ($kind === 'ahead') ? 'graph-up-arrow fg-success' : (($kind === 'behind') ? 'graph-down-arrow fg-danger' : 'hourglass-split fg-secondary');
                $sentences .= '<p class="mb-2"><i class="bi bi-' . $icon . ' me-2" aria-hidden="true"></i>' . h(pg_experiment_verdict_sentence($variant['result'], $key)) . '</p>';
            }
        }

        // The buttons of the sheet, posted there and back here.
        $buttons = '';
        $labels = array(
            'start' => array(($status === 'paused') ? lang('Resume') : lang('Start the experiment'), 'play-fill', 'theme-primary'),
            'pause' => array(lang('Pause'), 'pause-fill', 'theme-secondary'),
            'end' => array(lang('End without a winner'), 'stop-fill', 'theme-secondary'),
        );

        foreach ($actions as $action) {
            if (!isset($labels[$action]) || (($action === 'start') && !$is_live)) {
                continue;
            }

            $confirm = ($action === 'end') ? ' onsubmit="return confirm(' . h(json_encode(lang('End the experiment without publishing a winner?'))) . ');"' : '';
            $buttons .= '<form method="post" action="' . h($sheet) . '" class="d-inline"' . $confirm . '>' . $hidden . '<input type="hidden" name="action" value="' . h($action) . '">'
                . '<button type="submit" class="btn btn-sm btn-text ' . $labels[$action][2] . '"><i class="bi bi-' . $labels[$action][1] . ' me-1" aria-hidden="true"></i>' . h($labels[$action][0]) . '</button></form>';
        }

        // The daily rates: a picture and the same figures as a table.
        $range = pg_experiment_chart_range($experiment, time());
        $series = pg_experiment_daily_series(pg_experiment_results_days($experiment_id, $range['from'], $range['to']), array_keys($colors), $range['from'], $range['to']);
        $chart = pg_experiment_chart_svg($series, $colors);
        $output_chart = '';

        if ($chart !== '') {
            $head = '<th scope="col">' . h(lang('Date')) . '</th>';

            foreach ($results as $key => $variant) {
                $head .= '<th scope="col" class="text-end">' . h(strtoupper($key)) . '</th>';
            }

            $body = '';

            foreach ($series['days'] as $d => $day) {
                $cells = '';

                foreach ($results as $variant) {
                    $rate = $series['rates'][(int) $variant['id']][$d];
                    $cells .= '<td class="text-end">' . (($rate === null) ? '&ndash;' : h(pg_experiment_percent($rate, 1))) . '</td>';
                }

                $body .= '<tr><th scope="row">' . h(get_absolute_time(array('timestamp' => strtotime($day), 'type' => 'date', 'format' => 'plain_text'))) . '</th>' . $cells . '</tr>';
            }

            $output_chart = '
                <div class="card my-7">
                    <div class="card-header">' . h(lang('Conversion rate per day')) . '</div>
                    <div class="card-body">' . $chart . '
                        <details class="mt-3"><summary class="small fg-secondary">' . h(lang('Show the figures')) . '</summary>
                            <div class="table-responsive"><table class="table table-sm mb-0 mt-3"><caption class="visually-hidden">' . h(lang('Conversion rate per day')) . '</caption><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>
                        </details>
                    </div>
                </div>';
        }

        $goal = pg_experiment_goal_text($experiment, $goal_labels);

        $output = '
            <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
                ' . $buttons . '
                ' . ($is_live ? '<a class="btn btn-sm btn-text theme-secondary" href="' . h($sheet) . '"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('Experiment settings of the page')) . '</a>' : '') . '
                <div class="pg-toolbar-grow"></div>
            </nav>
            <p class="d-flex flex-wrap gap-3 align-items-center">' . $badge($status) . '
                <span class="small fg-secondary text-break min-w-0">' . (is_array($live) ? $page_text($live['page_name'], $live['page_folder']) : $page_text('', 0)) . '</span>
                <span class="small fg-secondary">' . h(lang(array('string' => 'Goal: {var:1}', 'vars' => $goal))) . '</span></p>
            ' . ($sentences !== '' ? '<div class="card my-7"><div class="card-body">' . $sentences . '</div></div>' : '') . '
            <div class="card my-7"><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>' . h(lang('Variant')) . '</th><th>' . h(lang('Page')) . '</th><th class="text-end">' . h(lang('Views')) . '</th><th class="text-end">' . h(lang('Conversions')) . '</th><th class="text-end">' . h(lang('Rate')) . '</th><th class="text-end">' . h(lang('Change against the live page')) . '</th><th></th></tr></thead>
                <tbody>' . $rows . '</tbody></table></div></div></div>
            ' . $output_chart;
    }
}

echo $shell() . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output . '
        </div>
    </div>
</main>
' . output_footer();

$liveform->remove_form();
