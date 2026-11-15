<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the zone a TZID parameter of an iCalendar time names. Outlook
 * and Exchange write their Windows zone names ("GMT Standard Time") or a
 * display label ("(UTC+03:00) Istanbul") instead of an IANA name; both are
 * turned into a zone PHP knows, so an invitation or a person's own calendar
 * read on a site in another zone keeps the sender's time.
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
 * The Windows zone names and the IANA zone each stands for (CLDR
 * windowsZones, territory 001). A zone renamed in a recent tz release
 * (Kyiv, Nuuk, Yangon) lists its new and old name, '|' between them: an
 * older database knows only the old one, a system database without the
 * backward links only the new one.
 *
 * @return string[] lower-case Windows name => IANA name(s)
 */
function ws_ical_windows_zones()
{
    return array(
        'dateline standard time' => 'Etc/GMT+12',
        'utc-11' => 'Etc/GMT+11',
        'aleutian standard time' => 'America/Adak',
        'hawaiian standard time' => 'Pacific/Honolulu',
        'marquesas standard time' => 'Pacific/Marquesas',
        'alaskan standard time' => 'America/Anchorage',
        'utc-09' => 'Etc/GMT+9',
        'pacific standard time (mexico)' => 'America/Tijuana',
        'utc-08' => 'Etc/GMT+8',
        'pacific standard time' => 'America/Los_Angeles',
        'us mountain standard time' => 'America/Phoenix',
        'mountain standard time (mexico)' => 'America/Mazatlan',
        'mountain standard time' => 'America/Denver',
        'yukon standard time' => 'America/Whitehorse',
        'central america standard time' => 'America/Guatemala',
        'central standard time' => 'America/Chicago',
        'easter island standard time' => 'Pacific/Easter',
        'central standard time (mexico)' => 'America/Mexico_City',
        'mexico standard time' => 'America/Mexico_City',
        'mexico standard time 2' => 'America/Chihuahua',
        'canada central standard time' => 'America/Regina',
        'sa pacific standard time' => 'America/Bogota',
        'eastern standard time (mexico)' => 'America/Cancun',
        'eastern standard time' => 'America/New_York',
        'haiti standard time' => 'America/Port-au-Prince',
        'cuba standard time' => 'America/Havana',
        'us eastern standard time' => 'America/Indiana/Indianapolis',
        'turks and caicos standard time' => 'America/Grand_Turk',
        'paraguay standard time' => 'America/Asuncion',
        'atlantic standard time' => 'America/Halifax',
        'venezuela standard time' => 'America/Caracas',
        'central brazilian standard time' => 'America/Cuiaba',
        'sa western standard time' => 'America/La_Paz',
        'pacific sa standard time' => 'America/Santiago',
        'newfoundland standard time' => 'America/St_Johns',
        'tocantins standard time' => 'America/Araguaina',
        'e. south america standard time' => 'America/Sao_Paulo',
        'sa eastern standard time' => 'America/Cayenne',
        'argentina standard time' => 'America/Argentina/Buenos_Aires',
        'greenland standard time' => 'America/Nuuk|America/Godthab',
        'montevideo standard time' => 'America/Montevideo',
        'magallanes standard time' => 'America/Punta_Arenas',
        'saint pierre standard time' => 'America/Miquelon',
        'bahia standard time' => 'America/Bahia',
        'utc-02' => 'Etc/GMT+2',
        'mid-atlantic standard time' => 'Etc/GMT+2',
        'azores standard time' => 'Atlantic/Azores',
        'cape verde standard time' => 'Atlantic/Cape_Verde',
        'utc' => 'UTC',
        'coordinated universal time' => 'UTC',
        'gmt standard time' => 'Europe/London',
        'greenwich standard time' => 'Atlantic/Reykjavik',
        'sao tome standard time' => 'Africa/Sao_Tome',
        'morocco standard time' => 'Africa/Casablanca',
        'w. europe standard time' => 'Europe/Berlin',
        'central europe standard time' => 'Europe/Budapest',
        'romance standard time' => 'Europe/Paris',
        'central european standard time' => 'Europe/Warsaw',
        'w. central africa standard time' => 'Africa/Lagos',
        'jordan standard time' => 'Asia/Amman',
        'gtb standard time' => 'Europe/Bucharest',
        'middle east standard time' => 'Asia/Beirut',
        'egypt standard time' => 'Africa/Cairo',
        'e. europe standard time' => 'Europe/Chisinau',
        'syria standard time' => 'Asia/Damascus',
        'west bank standard time' => 'Asia/Hebron',
        'south africa standard time' => 'Africa/Johannesburg',
        'fle standard time' => 'Europe/Kyiv|Europe/Kiev',
        'israel standard time' => 'Asia/Jerusalem',
        'south sudan standard time' => 'Africa/Juba',
        'kaliningrad standard time' => 'Europe/Kaliningrad',
        'sudan standard time' => 'Africa/Khartoum',
        'libya standard time' => 'Africa/Tripoli',
        'namibia standard time' => 'Africa/Windhoek',
        'arabic standard time' => 'Asia/Baghdad',
        'turkey standard time' => 'Europe/Istanbul',
        'arab standard time' => 'Asia/Riyadh',
        'belarus standard time' => 'Europe/Minsk',
        'russian standard time' => 'Europe/Moscow',
        'e. africa standard time' => 'Africa/Nairobi',
        'volgograd standard time' => 'Europe/Volgograd',
        'iran standard time' => 'Asia/Tehran',
        'arabian standard time' => 'Asia/Dubai',
        'astrakhan standard time' => 'Europe/Astrakhan',
        'azerbaijan standard time' => 'Asia/Baku',
        'russia time zone 3' => 'Europe/Samara',
        'mauritius standard time' => 'Indian/Mauritius',
        'saratov standard time' => 'Europe/Saratov',
        'georgian standard time' => 'Asia/Tbilisi',
        'caucasus standard time' => 'Asia/Yerevan',
        'armenian standard time' => 'Asia/Yerevan',
        'afghanistan standard time' => 'Asia/Kabul',
        'west asia standard time' => 'Asia/Tashkent',
        'ekaterinburg standard time' => 'Asia/Yekaterinburg',
        'pakistan standard time' => 'Asia/Karachi',
        'qyzylorda standard time' => 'Asia/Qyzylorda',
        'india standard time' => 'Asia/Kolkata',
        'sri lanka standard time' => 'Asia/Colombo',
        'nepal standard time' => 'Asia/Kathmandu',
        'central asia standard time' => 'Asia/Almaty',
        'bangladesh standard time' => 'Asia/Dhaka',
        'omsk standard time' => 'Asia/Omsk',
        'myanmar standard time' => 'Asia/Yangon|Asia/Rangoon',
        'se asia standard time' => 'Asia/Bangkok',
        'altai standard time' => 'Asia/Barnaul',
        'w. mongolia standard time' => 'Asia/Hovd',
        'north asia standard time' => 'Asia/Krasnoyarsk',
        'n. central asia standard time' => 'Asia/Novosibirsk',
        'tomsk standard time' => 'Asia/Tomsk',
        'china standard time' => 'Asia/Shanghai',
        'north asia east standard time' => 'Asia/Irkutsk',
        'singapore standard time' => 'Asia/Singapore',
        'w. australia standard time' => 'Australia/Perth',
        'taipei standard time' => 'Asia/Taipei',
        'ulaanbaatar standard time' => 'Asia/Ulaanbaatar',
        'aus central w. standard time' => 'Australia/Eucla',
        'transbaikal standard time' => 'Asia/Chita',
        'tokyo standard time' => 'Asia/Tokyo',
        'north korea standard time' => 'Asia/Pyongyang',
        'korea standard time' => 'Asia/Seoul',
        'yakutsk standard time' => 'Asia/Yakutsk',
        'cen. australia standard time' => 'Australia/Adelaide',
        'aus central standard time' => 'Australia/Darwin',
        'e. australia standard time' => 'Australia/Brisbane',
        'aus eastern standard time' => 'Australia/Sydney',
        'west pacific standard time' => 'Pacific/Port_Moresby',
        'tasmania standard time' => 'Australia/Hobart',
        'vladivostok standard time' => 'Asia/Vladivostok',
        'lord howe standard time' => 'Australia/Lord_Howe',
        'bougainville standard time' => 'Pacific/Bougainville',
        'russia time zone 10' => 'Asia/Srednekolymsk',
        'magadan standard time' => 'Asia/Magadan',
        'norfolk standard time' => 'Pacific/Norfolk',
        'sakhalin standard time' => 'Asia/Sakhalin',
        'central pacific standard time' => 'Pacific/Guadalcanal',
        'russia time zone 11' => 'Asia/Kamchatka',
        'kamchatka standard time' => 'Asia/Kamchatka',
        'new zealand standard time' => 'Pacific/Auckland',
        'utc+12' => 'Etc/GMT-12',
        'fiji standard time' => 'Pacific/Fiji',
        'chatham islands standard time' => 'Pacific/Chatham',
        'utc+13' => 'Etc/GMT-13',
        'tonga standard time' => 'Pacific/Tongatapu',
        'samoa standard time' => 'Pacific/Apia',
        'line islands standard time' => 'Pacific/Kiritimati',
    );
}

/**
 * The zone a TZID names: an IANA name as it is, a Windows name through
 * ws_ical_windows_zones(), a display label "(UTC+hh:mm) City" or "(UTC) City"
 * as its fixed offset (the label carries no clock-change rule); null for a
 * name that matches none, which the caller reads in the site's zone.
 *
 * @param string $tzid
 * @return DateTimeZone|null
 */
function ws_ical_zone($tzid)
{
    $tzid = trim((string) $tzid);

    if ($tzid === '') {
        return null;
    }

    $windows = ws_ical_windows_zones();
    $key = strtolower($tzid);

    if (isset($windows[$key])) {
        foreach (explode('|', $windows[$key]) as $name) {
            try {
                return new DateTimeZone($name);
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    if (preg_match('/^\(UTC(?:([+-])(\d{1,2}):(\d{2}))?\)/i', $tzid, $label)) {
        return new DateTimeZone(isset($label[1]) ? sprintf('%s%02d:%s', $label[1], (int) $label[2], $label[3]) : 'UTC');
    }

    try {
        return new DateTimeZone($tzid);
    } catch (Exception $e) {
        return null;
    }
}
