# Demonstration accounts

Every account seeded by `database/02_seed.sql` and by the full export
`database/14_ecocampus_export.sql` uses the same password:

```
password123
```

| Email | Password | Role | Name |
|---|---|---|---|
| `admin@ecocampus.my` | `password123` | Administrator | Admin |
| `zizhang@ecocampus.my` | `password123` | Administrator | Ng Zi Zhang |
| `siti@student.ecocampus.my` | `password123` | Reporter | Siti Nurhaliza |
| `weijie@student.ecocampus.my` | `password123` | Reporter | Lim Wei Jie |
| `raj@staff.ecocampus.my` | `password123` | Reporter | Raj Kumar |
| `zaki@cleaner.ecocampus.my` | `password123` | Cleaner | Ahmad Zaki |
| `mary@cleaner.ecocampus.my` | `password123` | Cleaner | Mary Chong |

Sign in at <http://localhost/EcoCampus/>.

## What each role can reach

| | Administrator | Reporter | Cleaner |
|---|---|---|---|
| Bins and locations | manage | view | view, record fill status |
| Users | manage | own profile | own profile |
| Complaints | every complaint, and resolve them | **only their own** | none |
| Schedules | generate and assign | none | their own assignments |

## Testing more than one role at once

Use **two browsers**, or one normal window and one private window. Several
behaviours in this system only appear when two roles disagree — a Reporter
seeing only their own complaints, an Administrator booking a cleaner while the
Reporter watches the notification arrive — and a single session cannot show
them.

Three Reporter accounts exist for the same reason: duplicate-report handling
needs several different people reporting one bin, and one account reporting the
same bin three times is not the same test.

## Why this is safe to commit, and what is not

These are fixtures, not credentials. They exist only inside a local XAMPP
database that each person creates by importing the SQL, they are reachable only
from `localhost`, and the bcrypt hashes they correspond to are already in the
committed SQL files. Publishing the plaintext alongside them gives away nothing
that was not already there.

What must never be committed:

- **`config/config.local.php`** — your own MySQL root password. It is
  git-ignored for this reason. `config/config.php` holds the XAMPP defaults
  only.
- **Personal access tokens, or any real account password.** Nothing in this
  repository should ever hold one.

And do not reuse `password123` anywhere that matters. It is public, in this
file, in `README.md` and in `readme.txt`.
