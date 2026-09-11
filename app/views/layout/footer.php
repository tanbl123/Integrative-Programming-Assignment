<?php
/**
 * Shared page footer.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 */
$uiVersion = filemtime(APP_ROOT . '/public/js/ui.js');
$validateVersion = filemtime(APP_ROOT . '/public/js/validate.js');
$unsavedVersion  = filemtime(APP_ROOT . '/public/js/unsaved.js');
$locationMapVersion = file_exists(APP_ROOT . '/public/js/location-map.js')
    ? filemtime(APP_ROOT . '/public/js/location-map.js')
    : null;
?>
</main>
<footer class="site-footer">
    <div class="wrap">
        <p>EcoCampus Waste Management System &mdash; BMIT3173 Integrative Programming, Group D</p>
        <p>Supporting UN SDG 11: Sustainable Cities and Communities</p>
    </div>
</footer>
<script src="<?= url('public/js/ui.js') ?>?v=<?= (int) $uiVersion ?>" defer></script>
<?php /* Client-side validation for every module's forms. Loaded after ui.js so the
        Clear fields button it appends is already in place. */ ?>
<script src="<?= url('public/js/validate.js') ?>?v=<?= (int) $validateVersion ?>" defer></script>
<?php /* Warns before a form with unsaved changes is abandoned. */ ?>
<script src="<?= url('public/js/unsaved.js') ?>?v=<?= (int) $unsavedVersion ?>" defer></script>
<?php if (!empty($useLeaflet)): ?>
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
        defer
    ></script>
    <script src="<?= url('public/js/location-map.js') ?>?v=<?= (int) $locationMapVersion ?>" defer></script>
<?php endif; ?>
</body>
</html>
