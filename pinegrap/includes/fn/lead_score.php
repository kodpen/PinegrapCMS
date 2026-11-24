<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor journey's scores: the site's interest score settings, the
 * behaviour events read from a contact's journey, the fit score from the
 * contact's own fields, the interests (the folders of the pages seen), the
 * general job's pass that works them out for the contacts touched since the
 * last pass, and the contact screen's "Interest" card.
 *
 * Scores are never worked out in the visitor's request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey.php');

/** Default score from which a contact is hot. */
const PG_LEAD_HOT_DEFAULT = 60;

/** Interests kept on a contact. */
const PG_LEAD_TOPICS_MAX = 5;

/** A page seen counts at least this many seconds towards its interest. */
const PG_LEAD_TOPIC_MIN_SECONDS = 10;

/** Contacts the scoring pass works out in one run at most. */
const PG_LEAD_SCORE_BATCH = 200;

/** Seconds between two scoring passes. */
const PG_LEAD_SCORE_EVERY = 600;

/** Seconds of a job tick the scoring pass may take. */
const PG_LEAD_SCORE_TICK_SECONDS = 10;

/**
 * The site's interest score settings (config_text 'lead_score') made safe:
 * the hot threshold (1 to 100), each act's points, the pricing and contact
 * pages (page ids; none means the page names guessed in code), the half-life
 * in days (1 to 365) and the keep period in months (1 to 36). Pure.
 *
 * @param mixed $raw the stored JSON text or its decoded array
 * @return array threshold, weights, pricing_pages, contact_pages, half_life, keep_months
 */
function pg_lead_score_settings($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : $raw;
    $data = is_array($data) ? $data : array();

    $number = function ($key, $default, $min, $max) use ($data) {
        if (!isset($data[$key]) || !is_numeric($data[$key])) {
            return $default;
        }

        return (int) max($min, min($max, (int) $data[$key]));
    };

    $pages = function ($key) use ($data) {
        $ids = (isset($data[$key]) && is_array($data[$key])) ? array_map('intval', $data[$key]) : array();

        return array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
    };

    return array(
        'threshold' => $number('threshold', PG_LEAD_HOT_DEFAULT, 1, 100),
        'weights' => pg_lead_score_weights((isset($data['weights']) && is_array($data['weights'])) ? $data['weights'] : array()),
        'pricing_pages' => $pages('pricing_pages'),
        'contact_pages' => $pages('contact_pages'),
        'half_life' => $number('half_life', PG_LEAD_HALF_LIFE_DAYS, 1, 365),
        'keep_months' => $number('keep_months', PG_LEAD_KEEP_MONTHS, 1, 36),
    );
}

/**
 * Which counted page a page is: 'pricing_page', 'contact_page' or ''. With
 * pages chosen in the settings only those count; without, a page whose name
 * reads as one (pricing, fiyat, contact, iletisim). Pure.
 *
 * @param string   $page    the page name
 * @param string[] $pricing the chosen pricing pages' names
 * @param string[] $contact the chosen contact pages' names
 * @return string
 */
function pg_lead_score_page_kind($page, array $pricing, array $contact)
{
    $page = strtolower(trim((string) $page));

    if ($page === '') {
        return '';
    }

    $guess = array(
        'pricing_page' => '/^(pricing|prices|plans|fiyat|fiyatlar|fiyat-listesi|ucretler|paketler)$/',
        'contact_page' => '/^(contact|contact-us|iletisim|bize-ulasin|bize-ulasin-form)$/',
    );

    foreach (array('pricing_page' => $pricing, 'contact_page' => $contact) as $kind => $names) {
        $names = array_map('strtolower', $names);

        if ($names ? in_array($page, $names, true) : (preg_match($guess[$kind], $page) === 1)) {
            return $kind;
        }
    }

    return '';
}

/**
 * The behaviour events of a contact's journey, as pg_lead_interest_score()
 * reads them: a visit that opened a pricing or contact page (once a visit
 * each), a visit within 7 days of the one before, a form, a booking, a
 * campaign e-mail opened, a download. Pure.
 *
 * @param array[]  $rows    pg_lead_journey_read()'s rows: kind, at, pages for a visit
 * @param string[] $pricing the chosen pricing pages' names
 * @param string[] $contact the chosen contact pages' names
 * @return array[] kind, at
 */
