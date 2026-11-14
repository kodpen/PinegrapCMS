<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp Cloud API driver's template calls: a template sent to Meta
 * for review, a rejected one sent again, and the sample file a picture,
 * video or document header needs for the review.
 *
 *   POST /<account id>/message_templates   a new template: name, language,
 *                                          category, parameter_format and
 *                                          components; the answer is its id,
 *                                          status (PENDING, APPROVED or
 *                                          REJECTED) and category
 *   POST /<template id>                    a template edited: its category
 *                                          and components (a rejected or
 *                                          paused one; name and language
 *                                          stay)
 *   POST /<app id>/uploads                 starts a Resumable Upload session
 *                                          for a header sample: the file's
 *                                          name, length and type; the answer
 *                                          is "upload:…"
 *   POST /<upload session id>              the file's bytes from offset 0,
 *                                          with "Authorization: OAuth"; the
 *                                          answer's h is the handle the
 *                                          header's example names
 *
 * Built from Meta's documentation, read on 2026-11-12 (the facts are in
 * docs/memory/platform/whatsapp-cloud-api-facts.md):
 * https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview
 * https://developers.facebook.com/docs/graph-api/guides/upload
 *
 * Request builders and answer readers are plain functions of their
 * arguments; the calls themselves go through pg_whatsapp_http()
 * (includes/fn/whatsapp.php). Loaded with includes/whatsapp/cloud_api.php,
 * whose answer reader (pg_whatsapp_cloud_answer()) and headers they use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');

/**
 * A template sent to Meta: new to the account's list, or an edit of the one
 * Meta knows by $meta_id.
 *
 * @param array  $app      pg_whatsapp_app() shape
 * @param array  $template name, language, category, components (Meta's
 *                         creation shape)
 * @param string $meta_id  '' for a new template
 * @return array url, headers, body
 */
