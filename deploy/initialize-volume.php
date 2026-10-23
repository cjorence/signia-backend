<?php
// A fresh deployment starts with empty media. Approved curriculum is imported separately.
$root = $argv[1] ?? '/data';
$active = $root.'/media';
$marker = $active.'/.signia-initialized.json';
$directories = ['app', 'app/public', 'app/private', 'app/stories', 'covers'];
if (is_dir($active)) {
    if (!is_file($marker) || json_decode(file_get_contents($marker), true) !== ['layout' => 1]) {
        throw new RuntimeException('Existing media has no valid layout marker. Review/restore; do not overwrite.');
    }
    foreach ($directories as $directory) {
        if (!is_dir($active.'/'.$directory)) throw new RuntimeException('Missing persistent media directory: '.$directory);
    }
    exit(0);
}
$staging = $root.'/.signia-initializing';
if (file_exists($staging)) throw new RuntimeException('Interrupted initialization; inspect/recover before retrying.');
foreach ($directories as $directory) {
    if (!is_dir($staging.'/'.$directory) && !mkdir($staging.'/'.$directory, 0775, true)) throw new RuntimeException('Cannot initialize media.');
}
file_put_contents($staging.'/.signia-initialized.json', json_encode(['layout' => 1]), LOCK_EX);
if (!rename($staging, $active)) throw new RuntimeException('Cannot activate media layout.');
