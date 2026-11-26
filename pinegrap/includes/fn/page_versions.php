<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A live page's new version: a draft prepared in the Page Design Studio that
 * replaces the page when it is published. It is a release bundle of one
 * shape (includes/fn/page_bundles.php): open, with no publish time, holding
 * one item, a replacement. No table or column marks it, so a bundle made by
 * hand with that shape acts the same; a second page or a time makes it a
 * plain bundle again.
 *
 * Starting a version makes the bundle and its copy through the bundle
 * module, then saves the tab's state onto the copy as a draft. A failure
 * after the bundle was made takes it back again. Discard removes the item,
 * deletes the emptied bundle and moves the draft to the Recycle Bin, in
 * that order: the bin refuses a draft its bundle still holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether a bundle has a new version's shape: open, no publish time, one
 * item, which is a replacement still waiting. Pure.
 *
 * @param array $bundle status, publish_at
 * @param array $items  the bundle's page_bundle_item rows (live_page_id,
 *                      status)
 * @return bool
 */
function pg_page_version_shape($bundle, $items)
{
    if (!is_array($bundle) || ((string) $bundle['status'] !== 'open') || ((int) $bundle['publish_at'] !== 0)) {
        return false;
    }

    $items = array_values((array) $items);

    if (count($items) !== 1) {
        return false;
    }

    return ((int) $items[0]['live_page_id'] > 0) && ((string) $items[0]['status'] === 'waiting');
}

/**
 * The new versions among bundles and their items: each bundle of the shape,
 * with its one item. Pure.
 *
 * @param array $bundles page_bundle rows (id, name, status, publish_at)
 * @param array $items   page_bundle_item rows of those bundles (id,
 *                       bundle_id, draft_page_id, live_page_id, status)
 * @return array list of bundle_id, bundle_name, item_id, draft_page_id,
 *               live_page_id
 */
function pg_page_versions_from_rows($bundles, $items)
{
    $by_bundle = array();

    foreach ((array) $items as $item) {
        $by_bundle[(int) $item['bundle_id']][] = $item;
    }

    $out = array();

    foreach ((array) $bundles as $bundle) {
        $id = (int) $bundle['id'];
        $own = isset($by_bundle[$id]) ? $by_bundle[$id] : array();

        if (!pg_page_version_shape($bundle, $own)) {
            continue;
        }

        $out[] = array(
            'bundle_id' => $id,
            'bundle_name' => (string) $bundle['name'],
            'item_id' => (int) $own[0]['id'],
            'draft_page_id' => (int) $own[0]['draft_page_id'],
            'live_page_id' => (int) $own[0]['live_page_id'],
        );
    }

    return $out;
}

/**
 * A new version's bundle name, "New version of <page>", kept within the
 * bundle name's 100 characters: a long page name is cut and ends in "…".
 *
 * @param string $page_name
 * @return string
 */
function pg_page_version_name($page_name)
{
    $page_name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $page_name));
    $name = lang(array('string' => 'New version of {var:1}', 'vars' => array($page_name)));
    $over = mb_strlen($name, 'UTF-8') - 100;

    if ($over > 0) {
        $cut = max(0, mb_strlen($page_name, 'UTF-8') - $over - 1);
        $name = lang(array('string' => 'New version of {var:1}', 'vars' => array(mb_substr($page_name, 0, $cut, 'UTF-8') . '…')));
    }

    return $name;
}

/**
 * Why a new version of a page may not be started, '' when it may: the
 * person's rights first, then the page being on the site, then an open
 * experiment on it. Pure.
 *
 * @param bool $may             pg_page_bundle_user_may()
 * @param bool $on_site         a visual-design page on the site
 * @param bool $experiment_open the page has an experiment not ended
 * @return string
 */
function pg_page_version_start_refusal($may, $on_site, $experiment_open)
{
    if (!$may) {
        return lang('Only administrators and designers can make a new version of a page.');
    }

    if (!$on_site) {
        return lang('A new version can only be made of a page of a visual design that is on the site.');
    }

    if ($experiment_open) {
        return lang('This page has an open experiment; end it before making a new version of the page.');
    }

    return '';
}

/**
 * The tab's state as it is saved onto a new version's draft: the copy's id
 * and name (the live page keeps its own), a draft that goes to the live
 * page's folder, never the home page, and none of the live tab's times,
 * hash or rename. Pure.
 *
 * @param array $page  the tab's page as the editor sends it to the save
 * @param array $draft page_id, page_name of the copy
 * @param int   $folder the live page's folder
 * @return array the page for pg_designer_save_page()
 */
