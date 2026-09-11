# Presentation notes — Collection Scheduling & Assignment

Ng Zi Zhang (2406898) · BMIT3173 · Design pattern: **Strategy**

> Written by reading the code, not by the person who wrote it. Check anything
> that sounds wrong before you say it — you know your own module best.

Getters and setters are left out. Every other function is here.

---

## 1. Opening — 45 seconds

> My module decides **who goes where, and when**. An Administrator generates a
> collection round, cleaners are assigned to bins, and each cleaner records the
> work when it is done.
>
> The interesting problem is *which bins belong in a round*, and there is no
> single right answer — sometimes it is the full ones, sometimes the ones people
> have complained about, sometimes just everything on a routine sweep. That is
> why my pattern is **Strategy**: the algorithm is chosen at runtime and the
> code that uses it never changes.

---

## 2. Web services — who provides to whom

| | |
|---|---|
| **I expose** | `GET /schedule-api/mine`, `GET /schedule-api/bin-status/{binId}`, `GET /schedule-api/cleaner-open-assignments`, plus REST CRUD on `/schedule-api` |
| **Consumed by** | **Ong Kar Heng** — his bin page shows the next planned collection<br>**Phang Jun Hong** — checks a cleaner has no open work before deleting them |
| **I consume** | `GET /bin-api` — **Kar Heng**<br>`GET /complaint-api/unresolved` — **Boon Leong**<br>`GET /user-api/cleaners` — **Jun Hong** |

**I consume from all three of them.** Point at
`app/views/schedule/form.php` lines 26–28:

```php
data-bin-api="<?= url('bin-api') ?>"
data-complaint-api="<?= url('complaint-api/unresolved') ?>"
data-user-api="<?= url('user-api/cleaners') ?>"
```

> Before the Administrator submits, the form previews which bins have work
> waiting and which cleaners are free — and every one of those three lists comes
> from another module's service, not from my tables.

---

## 3. The Strategy pattern — the walkthrough

Everything is in `app/services/SchedulingStrategyFactory.php` — one short file,
so you can scroll it top to bottom.

| Say this | Point at |
|---|---|
| "`BinSelectionStrategy` is the Strategy interface. Two methods: `name()` and `select()`." | line 7 |
| "Three Concrete Strategies, each a completely different algorithm behind the same two methods." | lines 13, 24, 49 |
| "`SchedulingStrategyFactory::make()` picks one from the name the Administrator chose in the form." | line 63 |
| "`SchedulingService::create()` is the Context. It calls `select()` and has **no idea** which algorithm answered." | `SchedulingService.php` line 16 |

**The three strategies, one line each:**

| Strategy | What it selects | Priority given |
|---|---|---|
| `FullBinSelectionStrategy` | Every bin marked Full | Urgent |
| `ComplaintPrioritySelectionStrategy` | Every bin with an unresolved complaint — **one entry per bin, not per complaint** | Urgent |
| `RoutineBinSelectionStrategy` | Every active bin | Routine |

**The line that makes the point** — `SchedulingService::create()`:

```php
$selections = SchedulingStrategyFactory::make($strategyName)->select();
```

> One line. Swapping the algorithm changes nothing else in the service, and
> adding a fourth — say "bins not collected for seven days" — means writing one
> class and adding one line to the factory. Nothing that *uses* a strategy has
> to change.

**Two details worth pointing out, because they show you thought about it:**

1. **All three return the same shape** — `['bin', 'complaint_id', 'priority',
   'reason']`. That uniform return is what lets the Context treat them
   identically.
2. **`ComplaintPrioritySelectionStrategy` de-duplicates by bin.** Five people
   reporting one bin is one visit, not five:

```php
if ($bin !== null && $bin->isActive() && !isset($selected[$bin->getKey()])) {
```

> And the `reason` it writes is `Complaint CMP-2026-0009: Overflow`, using the
> display code rather than the raw id — because that docket is what a cleaner
> reads, and `CMP-2026-0009` is what the complaint is called on every other
> screen.

---

## 4. Function reference

### `app/services/SchedulingStrategyFactory.php` — the pattern

| Function | What it does |
|---|---|
| `BinSelectionStrategy::name()` | The strategy's display name. |
| `BinSelectionStrategy::select()` | Returns the bins this algorithm wants, in the shared shape. |
| `FullBinSelectionStrategy::select()` | Every bin whose status is Full. |
| `ComplaintPrioritySelectionStrategy::select()` | Walks unresolved complaints, takes the first for each active bin, tags it with the complaint's display code. |
| `RoutineBinSelectionStrategy::select()` | Every active bin, Routine priority. |
| `SchedulingStrategyFactory::names()` | The three names, used to build the dropdown **and** to validate what comes back. |
| `SchedulingStrategyFactory::make()` | Name → object. Throws on anything else, so a crafted request cannot smuggle in a fourth. |

### `app/services/SchedulingService.php` — the Context and the rules

