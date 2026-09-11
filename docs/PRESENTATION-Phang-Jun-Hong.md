# Presentation notes — User & Access Management

Phang Jun Hong (2406646) · BMIT3173 · Design pattern: **Decorator**

> Written by reading the code, not by the person who wrote it. Check anything
> that sounds wrong before you say it — you know your own module best.

Getters and setters are left out. Every other function is here.

---

## 1. Opening — 45 seconds

> My module decides **who you are and what you are allowed to do**. It covers
> registration, signing in, the user directory an Administrator manages, and
> everybody's own profile and password.
>
> It also holds the thing the other three modules depend on: the permission
> system. Every guarded action in this application, in any module, ends up
> calling `UserPermissions::require()`.
>
> My design pattern is **Decorator**. Each role wraps the same basic signed-in
> profile and adds its own capabilities, without changing the `User` entity at
> all.

---

## 2. Web services — who provides to whom

| | |
|---|---|
| **I expose** | `GET /user-api/cleaners`, plus REST CRUD on `/user-api` |
| **Consumed by** | **Ng Zi Zhang** — his schedule form lists active cleaners to assign |
| **I consume** | `GET /schedule-api/cleaner-open-assignments` — from **Ng Zi Zhang** |

**My two directions:**

- **I produce → Zi Zhang consumes.** His schedule form needs the active
  cleaners. He does not read my `users` table; he calls `user-api/cleaners`.
- **I consume → Zi Zhang produces.** Before deleting a user I have to know
  whether that cleaner still has work assigned. That fact belongs to Scheduling,
  so `UserService::deleteByAdministrator()` calls
  `ScheduleServiceClient::hasOpenAssignments()` — line 140.

**The sentence to have ready:**

> Deleting a cleaner who is halfway through a round would strand that work. But
> "does this cleaner have open work" is not my question to answer from my own
> tables — it is Scheduling's. So I ask his service.

---

## 3. The Decorator pattern — the walkthrough

All in `app/services/UserPermissions.php` — one short file.

| Say this | Point at |
|---|---|
| "`PermissionProfile` is the Component interface. One method: `permissions()`." | line 12 |
| "`BasicPermissionProfile` is the Concrete Component — what **any** signed-in user can do: update their profile, view bins, view locations." | line 17 |
| "`PermissionProfileDecorator` is the abstract Decorator. It **holds a `PermissionProfile`** and its `add()` helper merges new permissions onto whatever it wraps." | line 25 |
| "Three Concrete Decorators, one per role." | lines 35, 43, 51 |
| "`UserPermissions::for()` builds the right one." | line 64 |

**The two lines that are the pattern:**

```php
public function __construct(protected PermissionProfile $wrapped) {}

protected function add(string ...$permissions): array
{
    return array_values(array_unique(array_merge($this->wrapped->permissions(), $permissions)));
}
```

> The decorator holds the thing it wraps **through the interface**, and `add()`
> calls `$this->wrapped->permissions()` before adding its own. That is the
> pattern in two lines: extend behaviour by wrapping, not by inheriting.

**What each decorator adds:**

| Decorator | Adds |
|---|---|
| Everyone (`BasicPermissionProfile`) | `profile.update`, `bin.view`, `location.view` |
| `ReporterPermissionDecorator` | `complaint.create`, `complaint.view_own` |
| `CleanerPermissionDecorator` | `bin.status`, `assignment.view_own`, `assignment.complete` |
| `AdministratorPermissionDecorator` | `bin.manage`, `location.manage`, `complaint.manage`, `schedule.manage`, `user.manage`, `report.view` |

> Notice a Reporter still has `bin.view` — they never declare it. They get it by
> wrapping the basic profile. **Nobody repeats the shared permissions**, and if
> we ever add one to the basic profile, all three roles get it at once.

**If asked "why not just a role column and a big switch?"**

> Because then every permission list would be written out in full, three times,
> and the shared ones would be copied into each. With this, the shared set lives
> once and each role states only its own difference. And the check the rest of
> the system makes — `UserPermissions::can($user, 'complaint.manage')` — asks
> **"may you?"** rather than **"what are you?"**, so a new role means a new
> decorator, not edits scattered across four modules.

