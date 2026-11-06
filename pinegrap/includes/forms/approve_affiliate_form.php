<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Approve Affiliate screen (approve_affiliate.php), opened from the link
 * in the affiliate request e-mail: the form column with the request (who
 * asked, the affiliate name and website) and the approval card, the sticky
 * status column with the Summary, and the sticky save bar with the Approve
 * Affiliate button. The screen keeps its gates, the contact load, the form
 * tag and the approval itself (pg_affiliate_approve()).
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
 * The request's facts in display order, as plain text, leaving out the
 * empty ones: the name, the e-mail address, the company, the affiliate name,
 * the website, the phone (the business phone, else the mobile phone), the
 * city and the country code.
 *
 * @param array $contact The contacts row
 * @return array  key => text; keys 'name', 'email_address', 'company',
 *                'affiliate_name', 'website', 'phone', 'city', 'country'
 */
function pg_approve_affiliate_form_facts($contact)
{
    $text = function ($key) use ($contact) {
        return (isset($contact[$key]) && is_scalar($contact[$key])) ? trim((string) $contact[$key]) : '';
    };

    $phone = $text('business_phone');
    if ($phone === '') {
        $phone = $text('mobile_phone');
    }

    $facts = array(
        'name' => trim($text('first_name') . ' ' . $text('last_name')),
        'email_address' => $text('email_address'),
        'company' => $text('company'),
        'affiliate_name' => $text('affiliate_name'),
        'website' => $text('website'),
        'phone' => $phone,
        'city' => $text('business_city'),
        'country' => strtoupper($text('business_country')),
    );

    return array_filter($facts, function ($value) {
        return $value !== '';
    });
}

/**
 * The address a website fact links to: the text as typed when it starts
 * with http:// or https://, else https:// before it. Anything with a scheme
 * other than those two, or spaces, is not linked ('').
 *
 * @param string $website The website as the contact typed it
 * @return string  the URL, or '' when it is not linked
 */
function pg_approve_affiliate_form_website_url($website)
{
    $website = trim((string) $website);

    if (($website === '') || preg_match('/\s/', $website)) {
        return '';
    }

    if (preg_match('~^https?://[^/]~i', $website)) {
        return $website;
    }

    // A scheme of its own (javascript:, data:, mailto:) or a //host start.
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $website) || (strpos($website, '/') === 0)) {
        return '';
    }

    return 'https://' . $website;
}

/**
 * The form column: the Affiliate Request card with the facts, and the
 * approval card that says what Approve Affiliate does, or that the contact
 * is approved already.
 *
 * @param array $contact          The contacts row
 * @param bool  $already_approved The contact is an approved affiliate
 * @return string  HTML
 */
function pg_approve_affiliate_form_card($contact, $already_approved)
{
    $labels = array(
        'name' => lang('Name'),
        'email_address' => lang('E-mail Address'),
        'company' => lang('Company'),
        'affiliate_name' => lang('Affiliate Name'),
        'website' => lang('Website'),
        'phone' => lang('Phone'),
        'city' => lang('City'),
        'country' => lang('Country'),
    );

    $facts = '';

    foreach (pg_approve_affiliate_form_facts($contact) as $key => $value) {
        if ($key === 'country') {
            $html = h(pg_geo_country_name($value));
        } elseif ($key === 'website') {
            $url = pg_approve_affiliate_form_website_url($value);
            $html = ($url !== '') ? '<a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($value) . '</a>' : h($value);
        } elseif ($key === 'email_address') {
            $html = '<a href="mailto:' . h($value) . '">' . h($value) . '</a>';
        } else {
            $html = h($value);
        }

        $facts .= '
                                        <div class="col-12 md:col-6 my-3">
                                            <dt class="small fw-normal fg-secondary">' . h($labels[$key]) . '</dt>
                                            <dd class="mb-0 fw-bold text-break">' . $html . '</dd>
                                        </div>';
    }

    $email = '<strong>' . h((string) ($contact['email_address'] ?? '')) . '</strong>';

    if ($already_approved) {
        $approval = lang(array('string' => '{var:1} is already an approved affiliate. Affiliate code: {var:2}', 'vars' => array($email, '<strong>' . h((string) ($contact['affiliate_code'] ?? '')) . '</strong>')));
    } else {
        $approval = lang(array('string' => 'Approve {var:1} as an affiliate? The affiliate code is created if there is none yet, and a welcome e-mail is sent to the contact.', 'vars' => $email));
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Affiliate Request') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <dl class="row mb-0">' . $facts . '
                                    </dl>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Approve Affiliate') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <p class="mb-0">' . $approval . '</p>
                                </div>
                            </div>
                        </div>';
}

/**
 * The sticky status column: the Summary with the status, the affiliate code
 * (a dash before one exists), the last change and a link to the contact.
 *
 * @param array  $contact          The contacts row
 * @param bool   $already_approved The contact is an approved affiliate
 * @param string $last_modified    The last change, already HTML
 * @param string $contact_url      The contact's edit screen
 * @return string  HTML
 */
function pg_approve_affiliate_form_status($contact, $already_approved, $last_modified, $contact_url)
{
    $status = $already_approved
        ? '<span class="badge theme-success">' . lang('Approved') . '</span>'
        : '<span class="badge theme-warning">' . lang('Pending Approval') . '</span>';

    $code = trim((string) ($contact['affiliate_code'] ?? ''));

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-3">'
        . pg_approve_affiliate_form_status_row(lang('Status'), $status, false)
        . pg_approve_affiliate_form_status_row(lang('Affiliate Code'), ($code !== '') ? h($code) : '&mdash;', false)
        . pg_approve_affiliate_form_status_row(lang('Last Modified'), $last_modified, true) . '
                                        </dl>
                                        <a href="' . h($contact_url) . '" class="btn btn-sm btn-outline theme-secondary w-100"><i class="bi bi-person me-1" aria-hidden="true"></i>' . lang('Go to Contact') . '</a>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label The label, plain text
 * @param string $value The value, already HTML
 * @param bool   $last  False draws the line under the row
 * @return string  HTML
 */
function pg_approve_affiliate_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns pg_approve_affiliate_form_card() . pg_approve_affiliate_form_status()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_approve_affiliate_form_layout($columns, $buttons)
{
    return '
                    <div class="row">' . $columns . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}