| Function | What it does |
|---|---|
| `create()` | **The Context.** Runs the chosen strategy, creates the schedule, writes one assignment per selected bin, and moves the complaints on those bins to Assigned — all in one transaction. |
| `createForComplaint()` | Books **one** cleaner for **one** bin, raised from the complaint page rather than from here. Refuses an inactive bin, and refuses a second booking for a bin somebody is already coming to. |
| `releaseComplaints()` | When a schedule is cancelled, puts the complaints back to New — but only where the bin has nothing else outstanding, because another schedule may still cover it. |
| `composeTimeSlot()` | Builds `09:00-12:00` from the two time pickers. Exists because the column is free text and had already collected the same three hours written two ways, one with an en dash. |
| `update()` | Edits a planned schedule and re-runs the strategy. |
| `cancel()` | Cancels a schedule, skips its assignments, and calls `releaseComplaints()`. |
| `completeAssignment()` | A cleaner marking one bin done: records the collection, the weight and the notes. |
| `findVisible()` | One schedule, if this user may see it — a Cleaner sees their own, an Administrator sees all. |
| `delete()` | Soft-deletes a cancelled or completed schedule, keeping the assignment history. |
| `markComplaintsAssigned()` | Moves **every** open complaint on a booked bin to Assigned, through the Complaint module's own service so its observers run. Skips any not New, so running twice changes nothing. |
| `validateSchedule()` | Date not in the past, time slot present, strategy known, cleaner active, notes within length. |
| `requireSchedule()` | Fetch or throw. |

**The cross-module sentence to have ready:**

> I never write `complaints.complaint_status` myself. I call Boon Leong's
> `ComplaintService::updateStatus()`, so his four observers run — the history is
> written, the reporter is notified, and the bin is updated. My module books the
> cleaner; his module decides what that means to the complaint.

### `app/controllers/ScheduleController.php`

| Function | What it does |
|---|---|
| `index()` | Schedule listing with date and status filters. |
| `create()` / `store()` | The generation form / run it. The form previews work from three other modules' services first. |
| `show()` | One schedule and its assignments. |
| `edit()` / `update()` | Change a planned schedule. |
| `cancel()` | Call it off — and release the complaints that were waiting on it. |
| `delete()` | Soft-delete a finished or cancelled one. |
| `my()` | A cleaner's own assignment list. |
| `complete()` / `storeCompletion()` | The completion form / save it. |
| `renderForm()` | Shared rendering for create and edit. |

### `app/controllers/ScheduleApiController.php` — the service I expose

| Function | What it does |
|---|---|
| `binStatus()` | Next planned collection for one bin. **Kar Heng's bin page calls this.** |
| `resource()` | REST CRUD routed by HTTP method. |
| `mine()` | The signed-in cleaner's own assignments. |
| `cleanerOpenAssignments()` | Whether a cleaner still has open work. **Jun Hong calls this before deleting an account.** |

### `app/models/CollectionSchedule.php`

| Function | What it does |
|---|---|
| `delete()` | Soft delete — keeps the schedule and its assignments as a record. |
| `splitTimeSlot()` | Turns `09:00-12:00` back into two values for the edit form. Tolerates the en-dash rows already in the data. |
| `search()` | Listing query with date and status filters. |

### `app/models/CollectionAssignment.php`

| Function | What it does |
|---|---|
| `complete()` | Marks one bin done, with the cleaner's notes. |
| `skip()` | Marks it skipped with a reason — what cancelling a schedule does to each one. |
| `forCleaner()` | A cleaner's own assignments, filtered by status. |
| `remainingForSchedule()` | How many are still open, so a schedule knows when it is finished. |
| `openForBin()` | **Is anybody already coming to this bin** — what stops a second cleaner being sent, and what the Complaint module reads to decide whether Assigned is allowed. |

`CollectionRecord` is a plain entity class — accessors only.

---

## 5. Questions the lecturer is likely to ask

**"Why Strategy and not just an `if` statement?"**
> Three `if` branches inside `create()` would mean editing the method every time
> a new rule appeared, and the method would grow to hold three unrelated
> algorithms. With Strategy each lives in its own class, and `create()` has one
> line that does not change.

**"Where is the Context?"**
> `SchedulingService::create()`. It calls `select()` and never learns which
> class answered.

**"How would you add a fourth strategy?"**
> One new class implementing `BinSelectionStrategy`, one line in
> `make()`, one in `names()`. Nothing that uses a strategy changes.

**"Is the Factory part of the Strategy pattern?"**
> No — it is a small Simple Factory helping Strategy. Strategy is about
> interchangeable algorithms; the factory just turns the posted name into the
> right object in one place, so no controller does that with a `switch`.

**"What happens if the strategy finds nothing?"**
> `create()` refuses with a validation error saying so, rather than saving an
> empty round.

**"Show me the integration."**
> `markComplaintsAssigned()`. I call the Complaint module's service rather than
> updating its table, so its observers fire and its reporters get told.

---

## 6. Five-minute demo order

1. Sign in as **admin@ecocampus.my** → **Generate schedule**.
2. Point at the preview — bins, complaints and cleaners, each loaded from a
   different module's web service.
3. Pick **Full Bins**, generate → assignments created.
4. Generate again with **Complaint Priority** → *a different set of bins, same
   code path*. That is the Strategy pattern in one click.
5. Open a complaint in a second browser → it moved to **Assigned** by itself,
   and the reporter was notified. That is the cross-module call.
6. **Cancel** the schedule → the complaint goes back to New and the reporter is
   told the visit is off.
7. Sign in as **zaki@cleaner.ecocampus.my** → his own assignment list, then
   complete one.
