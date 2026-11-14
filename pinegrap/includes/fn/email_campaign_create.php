<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail campaign create: picking the recipients from contact groups and
 * writing the campaign with its recipients and groups, one path that the
 * create screen (add_email_campaign.php) and the external API
 * (POST /campaigns) share, so a campaign made from either place reaches the
 * same people and leaves the same rows.
 *
 * Nothing here checks who is asking beyond the contact groups: the screen
 * gates on validate_email_access(), the API on campaigns:write. A group the
 * user may not reach (validate_contact_group_access()) is passed over, as the
 * screen has always done.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_recipients.php');

/**
 * The recipients of a new manual campaign: the addresses of the included
 * groups (opted-in contacts only for a commercial campaign), less every
 * address of the excluded groups, plus one entered address. Each address is
 * kept once, with the first contact found for it. For a user above the user
 * role an entered address that no contact or user has yet becomes a contact,
 * as on the create screen.
 *
 * @param array  $user
 * @param string $purpose  commercial or transactional
 * @param array  $groups   contact group id => 'included' or 'excluded'
 * @param string $entered  one more address, '' for none (checked by the caller)
 * @return array|false  recipients (addresses), contact_ids (address => id),
 *                      included and excluded (group ids); false when a query failed
 */
function pg_email_campaign_pick_recipients($user, $purpose, $groups, $entered)
{
    $recipients = array();
    $contact_ids = array();
    $included = array();
    $excluded = array();

    $result = mysqli_query(db::$con, "SELECT id FROM contact_groups");

    if ($result === false) {
        return false;
    }

    $contact_groups = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row['id'];
    }

    foreach ($contact_groups as $contact_group_id) {
        if ((($groups[$contact_group_id] ?? '') !== 'included') || !validate_contact_group_access($user, $contact_group_id)) {
            continue;
        }

        $included[] = $contact_group_id;

        $email_subscription = db_value("SELECT email_subscription FROM contact_groups WHERE id = '" . (int) $contact_group_id . "'");

        // A commercial campaign takes opted-in contacts only, a
        // transactional one every contact of the group with an address.
        $result = db_q(pg_email_campaign_recipient_query($purpose, $email_subscription == 1), array((string) $contact_group_id));

        if ($result === false) {
            return false;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $email_address = mb_strtolower($row['email_address']);
            $recipients[] = $email_address;

            // The first contact found for an address keeps it.
            if (!array_key_exists($email_address, $contact_ids)) {
                $contact_ids[$email_address] = $row['id'];
            }
        }
    }

    $recipients = array_unique($recipients);

    foreach ($contact_groups as $contact_group_id) {
        if ((($groups[$contact_group_id] ?? '') !== 'excluded') || !validate_contact_group_access($user, $contact_group_id)) {
            continue;
        }

        $excluded[] = $contact_group_id;

        $result = db_q(
            "SELECT contacts.email_address
            FROM contacts_contact_groups_xref
            LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
            WHERE
                (contacts_contact_groups_xref.contact_group_id = ?)
                AND (contacts.email_address != '')",
            array((string) $contact_group_id));

        if ($result === false) {
            return false;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $key = array_search(mb_strtolower($row['email_address']), $recipients);

            if ($key !== false) {
                unset($recipients[$key]);
            }
        }
    }

    if ($entered !== '') {
        $recipients[] = mb_strtolower($entered);

        // An address nobody has yet becomes a contact, for a user above the
        // user role.
        if ($user['role'] < 3) {
            $in_contacts = db_value("SELECT COUNT(*) FROM contacts WHERE email_address = '" . escape($entered) . "'");
            $in_users = db_value("SELECT COUNT(*) FROM user WHERE user_email = '" . escape($entered) . "'");

            if (((int) $in_contacts === 0) && ((int) $in_users === 0)) {
                $result = db_q(
                    "INSERT INTO contacts (email_address, user, timestamp) VALUES (?, ?, UNIX_TIMESTAMP())",
                    array((string) $entered, (string) $user['id']));

                if ($result !== false) {
                    // Read the id first: the audit line is itself an insert.
                    $new_contact_id = (int) mysqli_insert_id(db::$con);
                    pg_audit_record('create', 'contacts', $new_contact_id);
                }
            }
        }
    }

    return array(
        'recipients'  => array_values(array_unique($recipients)),
        'contact_ids' => $contact_ids,
        'included'    => $included,
        'excluded'    => $excluded,
    );
}

