<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The satisfaction survey results: the months a report covers, the survey
 * it reads (one survey, or every survey of a kind, since an NPS and a CSAT
 * do not add up), the grouping (what it follows, service, staff member,
 * course, event, form, month), the counted cells folded into rows with the
 * kind's score and the answer rate, the frequent words of the comments with
 * no AI (counted, stop words per language), the workbook rows, and the
 * contact card's scores. A report counts the surveys sent in its months;
 * a staff member's figures need 5 answers and a manager.
 *
 * Loaded by messages_survey_results.php, messages_surveys.php (the
 * Surveys / Results segments) and edit_contact.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

/** A staff member's figures show from this many answers on. */
define('PG_FEEDBACK_STAFF_MIN', 5);

/** Frequent words shown beside the comments. */
define('PG_FEEDBACK_WORDS_MAX', 20);

/** Comments read for the frequent words; the newest are listed. */
define('PG_FEEDBACK_COMMENTS_READ', 2000);
define('PG_FEEDBACK_COMMENTS_SHOWN', 50);

/**
 * The groupings of the results table, in the order of the choice. English;
 * callers translate. The staff grouping only for a manager and up.
 *
 * @param bool $may_staff
 * @return array key => label
 */
function pg_feedback_report_groupings($may_staff)
{
    $all = array(
        'touchpoint' => 'What it follows',
        'service'    => 'Service',
        'staff'      => 'Staff member',
        'course'     => 'Course',
        'event'      => 'Event',
        'form'       => 'Form',
        'month'      => 'Month',
    );

    if (!$may_staff) {
        unset($all['staff']);
    }

    return $all;
}

/**
 * The month a report shows ('Y-m'): the given one when it is a real month
 * not after this one, else this month. Pure.
 *
 * @param mixed $value
 * @param int   $now
 * @return string
 */
function pg_feedback_report_month($value, $now)
{
    $current = date('Y-m', (int) $now);

    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})$/', $value, $parts) || ((int) $parts[2] < 1) || ((int) $parts[2] > 12)) {
        return $current;
    }

    return (strcmp($value, $current) > 0) ? $current : $value;
}

/**
 * The month $delta months from a month. Pure.
 *
 * @param string $month 'Y-m'
 * @param int    $delta
 * @return string
 */
function pg_feedback_report_month_step($month, $delta)
{
    return date('Y-m', mktime(12, 0, 0, (int) substr($month, 5, 2) + (int) $delta, 1, (int) substr($month, 0, 4)));
}

/**
 * The months a report covers, oldest first: the month, or the twelve that
 * end with it. Pure.
 *
 * @param string $month
 * @param bool   $year
 * @return string[]
 */
function pg_feedback_report_months($month, $year)
{
    $out = array();

    for ($back = $year ? 11 : 0; $back >= 0; $back--) {
        $out[] = pg_feedback_report_month_step($month, -$back);
    }

    return $out;
}

/**
 * The start of a month in the site's zone.
 *
 * @param string $month 'Y-m'
 * @return int
 */
function pg_feedback_report_month_start($month)
{
    return mktime(0, 0, 0, (int) substr($month, 5, 2), 1, (int) substr($month, 0, 4));
}

/**
 * From the first month's start to the start of the month after the last.
 * Pure.
 *
 * @param string[] $months
 * @return int[] from, to (to not included)
 */
function pg_feedback_report_bounds(array $months)
{
    return array(pg_feedback_report_month_start($months[0]), pg_feedback_report_month_start(pg_feedback_report_month_step(end($months), 1)));
}

/**
 * The survey choice of the tools row: a survey's id, or 'k<kind>' for every
 * survey of a kind. An unknown value is every survey of $default_kind.
 * Pure.
 *
 * @param mixed $value
 * @param array $surveys      id => kind
 * @param int   $default_kind
 * @return array kind, survey (0 for all of the kind), value (the choice)
 */
