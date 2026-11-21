<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Satisfaction surveys as the panel keeps them: what a survey follows (its
 * touchpoints), the starting points of "Add survey", the defaults of each,
 * the texts per site language, the edit screen's input, the list's words,
 * the store of feedback_surveys and "Send me a test". Loaded by the Surveys
 * tab (messages_surveys.php) and its edit screen (edit_feedback_survey.php);
 * the score rules are in includes/fn/feedback.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** At most this many extra questions a survey. */
define('PG_FEEDBACK_EXTRA_MAX', 3);

/** "Send me a test" presses an hour per user. */
define('PG_FEEDBACK_TEST_HOURLY', 10);

/**
 * What a survey can follow, in the order of the starting points. Each:
 * label (the card's title), line (what starts it), icon, tone, kind (the
 * starting point's kind), delay (minutes), filter (its keys and defaults).
 * Labels are English; callers translate.
 *
 * @return array key => spec
 */
function pg_feedback_touchpoints()
{
    return array(
        'booking'  => array('label' => 'After a booking', 'line' => 'When staff mark a booking done.', 'icon' => 'bi-calendar-check', 'tone' => 'teal', 'kind' => PG_FEEDBACK_CSAT, 'delay' => 120, 'filter' => array('service' => 0)),
        'ticket'   => array('label' => 'After an event', 'line' => 'When a ticket is checked in at the door.', 'icon' => 'bi-ticket-perforated', 'tone' => 'violet', 'kind' => PG_FEEDBACK_CSAT, 'delay' => 1440, 'filter' => array('event' => 0)),
        'course'   => array('label' => 'After a course', 'line' => 'When a student finishes a course.', 'icon' => 'bi-mortarboard', 'tone' => 'amber', 'kind' => PG_FEEDBACK_NPS, 'delay' => 1440, 'filter' => array('course' => 0)),
        'form'     => array('label' => 'After a form', 'line' => 'When a form is sent, or when a choice on it changes.', 'icon' => 'bi-ui-checks', 'tone' => 'blue', 'kind' => PG_FEEDBACK_CSAT, 'delay' => 1440, 'filter' => array('form' => 0, 'field' => 0, 'value' => '')),
        'delivery' => array('label' => 'After a delivery', 'line' => 'When an order is delivered.', 'icon' => 'bi-truck', 'tone' => 'shop', 'kind' => PG_FEEDBACK_CSAT, 'delay' => 2880, 'filter' => array()),
        'mail'     => array('label' => 'After a mail conversation', 'line' => 'When a conversation in a mailbox is closed.', 'icon' => 'bi-envelope', 'tone' => 'blue', 'kind' => PG_FEEDBACK_EFFORT, 'delay' => 60, 'filter' => array('box' => 0)),
        'periodic' => array('label' => 'Every few months', 'line' => 'Asks recent customers how likely they are to recommend you.', 'icon' => 'bi-arrow-repeat', 'tone' => 'gray', 'kind' => PG_FEEDBACK_NPS, 'delay' => 0, 'filter' => array('every' => 6, 'active' => 12)),
    );
}

/**
 * The kinds with their names. English; callers translate.
 *
 * @return array kind => name
 */
function pg_feedback_kind_names()
{
    return array(PG_FEEDBACK_NPS => 'NPS', PG_FEEDBACK_CSAT => 'CSAT', PG_FEEDBACK_EFFORT => 'Effort');
}

/**
 * The channels a survey goes by. English; callers translate.
 *
 * @return array key => name
 */
function pg_feedback_channel_names()
{
    return array('email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp');
}

/**
 * The question and comment label a survey of a kind uses when the shop
 * wrote none, in the language lang() speaks.
 *
 * @param int $kind
 * @return array question, comment
 */
function pg_feedback_default_texts($kind)
{
    switch ((int) $kind) {
        case PG_FEEDBACK_CSAT:
            return array('question' => lang('How satisfied were you with our service?'), 'comment' => lang('What could we do better?'));
        case PG_FEEDBACK_EFFORT:
            return array('question' => lang('How easy was it to get what you needed?'), 'comment' => lang('What made it easy or hard?'));
    }

    return array('question' => lang('How likely are you to recommend us to a friend or colleague?'), 'comment' => lang('What is the main reason for your score?'));
}

