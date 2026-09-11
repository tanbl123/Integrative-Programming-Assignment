# Presentation notes — Bin & Location Management

Ong Kar Heng (2408830) · BMIT3173 · Design pattern: **Proxy**

> Written by reading the code, not by the person who wrote it. Check anything
> that sounds wrong before you say it — you know your own module best.

Getters and setters are left out. Every other function is here.

---

## 1. Opening — 45 seconds

> My module owns the physical side of the system: the bins, where they are, and
> what state they are in. An Administrator registers and edits bins and
> locations, a Cleaner records that a bin has been emptied, and everybody else
> can look one up.
>
> My design pattern is **Proxy** — specifically a **Protection Proxy**. Every
> other module talks to my service through it, and it checks the caller's role
> before letting anything through.

---

## 2. Web services — who provides to whom

| | |
|---|---|
| **I expose** | `GET /bin-api`, `GET /bin-api/show/{id}`, `POST /bin-api/update-status/{id}`, `GET /location-api` |
| **Consumed by** | **Tan Boon Leong** — his complaint detail page shows live bin details<br>**Ng Zi Zhang** — his schedule form lists the bins that need work |
| **I consume** | `GET /schedule-api/bin-status/{binId}` — from **Ng Zi Zhang** |

**My two directions:**

- **I produce → Boon Leong and Zi Zhang consume.** Neither of them reads my
  `bins` table. Boon Leong's complaint page calls `bin-api/show/{id}`; Zi
  Zhang's schedule form calls `bin-api`.
- **I consume → Zi Zhang produces.** My bin details page shows the next planned
  collection for that bin. That belongs to Scheduling, so I call
  `schedule-api/bin-status/{binId}` through `ScheduleServiceClient` rather than
  reading his tables.

**The strongest thing to say:**

> The dependency goes both ways and neither of us touches the other's tables.
> When Boon Leong's module records that a bin was reported full, it does not
> write `bins.fill_status` — it calls my `updateBinStatus()`, so **my**
> validation runs and the change lands in **my** `bin_status_updates` audit
> trail with a reason attached.

---

## 3. The Proxy pattern — the walkthrough

Three files, in this order:

| Say this | File |
|---|---|
| "This is the Subject interface — the contract. Fifteen operations." | `BinLocationServiceInterface.php` |
| "This is the Real Subject. It does the actual work and contains **no permission checks at all**." | `BinLocationService.php` |
| "This is the Proxy. Same interface, so callers cannot tell them apart — but it checks the role first, then delegates." | `BinLocationServiceProxy.php` |

**The line that makes the point** — `BinLocationServiceProxy` line 13:

```php
public function __construct(private BinLocationServiceInterface $realService)
```

> The Proxy holds the real service **through the interface**, not as a concrete
> class. That is what makes it a Proxy rather than a wrapper: it is
> substitutable for the thing it protects.

**Then show the three shapes of check, because they are not all the same:**

1. **Plain authorisation** — `createBin()`, `updateBin()`, `deactivateBin()`,
   `createLocation()`, `updateLocation()`, `deleteLocation()`:
   `authorizeAdministrator()` then delegate.
2. **Filtering what comes back** — `searchBins()` passes
   `$includeInactive && $user->isAdmin()`, so a non-admin asking for inactive
   bins simply does not get them. `findBin()` returns `null` for an inactive
   bin unless you are an Administrator.
3. **Checking the caller is who they claim** — `updateBinStatus()`:

```php
$user = UserPermissions::require('bin.status');
if ($user->getKey() !== $cleanerId) {
    throw new AuthorizationException('A cleaner can only record their own status update.');
}
```

> This is the one worth dwelling on. Having the `bin.status` permission is not
> enough — the cleaner id in the request must be **your own**. Without it, one
> cleaner could record work against another cleaner's name.

**If asked "why not just put the checks in the service?"**

