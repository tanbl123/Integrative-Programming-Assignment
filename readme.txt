================================================================================
ECOCAMPUS WASTE MANAGEMENT SYSTEM
BMIT3173 Integrative Programming - Assignment 202605
================================================================================

Programme        : RSD
Tutorial Group   : 1
Group            : D
SDG              : SDG 11 - Sustainable Cities and Communities

TEAM AND MODULES
--------------------------------------------------------------------------------
  Ong Kar Heng     2408830   Bin & Location Management          Proxy
  Tan Boon Leong   2402865   Complaint / Report Management      Observer
  Ng Zi Zhang      2406898   Collection Scheduling & Assignment Strategy
  Phang Jun Hong   2406646   User & Access Management           Decorator

Every source file carries its author's name in the header comment.


================================================================================
1. WHAT YOU NEED
================================================================================

  - XAMPP with Apache and MySQL/MariaDB  (PHP 8.1 or newer)
  - A web browser
  - NetBeans, only if you want to open the project in the IDE

The system was developed and tested on XAMPP with PHP 8.2 and
MariaDB 10.4.32.


================================================================================
2. INSTALLING
================================================================================

STEP 1 - Put the folder in htdocs
--------------------------------------------------------------------------------
Extract this project so that it sits directly inside the XAMPP htdocs
folder, in a folder named exactly:

    C:\xampp\htdocs\EcoCampus

The folder name matters. The application builds its own URLs from it, so a
different name means the links and the REST endpoints will not resolve.
After extracting, C:\xampp\htdocs\EcoCampus\index.php must exist.


STEP 2 - Start the server
--------------------------------------------------------------------------------
Open the XAMPP Control Panel and start Apache and MySQL. Both must show
green before continuing.


STEP 3 - Create the database
--------------------------------------------------------------------------------
Open http://localhost/phpmyadmin

The quickest complete setup, with demonstration data already in it:

    Import  ->  database/14_ecocampus_export.sql  ->  Go

That single file creates the ecocampus database, every table, and the rows
used in the demonstration. It creates the database itself, so no database
needs to be selected first, and nothing else needs to be run afterwards.

    IMPORTANT: do NOT also run files 01 to 19 after this one. Everything
    they build is already inside it. The file has no DROP TABLE statements,
    so importing it over an existing ecocampus database will stop at the
    first table that already exists. To start again, drop the ecocampus
    database first, then import this file on its own.

As an alternative clean setup without the demonstration data, create an
empty database named ecocampus and import, in this order:

    database/01_schema.sql      creates every table
    database/02_seed.sql        sample users, bins, locations, complaints

Files 03 to 19 are the numbered migrations. They exist as the record of how
the schema was built up and who owns each table, and are only needed to
bring an OLDER existing database up to date. A fresh install never runs
them.


STEP 4 - Database password (only if yours has one)
--------------------------------------------------------------------------------
config/config.php uses the XAMPP defaults: user "root" with no password. If
your MySQL root account has a password, create a new file

    config/config.local.php

containing:

    <?php
    define('DB_PASS', 'your_password_here');

That file is deliberately ignored by Git, so no password is ever committed.
config/config.php itself does not need to be edited.


================================================================================
3. RUNNING
================================================================================

Open:

    http://localhost/EcoCampus/

The sign-in page appears. The dashboard after signing in shows bin and user
counts read live from the database - if those numbers appear, MVC, the ORM
and the database connection are all working.

SAMPLE ACCOUNTS  (password for all of them: password123)
--------------------------------------------------------------------------------
    admin@ecocampus.my            Administrator
    zizhang@ecocampus.my          Administrator
    siti@student.ecocampus.my     Reporter
    weijie@student.ecocampus.my   Reporter
    raj@staff.ecocampus.my        Reporter
    zaki@cleaner.ecocampus.my     Cleaner
    mary@cleaner.ecocampus.my     Cleaner

Each role sees a different system. Sign in as the Administrator to manage
bins, users, complaints and schedules; as a Reporter to submit and track
your own complaints; as a Cleaner to record collections.


================================================================================
4. OPENING THE PROJECT IN NETBEANS
================================================================================

    File  ->  Open Project...  ->  select the EcoCampus folder

NetBeans recognises it as a PHP project from nbproject/project.xml and
nbproject/project.properties, both of which are included. The project is
configured to run as a local website at http://localhost/EcoCampus/, so
Run Project opens that address in the browser.

The project is NOT copied to another folder when it runs - it is served
from where it sits in htdocs, which is why Step 1 matters.


================================================================================
5. WHERE THINGS ARE
================================================================================

    index.php                 Front controller - every request enters here
    .htaccess                 Rewrites all URLs to index.php
    config/config.php         Database and application settings
    app/core/                 Router, base Controller, Database, ORM Model
    app/controllers/          One controller per module, plus the REST APIs
    app/models/               Entity classes
    app/views/                Presentation only - no SQL
    database/                 SQL scripts (see section 2)
    public/css, public/js     Stylesheet and shared client-side scripts
    uploads/                  Complaint photo evidence
    tests/                    PowerShell REST regression scripts
    nbproject/                NetBeans project files

WEB SERVICES
--------------------------------------------------------------------------------
Every module both exposes and consumes JSON REST services, following the
Interface Agreement: requests carry requestId and timeStamp, and responses
carry status (S, F or E), timeStamp and the requestId echoed back.

The service files are the *ApiController.php files in app/controllers/ and
the *ServiceClient.php files in app/services/. Signed in as an
Administrator, the endpoints can be opened directly in the browser, for
example:

    http://localhost/EcoCampus/complaint-api/unresolved
    http://localhost/EcoCampus/bin-api
    http://localhost/EcoCampus/bin-api/show/1
    http://localhost/EcoCampus/user-api/cleaners
    http://localhost/EcoCampus/schedule-api/mine

They require a signed-in session with the right role, so open them in the
same browser you signed in with. A caller without the permission receives a
proper IFA failure response rather than data.


================================================================================
6. ANYTHING ELSE WORTH KNOWING
================================================================================

UPLOADED PHOTOS
    Complaint photographs live in uploads/. That folder carries an .htaccess
    denying all direct web access - photographs are served only through
    ComplaintController::attachment(), which re-checks on every request that
    the signed-in user is allowed to see that complaint. The demonstration
    photos are included so the seeded complaints do not show broken images.

ERROR DISPLAY
    config/config.php defines DEBUG. It is set to false so that visitors
    never see raw error text. Set it to true while developing if you want
    PHP errors shown on screen.

NOTHING IS EVER HARD DELETED
    Users, bins, locations, complaints and schedules are soft-deleted: the
    row and its history stay in the database and the record simply stops
    appearing in the listings. This is deliberate - the system is an
    accountability record, and a complaint together with the audit trail
    explaining how it was handled must not be able to disappear.

REGRESSION TESTS
    The scripts in tests/ exercise the REST services against a running
    installation. From PowerShell, in the project folder:

        .\tests\user-rest.ps1
        .\tests\bin-location-rest.ps1
        .\tests\module-rest.ps1

    They create uniquely named test rows, check permissions and CRUD
    behaviour, and remove only those exact rows when they finish.

IF SOMETHING DOES NOT WORK
    - A blank page or "Table already exists" on import usually means the
      database was imported twice. Drop the ecocampus database and import
      database/14_ecocampus_export.sql once, on its own.
    - "Access denied for user 'root'" means MySQL has a password. See
      Step 4.
    - Links leading to a 404 usually means the folder is not named exactly
      EcoCampus, or Apache's mod_rewrite is off.

================================================================================