function pg_lead_score_events(array $rows, array $pricing = array(), array $contact = array())
{
    $events = array();
    $visits = array();

    foreach ($rows as $row) {
        $kind = (string) ($row['kind'] ?? '');
        $at = (int) ($row['at'] ?? 0);

        if (in_array($kind, array('form', 'booking', 'campaign_click', 'download'), true)) {
            $events[] = array('kind' => $kind, 'at' => $at);
            continue;
        }

        if ($kind !== 'visit') {
            continue;
        }

        $visits[] = $at;
        $seen = array();

        foreach ((array) ($row['pages'] ?? array()) as $page) {
            $page_kind = pg_lead_score_page_kind(is_array($page) ? ($page[0] ?? '') : $page, $pricing, $contact);

            if (($page_kind !== '') && !isset($seen[$page_kind])) {
                $seen[$page_kind] = true;
                $events[] = array('kind' => $page_kind, 'at' => $at);
            }
        }
    }

    sort($visits);

    foreach ($visits as $index => $at) {
        if (($index > 0) && pg_lead_is_quick_return($visits[$index - 1], $at)) {
            $events[] = array('kind' => 'return_7d', 'at' => $at);
        }
    }

    return $events;
}

/**
 * Whether an e-mail domain is a free mail service's, so says nothing of a
 * company. Pure.
 *
 * @param string $domain
 * @return bool
 */
function pg_lead_free_mail_domain($domain)
{
    $domain = strtolower(trim((string) $domain));
    $free = array(
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.com.tr', 'outlook.com', 'outlook.com.tr', 'live.com', 'msn.com',
        'windowslive.com', 'yahoo.com', 'yahoo.com.tr', 'ymail.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com',
        'yandex.com', 'yandex.com.tr', 'yandex.ru', 'mail.ru', 'mynet.com', 'proton.me', 'protonmail.com', 'gmx.com',
        'gmx.de', 'gmx.net', 'web.de', 'zoho.com', 'tutanota.com', 'mail.com', 'qq.com', '163.com',
    );

    return in_array($domain, $free, true);
}

/**
 * The fit score of a contact from its own fields: an e-mail on a company
 * domain (40), a company (25), a title (20), a contact group (15); 0 to 100
 * with the top three reasons. Pure.
 *
 * @param array $facts email, company, title, groups (count)
 * @return array{score: int, reasons: array[]}
 */
function pg_lead_fit_score(array $facts)
{
    $email = strtolower(trim((string) ($facts['email'] ?? '')));
    $at = strrpos($email, '@');
    $domain = ($at !== false) ? substr($email, $at + 1) : '';

    $reasons = array();

    if (($domain !== '') && (strpos($domain, '.') !== false) && !pg_lead_free_mail_domain($domain)) {
        $reasons[] = array('kind' => 'company_domain', 'points' => 40);
    }

    if (trim((string) ($facts['company'] ?? '')) !== '') {
        $reasons[] = array('kind' => 'company', 'points' => 25);
    }

    if (trim((string) ($facts['title'] ?? '')) !== '') {
        $reasons[] = array('kind' => 'title', 'points' => 20);
    }

    if ((int) ($facts['groups'] ?? 0) > 0) {
        $reasons[] = array('kind' => 'group', 'points' => 15);
    }

    return array(
        'score' => (int) min(100, array_sum(array_column($reasons, 'points'))),
        'reasons' => array_slice($reasons, 0, 3),
    );
}

/**
 * A contact's interests: the folders of the pages its visits saw and, on a
 * catalogue page, the product's categories (its product groups) or the
 * product group shown instead of the page's folder. Each is weighted by the
 * seconds spent there (at least PG_LEAD_TOPIC_MIN_SECONDS a page; a product
 * in several groups shares them out evenly) and faded by the half-life from
 * the visit's start; the top five, heaviest first (ties by name). Pages in
 * no folder but the site's root, and items no longer in the catalogue
 * shown on such a page, count for nothing. Pure.
 *
 * @param array[]   $rows       pg_lead_journey_read()'s rows; only visits count
 * @param array     $folders    page name => array(id, name)
 * @param int       $now
 * @param int|float $half_life_days
 * @param array     $categories 'type:id' (pg_lead_page_item_types()) =>
 *                              list of array(group id, group name)
 * @return array[] id, kind ('folder' or 'category'), name, weight
 */
