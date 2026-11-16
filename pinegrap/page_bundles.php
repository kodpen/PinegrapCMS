<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Bundles screen: release bundles, each a named list of drafts of a
 * visual design that are prepared together and later published at one
 * moment (includes/fn/page_bundles.php for the rules, page_bundles_screen.php
 * for what this screen reads). Without an id it lists the bundles and makes
 * a new one; with one it shows a bundle's pages, adds a copy of a live page
 * or a draft, removes a page, sets the name and the publish time, and
 * deletes the bundle. Designers and administrators.
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
$liveform = new liveform('page_bundles');

$bundle_id = isset($_REQUEST['id']) && is_scalar($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$list_url = PATH . SOFTWARE_DIRECTORY . '/page_bundles.php';
$self = $list_url . (($bundle_id > 0) ? '?id=' . $bundle_id : '');

$heading = lang('Bundles');
$crumbs = array(
    array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
    array('label' => lang('Bundles')),
);

// The frame of the screen, drawn once the bundle is known.
$shell = function () use (&$heading, &$crumbs) {
    return pg_page_shell(array(
        'title' => lang('Bundles'),
        'extra classes' => 'page pg-measure-edit',
        'icon' => 'page',
        'heading' => $heading,
        'heading_description' => lang('Prepare several pages as drafts, look at them together and publish them at one moment. A bundle holds new pages and copies of pages on the site; the site stays as it is until the bundle is published.'),
        'help' => 'visual-page-editor#editor',
        'breadcrumb' => $crumbs,
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

if (!pg_page_bundles_ready()) {
    $stop(lang('Release bundles are not available yet. Please run the upgrade.'));
}

$bundle = ($bundle_id > 0) ? pg_page_bundle_get($bundle_id) : null;

if (($bundle_id > 0) && !$bundle) {
    $stop(lang('The bundle was not found.'));
}

// ---- Actions ----------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $posted = function ($name) {
        return isset($_POST[$name]) && is_scalar($_POST[$name]) ? (string) $_POST[$name] : '';
    };

    if ($action === 'create') {
        $made = pg_page_bundle_create($posted('name'), $user);

        if (!$made['ok']) {
            $liveform->add_error($made['error']);
            go($list_url);
        }

        log_activity(lang(array('string' => 'release bundle "{var:1}" was made', 'vars' => array(pg_page_bundle_clean_name($posted('name'))))));
        $liveform->add_notice(lang('The bundle is made. Add the pages it publishes together.'));
        go($list_url . '?id=' . (int) $made['id']);
    }

    if (!$bundle) {
        $liveform->add_error(lang('The bundle was not found.'));
        go($list_url);
    }

    $done = array('ok' => false, 'error' => lang('The bundle cannot make that change now.'));
    $notice = '';
    $back = $self;

    if ($action === 'settings') {
        $done = pg_page_bundle_rename($bundle, $posted('name'));
        $time = pg_page_bundle_time_from_form($posted('publish_at'));

        if ($done['ok'] && ($time < 0)) {
            $done = array('ok' => false, 'error' => lang('The publish time is not a date and time.'));
        } elseif ($done['ok'] && ($time !== (int) $bundle['publish_at'])) {
            $done = pg_page_bundle_set_time(pg_page_bundle_get($bundle['id']), $time, $user);
        }

        $notice = lang('The bundle was saved.');
    } elseif ($action === 'add_copy') {
        $done = pg_page_bundle_add_copy($bundle, (int) $posted('live_page_id'), $user);
        $notice = lang('A copy of the page was made in Drafts and added to the bundle. Edit the copy; the page on the site stays as it is.');
    } elseif ($action === 'add_draft') {
        $done = pg_page_bundle_add_draft($bundle, (int) $posted('draft_page_id'), $user);
        $notice = !empty($done['time_cleared'])
            ? lang('The draft was added to the bundle. Its own publish time was cleared: the bundle publishes it.')
            : lang('The draft was added to the bundle.');
    } elseif ($action === 'remove') {
        $done = pg_page_bundle_remove_item($bundle, (int) $posted('item_id'));
        $notice = lang('The page was removed from the bundle; it stays in Drafts.');
    } elseif ($action === 'delete') {
        $done = pg_page_bundle_delete($bundle);
        $notice = lang('The bundle was deleted; its drafts stay in Drafts.');
        $back = $list_url;
    }

    if ($done['ok']) {
        log_activity(lang(array('string' => 'release bundle "{var:1}": {var:2}', 'vars' => array($bundle['name'], $action))));
        $liveform->add_notice($notice);
    } else {
        $liveform->add_error($done['error']);
        $back = $self;
    }

    go($back);
}

// ---- What every view reads ----------------------------------------------------

$status_badge = function ($status) {
    $theme = pg_page_bundle_status_theme($status);

    return '<span class="badge ' . ($theme !== '' ? 'theme-' . $theme : 'bg-1 fg-body border') . '">' . h(pg_page_bundle_status_label($status)) . '</span>';
};

$time_text = function ($time) {
    return ((int) $time > 0) ? prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $time), 'date and time') : '';
};

