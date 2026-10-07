# PineGrap `lang()` Kullanım Rehberi

## 1. Basit string
```php
lang('Page Name')
// tr: "Page Name": "Sayfa Adı"
```

---

## 2. Değişken içeren string — `{var:N}`
```php
lang(array('string' => '{var:1} Page(s) title updated.', 'vars' => array($count)))
// tr: "{var:1} Page(s) title updated.": "{var:1} sayfa başlığı güncellendi."
```

---

## 3. Suffix (çoğul eki) — `{suffix:N}`
```php
lang(array(
    'string' => '{var:1} folder{suffix:1}',
    'vars'   => array($folder_text),
    'suffix' => array($folder_suffix)
))
// tr: "{var:1} folder{suffix:1}": "{var:1} klasör"
```

---

## 4. Birden fazla değişken + suffix karışık
```php
lang(array(
    'string' => '{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]',
    'vars'   => array($folder_text, $and_text, $file_text, implode(', ', $deleted_names)),
    'suffix' => array($folder_suffix, $file_suffix)
))
// tr: "{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]":
//     "Temizleme aracıyla {var:1}{var:2}{var:3} silindi. [{var:4}]"
```

> **Kural:** Ek bilgi (dosya adları, liste vb.) string dışına `implode` ile **concatenate edilmez**.
> Her zaman `{var:N}` olarak `lang()` içine alınır.

---

## 5. YANLIŞ kullanım — asla yapma

```php
// YANLIŞ: string dışında concatenate
log_activity(
    lang(array('string' => '...deleted.', ...)) . ' [' . implode(', ', $names) . ']',
    $user
);

// DOĞRU: {var:4} olarak lang içinde
log_activity(
    lang(array(
        'string' => '...deleted. [{var:4}]',
        'vars'   => array($folder_text, $and_text, $file_text, implode(', ', $names)),
        'suffix' => array($folder_suffix, $file_suffix)
    )),
    $user
);
```

---

## 6. Çeviri dosyası — `includes/local/tr.json`

- Her yeni `lang()` string'i için tr.json'a karşılık eklenir.
- Key **tam olarak** PHP'deki string ile aynı olmalı (boşluk, noktalama dahil).
- `{var:N}` ve `{suffix:N}` placeholder'ları çeviride korunur.

```json
"{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]":
    "Temizleme aracıyla {var:1}{var:2}{var:3} silindi. [{var:4}]",
```
