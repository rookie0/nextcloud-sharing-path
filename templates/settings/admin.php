<?php

script(\OCA\SharingPath\AppInfo\Application::APP_ID, 'settings');

?>

<div class="section" id="sharingPath">
    <h2>Sharing Path</h2>
    <input type="hidden" id="type" value="admin">
    <p>
        <input type="checkbox"
               name="sharing_path_enabled"
               id="enableSharingPath"
               class="checkbox"
            <?php if ($_['enabled'] === 'yes') print_unescaped('checked="checked"'); ?>
        />
        <label for="enableSharingPath">
            <?php p($l->t('Default') . ' ' . $l->t('Enable') . ' ' . $l->t('sharing') . ' ' . $l->t('path')); ?>
        </label>
    </p>
    <br />
    <p>
        <label for="copyPrefix" style="display:inline-block;width:200px">
            <?php p($l->t('Default') . ' ' . $l->t('copy') . ' ' . $l->t('prefix')); ?>
        </label>
        <input type="url"
               placeholder="https://nextcloud.host/apps/sharingpath"
               id="copyPrefix"
               class="text"
               maxlength="500"
               style="width:300px;"
               value="<?php p($_['prefix']); ?>"
        />
        <span class="settings-hint"></span>
    </p>
    <br />
    <p>
        <label for="sharingFolder" style="display:inline-block;width:200px">
            <?php p($l->t('Default') . ' ' . $l->t('sharing') . ' ' . $l->t('folder')); ?>
        </label>
        <input type="text"
               placeholder="public/folder"
               id="sharingFolder"
               class="text"
               style="width:300px;"
               value="<?php p($_['folder']); ?>"
        />
        <span class="settings-hint">❗⚠️❗️️All files in this folder can be accessed without share first.</span>
    </p>
</div>