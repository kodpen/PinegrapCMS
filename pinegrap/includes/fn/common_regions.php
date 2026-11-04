<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Common regions: the one write path the create and edit screens
 * (add_common_region.php, edit_common_region.php) and the external API
 * (/design/common-regions) share, so a row written from either place is
 * written the same way.
 *
 * The callers check the name first (pg_region_form_name_refusal(),
 * pg_cregion_name_taken() in includes/forms/common_region_form.php) and keep
 * a region drawn by its shared component (pg_common_region_components())
 * from being deleted or given content; nothing here checks who is asking:
 * the screens gate on the designer role, the API on design:write.
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
 * Creates (id 0) or updates a row of the cregion table.
 *
 * @param int         $cregion_id
 * @param string      $name
 * @param string|null $content        prepared for the database (site links as
 *                                    {path}); null keeps the stored content
 *                                    (a region drawn by its component)
 * @param string|null $designer_type  'no' common, 'yes' designer region;
 *                                    null keeps the stored type ('no' on create)
 * @param int         $user_id
 * @return int the region's id, 0 when a query failed
 */
function pg_cregion_save($cregion_id, $name, $content, $designer_type, $user_id)
{
    $cregion_id = (int) $cregion_id;
    $designer_type = ($designer_type === null) ? null : (($designer_type === 'yes') ? 'yes' : 'no');

    if ($cregion_id > 0) {
        $set = array('cregion_name = ?');
        $row = array((string) $name);

        if ($content !== null) {
            $set[] = 'cregion_content = ?';
            $row[] = (string) $content;
        }

        if ($designer_type !== null) {
            $set[] = 'cregion_designer_type = ?';
            $row[] = $designer_type;
        }

        $row[] = (int) $user_id;
        $row[] = $cregion_id;

        return db_q("UPDATE cregion SET
                " . implode(', ', $set) . ",
                cregion_user = ?,
                cregion_timestamp = UNIX_TIMESTAMP()
            WHERE cregion_id = ?", $row) ? $cregion_id : 0;
    }

    if (!db_q("INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp)
            VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())", array(
                (string) $name,
                (string) $content,
                ($designer_type === null) ? 'no' : $designer_type,
                (int) $user_id,
            ))) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes a region and the users' access to it. The caller has checked that
 * no shared component draws it (#5516).
 *
 * @param int $cregion_id
 * @return bool
 */
function pg_cregion_delete($cregion_id)
{
    $cregion_id = (int) $cregion_id;

    return db_q("DELETE FROM cregion WHERE cregion_id = ?", array($cregion_id))
        && db_q("DELETE FROM users_common_regions_xref WHERE common_region_id = ?", array($cregion_id));
}
