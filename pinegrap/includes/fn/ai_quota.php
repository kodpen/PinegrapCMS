<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pinegrap AI's daily token counter: the licence's tokens of the day as the
 * gateway counts them (every site and key of the licence together), read
 * from its GET /license answer and kept as the config_text row ws_ai_quota,
 * so a panel page shows it without asking anyone. The Workspace AI's calls
 * keep it fresh (ws_ai_quota_refresh(), includes/workspace/ai.php).
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
 * How old the kept figure may grow, in seconds, before a call to Pinegrap AI
 * asks the gateway for it again.
 */
if (!defined('PG_AI_QUOTA_TTL')) {
    define('PG_AI_QUOTA_TTL', 60);
}

/**
 * A time the gateway sent: a unix time or a date strtotime() reads. Pure.
 *
 * @param mixed $value
 * @return int 0 when there is none
 */
function pg_ai_quota_time($value)
{
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return max(0, (int) $value);
    }

    $time = (is_string($value) && ($value !== '')) ? strtotime($value) : false;

    return ($time === false) ? 0 : (int) $time;
}

/**
 * The counter a gateway licence answer gives (its ai block: enabled,
 * daily_tokens with 0 for no limit, used, resets_at). An answer without the
 * block, or with AI off, gives a counter that is not shown, so the gateway is
 * not asked again before PG_AI_QUOTA_TTL. Pure.
 *
 * @param mixed $json the decoded answer
 * @param int   $now  when it was read
 * @return array enabled, limit, used, resets_at, checked
 */
function pg_ai_quota_from($json, $now)
{
    $ai = (is_array($json) && isset($json['ai']) && is_array($json['ai'])) ? $json['ai'] : array();

    return array(
        'enabled'   => !empty($ai['enabled']),
        'limit'     => max(0, (int) ($ai['daily_tokens'] ?? 0)),
        'used'      => max(0, (int) ($ai['used'] ?? 0)),
        'resets_at' => pg_ai_quota_time($ai['resets_at'] ?? 0),
        'checked'   => (int) $now,
    );
}

/**
 * A kept counter read back; anything else is no counter. Pure.
 *
 * @param string $text the config_text value
 * @return array|null pg_ai_quota_from()'s shape
 */
function pg_ai_quota_decode($text)
{
    $data = json_decode((string) $text, true);

    if (!is_array($data) || !isset($data['checked'])) {
        return null;
    }

    return array(
        'enabled'   => !empty($data['enabled']),
        'limit'     => max(0, (int) ($data['limit'] ?? 0)),
        'used'      => max(0, (int) ($data['used'] ?? 0)),
        'resets_at' => max(0, (int) ($data['resets_at'] ?? 0)),
        'checked'   => max(0, (int) $data['checked']),
    );
}

/**
 * The kept counter.
 *
 * @return array|null
 */
function pg_ai_quota()
{
    return pg_ai_quota_decode(pg_config_text('ws_ai_quota'));
}

/**
 * Keeps a counter.
 *
 * @param array $quota pg_ai_quota_from()
 * @return bool
 */
function pg_ai_quota_save($quota)
{
    return pg_config_text_save('ws_ai_quota', json_encode($quota));
}

/**
 * Whether the gateway should be asked again: nothing kept, a figure older
 * than PG_AI_QUOTA_TTL, or a day that has ended since. Pure.
 *
 * @param array|null $quota
 * @param int        $now
 * @return bool
 */
function pg_ai_quota_due($quota, $now)
{
    if (!is_array($quota)) {
        return true;
    }

    if (($now - (int) $quota['checked']) >= PG_AI_QUOTA_TTL) {
        return true;
    }

    return ((int) $quota['resets_at'] > 0) && ($now >= (int) $quota['resets_at']);
}

