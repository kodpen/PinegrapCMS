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
 * A contact's interests: the folders of the pages its visits saw, each
 * weighted by the seconds spent there (at least PG_LEAD_TOPIC_MIN_SECONDS a
 * page) and faded by the half-life from the visit's start; the top five,
 * heaviest first (ties by name). Pages in no folder but the site's root
 * count for nothing. Pure.
 *
 * @param array[]   $rows    pg_lead_journey_read()'s rows; only visits count
 * @param array     $folders page name => array(id, name)
 * @param int       $now
 * @param int|float $half_life_days
 * @return array[] id, name, weight
 */
function pg_lead_interest_topics(array $rows, array $folders, $now, $half_life_days = PG_LEAD_HALF_LIFE_DAYS)
{
    $topics = array();

    foreach ($rows as $row) {
        if ((string) ($row['kind'] ?? '') !== 'visit') {
            continue;
        }

        foreach ((array) ($row['pages'] ?? array()) as $page) {
            $name = is_array($page) ? (string) ($page[0] ?? '') : (string) $page;

            if (!isset($folders[$name])) {
                continue;
            }

            list($id, $folder) = $folders[$name];
            $seconds = max(PG_LEAD_TOPIC_MIN_SECONDS, is_array($page) ? (int) ($page[1] ?? 0) : 0);
            $weight = pg_lead_score_decay($seconds, (int) $now - (int) ($row['at'] ?? 0), $half_life_days);

            if (!isset($topics[$id])) {
                $topics[$id] = array('id' => (int) $id, 'name' => (string) $folder, 'weight' => 0.0);
            }

            $topics[$id]['weight'] += $weight;
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
    $topics = pg_lead_interest_topics($rows, $folders, $now, $settings['half_life']);
    $hot = ($interest['score'] >= $settings['threshold']) ? 1 : 0;

    // hot_at is assigned before hot, so it still reads the row's old flag.
    return db_q(
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

    $ids = (array) db_values("SELECT c.contact_id FROM (
            SELECT contact_id FROM lead_visits WHERE started_at >= '" . max(0, $since - 86400) . "' AND contact_id > 0
            UNION SELECT contact_id FROM contact_journey WHERE at >= '" . $since . "' AND contact_id > 0
            UNION SELECT contact_id FROM contact_scores WHERE interest > 0 AND scored_at < '" . ($now - 86400) . "'
        ) c
        LEFT JOIN contact_scores s ON s.contact_id = c.contact_id
        WHERE s.contact_id IS NULL OR s.scored_at < '" . $started . "'
        ORDER BY c.contact_id LIMIT " . PG_LEAD_SCORE_BATCH);

    $settings = pg_lead_settings();
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