function pg_page_version_draft_page($page, $draft, $folder)
{
    $page = is_array($page) ? $page : array();

    foreach (array('unchanged', 'base_hash', 'page_publish_at', 'page_unpublish_at', 'page_name_redirect', 'publish_from_draft', 'approval_override') as $key) {
        unset($page[$key]);
    }

    $page['page_id'] = (int) $draft['page_id'];
    $page['page_name'] = (string) $draft['page_name'];
    $page['page_folder'] = (int) $folder;
    $page['page_draft'] = 1;
    $page['page_home'] = 0;

    return $page;
}

/**
 * Runs steps in their order and stops at the first that fails. Discard's
 * steps are the item removed, the emptied bundle deleted and the draft
 * binned; a start taken back runs the same with the draft deleted. Pure:
 * the steps are callables.
 *
 * @param array $steps name => callable (): array ok, error
 * @return array ok, error, done (the names run that succeeded)
 */
function pg_page_version_run_steps($steps)
{
    $done = array();

    foreach ((array) $steps as $name => $step) {
        $result = call_user_func($step);

        if (empty($result['ok'])) {
            return array('ok' => false, 'error' => isset($result['error']) ? (string) $result['error'] : '', 'done' => $done);
        }

        $done[] = (string) $name;
    }

    return array('ok' => true, 'error' => '', 'done' => $done);
}

/**
 * The new versions whose draft or live page is one of the pages given.
 *
 * @param array $page_ids
 * @return array pg_page_versions_from_rows()
 */
function pg_page_versions_for_pages($page_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $page_ids))));

    if (!$ids || !pg_page_bundles_ready()) {
        return array();
    }

    $list = implode(',', $ids);

    $bundle_ids = db_values(
        "SELECT DISTINCT page_bundle.id
        FROM page_bundle_item
        JOIN page_bundle ON page_bundle.id = page_bundle_item.bundle_id
        WHERE page_bundle.status = 'open' AND page_bundle.publish_at = 0 AND page_bundle_item.status = 'waiting'
            AND (page_bundle_item.draft_page_id IN ($list) OR page_bundle_item.live_page_id IN ($list))");

    $bundle_ids = array_values(array_filter(array_map('intval', (array) $bundle_ids)));

    if (!$bundle_ids) {
        return array();
    }

    $in = implode(',', $bundle_ids);

    return pg_page_versions_from_rows(
        db_items("SELECT id, name, status, publish_at FROM page_bundle WHERE id IN ($in) ORDER BY id"),
        db_items("SELECT id, bundle_id, draft_page_id, live_page_id, status FROM page_bundle_item WHERE bundle_id IN ($in) ORDER BY id"));
}

/**
 * A live page's new version, or null.
 *
 * @param int $live_page_id
 * @return array|null pg_page_versions_from_rows() entry
 */
function pg_page_version_of_live($live_page_id)
{
    foreach (pg_page_versions_for_pages(array($live_page_id)) as $version) {
        if ($version['live_page_id'] === (int) $live_page_id) {
            return $version;
        }
    }

    return null;
}

/**
 * The new version a draft is, or null.
 *
 * @param int $draft_page_id
 * @return array|null pg_page_versions_from_rows() entry
 */
function pg_page_version_of_draft($draft_page_id)
{
    foreach (pg_page_versions_for_pages(array($draft_page_id)) as $version) {
        if ($version['draft_page_id'] === (int) $draft_page_id) {
            return $version;
        }
    }

    return null;
}

/**
 * The visual editor's versions block: for each tab page that is a new
 * version's draft, its live page; for each live page that has one, its
 * draft. Keys are page ids.
 *
 * @param array $page_ids the editor's page ids
 * @return object page id => role ('version' or 'live'), bundle_id,
 *                draft_page_id, live_page_id, draft_name, live_name
 */
function pg_page_version_editor_config($page_ids)
{
    $versions = pg_page_versions_for_pages($page_ids);
    $pages = array();

    if (!$versions) {
        return (object) $pages;
    }

    $names = array();
    $ids = array();

    foreach ($versions as $version) {
        $ids[] = $version['draft_page_id'];
        $ids[] = $version['live_page_id'];
    }

    foreach ((array) db_items("SELECT page_id, page_name FROM page WHERE page_id IN (" . implode(',', array_unique($ids)) . ")") as $row) {
        $names[(int) $row['page_id']] = (string) $row['page_name'];
    }

    foreach ($versions as $version) {
        $entry = array(
            'bundle_id' => $version['bundle_id'],
            'draft_page_id' => $version['draft_page_id'],
            'live_page_id' => $version['live_page_id'],
            'draft_name' => isset($names[$version['draft_page_id']]) ? $names[$version['draft_page_id']] : '',
            'live_name' => isset($names[$version['live_page_id']]) ? $names[$version['live_page_id']] : '',
        );

        $pages[(string) $version['draft_page_id']] = array('role' => 'version') + $entry;
        $pages[(string) $version['live_page_id']] = array('role' => 'live') + $entry;
    }

    return (object) $pages;
}

