<?php

/**
 * Collection scheduling and cleaner assignment controller.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class ScheduleController extends Controller {

    private SchedulingService $service;

    /** Creates the scheduling service this controller delegates every rule to. */
    public function __construct() {
        $this->service = new SchedulingService();
    }

    /**
     * The schedule listing. An Administrator sees every round; a Cleaner is sent to
     * their own assignments instead.
     */
    public function index(): void {
        try {
            $user = Auth::requireLogin();

            if (!UserPermissions::can($user, 'schedule.manage')) {
                if (UserPermissions::can($user, 'assignment.view_own')) {
                    $this->redirect('schedule/my');
                }

                throw new AuthorizationException('Your role does not have scheduling access.');
            }

            $date = trim((string) ($_GET['date'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? ''));

            $this->view('schedule/index', [
                'title' => 'Collection Schedules',
                'schedules' => CollectionSchedule::search($date, $status),
                'filters' => compact('date', 'status'),
            ]);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * The generate-schedule form. Before it is submitted the page previews the work
     * waiting by calling the Bin, Complaint and User web services.
     */
    public function create(): void {
        try {
            UserPermissions::require('schedule.manage');
            $this->renderForm('create', null, [], []);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Runs the chosen strategy and saves the round it produces. */
    public function store(): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $schedule = $this->service->create(Auth::requireLogin(), $_POST);

            Flash::set(
                    'success',
                    'Schedule #' . $schedule->getKey() . ' was generated using ' . $schedule->getStrategy() . '.'
            );

            $this->redirect('schedule/show/' . $schedule->getKey());
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderForm('create', null, $error->getErrors(), $_POST);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** One schedule and the assignments it created. */
    public function show(int $id): void {
        try {
            $user = Auth::requireLogin();
            $schedule = $this->service->findVisible($user, $id);

            if ($schedule === null || $schedule->isDeleted()) {
                $this->entityNotFound('Schedule');
                return;
            }

            $assignments = $schedule->getAssignments();

            /*
             * Admin can see all assignments in the schedule.
             * Cleaner can only see their own assignments.
             */
            if ($user->isCleaner() && !$user->isAdmin()) {
                $assignments = array_values(array_filter(
                                $assignments,
                                fn(CollectionAssignment $assignment): bool =>
                                $assignment->getCleanerId() === $user->getKey()
                        ));
            }

            $this->view('schedule/show', [
                'title' => 'Schedule #' . $id,
                'schedule' => $schedule,
                'assignments' => $assignments,
                'user' => $user,
            ]);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The edit form for a planned schedule. */
    public function edit(int $id): void {
        try {
            UserPermissions::require('schedule.manage');

            $schedule = CollectionSchedule::find($id);

            if ($schedule === null || $schedule->isDeleted()) {
                $this->entityNotFound('Schedule');
                return;
            }

            $this->renderForm('edit', $schedule, [], []);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves changes to a planned schedule. */
    public function update(int $id): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $schedule = $this->service->update($id, $_POST);

            Flash::set('success', 'Schedule details and open assignments were updated.');
            $this->redirect('schedule/show/' . $schedule->getKey());
        } catch (ValidationException $error) {
            $schedule = CollectionSchedule::find($id);

            if ($schedule === null || $schedule->isDeleted()) {
                $this->entityNotFound('Schedule');
                return;
            }

            http_response_code(422);
            $this->renderForm('edit', $schedule, $error->getErrors(), $_POST);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Schedule');
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Calls a round off. The assignments are marked skipped and the complaints that
     * were waiting on it are put back to New by the service.
     */
    public function cancel(int $id): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $this->service->cancel($id);

            Flash::set('success', 'Schedule was cancelled.');
            $this->redirect('schedule/show/' . $id);
        } catch (ValidationException $error) {
            Flash::set('error', reset($error->getErrors()));
            $this->redirect('schedule/show/' . $id);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Schedule');
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Soft-deletes a cancelled or completed schedule, keeping its assignments as a
     * record of what was done.
     */
    public function delete(int $id): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $this->service->delete($id);

            Flash::set('success', 'Schedule deleted. Collection history has been retained.');
            $this->redirect('schedule');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('schedule/show/' . $id);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Schedule');
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** A Cleaner’s own assignment list, filtered by status. */
    public function my(): void {
        try {
            $user = UserPermissions::require('assignment.view_own');

            $status = trim((string) ($_GET['status'] ?? ''));
            $routeView = trim((string) ($_GET['route_view'] ?? ''));

            $this->view('schedule/my', [
                'title' => 'My Collection Assignments',
                'assignments' => CollectionAssignment::forCleaner($user->getKey(), $status, $routeView),
                'status' => $status,
                'routeView' => $routeView,
            ]);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The form on which a Cleaner records that one bin has been collected. */
    public function complete(int $id): void {
        try {
            $user = UserPermissions::require('assignment.complete');
            $assignment = CollectionAssignment::find($id);

            if (
                    $assignment === null ||
                    $assignment->getCleanerId() !== $user->getKey()
            ) {
                $this->entityNotFound('Assignment');
                return;
            }

            if ($assignment->getStatus() !== 'Assigned') {
                Flash::set('error', 'Only assigned tasks can be completed.');
                $this->redirect('schedule/my');
            }

            $this->view('schedule/complete', [
                'title' => 'Complete Assignment',
                'assignment' => $assignment,
                'errors' => [],
                'values' => [],
            ]);
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves that completion, with the weight collected and any notes. */
    public function storeCompletion(int $id): void {
        $this->requirePost();

        try {
            Csrf::requireValid($_POST['_token'] ?? null);

            $assignment = $this->service->completeAssignment($id, Auth::requireLogin(), $_POST);

            Flash::set('success', 'Assignment completed.');
            $this->redirect('schedule/show/' . $assignment->getSchedule()->getKey());
        } catch (ValidationException $error) {
            $assignment = CollectionAssignment::find($id);

            if ($assignment === null) {
                $this->entityNotFound('Assignment');
                return;
            }

            http_response_code(422);

            $this->view('schedule/complete', [
                'title' => 'Complete Assignment',
                'assignment' => $assignment,
                'errors' => $error->getErrors(),
                'values' => $_POST,
            ]);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Assignment');
        } catch (AuthenticationException | AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Shared rendering for the create and edit forms, so both offer the same fields
     * and show errors the same way.
     */
    private function renderForm(string $mode, ?CollectionSchedule $schedule, array $errors, array $submitted): void {
        $firstAssignment = $schedule?->getAssignments()[0] ?? null;

        $values = $submitted !== [] ? $submitted : [
            'schedule_date' => $schedule?->getDate() ?? date('Y-m-d'),
            'time_slot' => $schedule?->getTimeSlot() ?? '09:00–12:00',
            'strategy' => $schedule?->getStrategy() ?? '',
            'cleaner_id' => $firstAssignment?->getCleanerId() ?? '',
            'notes' => $schedule?->getNotes() ?? '',
        ];

        $this->view('schedule/form', [
            'title' => $mode === 'create' ? 'Generate Schedule' : 'Edit Schedule',
            'mode' => $mode,
            'schedule' => $schedule,
            'values' => $values,
            'errors' => $errors,
            'strategies' => SchedulingStrategyFactory::names(),
            'cleaners' => User::findActiveCleaners(),
        ]);
    }
}
