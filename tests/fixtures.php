<?php
// Run only inside a disposable test instance: php < this file.
chdir('/var/www/html');
require 'lib/base.php';
$users = \OC::$server->get(\OCP\IUserManager::class);
$config = \OC::$server->get(\OCP\IConfig::class);
$root = \OC::$server->get(\OCP\Files\IRootFolder::class);
$shares = \OC::$server->get(\OCP\Share\IManager::class);
foreach (['alice', 'bob'] as $uid) {
    if (!$users->get($uid)) $users->createUser($uid, 'LocalReviewOnly-2026');
    $folder = $root->getUserFolder($uid);
    foreach (['public', 'private', 'linked', 'protected', 'hidden', 'expired', 'uploadonly'] as $dir) {
        if (!$folder->nodeExists($dir)) $folder->newFolder($dir);
        $path = $dir . '/file.txt';
        if (!$folder->nodeExists($path)) $folder->newFile($path, "0123456789abcdefghij\n");
    }
    foreach (['public/special #?% ü.txt', 'public/empty.txt'] as $path) {
        if (!$folder->nodeExists($path)) $folder->newFile($path, strpos($path, 'empty') !== false ? '' : 'special-content');
    }
    $config->setUserValue($uid, 'sharingpath', 'enabled', 'yes');
    $config->setUserValue($uid, 'sharingpath', 'copy_prefix', '');
    $config->setUserValue($uid, 'sharingpath', 'sharing_folder', 'public');
}
$config->setAppValue('sharingpath', 'default_enabled', 'no');
$config->setAppValue('sharingpath', 'default_copy_prefix', '');
$config->setAppValue('sharingpath', 'default_sharing_folder', '');
$folder = $root->getUserFolder('alice');
foreach (['linked', 'protected', 'hidden', 'expired', 'uploadonly'] as $dir) {
    $node = $folder->get($dir);
    $existing = $shares->getSharesBy('alice', \OCP\Share\IShare::TYPE_LINK, $node);
    if ($existing) { foreach ($existing as $old) $shares->deleteShare($old); }
    $share = $shares->newShare()->setNode($node)->setSharedBy('alice')->setShareType(\OCP\Share\IShare::TYPE_LINK)
        ->setPermissions($dir === 'uploadonly' ? \OCP\Constants::PERMISSION_CREATE : \OCP\Constants::PERMISSION_READ);
    if ($dir === 'protected') $share->setPassword('FixtureSharePassword-2026');
    if ($dir === 'hidden') $share->setHideDownload(true);
    $share = $shares->createShare($share);
    if ($dir === 'expired') {
        $connection = \OC::$server->get(\OCP\IDBConnection::class);
        $qb = $connection->getQueryBuilder();
        $qb->update('share')->set('expiration', $qb->createNamedParameter('2020-01-01 00:00:00'))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))->executeStatement();
    }
}
echo json_encode(['fixtures' => 'ready', 'version' => implode('.', \OCP\Util::getVersion())]), "\n";
