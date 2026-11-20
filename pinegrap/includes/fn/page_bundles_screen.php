<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Bundles screen's read side (page_bundles.php): the labels and themes
 * of a bundle's status, the pages a bundle may take (live pages for "Add a
 * copy of a page", drafts for "Add a draft"), a bundle's pages as the screen
 * lists them with "changed since", the publish time read from the form, and
 * the Publish and Take back buttons with what their dialogs post back.
 * The rules and the writes are in page_bundles.php.
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
 * A bundle status's label on the screen.
 *
 * @param string $status
 * @return string
 */
function pg_page_bundle_status_label($status)
{
    switch ((string) $status) {
        case 'open':
            return lang('Open');
        case 'scheduled':
            return lang('Scheduled');
        case 'published':
            return lang('Published');
        case 'partial':
            return lang('Partly published');
        case 'reverted':
            return lang('Taken back');
        default:
            return lang('Closed');
    }
}

/**
 * A bundle status's badge theme; '' draws the neutral badge. Pure.
 *
 * @param string $status
 * @return string
 */
function pg_page_bundle_status_theme($status)
{
    $themes = array('scheduled' => 'info', 'published' => 'success', 'partial' => 'danger', 'reverted' => 'secondary');

    return isset($themes[$status]) ? $themes[$status] : '';
}

/**
 * The live designer pages "Add a copy of a page" offers: on the site, not in
 * the Recycle Bin, without an open experiment and without a replacement in
 * an open bundle.
 *
 * @return array page_id, page_name
 */
function pg_page_bundle_copy_choices()
{
    if (!pg_page_bundles_ready()) {
        return array();
    }

    $draft_folder = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(false) : 0;
    $experiments = pg_experiment_sheet_ready()
        ? " AND page.page_id NOT IN (SELECT page_id FROM experiments WHERE status <> 'ended')" : '';

    $rows = db_items(
        "SELECT page.page_id, page.page_name FROM page
        WHERE " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . "
            AND page.page_folder <> '$draft_folder'" . $experiments . "
        ORDER BY page.page_name");

    return pg_page_bundle_free_choices((array) $rows, pg_page_bundle_open_items(), 'live');
}

/**
 * The drafts "Add a draft" offers: drafts of a visual design, not in the
 * Recycle Bin, not an open experiment's variant and not in an open bundle.
 *
 * @return array page_id, page_name
 */
function pg_page_bundle_draft_choices()
{
    $draft_folder = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(false) : 0;

    if (($draft_folder <= 0) || !pg_page_bundles_ready()) {
        return array();
    }

    $variants = pg_experiment_sheet_ready()
        ? " AND page.page_id NOT IN (SELECT experiment_variants.draft_page_id FROM experiment_variants
            JOIN experiments ON experiments.id = experiment_variants.experiment_id WHERE experiments.status <> 'ended')" : '';

    $rows = db_items(
        "SELECT page.page_id, page.page_name FROM page
        WHERE page.page_folder = '$draft_folder' AND " . pg_page_visual_design_sql() . $variants . "
        ORDER BY page.page_name");

    return pg_page_bundle_free_choices((array) $rows, pg_page_bundle_open_items(), 'draft');
}

/**
 * Pages left out of a choice list when an open bundle already has them: as
 * its draft ('draft') or as the live page of a replacement ('live'). Pure.
 *
 * @param array  $rows       page_id, page_name
 * @param array  $open_items pg_page_bundle_open_items()
 * @param string $as         'draft' or 'live'
 * @return array
 */
function pg_page_bundle_free_choices($rows, $open_items, $as)
{
    $column = ($as === 'live') ? 'live_page_id' : 'draft_page_id';
    $taken = array();

    foreach ((array) $open_items as $item) {
        $taken[(int) $item[$column]] = true;
    }

    $free = array();

    foreach ((array) $rows as $row) {
        if (!isset($taken[(int) $row['page_id']])) {
            $free[] = array('page_id' => (int) $row['page_id'], 'page_name' => (string) $row['page_name']);
        }
    }

    return $free;
}

/**
 * A bundle's pages as the screen lists them: the draft's name and design for
 * its Edit link, the live page's name, and "changed since" for a replacement
 * still waiting.
 *
 * @param int $bundle_id
 * @return array pg_page_bundle_items() rows with draft_style, kind ('new' or
 *               'replacement'), changed (bool), draft_gone (bool)
 */
function pg_page_bundle_screen_items($bundle_id)
{
    $items = pg_page_bundle_items($bundle_id);

    if (!$items) {
        return array();
    }

    $ids = array();

    foreach ($items as $item) {
        $ids[] = (int) $item['draft_page_id'];
    }

    $styles = array();

    foreach ((array) db_items("SELECT page_id, page_style FROM page WHERE page_id IN (" . implode(',', $ids) . ")") as $row) {
        $styles[(int) $row['page_id']] = (int) $row['page_style'];
    }

    foreach ($items as $i => $item) {
        $draft_id = (int) $item['draft_page_id'];
        $items[$i]['draft_style'] = isset($styles[$draft_id]) ? $styles[$draft_id] : 0;
        $items[$i]['draft_gone'] = !isset($styles[$draft_id]);
        $items[$i]['kind'] = ((int) $item['live_page_id'] > 0) ? 'replacement' : 'new';
        $items[$i]['changed'] = ($item['status'] === 'waiting') && pg_page_bundle_item_changed($item);
    }

    return $items;
}

/**
 * The names of the people a bundle names (made, scheduled, published), by
 * user id.
 *
 * @param array $user_ids
 * @return array user id => name
 */
function pg_page_bundle_people($user_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $user_ids))));

    if (!$ids) {
        return array();
    }

    $names = array();

    $rows = db_items(
        "SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_id IN (" . implode(',', $ids) . ")");

    foreach ((array) $rows as $row) {
        $names[(int) $row['user_id']] = pg_user_display_name($row['first_name'], $row['last_name'], (string) $row['user_username']);
    }

    return $names;
}