/**
 * A new survey from a starting point, off. Null for an unknown one. Pure
 * but for lang().
 *
 * @param string $start a key of pg_feedback_touchpoints()
 * @return array|null the columns as pg_feedback_survey_decode() gives them
 */
function pg_feedback_survey_start($start)
{
    $points = pg_feedback_touchpoints();

    if (!isset($points[$start])) {
        return null;
    }

    $point = $points[$start];

    return array(
        'id' => 0, 'name' => lang($point['label']), 'kind' => $point['kind'], 'touchpoint' => (string) $start,
        'filter' => $point['filter'], 'delay_minutes' => $point['delay'], 'channel' => 'email',
        'purpose' => ($start === 'periodic') ? 1 : 0, 'fallback_user_id' => 0, 'escalation_user_id' => 0,
        'ws_channel_id' => 0, 'review_invite' => 1, 'texts' => pg_feedback_texts_decode(null), 'enabled' => 0, 'archived_at' => 0,
    );
}

/**
 * A stored row with its JSON read and its numbers as integers. Pure.
 *
 * @param array $row a feedback_surveys row
 * @return array
 */
function pg_feedback_survey_decode(array $row)
{
    $points = pg_feedback_touchpoints();
    $touchpoint = isset($points[$row['touchpoint'] ?? '']) ? (string) $row['touchpoint'] : 'booking';
    $filter = json_decode((string) ($row['filter'] ?? ''), true);
    $survey = array('name' => (string) ($row['name'] ?? ''), 'touchpoint' => $touchpoint, 'channel' => (string) ($row['channel'] ?? 'email'));

    foreach (array('id', 'kind', 'delay_minutes', 'purpose', 'fallback_user_id', 'escalation_user_id', 'ws_channel_id', 'review_invite', 'enabled', 'archived_at', 'created_at', 'updated_at') as $column) {
        $survey[$column] = (int) ($row[$column] ?? 0);
    }

    $survey['filter'] = array();

    foreach ($points[$touchpoint]['filter'] as $key => $default) {
        $value = (is_array($filter) && isset($filter[$key])) ? $filter[$key] : $default;
        $survey['filter'][$key] = is_int($default) ? (int) $value : (string) $value;
    }

    $survey['texts'] = pg_feedback_texts_decode($row['texts'] ?? null);

    return $survey;
}

/**
 * A survey's texts: question and comment label per language, the extra
 * questions (type, label per language, choices per language). Whatever the
 * stored JSON holds, the answer has every key. Pure.
 *
 * @param string|null $json feedback_surveys.texts
 * @return array question (lang => text), comment (lang => text), extra (list)
 */
function pg_feedback_texts_decode($json)
{
    $data = json_decode((string) $json, true);
    $data = is_array($data) ? $data : array();
    $map = function ($value) {
        $out = array();

        foreach ((is_array($value) ? $value : array()) as $lang => $text) {
            if (is_string($lang) && is_string($text)) {
                $out[$lang] = $text;
            }
        }

        return $out;
    };
    $texts = array('question' => $map($data['question'] ?? null), 'comment' => $map($data['comment'] ?? null), 'extra' => array());

    foreach (array_slice(is_array($data['extra'] ?? null) ? $data['extra'] : array(), 0, PG_FEEDBACK_EXTRA_MAX) as $extra) {
        if (!is_array($extra)) {
            continue;
        }

        $choices = array();

        foreach ((is_array($extra['choices'] ?? null) ? $extra['choices'] : array()) as $lang => $list) {
            if (is_string($lang) && is_array($list)) {
                $choices[$lang] = array_values(array_filter($list, 'is_string'));
            }
        }

        $texts['extra'][] = array('type' => (($extra['type'] ?? '') === 'choice') ? 'choice' : 'text', 'label' => $map($extra['label'] ?? null), 'choices' => $choices);
    }

    return $texts;
}

/**
 * A text in a language: its own, else the first language's, else ''. Pure.
 *
 * @param array  $map       lang => text
 * @param string $lang
 * @param string $first_lang the site's first language
 * @return string
 */
function pg_feedback_text_in($map, $lang, $first_lang)
{
    foreach (array($lang, $first_lang) as $code) {
        if (isset($map[$code]) && (trim((string) $map[$code]) !== '')) {
            return (string) $map[$code];
        }
    }

    return '';
}

