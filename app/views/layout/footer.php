<?php
/**
 * Shared page footer.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 */
$uiVersion = filemtime(APP_ROOT . '/public/js/ui.js');
?>
</main>
<footer class="site-footer">
    <div class="wrap">
        <p>EcoCampus Waste Management System &mdash; BMIT3173 Integrative Programming, Group D</p>
        <p>Supporting UN SDG 11: Sustainable Cities and Communities</p>
    </div>
</footer>
<script src="<?= url('public/js/ui.js') ?>?v=<?= (int) $uiVersion ?>" defer></script>
</body>
</html>
