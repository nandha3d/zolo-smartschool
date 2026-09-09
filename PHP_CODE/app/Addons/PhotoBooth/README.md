# ID Card Photo Booth

Captures student photographs from a webcam and writes them to the student's existing
profile image, so the stock ID card generator can print them without a separate upload
step.

## Why it is shaped like this

The platform already generates ID cards (`Certificate & ID Card → Student ID Card`) but
has no way to *take* a photo — every student photograph has to be uploaded as a file.
This addon fills that gap without touching the ID card generator, the student model, or
the upload pipeline: it writes to the same `users.image` column the existing bulk photo
upload writes to, through the same `UploadService`.

## What lives where

```
app/Addons/PhotoBooth/
├── PhotoBoothServiceProvider.php     routes + views + migrations + translations
├── Http/Controllers/PhotoBoothController.php
├── routes/web.php                    /photo-booth, /photo-booth/roster, /photo-booth/capture
├── resources/views/index.blade.php   the booth screen
├── database/migrations/              registers the sellable feature row
├── lang/en.json                      fallback wording for this addon's strings
└── README.md
```

Only two lines outside this directory changed:

- `config/app.php` — provider registration.
- `resources/views/layouts/sidebar.blade.php` — one menu entry under *Certificate & ID Card*.

No existing controller, model, migration, service or route was modified.

## Turning it on

The migration seeds a feature named **ID Card Photo Booth** with `is_default = 0`, so no
school gets it automatically. A Super Admin activates it the same way as any other
feature — attach it to a package, or sell it as an addon. Until then the routes return
the standard no-feature response and the sidebar entry does not render.

Within a school it additionally requires the `student-edit` permission.

## Requirements

`getUserMedia` is only available on a **secure origin**. The booth works on `https://`
and on `http://localhost`; on a plain `http://` host the browser withholds the camera API
and the screen says so rather than failing silently.

## How a capture is handled

1. The operator picks a class section; the roster shows who already has a photo.
2. The preview is centre-cropped to 3:4 and drawn to a 600×800 canvas, so every card in a
   class is framed identically.
3. The JPEG is posted to `photo-booth.capture`, validated as an image, and passed to
   `UploadService::upload()`, which re-encodes it and scopes the path to the school.
4. The previous photo is deleted only after the new path is committed, so a failure can
   never leave a student with no photo at all.

Student lookups run on the tenant connection the request is already switched to
(`SwitchDatabase`), so an id belonging to another school does not resolve.
