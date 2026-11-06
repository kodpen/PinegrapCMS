---
name: design-template-blog-cover
description: A design template's blog or article Cover Image input takes image/* and carries _cf.kind = image; templates whose $file helper defaults to documents pass it explicitly (#7513)
---

## Which files does a template's blog cover input accept?
- Only images: `$file('<prefix>-post-cover', 'cover_image', 'public', 'image/*')`. The cover is printed as an `<img>` on the post cards and the post page and is the form's `share_image_field`, so a PDF or Word file showed as a broken image (#7513).
- `'image/*'` is also what makes `$file` add `_cf.kind = image` to the field, so the server refuses other files; an extension list such as `.jpg,.png` does not.

## Why pass the accept argument when other templates do not?
- Each template's `$file` helper in its `parts.php` has its own default. Dealership, Real Estate, Law, Portfolio, Launch, Hotel, App Studio, Gym, Photo, Travel and Market default to a document list (requests, CVs, documents), so their cover call must pass `'image/*'`. Templates whose default is already `'image/*'` (Salon, Social, Courses, Events and the single-file ones) need nothing.
- A new template's blog form follows the same rule; each account test checks the cover input (`test_<template>_account_blog_cover_takes_images_only`).