/**
 * The answer of a start or a discard. Pure.
 *
 * @param string     $error   '' when it went through
 * @param array|null $version pg_page_versions_from_rows() entry
 * @param bool       $existing the page had a version already
 * @return array ok, error, existing, bundle_id, draft_page_id, live_page_id,
 *               draft_name
 */
function pg_page_version_answer($error, $version = null, $existing = false)
{
    return array(
        'ok' => ($error === ''),
        'error' => (string) $error,
        'existing' => (bool) $existing,
        'bundle_id' => is_array($version) ? (int) $version['bundle_id'] : 0,
        'draft_page_id' => is_array($version) ? (int) $version['draft_page_id'] : 0,
        'live_page_id' => is_array($version) ? (int) $version['live_page_id'] : 0,
        'draft_name' => (is_array($version) && isset($version['draft_name'])) ? (string) $version['draft_name'] : '',
    );
}

/**
 * A version as answered, with its draft's name.
 *
 * @param array $version pg_page_versions_from_rows() entry
 * @param bool  $existing
 * @return array pg_page_version_answer()
 */
function pg_page_version_found($version, $existing)
{
    $version['draft_name'] = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $version['draft_page_id'] . "' LIMIT 1");

    return pg_page_version_answer('', $version, $existing);
}

/**
 * Starts a new version of a live page: the bundle "New version of <page>"
 * and its copy, then the tab's state saved onto the copy as a draft. A page
 * that has a version answers with that draft, not a second one; two starts
 * at one moment end with one (the bundle module's race keeps the first
 * replacement, the other bundle is deleted again).
 *
 * @param int        $live_page_id
 * @param array|null $page the tab's page as the editor sends it to the save;
 *                         null keeps the copy as the live page is
 * @param array      $user
 * @return array pg_page_version_answer() and warnings
 */
function pg_page_version_start($live_page_id, $page, $user)
{
    $fail = function ($error) {
        return pg_page_version_answer($error) + array('warnings' => array());
    };

    if (!pg_page_bundles_ready() || !pg_page_draft_ready()) {
        return $fail(lang('New versions are not available yet. Please run the upgrade.'));
    }

    $live = pg_experiment_sheet_live_page($live_page_id);
    $live_id = $live ? (int) $live['page_id'] : 0;
    $experiment_open = ($live_id > 0) && pg_experiment_sheet_ready()
        && db_value("SELECT id FROM experiments WHERE page_id = '$live_id' AND status <> 'ended' LIMIT 1");

    // The folder right is asked here too: the copy (duplicate_page()) ends
    // the request on a refused folder, which would leave the bundle behind.
    $may = pg_page_bundle_user_may($user)
        && (!$live || pg_folder_edit_access((int) $live['page_folder'], (int) $user['id'], (int) $user['role']));

    $refusal = pg_page_version_start_refusal($may, (bool) $live, (bool) $experiment_open);

    if ($refusal !== '') {
        return $fail($refusal);
    }

    $version = pg_page_version_of_live($live_id);

    if ($version) {
        return pg_page_version_found($version, true) + array('warnings' => array());
    }

    $made = pg_page_bundle_create(pg_page_version_name($live['page_name']), $user);

    if (!$made['ok']) {
        return $fail($made['error']);
    }

    $bundle = pg_page_bundle_get($made['id']);
    $copy = $bundle ? pg_page_bundle_add_copy($bundle, $live_id, $user) : array('ok' => false, 'error' => lang('The bundle was not found.'));

    if (!$copy['ok']) {
        if ($bundle) {
            pg_page_bundle_delete($bundle, $user);
        }

        // Started at the same moment by somebody else: theirs is the one.
        $version = pg_page_version_of_live($live_id);

        return $version ? pg_page_version_found($version, true) + array('warnings' => array()) : $fail($copy['error']);
    }

    $draft_id = (int) $copy['draft_page_id'];
    $version = array('bundle_id' => (int) $bundle['id'], 'item_id' => (int) $copy['item_id'],
        'draft_page_id' => $draft_id, 'live_page_id' => $live_id);

    $warnings = array();

    if (is_array($page)) {
        $draft = db_item("SELECT page_id, page_name FROM page WHERE page_id = '$draft_id' LIMIT 1");
        $saved = is_array($draft)
            ? pg_designer_save_page((int) $live['page_style'], pg_page_version_draft_page($page, $draft, (int) $live['page_folder']), $user, false)
            : array('ok' => false, 'errors' => array(lang('The copy of the page could not be made.')), 'warnings' => array());

        if (empty($saved['ok'])) {
            pg_page_version_take_back($bundle, $version, $user);

            $errors = array_values(array_filter(array_map('strval', (array) $saved['errors'])));

            return $fail($errors ? implode(' ', $errors) : lang('The new version could not be saved.'));
        }

        $warnings = array_values((array) $saved['warnings']);
    }

    log_activity(lang(array('string' => 'new version of page ({var:1}) was started', 'vars' => array((string) $live['page_name']))),
        isset($user['username']) ? (string) $user['username'] : '');

    return pg_page_version_found($version, false) + array('warnings' => $warnings);
}

