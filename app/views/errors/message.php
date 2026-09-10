<?php
/**
 * Shared safe error page.
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared presentation support
 */
?>
<section class="empty-state">
    <h1><?= e($heading) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="button button-secondary" href="<?= url() ?>">Return to dashboard</a></p>
</section>
