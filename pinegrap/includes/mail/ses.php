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

/**
 * An Amazon SNS message carrying an SES notification (a topic set as the
 * identity's feedback destination, or a configuration set's event
 * destination). A SubscriptionConfirmation gives one event of kind
 * 'confirm' with the address to visit, when that address is an SNS host
 * over HTTPS. A notification's Bounce (Permanent is hard, Transient and
 * Undetermined soft), Complaint and Delivery give one event per address.
 * Pure.
 *
 * https://docs.aws.amazon.com/ses/latest/dg/notification-contents.html
 *
 * @param string $raw
 * @param array  $headers lower-case name => value
 * @return array|false list of id, kind, address, message_id (and url)
 */
function pg_mail_ses_webhook($raw, $headers)
{
    $envelope = json_decode((string) $raw, true);

    if (!is_array($envelope) || !isset($envelope['Type']) || !is_string($envelope['Type'])) {
        return false;
    }

    $sns_id = isset($envelope['MessageId']) ? (string) $envelope['MessageId'] : '';

    if ($envelope['Type'] === 'SubscriptionConfirmation') {
        $url = isset($envelope['SubscribeURL']) ? (string) $envelope['SubscribeURL'] : '';
        $host = (string) parse_url($url, PHP_URL_HOST);
        $sns = (strncmp($url, 'https://', 8) === 0) && preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', $host);

        return $sns ? array(array('id' => 'ses:' . $sns_id, 'kind' => 'confirm', 'address' => '', 'message_id' => '', 'url' => $url)) : array();
    }

    $message = isset($envelope['Message']) ? json_decode((string) $envelope['Message'], true) : null;

    if (($envelope['Type'] !== 'Notification') || !is_array($message)) {
        return ($envelope['Type'] === 'Notification') ? false : array();
    }

    // Identity notifications name it notificationType, event publishing eventType.
    $type = isset($message['notificationType']) ? (string) $message['notificationType'] : (isset($message['eventType']) ? (string) $message['eventType'] : '');
    $message_id = isset($message['mail']['messageId']) ? (string) $message['mail']['messageId'] : '';
    $kind = '';
    $recipients = array();

    if (($type === 'Bounce') && isset($message['bounce']) && is_array($message['bounce'])) {
        $kind = (isset($message['bounce']['bounceType']) && ($message['bounce']['bounceType'] === 'Permanent')) ? 'hard' : 'soft';

        foreach ((isset($message['bounce']['bouncedRecipients']) ? (array) $message['bounce']['bouncedRecipients'] : array()) as $recipient) {
            $recipients[] = isset($recipient['emailAddress']) ? $recipient['emailAddress'] : '';
        }
    } elseif (($type === 'Complaint') && isset($message['complaint']) && is_array($message['complaint'])) {
        $kind = 'complaint';

        foreach ((isset($message['complaint']['complainedRecipients']) ? (array) $message['complaint']['complainedRecipients'] : array()) as $recipient) {
            $recipients[] = isset($recipient['emailAddress']) ? $recipient['emailAddress'] : '';
        }
    } elseif (($type === 'Delivery') && isset($message['delivery']['recipients'])) {
        $kind = 'delivered';
        $recipients = (array) $message['delivery']['recipients'];
    }

    $events = array();

    foreach ($recipients as $address) {
        if (is_string($address) && ($address !== '')) {
            $events[] = array('id' => 'ses:' . $sns_id . ':' . $address, 'kind' => $kind, 'address' => $address, 'message_id' => $message_id);
        }
    }

    return $events;
}
