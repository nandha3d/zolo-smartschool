# Library Management

Catalogue, barcoded copies, a scan-driven issue / return desk, renewals, a reservation
queue, overdue fines, an e-book shelf, and endpoints for the student, staff and parent apps.

## Turning it on

```bash
php artisan migrate          # registers the "Library Management" feature (off by default)
php artisan migrate:school   # creates the library tables in every school database
```

A Super Admin then adds **Library Management** to a package or sells it as an addon. The
tenant migration also creates the `library-*` permissions and grants them to each school's
School Admin; other roles (e.g. a *Librarian* custom role) get them from the role editor.

| Permission | Allows |
|---|---|
| `library-list` | dashboard, catalogue, loans, fines, reservations |
| `library-create` / `-edit` / `-delete` | catalogue and copies; `-edit` also settings and member limits |
| `library-circulation` | the issue / return desk, renewals, lost books, collecting / waiving fines |

`php artisan library:refresh` runs nightly (00:30, via the scheduler) to flag overdue loans
with their accrued fine and release reservations that were not collected in time.

## How circulation works

- **Members** are created on first use from any student, teacher or staff account. The desk
  accepts a membership number, admission number, email, mobile or user id.
- **Limits** come from Settings (students and staff separately) unless a member has an override.
  A member with unpaid fines cannot borrow.
- **Copies** get an accession number and a Code 39 barcode (`LB` + book id + copy no. + check
  digit); *Print labels* on the catalogue renders printable stickers without any library.
- **Due dates and fines** skip Sundays and school holidays when *Skip holidays* is on. The fine
  is `working days late − grace period` × `fine per day`, charged on return. Lost books are
  billed at the book's price.
- **Reservations** queue on a title when no copy is on the shelf. A returned copy is held for the
  oldest reservation (status *Ready*, push notification sent) until the hold expires.

Every state change runs in a transaction with the copy row locked.

## E-books

PDF / EPUB files are stored on the private `local` disk (`storage/app/private/library/…`), never
the public disk, and are streamed only to signed-in users through
`library/books/{id}/ebook` (web) or `api/library/ebook/{id}` (apps). Each open is logged in
`library_digital_access_logs`.

## Mobile API (`school-code` header + Sanctum token; guardians add `child_id`)

| Method | Path | |
|---|---|---|
| GET | `api/library/loans` | current loans, fines, reservations, recent history |
| GET | `api/library/catalog?q=` | paginated catalogue with shelf location and availability |
| POST | `api/library/reserve` | `book_id` |
| GET | `api/library/ebook/{id}` | streams the file |

## Not included

- RFID gate antennas / self-checkout kiosks (the desk works with any USB barcode or RFID reader
  that types into the focused field).
- Billing fines onto the main fee invoice — fines are collected at the library desk.
- A watermarking e-book viewer; files are served inline to authorised users only.