function pg_feedback_report_scope($value, array $surveys, $default_kind)
{
    $value = is_scalar($value) ? (string) $value : '';

    if (preg_match('/^[1-9][0-9]*$/', $value) && isset($surveys[(int) $value])) {
        return array('kind' => (int) $surveys[(int) $value], 'survey' => (int) $value, 'value' => $value);
    }

    $kind = preg_match('/^k([123])$/', $value, $match) ? (int) $match[1] : (int) $default_kind;

    return array('kind' => $kind, 'survey' => 0, 'value' => 'k' . $kind);
}

/**
 * The SQL of a grouping on feedback_responses r joined to feedback_surveys
 * s: the key's expression and the condition it adds. A month is the send
 * time's month in the site's zone, as a CASE over the months' starts, since
 * the database's zone may be another one. Pure.
 *
 * @param string   $grouping a key of pg_feedback_report_groupings()
 * @param string[] $months   the report's months, oldest first
 * @return array key, where (' AND …' or '')
 */
function pg_feedback_report_group_sql($grouping, array $months)
{
    $types = array('service' => 'booking', 'course' => 'course', 'event' => 'ticket', 'form' => 'form');

    if (isset($types[$grouping])) {
        return array('key' => 'r.subject_id', 'where' => " AND r.record_type = '" . $types[$grouping] . "'");
    }

    if ($grouping === 'staff') {
        return array('key' => 'r.staff_user_id', 'where' => ' AND r.staff_user_id <> 0');
    }

    if ($grouping === 'month') {
        $case = 'CASE';
        $last = count($months) - 1;

        foreach ($months as $index => $month) {
            if ($index === $last) {
                break;
            }

            $case .= ' WHEN r.sent_at < ' . pg_feedback_report_month_start($months[$index + 1]) . " THEN '" . $month . "'";
        }

        return array('key' => ($last > 0) ? $case . " ELSE '" . $months[$last] . "' END" : "'" . $months[0] . "'", 'where' => '');
    }

    return array('key' => 's.touchpoint', 'where' => '');
}

/**
 * Counted cells folded into rows: per key the surveys sent, the answers,
 * the answer rate, the low answers and the kind's sum
 * (pg_feedback_sum()); and the same for all of them. Pure.
 *
 * @param int   $kind
 * @param array $cells each k (key), s (score), a (1 answered), n (count)
 * @return array rows (key => row, in the order met), total
 */
function pg_feedback_report_fold($kind, array $cells)
{
    $scores = array();
    $rows = array();
    $blank = array('sent' => 0, 'answered' => 0, 'low' => 0);
    $total = $blank;
    $all = array();

    foreach ($cells as $cell) {
        $key = (string) $cell['k'];
        $count = max(0, (int) $cell['n']);

        if (!isset($rows[$key])) {
            $rows[$key] = $blank;
            $scores[$key] = array();
        }

        $rows[$key]['sent'] += $count;
        $total['sent'] += $count;

        if (empty($cell['a']) || !pg_feedback_score_valid($kind, $cell['s'])) {
            continue;
        }

        $low = pg_feedback_is_low($kind, (int) $cell['s']) ? $count : 0;
        $rows[$key]['answered'] += $count;
        $rows[$key]['low'] += $low;
        $total['answered'] += $count;
        $total['low'] += $low;
        $fill = array_fill(0, $count, (int) $cell['s']);
        $scores[$key] = array_merge($scores[$key], $fill);
        $all = array_merge($all, $fill);
    }

    foreach ($rows as $key => $row) {
        $rows[$key]['rate'] = pg_feedback_percent($row['answered'], $row['sent']);
        $rows[$key]['sum'] = pg_feedback_sum($kind, $scores[$key]);
    }

    $total['rate'] = pg_feedback_percent($total['answered'], $total['sent']);
    $total['sum'] = pg_feedback_sum($kind, $all);

    return array('rows' => $rows, 'total' => $total);
}

/**
 * The score of a sum as shown: an NPS whole and signed, a CSAT or effort
 * score as its mean with one decimal; '' with no answers. Pure but for the
 * site's number separators.
 *
 * @param int   $kind
 * @param array $sum pg_feedback_sum()
 * @return string
 */
