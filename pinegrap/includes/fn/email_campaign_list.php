<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What the e-mail campaign screens share: the Delete Selected button of the
 * Campaigns list and the deletion of e-mail campaigns, used by that list
 * (view_email_campaigns.php) and by delete_email_campaigns.php. The list
 * itself is includes/fn/messages_campaigns.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

/**
 * The Delete Selected button of a campaign list's selection bar; the list
 * sits in a form posting the selected ids to its own screen.
 *
 * @return string
 */
function pg_email_campaign_list_delete_button()
{
    return '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="'
        . h(lang(array('string' => 'WARNING: Selected {var:1} will be permanently deleted.', 'vars' => array(lang('campaigns'))))) . '">'
        . '<i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';
}

/**
 * Deletes e-mail campaigns with their contact group links and queued
 * recipients; a role 3 user deletes only campaigns they created.
 *
 * @param array $ids  email_campaigns ids
 * @param array $user validate_user()
 * @return int how many were deleted
 */
function pg_email_campaigns_delete(array $ids, array $user)
{
    $deleted = 0;

    foreach ($ids as $id) {
        if (!is_scalar($id) || ((int) $id < 1)) {
            continue;
        }

        $id = (string) (int) $id;
        $owner = db_value("SELECT created_user_id FROM email_campaigns WHERE id = '" . e($id) . "'");

        if (($owner === null) || (((int) $user['role'] === 3) && ((int) $owner !== (int) $user['id']))) {
            continue;
        }

        db_q("DELETE FROM email_campaigns WHERE id = ?", array($id)) or output_error('Query failed.');
        db_q("DELETE FROM contact_groups_email_campaigns_xref WHERE email_campaign_id = ?", array($id)) or output_error('Query failed.');
        db_q("DELETE FROM email_recipients WHERE email_campaign_id = ?", array($id)) or output_error('Query failed.');
        $deleted++;
    }

    return $deleted;
}
