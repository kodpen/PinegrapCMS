<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ActivityPub publishing: what the site publishes goes to its followers as
 * Create, Update and Delete activities through the ap_outbox queue.
 *
 * What is published: the records of the forms and the pages of the folders
 * chosen in ap_settings.publish (forms: custom form page ids, folders:
 * folder ids). A page counts while it is a standard page in a chosen
 * folder that is public, not the drafts and not in the recycle bin; a
 * record while it is complete and has an address on a public record page
 * that shows it (the record view open to everyone, its filters passed,
 * none comparing a field with the viewer). Anything else is never sent.
 *
 * How it is found: the general job sweeps (pg_ap_outbox_sweep()). It
 * compares what is published now, with each object's last change, against
 * the activities already logged, and queues the difference: Create for a
 * new object, Update for a changed one, Delete for one gone. Every path that
 * changes a page or a record (the panel, the designer, the API, a scheduled
 * move, the recycle bin) is covered, since none of them has to tell the
 * outbox. pg_announce() of a page or form event makes the next tick sweep;
 * otherwise a sweep runs every PG_AP_SWEEP_EVERY seconds. The first sweep
 * after the feature is switched on logs what is already published without
 * sending it, so followers do not receive the whole archive.
 *
 * The queue: one log row per activity (inbox '', state sent) that keeps the
 * activity for the outbox collection and the sweep, and one row per
 * follower inbox, a shared inbox once for all its followers. Delivery
 * (pg_ap_deliver()) signs and posts; a failure is retried on the mail
 * queue's ladder (pg_ap_backoff()); a refusal that will not change (4xx but
 * 408 and 429) fails at once.
 *
 * Loaded on demand (job.php, activitypub.php, pg_announce(), the tests).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub.php');

// Seconds between two sweeps when nothing announced a change.
if (!defined('PG_AP_SWEEP_EVERY')) {
    define('PG_AP_SWEEP_EVERY', 900);
}

// Activities one sweep queues; the rest wait for the next sweep, so a bulk
// import reaches followers a few at a time.
if (!defined('PG_AP_SWEEP_LIMIT')) {
    define('PG_AP_SWEEP_LIMIT', 20);
}

// Days a delivery row is kept once it was sent or given up.
if (!defined('PG_AP_DELIVERY_KEEP_DAYS')) {
    define('PG_AP_DELIVERY_KEEP_DAYS', 30);
}

// Attempts of one delivery before it is given up, as the mail queue's.
define('PG_AP_MAX_ATTEMPTS', 6);

define('PG_AP_PUBLIC', 'https://www.w3.org/ns/activitystreams#Public');

/**
 * The address of a published object: under the actor, by kind and id, so it
 * stays the same when the page or the record is renamed.
 *
 * @param array  $site pg_ap_site()
 * @param string $kind 'p' page, 'r' record
 * @param int    $id
 * @return string
 */
function pg_ap_object_id($site, $kind, $id)
{
    $urls = pg_ap_urls($site);

    return $urls['id'] . '/' . $kind . '/' . (int) $id;
}

/**
 * An ISO 8601 time in UTC.
 *
 * @param int $time
 * @return string
 */
function pg_ap_time($time)
{
    return gmdate('Y-m-d\TH:i:s\Z', (int) $time);
}

/**
 * Rich text as it goes out: sanitized as for visitors, every site address
 * made absolute (a follower's server reads it far from the site).
 *
 * @param string $html
 * @param array  $site pg_ap_site()
 * @return string
 */
