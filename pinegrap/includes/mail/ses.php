<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Amazon SES mail transport (SES API v2, SendEmail): one message is a
 * JSON POST to /v2/email/outbound-emails on the region's endpoint, signed
 * with AWS Signature Version 4 (includes/fn/aws_sign.php, service "ses")
 * by an IAM access key allowed ses:SendEmail. The message goes whole, as
 * Raw content (the MIME PHPMailer built, base64), so headers, Reply-To and
 * attachments travel as they would over SMTP; the recipients, Bcc
 * included, are named in Destination. A configuration set, when named,
 * decides where SES reports bounces and complaints. Success is 200 with the
 * MessageId; a refusal is a 4xx with {message}.
 *
 * Built from the public API documentation:
 * https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html
 * https://docs.aws.amazon.com/general/latest/gr/ses.html
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/aws_sign.php');

function pg_mail_ses_fields()
{
    return array(
        'key_id'            => array('label' => lang('Access key ID'), 'type' => 'text', 'required' => true),
        'secret'            => array('label' => lang('Secret access key'), 'type' => 'secret'),
        'region'            => array('label' => lang('Region'), 'type' => 'text', 'required' => true, 'default' => 'us-east-1', 'help' => lang('The AWS region the sending identity was verified in, for example eu-central-1.')),
        'configuration_set' => array('label' => lang('Configuration set'), 'type' => 'text', 'help' => lang('Optional. The configuration set whose event destinations receive bounces and complaints.')),
    );
}

function pg_mail_ses_capabilities()
{
    // 40 MB per message, the MIME counted in base64.
    return array('max_bytes' => 41943040, 'webhook' => true, 'raw' => true);
}

/**
 * A region name as an endpoint host takes it.
 *
 * @param string $region
 * @return string '' when it is no region name
 */
function pg_mail_ses_region($region)
{
    $region = strtolower(trim((string) $region));

    return preg_match('/^[a-z]{2}(-[a-z]+)+-\d{1,2}$/D', $region) ? $region : '';
}

function pg_mail_ses_request($message, $settings, $amz_date = null)
{
    $destination = array();

    foreach (array('to' => 'ToAddresses', 'cc' => 'CcAddresses', 'bcc' => 'BccAddresses') as $list => $name) {
        foreach ($message[$list] as $entry) {
            $destination[$name][] = pg_mail_transport_address($entry[0], isset($entry[1]) ? $entry[1] : '');
        }
    }

    $body = array(
        'FromEmailAddress' => pg_mail_transport_address($message['from'], $message['from_name']),
        'Destination'      => $destination,
        'Content'          => array('Raw' => array('Data' => base64_encode($message['mime']))),
    );

    if ($settings['values']['configuration_set'] !== '') {
        $body['ConfigurationSetName'] = $settings['values']['configuration_set'];
    }

    $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $region = pg_mail_ses_region($settings['values']['region']);
    $url = 'https://email.' . (($region !== '') ? $region : 'invalid') . '.amazonaws.com/v2/email/outbound-emails';
    $headers = array(array('Content-Type', 'application/json'));

    $signed = pg_aws_authorization(
        array('id' => $settings['values']['key_id'], 'secret' => $settings['secret']),
        $region, 'ses', 'POST', $url, $headers, is_string($json) ? $json : '', $amz_date);

    return array(
        'url'     => $url,
        'headers' => array_merge(array('Accept: application/json', 'Content-Type: application/json'), $signed),
        'body'    => $json,
    );
}

function pg_mail_ses_answer($status, $body)
{
    $data = json_decode((string) $body, true);
    $data = is_array($data) ? $data : array();

    if (($status === 200) && isset($data['MessageId'])) {
        return array('ok' => true, 'id' => (string) $data['MessageId'], 'error' => '');
    }

    foreach (array('message', 'Message') as $key) {
        if (isset($data[$key]) && is_string($data[$key])) {
            return array('ok' => false, 'id' => '', 'error' => $data[$key]);
        }
    }

    return array('ok' => false, 'id' => '', 'error' => '');
}

function pg_mail_ses_send($message, $settings)
{
    if (pg_mail_ses_region($settings['values']['region']) === '') {
        return pg_mail_transport_result(false, '', 'Amazon SES: ' . lang('The region is not an AWS region name.'));
    }

    return pg_mail_transport_call('ses', pg_mail_ses_request($message, $settings));
}