/**
 * A list of choices typed on one line, split on commas: trimmed, each once,
 * at most ten of 60 characters. Pure.
 *
 * @param string $typed
 * @return string[]
 */
function pg_feedback_choices_split($typed)
{
    $out = array();

    foreach (explode(',', (string) $typed) as $choice) {
        $choice = mb_substr(trim($choice), 0, 60);

        if (($choice !== '') && !in_array($choice, $out, true)) {
            $out[] = $choice;
        }
    }

    return array_slice($out, 0, 10);
}

/**
 * A delay as the edit screen shows it: whole days when it is some, else
 * hours. Pure.
 *
 * @param int $minutes
 * @return array amount, unit ('hours' or 'days')
 */
function pg_feedback_delay_split($minutes)
{
    $minutes = max(0, (int) $minutes);

    if (($minutes > 0) && ($minutes % 1440 === 0)) {
        return array('amount' => intdiv($minutes, 1440), 'unit' => 'days');
    }

    return array('amount' => (int) round($minutes / 60), 'unit' => 'hours');
}

/**
 * The edit screen's posted values as a survey, with what is wrong. Ids the
 * screen did not offer are read as none. Pure but for lang().
 *
 * @param array  $post      $_POST
 * @param array  $known     users, channels (id lists) and languages (codes,
 *                          the site's first one first)
 * @param string $touchpoint the survey's touchpoint (fixed once made)
 * @return array survey, errors (list of sentences)
 */
function pg_feedback_survey_input(array $post, array $known, $touchpoint)
{
    $points = pg_feedback_touchpoints();
    $touchpoint = isset($points[$touchpoint]) ? (string) $touchpoint : 'booking';
    $languages = $known['languages'] ? array_values($known['languages']) : array('en');
    $first = $languages[0];
    $string = function ($key, $max) use ($post) {
        return mb_substr(trim(is_string($post[$key] ?? null) ? $post[$key] : ''), 0, $max);
    };
    $id_in = function ($key, $list) use ($post) {
        $id = (int) (is_scalar($post[$key] ?? null) ? $post[$key] : 0);

        return in_array($id, array_map('intval', (array) $list), true) ? $id : 0;
    };
    $errors = array();
    $survey = pg_feedback_survey_start($touchpoint);
    $survey['name'] = $string('name', 150);
    $kind = (int) ($post['kind'] ?? 0);
    $survey['kind'] = isset(pg_feedback_kind_names()[$kind]) ? $kind : $points[$touchpoint]['kind'];
    $channel = (string) ($post['channel'] ?? '');
    $survey['channel'] = isset(pg_feedback_channel_names()[$channel]) ? $channel : 'email';
    $survey['purpose'] = (($touchpoint === 'periodic') || (($post['purpose'] ?? '') === '1')) ? 1 : 0;
    $survey['fallback_user_id'] = $id_in('fallback_user_id', $known['users'] ?? array());
    $survey['escalation_user_id'] = $id_in('escalation_user_id', $known['users'] ?? array());
    $survey['ws_channel_id'] = $id_in('ws_channel_id', $known['channels'] ?? array());
    $survey['review_invite'] = empty($post['review_invite']) ? 0 : 1;
    $survey['enabled'] = empty($post['enabled']) ? 0 : 1;

    if ($survey['name'] === '') {
        $errors[] = lang('Give the survey a name.');
    }

    // What it follows: only the touchpoint's own keys.
    $filter = (is_array($post['filter'] ?? null)) ? $post['filter'] : array();

    foreach ($points[$touchpoint]['filter'] as $key => $default) {
        $value = $filter[$key] ?? $default;
        $survey['filter'][$key] = is_int($default) ? max(0, (int) (is_scalar($value) ? $value : 0)) : mb_substr(trim(is_string($value) ? $value : ''), 0, 100);
    }

    if ($touchpoint === 'periodic') {
        $survey['delay_minutes'] = 0;

        if (($survey['filter']['every'] < 1) || ($survey['filter']['every'] > 24) || ($survey['filter']['active'] < 1) || ($survey['filter']['active'] > 36)) {
            $errors[] = lang('Ask every 1 to 24 months, people active in the last 1 to 36 months.');
        }
    } else {
        $amount = (int) ($post['delay_amount'] ?? 0);
        $survey['delay_minutes'] = max(0, $amount) * ((($post['delay_unit'] ?? '') === 'days') ? 1440 : 60);

        if (($amount < 0) || ($survey['delay_minutes'] > 90 * 1440)) {
            $errors[] = lang('The survey goes at most 90 days after.');
        }
    }

    if (($touchpoint === 'form') && ($survey['filter']['field'] > 0) && ($survey['filter']['value'] === '')) {
        $errors[] = lang('Type the choice that sends the survey.');
    }

    // Texts per language; an empty question or label uses the default.
    $texts = array('question' => array(), 'comment' => array(), 'extra' => array());

    foreach ($languages as $lang) {
        foreach (array('question' => 300, 'comment' => 150) as $key => $max) {
            $text = mb_substr(trim(is_string($post[$key][$lang] ?? null) ? $post[$key][$lang] : ''), 0, $max);

            if ($text !== '') {
                $texts[$key][$lang] = $text;
            }
        }
    }

    foreach (array_slice(is_array($post['extra'] ?? null) ? $post['extra'] : array(), 0, PG_FEEDBACK_EXTRA_MAX) as $row) {
        $label_first = is_array($row) ? trim(is_string($row['label'][$first] ?? null) ? $row['label'][$first] : '') : '';

        if ($label_first === '') {
            continue;
        }

        $extra = array('type' => (($row['type'] ?? '') === 'choice') ? 'choice' : 'text', 'label' => array(), 'choices' => array());

        foreach ($languages as $lang) {
            $label = mb_substr(trim(is_string($row['label'][$lang] ?? null) ? $row['label'][$lang] : ''), 0, 200);

            if ($label !== '') {
                $extra['label'][$lang] = $label;
            }

            if ($extra['type'] === 'choice') {
                $choices = pg_feedback_choices_split(is_string($row['choices'][$lang] ?? null) ? $row['choices'][$lang] : '');

                if ($choices) {
                    $extra['choices'][$lang] = $choices;
                }
            }
        }

        if (($extra['type'] === 'choice') && (count($extra['choices'][$first] ?? array()) < 2)) {
            $errors[] = lang(array('string' => 'Give "{var:1}" at least two choices, split by commas.', 'vars' => array($label_first)));
        }

        $texts['extra'][] = $extra;
    }

    $survey['texts'] = $texts;

    return array('survey' => $survey, 'errors' => $errors);
}

