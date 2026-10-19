<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * The address of the documentation for a control panel screen.
 *
 * The documentation lives on pinegrap.com: English at the root
 * (pinegrap.com/introduction) and every other language under a prefix of its
 * own (pinegrap.com/tr/introduction). Page names and the anchors inside them
 * are the same in every language, so a topic is written once, as
 * "page#anchor", and only the prefix follows the panel language.
 *
 * Read through pg_help_url() (includes/fn/output.php), which also leaves help
 * out on a private-labelled site. This file is loaded only when a header is
 * drawn, so the table below costs nothing on the requests that draw none.
 *
 * Properties:
 *   languageinfo  the panel language; one the documentation is not written in
 *                 gets the English pages
 *   topic         "page#anchor" to use as it is, instead of looking the
 *                 screen up -- for a screen whose subject depends on more
 *                 than its script name
 *   screen        the screen to look up, without ".php"; defaults to the
 *                 script that is running
 *
 * A screen that is not in the table gets the introduction rather than
 * nothing, so a new screen is never left without a way to the documentation.
 * A new screen still belongs in the table.
 *
 * @param array $properties
 * @return string
 */
function get_help_url($properties = array())
{
    if (!is_array($properties)) {
        $properties = array();
    }

    // The languages the documentation is written in, and the prefix each one
    // is served under.
    $languages = array(
        'en' => '',
        'tr' => 'tr/',
    );

    // Screen => topic. A screen whose list changes with ?filter= maps to an
    // array of filter => topic, where '' is the topic without a filter.
    static $topics = array(

        // Introduction
        'index'   => 'introduction#welcome',
        'welcome' => 'introduction#welcome',

        // Site Settings: the eight categories of the settings dialog, opened
        // as screens of their own, and the tools that live beside them.
        'settings_general'    => 'site-settings#general',
        'settings_appearance' => 'site-settings#appearance',
        'settings_features'   => 'site-settings#features',
        'settings_seo'        => 'site-settings#seo',
        'settings_languages'  => 'site-settings#languages',
        'settings_contact'    => 'site-settings#communication',
        'settings_security'   => 'site-settings#security',
        'settings_firewall'   => 'site-settings#firewall',
        'settings_commerce'   => 'site-settings#commerce',
        'settings_api'        => 'site-settings#api',
        'smtp_settings'       => 'site-settings#smtp-settings',
        'mail_queue'          => 'site-settings#mail-queue',
        'software_repair'     => 'site-settings#repair-software',
        'backups'             => 'site-settings#backups',
        'cloudflare'          => 'site-settings#cloudflare',
        'view_log'            => 'site-settings#site-log',

        // File Manager
        'view_folders'     => 'folders',
        'add_folder'       => 'folders#create-edit-folder',
        'edit_folder'      => 'folders#create-edit-folder',
        'create_file'      => 'folders#create-edit-folder',
        'duplicate_folder' => 'folders#duplicate-folder',

        // Pages
        'view_pages' => array(
            ''                           => 'pages',
            'all_my_pages'               => 'pages',
            'all_my_archived_pages'      => 'pages-cont#archived-pages',
            'my_home_pages'              => 'pages-cont#home-pages',
            'my_searchable_pages'        => 'pages-cont#searchable-pages',
            'my_unsearchable_pages'      => 'pages-cont#unsearchable-pages',
            'my_sitemap_pages'           => 'pages-cont#site-map-pages',
            'my_rss_enabled_pages'       => 'pages-cont#rss-enabled-pages',
            'my_standard_pages'          => 'pages-cont#standard-pages',
            'my_photo_gallery_pages'     => 'pages-cont#photo-gallery-pages',
            'my_calendar_pages'          => 'pages-cont#calendar-pages',
            'my_custom_form_pages'       => 'pages-cont#custom-form-pages',
            'my_form_view_pages'         => 'pages-cont#form-view-pages',
            'my_affiliate_pages'         => 'pages-cont#affiliate-pages',
            'my_commerce_pages'          => 'pages-cont#commerce-pages',
            'my_account_pages'           => 'pages-cont#account-pages',
            'my_login_pages'             => 'pages-cont#login-pages',
            'my_miscellaneous_pages'     => 'pages-cont#miscellaneous-pages',
            'my_public_pages'            => 'pages-cont#public-access-pages',
            'my_guest_pages'             => 'pages-cont#guest-access-pages',
            'my_registration_pages'      => 'pages-cont#registration-access-pages',
            'my_membership_pages'        => 'pages-cont#membership-access-pages',
            'my_private_pages'           => 'pages-cont#private-access-pages',
            'seo_not_calculated'         => 'seo#seo-score',
            'seo_critical'               => 'seo#seo-score',
            'seo_weak'                   => 'seo#seo-score',
            'seo_broken_links'           => 'seo#seo-checks',
            'seo_duplicate'              => 'seo#seo-checks',
            'seo_img_no_alt'             => 'seo#seo-checks',
            'seo_orphan'                 => 'seo#seo-checks',
            'seo_search_weak_keywords'   => 'seo#seo-checks',
            'seo_sitemap_no_description' => 'seo#seo-checks',
            'seo_sitemap_no_title'       => 'seo#seo-checks',
            'seo_struct_error'           => 'seo#seo-checks',
        ),
        'toolbar'             => 'pages#editing-page-content',
        'view_comments'       => 'pages#edit-comment',
        'edit_comment'        => 'pages#edit-comment',
        'add_page'            => 'pages#edit-page-properties',
        'edit_page'           => 'pages#edit-page-properties',
        'view_fields'         => 'pages#edit-custom-form',
        'preview_form'        => 'pages#edit-custom-form',
        'add_field'           => 'pages#edit-custom-form-field',
        'edit_field'          => 'pages#edit-custom-form-field',
        'edit_form_list_view' => 'pages#edit-form-list-view',
        'edit_form_item_view' => 'pages#edit-form-item-view',
        'view_short_links'    => 'pages-cont#short-links',
        'add_short_link'      => 'pages-cont#create-edit-short-link',
        'edit_short_link'     => 'pages-cont#create-edit-short-link',
        'view_redirects'      => 'pages-cont#redirects',
        'edit_redirect'       => 'pages-cont#create-edit-redirect',
        'import_redirects'    => 'pages-cont#import-redirects',
        'view_auto_dialogs'   => 'pages-cont#auto-dialogs',
        'add_auto_dialog'     => 'pages-cont#create-edit-auto-dialog',
        'edit_auto_dialog'    => 'pages-cont#create-edit-auto-dialog',

        // Files
        'view_files' => array(
            ''                      => 'files',
            'all_my_files'          => 'files',
            'all_my_archived_files' => 'files#archived-files',
            'my_photos'             => 'files#photos',
            'my_documents'          => 'files#browsing-by-type',
            'my_media'              => 'files#browsing-by-type',
            'my_attachments'        => 'files#browsing-by-type',
            'my_public_files'       => 'files#browsing-by-access',
            'my_guest_files'        => 'files#browsing-by-access',
            'my_registration_files' => 'files#browsing-by-access',
            'my_member_files'       => 'files#browsing-by-access',
            'my_private_files'      => 'files#browsing-by-access',
        ),
        'add_file'          => 'files#upload-files',
        'edit_file'         => 'files#edit-file',
        'editor_edit_file'  => 'files#edit-file',
        'image_editor_edit' => 'files#image-editor',

        // Calendars
        'view_calendars'                => 'calendars',
        'calendars'                     => 'calendars#create-edit-calendar',
        'add_calendar'                  => 'calendars#create-edit-calendar-properties',
        'edit_calendar'                 => 'calendars#create-edit-calendar-properties',
        'add_calendar_event'            => 'calendars#create-edit-calendar-event',
        'edit_calendar_event'           => 'calendars#create-edit-calendar-event',
        'view_calendar_event_locations' => 'calendars#event-locations',
        'add_calendar_event_location'   => 'calendars#create-edit-calendar-event-location',
        'edit_calendar_event_location'  => 'calendars#create-edit-calendar-event-location',

        // Forms
        'view_submitted_forms'   => 'forms#submitted-forms',
        'add_submitted_form'     => 'forms#add-submitted-form',
        'edit_submitted_form'    => 'forms#edit-submitted-form',
        'import_submitted_forms' => 'forms#import-submitted-forms',

        // Visitors
        'view_visitor_reports' => 'visitors#visitor-reports',
        'view_visitor_report'  => 'visitors#create-edit-visitor-report',
        'view_visitor'         => 'visitors#visit-details',
        'search_console'       => 'visitors#google-search-console',

        // Contacts
        'view_contacts' => array(
            ''                                => 'contacts',
            'all_my_contacts'                 => 'contacts',
            'my_subscribers'                  => 'contacts#subscribers',
            'my_affiliates'                   => 'contacts#affiliates',
            'my_customers'                    => 'contacts#customers',
            'my_members'                      => 'contacts#members',
            'my_active_members'               => 'contacts#active-members',
            'my_expired_members'              => 'contacts#expired-members',
            'my_unregistered_members'         => 'contacts#unregistered-members',
            'my_contacts_by_user'             => 'contacts#contacts-by-user',
            'my_contacts_by_business_address' => 'contacts#contacts-by-business-address',
            'my_contacts_by_home_address'     => 'contacts#contacts-by-home-address',
            'all_duplicate_contacts'          => 'contacts#duplicate-contacts',
        ),
        'add_contact'             => 'contacts#create-edit-contact',
        'edit_contact'            => 'contacts#create-edit-contact',
        'import_contacts'         => 'contacts#import-contacts',
        'view_contact_groups'     => 'contacts#contact-groups',
        'add_contact_group'       => 'contacts#create-edit-contact-group',
        'edit_contact_group'      => 'contacts#create-edit-contact-group',
        'view_orders_for_contact' => 'contacts#orders-for-contact',
        'approve_affiliate'       => 'contacts#affiliates',

        // Users
        'view_users' => array(
            ''                            => 'users',
            'all_my_users'                => 'users',
            'my_registered_users'         => 'users#registered-users',
            'my_private_users'            => 'users#private-users',
            'my_member_users'             => 'users#member-users',
            'my_content_managers'         => 'users#content-managers',
            'my_calendar_managers'        => 'users#calendar-managers',
            'my_submitted_forms_managers' => 'users#submitted-forms-managers',
            'my_visitor_report_managers'  => 'users#visitor-report-managers',
            'my_contact_managers'         => 'users#contact-managers',
            'my_campaign_managers'        => 'users#campaign-managers',
            'my_commerce_managers'        => 'users#commerce-managers',
            'all_site_designers'          => 'users#site-designers',
            'all_site_administrators'     => 'users#site-administrators',
            'all_site_managers'           => 'users#site-managers',
        ),
        'add_user'      => 'users#create-edit-user',
        'edit_user'     => 'users#create-edit-user',
        'import_users'  => 'users#import-users',
        'view_sessions' => 'users#sessions',

        // Campaigns
        'view_email_campaigns'           => 'campaigns',
        'add_email_campaign'             => 'campaigns#create-edit-campaign',
        'edit_email_campaign'            => 'campaigns#create-edit-campaign',
        'view_email_campaign_history'    => 'campaigns#campaign-history',
        'view_email_campaign_profiles'   => 'campaigns#campaign-profiles',
        'add_email_campaign_profile'     => 'campaigns#create-edit-campaign-profile',
        'edit_email_campaign_profile'    => 'campaigns#create-edit-campaign-profile',
        'import_email_campaign_profiles' => 'campaigns#import-campaign-profiles',

        // Commerce
        'view_orders'                        => 'commerce#orders',
        'view_order'                         => 'commerce#view-order',
        'print_order'                        => 'commerce#view-order',
        'add_order'                          => 'commerce#add-local-order',
        'view_parasut_inbox'                 => 'commerce#parasut-inbox',
        'view_order_reports'                 => 'commerce#order-reports',
        'view_order_report'                  => 'commerce#create-edit-order-report',
        'view_commissions'                   => 'commerce#commissions',
        'edit_commission'                    => 'commerce#edit-commission',
        'view_recurring_commission_profiles' => 'commerce#recurring-commission-profiles',
        'edit_recurring_commission_profile'  => 'commerce#edit-recurring-commission-profile',
        'view_products' => array(
            ''                          => 'commerce#products',
            'seo_weak_products'         => 'seo#seo-score',
            'seo_struct_error_products' => 'seo#seo-checks',
        ),
        'duplicate_products'          => 'commerce#products',
        'product_builder'             => 'commerce#variant-sets',
        'add_product'                 => 'commerce#create-edit-product',
        'edit_product'                => 'commerce#create-edit-product',
        'import_products'             => 'commerce#import-products',
        'barcode_increase_inventory'  => 'commerce#barcode-inventory',
        'barcode_decrease_inventory'  => 'commerce#barcode-inventory',
        'view_product_groups'         => 'commerce#product-groups',
        'add_product_group'           => 'commerce#create-edit-product-group',
        'edit_product_group'          => 'commerce#create-edit-product-group',
        'duplicate_product_group'     => 'commerce#duplicate-product-group',
        'edit_featured_and_new_items' => 'commerce#edit-featured-and-new-items',
        'view_product_attributes'     => 'commerce#product-attributes',
        'add_product_attribute'       => 'commerce#create-edit-product-attribute',
        'edit_product_attribute'      => 'commerce#create-edit-product-attribute',
        'view_gift_cards'             => 'commerce#gift-cards',
        'add_gift_card'               => 'commerce#create-gift-card',
        'edit_gift_card'              => 'commerce#edit-gift-card',

        // Commerce, continued
        'view_offers'                      => 'commerce-cont#offers',
        'add_offer'                        => 'commerce-cont#create-edit-offer',
        'edit_offer'                       => 'commerce-cont#create-edit-offer',
        'view_key_codes'                   => 'commerce-cont#key-codes',
        'add_key_code'                     => 'commerce-cont#create-edit-key-code',
        'edit_key_code'                    => 'commerce-cont#create-edit-key-code',
        'import_key_codes'                 => 'commerce-cont#import-key-codes',
        'delete_key_codes'                 => 'commerce-cont#key-codes',
        'view_zones'                       => 'commerce-cont#zones',
        'add_zone'                         => 'commerce-cont#create-edit-zone',
        'edit_zone'                        => 'commerce-cont#create-edit-zone',
        'view_shipping_methods'            => 'commerce-cont#shipping-methods',
        'add_shipping_method'              => 'commerce-cont#create-edit-shipping-method',
        'edit_shipping_method'             => 'commerce-cont#create-edit-shipping-method',
        'view_arrival_dates'               => 'commerce-cont#arrival-dates',
        'add_arrival_date'                 => 'commerce-cont#create-edit-arrival-date',
        'edit_arrival_date'                => 'commerce-cont#create-edit-arrival-date',
        'view_verified_shipping_addresses' => 'commerce-cont#verified-shipping-addresses',
        'add_verified_shipping_address'    => 'commerce-cont#create-edit-verified-shipping-address',
        'edit_verified_shipping_address'   => 'commerce-cont#create-edit-verified-shipping-address',
        'view_containers'                  => 'commerce-cont#containers',
        'add_container'                    => 'commerce-cont#create-edit-container',
        'edit_container'                   => 'commerce-cont#create-edit-container',
        'view_shipping_report'             => 'commerce-cont#shipping-report',
        'view_ship_date_adjustments'       => 'commerce-cont#ship-date-adjustments',
        'add_ship_date_adjustment'         => 'commerce-cont#create-edit-ship-date-adjustment',
        'edit_ship_date_adjustment'        => 'commerce-cont#create-edit-ship-date-adjustment',
        'view_tax_zones'                   => 'commerce-cont#tax-zones',
        'add_tax_zone'                     => 'commerce-cont#create-edit-tax-zone',
        'edit_tax_zone'                    => 'commerce-cont#create-edit-tax-zone',
        'view_referral_sources'            => 'commerce-cont#referral-sources',
        'add_referral_source'              => 'commerce-cont#create-edit-referral-source',
        'edit_referral_source'             => 'commerce-cont#create-edit-referral-source',
        'view_currencies'                  => 'commerce-cont#currencies',
        'add_currency'                     => 'commerce-cont#create-edit-currency',
        'edit_currency'                    => 'commerce-cont#create-edit-currency',
        'view_countries'                   => 'commerce-cont#countries',
        'add_country'                      => 'commerce-cont#create-edit-country',
        'edit_country'                     => 'commerce-cont#create-edit-country',
        'view_states'                      => 'commerce-cont#states',
        'add_state'                        => 'commerce-cont#create-edit-state',
        'edit_state'                       => 'commerce-cont#create-edit-state',

        // Ads
        'view_ads' => 'ads',
        'add_ad'   => 'ads#create-edit-ad',
        'edit_ad'  => 'ads#create-edit-ad',

        // Design
        'view_styles'              => 'design#styles',
        'add_style'                => 'design#create-style',
        'add_custom_style'         => 'design#create-edit-custom-style',
        'edit_custom_style'        => 'design#create-edit-custom-style',
        'generate_layout'          => 'design#create-edit-custom-style',
        'view_system_style_source' => 'design#view-system-style-source',
        'page_designer'            => 'design#page_designer',
        'view_menus'               => 'design#menus',
        'add_menu'                 => 'design#create-edit-menu',
        'edit_menu'                => 'design#create-edit-menu',
        'view_menu_items'          => 'design#menu-items',
        'add_menu_item'            => 'design#create-edit-menu-item',
        'edit_menu_item'           => 'design#create-edit-menu-item',
        'view_regions' => array(
            ''                     => 'design#common-regions',
            'all_common_regions'   => 'design#common-regions',
            'all_designer_regions' => 'design#designer-regions',
            'all_ad_regions'       => 'design#ad-regions',
            'all_dynamic_regions'  => 'design#dynamic-regions',
            'all_login_regions'    => 'design#login-regions',
        ),
        'add_common_region'    => 'design#create-edit-common-region',
        'edit_common_region'   => 'design#create-edit-common-region',
        'add_designer_region'  => 'design#create-edit-designer-region',
        'edit_designer_region' => 'design#create-edit-designer-region',
        'add_ad_region'        => 'design#create-edit-ad-region',
        'edit_ad_region'       => 'design#create-edit-ad-region',
        'add_dynamic_region'   => 'design#create-edit-dynamic-region',
        'edit_dynamic_region'  => 'design#create-edit-dynamic-region',
        'add_login_region'     => 'design#create-edit-login-region',
        'edit_login_region'    => 'design#create-edit-login-region',
        'view_themes'          => 'design#themes',
        'add_theme_file'       => 'design#add-theme-file',
        'edit_theme_file'      => 'design#edit-theme-file',
        'edit_theme_css'       => 'design#edit-theme-css',
        'theme_designer'       => 'design#theme-designer',
        'view_design_files'    => 'design#design-files',
        'add_design_file'      => 'design#upload-design-files',
        'edit_design_file'     => 'design#edit-design-file',
        'edit_javascript'      => 'design#edit-javascript',
        'import_design'        => 'design#import-my-site',
        'import_zip'           => 'design#import-zip-file',
        'find_and_replace'     => 'design#find-and-replace',
        'find_replace'         => 'design#find-and-replace',
        'mass_edit'            => 'design#mass-edit',
        'migration'            => 'design#migration',

        // Visual Page Editor
        'view_system_styles' => 'visual-page-editor#designs',
        'add_system_style'   => 'visual-page-editor#create-design',
        'edit_system_style'  => 'visual-page-editor#editor',

        // Multilingual site
        'translations' => 'multilingual#translations',

        // ERP
        'erp_dashboard'           => 'erp#erp-dashboard',
        'erp_accounts'            => 'erp#accounts',
        'add_erp_account'         => 'erp#create-edit-account',
        'edit_erp_account'        => 'erp#create-edit-account',
        'erp_account_prices'      => 'erp#account-prices',
        'erp_reconciliation'      => 'erp#reconciliation',
        'erp_accounts_import'     => 'erp#import-accounts',
        'erp_invoices'            => 'erp#invoices',
        'add_erp_manual_invoice'  => 'erp#create-edit-invoice',
        'edit_erp_invoice_draft'  => 'erp#create-edit-invoice',
        'add_erp_invoice'         => 'erp#invoice-order',
        'edit_erp_invoice'        => 'erp#view-invoice',
        'erp_invoice_recurrences' => 'erp#recurring-invoices',
        'add_erp_return'          => 'erp#returns',
        'erp_quotes'              => 'erp#quotes',
        'add_erp_quote'           => 'erp#create-edit-quote',
        'edit_erp_quote'          => 'erp#create-edit-quote',
        'erp_purchase_orders'     => 'erp#purchase-orders',
        'add_erp_purchase_order'  => 'erp#create-edit-purchase-order',
        'edit_erp_purchase_order' => 'erp#create-edit-purchase-order',
        'erp_inbox'               => 'erp#incoming-e-invoices',
        'erp_inbox_document'      => 'erp#incoming-e-invoices',
        'erp_account_sync'        => 'erp#account-sync',
        'erp_account_sync_item'   => 'erp#account-sync',
        'erp_aging'               => 'erp#aging-report',
        'erp_profit'              => 'erp#profit-and-loss',
        'erp_vat_report'          => 'erp#vat-report',

        // ERP, continued
        'erp_stock'              => 'erp-cont#stock',
        'erp_stock_minimums'     => 'erp-cont#stock-minimums',
        'erp_stock_counts'       => 'erp-cont#stock-counts',
        'erp_stock_count'        => 'erp-cont#stock-count',
        'erp_expenses'           => 'erp-cont#expenses',
        'add_erp_expense'        => 'erp-cont#create-edit-expense',
        'edit_erp_expense'       => 'erp-cont#view-expense',
        'erp_expense_categories' => 'erp-cont#expense-categories',
        'erp_waybills'           => 'erp-cont#delivery-notes',
        'add_erp_waybill'        => 'erp-cont#create-edit-delivery-note',
        'edit_erp_waybill'       => 'erp-cont#create-edit-delivery-note',
        'erp_export'             => 'erp-cont#export',
        'erp_accountant'         => 'erp-cont#accountant-pack',
        'erp_cash'               => 'erp-cont#cash-and-bank',
        'add_erp_till'           => 'erp-cont#create-edit-till',
        'edit_erp_till'          => 'erp-cont#create-edit-till',
        'add_erp_receipt'        => 'erp-cont#receipts',
        'erp_receipt'            => 'erp-cont#view-receipt',
        'add_erp_transfer'       => 'erp-cont#transfers',
        'erp_cashflow'           => 'erp-cont#cash-flow',
        'erp_cheques'            => 'erp-cont#cheques',
        'erp_cheque'             => 'erp-cont#cheque',
        'erp_bank_statements'    => 'erp-cont#bank-statements',
        'erp_bank_statement'     => 'erp-cont#bank-statement',
        'erp_audit'              => 'erp-cont#audit-trail',
        'erp_settings'           => 'erp-cont#erp-settings',

        // Workspace
        'workspace'          => 'workspace#channels',
        'workspace_board'    => 'workspace#planning-board',
        'workspace_calendar' => 'workspace#work-calendar',
        'workspace_tasks'    => 'workspace#my-tasks',
        'workspace_timeline' => 'workspace#decision-timeline',
        'workspace_settings' => 'workspace#workspace-settings',
        'workspace_notes'    => 'workspace-cont#notes',
        'workspace_template' => 'workspace-cont#channel-templates',

        // API and integrations
        'api_settings'         => 'api#api-keys',
        'api_docs'             => 'api#api-reference',
        'connected_apps'       => 'api#mcp',
        'connections'          => 'api#connected-accounts',
        'marketplace_settings' => 'api#marketplaces',

        // System tools
        'software_update'      => 'system#software-update',
        'clean_up'             => 'system#caches',
        'database_engine'      => 'system#database-engine',
        'view_performance_log' => 'system#performance-log',
        'private_label'        => 'system#private-label',

        // Security
        'view_waf_log'   => 'security#waf-log',
        'developer_lock' => 'security#developer-lock',
        'device_limit'   => 'security#devices',
        'mfa'            => 'security#two-step-verification',
    );

    $language = isset($properties['languageinfo']) ? (string) $properties['languageinfo'] : 'en';
    $prefix = isset($languages[$language]) ? $languages[$language] : '';

    $topic = isset($properties['topic']) ? (string) $properties['topic'] : '';

    if ($topic === '') {

        $screen = isset($properties['screen']) ? (string) $properties['screen'] : '';

        // SCRIPT_NAME rather than PHP_SELF: PHP_SELF carries any path info
        // after the script, and its last segment is then not the screen.
        if ($screen === '') {
            $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : (string) ($_SERVER['PHP_SELF'] ?? '');
            $screen = pathinfo(basename($script), PATHINFO_FILENAME);
        }

        $entry = isset($topics[$screen]) ? $topics[$screen] : '';

        if (is_array($entry)) {

            // Most lists take the filter from the address only; the product
            // list also keeps the last one in the session, and shows it again
            // when the address carries none.
            $filter = '';

            if (isset($_GET['filter']) && is_scalar($_GET['filter'])) {
                $filter = (string) $_GET['filter'];
            } elseif (isset($_SESSION['software'][$screen]['filter']) && is_scalar($_SESSION['software'][$screen]['filter'])) {
                $filter = (string) $_SESSION['software'][$screen]['filter'];
            }

            $entry = isset($entry[$filter]) ? $entry[$filter] : $entry[''];
        }

        $topic = (string) $entry;
    }

    // A topic is a page name and an anchor and nothing else, which is also
    // what keeps it out of the way of the href and the script it lands in.
    if (($topic === '') || !preg_match('/^[a-z0-9_-]+(#[a-z0-9_-]+)?$/', $topic)) {
        $topic = 'introduction';
    }

    return 'https://pinegrap.com/' . $prefix . $topic;
}