**Do not confuse it with the `User` subclasses.** Have this ready, because a
lecturer may well ask:

> `Reporter`, `Cleaner` and `Administrator` are subclasses of `User` — that is
> Single Table Inheritance, an ORM mapping, and it answers *"which records are
> yours?"*. The decorators answer *"may you do this?"*. Two different questions,
> which is why they are two different mechanisms.

---

## 4. Function reference

### `app/services/UserPermissions.php` — the pattern

| Function | What it does |
|---|---|
| `PermissionProfile::permissions()` | The Component interface — one method returning the permission list. |
| `BasicPermissionProfile::permissions()` | What any signed-in user may do. |
| `PermissionProfileDecorator::__construct()` | Holds the wrapped profile **through the interface**. |
| `PermissionProfileDecorator::add()` | Merges the wrapped profile's permissions with this decorator's, de-duplicated. |
| `ReporterPermissionDecorator::permissions()` | Basic + create and view own complaints. |
| `CleanerPermissionDecorator::permissions()` | Basic + record bin status, view and complete own assignments. |
| `AdministratorPermissionDecorator::permissions()` | Basic + manage bins, locations, complaints, schedules, users and reports. |
| `UserPermissions::for()` | Builds the right decorator around a basic profile for this user's role. |
| `UserPermissions::can()` | Is this permission in that list. Used to decide whether to show a button. |
| `UserPermissions::require()` | Same check, but throws `AuthorizationException` if not. **This is the one every other module calls** — it is what makes the pattern the backbone of the system rather than a demonstration. |

### `app/models/User.php` — the entity and the role dispatch

| Function | What it does |
|---|---|
| `hydrate()` | **Single Table Inheritance.** Reads the `role` column and returns a `Reporter`, `Cleaner` or `Administrator` — so a complaint's `getReporter()` yields a typed object without any caller choosing a class. |
| `forRole()` | Makes an empty instance of the right subclass. |
| `describe()` | A readable label for the user. |
| `visibleComplaints()` | Which complaints are this user's to see. Base answer: none. Overridden per role. |
| `maySee()` | May this user open one particular complaint. |
| `archivedComplaints()` | Which withdrawn or deleted complaints they may look back at. |
| `visibleNotifications()` | Which notifications are theirs. |
| `maySeeNotification()` | May they read one particular notice. |
| `unreadNotificationCount()` | The number beside the bell icon. |
| `demographics()` | The optional profile fields, for the profile page. |
| `delete()` | Soft delete — stamps `deleted_at`, keeps the row. |
| `hasOpenAssignments()` | Does this cleaner still have work — asked of Scheduling's service. |
| `apiData()` | The JSON shape for the web service, deliberately excluding the password hash. |
| `roles()` | The three role names. |
| `visibleCount()` | Dashboard count. |
| `findByEmail()` | Used at sign-in. |
| `search()` | The directory query with role and status filters. |
| `findActiveCleaners()` | **What `user-api/cleaners` returns** — the list Zi Zhang's schedule form uses. |

> The six complaint-related methods on `User` are the same idea as the
> decorators: the question is asked of the **role object** rather than answered
> with an `if` in a controller. Boon Leong's module calls them; they live here
> because the answer is a fact about the role.

### `app/services/UserService.php`

| Function | What it does |
|---|---|
| `registerReporter()` | Public self-registration. Always a Reporter — the role never comes from the form. |
| `createByAdministrator()` | An Administrator adding an account, with a generated temporary password returned once. |
| `updateByAdministrator()` | Edit someone else's account, including their role and status. |
| `updateOwnProfile()` | Your own details. **Cannot change your own role** — that separation is the point of having two methods. |
| `updateOwnPassword()` | Change your own password, current password required. |
| `search()` | The directory listing. |
| `find()` | One user. |
| `deleteByAdministrator()` | Soft-deletes an account — **after asking Scheduling whether that cleaner still has open work.** |
| `resetPasswordByAdministrator()` | Issues a new temporary password. |
| `validateDemographics()` | IC number, phone, birth date, postcode, state and the rest. |
| `validateIdentity()` | Name and email; email must be unique. |
| `validateAccess()` | Role and account status are valid values. |
| `generateTemporaryPassword()` | A random one-time password. |
| `validateNewPassword()` | Length and confirmation match. |
| `validateTextFields()` | Refuses a field posted as an array. |
| `requireUser()` | Fetch or throw. |