// A dialog of the screen: its form posts here and the page is drawn again.
$dialog = function ($id, $icon, $title, $action, $body, $submit) {
    return '
<dialog class="dialog pg-dialog-centered" id="' . $id . '" aria-labelledby="' . $id . '_label">
    <form method="post" class="d-contents">
        ' . get_token_field() . '
        <input type="hidden" name="action" value="' . h($action) . '">
        <div class="dialog-header">
            <h5 class="dialog-title" id="' . $id . '_label"><i class="bi bi-' . $icon . ' me-3" aria-hidden="true"></i>' . h($title) . '</h5>
            <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
        </div>
        <div class="dialog-body">' . $body . '</div>
        <div class="dialog-footer">
            <button type="button" class="btn btn-solid theme-secondary" data-bs-dismiss="dialog">' . h(lang('Cancel')) . '</button>
            ' . $submit . '
        </div>
    </form>
</dialog>';
};

$page_select = function ($id, $name, $label, $help, $choices, $empty) {
    if (!$choices) {
        return array('<p class="fg-secondary mb-0">' . h($empty) . '</p>', false);
    }

    $options = '<option value="">' . h(lang('Choose a page')) . '</option>';

    foreach ($choices as $choice) {
        $options .= '<option value="' . (int) $choice['page_id'] . '">' . h($choice['page_name']) . '</option>';
    }

    return array('
        <div class="form-field">
            <label for="' . $id . '" class="form-label">' . h($label) . '</label>
            <select id="' . $id . '" name="' . $name . '" class="form-control" required data-pg-combobox data-placeholder="' . h(lang('Choose a page')) . '" aria-describedby="' . $id . '_help">' . $options . '</select>
            <div id="' . $id . '_help" class="form-text">' . h($help) . '</div>
        </div>', true);
};

// ---- The list of bundles ------------------------------------------------------

if (!$bundle) {
    $rows = '';
    $bundles = pg_page_bundle_list();

    foreach ($bundles as $row) {
        $when = ((int) $row['publish_at'] > 0) ? h($time_text($row['publish_at']))
            : (((int) $row['published_at'] > 0) ? h($time_text($row['published_at'])) : '&ndash;');

        $rows .= '<tr>
            <td class="align-middle"><a class="text-break" href="' . h($list_url . '?id=' . (int) $row['id']) . '">' . h($row['name']) . '</a></td>
            <td class="align-middle">' . $status_badge($row['status']) . '</td>
            <td class="align-middle text-end">' . pg_format_number((int) $row['item_count'], 0) . '</td>
            <td class="align-middle text-nowrap d-none md:d-table-cell">' . $when . '</td>
            <td class="align-middle text-nowrap fg-secondary d-none md:d-table-cell">' . get_relative_time(array('timestamp' => (int) $row['updated_at'])) . '</td>
        </tr>';
    }

    $output_list = $rows
        ? '<div class="table-responsive">
                <table class="table table-sm mb-0 pg-card-table">
                    <thead><tr><th>' . h(lang('Name')) . '</th><th>' . h(lang('Status')) . '</th><th class="text-end">' . h(lang('Pages')) . '</th><th class="d-none md:d-table-cell">' . h(lang('Publish time')) . '</th><th class="d-none md:d-table-cell">' . h(lang('Changed')) . '</th></tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>'
        : '<p class="fg-secondary mb-0 px-5 pb-5">' . h(lang('No bundle yet. Make one for a campaign or a rearrangement that changes several pages at once.')) . '</p>';

    $output_new = $dialog('page_bundle_new', 'collection', lang('New bundle'), 'create', '
            <div class="form-field">
                <label for="page_bundle_name" class="form-label">' . h(lang('Name')) . '</label>
                <input type="text" id="page_bundle_name" name="name" maxlength="100" class="form-control" required autofocus>
                <div class="form-text">' . h(lang('For example, the spring campaign.')) . '</div>
            </div>',
        '<button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . h(lang('Make the bundle')) . '</button>');

    echo $shell() . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
        <button type="button" class="btn btn-sm btn-solid theme-primary" data-bs-toggle="dialog" data-bs-target="#page_bundle_new"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . h(lang('New bundle')) . '</button>
        <div class="pg-toolbar-grow"></div>
    </nav>
    <div class="card my-7">
        <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Bundles')) . '</h2>
        <div class="card-body p-0">' . $output_list . '</div>
    </div>
</main>
' . $output_new . '
' . output_footer();

    $liveform->remove_form();
    exit();
}

// ---- One bundle ----------------------------------------------------------------

$heading = h(lang(array('string' => 'Bundle: {var:1}', 'vars' => $bundle['name'])));
$crumbs = array(
    array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
    array('label' => lang('Bundles'), 'url' => $list_url),
    array('label' => $bundle['name']),
);

$may_change = pg_page_bundle_may_change($bundle['status']);
$items = pg_page_bundle_screen_items($bundle['id']);
$rows = '';
$count_new = 0;
$count_replacement = 0;
$count_changed = 0;

foreach ($items as $item) {
    if ($item['kind'] === 'new') {
        $count_new++;
    } else {
        $count_replacement++;
    }

    if ($item['changed']) {
        $count_changed++;
    }

    if ($item['draft_gone']) {
        $name = '<span class="fg-danger">' . h(lang('The draft was deleted.')) . '</span>';
    } else {
        $name = '<span class="text-break">' . h($item['draft_name']) . '</span> <a class="ms-1" href="' . h(pg_page_edit_url_by_id($item['draft_page_id'])) . '">' . h(lang('Edit')) . '</a>';
    }

    if ($item['kind'] === 'new') {
        $kind = h(lang('New page'));
    } else {
        $kind = h(lang(array('string' => 'Replaces {var:1}', 'vars' => ((string) $item['live_name'] !== '') ? $item['live_name'] : '#' . (int) $item['live_page_id'])));
    }

    $state = $item['changed']
        ? '<span class="badge theme-warning" title="' . h(lang('The page on the site was published since the copy was made. When the bundle is published you choose for this page: Replace anyway or Leave out.')) . '"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h(lang('Changed since')) . '</span>'
        : '<span class="fg-secondary">' . h(($item['status'] === 'waiting') ? lang('Waiting') : lang('Written to the site')) . '</span>';

    // The Remove buttons reach the form below the table through their form
    // attribute: no form sits inside a table row.
    $remove = ($may_change && ($item['status'] === 'waiting'))
        ? '<button type="submit" form="page_bundle_remove" name="item_id" value="' . (int) $item['id'] . '" class="btn btn-sm btn-text theme-danger" onclick="return confirm(' . h(json_encode(lang('Remove this page from the bundle? The draft stays in Drafts.'))) . ');"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Remove')) . '</button>'
        : '';

    $rows .= '<tr>
        <td class="align-middle">' . $name . '<div class="small fg-secondary md:d-none">' . $kind . '</div><div class="mt-1 md:d-none">' . $state . (($remove !== '') ? ' <span class="ms-2">' . $remove . '</span>' : '') . '</div></td>
        <td class="align-middle d-none md:d-table-cell">' . $kind . '</td>
        <td class="align-middle d-none md:d-table-cell">' . $state . '</td>
        <td class="align-middle text-end d-none md:d-table-cell">' . $remove . '</td>
    </tr>';
}

