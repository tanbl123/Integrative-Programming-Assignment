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

    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        try {
            $this->view('location/index', [
                'title' => 'Locations',
                'locations' => $this->service->searchLocations($query),
                'query' => $query,
                'user' => Auth::requireLogin(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function create(): void
    {
        try {
            $this->service->authorizeAdministrator();
            $this->renderForm('create', null, [], []);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

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
        ];

        $this->view('location/form', [
            'title' => $mode === 'create' ? 'Add Location' : 'Edit Location',
            'mode' => $mode,
            'location' => $location,
            'values' => $values,
            'errors' => $errors,
        ]);
    }
}