function pg_whatsapp_cloud_template_submit_request($app, $template, $meta_id = '')
{
    $proof = array('appsecret_proof' => pg_whatsapp_cloud_proof($app['token'], $app['app_secret']));

    if ((string) $meta_id !== '') {
        $body = array(
            'category'   => (string) $template['category'],
            'components' => array_values($template['components']),
        );
        $path = rawurlencode((string) $meta_id);
    } else {
        $body = array(
            'name'             => (string) $template['name'],
            'language'         => (string) $template['language'],
            'category'         => (string) $template['category'],
            'parameter_format' => 'POSITIONAL',
            'components'       => array_values($template['components']),
        );
        $path = rawurlencode((string) $app['account_id']) . '/message_templates';
    }

    return array(
        'url'     => pg_whatsapp_cloud_url($path, $proof),
        'headers' => pg_whatsapp_cloud_headers($app['token'], true),
        'body'    => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * Reads Meta's answer to a template sent for review.
 *
 * A new template's answer is {id, status, category}; an edit's is
 * {success: true}, after which Meta reviews it again.
 *
 * @param array  $answer  pg_whatsapp_cloud_answer()
 * @param string $meta_id the edited template's id, '' for a new one
 * @return array ok, id, status, category ('' when the answer has none),
 *               error
 */
function pg_whatsapp_cloud_template_submit_parse($answer, $meta_id = '')
{
    $refused = array('ok' => false, 'id' => '', 'status' => '', 'category' => '', 'error' => '');

    if ($answer['error'] !== '') {
        $refused['error'] = $answer['error'];
        return $refused;
    }

    $data = $answer['data'];
    $text = function ($name) use ($data) {
        return (isset($data[$name]) && is_scalar($data[$name])) ? strtoupper(trim((string) $data[$name])) : '';
    };

    if ((string) $meta_id !== '') {
        if (empty($data['success'])) {
            $refused['error'] = lang('Meta did not confirm the change to the template.');
            return $refused;
        }

        return array('ok' => true, 'id' => (string) $meta_id, 'status' => 'PENDING', 'category' => $text('category'), 'error' => '');
    }

    $id = (isset($data['id']) && is_scalar($data['id'])) ? trim((string) $data['id']) : '';

    if (!preg_match('/^[0-9]{1,32}$/D', $id)) {
        $refused['error'] = lang('Meta took the template but returned no id for it. Refresh the templates to see it.');
        return $refused;
    }

    return array('ok' => true, 'id' => $id, 'status' => ($text('status') !== '') ? $text('status') : 'PENDING', 'category' => $text('category'), 'error' => '');
}

/**
 * Starts a Resumable Upload session for a header's sample file.
 *
 * @param array  $app
 * @param string $name   the file's name
 * @param int    $length bytes
 * @param string $type   its MIME type (image/jpeg, video/mp4, application/pdf)
 * @return array url, headers
 */
function pg_whatsapp_cloud_upload_start_request($app, $name, $length, $type)
{
    return array(
        'url'     => pg_whatsapp_cloud_url(rawurlencode((string) $app['app_id']) . '/uploads', array(
            'file_name'       => (string) $name,
            'file_length'     => (int) $length,
            'file_type'       => (string) $type,
            'appsecret_proof' => pg_whatsapp_cloud_proof($app['token'], $app['app_secret']),
        )),
        'headers' => pg_whatsapp_cloud_headers($app['token']),
    );
}

/**
 * Sends a sample file's bytes into its upload session, from the start.
 *
 * @param array  $app
 * @param string $session "upload:…" from pg_whatsapp_cloud_upload_start_request()
 * @param string $bytes
 * @return array url, headers, body
 */
function pg_whatsapp_cloud_upload_send_request($app, $session, $bytes)
{
    return array(
        // The session id is used as it came ("upload:…"); its colon is
        // part of the path Meta gave.
        'url'     => pg_whatsapp_cloud_url((string) $session),
        'headers' => array(
            'Authorization: OAuth ' . $app['token'],
            'file_offset: 0',
            'Content-Type: application/octet-stream',
        ),
        'body'    => (string) $bytes,
    );
}

/**
 * Reads the answer to an upload call: the session's id ("upload:…") when it
 * started one, the file's handle (h) when the bytes arrived.
 *
 * @param array  $answer pg_whatsapp_cloud_answer()
 * @param string $field  'id' or 'h'
 * @return array ok, value, error
 */
function pg_whatsapp_cloud_upload_parse($answer, $field)
{
    if ($answer['error'] !== '') {
        return array('ok' => false, 'value' => '', 'error' => $answer['error']);
    }

    $value = (isset($answer['data'][$field]) && is_scalar($answer['data'][$field])) ? trim((string) $answer['data'][$field]) : '';

    if (($value === '') || (($field === 'id') && (strpos($value, 'upload:') !== 0)) || preg_match('/[\s\/?#]/', ($field === 'id') ? $value : '')) {
        return array('ok' => false, 'value' => '', 'error' => lang('Meta did not take the header\'s sample file.'));
    }

    return array('ok' => true, 'value' => $value, 'error' => '');
}

/**
 * Uploads a header's sample file and returns its handle.
 *
 * @param array  $app
 * @param string $name
 * @param string $type
 * @param string $bytes
 * @return array ok, handle, error
 */
function pg_whatsapp_cloud_upload($app, $name, $type, $bytes)
{
    $response = pg_whatsapp_http('POST', pg_whatsapp_cloud_upload_start_request($app, $name, strlen($bytes), $type));
    $start = pg_whatsapp_cloud_upload_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']), 'id');

    if (!$start['ok']) {
        return array('ok' => false, 'handle' => '', 'error' => $start['error']);
    }

    $response = pg_whatsapp_http('POST', pg_whatsapp_cloud_upload_send_request($app, $start['value'], $bytes));
    $sent = pg_whatsapp_cloud_upload_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']), 'h');

    return array('ok' => $sent['ok'], 'handle' => $sent['value'], 'error' => $sent['error']);
}

/**
 * Sends a template for review.
 *
 * @param array  $app
 * @param array  $template
 * @param string $meta_id '' for a new template
 * @return array pg_whatsapp_cloud_template_submit_parse()
 */
function pg_whatsapp_cloud_template_submit($app, $template, $meta_id = '')
{
    $response = pg_whatsapp_http('POST', pg_whatsapp_cloud_template_submit_request($app, $template, $meta_id));

    return pg_whatsapp_cloud_template_submit_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']), $meta_id);
}