/**
 * The columns a survey is stored with. Pure.
 *
 * @param array $survey
 * @return array column => value
 */
function pg_feedback_survey_columns(array $survey)
{
    $columns = array();

    foreach (array('name', 'kind', 'touchpoint', 'delay_minutes', 'channel', 'purpose', 'fallback_user_id', 'escalation_user_id', 'ws_channel_id', 'review_invite', 'enabled') as $column) {
        $columns[$column] = $survey[$column];
    }

    $columns['filter'] = $survey['filter'] ? json_encode($survey['filter'], JSON_UNESCAPED_UNICODE) : null;
    $columns['texts'] = json_encode($survey['texts'], JSON_UNESCAPED_UNICODE);

    return $columns;
}

/**
 * "Sent after" in the person's words: "2 hours after a booking", "Every 6
 * months". Pure but for lang().
 *
 * @param array $survey
 * @return string
 */
function pg_feedback_sent_after_words(array $survey)
{
    if ($survey['touchpoint'] === 'periodic') {
        return lang(array('string' => 'Every {var:1} months', 'vars' => array((int) $survey['filter']['every'])));
    }

    // Whole sentences, so each language can say them in its own order.
    $words = array(
        'booking'  => array('Right after a booking', '{var:1} hours after a booking', '{var:1} days after a booking'),
        'ticket'   => array('Right after an event', '{var:1} hours after an event', '{var:1} days after an event'),
        'course'   => array('Right after a course', '{var:1} hours after a course', '{var:1} days after a course'),
        'form'     => array('Right after a form', '{var:1} hours after a form', '{var:1} days after a form'),
        'delivery' => array('Right after a delivery', '{var:1} hours after a delivery', '{var:1} days after a delivery'),
        'mail'     => array('Right after a mail conversation', '{var:1} hours after a mail conversation', '{var:1} days after a mail conversation'),
    );
    $sentences = $words[$survey['touchpoint']] ?? $words['booking'];
    $delay = pg_feedback_delay_split($survey['delay_minutes']);

    if ($delay['amount'] === 0) {
        return lang($sentences[0]);
    }

    return lang(array('string' => $sentences[($delay['unit'] === 'days') ? 2 : 1], 'vars' => array($delay['amount'])));
}