/**
 * Writes a new manual campaign, one recipient row per address and the groups
 * it was put together from.
 *
 * @param array $values  from_name, from_email_address, reply_email_address,
 *                       subject, format, body, page_id, start_time (the
 *                       column value), purpose, status (ready or paused)
 * @param array $picked  pg_email_campaign_pick_recipients()
 * @param int   $user_id
 * @return int  the campaign's id, 0 when it could not be written
 */
function pg_email_campaign_insert($values, $picked, $user_id)
{
    // The body is stored as written or drawn. email() sends through
    // PHPMailer, which switches to quoted-printable for a line over 998
    // characters; a hard wrap here cut words, URLs, multibyte characters and
    // inline scripts apart.
    $body = (string) $values['body'];
    $status = (($values['status'] ?? 'ready') === 'paused') ? 'paused' : 'ready';
    $user_id = (string) (int) $user_id;

    $result = db_q(
        "INSERT INTO email_campaigns (
            from_name,
            from_email_address,
            reply_email_address,
            subject,
            format,
            body,
            page_id,
            start_time,
            purpose,
            status,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
        array(
            (string) $values['from_name'], (string) $values['from_email_address'], (string) $values['reply_email_address'],
            (string) $values['subject'], (string) $values['format'], $body, (string) $values['page_id'],
            (string) $values['start_time'], (string) $values['purpose'], $status, $user_id, $user_id
        ));

    if ($result === false) {
        return 0;
    }

    $email_campaign_id = (int) mysqli_insert_id(db::$con);

    foreach ($picked['recipients'] as $email_address) {
        $contact_id = array_key_exists($email_address, $picked['contact_ids']) ? $picked['contact_ids'][$email_address] : 0;

        db_q(
            "INSERT INTO email_recipients (email_campaign_id, email_address, contact_id) VALUES (?, ?, ?)",
            array((string) $email_campaign_id, (string) $email_address, (string) $contact_id));
    }

    foreach (array('included', 'excluded') as $type) {
        foreach ($picked[$type] as $contact_group_id) {
            db_q(
                "INSERT INTO contact_groups_email_campaigns_xref (contact_group_id, email_campaign_id, type) VALUES (?, ?, ?)",
                array((string) $contact_group_id, (string) $email_campaign_id, $type));
        }
    }

    return $email_campaign_id;
}

/**
 * The HTML of a page drawn as the body of a campaign.
 *
 * The page is drawn inside the sender's request, but the e-mail is read by
 * its recipients: the render is an e-mail render (the account widgets draw
 * signed out, get_page_content() writes no session token), and any copy of
 * the sender's CSRF token that still reached the HTML, such as a link a
 * widget built with it, is removed (pg_email_campaign_without_token()).
 *
 * @param int|string $page_id
 * @return string
 */
function pg_email_campaign_page_body($page_id)
{
    require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

    $body = (string) get_page_content($page_id, '', '', 'preview', true);

    return pg_email_campaign_without_token($body, (string) ($_SESSION['software']['token'] ?? ''));
}

/**
 * A page drawn as a campaign's body and made ready to send. An e-mail
 * design (its root carries props.pgEmail) goes through the mail pass first:
 * the page's style sheets out, the var() colours written as their hex, the
 * phone style in the head, and no fixed footer when the design draws its
 * own (pg_mail_pass()). Any other page is stored as before.
 *
 * @param int|string $page_id
 * @return string
 */
function pg_email_campaign_design_body($page_id)
{
    $body = pg_email_campaign_page_body($page_id);
    $with_footer = true;

    if (pg_email_design_tree_is(json_decode(pg_page_tree_json($page_id), true))) {
        $pass = pg_mail_pass($body);
        $body = $pass['html'];
        $with_footer = !$pass['has_footer'];
    }

    return pg_email_campaign_prepare_html_body($body, get_page_name($page_id), $with_footer);
}

/**
 * The HTML without the given session token. A token is a long random hex
 * string, so it is cut wherever it appears (a link's token= parameter, a
 * hidden field, an inline script); a blank or short value cuts nothing.
 *
 * @param string $html
 * @param string $token
 * @return string
 */
