<?php
/** Collection scheduling and cleaner assignment controller. Author: Ong Kar Heng (2408830). */
class ScheduleController extends Controller
{
    private SchedulingService $service;
    public function __construct() { $this->service = new SchedulingService(); }

    public function index(): void
    {
        try {
            $user = Auth::requireLogin();
            if (!UserPermissions::can($user, 'schedule.manage')) {
                if (UserPermissions::can($user, 'assignment.view_own')) { $this->redirect('schedule/my'); }
                throw new AuthorizationException('Your role does not have scheduling access.');
            }
            $date = trim((string) ($_GET['date'] ?? ''));
            $status = trim((string) ($_GET['status'] ?? ''));
            $this->view('schedule/index', [
                'title' => 'Collection Schedules',
                'schedules' => CollectionSchedule::search($date, $status),
                'filters' => compact('date', 'status'),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function create(): void
    {
        try { UserPermissions::require('schedule.manage'); $this->renderForm('create', null, [], []); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function store(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $schedule = $this->service->create(Auth::requireLogin(), $_POST);
            Flash::set('success', 'Schedule #' . $schedule->getKey() . ' was generated using ' . $schedule->getStrategy() . '.');
            $this->redirect('schedule/show/' . $schedule->getKey());
        } catch (ValidationException $error) {
            http_response_code(422); $this->renderForm('create', null, $error->getErrors(), $_POST);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function show(int $id): void
    {
        try {
            $schedule = $this->service->findVisible(Auth::requireLogin(), $id);
            if ($schedule === null || $schedule->isDeleted()) { $this->entityNotFound('Schedule'); return; }
            $this->view('schedule/show', [
                'title' => 'Schedule #' . $id,
                'schedule' => $schedule,
                'assignments' => $schedule->getAssignments(),
                'user' => Auth::requireLogin(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function edit(int $id): void
    {
        try {
            UserPermissions::require('schedule.manage');
            $schedule = CollectionSchedule::find($id);
            if ($schedule === null || $schedule->isDeleted()) { $this->entityNotFound('Schedule'); return; }
            $this->renderForm('edit', $schedule, [], []);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function update(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $schedule = $this->service->update($id, $_POST);
            Flash::set('success', 'Schedule details and open assignments were updated.');
            $this->redirect('schedule/show/' . $schedule->getKey());
        } catch (ValidationException $error) {
            $schedule = CollectionSchedule::find($id);
            if ($schedule === null || $schedule->isDeleted()) { $this->entityNotFound('Schedule'); return; }
            http_response_code(422); $this->renderForm('edit', $schedule, $error->getErrors(), $_POST);
        } catch (OutOfBoundsException $error) { $this->entityNotFound('Schedule'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function cancel(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->cancel($id);
            Flash::set('success', 'Schedule was cancelled.');
            $this->redirect('schedule/show/' . $id);
        } catch (ValidationException $error) { Flash::set('error', reset($error->getErrors())); $this->redirect('schedule/show/' . $id); }
        catch (OutOfBoundsException $error) { $this->entityNotFound('Schedule'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function delete(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->delete($id);
            Flash::set('success', 'Schedule deleted. Collection history has been retained.');
            $this->redirect('schedule');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('schedule/show/' . $id);
        } catch (OutOfBoundsException $error) { $this->entityNotFound('Schedule'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function my(): void
    {
        try {
            $user = UserPermissions::require('assignment.view_own');
            $status = trim((string) ($_GET['status'] ?? ''));
            $this->view('schedule/my', [
                'title' => 'My Collection Assignments',
                'assignments' => CollectionAssignment::forCleaner($user->getKey(), $status),
                'status' => $status,
            ]);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function complete(int $id): void
    {
        try {
            $user = UserPermissions::require('assignment.complete');
            $assignment = CollectionAssignment::find($id);
            if ($assignment === null || $assignment->getCleanerId() !== $user->getKey()) { $this->entityNotFound('Assignment'); return; }
            $this->view('schedule/complete', [
                'title' => 'Complete Assignment', 'assignment' => $assignment, 'errors' => [], 'values' => [],
            ]);
        } catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    public function storeCompletion(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $assignment = $this->service->completeAssignment($id, Auth::requireLogin(), $_POST);
            Flash::set('success', 'Assignment completed and collection record created.');
            $this->redirect('schedule/show/' . $assignment->getSchedule()->getKey());
        } catch (ValidationException $error) {
            $assignment = CollectionAssignment::find($id);
            if ($assignment === null) { $this->entityNotFound('Assignment'); return; }
            http_response_code(422);
            $this->view('schedule/complete', [
                'title' => 'Complete Assignment', 'assignment' => $assignment,
                'errors' => $error->getErrors(), 'values' => $_POST,
            ]);
        } catch (OutOfBoundsException $error) { $this->entityNotFound('Assignment'); }
        catch (AuthenticationException|AuthorizationException $error) { $this->handleAccessFailure($error); }
    }

    private function renderForm(string $mode, ?CollectionSchedule $schedule, array $errors, array $submitted): void
    {
        $firstAssignment = $schedule?->getAssignments()[0] ?? null;
        $values = $submitted !== [] ? $submitted : [
            'schedule_date' => $schedule?->getDate() ?? date('Y-m-d'),
            'time_slot' => $schedule?->getTimeSlot() ?? '09:00–12:00',
            'strategy' => $schedule?->getStrategy() ?? 'Full Bins',
            'cleaner_id' => $firstAssignment?->getCleanerId() ?? '',
            'notes' => $schedule?->getNotes() ?? '',
        ];
        $this->view('schedule/form', [
            'title' => $mode === 'create' ? 'Generate Schedule' : 'Edit Schedule',
            'mode' => $mode, 'schedule' => $schedule, 'values' => $values,
            'errors' => $errors, 'strategies' => SchedulingStrategyFactory::names(),
            'cleaners' => User::findActiveCleaners(),
        ]);
    }
}
