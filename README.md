# EcoCampus Waste Management System

**BMIT3173 Integrative Programming** — Assignment 202605
**Group D** · Programme RSD · Tutorial Group 1
**UN SDG 11** — Sustainable Cities and Communities

A web-based system that digitalises campus waste collection: administrators
register and monitor bins, students and staff report waste issues, and
collection is scheduled against bins that genuinely need attention.

---

## Team and modules

| Member | ID | Module | Design pattern |
|---|---|---|---|
| Ong Kar Heng | 2408830 | Bin & Location Management | Proxy |
| Tan Boon Leong | 2402865 | Complaint / Report Management | Observer |
| Ng Zi Zhang | 2406898 | Collection Scheduling & Assignment | Strategy |
| Phang Jun Hong | 2406646 | User & Access Management | Decorator |

---

## Setting up

### 1. Requirements

- XAMPP (Apache + MySQL, PHP 8.1 or newer)
- The project folder must sit inside `htdocs`

### 2. Clone

```
cd C:\xampp\htdocs
git clone https://github.com/tanbl123/Integrative-Programming-Assignment.git EcoCampus
```

### 3. Create the database

Start Apache and MySQL in the XAMPP Control Panel, then open
<http://localhost/phpmyadmin>. For the quickest complete demonstration setup,
start with an empty `ecocampus` database and import, **in this order**:

1. `database/14_ecocampus_export.sql` — complete schema and demonstration data
2. `database/18_history_withdrawn.sql` — adds the newest complaint history event
3. `database/19_location_coordinates.sql` — adds optional OpenStreetMap pins

Do not run files 01 to 17 after file 14. As an alternative clean setup, files
`01_schema.sql` and `02_seed.sql` already contain the current schema and seed
data, including the changes made by migrations 18 and 19.

### 4. Configure (only if needed)

`config/config.php` uses the XAMPP defaults (`root`, no password). If your
MySQL root account has a password, create `config/config.local.php`:

```php
<?php
define('DB_PASS', 'your_password_here');
```

That file is git-ignored, so credentials never reach GitHub.

### 5. Run

<http://localhost/EcoCampus/>

The dashboard shows bin and user counts read live from MySQL. If those
numbers appear, MVC, the ORM and the database connection are all working.

For an older existing database, run each migration it is missing in number
order. The complete migration sequence is:

1. `database/03_bin_location_module.sql`
2. `database/04_user_access_module.sql`
3. `database/05_complaint_module.sql`
4. `database/06_scheduling_module.sql`
5. `database/07_user_demographics.sql`
6. `database/08_module_soft_delete.sql`
7. `database/09_location_soft_delete.sql`
8. `database/10_complaint_notifications.sql`
9. `database/11_complaint_history_change_type.sql`
10. `database/12_complaint_revisions.sql`
11. `database/13_superseded_photos.sql`
12. `database/15_complaint_types.sql`
13. `database/16_drop_type_description.sql`
14. `database/17_type_marks_bin_full.sql`
15. `database/18_history_withdrawn.sql`
16. `database/19_location_coordinates.sql`

The numbered migrations are designed for existing installations. A new
installation should use one of the two clean setup choices above.

### Sample accounts

All seeded accounts use the password `password123`.

| Email | Role |
|---|---|
| admin@ecocampus.my | Administrator |
| siti@student.ecocampus.my | Reporter |
| zaki@cleaner.ecocampus.my | Cleaner |

---

## Architecture

The system follows **MVC** with a custom **ORM**, as the assignment requires.

```
EcoCampus/
├── index.php               Front controller - every request enters here
├── .htaccess               Rewrites all URLs to index.php
├── config/config.php       Database and application settings
├── app/
│   ├── core/
│   │   ├── App.php         Router: URL -> Controller -> method
│   │   ├── Controller.php  Base controller (view rendering, JSON, redirects)
│   │   ├── Database.php    Shared PDO connection, prepared statements only
│   │   ├── Model.php       ORM base: rows <-> objects, relationships
│   │   └── helpers.php     e() output escaping, url(), ifaTimestamp()
│   ├── controllers/        One controller per module
│   ├── models/             Entity classes
│   └── views/              Presentation only, no SQL
├── database/               SQL scripts
├── public/css/             Stylesheet
└── uploads/                Complaint photos (git-ignored)
```

### How the ORM works

