---
name: design-template-salon-words
description: Beauty Salon template wording (1.1.1, #4807): hizmet and randevu, own keys for the booking field, staff shortcut and statuses, e-posta page names; sample days and times fit the opening hours (#4944).
---

## Which Turkish words does it use? (1.1.1, #4807)
- "Hizmet" and "randevu" throughout: the booking field is `Treatment` ("Hizmet"; shared `Service` is "Servis"), the staff shortcut `Every appointment, newest first, with its status.`, the statuses Requested, Confirmed, `Appointment completed` ("Tamamlandı"), `Appointment cancelled` ("İptal edildi"); shared `Completed`/`Cancelled` stay the panel's. E-mail page names start "e-posta-". Installed sites keep stored labels and status values. Test: `test_salon_turkish_words`.

## Do the sample days and times agree with the opening hours? (#4944)
- Yes. Hours: Monday closed, Tuesday–Friday 10:00–20:00, Saturday 09:00–19:00, Sunday 11:00–17:00 (`$hours` in `parts.php`). No stylist works on a Monday (Deniz Tuesday to Saturday, Leyla Thursday to Sunday); the booking times start at 10:00 and the evening one says `Tuesday–Friday`. Test: `test_salon_sample_days_and_times_fit_the_hours`. Before #4944 two stylists worked Mondays and the form offered 09:00 and evenings on every day (#4805).