/**
 * What the counter shows at a time. A figure from a day that has ended counts
 * 0 until the gateway is asked again, and its reset moves on by days. Pure.
 *
 * @param array|null $quota pg_ai_quota()
 * @param int        $now
 * @return array|null used, limit (0 = no limit), percent (0-100), tone
 *                    (success | warning | danger), resets_at (0 when not
 *                    known); null when AI is not part of the licence or
 *                    nothing is known
 */
function pg_ai_quota_view($quota, $now)
{
    if (!is_array($quota) || empty($quota['enabled'])) {
        return null;
    }

    $used = (int) $quota['used'];
    $limit = (int) $quota['limit'];
    $resets = (int) $quota['resets_at'];

    if (($resets > 0) && ($now >= $resets)) {
        $used = 0;
        $resets += (intdiv($now - $resets, 86400) + 1) * 86400;
    }

    $percent = ($limit > 0) ? min(100, (int) floor($used * 100 / $limit)) : 0;
    $tone = 'success';

    if (($limit > 0) && ($used >= $limit)) {
        $tone = 'danger';
    } elseif ($percent >= 80) {
        $tone = 'warning';
    }

    return array('used' => $used, 'limit' => $limit, 'percent' => $percent, 'tone' => $tone, 'resets_at' => $resets);
}

/**
 * A token count with the site's number separators.
 *
 * @param int $n
 * @return string
 */
function pg_ai_quota_number($n)
{
    return function_exists('pg_format_number') ? pg_format_number((int) $n, 0) : number_format((int) $n);
}

/**
 * The counter in one line: "1,234 / 5,000 tokens" or "1,234 tokens, no
 * daily limit". Pure but for lang() and the number separators.
 *
 * @param array $view pg_ai_quota_view()
 * @return string plain text
 */
function pg_ai_quota_line($view)
{
    if ((int) $view['limit'] > 0) {
        return lang(array('string' => '{var:1} / {var:2} tokens', 'vars' => array(pg_ai_quota_number($view['used']), pg_ai_quota_number($view['limit']))));
    }

    return lang(array('string' => '{var:1} tokens, no daily limit', 'vars' => pg_ai_quota_number($view['used'])));
}

/**
 * When the day's count starts again, in the site's clock. Pure but for lang().
 *
 * @param array $view pg_ai_quota_view()
 * @return string plain text, '' when not known
 */
function pg_ai_quota_reset_text($view)
{
    if ((int) $view['resets_at'] <= 0) {
        return '';
    }

    return lang(array('string' => 'Starts again at {var:1}', 'vars' => date('H:i', (int) $view['resets_at'])));
}

/**
 * The counter under the licence card's days: today's Pinegrap AI tokens of
 * the licence, a thin bar while there is a limit, and when it starts again.
 * Pure but for lang().
 *
 * @param array|null $view pg_ai_quota_view()
 * @return string HTML, '' when there is nothing to show
 */
function pg_ai_quota_card_html($view)
{
    if (!is_array($view)) {
        return '';
    }

    $title = lang('Pinegrap AI today');
    $bar = '';

    if ((int) $view['limit'] > 0) {
        $fills = array('success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger');
        $bar = '
                    <div class="progress rounded-pill overflow-hidden mt-1" style="height:3px">
                        <div class="progress-bar ' . ($fills[$view['tone']] ?? 'bg-success') . '" role="progressbar" aria-label="' . h($title) . '"
                             style="width:' . (int) $view['percent'] . '%"
                             aria-valuenow="' . (int) $view['percent'] . '" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>';
    }

    $reset = pg_ai_quota_reset_text($view);

    return '
                <div class="px-5 pb-3 text-center pg-lic-ai">
                    <div class="fg-secondary small">' . h($title) . '</div>
                    <div class="small fw-bold fg-' . h($view['tone']) . '">' . h(pg_ai_quota_line($view)) . '</div>' . $bar
                    . (($reset !== '') ? '
                    <div class="fg-secondary mt-1" style="font-size:.7rem;letter-spacing:.02em">' . h($reset) . '</div>' : '') . '
                </div>';
}
