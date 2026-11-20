<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What setting up the site's Google application in Google Cloud needs,
 * shared by Google's page on Connections (connections.php?service=google)
 * and a Gmail mailbox's form (ws_mail_google_steps()): the redirect
 * addresses the OAuth client must list, the APIs each Google feature calls
 * (read from the registry's 'apis'), Google's "API has not been used in
 * project ... or it is disabled" answer read as a plain sentence, and the
 * notes on Google's verification warning.
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
 * The addresses a provider's application must list as redirect URIs: the
 * one Sign in with the provider returns to and connect.php's, without
 * repeats. Both are needed: sign-in and connected accounts come back to
 * different scripts.
 *
 * @param string $provider a provider key (google, microsoft)
 * @return string[]
 */
function pg_google_cloud_redirect_addresses($provider = 'google')
{
    if (!function_exists('pg_signin_redirect_uri') || !function_exists('pg_connect_redirect_uri')) {
        return array();
    }

    return array_values(array_unique(array(pg_signin_redirect_uri($provider), pg_connect_redirect_uri())));
}

/**
 * The page in Google Cloud Console where an API is turned on. Pure.
 *
 * @param string $api     the API's service name (gmail.googleapis.com)
 * @param string $project the project's number or id, when known
 * @return string
 */
function pg_google_cloud_enable_url($api, $project = '')
{
    return 'https://console.cloud.google.com/apis/library/' . rawurlencode((string) $api)
        . (((string) $project !== '') ? '?project=' . rawurlencode((string) $project) : '');
}

/**
 * The Google APIs the registry's Google features call, each once with the
 * features that need it, in the registry's order. Pure.
 *
 * @param array $registry pg_connections_registry()
 * @return array service name => name, features (registry keys)
 */
function pg_google_cloud_apis($registry)
{
    $out = array();

    foreach ((array) $registry as $key => $service) {

        if (empty($service['apis']) || !is_array($service['apis'])) {
            continue;
        }

        foreach ($service['apis'] as $api => $name) {

            if (!isset($out[$api])) {
                $out[$api] = array('name' => (string) $name, 'features' => array());
            }

            $out[$api]['features'][] = (string) $key;

        }

    }

    return $out;
}

/**
 * Reads Google's answer for an API the project has not turned on ("Google
 * Calendar API has not been used in project 123 before or it is
 * disabled. Enable it by visiting ..."). Pure.
 *
 * @param string $text an error text as a call or the account stored it
 * @return array|null name, api ('' when the text names no service), url
 *                    (its enable page), or null for any other error
 */
function pg_google_cloud_api_disabled($text)
{
    if (!preg_match('/([A-Z][A-Za-z0-9 ()-]{0,80}? API) has not been used in project ([A-Za-z0-9-]+) before or it is disabled/', (string) $text, $match)) {
        return null;
    }

    $api = '';

    if (preg_match('~https://console\.(?:developers|cloud)\.google\.com/apis/(?:api|library)/([a-z0-9.-]+\.googleapis\.com)~', (string) $text, $service)) {
        $api = $service[1];
    }

    return array(
        'name' => $match[1],
        'api'  => $api,
        'url'  => ($api !== '')
            ? pg_google_cloud_enable_url($api, $match[2])
            : 'https://console.cloud.google.com/apis/library?project=' . rawurlencode($match[2]),
    );
}

/**
 * The notes on Google's verification warning: when Google shows it, the
 * three ways past it (Testing with test users, an Internal application,
 * verification) and the seven days a test user's access lasts. Every
 * sentence is one Google's documentation states (support.google.com/cloud
 * answer 15549945, the sensitive and restricted scope verification pages).
 *
 * @param string $scope 'gmail' for a mailbox's full Gmail access (a
 *                      restricted scope, which also needs a security
 *                      assessment), else the general notes
 * @return string[] plain text
 */
function pg_google_cloud_verification_notes($scope = '')
{
    $gmail = ($scope === 'gmail');

    return array(
        $gmail
            ? lang('Reading a mailbox asks for full access to Gmail, one of Google\'s restricted scopes: until Google has verified the application, Google shows "Google hasn\'t verified this app" before the account allows it.')
            : lang('When a feature asks for a sensitive or restricted scope, such as reading events in Google Calendar or full access to Gmail, Google shows a warning that it has not verified the application until Google has.'),
        lang('While the application\'s publishing status is Testing, only the accounts listed under Test users can connect, and they pass that warning to go on.'),
        lang('In Testing, Google ends the access seven days after it was given: the account is then connected again.'),
        lang('In a Google Workspace organisation, an application whose user type is Internal serves only the organisation\'s accounts and needs no verification.'),
        $gmail
            ? lang('For any other account, publish the application and have it verified by Google; for this scope Google also asks for a security assessment.')
            : lang('For any other account, publish the application and have it verified by Google.'),
    );
}
