<?php
/**
 * Browser controller for campus locations.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class LocationController extends Controller
{
    private BinLocationServiceInterface $service;

    /** Wraps the real service in the Protection Proxy, so every call is role-checked. */
    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    /** The location directory, with search and the OpenStreetMap pins. */
    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        try {
            $this->view('location/index', [
                'title' => 'Locations',
                // Load the full directory once so Clear and map filters can
                // restore every location without another page request.
                'locations' => $this->service->searchLocations(''),
                'query' => $query,
                'user' => Auth::requireLogin(),
                'useLeaflet' => true,
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The new-location form. Administrators only. */
    public function create(): void
    {
        try {
            $this->service->authorizeAdministrator();
            $this->renderForm('create', null, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves a new location. */
    public function store(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $location = $this->service->createLocation($_POST);
            Flash::set('success', 'Location ' . $location->getLocationName() . ' was created.');
            $this->redirect('location');
        } catch (ValidationException $error) {
            http_response_code(422);
            $this->renderForm('create', null, $error->getErrors(), $_POST);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** The edit form for one location. */
    public function edit(int $id): void
    {
        try {
            $this->service->authorizeAdministrator();
            $location = $this->service->findLocation($id);
            if ($location === null) {
                $this->entityNotFound('Location');
                return;
            }
            $this->renderForm('edit', $location, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Saves changes to a location, including its optional map coordinates. */
    public function update(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->updateLocation($id, $_POST);
            Flash::set('success', 'Location details were updated.');
            $this->redirect('location');
        } catch (ValidationException $error) {
            $location = $this->service->findLocation($id);
            if ($location === null) {
                $this->entityNotFound('Location');
                return;
            }
            http_response_code(422);
            $this->renderForm('edit', $location, $error->getErrors(), $_POST);
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Location');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Soft-deletes a location. Refused while bins still sit in it, so a bin can
     * never be left pointing at somewhere that no longer exists.
     */
    public function delete(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $this->service->deleteLocation($id);
            Flash::set('success', 'Location was deleted.');
            $this->redirect('location');
        } catch (ValidationException $error) {
            Flash::set('error', implode(' ', $error->getErrors()));
            $this->redirect('location');
        } catch (OutOfBoundsException $error) {
            $this->entityNotFound('Location');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Shared rendering for the create and edit forms. */
    private function renderForm(
        string $mode,
        ?Location $location,
        array $errors,
        array $submitted
    ): void {
        $values = $submitted !== [] ? $submitted : [
            'location_name' => $location?->getLocationName() ?? '',
            'building_name' => $location?->getBuildingName() ?? '',
            'floor_no' => $location?->getFloorNo() ?? '',
            'description' => $location?->getDescription() ?? '',
            'latitude' => $location?->getLatitude() ?? '',
            'longitude' => $location?->getLongitude() ?? '',
        ];

        $this->view('location/form', [
            'title' => $mode === 'create' ? 'Add Location' : 'Edit Location',
            'mode' => $mode,
            'location' => $location,
            'values' => $values,
            'errors' => $errors,
            'useLeaflet' => true,
        ]);
    }
}