/**
 * Takes back a start whose save failed: the item removed, the bundle
 * deleted, the copy deleted for good (it was never shown to anybody).
 *
 * @param array $bundle  pg_page_bundle_get()
 * @param array $version bundle_id, item_id, draft_page_id
 * @param array $user
 * @return array pg_page_version_run_steps()
 */
function pg_page_version_take_back($bundle, $version, $user)
{
    return pg_page_bundle_with_lock($bundle, $user, pg_page_bundle_busy_answer(), function ($fresh) use ($version, $user) {
        return pg_page_version_run_steps(pg_page_version_end_steps($fresh, $version, function () use ($version, $user) {
            pg_page_bundle_load_recycle();
            $deleted = pg_delete_page_record((int) $version['draft_page_id'], $user);

            return array('ok' => ($deleted['status'] === 'success'), 'error' => (string) $deleted['message']);
        }));
    });
}

/**
 * The steps that end a version, in their order: the item removed, the
 * emptied bundle deleted, then the draft ($last: binned or deleted). Run
 * under the bundle's lock.
 *
 * @param array    $bundle  the bundle, read under the lock
 * @param array    $version item_id
 * @param callable $last    the draft's step
 * @return array name => callable
 */
function pg_page_version_end_steps($bundle, $version, $last)
{
    return array(
        'remove_item' => function () use ($bundle, $version) {
            return pg_page_bundle_remove_item_locked($bundle, (int) $version['item_id']);
        },
        'delete_bundle' => function () use ($bundle) {
            if ((int) db_value("SELECT COUNT(*) FROM page_bundle_item WHERE bundle_id = '" . (int) $bundle['id'] . "'") > 0) {
                return array('ok' => false, 'error' => lang('The bundle was changed meanwhile; look again.'));
            }

            return pg_page_bundle_delete_locked($bundle);
        },
        'draft' => $last,
    );
}

/**
 * Discards a new version: its item removed, the emptied bundle deleted and
 * the draft moved to the Recycle Bin, in that order. A draft that is no new
 * version (a plain bundle's, or none) is refused.
 *
 * @param int   $draft_page_id
 * @param array $user
 * @return array pg_page_version_answer()
 */
function pg_page_version_discard($draft_page_id, $user)
{
    if (!pg_page_bundle_user_may($user)) {
        return pg_page_version_answer(lang('Only administrators and designers can discard a new version of a page.'));
    }

    $version = pg_page_version_of_draft($draft_page_id);
    $bundle = $version ? pg_page_bundle_get($version['bundle_id']) : null;

    if (!$bundle) {
        return pg_page_version_answer(lang('This page is not a new version of a page on the site.'));
    }

    $run = pg_page_bundle_with_lock($bundle, $user, pg_page_bundle_busy_answer(), function ($fresh) use ($version, $user) {
        // Read again under the lock: a page added or a time set since
        // makes it a plain bundle, which is not discarded from here.
        if (!pg_page_version_shape($fresh, pg_page_bundle_items($fresh['id']))) {
            return array('ok' => false, 'error' => lang('This page is not a new version of a page on the site.'));
        }

        return pg_page_version_run_steps(pg_page_version_end_steps($fresh, $version, function () use ($version, $user) {
            pg_page_bundle_load_recycle();
            $binned = pg_recycle_page((int) $version['draft_page_id'], $user);

            return array('ok' => in_array($binned['status'], array('binned', 'inside'), true), 'error' => (string) $binned['message']);
        }));
    });

    return pg_page_version_answer($run['ok'] ? '' : (string) $run['error'], $version);
}
