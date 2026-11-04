<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The account menu's "My Account" link. Finding the account page can mean
 * scanning every designed page for the account widget, so the panel header
 * links here and the page is looked up only when the link is followed.
 * Without an account page the visitor lands on the home page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

validate_user();

go((string) get_page_type_url('my account'));
