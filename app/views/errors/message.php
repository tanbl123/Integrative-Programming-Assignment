<?php
/**
 * Shared safe error page.
 * Module : Shared presentation support
 */
?>
<section class="empty-state">
    <h1><?= e($heading) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="button button-secondary" href="<?= url() ?>">Return to dashboard</a></p>
</section>
