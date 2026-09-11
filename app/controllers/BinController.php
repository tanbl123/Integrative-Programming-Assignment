<?php
/**
 * Browser controller for bin listing, maintenance, and cleaner updates.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class BinController extends Controller
{
    private BinLocationServiceInterface $service;

    /**
     * Wraps the real service in the Protection Proxy, so every call from this
     * controller is role-checked before it reaches the bin logic.
     */
    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    /** Bin listing, with the search box, fill-status filter and location filter. */
    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $locationRaw = $_GET['location_id'] ?? null;
        $locationId = filter_var($locationRaw, FILTER_VALIDATE_INT);
        $locationId = $locationId === false ? null : $locationId;
        $user = Auth::user();
        $includeInactive = $user?->isAdmin() && ($_GET['include_inactive'] ?? '') === '1';

        try {
            $this->view('bin/index', [
                'title' => 'Bins',
                'bins' => $this->service->searchBins($query, $status, $locationId, $includeInactive),
                'locations' => $this->service->searchLocations(''),
                'statuses' => Bin::statuses(),
                'filters' => [
                    'q' => $query,
                    'status' => $status,
                    'location_id' => $locationId,
                    'include_inactive' => $includeInactive,
                ],
                'user' => Auth::requireLogin(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * One bin: its details, its status history, and the next planned collection
     * fetched from the Scheduling module’s web service rather than its tables.
     */
    public function show(int $id): void
    {
        try {
            $user = Auth::requireLogin();
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                $this->entityNotFound('Bin');
                return;
            }
            $scheduleClient = new ScheduleServiceClient();
            $scheduleStatus = $scheduleClient->getBinScheduleStatus($id);
            $this->view('bin/show', [
                'title' => $bin->getBinCode(),
                'bin' => $bin,
                'updates' => $bin->getStatusUpdates(),
                'user' => $user,
                'scheduleStatus' => $scheduleStatus,
                'scheduleServiceRequestId' => $scheduleClient->getLastRequestId(),
                'scheduleServiceError' => $scheduleClient->getLastError(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The bin registration form. Administrators only - the Proxy decides that. */
    public function create(): void
    {
        try {
            $this->service->authorizeAdministrator();
            $this->renderForm('create', null, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves a newly registered bin. */
    public function store(): void
    {
        $this->requirePost();
        $data = $_POST;
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $bin = $this->service->createBin($data);
            Flash::set('success', 'Bin ' . $bin->getBinCode() . ' was registered.');
            $this->redirect('bin/show/' . $bin->getKey());
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderForm('create', null, $error->getErrors(), $data);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The edit form for one bin. */
    public function edit(int $id): void
    {
        try {
            $this->service->authorizeAdministrator();
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                $this->entityNotFound('Bin');
                return;
            }
            $this->renderForm('edit', $bin, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves changes to a bin. */
    public function update(int $id): void
    {
        $this->requirePost();
        $data = $_POST;
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $bin = $this->service->updateBin($id, $data);
            Flash::set('success', 'Bin details were updated.');
            $this->redirect('bin/show/' . $bin->getKey());
        } catch (ValidationException $error) {
            http_response_code(422);
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                $this->entityNotFound('Bin');
                return;
            }
            $this->renderForm('edit', $bin, $error->getErrors(), $data);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Bin');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Retires a bin from service. It is kept, not deleted, so its history and the
     * complaints that name it stay valid.
     */
    public function deactivate(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->deactivateBin($id);
            Flash::set('success', 'Bin was deactivated and retained for historical records.');
            $this->redirect('bin/show/' . $id);
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('bin/show/' . $id);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Bin');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The form on which a Cleaner records a bin’s fill status. */
    public function status(int $id): void
    {
        try {
            $this->service->authorizeCleaner();
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                $this->entityNotFound('Bin');
                return;
            }
            $this->view('bin/status', [
                'title' => 'Update ' . $bin->getBinCode(),
                'bin' => $bin,
                'statuses' => Bin::statuses(),
                'errors' => [],
                'values' => ['fill_status' => $bin->getFillStatus(), 'remarks' => ''],
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Brings a retired bin back into service. */
    public function reactivate(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->reactivateBin($id);
            Flash::set('success', 'Bin was reactivated.');
            $this->redirect('bin/show/' . $id);
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('bin/show/' . $id);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Bin');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Saves a fill-status change. The Proxy checks both that the caller may record
     * one and that the cleaner id is their own.
     */
    public function updateStatus(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = Auth::requireLogin();
            $bin = $this->service->updateBinStatus(
                $id,
                $this->input('fill_status'),
                $this->input('remarks'),
                (int) $user->getKey()
            );
            Flash::set('success', 'Status for ' . $bin->getBinCode() . ' was recorded.');
            $this->redirect('bin/show/' . $bin->getKey());
        } catch (ValidationException $error) {
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                $this->entityNotFound('Bin');
                return;
            }
            http_response_code(422);
            $this->view('bin/status', [
                'title' => 'Update ' . $bin->getBinCode(),
                'bin' => $bin,
                'statuses' => Bin::statuses(),
                'errors' => $error->getErrors(),
                'values' => [
                    'fill_status' => $this->input('fill_status'),
                    'remarks' => $this->input('remarks'),
                ],
            ]);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Bin');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Shared rendering for the create and edit forms, so both show the same fields
     * and the same errors.
     */
    private function renderForm(
        string $mode,
        ?Bin $bin,
        array $errors,
        array $submitted
    ): void {
        $values = $submitted !== [] ? $submitted : [
            'bin_code' => $bin?->getBinCode() ?? '',
            'location_id' => $bin?->getLocationId() ?? '',
            'category_id' => $bin?->getCategoryId() ?? '',
            'capacity_litre' => $bin?->getCapacityLitre() ?? '',
            'is_active' => $bin === null || $bin->isActive() ? '1' : '0',
        ];

        $this->view('bin/form', [
            'title' => $mode === 'create' ? 'Register Bin' : 'Edit Bin',
            'mode' => $mode,
            'bin' => $bin,
            'values' => $values,
            'errors' => $errors,
            'locations' => $this->service->searchLocations(''),
            'categories' => $this->service->categories(),
        ]);
    }
}
