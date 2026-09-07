<?php
/**
 * Shared page footer.
 *
 * Author : Tan Boon Leong (2402865)
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