/**
 * The e-mail's body: the question, the score buttons and the comment's
 * label, in tables mail programs draw. In a preview or a test the buttons
 * lead nowhere; the sends give each its signed link. No promotion and no
 * review invitation: those belong to the answer page. Pure but for h().
 *
 * @param int    $kind
 * @param string $question
 * @param string $comment the comment's label
 * @return string HTML
 */
function pg_feedback_mail_html($kind, $question, $comment)
{
    $range = pg_feedback_range($kind);
    $cells = '';

    for ($score = $range[0]; $score <= $range[1]; $score++) {
        $cells .= '<td style="padding:2px"><span style="display:inline-block;min-width:28px;padding:8px 4px;border:1px solid #c9ced6;border-radius:6px;font:600 15px/1 Arial,sans-serif;color:#1f2933;text-align:center">' . $score . '</span></td>';
    }

    return '<div style="font:15px/1.5 Arial,sans-serif;color:#1f2933">'
        . '<p style="margin:0 0 12px;font-weight:600">' . h($question) . '</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse"><tr>' . $cells . '</tr></table>'
        . '<p style="margin:12px 0 0;color:#52606d">' . h($comment) . '</p></div>';
}

/**
 * Whether the tables are there (the update has run).
 *
 * @return bool
 */
function pg_feedback_surveys_ready()
{
    return pg_schema_has('feedback_surveys') && pg_schema_has('feedback_responses');
}

/**
 * The site's languages for a survey's texts, its first language first; one
 * when pages are not translated.
 *
 * @return array code => name
 */
function pg_feedback_survey_languages()
{
    $first = function_exists('pg_tr_source_language') ? pg_tr_source_language() : 'en';
    $out = array($first => strtoupper($first));

    if (function_exists('pg_tr_ready') && pg_tr_ready()) {
        foreach (pg_tr_languages() as $code => $row) {
            $out[(string) $code] = !empty($row['name']) ? (string) $row['name'] : strtoupper((string) $code);
        }
    }

    return $out;
}

/**
 * One survey, or null.
 *
 * @param int $id
 * @return array|null
 */
function pg_feedback_survey_get($id)
{
    $row = db_item("SELECT * FROM feedback_surveys WHERE id = '" . (int) $id . "'");

    return is_array($row) ? pg_feedback_survey_decode($row) : null;
}

/**
 * The surveys not archived, newest first, each with whether a send was
 * ever queued for it (then it is archived, not deleted).
 *
 * @return array[]
 */
function pg_feedback_survey_list()
{
    $out = array();

    foreach ((array) db_items("SELECT s.*, EXISTS (SELECT 1 FROM feedback_responses r WHERE r.survey_id = s.id) AS used FROM feedback_surveys s WHERE s.archived_at = 0 ORDER BY s.id DESC") as $row) {
        $survey = pg_feedback_survey_decode($row);
        $survey['used'] = !empty($row['used']);
        $out[] = $survey;
    }

    return $out;
}

/**
 * Whether a send was ever queued for a survey.
 *
 * @param int $id
 * @return bool
 */
function pg_feedback_survey_used($id)
{
    return (int) db_value("SELECT COUNT(*) FROM feedback_responses WHERE survey_id = '" . (int) $id . "' LIMIT 1") > 0;
}

/**
 * Stores a survey; a new one with its maker and time.
 *
 * @param int   $id 0 for a new one
 * @param array $survey
 * @param int   $user_id
 * @return int the survey's id, 0 when the write failed
 */
