# Presentation notes — Complaint / Report Management

Tan Boon Leong (2402865) · BMIT3173 · Design pattern: **Observer**

A crib sheet for presenting the code. Getters and setters are left out. Every
other function in the module is here, with what it does and the one sentence
worth saying about it.

---

## 1. Opening — 45 seconds

> My module is Complaint / Report Management. A Reporter submits a waste
> issue, an Administrator moves it through New → Assigned → Resolved or
> Rejected, and the reporter is told at every step that matters to them.
>
> The idea the whole module is built around is that **a complaint is a
> conversation with a person, not a row in a table.** Everything that happens
> to it is recorded, and everything the reporter needs to know is sent to them.
>
> My design pattern is **Observer**, and it is what makes that possible without
> one class doing four unrelated jobs.

---

## 2. Web services — who provides to whom

Say this as a sentence, not a list: *"I expose one service to Scheduling, and I
consume one from Bins, so communication is bidirectional."*

| Module (owner) | **Exposes** (producer) | **Consumes** (consumer) |
|---|---|---|
| Complaint — **Tan Boon Leong** | `GET /complaint-api/unresolved`<br>`GET/POST/PUT/PATCH/DELETE /complaint-api` | `GET /bin-api/show/{id}` — from **Kar Heng** |
| Bin & Location — Ong Kar Heng | `GET /bin-api`, `/bin-api/show/{id}`<br>`GET /location-api` | `GET /schedule-api/bin-status/{binId}` — from Zi Zhang |
| Scheduling — Ng Zi Zhang | `GET /schedule-api/mine`<br>`GET /schedule-api/bin-status/{binId}` | `/bin-api`, **`/complaint-api/unresolved`**, `/user-api/cleaners` |
| User & Access — Phang Jun Hong | `GET /user-api`, `/user-api/cleaners` | `GET /schedule-api` (via `ScheduleServiceClient`) |

**My two directions, precisely:**

- **I produce → Zi Zhang consumes.** `complaint-api/unresolved` returns every
  complaint still New or Assigned. His schedule-generation form calls it to
  show which bins actually have reports against them.
  Point at `app/views/schedule/form.php` line 27 — `data-complaint-api` — that
  is his page calling my service.
- **I consume → Kar Heng produces.** The complaint detail page shows the bin's
  live fill status. I do **not** read his `bins` table; I call
  `bin-api/show/{id}` through `BinServiceClient`.

**If asked "why not just query the table?"**

> Because then his module's rules would not run. When a complaint reports a bin
> as full, my observer calls his service too — so the change is validated and
> written into his `bin_status_updates` audit trail with a reason. I had it
> writing `bins.fill_status` directly at first, and a bin could turn Full with
> nothing anywhere to say what did it.

---

## 3. The Observer pattern — the walkthrough

Open `app/services/ComplaintStatusSubject.php`. Everything is in one file, so
you can scroll top to bottom while you talk.

**The four roles, in the order they appear:**

| Say this | Point at |
|---|---|
| "`ComplaintObserver` is the Observer interface. One method, `changed()`, and three event constants." | top of the file |
| "`ComplaintStatusSubject` is the Subject. It holds an array of observers and broadcasts. It is typed against the interface only — it has no idea what any of them do." | bottom of the file |
| "`ComplaintService` is the Client. It attaches four observers in its constructor and after that only ever calls `notify()`." | `ComplaintService::__construct()` |
| "These four are the Concrete Observers." | the four classes in between |

**The four observers, one line each:**

1. **`ComplaintHistoryObserver`** — writes the audit row. Reacts to all three events.
2. **`ComplaintNotificationObserver`** — writes the notification. Administrators for a new complaint, the reporter for everything that affects them.
3. **`ComplaintBinFlagObserver`** — tells the Bin module the bin was reported full. Ignores an edit entirely.
4. **`ComplaintRevisionObserver`** — keeps what an edit replaced. Ignores everything except an edit.

**The strongest thing you can say — the proof it is loosely coupled:**

> The fourth observer was added last. The history can record *that* a complaint
> was edited, but not what it said, because `remarks` is 255 characters and a
> description can be 2,000. So I wrote `ComplaintRevisionObserver`. Adding it
> changed **neither `ComplaintService` nor `ComplaintStatusSubject`** — one new
> class and one `attach()` call. That is the Open/Closed Principle, and I can
> show it in the diff rather than just claiming it.

**The demonstration (Table 4.4 in my report):** one submission, four observers,
three tables written and one deliberately not.

> The row that did **not** move matters as much as the three that did.
> `ComplaintRevisionObserver` received the same event and decided it did not
> apply. An observer declining an event is the pattern working, not a reaction
> that failed to fire.

---

## 4. Function reference

### `app/services/ComplaintStatusSubject.php` — the pattern