function pg_ap_content_html($html, $site)
{
    $html = (string) $html;

    if (trim($html) === '') {
        return '';
    }

    if (function_exists('pg_sanitize_rich_text')) {
        $html = pg_sanitize_rich_text($html);
    }

    $parts = parse_url($site['base']);
    $origin = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . (isset($parts['host']) ? $parts['host'] : $site['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

    // A root-relative address takes the origin; one relative to the page
    // (no scheme, no slash, no fragment or query alone) takes the base.
    $html = preg_replace('#(\s(?:href|src)=")/(?!/)#i', '$1' . $origin . '/', $html);

    return preg_replace('#(\s(?:href|src)=")(?![a-z][a-z0-9+.\-]*:|/|\#|\?)#i', '$1' . $site['base'], $html);
}

/**
 * The Article for one published object.
 *
 * @param array $site pg_ap_site()
 * @param array $item id, url, name, description (HTML), published (time),
 *                    updated (time), images (list of absolute addresses)
 * @return array
 */
function pg_ap_article($site, $item)
{
    $urls = pg_ap_urls($site);
    $link = htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8');
    $description = pg_ap_content_html(isset($item['description']) ? $item['description'] : '', $site);

    if (($description !== '') && (stripos($description, '<p') !== 0)) {
        $description = '<p>' . $description . '</p>';
    }

    // The description is part of the content: Mastodon shows an Article's
    // summary as a content warning that hides the post.
    $content = $description . '<p><a href="' . $link . '">' . $link . '</a></p>';

    $article = array(
        'id'           => (string) $item['id'],
        'type'         => 'Article',
        'attributedTo' => $urls['id'],
        'name'         => (string) $item['name'],
        'content'      => $content,
        'mediaType'    => 'text/html',
        'url'          => (string) $item['url'],
        'published'    => pg_ap_time($item['published']),
        'to'           => array(PG_AP_PUBLIC),
        'cc'           => array($urls['followers']),
    );

    if (!empty($item['updated']) && ((int) $item['updated'] > (int) $item['published'])) {
        $article['updated'] = pg_ap_time($item['updated']);
    }

    $attachments = array();

    foreach ((isset($item['images']) ? (array) $item['images'] : array()) as $image) {
        if (preg_match('#^https?://#i', (string) $image)) {
            $attachments[] = array('type' => 'Image', 'mediaType' => pg_ap_media_type($image), 'url' => (string) $image, 'name' => (string) $item['name']);
        }
    }

    if ($attachments) {
        $article['attachment'] = $attachments;
    }

    return $article;
}

/**
 * The Article of a page.
 *
 * @param array $page page_id, page_name, page_title, page_meta_description,
 *                    published (time), updated (time)
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_object_for_page($page, $site)
{
    $name = trim((string) $page['page_title']);
    $path = function_exists('encode_url_path') ? encode_url_path((string) $page['page_name']) : rawurlencode((string) $page['page_name']);
    $description = trim((string) $page['page_meta_description']);

    return pg_ap_article($site, array(
        'id'          => pg_ap_object_id($site, 'p', $page['page_id']),
        'url'         => $site['base'] . $path,
        'name'        => ($name !== '') ? $name : (string) $page['page_name'],
        'description' => ($description !== '') ? htmlspecialchars($description, ENT_QUOTES, 'UTF-8') : '',
        'published'   => (int) $page['published'],
        'updated'     => (int) $page['updated'],
        'images'      => array(),
    ));
}

/**
 * The Article of a record.
 *
 * @param array $row  id, url (absolute), title, description (HTML or
 *                    text), images, published (time), updated (time)
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_object_for_record($row, $site)
{
    $title = trim((string) $row['title']);

    return pg_ap_article($site, array(
        'id'          => pg_ap_object_id($site, 'r', $row['id']),
        'url'         => (string) $row['url'],
        'name'        => ($title !== '') ? $title : (string) $row['url'],
        'description' => (string) $row['description'],
        'published'   => (int) $row['published'],
        'updated'     => (int) $row['updated'],
        'images'      => isset($row['images']) ? (array) $row['images'] : array(),
    ));
}

/**
 * An activity around an object: Create and Update carry the Article, Delete
 * a Tombstone of its address. The activity's address names the object, the
 * type and the object's change time, which is how the sweep reads the log
 * back (pg_ap_activity_key()).
 *
 * @param array        $site   pg_ap_site()
 * @param string       $type   Create, Update or Delete
 * @param array|string $object the Article, or the object's address for Delete
 * @param int          $stamp  the object's change time (Delete: now)
 * @return array
 */
function pg_ap_activity($site, $type, $object, $stamp)
{
    $urls = pg_ap_urls($site);
    $object_id = is_array($object) ? (string) $object['id'] : (string) $object;

    $activity = array(
        '@context'  => 'https://www.w3.org/ns/activitystreams',
        'id'        => $object_id . '#' . strtolower($type) . '-' . (int) $stamp,
        'type'      => $type,
        'actor'     => $urls['id'],
        'published' => pg_ap_time($stamp),
        'to'        => array(PG_AP_PUBLIC),
        'cc'        => array($urls['followers']),
        'object'    => ($type === 'Delete') ? array('id' => $object_id, 'type' => 'Tombstone') : $object,
    );

    return $activity;
}

/**
 * An activity address read back into the object, type and stamp, or null
 * for one this outbox did not write.
 *
 * @param string $activity_id
 * @return array|null object, type (create, update, delete), stamp
 */
function pg_ap_activity_key($activity_id)
{
    if (!preg_match('/^(.+\/[pr]\/\d+)#(create|update|delete)-(\d+)$/', (string) $activity_id, $match)) {
        return null;
    }

    return array('object' => $match[1], 'type' => $match[2], 'stamp' => (int) $match[3]);
}

/**
 * The last logged activity of each object.
 *
 * @param array $activity_ids the log's activity addresses, oldest first
 * @return array object address => array(type, stamp)
 */
function pg_ap_logged_objects($activity_ids)
{
    $out = array();

    foreach ((array) $activity_ids as $activity_id) {
        $key = pg_ap_activity_key($activity_id);

        if ($key !== null) {
            $out[$key['object']] = array('type' => $key['type'], 'stamp' => $key['stamp']);
        }
    }

    return $out;
}

/**
 * What a sweep queues: Create for a published object never sent or sent
 * deleted, Update for one changed after its last activity, Delete for a
 * sent object no longer published. Creates and Updates first in the order
 * given, Deletes after; at most $limit.
 *
 * @param array $current object address => change time, what is published now
 * @param array $logged  pg_ap_logged_objects()
 * @param int   $limit
 * @return array list of array(object, type: Create, Update or Delete)
 */
function pg_ap_sweep_plan($current, $logged, $limit = PG_AP_SWEEP_LIMIT)
{
    $plan = array();

    foreach ((array) $current as $object => $stamp) {
        $last = isset($logged[$object]) ? $logged[$object] : null;

        if (($last === null) || ($last['type'] === 'delete')) {
            $plan[] = array('object' => (string) $object, 'type' => 'Create');
        } elseif ((int) $stamp > $last['stamp']) {
            $plan[] = array('object' => (string) $object, 'type' => 'Update');
        }
    }

    foreach ((array) $logged as $object => $last) {
        if (($last['type'] !== 'delete') && !array_key_exists($object, (array) $current)) {
            $plan[] = array('object' => (string) $object, 'type' => 'Delete');
        }
    }

    return array_slice($plan, 0, max(0, (int) $limit));
}

/**
 * The inboxes one activity goes to: a follower's shared inbox, else its own,
 * each once, https only.
 *
 * @param array $followers rows with inbox and shared_inbox
 * @return array
 */
function pg_ap_delivery_inboxes($followers)
{
    $out = array();

    foreach ((array) $followers as $follower) {
        $inbox = trim((string) (!empty($follower['shared_inbox']) ? $follower['shared_inbox'] : (isset($follower['inbox']) ? $follower['inbox'] : '')));

        if (preg_match('#^https://#i', $inbox) && !in_array($inbox, $out, true)) {
            $out[] = $inbox;
        }
    }

    return $out;
}

/**
 * Seconds before the next attempt after $attempts failed ones, 0 past the
 * ladder: the mail queue's (pg_mail_backoff(), one minute to a day).
 *
 * @param int $attempts
 * @return int
 */
function pg_ap_backoff($attempts)
{
    $ladder = array(60, 300, 1800, 7200, 21600, 86400);
    $index = max(0, (int) $attempts - 1);

    return isset($ladder[$index]) ? $ladder[$index] : 0;
}

/**
 * What a delivery answer means: sent (2xx), failed (a refusal that will not
 * change: 4xx but 408 and 429) or retry (anything else).
 *
 * @param int $status 0 when no answer came
 * @return string
 */
function pg_ap_delivery_outcome($status)
{
    $status = (int) $status;

    if (($status >= 200) && ($status < 300)) {
        return 'sent';
    }

    if (($status >= 400) && ($status < 500) && !in_array($status, array(408, 429), true)) {
        return 'failed';
    }

    return 'retry';
}

/**
 * The row an attempt leaves: sent, failed, or queued again on the ladder.
 *
 * @param string $outcome  pg_ap_delivery_outcome()
 * @param int    $attempts attempts before this one
 * @param int    $now
 * @return array state, attempts, next_at
 */
function pg_ap_delivery_update($outcome, $attempts, $now)
{
    $attempt = (int) $attempts + 1;

    if ($outcome !== 'retry') {
        return array('state' => $outcome, 'attempts' => $attempt, 'next_at' => 0);
    }

    $wait = ($attempt < PG_AP_MAX_ATTEMPTS) ? pg_ap_backoff($attempt) : 0;

    return ($wait > 0)
        ? array('state' => 'queued', 'attempts' => $attempt, 'next_at' => (int) $now + $wait)
        : array('state' => 'failed', 'attempts' => $attempt, 'next_at' => 0);
}

/**
 * Whether the outbox can run: its tables are there (2026.11.0, 11.0.163).
 *
 * @return bool
 */
function pg_ap_outbox_ready()
{
    return pg_schema_has('ap_outbox') && pg_schema_has('ap_followers');
}

/**
 * The listener pg_announce() calls for a page or form event: the next tick
 * of the general job sweeps instead of waiting for PG_AP_SWEEP_EVERY.
 *
 * @param string $event
 */
function pg_ap_heard($event)
{
    if (preg_match('/^(page|form)\./', (string) $event) && pg_ap_enabled()) {
        pg_config_text_save('ap_outbox_due', '1');
    }
}

/**
 * The folders whose pages are published: the chosen ones that are public,
 * not archived, not the drafts and not in the recycle bin.
 *
 * @param array $folder_ids
 * @return array folder ids
 */
function pg_ap_open_folders($folder_ids)
{
    $folder_ids = array_values(array_filter(array_map('intval', (array) $folder_ids)));

    if (!$folder_ids) {
        return array();
    }

    $binned = function_exists('pg_recycle_bin_folder_ids') ? array_map('intval', pg_recycle_bin_folder_ids()) : array();
    $rows = db_items("SELECT folder_id FROM folder WHERE folder_id IN (" . implode(',', $folder_ids) . ") AND folder_archived = '0'");
    $out = array();

    foreach ((array) $rows as $row) {
        $id = (int) $row['folder_id'];

        if (in_array($id, $binned, true) || (function_exists('pg_page_is_draft') && pg_page_is_draft($id))) {
            continue;
        }

        if (get_access_control_type($id) === 'public') {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The published pages: standard pages of the open chosen folders, the home
 * page left out.
 *
 * @param array $folder_ids pg_ap_open_folders()
 * @return array page id => row (page_id, page_name, page_title,
 *               page_meta_description, stamp)
 */
function pg_ap_published_pages($folder_ids)
{
    if (!$folder_ids) {
        return array();
    }

    $rows = db_items(
        "SELECT page_id, page_name, page_title, page_meta_description, page_timestamp AS stamp
         FROM page
         WHERE page_folder IN (" . implode(',', array_map('intval', $folder_ids)) . ")
           AND page_type = 'standard'
           AND page_home != 'yes'
         ORDER BY page_id");

    $out = array();

    foreach ((array) $rows as $row) {
        $out[(int) $row['page_id']] = $row;
    }

    return $out;
}

/**
 * The page a form's records are addressed on: a standard page in a public
 * folder (not archived, not the drafts, not in the bin) with a record view
 * of the form that is open to everyone and does not compare a field with
 * the viewer. Null when there is none: the records are then not published.
 *
 * @param int $form_page_id
 * @return array|null page_id, page_name, cfg (the record view's)
 */
function pg_ap_record_page($form_page_id)
{
    $form_page_id = (int) $form_page_id;

    if (($form_page_id <= 0) || !function_exists('pg_sw_page_widget')) {
        return null;
    }

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    $pages = db_items(
        "SELECT page.page_id, page.page_name, page.page_folder
         FROM page
         INNER JOIN folder ON page.page_folder = folder.folder_id
         WHERE page.page_type = 'standard' AND folder.folder_archived = '0'$binned
         ORDER BY page.page_id");
    $open = array();

    foreach ((array) $pages as $page) {
        $folder = (int) $page['page_folder'];

        if (!isset($open[$folder])) {
            $open[$folder] = !(function_exists('pg_page_is_draft') && pg_page_is_draft($folder)) && (get_access_control_type($folder) === 'public');
        }

        if (!$open[$folder]) {
            continue;
        }

        $view = pg_sw_page_widget((int) $page['page_id'], 'form_item_view', function ($cfg) use ($form_page_id) {
            return pg_sw_form_widget_form_page_id($cfg) === $form_page_id;
        });

        if ($view && pg_sw_form_widget_public($view['cfg']) && !pg_sw_filters_compare_viewer(pg_sw_record_view_filters($view['cfg']))) {
            return array('page_id' => (int) $page['page_id'], 'page_name' => (string) $page['page_name'], 'cfg' => $view['cfg']);
        }
    }

    return null;
}

/**
 * The published records of one form: complete, with a reference code, and
 * shown by its record page under that view's filters.
 *
 * @param int   $form_page_id
 * @param array $record_page pg_ap_record_page()
 * @return array record id => row (id, reference_code, address_name,
 *               submitted_timestamp, stamp)
 */
function pg_ap_published_records($form_page_id, $record_page)
{
    $filter = pg_sw_record_filter_clause((int) $form_page_id, pg_sw_record_view_filters($record_page['cfg']), $record_page['page_id'], true);
    $rows = db_items(
        "SELECT forms.id, forms.reference_code, forms.address_name, forms.submitted_timestamp,
                GREATEST(forms.submitted_timestamp, forms.last_modified_timestamp) AS stamp
         FROM forms
         " . $filter['join'] . "
         WHERE forms.page_id = '" . (int) $form_page_id . "'
           AND forms.complete = 1
           AND forms.reference_code != ''
           " . $filter['where'] . "
         ORDER BY forms.id");

    $out = array();

    foreach ((array) $rows as $row) {
        $out[(int) $row['id']] = $row;
    }

    return $out;
}

/**
 * What is published now, by object address.
 *
 * @param array $site     pg_ap_site()
 * @param array $settings pg_ap_settings()
 * @return array object address => array(kind, row, stamp, record_page)
 */
function pg_ap_published($site, $settings)
{
    $publish = $settings['publish'];
    $out = array();

    foreach (pg_ap_published_pages(pg_ap_open_folders(isset($publish['folders']) ? $publish['folders'] : array())) as $id => $row) {
        $out[pg_ap_object_id($site, 'p', $id)] = array('kind' => 'p', 'row' => $row, 'stamp' => (int) $row['stamp'], 'record_page' => null);
    }

    foreach (array_unique(array_map('intval', isset($publish['forms']) ? (array) $publish['forms'] : array())) as $form_page_id) {
        $record_page = pg_ap_record_page($form_page_id);

        if ($record_page === null) {
            continue;
        }

        $record_page['form_page_id'] = $form_page_id;

        foreach (pg_ap_published_records($form_page_id, $record_page) as $id => $row) {
            $out[pg_ap_object_id($site, 'r', $id)] = array('kind' => 'r', 'row' => $row, 'stamp' => (int) $row['stamp'], 'record_page' => $record_page);
        }
    }

    return $out;
}

/**
 * The record fields an Article needs: its address, its RSS title, its
 * first RSS description (HTML kept, text escaped) and its media picture
 * when everyone may open it.
 *
 * @param array $row         pg_ap_published_records() row
 * @param array $record_page pg_ap_record_page() with form_page_id
 * @return array for pg_ap_object_for_record()
 */
function pg_ap_record_fields($row, $record_page)
{
    $form_id = (int) $row['id'];
    $origin = URL_SCHEME . HOSTNAME_SETTING;
    $pretty = pg_sw_form_pretty_urls((int) $record_page['form_page_id']);
    $text = pg_sw_submitted_form_rss_text($form_id);

    $description = '';
    $value = db_item(
        "SELECT form_data.data, form_data.type
         FROM form_data
         INNER JOIN form_fields ON form_data.form_field_id = form_fields.id
         WHERE form_data.form_id = '" . $form_id . "' AND form_fields.rss_field = 'description' AND form_data.data != ''
         ORDER BY form_fields.sort_order ASC, form_data.id ASC
         LIMIT 1");

    if (is_array($value)) {
        $description = ($value['type'] === 'html') ? (string) $value['data'] : nl2br(htmlspecialchars((string) $value['data'], ENT_QUOTES, 'UTF-8'));
    }

    $images = array();
    $media = db_item(
        "SELECT form_data.data, form_data.file_id
         FROM form_data
         INNER JOIN form_fields ON form_data.form_field_id = form_fields.id
         WHERE form_data.form_id = '" . $form_id . "' AND form_fields.rss_field = 'media'
         ORDER BY form_fields.sort_order ASC
         LIMIT 1");

    if (is_array($media) && !empty($media['file_id']) && pg_file_open_to_everyone($media['file_id'])) {
        $file = db_item("SELECT name FROM files WHERE id = '" . (int) $media['file_id'] . "' LIMIT 1");

        if (is_array($file)) {
            $images[] = $origin . PATH . rawurlencode((string) $file['name']);
        }
    } elseif (is_array($media) && preg_match('#^https?://\S+\.(jpe?g|png|gif|webp|avif)$#i', trim((string) $media['data']))) {
        $images[] = trim((string) $media['data']);
    }

    return array(
        'id'          => $form_id,
        'url'         => $origin . pg_sw_record_url($record_page['page_name'], $row['reference_code'], $row['address_name'], $pretty, PATH),
        'title'       => $text['title'],
        'description' => $description,
        'images'      => $images,
        'published'   => (int) $row['submitted_timestamp'],
        'updated'     => (int) $row['stamp'],
    );
}

/**
 * The Article of a published object.
 *
 * @param array  $site
 * @param string $object_id
 * @param array  $entry pg_ap_published() entry
 * @return array
 */
function pg_ap_published_article($site, $object_id, $entry)
{
    if ($entry['kind'] === 'r') {
        return pg_ap_object_for_record(pg_ap_record_fields($entry['row'], $entry['record_page']), $site);
    }

    // A page has no time of its own for when it went out: its first Create.
    // An underscore of the handle matches any letter here, which the rest of
    // the address keeps exact.
    $first = (int) db_value("SELECT MIN(created_at) FROM ap_outbox WHERE inbox = '' AND activity_id LIKE '" . e($object_id) . "#create-%'");
    $page = $entry['row'];
    $page['published'] = ($first > 0) ? min($first, $entry['stamp']) : $entry['stamp'];
    $page['updated'] = $entry['stamp'];

    return pg_ap_object_for_page($page, $site);
}

/**
 * Queues one activity: its log row, and one delivery row per inbox. A
 * delivery of an unlogged activity (an Accept) already waiting for the same
 * inbox is not queued twice, so a repeated Follow gets one Accept.
 *
 * @param array        $activity
 * @param array|string $inboxes pg_ap_delivery_inboxes(), or one inbox
 * @param int|null     $now     null: the current time
 * @param bool         $logged  true: also the log row the sweep reads back
 *                              (Create, Update, Delete)
 * @return bool false when a row could not be written
 */
function pg_ap_queue($activity, $inboxes, $now = null, $logged = false)
{
    $now = ($now === null) ? time() : (int) $now;
    $id = isset($activity['id']) ? (string) $activity['id'] : '';
    $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = true;

    if ($logged) {
        $ok = (bool) db_q("INSERT INTO ap_outbox (activity_id, inbox, body, attempts, next_at, state, created_at) VALUES (?, '', ?, 0, 0, 'sent', ?)", array($id, $body, $now));
    }

    foreach ((array) $inboxes as $inbox) {
        if (!$logged) {
            $waiting = db_q("SELECT id FROM ap_outbox WHERE activity_id = ? AND inbox = ? AND state = 'queued' LIMIT 1", array($id, $inbox));

            if ($waiting && mysqli_num_rows($waiting)) {
                continue;
            }
        }

        $ok = (bool) db_q("INSERT INTO ap_outbox (activity_id, inbox, body, attempts, next_at, state, created_at) VALUES (?, ?, ?, 0, ?, 'queued', ?)", array($id, $inbox, $body, $now, $now)) && $ok;
    }

    return $ok;
}

/**
 * One sweep: what is published compared with the log, the difference
 * queued. The first sweep only logs what is already published (no body, no
 * delivery).
 *
 * @param int $now
 * @return array queued (count), baseline (count)
 */
function pg_ap_outbox_sweep($now)
{
    $out = array('queued' => 0, 'baseline' => 0);
    $site = pg_ap_site();
    $current = pg_ap_published($site, pg_ap_settings());

    $stamps = array();

    foreach ($current as $object_id => $entry) {
        $stamps[$object_id] = $entry['stamp'];
    }

    $log = db_items("SELECT activity_id FROM ap_outbox WHERE inbox = '' ORDER BY id");
    $logged = pg_ap_logged_objects(array_column((array) $log, 'activity_id'));

    if (pg_config_text('ap_outbox_since') === '') {
        foreach ($stamps as $object_id => $stamp) {
            if (!isset($logged[$object_id])) {
                db_q("INSERT INTO ap_outbox (activity_id, inbox, body, attempts, next_at, state, created_at) VALUES (?, '', '', 0, 0, 'sent', ?)", array($object_id . '#create-' . (int) $stamp, (int) $now));
                $out['baseline']++;
            }
        }

        pg_config_text_save('ap_outbox_since', (string) (int) $now);

        return $out;
    }

    $plan = pg_ap_sweep_plan($stamps, $logged);

    if (!$plan) {
        return $out;
    }

    $inboxes = pg_ap_delivery_inboxes(db_items("SELECT inbox, shared_inbox FROM ap_followers ORDER BY id"));

    foreach ($plan as $step) {
        $object_id = $step['object'];

        if ($step['type'] === 'Delete') {
            $activity = pg_ap_activity($site, 'Delete', $object_id, max((int) $now, $logged[$object_id]['stamp'] + 1));
        } else {
            $activity = pg_ap_activity($site, $step['type'], pg_ap_published_article($site, $object_id, $current[$object_id]), $current[$object_id]['stamp']);
        }

        pg_ap_queue($activity, $inboxes, $now, true);
        $out['queued']++;
    }

    return $out;
}

/**
 * Signs one delivery and posts it.
 *
 * @param array $row inbox, body
 * @return array status (0 without an answer), error
 */
function pg_ap_deliver($row)
{
    $inbox = (string) $row['inbox'];
    $body = (string) $row['body'];
    $signed = pg_ap_sign('POST', $inbox, $body);

    if (!$signed) {
        return array('status' => 0, 'error' => 'no key');
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $answer = pg_curl_run('POST', $inbox, array(
        'headers'       => array_merge(array('Content-Type: application/activity+json', 'Accept: application/activity+json'), $signed),
        'body'          => $body,
        'timeout'       => 10,
        'max_size'      => 65536,
        'check_address' => true,
    ));

    return array('status' => (int) $answer['status'], 'error' => (string) $answer['error']);
}

/**
 * The general job's part: a sweep when one is due, then the deliveries
 * that are due, then old delivery rows removed.
 *
 * @param int $limit deliveries in this run
 * @param int $now
 * @return array queued, baseline, sent, failed, retried
 */
function pg_ap_outbox_run($limit = 25, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $out = array('queued' => 0, 'baseline' => 0, 'sent' => 0, 'failed' => 0, 'retried' => 0);

    if (!pg_ap_enabled() || !pg_ap_outbox_ready()) {
        return $out;
    }

    $swept = (int) pg_config_text('ap_outbox_swept');

    if ((pg_config_text('ap_outbox_due') !== '') || (($now - $swept) >= PG_AP_SWEEP_EVERY)) {
        // Cleared first: a change announced while this sweep runs makes
        // the next tick sweep again.
        pg_config_text_save('ap_outbox_due', '');
        pg_config_text_save('ap_outbox_swept', (string) $now);
        $sweep = pg_ap_outbox_sweep($now);
        $out['queued'] = $sweep['queued'];
        $out['baseline'] = $sweep['baseline'];

        db("DELETE FROM ap_outbox WHERE inbox != '' AND state != 'queued' AND created_at < '" . ($now - (PG_AP_DELIVERY_KEEP_DAYS * 86400)) . "'");
    }

    $rows = db_items(
        "SELECT id, inbox, body, attempts, next_at
         FROM ap_outbox
         WHERE state = 'queued' AND next_at <= '$now' AND inbox != ''
         ORDER BY next_at, id
         LIMIT " . max(1, (int) $limit));

    foreach ((array) $rows as $row) {
        $id = (int) $row['id'];

        // Claimed by moving its time on, so a run that overlaps this one
        // does not post it twice.
        db("UPDATE ap_outbox SET next_at = '" . ($now + 600) . "' WHERE id = '$id' AND state = 'queued' AND next_at = '" . (int) $row['next_at'] . "'");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $answer = pg_ap_deliver($row);
        $update = pg_ap_delivery_update(pg_ap_delivery_outcome($answer['status']), (int) $row['attempts'], time());

        db("UPDATE ap_outbox SET state = '" . e($update['state']) . "', attempts = '" . (int) $update['attempts'] . "', next_at = '" . (int) $update['next_at'] . "' WHERE id = '$id'");

        $key = ($update['state'] === 'queued') ? 'retried' : $update['state'];
        $out[$key]++;
    }

    return $out;
}

/**
 * The activities the public outbox collection shows, newest first: of each
 * object only its newest activity. An older Create or Update carries the
 * content as it was then; once the object is deleted (taken off the site,
 * moved to a closed folder or the recycle bin, filtered out of its record
 * page) or rewritten, that content is no longer the site's to hand out, so
 * only the Tombstone or the newest Article stays. An activity whose address
 * this outbox did not write is kept as it is.
 *
 * @param array $activities decoded activities, newest first
 * @param int   $limit
 * @return array
 */
function pg_ap_outbox_visible($activities, $limit)
{
    $out = array();
    $seen = array();
    $limit = max(0, (int) $limit);

    foreach ((array) $activities as $activity) {
        if (count($out) >= $limit) {
            break;
        }

        if (!is_array($activity)) {
            continue;
        }

        $key = pg_ap_activity_key(isset($activity['id']) ? (string) $activity['id'] : '');

        if ($key !== null) {
            if (isset($seen[$key['object']])) {
                continue;
            }

            $seen[$key['object']] = true;
        }

        $out[] = $activity;
    }

    return $out;
}

/**
 * The last activities with a body, newest first, for the outbox collection
 * (pg_ap_outbox_visible()). More log rows than shown are read: the
 * superseded ones are left out.
 *
 * @param int $limit
 * @return array
 */
function pg_ap_outbox_items($limit = 20)
{
    if (!pg_ap_outbox_ready()) {
        return array();
    }

    $limit = max(1, (int) $limit);
    $activities = array();

    foreach ((array) db_items("SELECT body FROM ap_outbox WHERE inbox = '' AND body != '' ORDER BY id DESC LIMIT " . ($limit * 5)) as $row) {
        $activity = json_decode((string) $row['body'], true);

        if (is_array($activity)) {
            unset($activity['@context']);
            $activities[] = $activity;
        }
    }

    return pg_ap_outbox_visible($activities, $limit);
}
