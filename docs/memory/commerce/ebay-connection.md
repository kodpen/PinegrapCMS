---
name: ebay-connection
description: eBay marketplace accounts: the ebay and ebay_sandbox connect providers (RuName, three sell scopes), the connector test, the eBay site, remote selects for location and policies
module: ebay
---

## How does a site sign in to eBay?
- Each site registers its own eBay application (architect rule): Connections › eBay (or eBay (sandbox)) takes the keyset's App ID as Client ID, Cert ID as secret and the RuName. Providers `includes/connect/providers/ebay.php` and `ebay_sandbox.php`, both built by `pg_ebay_provider($environment)` in `includes/fn/ebay.php`; purpose `ebay` (`includes/connect/purposes/ebay.php`).
- Authorization code flow, client auth `basic`, scopes `sell.inventory`, `sell.fulfillment`, `sell.account` (`pg_ebay_scopes()`). Hosts: `auth.ebay.com` / `api.ebay.com`, sandbox `auth.sandbox.ebay.com` / `api.sandbox.ebay.com` (`pg_ebay_hosts()`).
- eBay's `redirect_uri` is the RuName, not a URL: the provider says `'redirect_option' => 'runame'` and `pg_connect_provider_redirect()` (`includes/fn/connect.php`) sends the app's saved option in place of the callback URL. The callback URL is entered in eBay's console as the RuName's auth accepted URL.
- The production provider has `copy_values`: the account deletion address and its verification token, shown with Copy buttons in the app form (`connections.php`, `connections_app_form()`), because eBay issues a production keyset only after they are entered (`ebay-account-deletion.md`).

## What does a marketplace account hold?
- `mp_providers()['ebay']` (`connectors/base.php`): no key of its own; fields `environment` (production/sandbox), `site` (`pg_ebay_sites()`: 19 marketplace ids with domain, currency, locale; label "domain · currency", never translated) and four remote selects `location`, `fulfillment_policy`, `payment_policy`, `return_policy`.
- One account per eBay site (architect rule); the site sits in the account's sealed credentials, no table or column for eBay. One sign-in serves every site's account: `mp_ebay_connection_id()` takes the newest connection of the environment's provider with purpose `ebay`.

## What is a remote select?
- `'type' => 'select', 'remote' => true`, empty `options`: its options come from the marketplace account. `test_credentials()` answers `data['choices']` (field => value => name); `marketplace_settings.php` keeps them in the credentials under `choices` through `mp_account_choices_merge()` (only remote fields, 100 options of 100 characters each; a chosen value no longer offered is cleared; a single option is chosen by itself).
- `mp_provider_credentials($provider, $input, $choices)` stores a remote value only when it is among the choices. The form shows "Test the connection to read the choices" until then, "Choose one" after; names are shown untranslated; "add account" leaves remote fields out.

## What does the connector's test do?
- `mp_ebay_test_credentials($account, $call = null)` (`connectors/ebay.php`): with no connection, "Connect an eBay account on Connections first." Calls through `pg_connect_api()`: `get_opted_in_programs` must list `SELLING_POLICY_MANAGEMENT`; `/sell/inventory/v1/location` (DISABLED left out) must give one location; `fulfillment_policy`, `payment_policy`, `return_policy` by `marketplace_id` must each give one. A missing one names the kind and the site's domain.
- Errors read eBay's `errors[0].message`; status 0, 429 and 5xx are retryable. `$call` is the seam tests pass with fixtures (`tests/fixtures/ebay/`, from eBay's documentation).

## Where is it named?
- `pg_connections_registry()['ebay']` (group marketplaces, place `marketplace_settings.php`) covers the marketplace provider, both connect providers and the purpose, so Connections and the settings search find it.
- Tests: `tests/ebay_test.php`. Not verified against a live seller: the first sandbox try is the owner's.