| Function | What it does |
|---|---|
| `ComplaintObserver::changed()` | The interface. Receives the complaint, old and new status, who acted, the remark, which of the three events it is, what the complaint said before, and any message typed for the reporter. Each observer decides for itself whether the event concerns it. |
| `ComplaintStatusSubject::attach()` | Adds an observer to the list. |
| `ComplaintStatusSubject::detach()` | Removes one. Exists so a test can run the subject without a given reaction. |
| `ComplaintStatusSubject::count()` | How many are attached. Used in testing. |
| `ComplaintStatusSubject::notify()` | Loops the list and calls `changed()` on each, in order. **This is the whole Subject.** |
| `ComplaintHistoryObserver::changed()` | Writes one row to `complaint_status_history`. `change_type` separates a status change from an edit from a withdrawal — an edit and a withdrawal carry the same status on both sides, so without it the history would read "New → New". |
| `ComplaintNotificationObserver::changed()` | Picks the audience and the wording from the event. New complaint → Administrators. Assigned, back-to-New, Resolved, Rejected → the reporter. Withdrawn → the other Administrators. Appends the administrator's typed message when there is one. |
| `ComplaintBinFlagObserver::__construct()` | Takes the Bin module's service. Injected so a test can watch what it asks for without a database. |
| `ComplaintBinFlagObserver::changed()` | On a new report whose **type** says it means "full", asks the Bin service to record the bin as Full. On a resolution or withdrawal, releases the bin if nothing else is open against it. Skips inactive bins and bins under maintenance. |
| `ComplaintRevisionObserver::changed()` | On an edit only, writes the old wording, type, bin and photo to `complaint_revisions`. |

### `app/services/ComplaintService.php` — the business rules

| Function | What it does |
|---|---|
| `__construct()` | Creates the Subject and attaches the four observers. **The Client role of the pattern.** |
| `create()` | Validates, stores the photo, saves the complaint, raises the event — all inside one transaction, so if an observer fails nothing is left half-written. |
| `searchVisible()` | Asks the **user object** which complaints are theirs. A Reporter's own; an Administrator's everything. |
| `archivedFor()` | Same question for withdrawn and deleted complaints. |
| `findArchived()` | Opens one archived complaint, read-only, applying the same ownership rule. |
| `findVisible()` | Opens one live complaint, or throws if it belongs to someone else. |
| `updateStatus()` | The lifecycle. Checks the step is allowed, checks the remark, saves, raises the event. **Joins the caller's transaction instead of starting its own**, because Scheduling calls this from inside one and PDO refuses a nested `beginTransaction()`. |
| `requiresCollectionForAssigned()` | Whether this issue type needs a cleaner booked before Assigned. Reads `marks_bin_full` — Full Bin and Overflow yes, Damaged Bin no, because that is a job for maintenance. |
| `nextStatusesFor()` | The statuses an Administrator may actually choose right now. **The form builds its dropdown from this and `updateStatus()` re-checks against it, so what is offered and what is accepted cannot drift apart.** |
| `closeDuplicates()` | Closes a whole group of reports of one issue together — each through `updateStatus()`, so every reporter gets their own history row and their own notification. |
| `update()` | Edits a complaint. Only while New and nothing booked, only by its reporter. Raises the edit event carrying what it said before. |
| `saveType()` | Adds or renames an issue type. A new type is not offered to reporters until someone deliberately activates it, which leaves a gap to check the spelling. |
| `setTypeActive()` | Withdraws a type from use or brings it back. **Withdrawing is not deleting** — complaints already filed under it keep naming it. |
| `deleteType()` | Removes a type that no complaint has ever used. |
| `canEdit()` | The single rule the Edit button and the service both read. |
| `canDelete()` | Same for Delete. A Reporter withdraws only an untouched complaint; an Administrator deletes only one already answered. |
| `delete()` | Soft delete — sets `deleted_at`, keeps the row and its history, then raises the withdrawal event **after** the delete so observers see a state that already excludes it. |
| `validateComplaint()` | Server-side validation. The only gate that counts; the browser's checks are advice. |

### `app/controllers/ComplaintController.php` — the web pages

| Function | What it does |
|---|---|
| `__construct()` | Creates the service. |
| `index()` | The listing, with search and filters, plus the duplicate-report panel for Administrators. |
| `create()` / `store()` | Show the submission form / handle the submission. |
| `show()` | The detail page. **Falls back to the archive instead of 404, so an old notification link still reaches the record.** |
| `archive()` | Withdrawn and deleted complaints, read-only. |
| `updateStatus()` | Handles the status form. |
| `assign()` | Books a cleaner without leaving the complaint — calls **Zi Zhang's** `SchedulingService::createForComplaint()`, so the collection is created by the module that owns collections. |
| `edit()` / `update()` | Show the edit form / save it. |
| `closeDuplicates()` | Handles the group action from the duplicates panel. |
| `delete()` | Withdraw or delete. |
| `attachment()` | Streams a photo **after re-checking on every request** that this user may see that complaint. |
| `showData()` | Builds everything the detail page needs — including calling the Bin web service. Also decides read-only for an archived complaint **in one place**, so a form added later cannot forget to ask. |
| `requireScalar()` | Refuses a field posted as an array. `complaint_status[]=New` would cast to the string "Array" and emit a warning. |
| `renderForm()` | Shared rendering for create and edit. |