> Then every caller would have to trust that I remembered to write them, and
> there would be no single place to read the rules. Separating them means the
> real service stays about bins, the Proxy stays about who may do what, and I
> can test the bin logic without a logged-in user.

---

## 4. Function reference

### `app/services/BinLocationServiceProxy.php` — the Proxy

Every method has the same shape: check, then delegate to `$this->realService`.

| Function | The check it applies |
|---|---|
| `__construct()` | Takes the real service **through the interface**. |
| `authorizeAdministrator()` | Requires `bin.manage`. |
| `authorizeCleaner()` | Requires `bin.status`. |
| `searchBins()` | Signed in; inactive bins only for an Administrator. |
| `findBin()` | Signed in; hides an inactive bin from anyone but an Administrator. |
| `createBin()` / `updateBin()` / `deactivateBin()` / `reactivateBin()` | Administrator only. |
| `updateBinStatus()` | `bin.status` **and** the cleaner id must be the caller's own. |
| `searchLocations()` / `findLocation()` / `categories()` | Signed in. |
| `createLocation()` / `updateLocation()` / `deleteLocation()` | Administrator only. |

### `app/services/BinLocationService.php` — the Real Subject

| Function | What it does |
|---|---|
| `authorizeAdministrator()` / `authorizeCleaner()` | **Deliberately empty.** The real service does no checking; that is the Proxy's job. Point at these — they are the pattern. |
| `searchBins()` | Bin listing with search, status filter and location filter. |
| `findBin()` | One bin by id. |
| `createBin()` | Validates and saves a new bin. |
| `updateBin()` | Validates and saves changes to one. |
| `deactivateBin()` | Retires a bin, but refuses while it still has open work. |
| `reactivateBin()` | Brings a retired bin back. |
| `updateBinStatus()` | Records a fill-status change **and writes the audit row** to `bin_status_updates` — who changed it, from what, to what, and why. This is the method the Complaint module calls. |
| `searchLocations()` | Location listing with search. |
| `findLocation()` | One location by id. |
| `createLocation()` / `updateLocation()` | Validate and save a location. |
| `deleteLocation()` | Soft-deletes a location, refusing while bins still sit in it. |
| `categories()` | The waste categories a bin can be assigned. |
| `validateBin()` | Server-side rules: code required and unique, location and category must exist, capacity sensible. |
| `validateLocation()` | Same for a location, including the optional map coordinates. |
| `requireBin()` / `requireLocation()` | Fetch or throw, so callers never work on null. |
| `requireScalarFields()` | Refuses a field posted as an array. |

### `app/controllers/BinController.php`

| Function | What it does |
|---|---|
| `index()` | The bin listing, with active/full/maintenance totals. |
| `show()` | One bin, its history, and **the next planned collection fetched from Zi Zhang's web service**. |
| `create()` / `store()` | Registration form / save it. |
| `edit()` / `update()` | Edit form / save it. |
| `deactivate()` / `reactivate()` | Retire a bin or bring it back. |
| `status()` | The form where a Cleaner records a fill-status change. |
| `updateStatus()` | Saves that change, through the Proxy, which checks it is their own id. |
| `renderForm()` | Shared rendering for create and edit. |

### `app/controllers/BinApiController.php` — the service I expose

| Function | What it does |
|---|---|
| `index()` | Every bin as JSON. **Zi Zhang's schedule form calls this.** |
| `show()` | One bin with its location and category. **Boon Leong's complaint page calls this.** |
| `updateStatus()` | Lets a Cleaner record a status change over the API. Needs the CSRF token. |
| `resource()` | Full REST CRUD routed by HTTP method. |
| `serializeBin()` | Turns a `Bin` object into the JSON shape. One method, so every endpoint returns the same fields. |

### `app/controllers/LocationController.php` and `LocationApiController.php`

