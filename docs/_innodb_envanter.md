# InnoDB envanteri (2026.4.8, 8.10–8.14)

Kaynak: `pinegrap/data/backups/english_default/sql.sql` ve
`turkish_default/sql.sql` (ikisi aynı 141 `CREATE TABLE`, hepsi
`ENGINE=MyISAM`) ve `pinegrap/includes/migrations/*.php` içinde eski `ENGINE`
sabitiyle (`" . ENGINE` → `ENGINE=MyISAM`) açılan tablolar: `order_refunds`,
`product_barcodes`, `iyzipay_3ds_state`, `notifications`,
`local_sale_history`, `local_sale_history_items`, `custom_apps`. Daha önce
taşınanlar (`visitors` 2026.3.6, `user` ve `files` 2026.4.4) ve 2026.4.4'te
düşürülen `custom_apps` çıkınca **144 tablo** kalır; liste
`pg_innodb_table_groups()` (`includes/fn/innodb.php`), denetimi
`tests/innodb_test.php` (dökümle ve migration'larla küme eşitliği).

Şema sütunları sandbox'taki (MariaDB 10.11, kurulum + 2026.4.8 zinciri)
gerçek şemadan okundu (`information_schema.COLUMNS` / `STATISTICS`).

## Özet

- **AUTO_INCREMENT birincil anahtarın ilk kolonu mu:** AUTO_INCREMENT taşıyan
  104 tablonun hepsinde evet (MyISAM'ın izin verip InnoDB'nin reddettiği
  "bileşik anahtarda ikinci kolon AUTO_INCREMENT" yok). **40 tabloda ne
  AUTO_INCREMENT ne PRIMARY KEY var** (`config`, `dashboard`,
  `next_order_number`, `messages`, `opt_in`, `aclfolder`, `preview_styles`,
  `submitted_form_info`, `submitted_form_views`,
  `remaining_reservation_spots`, `allow_new_comments_for_items`,
  `form_list_view_browse_fields` ve `*_xref` tabloları). Bu dönüşümü
  engellemez: InnoDB gizli bir satır kimliğiyle (GEN_CLUST_INDEX) kümeler.
  Sandbox'ta 144'ü de sorunsuz döndü. Birincil anahtar eklemek ayrı bir iştir,
  bu sürümde yapılmadı.
- **FULLTEXT:** yalnız `search_items`, 4 indeks (`content`, `title`,
  `description`, `keywords`).
- **MyISAM'a özgü tablo seçeneği** (`MAX_ROWS`, `AVG_ROW_LENGTH`,
  `PACK_KEYS`, `DELAY_KEY_WRITE`, `ROW_FORMAT=FIXED`, `CHECKSUM`,
  `INSERT_METHOD`, `UNION`): dökümlerde **yok**.
- **767 baytı aşan indeks** (utf8mb4 = 4 bayt/karakter; COMPACT/REDUNDANT
  satır biçiminde ya da `innodb_large_prefix` kapalıyken 1071 verir):
  `search_items.url(250)` = 1000 bayt ve `forms.address_name(250)` = 1000
  bayt. İkisi de DYNAMIC satır biçiminde geçer; `pg_innodb_capability()`
  bunu `@@innodb_default_row_format = dynamic` ile sorar.
