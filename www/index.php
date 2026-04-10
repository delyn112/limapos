<?php
require __DIR__ . '/vendor/autoload.php';
use Limahost\Eletron\Controllers\PrinterController;


/**
 * Process the data into readable formats
 */
$inputJSON = file_get_contents('php://input');
$orderData = json_decode($inputJSON, true);


$printerController = new PrinterController();
$printerController->handlePrinting($orderData);
