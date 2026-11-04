<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ads and ad regions: the one write path the create and edit screens
 * (add_ad.php, edit_ad.php, add_ad_region.php, edit_ad_region.php) and the
 * external API (/design/ads, /design/ad-regions) share, so a row written
 * from either place is written the same way.
 *
 * The callers check the values first (includes/forms/ad_form.php,
 * includes/forms/ad_region_form.php); nothing here checks who is asking:
 * the screens gate on the role and the user's ad regions, the API on
 * design:write. Every function returns 0/false/null when a query failed.
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
 * Whether another row of the table already has the name.
 *
 * @param string   $table      'ads' or 'ad_regions'
 * @param string   $name
 * @param int|null $except_id  the row being edited, null on create
 * @return bool|null null when the query failed
 */
function pg_ad_name_in_use($table, $name, $except_id)
{
    $table = ($table === 'ad_regions') ? 'ad_regions' : 'ads';
    $query = "SELECT id FROM " . $table . " WHERE (name = ?)";
    $values = array((string) $name);

    if ($except_id !== null) {
        $query .= " AND (id != ?)";
        $values[] = (int) $except_id;
    }

    $result = db_q($query . " LIMIT 1", $values);

    if (!$result) {
        return null;
    }

    return mysqli_num_rows($result) > 0;
}

/**
 * Whether the ad region exists.
 *
 * @param int $region_id
 * @return bool|null null when the query failed
 */
function pg_ad_region_exists($region_id)
{
    $result = db_q("SELECT id FROM ad_regions WHERE id = ?", array((int) $region_id));

    if (!$result) {
        return null;
    }

    return mysqli_num_rows($result) > 0;
}

/**
 * Whether any ad is in the ad region: such a region is not deleted.
 *
 * @param int $region_id
 * @return bool|null null when the query failed
 */
function pg_ad_region_has_ads($region_id)
{
    $result = db_q("SELECT id FROM ads WHERE ad_region_id = ? LIMIT 1", array((int) $region_id));

    if (!$result) {
        return null;
    }

    return mysqli_num_rows($result) > 0;
}

/**
 * Creates (id 0) or updates an ad.
 *
 * @param int   $ad_id
 * @param array $values   name, content, caption (with the {path} placeholder
 *                        already in place), ad_region_id, label, sort_order
 *                        ('' or 0 for random order)
 * @param int   $user_id
 * @return int the ad's id, 0 when a query failed
 */
function pg_ad_save($ad_id, $values, $user_id)
{
    $ad_id = (int) $ad_id;
    $row = array(
        (string) $values['name'],
        (string) $values['content'],
        (string) $values['caption'],
        (int) $values['ad_region_id'],
        (string) $values['label'],
        (int) $values['sort_order'],
    );

    if ($ad_id > 0) {
        $row[] = (int) $user_id;
        $row[] = $ad_id;

        return db_q("UPDATE ads SET
                name = ?,
                content = ?,
                caption = ?,
                ad_region_id = ?,
                label = ?,
                sort_order = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $ad_id : 0;
    }

    $row[] = (int) $user_id;
    $row[] = (int) $user_id;

    if (!db_q("INSERT INTO ads (
                name, content, caption, ad_region_id, label, sort_order,
                created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * @param int $ad_id
 * @return bool
 */
function pg_ad_delete($ad_id)
{
    return (bool) db_q("DELETE FROM ads WHERE id = ?", array((int) $ad_id));
}

/**
 * Creates (id 0) or updates an ad region.
 *
 * @param int         $region_id
 * @param string|null $name      null keeps the stored name (a manager may not
 *                               rename a region; a create always sends one)
 * @param array       $behavior  the six values of pg_ad_region_behavior_values()
 * @param int         $user_id
 * @return int the region's id, 0 when a query failed
 */
function pg_ad_region_save($region_id, $name, $behavior, $user_id)
{
    $region_id = (int) $region_id;
    $behavior = array_values(array_map('strval', $behavior));

    if ($region_id > 0) {
        $set = ($name === null) ? '' : 'name = ?, ';
        $row = ($name === null) ? array() : array((string) $name);
        $row = array_merge($row, $behavior, array((int) $user_id, $region_id));

        return db_q("UPDATE ad_regions SET
                " . $set . "display_type = ?,
                transition_type = ?,
                transition_duration = ?,
                slideshow = ?,
                slideshow_interval = ?,
                slideshow_continuous = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $region_id : 0;
    }

    $row = array_merge(array((string) $name), $behavior, array((int) $user_id, (int) $user_id));

    if (!db_q("INSERT INTO ad_regions (
                name, display_type, transition_type, transition_duration, slideshow,
                slideshow_interval, slideshow_continuous,
                created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes an ad region and the users' access to it. The caller has checked
 * pg_ad_region_has_ads() first.
 *
 * @param int $region_id
 * @return bool
 */
function pg_ad_region_delete($region_id)
{
    $region_id = (int) $region_id;

    return db_q("DELETE FROM ad_regions WHERE id = ?", array($region_id))
        && db_q("DELETE FROM users_ad_regions_xref WHERE ad_region_id = ?", array($region_id));
}
