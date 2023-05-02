<?php

include 'spring.php';
require_once 'courier.php';

$courier = new Courier();
$order = $courier->mapDataKeys($order, $params);
$trackingNumber = $courier->newPackage($order, $params);
$courier->packagePDF($trackingNumber);