function pg_lead_interest_topics(array $rows, array $folders, $now, $half_life_days = PG_LEAD_HALF_LIFE_DAYS, array $categories = array())
{
    $topics = array();

    $add = function ($kind, $id, $name, $weight) use (&$topics) {
        $key = $kind . ':' . (int) $id;

        if (!isset($topics[$key])) {
            $topics[$key] = array('id' => (int) $id, 'kind' => $kind, 'name' => (string) $name, 'weight' => 0.0);
        }

        $topics[$key]['weight'] += $weight;
    };

    foreach ($rows as $row) {
        if ((string) ($row['kind'] ?? '') !== 'visit') {
            continue;
        }

        foreach ((array) ($row['pages'] ?? array()) as $page) {
            $name = is_array($page) ? (string) ($page[0] ?? '') : (string) $page;
            $item = (is_array($page) && isset($page[2], $page[3])) ? (string) $page[2] . ':' . (int) $page[3] : '';
            $groups = (($item !== '') && !empty($categories[$item])) ? $categories[$item] : array();

            if (!$groups && !isset($folders[$name])) {
                continue;
            }

            $seconds = max(PG_LEAD_TOPIC_MIN_SECONDS, is_array($page) ? (int) ($page[1] ?? 0) : 0);
            $weight = pg_lead_score_decay($seconds, (int) $now - (int) ($row['at'] ?? 0), $half_life_days);

            if (!$groups) {
                $add('folder', $folders[$name][0], $folders[$name][1], $weight);
                continue;
            }

            foreach ($groups as $group) {
                $add('category', $group[0], $group[1], $weight / count($groups));
            }
        }
    }

    $topics = array_values($topics);
    usort($topics, function ($a, $b) {
        if ($a['weight'] == $b['weight']) {
            return strcmp($a['name'], $b['name']);
        }

        return ($a['weight'] < $b['weight']) ? 1 : -1;
    });

    foreach ($topics as $index => $topic) {
        $topics[$index]['weight'] = round($topic['weight'], 1);
    }

    return array_slice($topics, 0, PG_LEAD_TOPICS_MAX);
}

/**
 * The 'type:id' keys of the catalogue items a journey's visits showed, as
 * pg_lead_interest_topics() looks them up. Pure.
 *
 * @param array[] $rows pg_lead_journey_read()'s rows
 * @return array type => list of ids
 */
function pg_lead_interest_items(array $rows)
{
    $items = array();

    foreach ($rows as $row) {
        if ((string) ($row['kind'] ?? '') !== 'visit') {
            continue;
        }

        foreach ((array) ($row['pages'] ?? array()) as $page) {
            if (is_array($page) && isset($page[2], $page[3]) && in_array((string) $page[2], pg_lead_page_item_types(), true) && ((int) $page[3] > 0)) {
                $items[(string) $page[2]][(int) $page[3]] = (int) $page[3];
            }
        }
    }

    return array_map('array_values', $items);
}

/**
 * The product groups of the products and the product groups a journey
 * showed, keyed as pg_lead_interest_topics() takes them. Only groups still
 * in the catalogue count, and never the catalogue's top group (it holds
 * everything, as the site's root folder does).
 *
 * @param array $items pg_lead_interest_items()'s answer
 * @return array 'type:id' => list of array(group id, group name)
 */
