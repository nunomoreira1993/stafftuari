<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/lib/config.php";
require_once($_SERVER['DOCUMENT_ROOT'] . '/rp/privados/privados.obj.php');

$dbprivados = new privados($db);
$totalLibertadas = $dbprivados->libertaReservasMbwayExpiradas();

echo "Mesas libertadas automaticamente: " . (int) $totalLibertadas . PHP_EOL;
