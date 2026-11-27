<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * QuickBooks Online as a provider of connected accounts
 * (includes/fn/connect.php).
 *
 * The application is an app the site owner makes on Intuit's developer
 * portal (developer.intuit.com/app/developer/dashboard): its development
 * keys reach only sandbox companies, its production keys come after
 * Intuit's App Assessment Questionnaire. The one return address of
 * connected accounts (connect.php) is entered there, for each set of keys.
 *
 * The sign-in is Intuit's OAuth 2.0 server: an authorization code, the
 * client authenticated with HTTP Basic, the scope
 * com.intuit.quickbooks.accounting. The access token lives an hour; the
 * refresh token 100 days and is replaced on renewal. Its addresses are
 * read from Intuit's discovery document (quickbooks_sign_in()).
 *
 * The site holds one QuickBooks connection (identity => false). The
 * company is named only by the realmId parameter of the return address,
 * which pg_connect_finish() keeps ('return_params') and
 * quickbooks_after_connect() reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quickbooks.php');

$quickbooks_sign_in = quickbooks_state()['sign_in'];

return array(
    'label'              => 'QuickBooks',
    'icon'               => 'bi-journal-text',
    'authorize_url'      => $quickbooks_sign_in['authorize'],
    'token_url'          => $quickbooks_sign_in['token'],
    'revoke_url'         => $quickbooks_sign_in['revoke'],
    'revoke_client_auth' => true,
    'issuers'            => array($quickbooks_sign_in['issuer']),
    'identity_scopes'    => array(),
    'identity'           => false,
    'account_label'      => 'QuickBooks',
    'nonce'              => false,
    'client_auth'        => 'basic',
    'token_per_scope'    => false,
    'default_ttl'        => 3600,
    'return_params'      => array('realmId' => '/^[0-9]{1,32}$/D'),
    'connect_purpose'    => 'quickbooks',
    'listed'             => false,
    'console_url'        => 'https://developer.intuit.com/app/developer/dashboard',
    'options'            => array(),
    'redirect_help'      => lang('Enter this address as a Redirect URI of your app on Intuit\'s developer portal, under the keys of the environment chosen below.'),
    'setup'              => array(
        lang('Sign in to Intuit\'s developer portal and create an app for QuickBooks Online and Payments with the com.intuit.quickbooks.accounting scope.'),
        lang('Under Keys and credentials, add the redirect address below as a Redirect URI and copy the Client ID and the Client secret here.'),
        lang('Development keys reach only sandbox companies: choose Sandbox as the environment to try them.'),
        lang('Production keys are given after Intuit\'s App Assessment Questionnaire, which asks for the app\'s privacy and licence addresses, its host domain and how it keeps data. Then choose Production and connect the company the site\'s sales go to.'),
    ),
);