function pg_lead_interest_categories(array $items)
{
    $categories = array();

    if (!empty($items['product'])) {
        foreach ((array) db_items("SELECT x.product, g.id, g.name FROM products_groups_xref x
            INNER JOIN product_groups g ON g.id = x.product_group AND g.parent_id <> 0
            WHERE x.product IN (" . implode(',', array_map('intval', $items['product'])) . ")
            ORDER BY g.name, g.id") as $group) {
            $categories['product:' . (int) $group['product']][] = array((int) $group['id'], (string) $group['name']);
        }
    }

    if (!empty($items['product_group'])) {
        foreach ((array) db_items("SELECT id, name FROM product_groups WHERE parent_id <> 0 AND id IN (" . implode(',', array_map('intval', $items['product_group'])) . ")") as $group) {
            $categories['product_group:' . (int) $group['id']][] = array((int) $group['id'], (string) $group['name']);
        }
    }

    return $categories;
}

/**
 * A reason code as the Interest card says it.
 *
 * @param string $kind
 * @return string
 */
function pg_lead_score_reason_text($kind)
{
    $texts = array(
        'pricing_page' => lang('Opened the pricing page'),
        'contact_page' => lang('Opened the contact page'),
        'return_7d' => lang('Came back within 7 days'),
        'form' => lang('Sent a form'),
        'booking' => lang('Made a booking'),
        'campaign_click' => lang('Opened a campaign e-mail'),
        'download' => lang('Downloaded a file'),
        'company_domain' => lang('Writes from a company e-mail address'),
        'company' => lang('Company is filled in'),
        'title' => lang('Title is filled in'),
        'group' => lang('Is in a contact group'),
    );

    return isset($texts[$kind]) ? $texts[$kind] : '';
}

/**
 * The contacts list's Score cell: the interest score, with a "Hot" pill
 * from the site's threshold on; empty for a contact not scored. Pure but
 * for lang().
 *
 * @param mixed $interest contact_scores.interest, null without a row
 * @param int   $threshold
 * @return string HTML
 */
function pg_lead_score_list_cell($interest, $threshold)
{
    if (($interest === null) || ($interest === '')) {
        return '';
    }

    $interest = (int) $interest;

    return (($interest >= (int) $threshold) ? '<span class="pg-pill pg-tone-rose me-2">' . h(lang('Hot')) . '</span>' : '')
        . $interest;
}

/**
 * The interest score settings as Site Settings posted them (the
 * "Interest score" row): each value checked against its bounds, a field out
 * of them named with its message. Pure but for lang().
 *
 * @param array $post the posted fields: lead_score_threshold,
 *                    lead_score_weight_<kind>, lead_score_pricing_pages,
 *                    lead_score_contact_pages, lead_score_half_life,
 *                    lead_score_keep_months
 * @return array settings (pg_lead_score_settings()'s shape), errors (field => message)
 */
function pg_lead_score_settings_input(array $post)
{
    $errors = array();
    $number = function ($field, $min, $max, $message) use ($post, &$errors) {
        $value = trim((string) ($post[$field] ?? ''));

        if (!preg_match('/^\d{1,4}$/', $value) || ((int) $value < $min) || ((int) $value > $max)) {
            $errors[$field] = $message;
            return $min;
        }

        return (int) $value;
    };

    $weights = array();

    foreach (array_keys(pg_lead_score_weights()) as $kind) {
        $weights[$kind] = $number('lead_score_weight_' . $kind, 0, 100, lang('Give each act 0 to 100 points.'));
    }

    $data = array(
        'threshold' => $number('lead_score_threshold', 1, 100, lang('Hot from a score of 1 to 100.')),
        'weights' => $weights,
        'pricing_pages' => (isset($post['lead_score_pricing_pages']) && is_array($post['lead_score_pricing_pages'])) ? $post['lead_score_pricing_pages'] : array(),
        'contact_pages' => (isset($post['lead_score_contact_pages']) && is_array($post['lead_score_contact_pages'])) ? $post['lead_score_contact_pages'] : array(),
        'half_life' => $number('lead_score_half_life', 1, 365, lang('Points halve in 1 to 365 days.')),
        'keep_months' => $number('lead_score_keep_months', 1, 36, lang('Keep the journey 1 to 36 months.')),
    );

    return array('settings' => pg_lead_score_settings($data), 'errors' => $errors);
}

/**
 * The text config_text 'lead_score' keeps for the settings: '' when they
 * are the defaults, so a later change of a default reaches the site. Pure.
 *
 * @param array $settings pg_lead_score_settings()'s shape
 * @return string
 */
function pg_lead_score_settings_text(array $settings)
{
    $settings = pg_lead_score_settings($settings);

    return ($settings === pg_lead_score_settings(array())) ? '' : (string) json_encode($settings);
}

/**
 * The site's interest score settings, read once per request.
 *
 * @return array pg_lead_score_settings()
 */
function pg_lead_settings()
{
    static $settings = null;

    if ($settings === null) {
        $settings = pg_lead_score_settings(function_exists('pg_config_text') ? pg_config_text('lead_score') : '');
    }

    return $settings;
}

/**
 * The journey's keep period in months, from the settings.
 *
 * @return int
 */
function pg_lead_keep_months()
{
    $settings = pg_lead_settings();

    return $settings['keep_months'];
}

/**
 * Works out one contact's scores and interests and writes its
 * contact_scores row; a contact with panel work or no visit tied any more
 * loses its row.
 *
 * @param int   $contact_id
 * @param array $settings pg_lead_settings()
 * @param int   $now
 * @return bool whether a row was written
 */
function pg_lead_score_contact($contact_id, array $settings, $now)
{
    $contact_id = (int) $contact_id;
    $journey = pg_lead_journey_contact_is_staff($contact_id) ? null : pg_lead_journey_read($contact_id, array('forms' => true, 'orders' => false));
    $contact = $journey ? db_item("SELECT email_address, company, title FROM contacts WHERE id = '" . $contact_id . "'") : null;

    if (!$journey || !is_array($contact)) {
        db("DELETE FROM contact_scores WHERE contact_id = '" . $contact_id . "'");
        return false;
    }

    $rows = $journey['rows'];
    $names = array();
    $last_visit = 0;
    $touched = 0;

    foreach ($rows as $row) {
        $touched = max($touched, (int) $row['at']);

        if ($row['kind'] === 'visit') {
            $last_visit = max($last_visit, (int) $row['at']);

            foreach ($row['pages'] as $page) {
                $names[(string) $page[0]] = true;
            }
        }
    }

    $chosen = array('pricing_pages' => array(), 'contact_pages' => array());
    $ids = array_merge($settings['pricing_pages'], $settings['contact_pages']);
    $by_id = $ids ? array_column((array) db_items("SELECT page_id, page_name FROM page WHERE page_id IN (" . implode(',', array_map('intval', $ids)) . ")"), 'page_name', 'page_id') : array();

    foreach ($chosen as $key => $list) {
        foreach ($settings[$key] as $id) {
            if (isset($by_id[$id])) {
                $chosen[$key][] = (string) $by_id[$id];
            }
        }
    }

    $folders = array();

    if ($names) {
        $quoted = array();

        foreach (array_keys($names) as $name) {
            $quoted[] = "'" . e($name) . "'";
        }

        foreach ((array) db_items("SELECT p.page_name, f.folder_id, f.folder_name FROM page p
            INNER JOIN folder f ON f.folder_id = p.page_folder AND f.folder_parent <> 0
            WHERE p.page_name IN (" . implode(',', $quoted) . ")") as $folder) {
            $folders[(string) $folder['page_name']] = array((int) $folder['folder_id'], (string) $folder['folder_name']);
        }
    }

    $interest = pg_lead_interest_score(pg_lead_score_events($rows, $chosen['pricing_pages'], $chosen['contact_pages']), $now, $settings['weights'], $settings['half_life']);
    $fit = pg_lead_fit_score(array(
        'email' => $contact['email_address'],
        'company' => $contact['company'],
        'title' => $contact['title'],
        'groups' => (int) db_value("SELECT COUNT(*) FROM contacts_contact_groups_xref WHERE contact_id = '" . $contact_id . "'"),
    ));
    $topics = pg_lead_interest_topics($rows, $folders, $now, $settings['half_life'], pg_lead_interest_categories(pg_lead_interest_items($rows)));
    $hot = ($interest['score'] >= $settings['threshold']) ? 1 : 0;
    $old = db_item("SELECT hot, last_visit_at, returned_at FROM contact_scores WHERE contact_id = '" . $contact_id . "'");

    // hot_at is assigned before hot, so it still reads the row's old flag.
    $written = db_q(
        "INSERT INTO contact_scores (contact_id, interest, fit, reasons, topics, points, hot, hot_at, last_visit_at, touched_at, scored_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE interest = VALUES(interest), fit = VALUES(fit), reasons = VALUES(reasons), topics = VALUES(topics),
                points = VALUES(points), hot_at = IF(VALUES(hot) = 1 AND hot = 0, VALUES(hot_at), hot_at), hot = VALUES(hot),
                last_visit_at = VALUES(last_visit_at), touched_at = VALUES(touched_at), scored_at = VALUES(scored_at)",
        array(
            (string) $contact_id, (string) $interest['score'], (string) $fit['score'],
            json_encode(array('interest' => $interest['reasons'], 'fit' => $fit['reasons'])),
            json_encode($topics), json_encode(array('at' => (int) $now, 'kinds' => $interest['points'])),
            (string) $hot, (string) ($hot ? $now : 0), (string) $last_visit, (string) $touched, (string) $now,
        )
    ) !== false;

    // customer.hot and customer.returned (includes/fn/lead_tell.php).
    if ($written) {
        pg_lead_tell(pg_lead_tell_moves($old, $hot, $last_visit, $now), array(
            'contact_id' => $contact_id, 'interest' => $interest['score'], 'fit' => $fit['score'],
            'reasons' => $interest['reasons'], 'topics' => $topics, 'last_visit_at' => $last_visit,
        ), $settings['threshold'], $now);
    }

    return $written;
}

/**
 * The contacts a scoring pass works out, at most PG_LEAD_SCORE_BATCH: a
 * visit started since a day before the last pass, a journey row since the
 * last pass, a score with interest not worked out for a day (it fades), a
 * score whose newest visit is past the keep period not worked out for a
 * day (its visits are trimmed, so the row goes, as the journey's own rows
 * do, even at interest 0), or a row marked for work (scored_at 0: a contact
 * merge); minus those this pass already scored. Pure.
 *
 * @param int $since      the last finished pass's start
 * @param int $started    this pass's start
 * @param int $now
 * @param int $keep_start pg_lead_keep_start()'s answer
 * @return string SQL
 */
function pg_lead_score_candidates_sql($since, $started, $now, $keep_start)
{
    return "SELECT c.contact_id FROM (
            SELECT contact_id FROM lead_visits WHERE started_at >= '" . max(0, (int) $since - 86400) . "' AND contact_id > 0
            UNION SELECT contact_id FROM contact_journey WHERE at >= '" . (int) $since . "' AND contact_id > 0
            UNION SELECT contact_id FROM contact_scores WHERE ((interest > 0 OR last_visit_at < '" . (int) $keep_start . "') AND scored_at < '" . ((int) $now - 86400) . "') OR scored_at = 0
        ) c
        LEFT JOIN contact_scores s ON s.contact_id = c.contact_id
        WHERE s.contact_id IS NULL OR s.scored_at < '" . (int) $started . "'
        ORDER BY c.contact_id LIMIT " . PG_LEAD_SCORE_BATCH;
}

/**
 * What a contact merge does to the merged contacts' score rows: the
 * strongest row (hot first, then the highest interest, the survivor's own
 * on a tie) is kept for the contact that stays and marked for the next pass
 * (scored_at 0); the others go. The kept row takes the newest visit and
 * the last return of all of them, and stays hot when any was, so a person
 * already told hot is not told again. Pure.
 *
 * @param array[] $rows    contact_scores rows of the survivor and the merged
 *                         contacts: contact_id, interest, hot, hot_at,
 *                         returned_at, last_visit_at
 * @param int     $keep_id the contact that stays
 * @return array|null from (the contact id whose row is kept), drop (ids),
 *                    hot, hot_at, returned_at, last_visit_at; null for no row
 */
function pg_lead_score_merge_plan(array $rows, $keep_id)
{
    if (!$rows) {
        return null;
    }

    usort($rows, function ($a, $b) use ($keep_id) {
        $order = array((int) $b['hot'], (int) $b['interest'], (int) ((int) $b['contact_id'] === (int) $keep_id), -(int) $b['contact_id']);
        $other = array((int) $a['hot'], (int) $a['interest'], (int) ((int) $a['contact_id'] === (int) $keep_id), -(int) $a['contact_id']);

        return $order <=> $other;
    });

    $hot = 0;
    $hot_at = (int) $rows[0]['hot_at'];
    $returned_at = 0;
    $last_visit_at = 0;
    $drop = array();

    foreach ($rows as $index => $row) {
        if ((int) $row['hot'] === 1) {
            $hot_at = $hot ? max($hot_at, (int) $row['hot_at']) : (int) $row['hot_at'];
            $hot = 1;
        }

        $returned_at = max($returned_at, (int) $row['returned_at']);
        $last_visit_at = max($last_visit_at, (int) $row['last_visit_at']);

        if ($index > 0) {
            $drop[] = (int) $row['contact_id'];
        }
    }

    sort($drop);

    return array(
        'from' => (int) $rows[0]['contact_id'], 'drop' => $drop, 'hot' => $hot, 'hot_at' => $hot_at,
        'returned_at' => $returned_at, 'last_visit_at' => $last_visit_at,
    );
}

/**
 * Leaves the contact that stays after a merge one score row, marked for the
 * next pass (pg_lead_score_merge_plan()); the merged contacts' rows go.
 *
 * @param int[] $merged_ids
 * @param int   $keep_id
 * @return bool false when a query failed
 */
function pg_lead_score_merge(array $merged_ids, $keep_id)
{
    $ids = array_values(array_unique(array_merge(array_map('intval', $merged_ids), array((int) $keep_id))));
    $rows = db_items("SELECT contact_id, interest, hot, hot_at, returned_at, last_visit_at FROM contact_scores WHERE contact_id IN (" . implode(',', $ids) . ")");

    if ($rows === false) {
        return false;
    }

    $plan = pg_lead_score_merge_plan((array) $rows, $keep_id);

    if ($plan === null) {
        return true;
    }

    if ($plan['drop'] && (db_q("DELETE FROM contact_scores WHERE contact_id IN (" . implode(',', $plan['drop']) . ")") === false)) {
        return false;
    }

    return db_q(
        "UPDATE contact_scores SET contact_id = ?, hot = ?, hot_at = ?, returned_at = ?, last_visit_at = ?, scored_at = 0 WHERE contact_id = ?",
        array((string) (int) $keep_id, (string) $plan['hot'], (string) $plan['hot_at'], (string) $plan['returned_at'], (string) $plan['last_visit_at'], (string) $plan['from'])
    ) !== false;
}

/**
 * The general job's scoring pass (J8): contacts with a visit started since
 * a day before the last pass (a visit still going gets later pages), a
 * journey row since the last pass, or a score not worked out for a day
 * (points fade), at most PG_LEAD_SCORE_BATCH a run, going on next tick
 * until none is left. Runs every PG_LEAD_SCORE_EVERY seconds; of two
 * overlapping runs only one claims the pass record (config_text
 * 'lead_score_pass').
 *
 * @param int       $now
 * @param int|float $budget seconds this run may take
 * @return int contacts scored
 */
function pg_lead_score_run($now, $budget = PG_LEAD_SCORE_TICK_SECONDS)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready() || !pg_schema_has('contact_scores', 'scored_at') || !pg_schema_has('config_text')) {
        return 0;
    }

    if ($budget <= 0) {
        return 0;
    }

    $now = (int) $now;
    $raw = (string) db_value("SELECT value FROM config_text WHERE name = 'lead_score_pass'");
    $record = json_decode($raw, true);
    $since = (int) ($record['since'] ?? 0);
    $started = (int) ($record['started'] ?? 0);

    if (($started === 0) && ($since > $now - PG_LEAD_SCORE_EVERY)) {
        return 0;
    }

    $started = $started ?: $now;

    if (!pg_config_text_claim('lead_score_pass', $raw, json_encode(array('since' => $since, 'started' => $started, 'run' => $now)))) {
        return 0;
    }

    $settings = pg_lead_settings();
    $ids = (array) db_values(pg_lead_score_candidates_sql($since, $started, $now, pg_lead_keep_start($now, $settings['keep_months'])));
    $clock = microtime(true);
    $scored = 0;
    $done = 0;

    foreach ($ids as $contact_id) {
        if (microtime(true) - $clock > $budget) {
            break;
        }

        $scored += pg_lead_score_contact((int) $contact_id, $settings, $started) ? 1 : 0;
        $done++;
    }

    $finished = ($done === count($ids)) && (count($ids) < PG_LEAD_SCORE_BATCH);
    pg_config_text_save('lead_score_pass', json_encode($finished ? array('since' => $started, 'started' => 0) : array('since' => $since, 'started' => $started)));

    // The staff's "Hot contacts" alert, once a day (includes/fn/lead_tell.php).
    if ($finished) {
        pg_lead_alert_send($now);
    }

    return $scored;
}