### `app/controllers/ComplaintTypeController.php` — maintaining the issue types

| Function | What it does |
|---|---|
| `index()` | Lists every issue type with its `marks_bin_full` flag and how many complaints use it. |
| `store()` / `update()` | Add a type, or rename one. Both go through `save()`. |
| `toggle()` | Withdraws a type from use, or brings it back. **Withdrawing is not deleting** — complaints already filed under it keep naming it and stay valid. |
| `delete()` | Removes a type only when no complaint has ever used it. The foreign key enforces the same thing, so it cannot be got round by calling the API. |
| `save()` | Shared validation for add and rename: 1–50 characters, and the name must be unique. |
| `render()` | Draws the maintenance screen. |

**Worth saying:** *"A new type is not offered to reporters the moment it is
created — someone has to activate it. That leaves a gap in which a typo can be
corrected, and a typo matters here because a complaint naming a type pins it
forever through the foreign key."*

### `app/controllers/ComplaintNotificationController.php` — the bell icon

| Function | What it does |
|---|---|
| `index()` | The notification panel. Asks the **user object** which notices are theirs, so a Reporter and an Administrator get different queues from the same page. |
| `read(int $id)` | Marks one notice read — after checking it belongs to this user, so passing somebody else's id does nothing. |
| `readAll()` | Marks everything currently visible to this user read. It marks **only what that user may see**, which is the same ownership rule again rather than a second copy of it. |

**Worth saying:** *"The unread count beside the bell is `count()` of the same
query that fills the panel, so the badge and the list can never disagree."*

### `app/controllers/ComplaintApiController.php` — the service I expose

| Function | What it does |
|---|---|
| `resource()` | Full REST CRUD on one URL — GET, POST, PUT, PATCH, DELETE — routed by HTTP method. |
| `unresolved()` | **The endpoint Zi Zhang consumes.** Every complaint still New or Assigned, each with its bin. |
| `ifaSuccess()` | Wraps a result in the IFA envelope with status `S`. |
| `ifaFailure()` | Turns an exception into `F` (the caller can fix it) or `E` (the server faulted). |
| `envelope()` | Builds the envelope — `status`, `timeStamp`, `requestId`, `data`, `message`, `meta`. One method, so **every endpoint returns the same shape**. |
| `requestId()` | The caller's id, or one generated if they sent none, so the field is never absent. |
| `newRequestId()` | Generates `CMP-YYYYMMDDHHMMSS-xxxxxxxx`. |

### `app/services/BinServiceClient.php` — the service I consume

| Function | What it does |
|---|---|
| `getBinInfo()` | Calls Kar Heng's `bin-api/show/{id}` with the IFA fields attached, checks `status` is `S`, returns the data or null. |
| `getLastError()` | Why the last call failed, so the page can say so. |
| `getLastRequestId()` | Shown on the page for traceability. |
| `send()` | The actual HTTP call. **Closes the session first** — PHP locks the session file for the whole request, so an app calling itself over HTTP would deadlock waiting for its own lock. |
| `sessionCookieHeader()` | Forwards the session cookie so his module sees a signed-in user. |
| `baseUrl()` | Works out the site's own base URL. |
| `newRequestId()` | The IFA request id for the outgoing call. |

**The one sentence to have ready:** *"If his module is down, my page still renders
from local data — every failure path returns null and records a reason. A
reporter must never be unable to read their own complaint because another
module is unavailable."*

### `app/services/ComplaintUploadService.php`

| Function | What it does |
|---|---|
| `validateAndStore()` | The upload defence. Reads the file's **own bytes** with `finfo` instead of trusting the browser's Content-Type, takes the extension from a fixed allow-list, and saves under a name built from 20 random bytes. Nothing the attacker controls reaches the filesystem. |

### `app/models/Complaint.php`

