<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: PHPMailer and email(): comment and form notifications, automatic campaigns, recipients, HTML/text conversion.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Manual includes for PHPMailer 6.x (no Composer)
require_once PG_FUNCTIONS_DIR . '/includes/phpmailer/src/Exception.php';
require_once PG_FUNCTIONS_DIR . '/includes/phpmailer/src/PHPMailer.php';
require_once PG_FUNCTIONS_DIR . '/includes/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function convert_html_to_text($html)
{
    // remove white-space from beginning and end
    $content = trim($html);
    // replace various HTML elements with plain text
    $content = preg_replace("/\r/", '', $content);
    $content = preg_replace("/[\n\t]+/", ' ', $content);
    $content = preg_replace('/<title[^>]*>.*?<\/title>/i', '', $content);
    $content = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $content);
    $content = preg_replace('/<style[^>]*>.*?<\/style>/i', '', $content);
    if (!function_exists('convert_html_to_text_heading_big_callback')) {
        function convert_html_to_text_heading_big_callback($matches)
        {
            return "\n\n" . mb_strtoupper($matches[1]) . "\n\n";
        }
    }
    $content = preg_replace_callback('/<h[123][^>]*>(.+?)<\/h[123]>/i', 'convert_html_to_text_heading_big_callback', $content);
    if (!function_exists('convert_html_to_text_heading_small_callback')) {
        function convert_html_to_text_heading_small_callback($matches)
        {
            return "\n\n" . ucwords($matches[1]) . "\n\n";
        }
    }
    $content = preg_replace_callback('/<h[456][^>]*>(.+?)<\/h[456]>/i', 'convert_html_to_text_heading_small_callback', $content);
    $content = preg_replace('/<p[^>]*>/i', "\n\n", $content);
    $content = preg_replace('/<br[^>]*>/i', "\n", $content);
    if (!function_exists('convert_html_to_text_bold_callback')) {
        function convert_html_to_text_bold_callback($matches)
        {
            return mb_strtoupper($matches[1]);
        }
    }
    $content = preg_replace_callback('/<b[^>]*>(.+?)<\/b>/i', 'convert_html_to_text_bold_callback', $content);
    if (!function_exists('convert_html_to_text_strong_callback')) {
        function convert_html_to_text_strong_callback($matches)
        {
            return mb_strtoupper($matches[1]);
        }
    }
    $content = preg_replace_callback('/<strong[^>]*>(.+?)<\/strong>/i', 'convert_html_to_text_strong_callback', $content);
    $content = preg_replace('/<i[^>]*>(.+?)<\/i>/i', '_\\1_', $content);
    $content = preg_replace('/<em[^>]*>(.+?)<\/em>/i', '_\\1_', $content);
    $content = preg_replace('/(<ul[^>]*>|<\/ul>)/i', "\n\n", $content);
    $content = preg_replace('/(<ol[^>]*>|<\/ol>)/i', "\n\n", $content);
    $content = preg_replace('/<link[^>]*>/i', '', $content);
    $content = preg_replace('/<li[^>]*>/i', "\n*", $content);
    $content = preg_replace('/<a href="([^"]+)"[^>]*>(.+?)<\/a>/i', '\\2 (\\1)', $content);
    $content = preg_replace('/<hr[^>]*>/i', "\n-------------------------\n", $content);
    $content = preg_replace('/(<table[^>]*>|<\/table>)/i', "\n\n", $content);
    $content = preg_replace('/(<tr[^>]*>|<\/tr>)/i', "\n", $content);
    $content = preg_replace('/<td[^>]*>(.+?)<\/td>/i', "\t\t\\1\n", $content);
    if (!function_exists('convert_html_to_text_table_heading_callback')) {
        function convert_html_to_text_table_heading_callback($matches)
        {
            return "\t\t" . mb_strtoupper($matches[1]) . "\n";
        }
    }
    $content = preg_replace_callback('/<th[^>]*>(.+?)<\/th>/i', 'convert_html_to_text_table_heading_callback', $content);
    $content = preg_replace('/&nbsp;/i', ' ', $content);
    $content = preg_replace('/&quot;/i', '"', $content);
    $content = preg_replace('/&gt;/i', '>', $content);
    $content = preg_replace('/&lt;/i', '<', $content);
    $content = preg_replace('/&amp;/i', '&', $content);
    $content = preg_replace('/&copy;/i', '(c)', $content);
    $content = preg_replace('/&trade;/i', '(tm)', $content);
    $content = preg_replace('/&#8220;/', '"', $content);
    $content = preg_replace('/&#8221;/', '"', $content);
    $content = preg_replace('/&#8211;/', '-', $content);
    $content = preg_replace('/&#8217;/', "'", $content);
    $content = preg_replace('/&#38;/', '&', $content);
    $content = preg_replace('/&#169;/', '(c)', $content);
    $content = preg_replace('/&#8482;/', '(tm)', $content);
    $content = preg_replace('/&#151;/', '--', $content);
    $content = preg_replace('/&#147;/', '"', $content);
    $content = preg_replace('/&#148;/', '"', $content);
    $content = preg_replace('/&#149;/', '*', $content);
    $content = preg_replace('/&reg;/i', '(R)', $content);
    $content = preg_replace('/&bull;/i', '*', $content);
    $content = preg_replace('/&[&;]+;/i', '', $content);
    // remove any remaining tags
    $content = strip_tags($content);
    // do not allow more than 2 empty lines in a row
    $content = preg_replace("/\n\s+\n/", "\n", $content);
    $content = preg_replace("/[\n]{3,}/", "\n\n", $content);
    // replace multiple spaces with a single space
    $content = preg_replace("/ +/", ' ', $content);
    // wrap content
    $content = wordwrap($content);
    return $content;
}
function convert_text_to_html($content)
{
    // get all URL's so that we can later replace them with links
    preg_match_all('/(((http|https|ftp):\/\/)(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|"|\'|:|\<|$|\.\s)/i', $content, $matches);
    $urls = $matches[1];
    // if there is at least one URL, then convert all URL's to placeholders
    if (count($urls) > 0) {
        $content = preg_replace('/(((http|https|ftp):\/\/)(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|"|\'|:|\<|$|\.\s)/i', '^^^placeholder^^^' . "$5", $content);
    }
    // prepare the content for HTML
    $content = h($content);
    // loop through the URL's in order to convert them to links
    foreach ($urls as $url) {
        $output_url = h($url);
        // convert URL to link
        $content = preg_replace('/' . preg_quote('^^^placeholder^^^') . '/', '<a href="' . $output_url . '" target="_blank" rel="nofollow">' . $output_url . '</a>', $content, 1);
    }
    // convert all new lines to line breaks
    $content = nl2br($content);
    return $content;
}


