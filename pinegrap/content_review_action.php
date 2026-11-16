<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The actions of the Content review screen (view_content_review.php): Set
 * person, Set review date, Mark reviewed and Clear, on one row or on a
 * selection, in one request. The selection is read with the list's own
 * query and the user's rights (includes/fn/content_review_list.php), and
 * every item is written through the rules of includes/fn/content_review.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
pg_post_body_discarded();

$liveform = new liveform('view_content_review');

$user = validate_user();
validate_area_access($user, 'user');
validate_token_field();

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review_list.php');

$review_params = json_decode((string) ($_POST['list_params'] ?? ''), true);
$review_params = is_array($review_params) ? $review_params : array();
$review_filter = pg_content_review_list_filter($review_params['filter'] ?? null, (int) $user['role']);
$review_back = 'view_content_review.php?filter=' . rawurlencode($review_filter);
$review_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';

if (!pg_content_review_list_action_allowed($review_action, (int) $user['role'])) {
    log_activity('access denied: content review action ' . substr(preg_replace('/[^a-z_]/', '', $review_action), 0, 20));
    output_error(lang('Access denied'));
}

$review_values = pg_content_review_list_action_values($_POST);

if ($review_values === null) {
    $liveform->mark_error('', lang('Enter a valid date.'));
    go($review_back);
}

$list_source = pg_content_review_list_source($user, array('filter' => $review_filter));

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$review_selection = pg_list_table_selection_parse(json_decode((string) ($_POST['selection'] ?? ''), true), time());

if ($review_selection === null) {
    $liveform->mark_error('', lang('Select at least one item.'));
    go($review_back);
}

$review_ids = pg_list_table_selected_ids($list_source, $review_selection);

if ($review_ids === false) {
    output_error(lang('Query failed.'));
}

if (!empty($review_ids['more'])) {
    $liveform->mark_error('', lang('More than 10,000 items are selected. Narrow the list and try again.'));
    go($review_back);
}

// Ids the list no longer holds for this user (another person's item, a
// page moved to the Recycle Bin) are dropped by the resolver.
if (!$review_ids['ids']) {
    $liveform->mark_error('', lang('Select at least one item.'));
    go($review_back);
}

$review_counts = pg_content_review_list_apply($review_action, $review_ids['ids'], $review_values, $user);

if ($review_counts['done'] > 0) {
    $liveform->add_notice(h(lang(array('string' => '{var:1} items were updated.', 'vars' => array($review_counts['done'])))));
}

if ($review_counts['person'] > 0) {
    $liveform->mark_error('', h(lang(array('string' => '{var:1} items were left as they were: the person may not edit them.', 'vars' => array($review_counts['person'])))));
}

if ($review_counts['refused'] > 0) {
    $liveform->mark_error('', h(lang(array('string' => '{var:1} items could not be changed.', 'vars' => array($review_counts['refused'])))));
}

go($review_back);