$output_items = $rows
    ? '<div class="table-responsive">
            <table class="table table-sm mb-0 pg-card-table">
                <thead><tr><th>' . h(lang('Draft')) . '</th><th class="d-none md:d-table-cell">' . h(lang('On the site')) . '</th><th class="d-none md:d-table-cell">' . h(lang('Status')) . '</th><th class="d-none md:d-table-cell"></th></tr></thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>
        <form method="post" id="page_bundle_remove" class="d-none">' . get_token_field() . '<input type="hidden" name="action" value="remove"></form>'
    : '<p class="fg-secondary mb-0 px-5 pb-5">' . h(lang('No page in this bundle yet. Add a copy of a page on the site to change it, or a draft to publish it as a new page.')) . '</p>';

$output_dialogs = '';
$output_buttons = '';

if ($may_change) {
    list($copy_body, $copy_ok) = $page_select('page_bundle_live_page', 'live_page_id', lang('Page on the site'),
        lang('A copy is made in Drafts and stands for this page: publishing the bundle puts the copy\'s design, title and description on the page. A page with an open experiment cannot be added.'),
        pg_page_bundle_copy_choices(), lang('No page of a visual design can be added: every page is already in an open bundle or in an open experiment.'));

    list($draft_body, $draft_ok) = $page_select('page_bundle_draft_page', 'draft_page_id', lang('Draft'),
        lang('The draft is published as a new page with the bundle. A publish time of its own is cleared.'),
        pg_page_bundle_draft_choices(), lang('No draft can be added: drafts in an open bundle or in an open experiment are left out.'));

    $output_dialogs .= $dialog('page_bundle_add_copy', 'files', lang('Add a copy of a page'), 'add_copy', $copy_body,
        $copy_ok ? '<button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . h(lang('Add the copy')) . '</button>' : '');

    $output_dialogs .= $dialog('page_bundle_add_draft', 'file-earmark-plus', lang('Add a draft'), 'add_draft', $draft_body,
        $draft_ok ? '<button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . h(lang('Add the draft')) . '</button>' : '');

    $output_dialogs .= $dialog('page_bundle_settings', 'gear', lang('Bundle settings'), 'settings', '
            <div class="form-field mb-5">
                <label for="page_bundle_settings_name" class="form-label">' . h(lang('Name')) . '</label>
                <input type="text" id="page_bundle_settings_name" name="name" maxlength="100" class="form-control" required value="' . h($bundle['name']) . '">
            </div>
            <div class="form-field">
                <label for="page_bundle_publish_at" class="form-label">' . h(lang('Publish time')) . '</label>
                <input type="text" id="page_bundle_publish_at" name="publish_at" maxlength="19" class="form-control" data-pg-datetime value="' . h($time_text($bundle['publish_at'])) . '" aria-describedby="page_bundle_publish_at_help">
                <div id="page_bundle_publish_at_help" class="form-text">' . h(lang('Leave it empty to publish the bundle by hand. At the time every page goes on the site at once; a bundle that cannot go out whole stays open.')) . '</div>
            </div>',
        '<button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>');

    $output_buttons = '
        <button type="button" class="btn btn-sm btn-solid theme-primary" data-bs-toggle="dialog" data-bs-target="#page_bundle_add_copy"><i class="bi bi-files me-1" aria-hidden="true"></i>' . h(lang('Add a copy of a page')) . '</button>
        <button type="button" class="btn btn-sm btn-outline theme-primary" data-bs-toggle="dialog" data-bs-target="#page_bundle_add_draft"><i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>' . h(lang('Add a draft')) . '</button>
        <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="dialog" data-bs-target="#page_bundle_settings"><i class="bi bi-gear me-1" aria-hidden="true"></i>' . h(lang('Bundle settings')) . '</button>';
}

if (pg_page_bundle_may_delete($bundle['status'])) {
    $output_buttons .= '
        <form method="post" class="d-inline" onsubmit="return confirm(' . h(json_encode(lang('Delete this bundle? Its drafts stay in Drafts and nothing on the site changes.'))) . ');">
            ' . get_token_field() . '<input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-sm btn-text theme-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Delete')) . '</button>
        </form>';
}

$people = pg_page_bundle_people(array($bundle['created_by'], $bundle['scheduled_by'], $bundle['published_by']));
$person = function ($id) use ($people) {
    return isset($people[(int) $id]) ? $people[(int) $id] : '';
};

$summary = '
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Status')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . $status_badge($bundle['status']) . '</dd>
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('New pages')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . pg_format_number($count_new, 0) . '</dd>
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Replacements')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . pg_format_number($count_replacement, 0) . '</dd>
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Changed since')) . '</dt>
                            <dd class="col-6 text-end mb-2' . ($count_changed ? ' fg-warning fw-semibold' : '') . '">' . pg_format_number($count_changed, 0) . '</dd>
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Publish time')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . (((int) $bundle['publish_at'] > 0) ? h($time_text($bundle['publish_at'])) . (($person($bundle['scheduled_by']) !== '') ? '<div class="small fg-secondary">' . h($person($bundle['scheduled_by'])) . '</div>' : '') : h(lang('By hand'))) . '</dd>
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Made')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . get_relative_time(array('timestamp' => (int) $bundle['created_at'])) . (($person($bundle['created_by']) !== '') ? '<div class="small fg-secondary">' . h($person($bundle['created_by'])) . '</div>' : '') . '</dd>';

if ((int) $bundle['published_at'] > 0) {
    $summary .= '
                            <dt class="col-6 fw-normal fg-secondary">' . h(lang('Published')) . '</dt>
                            <dd class="col-6 text-end mb-2">' . h($time_text($bundle['published_at'])) . (($person($bundle['published_by']) !== '') ? '<div class="small fg-secondary">' . h($person($bundle['published_by'])) . '</div>' : '') . '</dd>';
}

$hint = $count_changed
    ? lang('A page marked "Changed since" was published on the site after its copy was made. Look at the copy again; when the bundle is published you choose for it: Replace anyway or Leave out.')
    : lang('The drafts of an open bundle stay drafts: they cannot be published alone, deleted or made a variant until the bundle is published or deleted, or the page is removed from it.');

echo $shell() . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <div class="row">
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
                ' . $output_buttons . '
                <div class="pg-toolbar-grow"></div>
                <a class="btn btn-sm btn-text theme-secondary" href="' . h($list_url) . '"><i class="bi bi-collection me-1" aria-hidden="true"></i>' . h(lang('All bundles')) . '</a>
            </nav>
            <div class="card my-7">
                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Pages')) . '</h2>
                <div class="card-body p-0">' . $output_items . '</div>
            </div>
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky my-7" style="top:3.3rem;">
                <div class="card">
                    <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Summary')) . '</h2>
                    <div class="card-body pt-0">
                        <div class="fw-semibold text-break mb-5">' . h($bundle['name']) . '</div>
                        <dl class="row small mb-0">' . $summary . '
                        </dl>
                        <div class="form-text mt-5">' . h($hint) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' . $output_dialogs . ($may_change ? get_date_time_picker_format() . pg_fields_script() : '') . '
' . output_footer();

$liveform->remove_form();
