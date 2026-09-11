<?php
/**
 * Real subject containing Bin & Location business rules.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class BinLocationService implements BinLocationServiceInterface
{
    public function authorizeAdministrator(): void {}
    public function authorizeCleaner(): void {}

    public function searchBins(
        string $query,
        string $status,
        ?int $locationId,
        bool $includeInactive
    ): array {
        if ($status !== '' && !in_array($status, Bin::statuses(), true)) {
            $status = '';
        }

        return Bin::search(trim($query), $status, $locationId, $includeInactive);
    }

    public function findBin(int $id): ?Bin
    {
        return Bin::find($id);
    }

    public function createBin(array $data): Bin
    {
        $values = $this->validateBin($data);
        $bin = new Bin();
        $bin->setDetails(...$values);
        $bin->setFillStatus(Bin::STATUS_EMPTY);
        $bin->save();
        return $bin;
    }

    public function updateBin(int $id, array $data): Bin {
        $bin = $this->requireBin($id);

        if ($bin->hasOpenWork()) {
            throw new ValidationException([
                        'bin' => 'This bin has open complaints or assignments, so it cannot be edited or deactivated.'
            ]);
        }

        $values = $this->validateBin($data, $id);

        $bin->setDetails(...$values);
        $bin->save();

        return $bin;
    }

    public function deactivateBin(int $id): void
    {
        $bin = $this->requireBin($id);
        if ($bin->hasOpenWork()) {
            throw new ValidationException(['bin' => 'Resolve open complaints and complete or cancel assignments before deactivating this bin.']);
        }
        $bin->deactivate();
        $bin->save();
    }

    public function reactivateBin(int $id): Bin
    {
        $bin = $this->requireBin($id);
        if ($bin->getLocation() === null || $bin->getLocation()->isDeleted()) {
            throw new ValidationException(['location_id' => 'Choose an available location before reactivating this bin.']);
        }
        $bin->reactivate();
        $bin->save();
        return $bin;
    }

    public function deleteLocation(int $id): void
    {
        $location = $this->requireLocation($id);
        if ($location->getBins() !== []) {
            throw new ValidationException(['location' => 'Move all bins to another location before deleting this location.']);
        }
        $location->softDelete();
    }

    public function updateBinStatus(
        int $id,
        string $status,
        string $remarks,
        int $cleanerId
    ): Bin {
        $bin = $this->requireBin($id);
        $errors = [];
        if (!$bin->isActive()) {
            $errors['fill_status'] = 'An inactive bin cannot receive status updates.';
        }
        if (!in_array($status, Bin::statuses(), true)) {
            $errors['fill_status'] = 'Select a valid fill status.';
        }
        if (mb_strlen($remarks) > 255) {
            $errors['remarks'] = 'Remarks cannot exceed 255 characters.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $oldStatus = $bin->getFillStatus();
        $pdo = Database::getInstance()->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $bin->setFillStatus($status);
            $bin->save();

            $update = new BinStatusUpdate();
            $update->setDetails(
                $bin->getKey(),
                $cleanerId,
                $oldStatus,
                $status,
                $remarks === '' ? null : $remarks
            );
            $update->save();
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        return $bin;
    }

    public function searchLocations(string $query): array
    {
        return Location::search(trim($query));
    }

    public function findLocation(int $id): ?Location
    {
        $location = Location::find($id);
        return $location !== null && !$location->isDeleted() ? $location : null;
    }

    public function createLocation(array $data): Location
    {
        $values = $this->validateLocation($data);
        $location = new Location();
        $location->setDetails(...$values);
        $location->save();
        return $location;
    }

    public function updateLocation(int $id, array $data): Location
    {
        $location = $this->requireLocation($id);
        $values = $this->validateLocation($data);
        $location->setDetails(...$values);
        $location->save();
        return $location;
    }

    public function categories(): array
    {
        return WasteCategory::all();
    }

    private function validateBin(array $data, ?int $currentId = null): array
    {
        $this->requireScalarFields($data, ['bin_code', 'location_id', 'category_id', 'capacity_litre', 'is_active']);
        $code = mb_strtoupper(trim((string) ($data['bin_code'] ?? '')));
        $locationId = filter_var($data['location_id'] ?? null, FILTER_VALIDATE_INT);
        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
        $capacityRaw = trim((string) ($data['capacity_litre'] ?? ''));
        $capacity = $capacityRaw === '' ? null : filter_var($capacityRaw, FILTER_VALIDATE_INT);
        $active = (string) ($data['is_active'] ?? '1') === '1';
        $errors = [];

        if (isset($data['is_active']) && !in_array((string) $data['is_active'], ['0', '1'], true)) {
            $errors['is_active'] = 'Select a valid active status.';
        }

        if (!preg_match('/^[A-Z0-9-]{3,50}$/', $code)) {
            $errors['bin_code'] = 'Use 3-50 uppercase letters, numbers, or hyphens.';
        } else {
            $existing = Bin::findByCode($code);
            if ($existing !== null && $existing->getKey() !== $currentId) {
                $errors['bin_code'] = 'That bin code is already registered.';
            }
        }
        if ($locationId === false || $this->findLocation((int) $locationId) === null) {
            $errors['location_id'] = 'Select a valid location.';
        }
        if ($categoryId === false || WasteCategory::find((int) $categoryId) === null) {
            $errors['category_id'] = 'Select a valid waste category.';
        }
        if ($capacity !== null && ($capacity === false || $capacity < 1 || $capacity > 10000)) {
            $errors['capacity_litre'] = 'Capacity must be between 1 and 10,000 litres.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$code, (int) $locationId, (int) $categoryId, $capacity, $active];
    }

    private function validateLocation(array $data): array {
        $this->requireScalarFields($data, [
            'location_name', 'building_name', 'floor_no', 'description',
            'latitude', 'longitude',
        ]);

        $name = trim((string) ($data['location_name'] ?? ''));
        $building = trim((string) ($data['building_name'] ?? ''));
        $floor = trim((string) ($data['floor_no'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $latitudeRaw = trim((string) ($data['latitude'] ?? ''));
        $longitudeRaw = trim((string) ($data['longitude'] ?? ''));
        $latitude = $latitudeRaw === '' ? null : filter_var($latitudeRaw, FILTER_VALIDATE_FLOAT);
        $longitude = $longitudeRaw === '' ? null : filter_var($longitudeRaw, FILTER_VALIDATE_FLOAT);

        $errors = [];

        $validBuildings = [
            'Block A',
            'Block B',
            'Block C',
            'Block D',
            'Open Area'
        ];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['location_name'] = 'Location name is required and cannot exceed 100 characters.';
        }

        if ($building === '' || !in_array($building, $validBuildings, true)) {
            $errors['building_name'] = 'Select a valid campus building.';
        }

        if (mb_strlen($floor) > 20) {
            $errors['floor_no'] = 'Floor cannot exceed 20 characters.';
        }

        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description cannot exceed 255 characters.';
        }

        if (($latitudeRaw === '') !== ($longitudeRaw === '')) {
            $errors['coordinates'] = 'Set both latitude and longitude, or leave both blank.';
        } else {
            if ($latitudeRaw !== '' && ($latitude === false || $latitude < -90 || $latitude > 90)) {
                $errors['latitude'] = 'Latitude must be between -90 and 90.';
            }
            if ($longitudeRaw !== '' && ($longitude === false || $longitude < -180 || $longitude > 180)) {
                $errors['longitude'] = 'Longitude must be between -180 and 180.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            $name,
            $building,
            $floor === '' ? null : $floor,
            $description === '' ? null : $description,
            $latitude === false ? null : $latitude,
            $longitude === false ? null : $longitude,
        ];
    }

    private function requireBin(int $id): Bin
    {
        $bin = Bin::find($id);
        if ($bin === null) {
            throw new OutOfBoundsException('Bin not found.');
        }
        return $bin;
    }

    private function requireScalarFields(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && !is_scalar($data[$field]) && $data[$field] !== null) {
                $errors[$field] = 'Enter a single value.';
            }
        }
        if ($errors !== []) { throw new ValidationException($errors); }
    }

    private function requireLocation(int $id): Location
    {
        $location = $this->findLocation($id);
        if ($location === null) {
            throw new OutOfBoundsException('Location not found.');
        }
        return $location;
    }
}
