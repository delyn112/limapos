<?php
require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;

$inputJSON = file_get_contents('php://input');
$orderData = json_decode($inputJSON, true);

function printLogoInSlices(Printer $printer, string $logoPath, int $maxWidth = 300, int $sliceHeight = 24): void
{
    if (!file_exists($logoPath)) {
        return;
    }

    $imageContent = file_get_contents($logoPath);
    if ($imageContent === false) {
        return;
    }

    $im = imagecreatefromstring($imageContent);
    if ($im === false) {
        return;
    }

    $width = imagesx($im);
    $height = imagesy($im);

    // Resize smaller for weak printers
    if ($width > $maxWidth) {
        $newHeight = (int) round($height * ($maxWidth / $width));

        $resized = imagecreatetruecolor($maxWidth, $newHeight);
        $whiteBg = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $whiteBg);

        imagecopyresampled(
            $resized,
            $im,
            0,
            0,
            0,
            0,
            $maxWidth,
            $newHeight,
            $width,
            $height
        );

        imagedestroy($im);
        $im = $resized;
        $width = imagesx($im);
        $height = imagesy($im);
    }

    // Convert to monochrome
    $bw = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($bw, 255, 255, 255);
    $black = imagecolorallocate($bw, 0, 0, 0);
    imagefill($bw, 0, 0, $white);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($im, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            $gray = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

            if ($gray < 180) {
                imagesetpixel($bw, $x, $y, $black);
            } else {
                imagesetpixel($bw, $x, $y, $white);
            }
        }
    }

    $printer->setJustification(Printer::JUSTIFY_CENTER);

    for ($y = 0; $y < $height; $y += $sliceHeight) {
        $h = min($sliceHeight, $height - $y);

        $slice = imagecreatetruecolor($width, $h);
        imagefill($slice, 0, 0, $white);

        imagecopy(
            $slice,
            $bw,
            0,
            0,
            0,
            $y,
            $width,
            $h
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'escpos_slice_');
        if ($tempFile === false) {
            imagedestroy($slice);
            continue;
        }

        $tempPng = $tempFile . '.png';
        rename($tempFile, $tempPng);
        imagepng($slice, $tempPng);

        imagedestroy($slice);

        try {
            $logoSlice = EscposImage::load($tempPng, false);
            $printer->graphic($logoSlice);
            // no feed here
        } catch (\Throwable $e) {
            // stop image printing quietly if printer rejects slice
            @unlink($tempPng);
            break;
        }

        @unlink($tempPng);

        // very small pause can help weak Windows spooler/printer combos
        usleep(50000); // 50ms
    }

    $printer->feed(1);

    imagedestroy($bw);
    imagedestroy($im);
}