- **`config` satır boyutu:** 410 kolon (döküm + migration'lar), PK yok.
  COMPACT'ta satır içi 768 baytlık önekler 8126 bayt sınırını aşar (1118).
  DYNAMIC'te de sunucuya göre değişir. ≤ 255 oktetlik değişken kolon
  (utf8mb4'te VARCHAR(63) ve altı) satırın içinde kalmak zorundadır, L + 1
  sayılır. Uzun kolon ve TEXT MySQL 8.0'da 41, MariaDB 10.4+'da 21 sayılır;
  MySQL 5.7 40 baytı aşan her değişken kolonu 41 sayar. 2026.4.8 öncesi stok
  tablo MySQL 8.0'da **8468 / 8126** ediyordu ve dönüşümü 1118 ile
  reddediliyordu; MySQL 5.7'de 6988, MariaDB'de 5868. İlk doğrulama
  sandbox'ı MariaDB olduğu için geçmişti (33 TEXT kolona 2000 bayt yazan
  UPDATE de MariaDB'de denendi). Ayrıca sunucunun 65.535 baytlık satır
  sınırında 64.069 bayt (1,46 KB pay) vardı. 8.17
  (`upgrade_2026_4_8_config_text_columns()`) her VARCHAR kolonu TEXT yapar:
  MySQL 8.0'da 7064 / 8126, SQL katmanında 2126 / 65.535. Tahmin:
  `pg_innodb_row_estimate()`; ayrıntı `docs/degisiklikler.md`.

## Kodda motora bağlı yerler

- **12 `MATCH … AGAINST`:** `get_search_results.php:460,465,503-505,512`,
  `includes/fn/widgets.php:3261,3263,3272-3274,3276`. Değiştirilmedi. InnoDB
  FT farkları: `innodb_ft_min_token_size` 3 (MyISAM `ft_min_word_len` 4) iki
  kipte de; varsayılan stopword listesi kısa (`and` yok, `the` var); doğal
  dilde %50 eşiği yok. Altın kayıt (36 satır, 30 sorgu): 15 aynı, 14 yalnız
  "daha çok sonuç", 1 aynı küme farklı sıra; eksilen sonuç yok.
- **`LOCK TABLES` (14 satır, 7 yer):** `submit_order.php:4295`,
  `includes/local_sale.php:810`, `includes/api/outbound/orders.php:765`,
  `includes/settings/commerce.save.php:483`, `email_campaign_job.php:129`,
  `mysqldump.php:2171,2183` (her biri `UNLOCK TABLES` ile; 14 = 7 kilit + 7
  açma). InnoDB'de aynen çalışır; dokunulmadı.
- **`check_and_repair_database_tables()`** (`includes/fn/system_status.php`):
  hızlı taramada `CHECK TABLE … QUICK`/`REPAIR` yalnız
  `MYISAM`/`ARIA`/`MRG_MYISAM`/`ISAM` için; InnoDB zaten dışarıda.
- **`OPTIMIZE TABLE`:** kod tabanında kullanılmıyor (`optimize.php` görsel
  sıkıştırmadır).
- **`SELECT COUNT(*) FROM log`:** `includes/fn/output.php` menü rozeti, 60 sn
  oturum önbelleği. InnoDB'de tam sayım; değiştirilmedi.
- **Döküm geri yükleme:** `install/index.php` `parse_mysql_dump()` dökümdeki
  `SET autocommit=0`'ı bağlantıda açık bırakıyordu; 2026.4.8'de sonuna
  `SET autocommit = 1` eklendi (ayrıntı `docs/degisiklikler.md`).

## Tablo listesi

| Tablo | Kaynak | Grup | AUTO_INCREMENT = PK ilk kolon | FULLTEXT | MyISAM'a özgü seçenek | 767 bayt üstü indeks |
|---|---|---|---|---|---|---|
| `next_order_number` | döküm | orders | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `orders` | döküm | orders | evet (`id`) | — | yok | — |
| `order_items` | döküm | orders | evet (`id`) | — | yok | — |
| `order_item_gift_cards` | döküm | orders | evet (`id`) | — | yok | — |
| `applied_gift_cards` | döküm | orders | evet (`id`) | — | yok | — |
| `gift_cards` | döküm | orders | evet (`id`) | — | yok | — |
| `ship_tos` | döküm | orders | evet (`id`) | — | yok | — |
| `shipping_tracking_numbers` | döküm | orders | evet (`id`) | — | yok | — |
| `verified_shipping_addresses` | döküm | orders | evet (`id`) | — | yok | — |
| `address_book` | döküm | orders | evet (`id`) | — | yok | — |
| `key_codes` | döküm | orders | evet (`id`) | — | yok | — |
| `commissions` | döküm | orders | evet (`id`) | — | yok | — |
| `recurring_commission_profiles` | döküm | orders | evet (`id`) | — | yok | — |
| `order_reports` | döküm | orders | evet (`id`) | — | yok | — |
| `order_report_filters` | döküm | orders | evet (`id`) | — | yok | — |
| `offers` | döküm | orders | evet (`id`) | — | yok | — |
| `offer_actions` | döküm | orders | evet (`id`) | — | yok | — |
| `offer_rules` | döküm | orders | evet (`id`) | — | yok | — |
| `offer_rules_products_xref` | döküm | orders | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `offers_offer_actions_xref` | döküm | orders | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `offer_actions_shipping_methods_xref` | döküm | orders | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `order_refunds` | migration: 2026.1.14.php | orders | evet (`id`) | — | yok | — |
| `iyzipay_3ds_state` | migration: 2026.1.php | orders | evet (`id`) | — | yok | — |
| `local_sale_history` | migration: legacy.php (2022.2.1) | orders | evet (`id`) | — | yok | — |
| `local_sale_history_items` | migration: legacy.php (2022.2.1) | orders | evet (`id`) | — | yok | — |
| `shipping_methods` | döküm | orders | evet (`id`) | — | yok | — |
| `shipping_methods_zones_xref` | döküm | orders | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `shipping_rates` | döküm | orders | evet (`id`) | — | yok | — |
| `shipping_cutoffs` | döküm | orders | evet (`id`) | — | yok | — |
| `shipping_delivery_dates` | döküm | orders | evet (`id`) | — | yok | — |
| `ship_date_adjustments` | döküm | orders | evet (`id`) | — | yok | — |
| `products` | döküm | products | evet (`id`) | — | yok | — |
| `product_groups` | döküm | products | evet (`id`) | — | yok | — |
| `products_groups_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `product_attributes` | döküm | products | evet (`id`) | — | yok | — |
| `product_attribute_options` | döküm | products | evet (`id`) | — | yok | — |
| `products_attributes_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `product_groups_attributes_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `products_images_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `product_groups_images_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `products_zones_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `product_submit_form_fields` | döküm | products | evet (`id`) | — | yok | — |
| `product_barcodes` | migration: 2026.1.16.php | products | evet (`id`) | — | yok | — |
| `tag_cloud_keywords` | döküm | products | evet (`id`) | — | yok | — |
| `tag_cloud_keywords_xref` | döküm | products | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `contacts` | döküm | people | evet (`id`) | — | yok | — |
| `contact_groups` | döküm | people | evet (`id`) | — | yok | — |
| `contacts_contact_groups_xref` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `users_contact_groups_xref` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `log` | döküm | people | evet (`log_id`) | — | yok | — |
| `email_recipients` | döküm | people | evet (`id`) | — | yok | — |
| `email_campaigns` | döküm | people | evet (`id`) | — | yok | — |
| `email_campaign_profiles` | döküm | people | evet (`id`) | — | yok | — |
| `contact_groups_email_campaigns_xref` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `opt_in` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `messages` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `users_messages_xref` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `comments` | döküm | people | evet (`id`) | — | yok | — |
| `allow_new_comments_for_items` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `submitted_form_info` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `submitted_form_views` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `form_data` | döküm | people | evet (`id`) | — | yok | — |
| `forms` | döküm | people | evet (`id`) | — | yok | `address_name(250)` = 1000 bayt |
| `form_fields` | döküm | people | evet (`id`) | — | yok | — |
| `form_field_options` | döküm | people | evet (`id`) | — | yok | — |
| `form_list_view_browse_fields` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `form_list_view_filters` | döküm | people | evet (`id`) | — | yok | — |
| `form_view_directories_form_list_views_xref` | döküm | people | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `notifications` | migration: legacy.php | people | evet (`id`) | — | yok | — |
| `visitor_reports` | döküm | people | evet (`id`) | — | yok | — |
| `visitor_report_filters` | döküm | people | evet (`id`) | — | yok | — |
| `referral_sources` | döküm | people | evet (`id`) | — | yok | — |
| `config` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `page` | döküm | site | evet (`page_id`) | — | yok | — |
| `style` | döküm | site | evet (`style_id`) | — | yok | — |
| `pregion` | döküm | site | evet (`pregion_id`) | — | yok | — |
| `cregion` | döküm | site | evet (`cregion_id`) | — | yok | — |
| `dregion` | döküm | site | evet (`dregion_id`) | — | yok | — |
| `folder` | döküm | site | evet (`folder_id`) | — | yok | — |
| `aclfolder` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `menus` | döküm | site | evet (`id`) | — | yok | — |
| `menu_items` | döküm | site | evet (`id`) | — | yok | — |
| `users_menus_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `containers` | döküm | site | evet (`id`) | — | yok | — |
| `system_style_cells` | döküm | site | evet (`id`) | — | yok | — |
| `system_theme_css_rules` | döküm | site | evet (`id`) | — | yok | — |
| `preview_styles` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `dashboard` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `users_common_regions_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `login_regions` | döküm | site | evet (`id`) | — | yok | — |
| `affiliate_sign_up_form_pages` | döküm | site | evet (`id`) | — | yok | — |
| `order_form_pages` | döküm | site | evet (`id`) | — | yok | — |
| `shipping_address_and_arrival_pages` | döküm | site | evet (`id`) | — | yok | — |
| `custom_form_pages` | döküm | site | evet (`id`) | — | yok | — |
| `search_results_pages` | döküm | site | evet (`id`) | — | yok | — |
| `form_item_view_pages` | döküm | site | evet (`id`) | — | yok | — |
| `shipping_method_pages` | döküm | site | evet (`id`) | — | yok | — |
| `billing_information_pages` | döküm | site | evet (`id`) | — | yok | — |
| `photo_gallery_pages` | döküm | site | evet (`id`) | — | yok | — |
| `update_address_book_pages` | döküm | site | evet (`id`) | — | yok | — |
| `catalog_pages` | döküm | site | evet (`id`) | — | yok | — |
| `email_a_friend_pages` | döküm | site | evet (`id`) | — | yok | — |
| `order_receipt_pages` | döküm | site | evet (`id`) | — | yok | — |
| `calendar_view_pages` | döküm | site | evet (`id`) | — | yok | — |
| `form_view_directory_pages` | döküm | site | evet (`id`) | — | yok | — |
| `catalog_detail_pages` | döküm | site | evet (`id`) | — | yok | — |
| `order_preview_pages` | döküm | site | evet (`id`) | — | yok | — |
| `form_list_view_pages` | döküm | site | evet (`id`) | — | yok | — |
| `folder_view_pages` | döküm | site | evet (`id`) | — | yok | — |
| `custom_form_confirmation_pages` | döküm | site | evet (`id`) | — | yok | — |
| `express_order_pages` | döküm | site | evet (`id`) | — | yok | — |
| `shopping_cart_pages` | döküm | site | evet (`id`) | — | yok | — |
| `calendar_event_view_pages` | döküm | site | evet (`id`) | — | yok | — |
| `calendars` | döküm | site | evet (`id`) | — | yok | — |
| `calendar_events` | döküm | site | evet (`id`) | — | yok | — |
| `calendar_event_locations` | döküm | site | evet (`id`) | — | yok | — |
| `calendar_events_calendars_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `calendar_events_calendar_event_locations_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `calendar_views_calendars_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `calendar_event_views_calendars_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `calendar_event_exceptions` | döküm | site | evet (`id`) | — | yok | — |
| `users_calendars_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `remaining_reservation_spots` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `zones` | döküm | site | evet (`id`) | — | yok | — |
| `zones_countries_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `zones_states_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `tax_zones` | döküm | site | evet (`id`) | — | yok | — |
| `tax_zones_countries_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `tax_zones_states_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `countries` | döküm | site | evet (`id`) | — | yok | — |
| `states` | döküm | site | evet (`id`) | — | yok | — |
| `currencies` | döküm | site | evet (`id`) | — | yok | — |
| `arrival_dates` | döküm | site | evet (`id`) | — | yok | — |
| `excluded_transit_dates` | döküm | site | evet (`id`) | — | yok | — |
| `banned_ip_addresses` | döküm | site | evet (`id`) | — | yok | — |
| `cookies` | döküm | site | evet (`id`) | — | yok | — |
| `watchers` | döküm | site | evet (`id`) | — | yok | — |
| `target_options` | döküm | site | evet (`id`) | — | yok | — |
| `short_links` | döküm | site | evet (`id`) | — | yok | — |
| `auto_dialogs` | döküm | site | evet (`id`) | — | yok | — |
| `ads` | döküm | site | evet (`id`) | — | yok | — |
| `ad_regions` | döküm | site | evet (`id`) | — | yok | — |
| `users_ad_regions_xref` | döküm | site | AUTO_INCREMENT yok, PK yok | — | yok | — |
| `search_items` | döküm | search | evet (`id`) | 4 (content, title, description, keywords) | yok | `url(250)` = 1000 bayt |
