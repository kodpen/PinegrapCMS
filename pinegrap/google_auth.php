<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sign in with Google: the return address Google sends people back to, and
 * the address older sign-in links and custom layouts still point at. The
 * flow is signin.php's; this file only names the provider, so the redirect
 * URI registered in Google Cloud Console stays the same.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_SIGNIN_PROVIDER', 'google');

require(dirname(__FILE__) . '/signin.php');
