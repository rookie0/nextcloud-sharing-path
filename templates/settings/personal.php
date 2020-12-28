<?php

script(\OCA\SharingPath\AppInfo\Application::APP_ID, 'settings');

?>

<div class="section" id="sharingPath">
    <h2>Sharing Path</h2>
    <input type="hidden" id="type" value="personal">
    <p>
        <input type="checkbox"
               name="sharing_path_enabled"
               id="enableSharingPath"
               class="checkbox"
            <?php if ($_['enabled'] === 'yes' || (! $_['enabled'] && $_['default_enabled'] === 'yes')) print_unescaped('checked="checked"'); ?>
        />
        <label for="enableSharingPath">
            <?php p($l->t('Enable') . ' ' . $l->t('sharing') . ' ' . $l->t('path')); ?>
        </label>
        <span class="settings-hint"><?php if ($_['default_enabled'] === 'yes' && ! $_['enabled']) print_unescaped('Set by admin'); ?></span>
    </p>
    <br />
    <p>
        <label for="copyPrefix" style="display:inline-block;width:200px">
            <?php p($l->t('Copy') . ' ' . $l->t('prefix')); ?>
        </label>
        <input type="url"
               placeholder="https://nextcloud.host/apps/sharingpath/uid"
               id="copyPrefix"
               class="text"
               maxlength="500"
               style="width:300px;"
               value="<?php p($_['prefix'] ?: $_['default_prefix']); ?>"
        />
        <span class="settings-hint"><?php if ($_['default_prefix'] && ! $_['prefix']) print_unescaped('Set by admin'); ?></span>
    </p>
    <br />
    <p>
        <label for="sharingFolder" style="display:inline-block;width:200px">
            <?php p($l->t('Sharing') . ' ' . $l->t('folder')); ?>
        </label>
        <input type="text"
               placeholder="public/folder"
               id="sharingFolder"
               class="text"
               style="width:300px;"
               value="<?php p($_['folder'] ?: $_['default_folder']); ?>"
        />
        <span class="settings-hint"><?php if ($_['default_folder'] && ! $_['folder']) print_unescaped('Set by admin, '); ?>❗⚠️❗️️All files in this folder can be accessed without share first.</span>
    </p>
</div>