/**
 * The publish time typed in the form as a unix time: 0 for an empty field,
 * -1 when it is not a date and time.
 *
 * @param string $text the site's date and time format, or ISO
 * @return int
 */
function pg_page_bundle_time_from_form($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return 0;
    }

    $iso = (string) prepare_form_data_for_input($text, 'date and time');

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $iso)) {
        return -1;
    }

    $time = strtotime($iso);

    return ($time === false) ? -1 : (int) $time;
}

/**
 * The Publish and Take back buttons a bundle shows in a status: Publish
 * while it holds its drafts ("Publish the rest" once partly published),
 * Take back once anything of it is on the site. Pure.
 *
 * @param string $status
 * @return array publish (the button's label, '' for none), take_back (bool)
 */
function pg_page_bundle_screen_actions($status)
{
    $status = (string) $status;
    $publish = '';

    if (pg_page_bundle_holds($status)) {
        $publish = ($status === 'partial') ? lang('Publish the rest') : lang('Publish');
    }

    return array('publish' => $publish, 'take_back' => pg_page_bundle_may_move($status, 'reverted'));
}

/**
 * The publish dialog's posted choice[item id]: "replace" or "leave" for a
 * positive item id, anything else dropped. Pure.
 *
 * @param mixed $posted
 * @return array item id => 'replace' or 'leave'
 */
function pg_page_bundle_screen_choices($posted)
{
    $choices = array();

    foreach (is_array($posted) ? $posted : array() as $id => $choice) {
        if (is_string($choice) && in_array($choice, array('replace', 'leave'), true) && ctype_digit((string) $id) && ((int) $id > 0)) {
            $choices[(int) $id] = $choice;
        }
    }

    return $choices;
}

/**
 * The take-back dialog's posted kept[]: the positive item ids, once each.
 * Pure.
 *
 * @param mixed $posted
 * @return int[]
 */
function pg_page_bundle_screen_kept($posted)
{
    $kept = array();

    foreach (is_array($posted) ? $posted : array() as $id) {
        if (is_scalar($id) && ctype_digit((string) $id) && ((int) $id > 0) && !in_array((int) $id, $kept, true)) {
            $kept[] = (int) $id;
        }
    }

    return $kept;
}

/**
 * The notice a publish or take-back leaves on the screen. A run that wrote
 * but could not move the bundle reports that error. Pure.
 *
 * @param string $action 'publish' or 'take_back'
 * @param array  $done   pg_page_bundle_publish() or pg_page_bundle_take_back()
 * @return array ok (a notice, else an error), text
 */
function pg_page_bundle_screen_outcome($action, $done)
{
    $error = isset($done['error']) ? (string) $done['error'] : '';

    if (!empty($done['ok']) && ($error === '')) {
        return array('ok' => true, 'text' => ($action === 'take_back')
            ? lang('The bundle was taken back.')
            : lang('The bundle was published: its pages are on the site.'));
    }

    return array('ok' => false, 'text' => ($error !== '') ? $error : lang('The bundle cannot make that change now.'));
}