// store list of recipients from address book in session
function initialize_recipients()
{
    // If recipients have not already been initialized, and user is logged in and not ghosting,
    // store recipients from address book in session.
    if (empty($_SESSION['ecommerce']['initialized_recipients']) and USER_LOGGED_IN and empty($_SESSION['software']['ghost'])) {
        // get user id
        $query = "SELECT user_id FROM user WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $user_id = $row['user_id'];
        // get all recipients in address book
        $query = "SELECT ship_to_name FROM address_book WHERE user = '$user_id' ORDER BY ship_to_name";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // loop through all recipients in address book to make sure recipient is in ship to selection
        while ($row = mysqli_fetch_assoc($result)) {
            $recipient_in_session = false;
            // if there are recipients in the session
            if (!empty($_SESSION['ecommerce']['recipients'])) {
                // loop through all recipients
                foreach ($_SESSION['ecommerce']['recipients'] as $recipient) {
                    // if recipient from address book is already in session, remember that
                    if (mb_strtolower($recipient) == mb_strtolower($row['ship_to_name'])) {
                        $recipient_in_session = true;
                        break;
                    }
                }
            }
            // if recipient was not found in the session and recipient is not the
            // buyer's own name - stored translated, so both spellings count -
            // add recipient to session
            if (($recipient_in_session == false) && (mb_strtolower($row['ship_to_name']) != 'myself') && ($row['ship_to_name'] != lang('myself'))) {
                $_SESSION['ecommerce']['recipients'][] = $row['ship_to_name'];
            }
        }
        // remember that recipients have been initialized
        $_SESSION['ecommerce']['initialized_recipients'] = true;
    }
}
// add a recipient to session so that ship to name appears in ship to pick lists
function add_recipient($ship_to_name)
{
    // if shipping is on and recipient mode is multi-recipient and ship to name is
    // not the buyer's own name - stored translated, so both spellings count - continue
    if ((ECOMMERCE_SHIPPING == true) && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient') && ($ship_to_name != 'myself') && ($ship_to_name != lang('myself'))) {
        // initialize variable that will be used to determine if recipient is already in session
        $recipient_in_session = false;
        // if recipients have been stored in session
        if (!empty($_SESSION['ecommerce']['recipients'])) {
            // loop through all recipients
            foreach ($_SESSION['ecommerce']['recipients'] as $recipient) {
                // if recipient from address book is already in session, remember that
                if (mb_strtolower($recipient) == mb_strtolower($ship_to_name)) {
                    $recipient_in_session = true;
                    break;
                }
            }
        }
        // if recipient is not already in the session, add recipient to session
        if ($recipient_in_session == false) {
            // if recipients array in session is not set, create it
            if (!isset($_SESSION['ecommerce']['recipients'])) {
                $_SESSION['ecommerce']['recipients'] = array();
            }
            $_SESSION['ecommerce']['recipients'][] = $ship_to_name;
        }
    }
}

// Whether the scheduled e-mail campaign job is switched on in config.php.
// The define is written as a real boolean, but older installers and settings
// screens stored it as the quoted strings 'true' / 'false'; the string 'false'
// is truthy in PHP, so every consumer goes through here instead of testing the
// constant directly.
function email_campaign_job_enabled()
{
    if (defined('EMAIL_CAMPAIGN_JOB') == false) {
        return false;
    }

    $value = EMAIL_CAMPAIGN_JOB;

    if (is_string($value) == true) {
        $value = strtolower(trim($value));
        return (($value === 'true') || ($value === '1'));
    }

    return ($value == true);
}