/**
 * The contact screen's "Interest" card: the interest and fit figures of
 * 100, "Hot" from the site's threshold, the reasons and up to five
 * interests; '' before the first pass scored the contact, for a contact with
 * panel work or before the journey's tables are there.
 *
 * @param int $contact_id
 * @return string HTML
 */
function pg_lead_score_contact_card($contact_id)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready() || !pg_schema_has('contact_scores', 'scored_at') || pg_lead_journey_contact_is_staff($contact_id)) {
        return '';
    }

    $row = db_item("SELECT interest, fit, reasons, topics FROM contact_scores WHERE contact_id = '" . (int) $contact_id . "'");

    if (!is_array($row)) {
        return '';
    }

    $settings = pg_lead_settings();
    $reasons = json_decode((string) $row['reasons'], true);
    $topics = json_decode((string) $row['topics'], true);
    $lines = '';
    $pills = '';

    $all = array_merge(
        (is_array($reasons) && isset($reasons['interest']) && is_array($reasons['interest'])) ? $reasons['interest'] : array(),
        (is_array($reasons) && isset($reasons['fit']) && is_array($reasons['fit'])) ? $reasons['fit'] : array()
    );

    usort($all, function ($a, $b) {
        return ((float) ($b['points'] ?? 0)) <=> ((float) ($a['points'] ?? 0));
    });

    foreach (array_slice($all, 0, 3) as $reason) {
        $text = pg_lead_score_reason_text((string) ($reason['kind'] ?? ''));

        if ($text !== '') {
            $lines .= '<div class="pg-gather-line"><span>' . h($text) . '</span></div>';
        }
    }

    foreach (array_slice(is_array($topics) ? $topics : array(), 0, PG_LEAD_TOPICS_MAX) as $topic) {
        if (is_array($topic) && ((string) ($topic['name'] ?? '') !== '')) {
            $pills .= '<span class="pg-pill pg-tone-violet">' . h((string) $topic['name']) . '</span>';
        }
    }

    $figure = function ($label, $value) {
        return '<div class="pg-card pg-figure"><span class="pg-figure-label">' . h($label) . '</span><span class="pg-figure-value">' . h(pg_format_number((int) $value, 0)) . ' <small>/ 100</small></span></div>';
    };

    return '
                                <section class="pg-card p-5 mb-7" id="lead_interest" aria-labelledby="lead_interest_title">
                                    <div class="d-flex align-items-center gap-3 mb-1"><h2 class="pg-title-section mb-0" id="lead_interest_title">' . h(lang('Interest')) . '</h2>'
                                    . (((int) $row['interest'] >= $settings['threshold']) ? '<span class="pg-pill pg-tone-rose">' . h(lang('Hot')) . '</span>' : '') . '</div>
                                    <p class="pg-card-sub mb-5">' . h(lang('Worked out from the visits of a browser that accepted marketing cookies, and from the contact\'s fields.')) . '</p>
                                    <div class="pg-figures pg-figures-sm mb-5">' . $figure(lang('Interest'), $row['interest']) . $figure(lang('Fit'), $row['fit']) . '</div>'
                                    . (($lines !== '') ? '<h3 class="pg-title-status mb-2">' . h(lang('Why')) . '</h3><div class="pg-gather mb-5">' . $lines . '</div>' : '')
                                    . (($pills !== '') ? '<h3 class="pg-title-status mb-2">' . h(lang('Interested in')) . '</h3><div class="d-flex flex-wrap gap-2">' . $pills . '</div>' : '') . '
                                </section>';
}
