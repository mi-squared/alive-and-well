<?php
// $ignoreAuth = true; // This ignore is only used for developing outisde of OpenEMR

require_once __DIR__.'/../../../globals.php';
require_once __DIR__.'/vendor/autoload.php';

$app = new Mi2\Framework\App();
$app->run();
