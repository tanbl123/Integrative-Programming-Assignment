<?php /** Complaint listing. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<div class="page-heading">
    <div>
        <h1><?= $user->isAdmin() ? 'All complaints' : 'My complaints' ?></h1>
        <p class="lead">Track reported waste issues from submission to resolution.</p>
    </div>

    <div class="button-row">
        <?php /* Withdrawn and deleted reports are kept, not destroyed, so
                 there has to be somewhere to read them. */ ?>
        <a class="button button-secondary" href="<?= url('complaint/archive') ?>">Archive</a>

        <?php if (UserPermissions::can($user, 'complaint.create')): ?>
            <a class="button" href="<?= url('complaint/create') ?>">Report issue</a>
        <?php endif; ?>
    </div>
</div>

<?php /*
 * Duplicate reports panel - administrators only.
 *
 * Several people reporting one overflowing bin is one problem, not several.
 * Each group is shown as a single row that expands to the individual reports,
 * and is answered as a group: one cleaner booked for the bin moves all of
 * them to Assigned, and one outcome closes all of them - each notifying its
 * own reporter separately. No report is turned down for being second.
 */ ?>
<?php if ($duplicateGroups !== []): ?>
    <section class="content-card duplicate-groups">
        <h2>Duplicate reports</h2>
        <p class="lead">
            These bins have more than one open report of the same issue. One
            collection answers all of them, so close them together and every
            reporter is told the same thing.
        </p>

        <?php foreach ($duplicateGroups as $group): ?>
            <details class="duplicate-group">
                <summary>
                    <strong><?= e($group['bin']?->getBinCode() ?? 'Unknown bin') ?></strong>
                    &middot; <?= e($group['type']) ?>
                    <span class="badge badge-status"><?= count($group['complaints']) ?> reports</span>
                    <small><?= e($group['bin']?->getLocation()?->getFullLabel() ?? '') ?></small>
                </summary>

                <?php
                /* A group needs one of two things, never both: a cleaner sent,
                   or - once one has been - closing together. Asking the first
                   report in the group is enough; they all name the same bin. */
                $booked = $group['complaints'][0]->binHasOpenCollection();
                $first = (int) $group['complaints'][0]->getKey();
                ?>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Complaint ID</th><th>Reporter</th>
                                    <th>Reason given</th><th>Status</th><th>Submitted</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($group['complaints'] as $item): ?>
                                <tr>
                                    <td><?= e($item->getNumber()) ?></td>
                                    <td><?= e($item->getReporter()?->getFullName() ?? 'Unknown') ?></td>
                                    <?php /* Each reporter described the issue in their own words. The
                                            administrator needs them side by side to judge whether
                                            they really are the same issue. */ ?>
                                    <td class="duplicate-reason"><?= e($item->getDescription()) ?></td>
                                    <td><span class="badge badge-status"><?= e($item->getStatus()) ?></span></td>
                                    <td><?= e($item->getCreatedAt()) ?></td>
                                    <td><a href="<?= url('complaint/show/' . $item->getKey()) ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php if (!$booked): ?>
                    <?php /* Booked here rather than from the Schedules tab. A
                             strategy chooses its own bins and sweeps up every
                             open complaint on the campus, which is the right
                             shape for planning a round and the wrong one for
                             answering the group in front of you. This books
                             the one bin these reports name; the Scheduling
                             module still does the work, through the same
                             endpoint the complaint page uses, and all of these
                             reports move to Assigned with it. */ ?>
                    <form
                        method="post"
                        action="<?= url('complaint/assign/' . $first) ?>"
                        class="group-action"
                        data-no-auto-clear="1"
                    >
                        <h3>Book a cleaner</h3>
                        <?= csrfField() ?>
                        <?php /* So the administrator comes back to the list they
                                 were working through, not to one report of the
                                 group. A fixed marker, not a URL, so nothing
                                 posted here can choose where the site goes. */ ?>
                        <input type="hidden" name="from" value="list">

                        <p class="field-help">
                            No cleaner is booked for
                            <strong><?= e($group['bin']?->getBinCode() ?? 'this bin') ?></strong> yet.
                            One visit answers all <?= count($group['complaints']) ?> reports, and every
                            reporter is told.
                        </p>

                        <div class="form-grid time-grid">
                            <label>
                                Collection date
                                <input type="date" name="schedule_date" required
                                       min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>"
                                       data-message-required="Choose the day the cleaner should go.">
                            </label>

                            <div class="time-range-field">
                                <span class="time-range-legend">Time slot</span>
                                <div class="time-range">
                                    <label>From
                                        <input type="time" name="time_from" required value="09:00"
                                               data-message-required="Choose a start time."></label>
                                    <label>To
                                        <input type="time" name="time_to" required value="12:00"
                                               data-message-required="Choose an end time."
                                               data-after="time_from"
                                               data-message-after="The end time must be later than the start time."></label>
                                </div>
                            </div>
                        </div>

                        <label>
                            Cleaner
                            <select name="cleaner_id" required>
                                <option value="">Select a cleaner</option>
                                <?php foreach ($cleaners as $cleaner): ?>
                                    <option value="<?= (int) $cleaner->getKey() ?>">
                                        <?= e($cleaner->getFullName()) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($cleaners === []): ?>
                                <span class="field-error">No active cleaner is available to assign.</span>
                            <?php endif; ?>
                        </label>

                        <label>
                            Notes for the cleaner
                            <textarea name="notes" rows="2" maxlength="500"
                                      placeholder="e.g. Bin is behind the drinks stall."></textarea>
                            <span class="field-help">Only the cleaner sees this.</span>
                        </label>

                        <?php /* Same field as the complaint page's booking
                                 form, and it matters more here: this books one
                                 visit for several people who each reported the
                                 bin separately, and every one of them receives
                                 what is written. */ ?>
                        <label>
                            Message to the reporters
                            <textarea name="reporter_message" rows="2"
                                      maxlength="<?= (int) ComplaintService::REPORTER_MESSAGE_MAX ?>"
                                      placeholder="e.g. Thanks for reporting - a cleaner will be there this afternoon."></textarea>
                            <span class="field-help">
                                Optional. Sent to all <?= count($group['complaints']) ?> reporters
                                with the news that work is being arranged.
                            </span>
                        </label>

                        <button class="button" type="submit" <?= $cleaners === [] ? 'disabled' : '' ?>>
                            Book a cleaner for all <?= count($group['complaints']) ?> reports
                        </button>
                    </form>
                <?php endif; ?>

                <?php /* Closing the group, either way. Rejecting needs no
                         collection - a report nobody is going to act on can be
                         turned down whether or not a cleaner was ever sent -
                         so it is offered in both states, while resolving
                         appears only once somebody has been.

                         The confirmation belongs on the form: ui.js reads
                         data-confirm from the form element, so a copy on a
                         button is silently ignored and the reports are closed
                         on the first click. */ ?>
                <form
                    method="post"
                    action="<?= url('complaint/close-duplicates') ?>"
                    class="group-action"
                    data-no-auto-clear="1"
                    data-confirm="Close all <?= count($group['complaints']) ?> reports of this issue? Each reporter is told separately, and none of it can be undone."
                >
                    <h3><?= $booked ? 'Close these reports' : 'Or turn them down' ?></h3>
                    <?= csrfField() ?>
                    <input type="hidden" name="bin_id" value="<?= (int) ($group['bin']?->getKey() ?? 0) ?>">
                    <input type="hidden" name="complaint_type" value="<?= e($group['type']) ?>">

                    <label>
                        <?= $booked ? 'What was done' : 'Why these reports are being rejected' ?>
                        <input
                            name="remarks"
                            required
                            maxlength="255"
                            placeholder="<?= $booked
                                ? 'e.g. Bin emptied and the area around it cleaned.'
                                : 'e.g. This bin was removed from the cafeteria last week.' ?>"
                            data-message-required="Say why; every reporter is told this."
                        >
                        <span class="field-help">
                            Sent to all <?= count($group['complaints']) ?> reporters with the outcome.
                        </span>
                    </label>

                    <div class="button-row">
                        <?php if ($booked): ?>
                            <button class="button" type="submit"
                                    name="outcome" value="<?= e(Complaint::STATUS_RESOLVED) ?>">
                                Resolve all <?= count($group['complaints']) ?> reports
                            </button>
                        <?php endif; ?>

                        <button class="button button-danger" type="submit"
                                name="outcome" value="<?= e(Complaint::STATUS_REJECTED) ?>">
                            Reject all <?= count($group['complaints']) ?> reports
                        </button>
                    </div>
                </form>
            </details>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<form method="get" action="<?= url('complaint') ?>" class="filter-panel user-filter-panel" id="complaintFilterForm">
    <div class="filter-field filter-search">
        <label>Search</label>
        <input 
            type="search" 
            name="q" 
            id="complaintSearch"
            value="<?= e($filters['query']) ?>" 
            placeholder="Complaint ID, issue, bin, or location"
            >
    </div>

    <div class="filter-field">
        <label>Status</label>
        <select name="status" id="complaintStatusFilter">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                    <?= e($status) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-field">
        <label>Location</label>
        <select name="location_id" id="complaintLocationFilter">
            <option value="">All locations</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= $location->getKey() ?>" <?= (string) $filters['location'] === (string) $location->getKey() ? 'selected' : '' ?>>
                    <?= e($location->getFullLabel()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearComplaintFilters">
            Clear
        </button>
    </div>
</form>

<div class="table-scroll">
    <table class="table" id="complaintsTable">
        <thead>
            <tr>
                <?php /* "Complaint ID", not "No.": the value is the complaint's own
                        identifier, not its position in this list. A Reporter sees only
                        their own complaints, so the codes are never consecutive, and a
                        heading promising a row count would look wrong to them. */ ?>
                <th><button type="button" class="sort-header" data-column="0">Complaint ID</button></th>
                <th><button type="button" class="sort-header" data-column="1">Bin</button></th>
                <th><button type="button" class="sort-header" data-column="2">Issue</button></th>
                <th><button type="button" class="sort-header" data-column="3">Status</button></th>
                <th><button type="button" class="sort-header" data-column="4">Submitted</button></th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($complaints as $complaint): ?>
                <?php
                $bin = $complaint->getBin();
                $location = $bin?->getLocation();
                ?>

                <tr 
                    class="complaint-row"
                    data-search="<?=
                    e(mb_strtolower(
                                    $complaint->getNumber() . ' ' .
                                    // Also the bare key, so typing 12 or #12
                                    // finds CMP-2026-0012 the way the
                                    // server-side search already does.
                                    '#' . $complaint->getKey() . ' ' .
                                    ($bin?->getBinCode() ?? '') . ' ' .
                                    ($location?->getFullLabel() ?? '') . ' ' .
                                    $complaint->getType()
                            ))
                    ?>"
                    data-status="<?= e($complaint->getStatus()) ?>"
                    data-location="<?= e((string) ($location?->getKey() ?? '')) ?>"
                    >
                    <td><?= e($complaint->getNumber()) ?></td>

                    <td>
    <?= e($bin?->getBinCode() ?? 'Unknown') ?><br>
                        <small><?= e($location?->getFullLabel() ?? '') ?></small>
                    </td>

                    <td><?= e($complaint->getType()) ?></td>

                    <td>
                        <span class="badge badge-status">
    <?= e($complaint->getStatus()) ?>
                        </span>
                    </td>

                    <td><?= e($complaint->getCreatedAt()) ?></td>

                    <td>
                        <a href="<?= url('complaint/show/' . $complaint->getKey()) ?>">View</a>
                    </td>
                </tr>
<?php endforeach; ?>

            <tr id="noComplaintResults" <?= $complaints === [] ? '' : 'hidden' ?>>
                <td colspan="6" class="empty">No complaints match the filters.</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('complaintFilterForm');
        const search = document.getElementById('complaintSearch');
        const status = document.getElementById('complaintStatusFilter');
        const location = document.getElementById('complaintLocationFilter');
        const clear = document.getElementById('clearComplaintFilters');
        // Must be scoped to the complaints table by id. The duplicate reports
        // panel above also renders .table elements, so a bare '.table tbody'
        // selector matches the first duplicate group instead, and sorting then
        // moves every complaint row into that group's collapsed table.
        const complaintsTable = document.getElementById('complaintsTable');
        const tbody = complaintsTable ? complaintsTable.tBodies[0] : null;
        const rows = Array.from(document.querySelectorAll('.complaint-row'));
        const noResults = document.getElementById('noComplaintResults');

        if (!form || !tbody) {
            return;
        }

        let currentColumn = null;
        let currentDirection = 'asc';

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        function filterComplaints() {
            const keyword = search.value.toLowerCase().trim();
            const selectedStatus = status.value;
            const selectedLocation = location.value;
            let count = 0;

            rows.forEach(row => {
                const matchSearch = row.dataset.search.includes(keyword);
                const matchStatus = selectedStatus === '' || row.dataset.status === selectedStatus;
                const matchLocation = selectedLocation === '' || row.dataset.location === selectedLocation;

                const show = matchSearch && matchStatus && matchLocation;
                row.hidden = !show;

                if (show) {
                    count++;
                }
            });

            if (noResults) {
                noResults.hidden = count !== 0;
            }
        }

        function sortComplaints(column) {
            if (currentColumn === column) {
                currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentColumn = column;
                currentDirection = 'asc';
            }

            rows.sort((a, b) => {
                const valueA = a.cells[column].textContent.trim();
                const valueB = b.cells[column].textContent.trim();

                return valueA.localeCompare(valueB, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                }) * (currentDirection === 'asc' ? 1 : -1);
            });

            rows.forEach(row => tbody.appendChild(row));

            if (noResults) {
                tbody.appendChild(noResults);
            }

            paintSortIndicators();
            filterComplaints();
        }

        search.addEventListener('input', filterComplaints);
        status.addEventListener('change', filterComplaints);
        location.addEventListener('change', filterComplaints);

        clear.addEventListener('click', function () {
            search.value = '';
            status.value = '';
            location.value = '';
            filterComplaints();
        });

        // Each sortable header carries an arrow so the current sort column and
        // direction are visible: neutral until sorted, then up or down.
        const sortButtons = Array.from(complaintsTable.querySelectorAll('.sort-header'));

        sortButtons.forEach(button => {
            // Let the cell hand its padding to the button, so the entire header
            // cell is clickable rather than only the width of the label.
            const header = button.closest('th');
            if (header) {
                header.classList.add('th-sortable');
            }

            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.textContent = '\u21c5';
            button.append(' ', arrow);

            button.addEventListener('click', function () {
                sortComplaints(parseInt(button.dataset.column, 10));
            });
        });

        // aria-sort belongs on the column header itself, so a screen reader
        // announces the sort state when it reads the column.
        function paintSortIndicators() {
            sortButtons.forEach(button => {
                const column = parseInt(button.dataset.column, 10);
                const header = button.closest('th');
                const arrow = button.querySelector('.sort-arrow');
                const active = column === currentColumn;

                if (header) {
                    if (active) {
                        header.setAttribute('aria-sort',
                            currentDirection === 'asc' ? 'ascending' : 'descending');
                    } else {
                        header.removeAttribute('aria-sort');
                    }
                }
                if (arrow) {
                    arrow.textContent = active
                        ? (currentDirection === 'asc' ? '\u2191' : '\u2193')
                        : '\u21c5';
                }
            });
        }

        paintSortIndicators();
        filterComplaints();
    });
</script>