function pg_feedback_report_score_text($kind, array $sum)
{
    if ((int) $kind === PG_FEEDBACK_NPS) {
        if (!isset($sum['score'])) {
            return '';
        }

        return (($sum['score'] > 0) ? '+' : '') . (string) $sum['score'];
    }

    return isset($sum['average']) ? pg_format_number($sum['average'], 1) : '';
}

/**
 * Whether a row's figures are held back: a staff member with fewer than
 * PG_FEEDBACK_STAFF_MIN answers. Pure.
 *
 * @param string $grouping
 * @param array  $row
 * @return bool
 */
function pg_feedback_report_few($grouping, array $row)
{
    return ($grouping === 'staff') && ((int) $row['answered'] < PG_FEEDBACK_STAFF_MIN);
}

/**
 * The bands with their filter words and names. English; callers translate.
 *
 * @return array word => array(band, name, tone)
 */
function pg_feedback_band_names()
{
    return array(
        'low'    => array(PG_FEEDBACK_BAND_LOW, 'Low', 'rose'),
        'middle' => array(PG_FEEDBACK_BAND_MIDDLE, 'Middle', 'amber'),
        'high'   => array(PG_FEEDBACK_BAND_HIGH, 'High', 'teal'),
    );
}

/**
 * A band from its filter word, 0 (every band) for anything else. Pure.
 *
 * @param mixed $value
 * @return int
 */
function pg_feedback_band_key($value)
{
    $bands = pg_feedback_band_names();

    return (is_string($value) && isset($bands[$value])) ? $bands[$value][0] : 0;
}

/**
 * A score as a pill toned by its band.
 *
 * @param int $score
 * @param int $band
 * @return string
 */
function pg_feedback_score_pill($score, $band)
{
    $tones = array(PG_FEEDBACK_BAND_LOW => 'rose', PG_FEEDBACK_BAND_MIDDLE => 'amber', PG_FEEDBACK_BAND_HIGH => 'teal');

    return '<span class="pg-pill pg-tone-' . ($tones[(int) $band] ?? 'gray') . '">' . (int) $score . '</span>';
}

/**
 * Words a comment is made of but that say nothing alone, per language;
 * English for a language with no list.
 *
 * @param string $lang
 * @return array word => true
 */
function pg_feedback_stop_words($lang)
{
    $lists = array(
        'en' => 'about after again all also and any are because been before being but can could did does doing down during each even ever every few for from had has have having her here hers him his how into its just like more most much not now off once only other our out over own same she should some such than that the their them then there these they this those through too under until very was were what when where which while who whom why will with would you your yours',
        'tr' => 'acaba ama ancak artık aslında bana bazı belki ben benim beni bile bir biraz birkaç biz bize bizim böyle bu buna bunu bunun burada çok çünkü da daha de defa diye en gibi göre hem hep hepsi her hiç için ile ise işte kadar ki kim mi mu mü mı nasıl ne neden nerede niye o olan olarak oldu olduğu olsun on ona onlar onu onun öyle sadece sen siz şey şu şimdi tüm ve veya ya yani yine zaten',
    );
    $list = $lists[$lang] ?? $lists['en'];

    return array_fill_keys(explode(' ', $list), true);
}

/**
 * A text in lower case the way its language writes it: Turkish keeps the
 * dotted and dotless i apart.
 *
 * @param string $text
 * @param string $lang
 * @return string
 */
function pg_feedback_lower($text, $lang)
{
    if ($lang === 'tr') {
        $text = str_replace(array('İ', 'I'), array('i', 'ı'), $text);
    }

    return mb_strtolower($text, 'UTF-8');
}

/**
 * The frequent words of the comments: each word counted once a comment,
 * words of fewer than three letters, numbers and the language's stop words
 * left out; the most frequent first, a tie by the word. No AI. Pure.
 *
 * @param array  $comments
 * @param string $lang     the comments' language (the site's first)
 * @param int    $max
 * @return array word => comments
 */