$printers = $orderData["printers"];
if(count($printers) > 0)
{
    foreach($printers as $myPrinter)
    {
        // Skip if no name/IP
       //if (empty($printerData["printer"])) continue;
        $connector = null;
        $printableCategory = json_decode($myPrinter['categories'], true);
        $itemsCategory = array_column($orderData['items'], 'category_id');

        $matched = array_intersect($itemsCategory ?? [], $printableCategory ?? []);


        if(!empty($matched) || $myPrinter["is_default"] == "1")
        {
            if($myPrinter["connection_type"] == "usb")
            {
                $connector = new WindowsPrintConnector($myPrinter["printer"]);
            }elseif($myPrinter["connection_type"] == "ethernet")
            {
                $port = $myPrinter["printer_port"] ? $myPrinter["printer_port"] : '9100';
                $connector = new NetworkPrintConnector($myPrinter["printer_ip"], $port );
            }


            $profile = CapabilityProfile::load("simple");
            $printer = new Printer($connector, $profile);

            try {
                $printer->initialize();

                $orderNumber = $orderData['order_number'] ?? '';
                $customer = $orderData['full_name'] ?? '';
                $table = $orderData['table_name'] ?? '';
                $currencySymbol = $orderData['currency_symbol'] ?? 'RM';

                $date = '';
                if (!empty($orderData['date']) && !empty($orderData['setting']['date_format'])) {
                    $date = date($orderData['setting']['date_format'], strtotime($orderData['date']));
                }

                if (
                    ($orderData['setting']['enable_time_on_receipt'] ?? "0") === "1" &&
                    !empty($orderData['created_at']) &&
                    !empty($orderData['setting']['date_format']) &&
                    !empty($orderData['setting']['time_format'])
                ) {
                    $date = date(
                        $orderData['setting']['date_format'] . ' ' . $orderData['setting']['time_format'],
                        strtotime($orderData['created_at'])
                    );
                }

                // Header
                if (($orderData['setting']['show_logo'] ?? "0") === "1") {
                    $logoPath = "C:\\xampp\\htdocs\\Projects\\limapay\\public\\images\\New Logo Black.png";
                    printLogoInSlices($printer, $logoPath, 300, 24);
                }

                $printer->setJustification(Printer::JUSTIFY_CENTER);

                if (!empty($orderData['company_name'])) {
                    $printer->text($orderData['company_name'] . "\n");
                }

                $printer->setLineSpacing(38);

                if (!empty($orderData['company_profile'])) {
                    $printer->text($orderData['company_profile'] . "\n");
                }

                $printer->text("----------------------------------------------\n");

                // Order info
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text("Order: $orderNumber\n");

                if (!empty($customer)) {
                    $printer->text("Customer: $customer\n");
                }

                if (!empty($table)) {
                    $printer->text("Table: $table\n");
                }

                if (!empty($date)) {
                    $printer->text("Date: $date\n");
                }

                if (($orderData['setting']['show_payment_method'] ?? "0") === "1") {
                    $printer->text("Payment Method: " . ($orderData['payment_gateway_name'] ?? '') . "\n");
                }

                if (($orderData['setting']['show_cashier'] ?? "0") === "1") {
                    $cashier = $orderData['emp_name'] ?? ($orderData['emp_full_name'] ?? '');
                    $printer->text("Cashier: " . $cashier . "\n");
                }

                $printer->text("----------------------------------------------\n");

                // Items
                if (!empty($orderData['items']) && is_array($orderData['items'])) {
                    $printer->setLineSpacing(2);


                    foreach ($orderData['items'] as $item) {


                        if($myPrinter["is_default"] == "1")
                        {
                            $qty = $item['quantity'] ?? 0;
                            $desc = $item['description'] ?? '';
                            $lineTotal = isset($item['total']) ? number_format((float)$item['total'], 2) : '0.00';

                            $printer->text($qty . "x " . $desc . "  " . $currencySymbol . $lineTotal . "\n\n");
                        }elseif(in_array($item["category_id"], $printableCategory)){
                            $qty = $item['quantity'] ?? 0;
                            $desc = $item['description'] ?? '';
                           // $lineTotal = isset($item['total']) ? number_format((float)$item['total'], 2) : '0.00';

                            $printer->text($qty . "x " . $desc . "\n\n");
                        }

                    }
                }

                // Totals
                $subtotal = (float)($orderData['subtotal'] ?? 0);
                $discount = (float)($orderData['discount'] ?? 0);
                $tax = (float)($orderData['tax_amount'] ?? 0);
                $voucher = (float)($orderData['voucher'] ?? 0);
                $shipping = (float)($orderData['shipping_fee'] ?? 0);
                $due = (float)($orderData['amount_due'] ?? 0);
                $paid = (float)($orderData['amount_paid'] ?? 0);
                $total = (float)($orderData['total'] ?? 0);

                $printer->setLineSpacing(38);
                $printer->text("----------------------------------------------\n");
                $printer->text("Subtotal: " . $currencySymbol . number_format($subtotal, 2) . "\n");

                if ($discount > 0) {
                    $printer->text("Discount: " . $currencySymbol . number_format($discount, 2) . "\n");
                }

                if ($tax > 0) {
                    $printer->text("Tax Amount: " . $currencySymbol . number_format($tax, 2) . "\n");
                }

                if ($voucher > 0) {
                    $printer->text("Coupon: " . $currencySymbol . number_format($voucher, 2) . "\n");
                }

                if ($shipping > 0) {
                    $printer->text("Shipping Fee: " . $currencySymbol . number_format($shipping, 2) . "\n");
                }

                if ($due > 0 && (($orderData['setting']['show_due_amount'] ?? "0") === "1")) {
                    $printer->text("Total Due: " . $currencySymbol . number_format($due, 2) . "\n");
                }

                if (($orderData['setting']['show_amount_paid'] ?? "0") === "1") {
                    $printer->text("Amount Paid: " . $currencySymbol . number_format($paid, 2) . "\n");
                }

                $printer->text("Total: " . $currencySymbol . number_format($total, 2) . "\n");
                $printer->text("----------------------------------------------\n");

                // Footer
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("\n");

//    if (($orderData['setting']['show_qr'] ?? "0") === "1" && !empty($orderData['setting']['qr_content'])) {
//        $printer->qrCode(
//            $orderData['setting']['qr_content'],
//            Printer::QR_ECLEVEL_M,
//            6
//        );
//    }

                if (!empty($orderData['setting']['footer_note'])) {
                    $printer->text($orderData['setting']['footer_note'] . "\n");
                } else {
                    $printer->text("Thank you for your purchase!\n");
                }

                $printer->text("Visit again!\n\n");

                if (($orderData['setting']['auto_open_cash_drawer'] ?? "0") === "1") {
                    $printer->pulse();
                }

                $printer->cut();
                echo "Receipt printed successfully!";
            } catch (\Throwable $e) {
                file_put_contents('error.php', "Print failed: " . $e->getMessage());
                echo "Print failed: " . $e->getMessage();
            } finally {
                $printer->close();
            }
        }

    }
}

