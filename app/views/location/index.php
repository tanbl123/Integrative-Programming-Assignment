<?php
/**
 * Searchable campus location directory with OpenStreetMap overview.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
$locationCards = [];
$mapLocations = [];
$buildingOptions = [];
$totalActiveBins = 0;
$totalAttentionBins = 0;
$mappedLocations = 0;

foreach ($locations as $position => $location) {
    $statusCounts = $location->getBinStatusCounts();
    $activeBinCount = array_sum($statusCounts);
    $attentionCount = ($statusCounts[Bin::STATUS_FULL] ?? 0)
        + ($statusCounts[Bin::STATUS_MAINTENANCE] ?? 0);
    $building = $location->getBuildingName() ?? 'Campus';
    $hasCoordinates = $location->hasCoordinates();

    $buildingOptions[$building] = true;
    $totalActiveBins += $activeBinCount;
    $totalAttentionBins += $attentionCount;
    $mappedLocations += $hasCoordinates ? 1 : 0;

    $card = [
        'location' => $location,
        'number' => $position + 1,
        'building' => $building,
        'activeBinCount' => $activeBinCount,
        'attentionCount' => $attentionCount,
        'statusCounts' => $statusCounts,
        'hasCoordinates' => $hasCoordinates,
    ];
    $locationCards[] = $card;

    if ($hasCoordinates) {
        $mapLocations[] = [
            'id' => (int) $location->getKey(),
            'number' => $position + 1,
            'name' => $location->getLocationName(),
            'building' => $building,
            'floor' => $location->getFloorNo(),
            'description' => $location->getDescription(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'activeBins' => $activeBinCount,
            'fullBins' => (int) ($statusCounts[Bin::STATUS_FULL] ?? 0),
            'maintenanceBins' => (int) ($statusCounts[Bin::STATUS_MAINTENANCE] ?? 0),
            'binsUrl' => url('bin?location_id=' . $location->getKey()),
        ];
    }
}
ksort($buildingOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Bin &amp; Location Management</p>
        <h1>Campus location directory</h1>
        <p class="lead">Find every collection point, see which areas need attention, and open the exact position on the campus map.</p>
    </div>

    <?php if ($user->isAdmin()): ?>
        <a class="button" href="<?= url('location/create') ?>">Add location</a>
    <?php endif; ?>
</div>

<section class="stats location-stats" aria-label="Location summary">
    <div class="stat">
        <span class="stat-value"><?= count($locationCards) ?></span>
        <span class="stat-label">Campus locations</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $totalActiveBins ?></span>
        <span class="stat-label">Active bins</span>
    </div>
    <div class="stat <?= $totalAttentionBins > 0 ? 'stat-attention' : '' ?>">
        <span class="stat-value"><?= $totalAttentionBins ?></span>
        <span class="stat-label">Full or maintenance bins</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $mappedLocations ?>/<?= count($locationCards) ?></span>
        <span class="stat-label">Locations pinned on map</span>
    </div>
</section>

<section class="location-map-panel" aria-labelledby="campus-map-heading">
    <div class="location-map-heading">
        <div>
            <h2 id="campus-map-heading">Campus map</h2>
            <p>Markers use saved coordinates. Select a marker or a location card to connect the map with its bin information.</p>
        </div>
        <button
            type="button"
            class="button button-secondary button-small"
            id="fitLocationMarkers"
            <?= $mappedLocations === 0 ? 'disabled' : '' ?>
        >
            Show all pins
        </button>
    </div>

    <div
        id="campusLocationMap"
        class="location-map campus-location-map"
        role="application"
        aria-label="OpenStreetMap showing campus waste collection locations"
        data-default-lat="<?= e((string) MAP_DEFAULT_LAT) ?>"
        data-default-lng="<?= e((string) MAP_DEFAULT_LNG) ?>"
        data-default-zoom="<?= (int) MAP_DEFAULT_ZOOM ?>"
    >
        <p class="map-loading">Loading OpenStreetMap…</p>
    </div>

    <div class="map-panel-footer">
        <div class="map-legend" aria-label="Map marker legend">
            <span><i class="map-key map-key-normal"></i> Normal</span>
            <span><i class="map-key map-key-full"></i> Full bin</span>
            <span><i class="map-key map-key-maintenance"></i> Maintenance</span>
        </div>
        <p class="muted" id="mapCoverageMessage">
            <?php if ($mappedLocations === 0): ?>
                No coordinates are saved yet. Edit a location and click its exact position on the map.
            <?php else: ?>
                Showing <?= $mappedLocations ?> mapped location<?= $mappedLocations === 1 ? '' : 's' ?>.
                <?= count($locationCards) - $mappedLocations ?> still need<?= count($locationCards) - $mappedLocations === 1 ? 's' : '' ?> a pin.
            <?php endif; ?>
        </p>
    </div>
</section>

<script type="application/json" id="locationMapData"><?=
    json_encode(
        $mapLocations,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    )
?></script>

<form method="get" action="<?= url('location') ?>" class="filter-panel location-directory-tools" id="locationFilterForm">
    <div class="filter-field filter-search">
        <label for="locationSearch">Search locations</label>
        <input
            type="search"
            name="q"
            id="locationSearch"
            value="<?= e($query ?? '') ?>"
            placeholder="Name, building, floor, description, or bin status"
        >
    </div>
    <div class="filter-field">
        <label for="locationBuilding">Building</label>
        <select id="locationBuilding">
            <option value="">All buildings</option>
            <?php foreach (array_keys($buildingOptions) as $building): ?>
                <option value="<?= e(mb_strtolower($building)) ?>"><?= e($building) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="locationAttention">Map and bin condition</label>
        <select id="locationAttention">
            <option value="">All conditions</option>
            <option value="full">Has full bins</option>
            <option value="maintenance">Has maintenance bins</option>
            <option value="attention">Needs attention</option>
            <option value="unpinned">Map pin missing</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="locationSort">Sort by</label>
        <select id="locationSort">
            <option value="building">Building and floor</option>
            <option value="name">Location name</option>
            <option value="bins">Most bins</option>
            <option value="attention">Needs attention first</option>
        </select>
    </div>
    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearLocationFilters">Clear</button>
    </div>
</form>

<div class="directory-results-heading">
    <h2>Location details</h2>
    <p class="muted" id="locationResultSummary" aria-live="polite"></p>
</div>

<div class="card-grid location-directory-grid" id="locationCardGrid">
    <?php foreach ($locationCards as $card): ?>
        <?php
        $location = $card['location'];
        $counts = $card['statusCounts'];
        $searchText = mb_strtolower(implode(' ', [
            $card['building'],
            $location->getLocationName(),
            $location->getFloorNo() ?? '',
            $location->getDescription() ?? '',
            ($counts[Bin::STATUS_FULL] ?? 0) > 0 ? 'full needs attention' : '',
            ($counts[Bin::STATUS_MAINTENANCE] ?? 0) > 0 ? 'maintenance needs attention' : '',
            $card['hasCoordinates'] ? 'mapped pinned' : 'unpinned map pin missing',
        ]));
        ?>
        <article
            class="location-card location-row <?= $card['attentionCount'] > 0 ? 'location-card-attention' : '' ?>"
            id="location-<?= (int) $location->getKey() ?>"
            data-location-id="<?= (int) $location->getKey() ?>"
            data-search="<?= e($searchText) ?>"
            data-building="<?= e(mb_strtolower($card['building'])) ?>"
            data-name="<?= e(mb_strtolower($location->getLocationName())) ?>"
            data-bin-count="<?= (int) $card['activeBinCount'] ?>"
            data-full-count="<?= (int) ($counts[Bin::STATUS_FULL] ?? 0) ?>"
            data-maintenance-count="<?= (int) ($counts[Bin::STATUS_MAINTENANCE] ?? 0) ?>"
            data-attention-count="<?= (int) $card['attentionCount'] ?>"
            data-mapped="<?= $card['hasCoordinates'] ? '1' : '0' ?>"
            data-original-order="<?= (int) $card['number'] ?>"
        >
            <div>
                <div class="location-card-heading">
                    <span class="location-number" aria-hidden="true"><?= (int) $card['number'] ?></span>
                    <div>
                        <p class="eyebrow"><?= e($card['building']) ?></p>
                        <h3><?= e($location->getLocationName()) ?></h3>
                    </div>
                </div>
                <p class="location-floor"><?= e($location->getFloorNo() ?? 'Floor not specified') ?></p>
                <p class="muted location-description"><?= e($location->getDescription() ?? 'No directions have been added yet.') ?></p>

                <div class="location-bin-summary" aria-label="Bin status summary">
                    <span><strong><?= (int) $card['activeBinCount'] ?></strong> active</span>
                    <span class="<?= ($counts[Bin::STATUS_FULL] ?? 0) > 0 ? 'status-full' : '' ?>">
                        <strong><?= (int) ($counts[Bin::STATUS_FULL] ?? 0) ?></strong> full
                    </span>
                    <span class="<?= ($counts[Bin::STATUS_MAINTENANCE] ?? 0) > 0 ? 'status-maintenance' : '' ?>">
                        <strong><?= (int) ($counts[Bin::STATUS_MAINTENANCE] ?? 0) ?></strong> maintenance
                    </span>
                </div>

                <p class="location-map-state <?= $card['hasCoordinates'] ? 'is-mapped' : 'is-unmapped' ?>">
                    <?= $card['hasCoordinates'] ? 'Map pin saved' : 'Map pin still needed' ?>
                </p>
            </div>

            <div class="location-card-footer">
                <div class="location-primary-actions">
                    <a class="button button-secondary button-small" href="<?= url('bin?location_id=' . $location->getKey()) ?>">View bins</a>
                    <?php if ($card['hasCoordinates']): ?>
                        <button
                            type="button"
                            class="button button-secondary button-small"
                            data-map-location="<?= (int) $location->getKey() ?>"
                        >Show on map</button>
                    <?php endif; ?>
                </div>

                <?php if ($user->isAdmin()): ?>
                    <div class="location-actions">
                        <a class="button button-secondary button-small" href="<?= url('location/edit/' . $location->getKey()) ?>">Edit</a>
                        <form
                            method="post"
                            action="<?= url('location/delete/' . $location->getKey()) ?>"
                            data-confirm="Delete this location? Locations with bins must be emptied first."
                        >
                            <?= csrfField() ?>
                            <button type="submit" class="button button-danger button-small">Delete</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>

    <div id="noLocationResults" class="empty-state location-empty-state" <?= $locationCards === [] ? '' : 'hidden' ?>>
        <h3>No matching locations</h3>
        <p>Clear the filters or try another building, floor, or area name.</p>
    </div>
</div>