function pg_feedback_words(array $comments, $lang, $max)
{
    $stop = pg_feedback_stop_words($lang);
    $counts = array();

    foreach ($comments as $comment) {
        if (!is_string($comment) || ($comment === '')) {
            continue;
        }

        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', pg_feedback_lower($comment, $lang), -1, PREG_SPLIT_NO_EMPTY);

        foreach (array_unique((array) $words) as $word) {
            if ((mb_strlen($word, 'UTF-8') < 3) || isset($stop[$word]) || preg_match('/^\p{N}+$/u', $word)) {
                continue;
            }

            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
    }

    $words = array_keys($counts);
    usort($words, function ($a, $b) use ($counts) {
        return ($counts[$b] - $counts[$a]) ?: strcmp($a, $b);
    });
    $out = array();

    foreach (array_slice($words, 0, max(0, (int) $max)) as $word) {
        $out[(string) $word] = $counts[$word];
    }

    return $out;
}

/**
 * A heading and lines as the typed rows of pg_xlsx_write_book(): row
 * number => cells, a number a numeric cell, null a gap, anything else text
 * (never a formula). Pure.
 *
 * @param string[] $head
 * @param array    $lines
 * @return array
 */
function pg_feedback_report_sheet(array $head, array $lines)
{
    $cell = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return array('t' => 'n', 'v' => (string) $value);
        }

        return array('t' => 's', 'v' => (string) $value);
    };
    $rows = array(1 => array_map($cell, array_values($head)));

    foreach (array_values($lines) as $index => $line) {
        $rows[$index + 2] = array_map($cell, array_values($line));
    }

    return $rows;
}

/**
 * The panel screen an answer's record opens on, '' for none. Pure.
 *
 * @param string $type feedback_responses.record_type
 * @param int    $id
 * @return string
 */
function pg_feedback_report_record_url($type, $id)
{
    $screens = array('booking' => 'edit_booking.php', 'order' => 'view_order.php', 'form' => 'edit_submitted_form.php');

    return (isset($screens[$type]) && ((int) $id > 0)) ? $screens[$type] . '?id=' . (int) $id : '';
}

/**
 * Whether a person sees the results: a manager and up, or a user with the
 * contacts right.
 *
 * @param array $user validate_user()
 * @return bool
 */
function pg_feedback_results_may($user)
{
    return ((int) $user['role'] <= 2) || (!empty($user['manage_contacts']) && validate_contacts_access($user, true));
}

/**
 * The Surveys / Results link segments under the tab strip; Results alone
 * below manager, so then none.
 *
 * @param string $current 'surveys' or 'results'
 * @param array  $user
 * @return string
 */
function pg_feedback_segments_html($current, $user)
{
    if ((int) $user['role'] > 2) {
        return '';
    }

    $items = '';

    foreach (array('surveys' => array(lang('Surveys'), 'messages_surveys.php'), 'results' => array(lang('Results'), 'messages_survey_results.php')) as $key => $segment) {
        $active = ($key === $current);
        $items .= '<a class="pg-seg-item' . ($active ? ' is-active' : '') . '" href="' . h($segment[1]) . '"' . ($active ? ' aria-current="page"' : '') . '>' . h($segment[0]) . '</a>';
    }

    return '<nav class="mb-5" aria-label="' . h(lang('Surveys')) . '"><div class="pg-seg">' . $items . '</div></nav>';
}

/**
 * Every survey, archived ones too (their answers stay in the results),
 * newest first.
 *
 * @return array id => id, name, kind, touchpoint, archived_at
 */
function pg_feedback_report_surveys()
{
    $out = array();

    foreach ((array) db_items("SELECT id, name, kind, touchpoint, archived_at FROM feedback_surveys ORDER BY archived_at <> 0, id DESC") as $row) {
        $out[(int) $row['id']] = array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'kind' => (int) $row['kind'], 'touchpoint' => (string) $row['touchpoint'], 'archived_at' => (int) $row['archived_at']);
    }

    return $out;
}

