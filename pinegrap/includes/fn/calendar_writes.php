<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Calendar writes: one write path the calendar screens (add_calendar.php,
 * edit_calendar.php) and the external API (/calendars) share, so a calendar
 * written from either place is written the same way.
 *
 * Nothing here checks who is asking: the screens gate on
 * validate_calendars_access() and validate_calendar_access(), the API on
 * calendars:write and the owner's assigned calendars. Every function returns
 * 0/false when a query failed.
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
 * Whether another calendar already has this name. Names are unique: the
 * calendar pick lists on pages and in the event screen show only the name.
 *
 * @param string $name
 * @param int    $except_id the calendar being edited, 0 on create
 * @return bool|null null when the query failed
 */
function pg_calendar_name_taken($name, $except_id = 0)
{
    $except_id = (int) $except_id;

    if ($except_id > 0) {
        $result = db_q("SELECT id FROM calendars WHERE (name = ?) AND (id != ?)", array((string) $name, $except_id));
    } else {
        $result = db_q("SELECT id FROM calendars WHERE (name = ?)", array((string) $name));
    }

    if (!$result) {
        return null;
    }

    return mysqli_num_rows($result) > 0;
}

/**
 * Creates (id 0) or renames a calendar.
 *
 * @param int    $calendar_id 0 to create
 * @param string $name checked by the caller (required, not taken)
 * @param int    $user_id the user the change is recorded for
 * @return int the calendar's id, 0 when a query failed
 */
function pg_calendar_save($calendar_id, $name, $user_id)
{
    $calendar_id = (int) $calendar_id;
    $user_id = (int) $user_id;

    if ($calendar_id > 0) {
        $result = db_q("UPDATE calendars SET
                name = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", array((string) $name, $user_id, $calendar_id));

        return $result ? $calendar_id : 0;
    }

    $result = db_q("INSERT INTO calendars (name, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES (?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", array((string) $name, $user_id, $user_id));

    return $result ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * How many events are on a calendar. A calendar is deleted only when it
 * holds none: the events are deleted or moved to another calendar first.
 *
 * @param int $calendar_id
 * @return int|null null when the query failed
 */
function pg_calendar_event_count($calendar_id)
{
    $result = db_q("SELECT COUNT(calendar_event_id) FROM calendar_events_calendars_xref WHERE calendar_id = ?", array((int) $calendar_id));

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_row($result);

    return (int) $row[0];
}

/**
 * Deletes a calendar with its user assignments and its place in the calendar
 * views and calendar event views. The caller checks that it holds no event
 * (pg_calendar_event_count()).
 *
 * @param int $calendar_id
 * @return bool
 */
function pg_calendar_delete($calendar_id)
{
    $calendar_id = (int) $calendar_id;

    foreach (array(
        'calendars' => 'id',
        'users_calendars_xref' => 'calendar_id',
        'calendar_views_calendars_xref' => 'calendar_id',
        'calendar_event_views_calendars_xref' => 'calendar_id',
        'calendar_events_calendars_xref' => 'calendar_id',
    ) as $table => $column) {
        if (!db_q("DELETE FROM " . $table . " WHERE " . $column . " = ?", array($calendar_id))) {
            return false;
        }
    }

    return true;
}