`Model` maps database rows to PHP objects. Each entity declares its table
and columns, then inherits `find()`, `all()`, `where()`, `count()`, `save()`
and `delete()`.

Relationships are exposed as **object references rather than foreign keys**,
as the report template requires:

```php
$bin = Bin::find(3);
echo $bin->getLocation()->getFullLabel();   // object reference, not location_id
```

### URL routing

```
/EcoCampus/complaint/view/7
             |        |    |
             |        |    +-- parameter
             |        +------- method     -> view()
             +---------------- controller -> ComplaintController
```

### Bin & Location module

The module is protected by `BinLocationServiceProxy`, a Protection Proxy that
delegates to the real service only after checking the authenticated role.

- Administrator: register/edit/deactivate bins and create/edit locations.
- Cleaner: view bins and record fill-status updates with an audit history.
- Reporter: search and view active bin/location records.

The location directory uses OpenStreetMap pins, searchable location cards, and
active/full/maintenance bin totals. Coordinates are optional so existing
locations continue working until an Administrator pins them from Edit location.

Authenticated JSON endpoints are available at:

- `GET /EcoCampus/bin-api`
- `GET /EcoCampus/bin-api/show/{id}`
- `POST /EcoCampus/bin-api/update-status/{id}` (Cleaner + CSRF token)
- `GET /EcoCampus/location-api`

The Bin details page consumes Scheduling's
`GET /EcoCampus/schedule-api/bin-status/{binId}` REST endpoint through
`ScheduleServiceClient::getBinScheduleStatus()`. This keeps Scheduling's table
access inside its own module and displays the next planned collection on the
Bin page.

### Remaining design patterns and web services

- **Observer:** `ComplaintStatusSubject` broadcasts one complaint event to
  four observers - history, notification, bin flag and revision - whenever a
  complaint is created, changes status, is edited or is withdrawn.
- **Strategy:** `SchedulingStrategyFactory` selects Full Bins, Complaint
  Priority, or Routine bin-selection algorithms at runtime.
- **Decorator:** role decorators add permissions to a basic authenticated
  user profile without modifying the User entity.

Additional authenticated JSON endpoints:

- `GET /EcoCampus/complaint-api/unresolved`
- `GET /EcoCampus/user-api/cleaners`
- `GET /EcoCampus/schedule-api/mine`
- `GET /EcoCampus/schedule-api/bin-status/{binId}`

The schedule generation screen consumes the Bin, Complaint, and User JSON
services to preview eligible work and active cleaners before submission.

### REST CRUD services

Each module exposes database-backed JSON CRUD through its MVC controller and
ORM models. Collection URLs accept `GET` and `POST`; item URLs accept `GET`,
`PUT`, `PATCH`, and `DELETE`:

| Module | Collection URL | Delete behavior |
|---|---|---|
| Users | `/EcoCampus/user-api` | Soft-deletes accounts when no open cleaner assignment exists |
| Bins | `/EcoCampus/bin-api` | Deactivates bins when no open work exists; bins can be reactivated in the web UI |
| Locations | `/EcoCampus/location-api` | Soft-deletes empty locations |
| Complaints | `/EcoCampus/complaint-api` | Soft-deletes eligible complaints and retains Observer history |
| Schedules | `/EcoCampus/schedule-api` | Soft-deletes cancelled/completed schedules and retains assignment history |

Writes require an authenticated session, the correct role permission, JSON
input, and the session CSRF token in the `X-CSRF-Token` header. The existing
`/bin-api`, `/complaint-api/unresolved`, and `/user-api/cleaners` calls in the
schedule form demonstrate service consumption between modules.

Bin, Location, and Bin Scheduling-status requests also follow the Interface
Agreement: send `requestID` or `timeStamp` (`YYYY-MM-DD HH:MM:SS`). Responses
echo `requestID` and include `status` (`S`, `F`, or `E`) and `timeStamp`.

To repeat the local MySQL/HTTP regression checks from PowerShell:

```powershell
.\tests\user-rest.ps1
.\tests\bin-location-rest.ps1
.\tests\module-rest.ps1
```

The scripts create uniquely named test rows, verify permissions and CRUD, and
remove only those exact test rows when they finish.

---

## Working as a team

Pull before you start each session:

**NetBeans → Team → Git → Remote → Pull…**

Put your name in the header comment of every file you create — the
assignment brief requires it.

`readme.txt` in the project root is the plain-text install guide that ships
inside the submitted ZIP. If setup steps change here, change them there too.