/**
 * The WHERE of a report's sends: sent in its months (answered, waiting or
 * no answer), of its survey or kind and what it follows.
 *
 * @param array  $scope      pg_feedback_report_scope()
 * @param string $touchpoint '' for any
 * @param int[]  $bounds     pg_feedback_report_bounds()
 * @return array sql, params
 */
function pg_feedback_report_where(array $scope, $touchpoint, array $bounds)
{
    $sql = 'r.status IN (' . PG_FEEDBACK_SENT . ', ' . PG_FEEDBACK_ANSWERED . ', ' . PG_FEEDBACK_NO_ANSWER . ') AND r.sent_at >= ? AND r.sent_at < ? AND s.kind = ?';
    $params = array($bounds[0], $bounds[1], (int) $scope['kind']);

    if ($scope['survey'] > 0) {
        $sql .= ' AND r.survey_id = ?';
        $params[] = (int) $scope['survey'];
    }

    if ($touchpoint !== '') {
        $sql .= ' AND s.touchpoint = ?';
        $params[] = (string) $touchpoint;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A report's counted cells for a grouping, and its open follow-ups.
 *
 * @param array    $scope
 * @param string   $touchpoint
 * @param string   $grouping
 * @param string[] $months
 * @return array cells (pg_feedback_report_fold()), open
 */
function pg_feedback_report_read(array $scope, $touchpoint, $grouping, array $months)
{
    $where = pg_feedback_report_where($scope, $touchpoint, pg_feedback_report_bounds($months));
    $group = pg_feedback_report_group_sql($grouping, $months);
    $from = ' FROM feedback_responses r INNER JOIN feedback_surveys s ON s.id = r.survey_id WHERE ' . $where['sql'];
    $cells = array();
    $result = db_q('SELECT ' . $group['key'] . ' AS k, r.score AS s, (r.status = ' . PG_FEEDBACK_ANSWERED . ') AS a, COUNT(*) AS n' . $from . $group['where'] . ' GROUP BY k, s, a', $where['params']);

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $cells[] = $row;
    }

    $open = db_q('SELECT COUNT(*)' . $from . ' AND r.band = ' . PG_FEEDBACK_BAND_LOW . ' AND r.task_id <> 0 AND r.outcome = 0', $where['params']);

    return array('cells' => $cells, 'open' => $open ? (int) mysqli_fetch_row($open)[0] : 0);
}

/**
 * The names of a grouping's keys.
 *
 * @param string $grouping
 * @param array  $keys
 * @return array key => name
 */
function pg_feedback_report_labels($grouping, array $keys)
{
    $out = array();

    if ($grouping === 'touchpoint') {
        foreach (pg_feedback_touchpoints() as $key => $point) {
            $out[$key] = lang($point['label']);
        }

        return $out;
    }

    if ($grouping === 'month') {
        foreach ($keys as $key) {
            $out[$key] = pg_customer_value_month_name($key);
        }

        return $out;
    }

    $sources = array(
        'service' => array('booking_services', "SELECT id, name FROM booking_services WHERE id IN (%s)"),
        'staff'   => array('user', "SELECT user_id AS id, user_username AS name FROM user WHERE user_id IN (%s)"),
        'course'  => array('course', "SELECT id, name FROM course WHERE id IN (%s)"),
        'event'   => array('calendar_events', "SELECT id, name FROM calendar_events WHERE id IN (%s)"),
        'form'    => array('custom_form_pages', "SELECT page_id AS id, form_name AS name FROM custom_form_pages WHERE page_id IN (%s)"),
    );
    $ids = array_filter(array_map('intval', $keys));

    if (!isset($sources[$grouping]) || !$ids || !pg_schema_has($sources[$grouping][0])) {
        return $out;
    }

    foreach ((array) db_items(sprintf($sources[$grouping][1], implode(',', $ids))) as $row) {
        $out[(string) $row['id']] = (string) $row['name'];
    }

    return $out;
}

/**
 * The comments of a report's answers, newest first, of a band or all.
 *
 * @param array  $scope
 * @param string $touchpoint
 * @param int[]  $bounds
 * @param int    $band 0 for all
 * @return array[] id, answered_at, survey, score, band, comment, record_type, record_id
 */
function pg_feedback_report_comments(array $scope, $touchpoint, array $bounds, $band)
{
    $where = pg_feedback_report_where($scope, $touchpoint, $bounds);
    $sql = "SELECT r.id, r.answered_at, s.name AS survey, r.score, r.band, r.comment, r.record_type, r.record_id
        FROM feedback_responses r INNER JOIN feedback_surveys s ON s.id = r.survey_id
        WHERE " . $where['sql'] . " AND r.status = " . PG_FEEDBACK_ANSWERED . " AND r.comment IS NOT NULL AND r.comment <> ''";

    if ($band > 0) {
        $sql .= ' AND r.band = ' . (int) $band;
    }

    $out = array();
    $result = db_q($sql . ' ORDER BY r.answered_at DESC LIMIT ' . PG_FEEDBACK_COMMENTS_READ, $where['params']);

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $out[] = $row;
    }

    return $out;
}

