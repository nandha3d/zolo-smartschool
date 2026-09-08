# Zolo Schools

Multi-tenant school management platform. Laravel 12, MySQL 8, with two Flutter clients
(student/parent and staff).

Each school gets **its own MySQL database**. The central database holds schools,
packages, subscriptions and the super admin; everything academic — students, classes,
attendance, fees — lives in the tenant database for that school.

---

## Run it locally

Everything below is verified working on this machine.

### 1. Requirements

| | |
|---|---|
| PHP | 8.2+ (running 8.4.20) |
| Extensions | curl, pdo_mysql, zip, gd, mbstring, fileinfo, openssl, exif, bcmath |
| MySQL | 8.0+ |
| Node | only if you want to rebuild front-end assets |

On this machine PHP and MySQL come from ServBay and PHP is **not** on the PATH, so
every command below starts by adding it:

```bash
export PATH="/c/ServBay/packages/php/8.4:$PATH"
cd "D:/PROJECTS/WEBSITES/eSchool SaaS v1.8.0/PHP_CODE"
```

### 2. Serve it

The database is already installed and seeded, so pick whichever you prefer.

**Laravel Herd (recommended — already set up):** <http://zolo-smartschool.test>

Nothing to start; Herd serves it in the background. The site is linked with:

```bash
cd PHP_CODE
herd link zolo-smartschool
```

That link matters. Herd serves `<site>/public`, and this Laravel app is one level
down at `zolo-smartschool/PHP_CODE/public` — so parking the parent folder gives a
"Site not found" 404. Linking from inside `PHP_CODE` points Herd at the right root.
`herd links` lists what is registered.

**Or the built-in server:**

```bash
php artisan serve --host=127.0.0.1 --port=8080
```

Then open <http://127.0.0.1:8080>. Port 8000 is taken by another process on this
machine, hence 8080.

Note that `APP_URL` in `.env` is set to `http://zolo-smartschool.test`. If you switch
to `artisan serve`, change it to match, or uploaded images will be given the wrong
URLs.

### 3. Sign in

Every demo account uses the password **`Demo@12345`**, except the super admin.

| Role | Sign in with | Password | School code |
|---|---|---|---|
| Super Admin | `superadmin@gmail.com` | `Zolo!Schools2026x` | *leave blank* |
| School Admin | `admin@demo.test` | `Demo@12345` | `DEMO001` |
| Teacher | `teacher@demo.test` | `Demo@12345` | `DEMO001` |
| Guardian | `parent@demo.test` | `Demo@12345` | `DEMO001` |
| Student | `DEMO2026001` | `Demo@12345` | `DEMO001` |

The **school code** field on the login form is what selects the tenant database. Leave
it empty to sign in as the super admin, who manages schools rather than belonging to
one. Students sign in with their admission number, not an email address.

Students and guardians cannot use the web panel by design — they are app-only.

### 4. Queue worker (only when creating schools)

Creating a school provisions a whole database, which runs on the queue. Nothing happens
without a worker:

```bash
php artisan queue:work --timeout=3600
```

This takes roughly **28 minutes per school** on this machine, because it runs the full
tenant migration set. That is expected, not a hang.

---

## Rebuilding from scratch

Only needed if you want a clean database.

```bash
php create_db.php                 # creates the central database
php artisan migrate --force       # ~28 minutes
php artisan db:seed --force       # prints the super admin password once
php artisan storage:link
touch storage/installed           # makes the install/* routes 404

php artisan demo:setup            # creates the demo school + tenant database
php artisan demo:content          # classes, subjects, teacher, guardian, student
```

`demo:setup --fresh` drops and rebuilds the demo school. `demo:content` prints the
login table again, so run it any time you forget the credentials.

---

## Useful commands

```bash
php artisan optimize:clear        # clear config, route and view caches
php artisan config:cache          # production caches - all three work
php artisan route:cache
php artisan view:cache
php artisan route:list            # 980 routes
php artisan queue:work --timeout=3600
```

Logs are in `storage/logs/laravel.log`.

---

## The mobile apps

Both live under `../App code/`.

| | Student/parent | Staff |
|---|---|---|
| Directory | `e-school-saas-student-parent/e-school-saas` | `eschool-saas-staff/eschool-saas-staff` |
| Bundle id | `com.zoloschools.app` | `com.zoloschools.staff` |
| Display name | Zolo Schools | Zolo Schools Staff |

```bash
cd "../App code/e-school-saas-student-parent/e-school-saas"
flutter pub get
flutter run
```

Point them at your backend by editing `baseUrl` in `lib/utils/constants.dart`. It must
be the site root with **no trailing slash** — the app appends `/api/`. For a device on
your LAN use your machine's IP, not `127.0.0.1`.

The demo credentials above are pre-filled on both login screens via
`showDefaultCredentials` in the same file. Turn that off for anything real.

**Push notifications are switched off.** `lib/utils/pushMessaging.dart` is a no-op
stand-in, so the apps build and run with no Google project. Local notifications still
work, including chat download progress. To turn push back on: create a Firebase
project, add `firebase_core` and `firebase_messaging` back to `pubspec.yaml`, run
`flutterfire configure`, restore the `com.google.gms.google-services` Gradle plugin,
and repoint the imports in `notificationUtility.dart` and `authRepository.dart`.

To sign a release build you will need to generate your own upload keystore —
`android/key.properties` names one that is not in this tree.

---

## Deploying somewhere real

### Database privileges

The application account needs `CREATE` and `DROP DATABASE`, because it provisions a
schema per school. Do not use root — scope it to a prefix:

```sql
CREATE USER 'zolo'@'localhost' IDENTIFIED BY '...';
GRANT ALL PRIVILEGES ON `zolo_schools_%`.* TO 'zolo'@'localhost';
GRANT ALL PRIVILEGES ON `zolo_schools`.*   TO 'zolo'@'localhost';
GRANT CREATE, DROP ON *.* TO 'zolo'@'localhost';
```

### Scheduler

Billing runs from the scheduler. Add it to system cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Web server

`public/.htaccess` is Apache-only. On nginx, port the rewrite rules by hand and make
sure the **`school-code` request header reaches PHP** — the API uses it to pick the
tenant database.

### Before going live

- `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning`
- `SESSION_SECURE_COOKIE=true`
- Set `CORS_ALLOWED_ORIGINS` to your real origins
- Benchmark school provisioning on the real server and set `SCHOOL_SETUP_TIMEOUT`
- Turn off `showDefaultCredentials` in both apps
- Replace the demo accounts

---

## Security posture

This build has been through a security pass. Briefly:

- Initial passwords are generated, shown once, and must be changed on first sign-in.
  Login locks out for 15 minutes after 5 failed attempts.
- All `whereRaw` search parameters are bound; sort directions are allowlisted.
- The API authenticates through Sanctum's guard, so token expiry and abilities are
  actually enforced.
- Payment webhooks verify their signatures before touching a transaction.
- Uploads take their extension from file content, not the client-supplied name, and
  SVG is rejected.

The one thing still missing is a **test suite** — there are only two stock Laravel
example files. Everything above was verified by hand, so none of it is protected
against regression. That is the first thing worth adding.

Third-party licence notices under `packages/`, `public/assets/ckeditor-4/` and
`public/assets/tinymce/` are the MIT and LGPL notices of those libraries and must stay.