function pg_feedback_survey_save($id, array $survey, $user_id)
{
    $columns = pg_feedback_survey_columns($survey);
    $columns['updated_at'] = time();

    if ((int) $id <= 0) {
        $columns['created_by'] = (int) $user_id;
        $columns['created_at'] = $columns['updated_at'];
    }

    $set = array();

    foreach ($columns as $column => $value) {
        $set[] = $column . ' = ' . (($value === null) ? 'NULL' : "'" . escape((string) $value) . "'");
    }

    if ((int) $id > 0) {
        return (db("UPDATE feedback_surveys SET " . implode(', ', $set) . " WHERE id = '" . (int) $id . "'") === false) ? 0 : (int) $id;
    }

    return (db("INSERT INTO feedback_surveys SET " . implode(', ', $set)) === false) ? 0 : (int) mysqli_insert_id(db::$con);
}

/**
 * Takes a survey off: deleted while nothing was ever queued for it, else
 * archived and switched off, its answers kept for the results.
 *
 * @param int $id
 * @return string 'deleted' or 'archived'
 */
function pg_feedback_survey_remove($id)
{
    if (!pg_feedback_survey_used($id)) {
        db("DELETE FROM feedback_surveys WHERE id = '" . (int) $id . "'");

        return 'deleted';
    }

    db("UPDATE feedback_surveys SET archived_at = '" . time() . "', enabled = 0, updated_at = '" . time() . "' WHERE id = '" . (int) $id . "'");

    return 'archived';
}

/**
 * The choices of the edit screen's selects, each id => name, read once.
 *
 * @return array services, events, courses, forms, fields (id => form,
 *               label), boxes, users, channels (empty without the Workspace)
 */
function pg_feedback_survey_choices()
{
    $pairs = function ($sql) {
        $out = array();

        foreach ((array) db_items($sql) as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }

        return $out;
    };
    $choices = array(
        'services' => pg_schema_has('booking_services') ? $pairs("SELECT id, name FROM booking_services ORDER BY sort_order, name") : array(),
        'events'   => $pairs("SELECT id, name FROM calendar_events WHERE reservations = '1' ORDER BY name LIMIT 500"),
        'courses'  => pg_schema_has('course') ? $pairs("SELECT id, name FROM course ORDER BY name") : array(),
        'forms'    => $pairs("SELECT page_id AS id, form_name AS name FROM custom_form_pages ORDER BY form_name"),
        'fields'   => array(),
        'boxes'    => pg_schema_has('ws_mail_boxes') ? $pairs("SELECT id, IF(name = '', address, name) AS name FROM ws_mail_boxes ORDER BY name") : array(),
        'users'    => $pairs("SELECT user_id AS id, user_username AS name FROM user WHERE user_role <= 3 ORDER BY user_username LIMIT 1000"),
        'channels' => array(),
    );

    foreach ((array) db_items("SELECT id, page_id, IF(label = '', name, label) AS label FROM form_fields WHERE type IN ('pick list', 'radio button') ORDER BY page_id, sort_order LIMIT 1000") as $row) {
        if (isset($choices['forms'][(int) $row['page_id']])) {
            $choices['fields'][(int) $row['id']] = array('form' => (int) $row['page_id'], 'label' => (string) $row['label']);
        }
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if (function_exists('ws_enabled') && ws_enabled()) {
        $choices['channels'] = $pairs("SELECT id, name FROM ws_channels WHERE archived_at = 0 AND kind = 'public' ORDER BY name");
    }

    return $choices;
}

/**
 * "Send me a test": the survey's e-mail in the site's first language to the
 * signed-in person, subject marked [Test]. Counts nowhere and writes no
 * answer row; the buttons lead nowhere until the sends give them links.
 *
 * @param array  $survey
 * @param string $address
 * @return bool
 */
function pg_feedback_survey_test_send(array $survey, $address)
{
    $first = array_keys(pg_feedback_survey_languages())[0];
    $defaults = pg_feedback_default_texts($survey['kind']);
    $question = pg_feedback_text_in($survey['texts']['question'], $first, $first);
    $comment = pg_feedback_text_in($survey['texts']['comment'], $first, $first);
    $question = ($question !== '') ? $question : $defaults['question'];

    return (bool) email(array(
        'to' => (string) $address,
        'subject' => lang(array('string' => '[Test] {var:1}', 'vars' => array($question))),
        'format' => 'html',
        'body' => pg_feedback_mail_html($survey['kind'], $question, ($comment !== '') ? $comment : $defaults['comment']),
    ));
}
