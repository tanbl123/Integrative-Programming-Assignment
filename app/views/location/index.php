<?php 
/** 
 * Searchable campus location register. 
 * Module : Bin & Location Management 
 */ 
?> 

<div class="page-heading"> 
    <div> 
        <h1>Campus locations</h1> 
        <p class="lead">Locations are shared reference records for bins and complaint reporting.</p> 
    </div> 

    <?php if ($user->isAdmin()): ?> 
        <a class="button" href="<?= url('location/create') ?>">Add location</a> 
    <?php endif; ?> 
</div> 
 
<form method="get" action="<?= url('location') ?>" class="filter-panel location-filter-panel" id="locationFilterForm"> 
    <div class="filter-field filter-search"> 
        <label>Search</label> 
        <input  
            type="search"  
            name="q"  
            id="locationSearch" 
            value="<?= e($query ?? '') ?>"  
            placeholder="Building, floor, or area" 
        > 
    </div> 
 
    <div class="filter-actions"> 
        <button class="button button-secondary" type="button" id="clearLocationFilters"> 
            Clear 
        </button> 
    </div> 
</form> 
 
<div class="card-grid"> 
    <?php foreach ($locations as $location): ?> 
        <article 
            class="location-card location-row"
            data-search="<?= e(mb_strtolower(
                ($location->getBuildingName() ?? '') . ' ' .
                $location->getLocationName() . ' ' .
                ($location->getFloorNo() ?? '') . ' ' .
                ($location->getDescription() ?? '')
            )) ?>"
        > 
            <div> 
                <p class="eyebrow"><?= e($location->getBuildingName() ?? 'Campus') ?></p> 
                <h2><?= e($location->getLocationName()) ?></h2> 
                <p><?= e($location->getFloorNo() ?? 'Floor not specified') ?></p> 
                <p class="muted"><?= e($location->getDescription() ?? 'No description') ?></p> 
            </div> 

            <div class="location-card-footer"> 
                <span><?= count($location->getBins()) ?> bin(s)</span> 

                <?php if ($user->isAdmin()): ?> 
                    <div class="location-actions">
                        <a 
                            class="button button-secondary button-small" 
                            href="<?= url('location/edit/' . $location->getKey()) ?>"
                            >
                            Edit
                        </a>

                        <form 
                            method="post" 
                            action="<?= url('location/delete/' . $location->getKey()) ?>" 
                            data-confirm="Delete this location? Locations with bins must be emptied first."
                            > 
                                <?= csrfField() ?> 
                            <button type="submit" class="button button-danger button-small">
                                Delete
                            </button> 
                        </form> 
                    </div>

                <?php else: ?>
                    <a 
                        class="button button-secondary button-small" 
                        href="<?= url('bin?location_id=' . $location->getKey()) ?>"
                        >
                        View bins
                    </a>
                <?php endif; ?> 
            </div> 
        </article> 
    <?php endforeach; ?> 

    <p id="noLocationResults" class="empty" <?= $locations === [] ? '' : 'hidden' ?>>
        No locations match your search.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('locationFilterForm');
    const search = document.getElementById('locationSearch');
    const clear = document.getElementById('clearLocationFilters');
    const cards = document.querySelectorAll('.location-row');
    const noResults = document.getElementById('noLocationResults');

    form.addEventListener('submit', function (event) {
        event.preventDefault();
    });

    function filterLocations() {
        const keyword = search.value.toLowerCase().trim();
        let count = 0;

        cards.forEach(card => {
            const matchSearch = card.dataset.search.includes(keyword);
            card.hidden = !matchSearch;

            if (matchSearch) {
                count++;
            }
        });

        if (noResults) {
            noResults.hidden = count !== 0;
        }
    }

    search.addEventListener('input', filterLocations);

    clear.addEventListener('click', function () {
        search.value = '';
        filterLocations();
    });

    filterLocations();
});
</script>