**Passwords:** stored with `password_hash()` (bcrypt) and checked with
`password_verify()`. The hash is never in `apiData()`, so it cannot leave
through the web service.

### `app/controllers/AuthController.php`

| Function | What it does |
|---|---|
| `index()` | The sign-in page. |
| `register()` / `storeRegistration()` | Public registration form / create the Reporter. |
| `login()` | Verifies the password, starts the session, sends each role to its own landing page. |
| `logout()` | Ends the session. |

### `app/controllers/UserController.php`

| Function | What it does |
|---|---|
| `index()` | The user directory, Administrators only. |
| `create()` / `store()` | Add an account. |
| `edit()` / `update()` | Edit one. |
| `delete()` | Soft-delete one. |
| `resetPassword()` | Issue a temporary password. |
| `profile()` | Your own profile page. |
| `editProfile()` / `updateProfile()` | Edit your own details. |
| `changePassword()` / `updatePassword()` | Change your own password. |
| `renderAdminForm()` | Shared rendering for the admin add/edit forms. |

> The split between `update()` and `updateProfile()` is deliberate: the first
> can change a role, the second cannot. Two methods rather than one method with
> a flag, so it is impossible to reach the role field from the profile page.

### `app/controllers/UserApiController.php` — the service I expose

| Function | What it does |
|---|---|
| `resource()` | REST CRUD on users, routed by HTTP method, in the IFA envelope. |
| `cleaners()` | Active cleaners. **Zi Zhang's schedule form calls this.** |

---

## 5. Questions the lecturer is likely to ask

**"How is Decorator different from Kar Heng's Proxy?"**
> Same structure — both wrap an object and implement its interface. The intent
> differs. A Decorator **adds behaviour**; a Proxy **controls access** to
> behaviour that is already there. Mine builds up a bigger permission list; his
> decides whether you may call a method at all.

**"Why is it not just inheritance?"**
> Inheritance would fix the combination at compile time. Decorators compose at
> runtime — `UserPermissions::for()` chooses the wrapping when the user signs
> in, and a role that needed two sets of capabilities could wrap twice without a
> new subclass.

**"Where is it actually used?"**
> Everywhere. `UserPermissions::require()` is called in all four modules — it is
> what guards every administrator action, every cleaner action and every API
> write in the system.

**"Show me a permission a role gets without declaring it."**
> A Reporter has `bin.view`. `ReporterPermissionDecorator` only adds
> `complaint.create` and `complaint.view_own` — `bin.view` comes from the basic
> profile it wraps.

**"How are passwords stored?"**
> `password_hash()` with bcrypt, verified with `password_verify()`. Never
> reversible, never in the API response.

**"What stops a user editing their own role?"**
> `updateOwnProfile()` does not accept a role at all. Changing a role is
> `updateByAdministrator()`, which requires `user.manage`.

---

## 6. Five-minute demo order

1. Register a new account from the sign-in page → it is created as a Reporter,
   and the form never offers a role.
2. Sign in as that Reporter → no Users menu, no Schedules. Try
   `/EcoCampus/user` in the address bar → refused by `require('user.manage')`.
3. Sign in as **admin@ecocampus.my** → the full directory appears.
4. Add a cleaner → a temporary password is shown once.
5. Try to delete **zaki@cleaner.ecocampus.my**, who has open assignments →
   refused, and the reason came from **Zi Zhang's** service, not my tables.
6. Open `user-api/cleaners` in the address bar — the service Zi Zhang consumes,
   live, and no password hash in it.
