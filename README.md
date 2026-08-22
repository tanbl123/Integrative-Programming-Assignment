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
<http://localhost/phpmyadmin> and import, **in this order**:

1. `database/01_schema.sql` — creates the `ecocampus` database and its tables
2. `database/02_seed.sql` — inserts sample bins, users and complaints

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

### Sample accounts

All seeded accounts use the password `password123`.

| Email | Role |
|---|---|
| admin@ecocampus.edu.my | Administrator |
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

---

## Working as a team

Pull before you start each session:

**NetBeans → Team → Git → Remote → Pull…**

Put your name in the header comment of every file you create — the
assignment brief requires it.
