<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's one home page: the value a page save writes into
 * page.page_home and the step that takes the flag from every other page
 * when a page becomes the home page. Edit Page (edit_page.php) and the
 * visual designer's saves (includes/fn/designer.php) both use it, so the
 * site root (get_page.php) always finds a single row. A page deleted for
 * good also leaves the users' start pages (pg_page_start_page_forget()).
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
 * The page_home value for a submitted switch. The column is
 * ENUM('no','yes'): an empty string is refused by a strict server and
 * stored as the invalid empty member otherwise, so anything but 'yes' is
 * 'no'. Pure.
 *
 * @param mixed $posted
 * @return string 'yes' or 'no'
 */
function pg_page_home_value($posted)
{
    return (is_scalar($posted) && (string) $posted === 'yes') ? 'yes' : 'no';
}

/**
 * The statement that takes the home flag from every page but the given
 * one. Pure.
 *
 * @param int $page_id The page that is the home page now
 * @return string
 */
function pg_page_home_claim_sql($page_id)
{
    return "UPDATE page SET page_home = 'no' WHERE page_home = 'yes' AND page_id <> '" . (int) $page_id . "'";
}

/**
 * Makes the given page the only home page: every other page loses the
 * flag. Call it after the page itself was saved with page_home = 'yes'.
 *
 * @param int $page_id
 * @return string[] Names of the pages that stopped being the home page
 */
function pg_page_home_claim($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return array();
    }

    $names = array();

    foreach ((array) db_items("SELECT page_name FROM page WHERE page_home = 'yes' AND page_id <> '$page_id' ORDER BY page_name") as $row) {
        $names[] = (string) $row['page_name'];
    }

    if ($names) {
        db(pg_page_home_claim_sql($page_id));
    }

    return $names;
}

/**
 * The statement that takes deleted pages off every user's start page
 * (user.user_home), as a design template install does with the pages it
 * replaces. A user with no start page signs in to the site's default page,
 * so 0 is what "[None]" was. Pure.
 *
 * @param int[] $page_ids
 * @return string '' when no id is left
 */
function pg_page_start_page_forget_sql($page_ids)
{
    $ids = array();

    foreach ((array) $page_ids as $id) {
        $id = (int) $id;

        if (($id > 0) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids ? "UPDATE user SET user_home = 0 WHERE user_home IN (" . implode(', ', $ids) . ")" : '';
}

/**
 * Takes a page that is deleted for good off every user's start page, so
 * Edit User and the users list never keep an id with no page behind it.
 *
 * @param int|int[] $page_ids
 * @return void
 */
function pg_page_start_page_forget($page_ids)
{
    $sql = pg_page_start_page_forget_sql(is_array($page_ids) ? $page_ids : array($page_ids));

    if ($sql !== '') {
        db($sql);
    }
}