| Function | What it does |
|---|---|
| `statuses()` | The four lifecycle states. |
| `delete()` | Soft delete — stamps `deleted_at`. |
| `hasOpenAssignments()` | Is a cleaner still working on **this complaint**. |
| `assignmentCounts()` | Open, completed and total assignments, so the page can tell "a cleaner is on the way" from "the visit was cancelled". |
| `binHasOpenCollection()` | Is anyone booked to visit **this bin** — per bin, not per complaint, because one visit answers every report of it. |
| `allowedNextStatuses()` | The lifecycle itself: which step may follow which. |
| `types()` | The issue types a reporter may choose. |
| `friendlyDate()` | `2026-09-12 01:27:47` → `12 Sep 2026`, for anything a reporter reads. |
| `search()` | The listing query, with the search box, status and location filters. Also recognises `CMP-2026-0007`, `#7` and `7` as the same complaint. |
| `archived()` | Withdrawn and deleted complaints, scoped to one reporter or all. |
| `unresolved()` | **What the web service returns.** |
| `openSummaryByBin()` | Open reports per bin, so the submission form can warn before a duplicate is created. |
| `duplicateGroups()` | Groups open reports by bin and issue type for the Administrator's panel. |
| `openForBinAndType()` | One group, shown on the detail page so acting on one report does not hide the others. |
| `openForBin()` | Every open report of a bin — used when a booking moves them all to Assigned together. |
| `countUnresolvedForBin()` | How many are still open, so the bin observer knows whether it may release the bin. |

### `app/models/ComplaintType.php`

| Function | What it does |
|---|---|
| `marksBinFull()` | Does a report of this type mean the bin needs emptying. |
| `marksBinFullByName()` | The same question by name. **This is the fix for a real bug** — the code used to compare against the literal strings `'Overflow'` and `'Full Bin'`, which broke silently the moment an Administrator renamed a type. |
| `nextSortOrder()` | Puts a newly added type after the seeded ones, so `Other` stays last. |
| `allOrdered()` | Every type for the maintenance screen. |
| `activeNames()` | The names a reporter may currently choose. |
| `complaintCount()` | How many complaints use it — a type in use cannot be deleted. |

### `app/models/ComplaintNotification.php`

| Function | What it does |
|---|---|
| `forAdministrators()` | The administrators' shared queue. |
| `forReporter()` | **The access control, and it is a join.** `recipient_role = 'Reporter'` on its own means *every* reporter, so the query joins the notification to its complaint and matches `reporter_id`. Without that join every reporter would read every other reporter's notifications. |
| `markRead()` | Marks notifications read. Ownership is decided before it is called. |

### `app/models/ComplaintAttachment.php`

| Function | What it does |
|---|---|
| `supersede()` | Marks a photo as replaced rather than deleting it, so the revision that replaced it can still show it. |

---

## 5. Questions the lecturer is likely to ask

**"Why Observer and not just call the three methods?"**
> One event, several unrelated consequences. If they lived in `ComplaintService`
> that class would do auditing, notification, bin management and revision
> history at once, and I would edit it every time a new reaction was needed.
> I added a fourth reaction without touching it.

**"Show me where the pattern actually runs."**
> `ComplaintService::create()` line 59 and `updateStatus()` — both call
> `$this->subject->notify(...)`. That one line is every reaction.

**"What is the difference between your Subject and MVC?"**
> MVC is how the whole system is organised. Observer is inside my service layer
> and solves a different problem — one event, many independent reactions.
> The brief excludes MVC and Singleton as the chosen pattern, which is why I
> present Observer.

**"Where is your ORM?"**
> `app/core/Model.php`. My entities extend it and expose **object references,
> not foreign keys** — `$complaint->getBin()->getLocation()->getFullLabel()`
> traverses two relationships with no SQL in the view.

**"How do you stop one reporter reading another's complaint?"**
> Two places. `findVisible()` asks the user object `maySee()`, and the
> notification query joins through to `complaints.reporter_id`. Both are in the
> service and model layers, so the REST API is protected by the same rule as the
> web page.

**"What happens if another module's service is down?"**
> The page still renders from local data and shows why the live details are
> missing. It degrades, it does not fail.

**"Did anything go wrong that you fixed?"** — a good one to invite:
> Three, and they all had the same cause: a rule written in one place and
> enforced in another. The issue type compared against a literal string. The
> deletion rule said one thing in a comment and another in the constant. The
> booking form appeared based on the dropdown rather than on the bin. Now each
> rule has one definition that everything else reads.

---

## 6. Five-minute demo order

1. Sign in as **siti@student.ecocampus.my** — submit an Overflow report on a
   bin that already has one. *Point at the warning listing the existing report.*
2. Sign in as **admin@ecocampus.my** in a second browser — the bell icon shows
   the new notification.
3. Open the complaint → **Book a cleaner**, type a message to the reporter.
4. Back to Siti's browser → the notification carries the administrator's
   sentence, and the status history reads in plain English.
5. Open the **duplicate reports panel** — one booking answered every report of
   that bin.
6. Resolve it → the bin returns to Empty on Kar Heng's bin page, with the reason
   recorded there.
7. Open `complaint-api/unresolved` in the address bar — the service Zi Zhang
   consumes, live.

**Have two browsers open before you start.** Most of this only shows when two
roles disagree, and switching accounts mid-demo wastes a minute you do not have.