| Function | What it does |
|---|---|
| `index()` | Location directory, with the OpenStreetMap pins. |
| `create()` / `store()` / `edit()` / `update()` | Maintain a location. |
| `delete()` | Soft-delete an empty one. |
| `renderForm()` | Shared rendering. |
| `LocationApiController::index()` / `show()` / `resource()` | The same data as JSON, in the IFA envelope. |

### `app/services/ScheduleServiceClient.php` — the service I consume

| Function | What it does |
|---|---|
| `getBinScheduleStatus()` | Calls Zi Zhang's `schedule-api/bin-status/{binId}` and returns the next planned collection for a bin. |
| `hasOpenAssignments()` | Asks whether a cleaner still has open work — used before deactivating or deleting. |
| `requestJson()` | Sends the request and checks the IFA `status` is `S`. |
| `send()` | The HTTP call. **Closes the session first**, because PHP locks the session file for a whole request and the app calling itself would otherwise wait forever on its own lock. |
| `sessionCookieHeader()` | Forwards the session cookie so his module sees a signed-in user. |
| `baseUrl()` | Works out the site's own base URL. |
| `newRequestId()` | The IFA request id for the outgoing call. |

### `app/models/Bin.php`

| Function | What it does |
|---|---|
| `statuses()` | Empty, Half, Full, Under Maintenance. |
| `deactivate()` / `reactivate()` | Flip `is_active`. Retiring, not deleting — the history stays. |
| `hasOpenAssignments()` | Is a cleaner still assigned to this bin. |
| `hasOpenWork()` | Assignments **or** open complaints — what deactivation checks. |
| `needsCollection()` | Is the bin Full, i.e. does a strategy want it. |
| `findFullBins()` | **What Zi Zhang's Full Bins strategy selects from.** |
| `findActive()` | Every bin still in service. |
| `findByCode()` | Look up by `BIN-A-001`. |
| `search()` | The listing query with search, status and location filters. |

### `app/models/Location.php`

| Function | What it does |
|---|---|
| `hasCoordinates()` | Whether the map pin has been set. Optional, so older locations keep working. |
| `softDelete()` | Stamps `deleted_at`; every query filters it out. |
| `search()` | Location search. |
| `allAlphabetical()` | Ordered list for dropdowns. |

`BinStatusUpdate` and `WasteCategory` are plain entity classes — accessors only.

---

## 5. Questions the lecturer is likely to ask

**"What kind of Proxy is it?"**
> A Protection Proxy. It controls *access* rather than creating the object
> lazily (Virtual Proxy) or reaching across a network (Remote Proxy).

**"How is it different from the Decorator in Jun Hong's module?"**
> Same shape, different intent. A Decorator **adds behaviour**; a Proxy
> **controls access** to behaviour that already exists. Mine adds nothing to
> what the service does — it decides whether you may call it.

**"Show me that the real service has no checks."**
> `BinLocationService::authorizeAdministrator()` and `authorizeCleaner()` are
> empty method bodies. The interface requires them, and only the Proxy fills
> them in.

**"What stops a controller using the real service directly and skipping you?"**
> Controllers construct the Proxy, and the Proxy is what implements the
> interface everything is typed against. It is a convention rather than a
> language guarantee — but the checks live in one file, so it is visible.

**"Where is the audit trail?"**
> `bin_status_updates`. Every fill-status change records who, from what, to
> what and why — including changes driven by a complaint from another module.

---

## 6. Five-minute demo order

1. Sign in as **admin@ecocampus.my** — bin listing, with the totals.
2. Open a bin → the **next planned collection** is showing, and it came from
   Zi Zhang's web service, not my tables.
3. Sign in as **zaki@cleaner.ecocampus.my** in a second browser — the same bin
   now offers **Record status**. Record it Empty with a remark.
4. Back on the bin page → the audit history shows the change with the reason.
5. As the Cleaner, try to open bin **create** — refused by the Proxy.
6. Open `bin-api/show/1` in the address bar — the service the other two modules
   consume, live.