function get_email_campaign_status_name($status)
{
    switch ($status) {
        case 'ready':
            if (email_campaign_job_enabled()) {
                return lang('Scheduled');
            } else {
                return lang('Ready to Send');
            }
            break;
        case 'paused':
            return lang('Paused');
            break;
        case 'cancelled':
            return lang('Cancelled');
            break;
        case 'complete':
            return lang('Complete');
            break;
    }
}
// This function is currently used when a comment is added
// by the product submit form feature.
// It is not currently used when a comment is added manually,
// because that area of the code is a little different,
// however it could be used for that also in the future.
function send_comment_email_to_administrators($comment_id)
{
    // Get comment info.
    $comment = db_item("SELECT

            id,

            page_id,

            item_id,

            item_type,

            name,

            message

        FROM comments

        WHERE id = '" . escape($comment_id) . "'");
    // Get info for the page that the comment is connected to.
    $page = db_item("SELECT

            page_id AS id,

            page_name AS name,

            page_type AS type,

            comments_label,

            comments_administrator_email_to_email_address,

            comments_administrator_email_subject,

            comments_administrator_email_conditional_administrators

        FROM page

        WHERE page_id = '" . $comment['page_id'] . "'");
    // Prepare to e-mail addresses for the administrator e-mail.
    $administrator_email_addresses = array();
    // If there is a to e-mail address, then add it to the array.
    if ($page['comments_administrator_email_to_email_address'] != '') {
        $administrator_email_addresses[] = $page['comments_administrator_email_to_email_address'];
    }
    // If the comment is connected to a submitted form,
    // then get additional administrator e-mail addresses.
    if ($comment['item_type'] == 'submitted_form') {
        // If e-mailing conditional administrators is enabled,
        // then check if there are any to add.
        if ($page['comments_administrator_email_conditional_administrators']) {
            $custom_form_page_id = db_value("SELECT page_id FROM forms WHERE id = '" . $comment['item_id'] . "'");
            // Get conditional administrators
            $conditional_administrators = db_values("SELECT form_field_options.email_address

                FROM form_field_options

                LEFT JOIN form_data ON form_field_options.form_field_id = form_data.form_field_id

                WHERE

                    (form_field_options.page_id = '$custom_form_page_id')

                    AND (form_data.form_id = '" . $comment['item_id'] . "')

                    AND (form_field_options.email_address != '')

                    AND (form_data.data = form_field_options.value)");
            // Loop through the conditional administrators in order to add them.
            foreach ($conditional_administrators as $conditional_administrator) {
                // We support multiple conditional admin email addresses, separated by comma,
                // (e.g. ^^example1@example.com,example2@example.com^^), so deal with that.
                $conditional_email_addresses = explode(',', $conditional_administrator);
                foreach ($conditional_email_addresses as $conditional_email_address) {
                    $conditional_email_address = trim($conditional_email_address);
                    // If this e-mail address has not already been added, then add it.
                    if (in_array($conditional_email_address, $administrator_email_addresses) == false) {
                        $administrator_email_addresses[] = $conditional_email_address;
                    }
                }
            }
        }
        $form_editor_email_address = db_value("SELECT user.user_email as form_editor_email_address

            FROM forms

            LEFT JOIN user ON forms.form_editor_user_id = user.user_id

            WHERE forms.id = '" . $comment['item_id'] . "'");
        // If a form editor e-mail address was found and it has not already been added, then add it.
        if (($form_editor_email_address != '') && (in_array($form_editor_email_address, $administrator_email_addresses) == false)) {
            $administrator_email_addresses[] = $form_editor_email_address;
        }
    }
    // If there is at least one administrator email address to e-mail,
    // then send emails to administrators.
    if (count($administrator_email_addresses) > 0) {
        $comment_label = get_comment_label(array(
            'label' => $page['comments_label']
        ));
        $comment_label_lowercase = mb_strtolower($comment_label);
        // If the administrator e-mail subject is not blank,
        // then prepare it for output and set it to the e-mail subject
        if ($page['comments_administrator_email_subject'] != '') {
            // If the item is a submitted form, then replace variables
            // in subject with submitted form data
            if ($comment['item_type'] == 'submitted_form') {
                $subject = get_variable_submitted_form_data_for_content($page['id'], $comment['item_id'], $page['comments_administrator_email_subject'], $prepare_for_html = false);
                // Otherwise the item is not a submitted form, so just use the subject.
            } else {
                $subject = $page['comments_administrator_email_subject'];
            }
            // Otherwise, use the default subject.
        } else {
            $subject = lang(array('string' => 'A {var:1} has been added and published.', 'vars' => $comment_label_lowercase));
        }
        $body = lang(array('string' => 'The following {var:1} has been added and published', 'vars' => $comment_label_lowercase)) . ':' . "\n" . "\n" . lang('Display Name') . ': ' . $comment['name'] . "\n" . "\n" . $comment_label . ': ' . $comment['message'] . "\n";
        $query_string = get_query_string_for_page_url($page['type'], $comment['item_id'], $comment['item_type']);
        // If this is the first item that is being added to the query string, then add question mark.
        if (mb_strpos($query_string, '?') === false) {
            $query_string .= '?';
            // Otherwise this is not the first item that is being added to the query string, so add ampersand.
        } else {
            $query_string .= '&';
        }
        $query_string .= 'comments=all';
        // Mailed links use the configured hostname: comments arrive from anonymous
        // visitors, and a spoofed Host header must not choose where this points.
        $body .= "\n" . lang(array('string' => 'The {var:1} appears at the link below.', 'vars' => $comment_label_lowercase)) . "\n" . "\n" . URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($page['name']) . $query_string . '#c-' . $comment['id'];
        email(array(
            'to' => $administrator_email_addresses,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject' => $subject,
            'body' => $body
        ));
    }
}
function send_comment_email_to_custom_form_submitter($comment_id)
{
    // get comment data
    $query = "SELECT 

            comments.page_id,

            comments.item_id,

            comments.name,

            comments.message,

            files.name as file_name,

            files.size as file_size,

            comments.created_timestamp

        FROM comments

        LEFT JOIN files ON comments.file_id = files.id

        WHERE comments.id = '" . escape($comment_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $page_id = $row['page_id'];
    $item_id = $row['item_id'];
    $name = $row['name'];
    $message = $row['message'];
    $file_name = $row['file_name'];
    $file_size = $row['file_size'];
    $created_timestamp = $row['created_timestamp'];
    // get page properties
    $query = "SELECT

            page.page_type,

            page.comments_submitter_email_page_id,

            page.comments_submitter_email_subject,

            custom_form_pages.submitter_email_from_email_address

        FROM page

        LEFT JOIN form_item_view_pages ON

            (page.page_id = form_item_view_pages.page_id)

            AND (form_item_view_pages.collection = 'a')

        LEFT JOIN custom_form_pages ON form_item_view_pages.custom_form_page_id = custom_form_pages.page_id

        WHERE page.page_id = '$page_id'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $page_type = $row['page_type'];
    $submitter_email_from_email_address = $row['submitter_email_from_email_address'];
    $comments_submitter_email_page_id = $row['comments_submitter_email_page_id'];
    $comments_submitter_email_subject = $row['comments_submitter_email_subject'];
    // get forms reference code
    $query = "SELECT reference_code FROM forms WHERE id = '$item_id'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $reference_code = $row['reference_code'];
    $email_address = get_submitter_email_address($item_id);
    // if there is a page to send and an e-mail address to e-mail, e-mail page
    if ($comments_submitter_email_page_id && $email_address) {
        if ($submitter_email_from_email_address) {
            $from_email_address = $submitter_email_from_email_address;
        } else {
            $from_email_address = EMAIL_ADDRESS;
        }
        // check the subject line for variable data and replace any variables with content from the submitted form
        $comments_submitter_email_subject = get_variable_submitted_form_data_for_content($page_id, $item_id, $comments_submitter_email_subject, $prepare_for_html = false);
        $body = '';
        $extra_system_content = '';
        // if name is blank then output anonymous
        if ($name == '') {
            $name = lang('Anonymous');
        }
        $output_file_attachment = '';
        // if there is a file attachment, then output it
        if ($file_name != '') {
            // we are using a separate link for the image and the file name because we don't want an underline on the image and we don't want to have to update all themes with new CSS
            $output_file_attachment = '<div class="software_attachment" style="margin-top: 1.5em"><a href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank" style="background: none; padding: 0"><img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/icon_attachment.png" width="16" height="16" alt="attachment" title="" border="0" style="padding-right: .5em; vertical-align: middle" /></a><a href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank">' . h($file_name) . '</a> (' . convert_bytes_to_string($file_size) . ')</div>';
        }
        // set body message
        $extra_system_content = '<div class="comment">

                <div class="name_line"><span class="added_by">' . lang('Added by') . '</span> <span class="name">' . h($name) . '</span></div>

                <div class="date_and_time">' . get_absolute_time(array(
                'timestamp' => $created_timestamp,
                'size' => 'long',
                'timezone_type' => 'site'
            )) . '</div>

                <br />

                <div class="message">' . convert_text_to_html($message) . '</div>

                ' . $output_file_attachment . '

            </div>

            <div style="margin-top: 1em;"><a class="software_input_submit_primary reply_button" href="' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . get_page_name($page_id) . '?r=' . $reference_code . '&comments=all#c-' . $comment_id) . '">' . lang('View or Reply') . '</a></div>';
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        $body = get_page_content($comments_submitter_email_page_id, $system_content = '', $extra_system_content, $mode = 'preview', $email = true);
        email(array(
            'to' => $email_address,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => $from_email_address,
            'subject' => $comments_submitter_email_subject,
            'format' => 'html',
            'body' => $body
        ));
    }
}
function send_comment_email_to_watchers($comment_id)
{
    // get comment and page info (the email_page join is used to determine if the page still exists)
    $query = "SELECT 

            comments.page_id,

            comments.item_id,

            comments.item_type,

            comments.name,

            comments.message,

            files.name as file_name,

            files.size as file_size,

            comments.created_timestamp,

            page.page_name,

            page.page_type,

            page.comments_watcher_email_page_id,

            page.comments_watcher_email_subject,

            email_page.page_id as email_page_id

        FROM comments

        LEFT JOIN files ON comments.file_id = files.id

        LEFT JOIN page ON comments.page_id = page.page_id

        LEFT JOIN page AS email_page ON page.comments_watcher_email_page_id = email_page.page_id

        WHERE comments.id = '" . escape($comment_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $page_id = $row['page_id'];
    $item_id = $row['item_id'];
    $item_type = $row['item_type'];
    $name = $row['name'];
    $message = $row['message'];
    $file_name = $row['file_name'];
    $file_size = $row['file_size'];
    $created_timestamp = $row['created_timestamp'];
    $page_name = $row['page_name'];
    $page_type = $row['page_type'];
    $comments_watcher_email_page_id = $row['comments_watcher_email_page_id'];
    $comments_watcher_email_subject = $row['comments_watcher_email_subject'];
    $email_page_id = $row['email_page_id'];
    // if the page to be e-mailed still exists, then continue to e-mail watchers
    if ($email_page_id != '') {
        // get all watchers for this page and etc.
        $query = "SELECT

                watchers.email_address AS watcher_email_address,

                user.user_email AS user_email_address

            FROM watchers

            LEFT JOIN user ON watchers.user_id = user.user_id

            WHERE

                (watchers.page_id = '" . escape($page_id) . "')

                AND (watchers.item_id = '" . escape($item_id) . "')

                AND (watchers.item_type = '" . escape($item_type) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $watchers = array();
        // loop through all watchers in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $watchers[] = $row;
        }
        // if the page that the comment was added to is a form item view, then check the subject line for variable data and replace any variables with content from the submitted form
        if ($page_type == 'form item view') {
            $comments_watcher_email_subject = get_variable_submitted_form_data_for_content($page_id, $item_id, $comments_watcher_email_subject, $prepare_for_html = false);
        }
        // if name is blank then output anonymous
        if ($name == '') {
            $name = lang('Anonymous');
        }
        $output_file_attachment = '';
        // if there is a file attachment, then output it
        if ($file_name != '') {
            // we are using a separate link for the image and the file name because we don't want an underline on the image and we don't want to have to update all themes with new CSS
            $output_file_attachment = '<div class="software_attachment" style="margin-top: 1.5em"><a href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank" style="background: none; padding: 0"><img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/icon_attachment.png" width="16" height="16" alt="attachment" title="" border="0" style="padding-right: .5em; vertical-align: middle" /></a><a href="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" target="_blank">' . h($file_name) . '</a> (' . convert_bytes_to_string($file_size) . ')</div>';
        }
        $query_string = get_query_string_for_page_url($page_type, $item_id, $item_type);
        // If this is the first item that is being added to the query string, then add question mark.
        if (mb_strpos($query_string, '?') === false) {
            $query_string .= '?';
            // Otherwise this is not the first item that is being added to the query string, so add ampersand.
        } else {
            $query_string .= '&';
        }
        // Add comments parameter now.
        $query_string .= 'comments=all';
        // set body message
        $extra_system_content = '<div class="comment">

                <div class="name_line"><span class="added_by">' . lang('Added by') . '</span> <span class="name">' . h($name) . '</span></div>

                <div class="date_and_time">' . get_absolute_time(array(
                'timestamp' => $created_timestamp,
                'size' => 'long',
                'timezone_type' => 'site'
            )) . '</div>

                <br />

                <div class="message">' . convert_text_to_html($message) . '</div>

                ' . $output_file_attachment . '

            </div>

            <div style="margin-top: 1em;"><a class="software_input_submit_primary reply_button" href="' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . $page_name . $query_string . '#c-' . $comment_id) . '">' . lang('View or Reply') . '</a></div>';
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        $body = get_page_content($comments_watcher_email_page_id, $system_content = '', $extra_system_content, $mode = 'preview', $email = true);
        // loop through all watchers in order to e-mail each one
        foreach ($watchers as $watcher) {
            // If this watcher is connected to a user, then use the user's e-mail address.
            if ($watcher['user_email_address'] != '') {
                $to = $watcher['user_email_address'];
                // Otherwise the watcher is not connected to a user, so use the static e-mail address for the watcher.
            } else {
                $to = $watcher['watcher_email_address'];
            }
            email(array(
                'to' => $to,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject' => $comments_watcher_email_subject,
                'format' => 'html',
                'body' => $body
            ));
        }
    }
}
// Create function that can be used to build the options for a pick list of email campaign profiles.
function get_email_campaign_profile_options()
{
    $email_campaign_profile_options = array();
    $email_campaign_profile_options['-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('campaign profile')))) . '-'] = '';
    // Get email campaign profiles in order to prepare options.
    $email_campaign_profiles = db_items("SELECT

            id,

            name,

            created_user_id

        FROM email_campaign_profiles

        ORDER BY name ASC");
    // Loop through the profiles in order to prepare options.
    foreach ($email_campaign_profiles as $email_campaign_profile) {
        // If this user has a role greater than a user role or if this user is the creator,
        // then add this profile as an option.
        if ((USER_ROLE < 3) || (USER_ID == $email_campaign_profile['created_user_id'])) {
            $email_campaign_profile_options[h($email_campaign_profile['name'])] = $email_campaign_profile['id'];
        }
    }
    return $email_campaign_profile_options;
}
// Create function that is used to see if auto email campaigns need to be created after a specific action has occurred
// and then create them if necessary.
// Properties:
// action: "calendar_event_reserved", "custom_form_submitted", "email_campaign_sent", "order_abandoned", "order_completed", "order_shipped", "product_ordered"
// action_item_id: The id of item that is related to the action (e.g. the calendar event id)
// calendar_event_recurrence_number
// order_id: Used by order_abandoned action.
// contact_id: The ID of the contact that generated the action.
// email_address: You can pass an email address for recipient, instead of the contact above.
// If you pass both, then the contact email address will be used.
// fields: An array of mail-merge fields (e.g. so ^^order_number^^ can be replaced in subject/body).
// data: An array of misc data that will be passed to get_page_content(), so custom PHP on page can
// use the data.
function create_auto_email_campaigns($properties)
{
    // Everything except 'action' is optional (see the notes above), so fill in whatever the
    // caller left out.
    $properties = $properties + array(
        'action_item_id'                   => '',
        'calendar_event_recurrence_number' => '',
        'order_id'                         => '',
        'contact_id'                       => '',
        'email_address'                    => '',
        'fields'                           => array(),
        'data'                             => array());

    $action = $properties['action'];
    $action_item_id = $properties['action_item_id'];
    $calendar_event_recurrence_number = $properties['calendar_event_recurrence_number'];
    $order_id = $properties['order_id'];
    $contact_id = $properties['contact_id'];
    $email_address = $properties['email_address'];
    $fields = $properties['fields'];
    $data = $properties['data'];
    // If a contact id was not passed and there is no email address, then abort.
    if (!$contact_id and !$email_address) {
        return;
    }
    $contact = array();
    // If a contact id was passed, then get contact info in order to determine if contact is
    // opted-out and to get e-mail address.
    if ($contact_id) {
        $contact = db_item("SELECT
                id,
                opt_in,
                email_address
            FROM contacts
            WHERE id = '" . e($contact_id) . "'");
    }
    // If a contact email was found, then use that email address for recipient.
    if (!empty($contact['email_address'])) {
        $email_address = $contact['email_address'];
        // Otherwise if no email address was passed either, then return, because we have no one to email.
    } else if (!$email_address) {
        return;
    }
    // Get all enabled email campaign profiles for this action and action item.
    $email_campaign_profiles = db_items("SELECT

            email_campaign_profiles.id,

            email_campaign_profiles.name,

            email_campaign_profiles.subject,

            email_campaign_profiles.format,

            email_campaign_profiles.body,

            email_campaign_profiles.page_id,

            page.page_name,

            email_campaign_profiles.from_name,

            email_campaign_profiles.from_email_address,

            email_campaign_profiles.reply_email_address,

            email_campaign_profiles.bcc_email_address,

            email_campaign_profiles.schedule_time,

            email_campaign_profiles.schedule_length,

            email_campaign_profiles.schedule_unit,

            email_campaign_profiles.schedule_period,

            email_campaign_profiles.schedule_base,

            email_campaign_profiles.purpose,

            email_campaign_profiles.created_user_id

        FROM email_campaign_profiles

        LEFT JOIN page ON email_campaign_profiles.page_id = page.page_id

        WHERE

            (email_campaign_profiles.enabled = '1')

            AND (email_campaign_profiles.action = '$action')

            AND (email_campaign_profiles.action_item_id = '" . e($action_item_id) . "')

        ORDER BY email_campaign_profiles.name ASC");
    // Loop through the profiles in order to create auto email campaigns.
    foreach ($email_campaign_profiles as $email_campaign_profile) {
        // If the purpose is commercial and there is a contact email and the contact is opted-out,
        // then we don't want to send email to recipient, so skip to the next profile.
        if ($email_campaign_profile['purpose'] == 'commercial' and $contact['email_address'] and $contact['opt_in'] != 1) {
            continue;
        }
        $subject = $email_campaign_profile['subject'];
        // If there are mail-merge fields, then replace in subject.
        if ($fields) {
            $subject = replace_variables(array(
                'content' => $subject,
                'fields' => $fields,
                'format' => 'plain_text'
            ));
        }
        // If plain text was selected for the format, then store body in variable
        // and clear page id so that we don't store it with the e-mail campaign.
        if ($email_campaign_profile['format'] == 'plain_text') {
            $body = $email_campaign_profile['body'];
            $email_campaign_profile['page_id'] = '';
            // Otherwise HTML was selected for the format, so prepare body for that format.
        } else {
            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
            // Get html for page.
            $body = get_page_content($email_campaign_profile['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true, $data);
            // Find if there is a base tag in the HTML.
            $base_in_html = preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](?:http:\/\/|https:\/\/|ftp:\/\/).*?["\']/is', $body);
            // If there is not a base tag in the HTML, add base tag and convert relative links to absolute links.
            if (!$base_in_html) {
                $base = '<head>' . "\n" . '<base href="' . URL_SCHEME . HOSTNAME_SETTING . '/" />';
                $body = preg_replace('/<head>/i', $base, $body);
                // Change relative URLs to absolute URLs for links.
                $body = preg_replace('/(<\s*a\s+[^>]*href\s*=\s*["\'])(?!ftp:\/\/|https:\/\/|mailto:|http:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
                // Change relative URLs to absolute URLs for images.
                $body = preg_replace('/(<\s*img\s+[^>]*src\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
                // Change relative URLs to absolute URLs for CSS background images.
                $body = preg_replace('/(background-image\s*:\s*url\s*\(\s*(?:"|\'|))(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?(?:"|\'|).*?\))/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
                // Change relative URLs to absolute URLs for HTML background images.
                $body = preg_replace('/(background\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'])/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
            }
            $commercial_content = '';
            // If the campaign is commercial, then add tracking codes to links and prepare
            // commercial content for footer.
            if ($email_campaign_profile['purpose'] == 'commercial') {
                // Get all links in order to add tracking codes to links.
                preg_match_all('/(<\s*a\s+[^>]*href\s*=\s*["\']\s*)(.*?)(\s*["\'].*?>)/is', $body, $links);
                // If the date format is month and then day, then use that format.
                if (DATE_FORMAT == 'month_day') {
                    $month_and_day_format = 'm/d';
                    // Otherwise the date format is day and then month, so use that format.
                } else {
                    $month_and_day_format = 'd/m';
                }
                // Set tracking code to contain the page name and date and time.
                $tracking_code = $email_campaign_profile['page_name'] . '_' . date($month_and_day_format . '/Y_h:i_A');
                // loop through all links in order to add tracking codes to links
                foreach ($links[0] as $key => $link) {
                    // set the URL that was found in the link
                    $url = $links[2][$key];
                    // remove new lines from the link
                    $url = str_replace("\r\n", '', $url);
                    $url = str_replace("\n", '', $url);
                    $url_parts = @parse_url($url);
                    // if the URL is valid
                    // and if there is not a scheme or the scheme is http or https
                    // and if there is not a hostname or the hostname is this site's hostname
                    // and if there is not already a tracking code in the URL
                    // then continue with adding tracking code to URL
                    if (($url_parts != false) && ((isset($url_parts['scheme']) == false) || ($url_parts['scheme'] == '') || (mb_strtolower($url_parts['scheme']) == 'http') || (mb_strtolower($url_parts['scheme']) == 'https')) && ((isset($url_parts['host']) == false) || ($url_parts['host'] == '') || (mb_strtolower(str_replace('www.', '', $url_parts['host'])) == mb_strtolower(str_replace('www.', '', HOSTNAME_SETTING)))) && ((isset($url_parts['query']) == false) || ($url_parts['query'] == '') || mb_strpos($url_parts['query'], 't=') === false)) {
                        $new_url = '';
                        // if there is a scheme, then add scheme to new URL
                        if ((isset($url_parts['scheme']) == true) && ($url_parts['scheme'] != '')) {
                            $new_url .= $url_parts['scheme'] . '://';
                        }
                        // if there is a hostname, then add hostname to new URL
                        if ((isset($url_parts['host']) == true) && ($url_parts['host'] != '')) {
                            $new_url .= $url_parts['host'];
                        }
                        // if there is a path, then add path to new URL
                        if ((isset($url_parts['path']) == true) && ($url_parts['path'] != '')) {
                            $new_url .= $url_parts['path'];
                        }
                        $new_url .= '?';
                        // if there is a query string, then add query string and ampersand to new URL
                        if ((isset($url_parts['query']) == true) && ($url_parts['query'] != '')) {
                            $new_url .= $url_parts['query'] . '&amp;';
                        }
                        $new_url .= 't=' . h(urlencode($tracking_code));
                        // if there is a bookmark, then add bookmark to the new URL
                        if ((isset($url_parts['fragment']) == true) && ($url_parts['fragment'] != '')) {
                            $new_url .= '#' . $url_parts['fragment'];
                        }
                        $entire_link = $links[0][$key];
                        $link_start = $links[1][$key];
                        $link_end = $links[3][$key];
                        // replace the link with the new link
                        $body = str_replace($entire_link, $link_start . $new_url . $link_end, $body);
                    }
                }
                $commercial_content = h(ORGANIZATION_NAME) . '

                    ' . h(ORGANIZATION_ADDRESS_1) . '

                    ' . h(ORGANIZATION_ADDRESS_2) . '

                    ' . h(ORGANIZATION_CITY) . ' ' . h(ORGANIZATION_STATE) . ' ' . h(ORGANIZATION_ZIP_CODE) . ' ' . h(ORGANIZATION_COUNTRY) . '<br>';
                // If there is a contact, then include email preferences info.
                if ($contact) {
                    $email_preferences_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/email_preferences.php?id=<email_address_id></email_address_id>';
                    $commercial_content .= '<a href="' . $email_preferences_url . '" style="color: #666666">' . lang('Update email preferences') . '</a>' . lang(' or ') . '<a href="' . $email_preferences_url . '" style="color: #666666">' . lang('unsubscribe') . '</a><br>';
                }
            }
            // get URL for page
            $page_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign.php?r=<reference_code></reference_code>';
            $footer = '<div class="software_email_footer" style="font-family: arial; font-size: 11px; color: #666666; text-align: center; background-color: #ffffff; padding: 5px; margin-top: 15px">
				    <a href="' . $page_url . '" style="color: #666666">' . lang('View this email at our site') . '</a><br>
				    ' . $commercial_content . '
				</div>
				</body>';
            $body = preg_replace('/<\/body>/i', $footer, $body);
        }
        // Wrap long lines (RFC 821).
        $body = wordwrap($body, 900, "\n", 1);
        // If there are mail-merge fields, then replace in body.
        if ($fields) {
            $body = replace_variables(array(
                'content' => $body,
                'fields' => $fields,
                'format' => $email_campaign_profile['format']
            ));
        }
        // Prepare start date and time for email campaign.
        // We start with the schedule base and then use the length of the schedule to calculate the start timestamp.
        // The calculated start timestamp goes back or forward at least the amount of the schedule length (e.g. 5 days)
        // and then might go back more (for "before") or forward more (for "after") so that the time matches the schedule time override (e.g. 12:00 PM).
        $current_timestamp = time();
        // Set base timestamp differently based on the selected base.
        switch ($email_campaign_profile['schedule_base']) {
            // If the schedule base is the action, then set the base timestamp to the current timestamp.
            case 'action':
                $base_timestamp = $current_timestamp;
                break;
            // If the schedule base is calendar_event_start_time then set the base timestamp to that.
            case 'calendar_event_start_time':
                // Get calendar event info in order to get start date and time.
                $calendar_event = get_calendar_event($action_item_id, $calendar_event_recurrence_number);
                $base_timestamp = strtotime($calendar_event['start_date_and_time']);
                break;
        }
        // Set start timestamp differently based on the period.
        switch ($email_campaign_profile['schedule_period']) {
            case 'before':
                // If there is a schedule length, then calculate the start timestamp.
                if ($email_campaign_profile['schedule_length'] != 0) {
                    $start_timestamp = strtotime('-' . $email_campaign_profile['schedule_length'] . ' ' . $email_campaign_profile['schedule_unit'], $base_timestamp);
                    // Otherwise there is not a schedule length, so just set the start timestamp to the base timestamp.
                } else {
                    $start_timestamp = $base_timestamp;
                }
                // If there is a schedule time, and the calculated start time does not happen to equal that time,
                // then adjust the start timestamp to be at the schedule time.
                if (($email_campaign_profile['schedule_time'] != '00:00:00') && (date('H:i:s', $start_timestamp) != $email_campaign_profile['schedule_time'])) {
                    // If the schedule time comes before the calculated start time, then set the start timestamp
                    // to the schedule time on the same calculated date.
                    if ($email_campaign_profile['schedule_time'] < date('H:i:s', $start_timestamp)) {
                        $start_timestamp = strtotime(date('Y-m-d', $start_timestamp) . ' ' . $email_campaign_profile['schedule_time'], $start_timestamp);
                        // Otherwise the schedule time comes after the calculated start time, so set the start timestamp
                        // to the schedule time on the day before the calculated date.
                    } else {
                        $start_timestamp = strtotime('-1 day', $start_timestamp);
                        $start_timestamp = strtotime(date('Y-m-d', $start_timestamp) . ' ' . $email_campaign_profile['schedule_time'], $start_timestamp);
                    }
                }
                break;
            case 'after':
                // If there is a schedule length, then calculate the start timestamp.
                if ($email_campaign_profile['schedule_length'] != 0) {
                    $start_timestamp = strtotime('+' . $email_campaign_profile['schedule_length'] . ' ' . $email_campaign_profile['schedule_unit'], $base_timestamp);
                    // Otherwise there is not a schedule length, so just set the start timestamp to the base timestamp.
                } else {
                    $start_timestamp = $base_timestamp;
                }
                // If there is a schedule time, and the calculated start time does not happen to equal that time,
                // then adjust the start timestamp to be at the schedule time.
                if (($email_campaign_profile['schedule_time'] != '00:00:00') && (date('H:i:s', $start_timestamp) != $email_campaign_profile['schedule_time'])) {
                    // If the schedule time comes after the calculated start time, then set the start timestamp
                    // to the schedule time on the same calculated date.
                    if ($email_campaign_profile['schedule_time'] > date('H:i:s', $start_timestamp)) {
                        $start_timestamp = strtotime(date('Y-m-d', $start_timestamp) . ' ' . $email_campaign_profile['schedule_time'], $start_timestamp);
                        // Otherwise the schedule time comes before the calculated start time, so set the start timestamp
                        // to the schedule time on the day after the calculated date.
                    } else {
                        $start_timestamp = strtotime('+1 day', $start_timestamp);
                        $start_timestamp = strtotime(date('Y-m-d', $start_timestamp) . ' ' . $email_campaign_profile['schedule_time'], $start_timestamp);
                    }
                }
                break;
        }
        // If the campaign is set to be scheduled for the present or future, then create campaign.
        // We don't create campaigns if the scheduled date and time is in the past.
        if ($start_timestamp >= $current_timestamp) {
            $start_date_and_time = date('Y-m-d H:i:s', $start_timestamp);
            $status = 'ready';
            // Create e-mail campaign.
            db("INSERT INTO email_campaigns (

                    type,

                    email_campaign_profile_id,

                    action,

                    action_item_id,

                    calendar_event_recurrence_number,

                    order_id,

                    from_name,

                    from_email_address,

                    reply_email_address,

                    bcc_email_address,

                    subject,

                    format,

                    body,

                    page_id,

                    start_time,

                    status,

                    purpose,

                    created_user_id,

                    created_timestamp,

                    last_modified_user_id,

                    last_modified_timestamp)

                VALUES (

                    'automatic',

                    '" . $email_campaign_profile['id'] . "',

                    '" . $action . "',

                    '" . $action_item_id . "',

                    '" . $calendar_event_recurrence_number . "',

                    '" . e($order_id) . "',

                    '" . e($email_campaign_profile['from_name']) . "',

                    '" . e($email_campaign_profile['from_email_address']) . "',

                    '" . e($email_campaign_profile['reply_email_address']) . "',

                    '" . e($email_campaign_profile['bcc_email_address']) . "',

                    '" . e($subject) . "',

                    '" . $email_campaign_profile['format'] . "',

                    '" . e($body) . "',

                    '" . $email_campaign_profile['page_id'] . "',

                    '$start_date_and_time',

                    '$status',

                    '" . e($email_campaign_profile['purpose']) . "',

                    '" . $email_campaign_profile['created_user_id'] . "',

                    UNIX_TIMESTAMP(),

                    '" . $email_campaign_profile['created_user_id'] . "',

                    UNIX_TIMESTAMP())");
            $email_campaign_id = mysqli_insert_id(db::$con);
            // Create record for e-mail recipient.
            db("INSERT INTO email_recipients (

                    email_campaign_id,

                    type,

                    email_address,

                    contact_id)

                VALUES (

                    '$email_campaign_id',

                    'automatic',

                    '" . e($email_address) . "',

                    '" . (isset($contact['id']) ? (int) $contact['id'] : 0) . "')");
            // Get user-friendly action name for log message.
            switch ($action) {
                case 'calendar_event_reserved':
                    $log_action = lang('calendar event reserved');
                    break;
                case 'custom_form_submitted':
                    $log_action = lang('custom form submitted');
                    break;
                case 'email_campaign_sent':
                    $log_action = lang('auto campaign sent');
                    break;
                case 'order_abandoned':
                    $log_action = lang('order abandoned');
                    break;
                case 'order_completed':
                    $log_action = lang('order completed');
                    break;
                case 'order_shipped':
                    $log_action = lang('order shipped');
                    break;
                case 'product_ordered':
                    $log_action = lang('product ordered');
                    break;
            }

            log_activity(lang(array('string' => 'auto campaign for profile ({var:1}) was created because of an action ({var:2})', 'vars' => array($email_campaign_profile['name'], $log_action))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
        }
    }
}
// Create function that is used to replace variables with data (e.g. ^^name^^ in email campaigns)
// Properties:
// content: The content that you want to replace variables in.
// fields: An array of fields
//     name: The system name of the field (e.g. "name")
//     data: The content/data that the variable should be replaced with.
//     type: The type of field (e.g. "standard")
// format: "plain_text" or "html"
function replace_variables($properties)
{
    $content = $properties['content'];
    $fields = $properties['fields'];
    $format = $properties['format'];
    // if the content contains variables then replace them with data
    if (mb_strpos($content, '^^') !== false) {
        // Set prepare for html value based on the format.
        switch ($format) {
            case 'plain_text':
                $prepare_for_html = false;
                break;
            case 'html':
                $prepare_for_html = true;
                break;
        }
        // Get all conditionals.
        preg_match_all('/\[\[(.*?\^\^(.*?)\^\^.*?)(\|\|(.*?))?\]\]/si', $content, $conditionals, PREG_SET_ORDER);
        // Loop through all conditionals in order to replace them with either the positive or negative version
        // depending on whether there is data for a field or not.
        foreach ($conditionals as $conditional) {
            $whole_string = $conditional[0];
            $positive_string = $conditional[1];
            $negative_string = $conditional[4];
            $field_name = $conditional[2];
            // Assume that the field name is not valid, until we find out otherwise.
            $field_name_valid = false;
            // Loop through the fields in order to determine if the field name is valid
            // and store field data in $field variable for use below.
            foreach ($fields as $field) {
                // If this field name matches the field name from the variable, then the field name from the variable is valid,
                // so remember that and break out of the loop.
                if ($field['name'] == $field_name) {
                    $field_name_valid = true;
                    break;
                }
            }
            // If the field name is valid, then replace conditional.
            if ($field_name_valid == true) {
                // If there is data to output, use first part of conditional.
                if ($field['data'] != '') {
                    $content = str_replace($whole_string, $positive_string, $content);
                    // Otherwise there is no data to output, so use second part of conditional.
                } else {
                    $content = str_replace($whole_string, $negative_string, $content);
                }
            }
        }
        // Get all variables so they can be replaced with data.
        preg_match_all('/\^\^(.*?)\^\^(%%(.*?)%%)?/i', $content, $variables, PREG_SET_ORDER);
        // Loop through the variables in order to replace them with data
        foreach ($variables as $variable) {
            $whole_string = $variable[0];
            $field_name = $variable[1];
            $date_format = '';
            // If a date format was passed along with the variable, then store that.
            if (isset($variable[3]) == true) {
                $date_format = $variable[3];
                // If the format is html, then that means we have to use unhtmlspecialchars()
                // because the date format might contain HTML entities (e.g. &lt;)
                // and the date() function would convert those characters into date elements.
                if ($format == 'html') {
                    $date_format = unhtmlspecialchars($date_format);
                }
            }
            // Assume that the field name is not valid, until we find out otherwise.
            $field_name_valid = false;
            // Loop through the fields in order to determine if the field name is valid
            // and store field data in $field variable for use below.
            foreach ($fields as $field) {
                // If this field name matches the field name from the variable, then the field name from the variable is valid,
                // so remember that and break out of the loop.
                if ($field['name'] == $field_name) {
                    $field_name_valid = true;
                    break;
                }
            }
            // If the field name is valid, then continue to replace variable with data.
            if ($field_name_valid == true) {
                // Start data off with the field data.
                $data = $field['data'];
                // Update the data so that it is ready for output (e.g. convert dates to fiendly formats).
                $data = prepare_form_data_for_output($data, $field['type'] ?? '', $prepare_for_html, $date_format);
                // Replace the variable with the data.
                // We can't use str_replace() for this because we need to limit the number of replacements to 1
                // in order to prevent bugs where it will replace variables further below that might have date formats.
                $content = preg_replace('/' . preg_quote($whole_string, '/') . '/', addcslashes($data, '\\$'), $content, 1);
            }
        }
    }
    return $content;
}

// These two aliases are file-scoped: from this point to the end of the file an
// unqualified "Exception" resolves to PHPMailer\PHPMailer\Exception rather than the
// global class. Any catch or type hint below that means the global one must spell it
// "\Exception" explicitly.

function email($properties)
{
    $to = isset($properties['to']) ? $properties['to'] : '';
    $to_name = isset($properties['to_name']) ? $properties['to_name'] : '';
    $bcc = isset($properties['bcc']) ? $properties['bcc'] : '';
    $from_name = isset($properties['from_name']) ? $properties['from_name'] : '';
    $from_email_address = isset($properties['from_email_address']) ? $properties['from_email_address'] : '';
    $reply_to = isset($properties['reply_to']) ? $properties['reply_to'] : '';
    $subject = isset($properties['subject']) ? $properties['subject'] : '';
    $format = isset($properties['format']) ? $properties['format'] : 'plain_text';
    $body = isset($properties['body']) ? $properties['body'] : '';
    $type = isset($properties['type']) ? $properties['type'] : 'system';
    $notify_sender = isset($properties['notify_sender']) ? $properties['notify_sender'] : true;

    $mail = new PHPMailer(true);

    // Language setting
    if (lang(array('info' => '')) == 'tr') {
        $mail->setLanguage('tr', PG_FUNCTIONS_DIR . '/includes/phpmailer/language/');
    }

    try {
        $mail->CharSet = 'UTF-8';
        $mail->XMailer = '';
        PHPMailer::$validator = 'html5';

        // SMTP config
        if ($type === 'system' && defined('SYSTEM_SMTP_HOSTNAME') && SYSTEM_SMTP_HOSTNAME !== '') {
            $mail->isSMTP();
            $mail->Host = SYSTEM_SMTP_HOSTNAME;
            if (defined('SYSTEM_SMTP_USERNAME') && SYSTEM_SMTP_USERNAME !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = SYSTEM_SMTP_USERNAME;
                $mail->Password = defined('SYSTEM_SMTP_PASSWORD') ? SYSTEM_SMTP_PASSWORD : '';
            }
            $mail->Port = (defined('SYSTEM_SMTP_PORT') && SYSTEM_SMTP_PORT !== '') ? SYSTEM_SMTP_PORT : 587;
        } elseif ($type === 'campaign' && defined('CAMPAIGN_SMTP_HOSTNAME') && CAMPAIGN_SMTP_HOSTNAME !== '') {
            $mail->isSMTP();
            $mail->Host = CAMPAIGN_SMTP_HOSTNAME;
            if (defined('CAMPAIGN_SMTP_USERNAME') && CAMPAIGN_SMTP_USERNAME !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = CAMPAIGN_SMTP_USERNAME;
                $mail->Password = defined('CAMPAIGN_SMTP_PASSWORD') ? CAMPAIGN_SMTP_PASSWORD : '';
            }
            $mail->Port = (defined('CAMPAIGN_SMTP_PORT') && CAMPAIGN_SMTP_PORT !== '') ? CAMPAIGN_SMTP_PORT : 587;
        } elseif (!function_exists('mail')) {
            log_activity(lang('Email could not be sent because the PHP mail() function is disabled.'));
            return false;
        }

        // Recipients
        if (is_array($to)) {
            foreach ($to as $addr) {
                $mail->addAddress($addr);
            }
        } else {
            $mail->addAddress($to, $to_name);
        }

        if (is_array($bcc)) {
            foreach ($bcc as $bccAddr) {
                $mail->addBCC($bccAddr);
            }
        } elseif (!empty($bcc)) {
            $mail->addBCC($bcc);
        }

        $mail->setFrom($from_email_address, $from_name);
        $mail->Sender = $from_email_address;
        if (!empty($reply_to)) {
            $mail->addReplyTo($reply_to);
        }

        // Subject & body
        $mail->Subject = $subject;
        if ($format === 'html') {
            $body = prepare_page_for_email($body);
            $mail->isHTML(true);
            $mail->Body = $body;
            $mail->AltBody = convert_html_to_text($mail->Body);
            $charset = get_charset($mail->Body);
            $mail->CharSet = $charset ? $charset : 'utf-8';
        } else {
            $mail->Body = $body;
            $mail->CharSet = 'utf-8';
        }

        // DKIM
        if (file_exists(FILE_DIRECTORY_PATH . '/dkim.key')) {
            $mail->DKIM_domain = (defined('DKIM_DOMAIN') && DKIM_DOMAIN) ? DKIM_DOMAIN : substr(strrchr(EMAIL_ADDRESS, '@'), 1);
            $mail->DKIM_private = FILE_DIRECTORY_PATH . '/dkim.key';
            $mail->DKIM_selector = (defined('DKIM_SELECTOR') && DKIM_SELECTOR) ? DKIM_SELECTOR : 'pinegrap';
        }

        return $mail->send();

    } catch (Exception $e) {
        $log = lang('The following email could not be delivered.') . "\n\n";
        $log .= lang('Error') . ': ' . $mail->ErrorInfo . "\n\n";
        $log .= lang('Subject') . ': ' . $subject . "\n";
        $log .= lang('To') . ': ' . (is_array($to) ? implode(', ', $to) : $to) . "\n";

        if ($bcc) {
            $log .= lang('BCC') . ': ' . (is_array($bcc) ? implode(', ', $bcc) : $bcc) . "\n";
        }

        $log .= lang('From') . ': ' . ($from_name ? "$from_name <$from_email_address>" : $from_email_address) . "\n";

        if ($reply_to) {
            $log .= lang('Reply-To') . ': ' . $reply_to . "\n";
        }

        if ($format !== 'html') {
            $log .= lang('Body') . ":\n" . $body;
        }

        log_activity($log);

        if ($notify_sender) {
            email([
                'to' => $from_email_address,
                'to_name' => $from_name,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject' => lang('Email delivery failed') . ' (' . $subject . ')',
                'body' => $log,
                'notify_sender' => false
            ]);
        }
        return false;
    }
}
// Looks at 3 different areas of a submitted form in order to determine
// which we consider to be the submitter email address for a submitted form.
function get_submitter_email_address($submitted_form_id)
{
    $submitter_email_address = '';
    // Check if there is a connect-to-contact email field,
    // and use that as the submitter email address if we find one.
    $submitter_email_address = db_value("SELECT form_data.data

        FROM form_data

        LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

        WHERE

            (form_data.form_id = '" . e($submitted_form_id) . "')

            AND (form_fields.contact_field = 'email_address')

        LIMIT 1");
    // If a submitter email address was not found from a
    // contact-to-contact email field, then check if there was a user that
    // submitted form.
    if (!$submitter_email_address) {
        $submitter_email_address = db_value("SELECT user.user_email

            FROM forms

            LEFT JOIN user ON forms.user_id = user.user_id

            WHERE forms.id = '" . e($submitted_form_id) . "'");
    }
    // If a submitter email address has still not been found,
    // then check for just a field with an email type.
    if (!$submitter_email_address) {
        $email_addresses = db_values("SELECT form_data.data

            FROM form_data

            LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

            WHERE

                (form_data.form_id = '" . e($submitted_form_id) . "')

                AND (form_fields.type = 'email address')");
        // If only one email address was found,
        // then it is safe to assume that is the submitter
        // email address, so we can use it.
        if (count($email_addresses) == 1) {
            $submitter_email_address = $email_addresses[0];
        }
    }
    return $submitter_email_address;
}
// Sends the notification e-mails (submitter e-mail and administrator e-mail)
// for a submitted form, and creates any auto e-mail campaigns that are
// triggered by a custom form submission.
// This logic used to live inline in edit_submitted_form.php.  It was extracted
// so that add_submitted_form.php can reuse it instead of duplicating it.
// $require_save_for_later preserves the original behavior of
// edit_submitted_form.php, which only sent notifications for custom forms that
// have save-for-later enabled.
function pg_send_custom_form_notifications($custom_form_page_id, $form_id, $contact_id = 0, $require_save_for_later = false)
{
    $custom_form = db_item(
        "SELECT
            page.page_id,
            custom_form_pages.enabled,
            custom_form_pages.save,
            custom_form_pages.submitter_email,
            custom_form_pages.submitter_email_from_email_address,
            custom_form_pages.submitter_email_subject,
            custom_form_pages.submitter_email_format,
            custom_form_pages.submitter_email_body,
            custom_form_pages.submitter_email_page_id,
            custom_form_pages.administrator_email,
            custom_form_pages.administrator_email_to_email_address,
            custom_form_pages.administrator_email_bcc_email_address,
            custom_form_pages.administrator_email_subject,
            custom_form_pages.administrator_email_format,
            custom_form_pages.administrator_email_body,
            custom_form_pages.administrator_email_page_id
        FROM page
        LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
        WHERE
            (page.page_id = '" . e($custom_form_page_id) . "')
            AND " . pg_form_page_sql('page'));

    // If the custom form could not be found, or it is not enabled, then there
    // is nothing to send.
    if (!$custom_form['enabled']) {
        return false;
    }

    // If the caller requires save-for-later to be enabled (edit screen), and it
    // is not enabled, then do not send anything.
    if (($require_save_for_later == true) && (!$custom_form['save'])) {
        return false;
    }

    $submitter_email_address = '';

    // If the submitter or admin email is enabled, then get
    // submitter email address for later.
    if ($custom_form['submitter_email'] or $custom_form['administrator_email']) {
        $submitter_email_address = get_submitter_email_address($form_id);
    }

    // If submitter email is enabled, and a submitter email address
    // was found, then determine if there is a recipient to send email to.
    if ($custom_form['submitter_email'] and $submitter_email_address) {

        if ($custom_form['submitter_email_from_email_address']) {
            $from_email_address = $custom_form['submitter_email_from_email_address'];
        } else {
            $from_email_address = EMAIL_ADDRESS;
        }

        $subject = get_variable_submitted_form_data_for_content($custom_form['page_id'], $form_id, $custom_form['submitter_email_subject'], $prepare_for_html = false);

        // If the format of the e-mail should be plain text,
        // then prepare that.
        if ($custom_form['submitter_email_format'] == 'plain_text') {

            // Check the body for variable data and replace
            // any variables with content from the submitted form.
            $body = get_variable_submitted_form_data_for_content($custom_form['page_id'], $form_id, $custom_form['submitter_email_body'], $prepare_for_html = false);

        // Otherwise the format of the e-mail should be HTML, so prepare that.
        } else {

            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

            $body = get_page_content($custom_form['submitter_email_page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true, array('form_id' => $form_id));

        }

        email(array(
            'to' => $submitter_email_address,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => $from_email_address,
            'subject' => $subject,
            'format' => $custom_form['submitter_email_format'],
            'body' => $body
        ));

    }

    // If admin email is enabled, then determine if we should send it.
    if ($custom_form['administrator_email']) {

        $administrator_email_addresses = array();

        if ($custom_form['administrator_email_to_email_address']) {
            $administrator_email_addresses[] = $custom_form['administrator_email_to_email_address'];
        }

        // Get conditional administrators.
        $conditional_administrators = db_values(
            "SELECT form_field_options.email_address
            FROM form_field_options
            LEFT JOIN form_data ON form_field_options.form_field_id = form_data.form_field_id
            WHERE
                (form_field_options.page_id = '" . $custom_form['page_id'] . "')
                AND (form_data.form_id = '" . e($form_id) . "')
                AND (form_field_options.email_address != '')
                AND (form_data.data = form_field_options.value)");

        // Loop through the conditional administrators in order to add them.
        foreach ($conditional_administrators as $conditional_administrator) {
            // We support multiple conditional admin email addresses, separated by comma,
            // (e.g. ^^example1@example.com,example2@example.com^^), so deal with that.
            $conditional_email_addresses = explode(',', $conditional_administrator);

            foreach ($conditional_email_addresses as $conditional_email_address) {
                $conditional_email_address = trim($conditional_email_address);

                // If this e-mail address has not already been added, then add it.
                if (in_array($conditional_email_address, $administrator_email_addresses) == false) {
                    $administrator_email_addresses[] = $conditional_email_address;
                }
            }
        }

        // If there is an admin to email, then continue to send email.
        if (
            $administrator_email_addresses
            or $custom_form['administrator_email_bcc_email_address']
        ) {

            $subject = get_variable_submitted_form_data_for_content($custom_form['page_id'], $form_id, $custom_form['administrator_email_subject'], $prepare_for_html = false);

            // If the format of the e-mail should be plain text,
            // then prepare that.
            if ($custom_form['administrator_email_format'] == 'plain_text') {

                // Check the body for variable data and replace
                // any variables with content from the submitted form.
                $body = get_variable_submitted_form_data_for_content($custom_form['page_id'], $form_id, $custom_form['administrator_email_body'], $prepare_for_html = false);

            // Otherwise the format of the e-mail should be HTML, so prepare that.
            } else {

                require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

                $body = get_page_content($custom_form['administrator_email_page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true, array('form_id' => $form_id));

            }

            email(array(
                'to' => $administrator_email_addresses,
                'bcc' => $custom_form['administrator_email_bcc_email_address'],
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'reply_to' => $submitter_email_address,
                'subject' => $subject,
                'format' => $custom_form['administrator_email_format'],
                'body' => $body
            ));

        }

    }

    // Check if there are auto e-mail campaigns that should be
    // created based on this custom form being submitted.
    create_auto_email_campaigns(array(
        'action' => 'custom_form_submitted',
        'action_item_id' => $custom_form['page_id'],
        'contact_id' => $contact_id));

    return true;
}
