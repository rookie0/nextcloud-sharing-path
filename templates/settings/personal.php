<?php

script(\OCA\SharingPath\AppInfo\Application::APP_ID, 'settings-personal');

?>

<div class="section" id="sharingPath">
    <h2><?php p($l->t('Sharing') . ' ' . $l->t('Path')); ?></h2>
    <p>
        <input type="checkbox"
               name="sharing_path_enabled"
               id="enableSharingPath"
               class="checkbox"
            <?php if ($_['enabled'] === 'yes') print_unescaped('checked="checked"'); ?>
        />
        <label for="enableSharingPath">
            <?php p($l->t('Enable') . ' ' . $l->t('sharing') . ' ' . $l->t('path')); ?>
        </label><br />
    </p>
</div>