/**
 * A contact's answered surveys, newest first: by the contact, or by the
 * hash of its e-mail address or mobile number (an answer queued before the
 * contact existed).
 *
 * @param int    $contact_id
 * @param string $email
 * @param string $phone
 * @return array[] answered_at, survey, kind, score, band
 */
function pg_feedback_contact_scores($contact_id, $email, $phone)
{
    $hashes = pg_feedback_person_hashes(array_filter(array((string) $email, pg_sms_number((string) $phone))));
    $params = array((int) $contact_id);
    $match = 'r.contact_id = ?';

    if ($hashes) {
        $match = '(' . $match . ' OR r.person_hash IN (' . implode(', ', array_fill(0, count($hashes), '?')) . '))';
        $params = array_merge($params, $hashes);
    }

    return (array) db_items(
        "SELECT r.answered_at, s.name AS survey, s.kind, r.score, r.band
        FROM feedback_responses r INNER JOIN feedback_surveys s ON s.id = r.survey_id
        WHERE " . $match . " AND r.status = " . PG_FEEDBACK_ANSWERED . "
        ORDER BY r.answered_at DESC LIMIT 50", $params);
}

/**
 * The contact card's "Scores": a plain row per answer, date, survey and
 * the score's pill. '' before the update or with no answer.
 *
 * @param int   $contact_id
 * @param array $contact the contacts row
 * @return string
 */
function pg_feedback_contact_card($contact_id, array $contact)
{
    if (!pg_feedback_surveys_ready()) {
        return '';
    }

    $scores = pg_feedback_contact_scores($contact_id, (string) ($contact['email_address'] ?? ''), (string) ($contact['mobile_phone'] ?? ''));

    if (!$scores) {
        return '';
    }

    $kinds = pg_feedback_kind_names();
    $rows = '';

    foreach ($scores as $score) {
        $rows .= '<div class="pg-gather-line"><span>' . h(get_absolute_time(array('timestamp' => (int) $score['answered_at'], 'type' => 'date', 'format' => 'plain_text'))) . '</span>'
            . '<span class="text-break">' . h((string) $score['survey']) . ' · ' . h(lang($kinds[(int) $score['kind']] ?? 'NPS')) . '</span>'
            . '<span class="pg-gather-line-end">' . pg_feedback_score_pill((int) $score['score'], (int) $score['band']) . '</span></div>';
    }

    return '
                            <section class="pg-card p-5 mb-7" aria-labelledby="feedback_scores_title">
                                <h2 class="pg-title-section mb-1" id="feedback_scores_title">' . h(lang('Scores')) . '</h2>
                                <p class="pg-card-sub mb-5">' . h(lang('What this person answered to your surveys.')) . '</p>
                                <div class="pg-gather">' . $rows . '</div>
                            </section>';
}