function pg_email_campaign_without_token($html, $token)
{
    $token = (string) $token;

    if (strlen($token) < 16) {
        return (string) $html;
    }

    return str_replace($token, '', (string) $html);
}

/**
 * An HTML body made ready to send, as a campaign stores it: a base address
 * and absolute addresses when the body has no base, the visitor tracking
 * code (t=) on every link to this site that has none, and the fixed
 * campaign footer (web version, the organization's address, preferences
 * and unsubscribe) before </body> unless $with_footer is false (a design
 * that draws its own footer block).
 *
 * @param string $body          a whole HTML document
 * @param string $tracking_name the name the tracking code starts with (a page's name)
 * @param bool   $with_footer
 * @return string
 */
function pg_email_campaign_prepare_html_body($body, $tracking_name, $with_footer = true)
{
    $body = (string) $body;
    $tracking_name = (string) $tracking_name;

    // find if there is a base tag in the HTML
    $base_in_html = preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](?:http:\/\/|https:\/\/|ftp:\/\/).*?["\']/is', $body);

    // if there is not a base tag in the HTML, add base tag and convert relative links to absolute links
    if (!$base_in_html) {
        $base = '<head>' . "\n" . '<base href="' . URL_SCHEME . HOSTNAME_SETTING . '/" />';
        $body = preg_replace('/<head>/i', $base, $body);

        // change relative URLs to absolute URLs for links
        $body = preg_replace('/(<\s*a\s+[^>]*href\s*=\s*["\'])(?!ftp:\/\/|https:\/\/|mailto:|http:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

        // change relative URLs to absolute URLs for images
        $body = preg_replace('/(<\s*img\s+[^>]*src\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

        // change relative URLs to absolute URLs for CSS background images
        $body = preg_replace('/(background-image\s*:\s*url\s*\(\s*(?:"|\'|))(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?(?:"|\'|).*?\))/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

        // change relative URLs to absolute URLs for HTML background images
        $body = preg_replace('/(background\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'])/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
    }
    
    // get all links in order to add tracking codes to links
    preg_match_all('/(<\s*a\s+[^>]*href\s*=\s*["\']\s*)(.*?)(\s*["\'].*?>)/is', $body, $links);

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'm/d';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'd/m';
    }

    // set tracking code to contain the page name and date and time
    $tracking_code = $tracking_name . '_' . date($month_and_day_format . '/Y_h:i_A');

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
        if (
            ($url_parts != false)
            && ((isset($url_parts['scheme']) == false) || ($url_parts['scheme'] == '') || (mb_strtolower($url_parts['scheme']) == 'http') || (mb_strtolower($url_parts['scheme']) == 'https'))
            && ((isset($url_parts['host']) == false) || ($url_parts['host'] == '') || (mb_strtolower(str_replace('www.', '', $url_parts['host'])) == mb_strtolower(str_replace('www.', '', HOSTNAME_SETTING))))
            && ((isset($url_parts['query']) == false) || ($url_parts['query'] == '') || mb_strpos($url_parts['query'], 't=') === false)
        ) {
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
    
    // get URL for page
    $page_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign.php?r=<reference_code></reference_code>';

    $email_preferences_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/email_preferences.php?id=<email_address_id></email_address_id>';
    
    $footer =
			     '<div class="software_email_footer" style="font-family: arial; font-size: 11px; color: #666666; text-align: center; background-color: #ffffff; padding: 5px; margin-top: 15px">
			         <a href="' . $page_url . '" style="color: #666666">' . lang('View this email at our site') . '</a><br>
			         ' . h(ORGANIZATION_NAME) . '
			         ' . h(ORGANIZATION_ADDRESS_1) . '
			         ' . h(ORGANIZATION_ADDRESS_2) . '
			         ' . h(ORGANIZATION_CITY) . ' ' . h(ORGANIZATION_STATE) . ' ' . h(ORGANIZATION_ZIP_CODE) . ' ' . h(ORGANIZATION_COUNTRY) . '<br>
			         <a href="' . $email_preferences_url . '" style="color: #666666">' . lang('Update email preferences') . '</a>' . lang(' or ') . '<a href="' . $email_preferences_url . '" style="color: #666666">' . lang('unsubscribe') . '</a><br>
			     </div>
			     </body>';

    if ($with_footer) {
        $body = preg_replace('/<\/body>/i', $footer, $body);
    }

    return (string) $body;
}
