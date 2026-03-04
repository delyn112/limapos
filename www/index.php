<?php
require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;

$inputJSON = file_get_contents('php://input');
$orderData = json_decode($inputJSON, true);


// --- Setup printer ---
$connector = new WindowsPrintConnector("POS-80"); // Replace with your printer name
$profile = CapabilityProfile::load("simple");
$printer = new Printer($connector, $profile);
$printer->initialize();

$orderNumber = $orderData['order_number'];
$date = date("l, d F Y H:i:sA", strtotime($orderData['date']));
$customer = $orderData['full_name'];
$table = $orderData['table_name'];
//--- Header ---

// Load image
// Download image to temp file
$logoUrl = $orderData["logo"];
if ($logoUrl) {
    $imageContent = file_get_contents($logoUrl);
    $im = imagecreatefromstring($imageContent);

    if ($im !== false) {
        $maxWidth = 384; // 58mm printer. Use 576 for 80mm printer
        $width = imagesx($im);
        $height = imagesy($im);

        // Resize width if needed
        if ($width > $maxWidth) {
            $newHeight = intval($height * ($maxWidth / $width));
            $resized = imagescale($im, $maxWidth, $newHeight);
            imagedestroy($im);
            $im = $resized;
            $width = imagesx($im);
            $height = imagesy($im);
        }

        // Convert to 1-bit B/W
        $bw = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($bw, 255, 255, 255);
        $black = imagecolorallocate($bw, 0, 0, 0);
        imagefill($bw, 0, 0, $white);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = ($r + $g + $b) / 3;
                if ($gray < 150) {
                    imagesetpixel($bw, $x, $y, $black);
                }
            }
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Split into small slices to avoid buffer overflow
        $sliceHeight = 64; // small slice height
        for ($y = 0; $y < $height; $y += $sliceHeight) {
            $h = min($sliceHeight, $height - $y);
            $slice = imagecreatetruecolor($width, $h);
            imagefill($slice, 0, 0, $white);
            imagecopy($slice, $bw, 0, 0, 0, $y, $width, $h);

            $tempFile = tempnam(sys_get_temp_dir(), 'logo') . '.png';
            imagepng($slice, $tempFile);
            imagedestroy($slice);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $logoSlice = EscposImage::load($tempFile, false);
            $printer->bitImage($logoSlice);
            $printer->feed(1);
            unlink($tempFile);
        }

        imagedestroy($bw);
        imagedestroy($im);
    }
}

$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text($orderData['company_name'] . "\n");
$printer->setLineSpacing(45);
$printer->text($orderData['company_profile'] . "\n");
$printer->text("----------------------------------------------\n");

// --- Order info ---
$printer->setJustification(Printer::JUSTIFY_LEFT);
$printer->text("Order: $orderNumber\n");
if($customer)
{
    $printer->text("Customer: $customer\n");
}
if($table)
{
    $printer->text("Table: $table\n");
}
$printer->text("Date:$date\n");
$printer->text("----------------------------------------------\n");

// --- Items ---
$nameWidth = 50;
foreach ($orderData['items'] as $item) {
    $printer->setLineSpacing(40);
    $printer->text($item['quantity'] . "x " . $item['description'] . "  " . ($orderData['currency_symbol'].number_format($item['total'], 2)) . " \n");
    $printer->text("\n");
}

//--- Totals ---
$printer->setLineSpacing(30);
$printer->text("----------------------------------------------\n");
$printer->text("Subtotal: " . $orderData['currency_symbol'].number_format($orderData['subtotal']) . " \n");
if ($orderData['discount'] > 0) {
    $printer->text("Discount: " . $orderData['currency_symbol'].number_format($orderData['discount']) . " \n");
}

if ($orderData['tax_amount'] > 0) {
    $printer->text("Tax Amount: " . $orderData['currency_symbol'].number_format($orderData['tax_amount']) . " \n");
}
if ($orderData['voucher'] > 0) {
    $printer->text("Coupon: " . $orderData['currency_symbol'].number_format($orderData['voucher']) . " \n");
}
if ($orderData['shipping_fee'] > 0) {
    $printer->text("Shipping Fee: " . $orderData['currency_symbol'].number_format($orderData['shipping_fee']) . " \n");
}
if ($orderData['amount_due'] > 0) {
    $printer->text("Total Due: " . $orderData['currency_symbol'].number_format($orderData['amount_due']) . " \n");
    $printer->text("Amount Paid: " . $orderData['currency_symbol'].number_format($orderData['amount_paid']) . " \n");
}

$printer->text("TOTAL: " . $orderData['currency_symbol'].number_format($orderData['total']) . " \n");
$printer->text("----------------------------------------------\n");

// --- Footer ---
$printer->setLineSpacing(100);
$printer->text("\n");
$printer->setLineSpacing(40);
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text("Thank you for your purchase!\n");
$printer->text("Visit again!\n");
$printer->setLineSpacing(150);
$printer->text("\n");

//--- Cut paper ---
$printer->cut();
$printer->close();

echo "Receipt printed successfully!";


