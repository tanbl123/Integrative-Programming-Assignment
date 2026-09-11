/*
 * OpenStreetMap directory and coordinate picker for campus locations.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
(() => {
    'use strict';

    const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>';

    function number(value, fallback) {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function addOpenStreetMapTiles(map) {
        return window.L.tileLayer(TILE_URL, {
            maxZoom: 19,
            attribution: ATTRIBUTION,
        }).addTo(map);
    }

    function defaultView(element) {
        return {
            latitude: number(element.dataset.defaultLat, 3.215118),
            longitude: number(element.dataset.defaultLng, 101.728345),
            zoom: number(element.dataset.defaultZoom, 16),
        };
    }

    function markerKind(location) {
        if (location.fullBins > 0) return 'full';
        if (location.maintenanceBins > 0) return 'maintenance';
        return 'normal';
    }

    function markerIcon(location) {
        const kind = markerKind(location);
        return window.L.divIcon({
            className: 'campus-map-marker-wrap',
            html: `<span class="campus-map-marker marker-${kind}">${Number(location.number)}</span>`,
            iconSize: [36, 42],
            iconAnchor: [18, 40],
            popupAnchor: [0, -38],
        });
    }

    function popupContent(location) {
        const details = [location.building, location.floor].filter(Boolean).map(escapeHtml).join(' · ');
        const attention = location.fullBins > 0
            ? `<strong class="popup-attention">${Number(location.fullBins)} full bin(s)</strong>`
            : location.maintenanceBins > 0
                ? `<strong class="popup-maintenance">${Number(location.maintenanceBins)} under maintenance</strong>`
                : '<span>No urgent bin condition</span>';
        const osmUrl = 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(location.latitude)
            + '&mlon=' + encodeURIComponent(location.longitude)
            + '#map=19/' + encodeURIComponent(location.latitude) + '/' + encodeURIComponent(location.longitude);

        return `<div class="map-popup">
            <strong>${escapeHtml(location.name)}</strong>
            <span>${details}</span>
            <span>${Number(location.activeBins)} active bin(s)</span>
            ${attention}
            <div class="map-popup-actions">
                <a href="${escapeHtml(location.binsUrl)}">View bins</a>
                <a href="${escapeHtml(osmUrl)}" target="_blank" rel="noopener">Open larger map</a>
            </div>
        </div>`;
    }

    function markSelectedCard(locationId) {
        document.querySelectorAll('.location-row.is-map-selected').forEach(card => {
            card.classList.remove('is-map-selected');
        });
        document.getElementById(`location-${locationId}`)?.classList.add('is-map-selected');
    }

    function initDirectoryMap() {
        const mapElement = document.getElementById('campusLocationMap');
        const dataElement = document.getElementById('locationMapData');
        if (!mapElement || !dataElement) return null;

        if (!window.L) {
            mapElement.innerHTML = '<p class="map-unavailable">OpenStreetMap could not load. Location cards and filters are still available.</p>';
            return null;
        }

        let locations = [];
        try {
            locations = JSON.parse(dataElement.textContent || '[]');
        } catch (error) {
            mapElement.innerHTML = '<p class="map-unavailable">Map location data could not be read.</p>';
            return null;
        }

        mapElement.textContent = '';
        const initial = defaultView(mapElement);
        const map = window.L.map(mapElement, {scrollWheelZoom: false})
            .setView([initial.latitude, initial.longitude], initial.zoom);
        addOpenStreetMapTiles(map);

        const markers = new Map();
        locations.forEach(location => {
            const marker = window.L.marker(
                [Number(location.latitude), Number(location.longitude)],
                {icon: markerIcon(location), title: location.name, keyboard: true}
            );
            marker.bindPopup(popupContent(location));
            marker.on('click', () => markSelectedCard(location.id));
            marker.addTo(map);
            markers.set(String(location.id), {marker, location});
        });

        function visibleEntries() {
            return [...markers.entries()].filter(([id]) => {
                const card = document.getElementById(`location-${id}`);
                return card && !card.hidden;
            });
        }

        function fitVisible() {
            const entries = visibleEntries();
            if (entries.length === 0) return;
            if (entries.length === 1) {
                const location = entries[0][1].location;
                map.setView([location.latitude, location.longitude], 18);
                entries[0][1].marker.openPopup();
                return;
            }
            map.fitBounds(
                window.L.latLngBounds(entries.map(([, entry]) => [entry.location.latitude, entry.location.longitude])),
                {padding: [35, 35], maxZoom: 18}
            );
        }

        function refreshVisibleMarkers() {
            markers.forEach(({marker}, id) => {
                const card = document.getElementById(`location-${id}`);
                if (card && !card.hidden) {
                    if (!map.hasLayer(marker)) marker.addTo(map);
                } else if (map.hasLayer(marker)) {
                    map.removeLayer(marker);
                }
            });
        }

        function focusLocation(locationId) {
            const entry = markers.get(String(locationId));
            if (!entry) return;
            map.setView([entry.location.latitude, entry.location.longitude], 19);
            entry.marker.openPopup();
            markSelectedCard(locationId);
            mapElement.scrollIntoView({behavior: 'smooth', block: 'center'});
        }

        document.getElementById('fitLocationMarkers')?.addEventListener('click', fitVisible);
        document.querySelectorAll('[data-map-location]').forEach(button => {
            button.addEventListener('click', () => focusLocation(button.dataset.mapLocation));
        });

        if (locations.length > 0) fitVisible();
        return {refreshVisibleMarkers};
    }

    function initDirectoryFilters(mapApi) {
        const form = document.getElementById('locationFilterForm');
        const grid = document.getElementById('locationCardGrid');
        if (!form || !grid) return;

        const search = document.getElementById('locationSearch');
        const building = document.getElementById('locationBuilding');
        const condition = document.getElementById('locationAttention');
        const sort = document.getElementById('locationSort');
        const clear = document.getElementById('clearLocationFilters');
        const noResults = document.getElementById('noLocationResults');
        const resultSummary = document.getElementById('locationResultSummary');
        const cards = [...grid.querySelectorAll('.location-row')];

        function conditionMatches(card) {
            switch (condition.value) {
                case 'full': return Number(card.dataset.fullCount) > 0;
                case 'maintenance': return Number(card.dataset.maintenanceCount) > 0;
                case 'attention': return Number(card.dataset.attentionCount) > 0;
                case 'unpinned': return card.dataset.mapped === '0';
                default: return true;
            }
        }

        function compareCards(left, right) {
            if (sort.value === 'name') {
                return left.dataset.name.localeCompare(right.dataset.name);
            }
            if (sort.value === 'bins') {
                return Number(right.dataset.binCount) - Number(left.dataset.binCount)
                    || Number(left.dataset.originalOrder) - Number(right.dataset.originalOrder);
            }
            if (sort.value === 'attention') {
                return Number(right.dataset.attentionCount) - Number(left.dataset.attentionCount)
                    || Number(left.dataset.originalOrder) - Number(right.dataset.originalOrder);
            }
            return Number(left.dataset.originalOrder) - Number(right.dataset.originalOrder);
        }

        function applyFilters() {
            const keyword = search.value.toLowerCase().trim();
            let visibleCount = 0;

            cards.sort(compareCards).forEach(card => {
                const matches = card.dataset.search.includes(keyword)
                    && (!building.value || card.dataset.building === building.value)
                    && conditionMatches(card);
                card.hidden = !matches;
                if (matches) visibleCount++;
                grid.insertBefore(card, noResults);
            });

            noResults.hidden = visibleCount !== 0;
            resultSummary.textContent = `Showing ${visibleCount} of ${cards.length} location${cards.length === 1 ? '' : 's'}.`;
            mapApi?.refreshVisibleMarkers();
        }

        form.addEventListener('submit', event => event.preventDefault());
        [search, building, condition, sort].forEach(control => {
            control.addEventListener(control === search ? 'input' : 'change', applyFilters);
        });
        clear.addEventListener('click', () => {
            search.value = '';
            building.value = '';
            condition.value = '';
            sort.value = 'building';
            applyFilters();
            search.focus();
        });
        applyFilters();
    }

    function initLocationPicker() {
        const form = document.getElementById('locationForm');
        const mapElement = document.getElementById('locationPickerMap');
        const latitude = document.getElementById('locationLatitude');
        const longitude = document.getElementById('locationLongitude');
        const status = document.getElementById('mapPickerStatus');
        if (!form || !mapElement || !latitude || !longitude || !status) return;

        if (!window.L) {
            mapElement.innerHTML = '<p class="map-unavailable">OpenStreetMap could not load. You can still enter coordinates directly.</p>';
            return;
        }

        mapElement.textContent = '';
        const initial = defaultView(form);
        const savedLatitude = number(latitude.value, null);
        const savedLongitude = number(longitude.value, null);
        const hasSavedPoint = savedLatitude !== null && savedLongitude !== null;
        const map = window.L.map(mapElement).setView(
            hasSavedPoint ? [savedLatitude, savedLongitude] : [initial.latitude, initial.longitude],
            hasSavedPoint ? 19 : initial.zoom
        );
        addOpenStreetMapTiles(map);
        let marker = null;

        function dispatchCoordinateChange(input) {
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.dispatchEvent(new Event('change', {bubbles: true}));
        }

        function placeMarker(lat, lng, message, moveMap = true) {
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
            if (marker === null) {
                marker = window.L.marker([lat, lng], {draggable: true}).addTo(map);
                marker.on('dragend', event => {
                    const point = event.target.getLatLng();
                    placeMarker(point.lat, point.lng, 'Pin moved. Save the form to keep this position.', false);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }
            latitude.value = Number(lat).toFixed(7);
            longitude.value = Number(lng).toFixed(7);
            dispatchCoordinateChange(latitude);
            dispatchCoordinateChange(longitude);
            status.textContent = message;
            if (moveMap) map.setView([lat, lng], Math.max(map.getZoom(), 18));
        }

        function syncTypedCoordinates() {
            const lat = number(latitude.value, null);
            const lng = number(longitude.value, null);
            if (lat !== null && lng !== null) {
                placeMarker(lat, lng, 'Coordinates entered. Save the form to keep this position.');
            }
        }

        if (hasSavedPoint) {
            placeMarker(savedLatitude, savedLongitude, 'Saved pin loaded. Click elsewhere to move it.', false);
        }
        map.on('click', event => {
            placeMarker(event.latlng.lat, event.latlng.lng, 'Pin selected. Save the form to keep this position.');
        });
        latitude.addEventListener('change', syncTypedCoordinates);
        longitude.addEventListener('change', syncTypedCoordinates);

        document.getElementById('clearLocationPin')?.addEventListener('click', () => {
            latitude.value = '';
            longitude.value = '';
            dispatchCoordinateChange(latitude);
            dispatchCoordinateChange(longitude);
            if (marker !== null) {
                map.removeLayer(marker);
                marker = null;
            }
            status.textContent = 'Map pin removed. Save the form to apply this change.';
        });

        document.getElementById('useCurrentPosition')?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                status.textContent = 'This browser cannot provide your current position.';
                return;
            }
            status.textContent = 'Waiting for your browser to provide the current position…';
            navigator.geolocation.getCurrentPosition(
                position => placeMarker(
                    position.coords.latitude,
                    position.coords.longitude,
                    'Current position selected. Check the pin, then save the form.'
                ),
                () => { status.textContent = 'Current position was unavailable. Click the map instead.'; },
                {enableHighAccuracy: true, timeout: 10000, maximumAge: 30000}
            );
        });
    }

    const mapApi = initDirectoryMap();
    initDirectoryFilters(mapApi);
    initLocationPicker();
})();
