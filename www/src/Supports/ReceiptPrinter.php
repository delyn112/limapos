<?php

namespace Limahost\Eletron\Supports;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Limahost\Eletron\Supports\PrintSupport;



class ReceiptPrinter
{

    private $printer;
    private $logoPath = __DIR__ . "/../../assets/images";
    private $printSupport;

    public function __construct()
    {
        $this->printSupport = new PrintSupport();
    }


    private function makeLogo(mixed $param, mixed $printer): void
    {
        if (empty($param['logo'])) {
            return;
        }

        // Ensure directory exists
        if (!is_dir($this->logoPath)) {
            mkdir($this->logoPath, 0777, true);
        }

        $imageUrl  = $param['logo'];
        $imagePath = $this->logoPath . "/company_logo.png";

        // Download image safely
        $imageData = @file_get_contents($imageUrl);
        if ($imageData === false) {
            return; // stop if image cannot be loaded
        }

        file_put_contents($imagePath, $imageData);

        try {
            $logo = EscposImage::load($imagePath, false);

            // Center the logo
            $printer->setJustification($printer::JUSTIFY_CENTER);

            // Print image
            $printer->bitImage($logo);

            // Add spacing after logo
            $printer->text("\n");
        } catch (\Exception $e) {
            // Optional: log error instead of breaking print
        }
    }



    public function handle(mixed $parameter, mixed $printer, ?array $items = [])
    {
        $this->printer = $printer;
        /**
         * 
         *  make the logo
         */
        ///$this->makeLogo($parameter, $printer);

        /**
         * Make the receipt business info header
         */

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);  //enable bold
        $this->printSupport->printWrappedText($parameter["company_name"], $this->printer);

        $printer->setEmphasis(false);  //disable bold
        $this->printSupport->printWrappedText($parameter["company_profile"], $this->printer);
        $printer->text("\n");


        $headerArray = [
            "Order Number :" => $parameter["order_number"],
            "Date :" => !empty($parameter['date']) && !empty($parameter['setting']['date_format'])
                ? date($parameter['setting']['date_format'], strtotime($parameter['date'])) : null,
            "Table :" => $parameter["table_name"],
            "Customer :" => $parameter["full_name"],
            "Payment Method :" => $parameter["setting"]["show_payment_method"] == "1" ? $parameter["payment_gateway_name"] : null,
            "Cashier :" => $parameter["setting"]["show_cashier"] == "1" && $parameter["emp_full_name"] ? $parameter["emp_full_name"] : null,
            "Order Type:" => $parameter["order_option"]
        ];


        if (
            ($parameter['setting']['enable_time_on_receipt'] ?? "0") === "1" &&
            !empty($parameter['created_at']) &&
            !empty($parameter['setting']['date_format']) &&
            !empty($parameter['setting']['time_format'])
        ) {
            $headerArray["Date"] = date(
                $parameter['setting']['date_format'] . ' ' . $parameter['setting']['time_format'],
                strtotime($parameter['created_at'])
            );
        }

        $printer->setLineSpacing(38);
        $printer->text("----------------------------------------------\n");
        foreach ($headerArray as $key => $value) {
            if (!empty($value)) {
                $this->printSupport->makeColumns($key, ucfirst($value), $this->printer);
            }
        }
        $printer->text("----------------------------------------------\n");
        /**
         * 
         * Print items
         */

        foreach ($items as $item) {
            $desc = $item["quantity"] . "X " . $item["description"];
            $total = $parameter["currency_symbol"] . number_format($item["total"], 2);
             $this->printSupport->makeColumns($desc, $total, $this->printer,  40);
        }

        /**
         * Print payment summary
         * 
         */
        $printer->text("----------------------------------------------\n");

        $paymentInfoArray = [
            "Subtotal" => $parameter["subtotal"] > 0 ? $parameter["currency_symbol"] . number_format($parameter["subtotal"], 2) : null,
            "Shipping Fee" => $parameter["shipping_fee"] > 0 ?  $parameter["currency_symbol"] . number_format($parameter["shipping_fee"], 2) : null,
            "Discount" => $parameter["discount"] > 0 ? $parameter["currency_symbol"] . number_format($parameter["discount"], 2) : null,
            "Tax Rate" => $parameter["tax_rate"] > 0 ? $parameter["currency_symbol"] . $parameter["tax_rate"] . "%" : null,
            "Tax Amount" => $parameter["tax_amount"] > 0 ? $parameter["currency_symbol"] . number_format($parameter["tax_amount"], 2) : null,
            "Total" => $parameter["total"] > 0 ?  $parameter["currency_symbol"] . number_format($parameter["total"], 2) : null,
            "Total Due" => $parameter["amount_due"] > 0 ?  $parameter["currency_symbol"] . number_format($parameter["amount_due"], 2) : null,
            //"Total Paid" => $parameter["amount_paid"] > 0 ?  $parameter["currency_symbol"] .number_format($parameter["amount_paid"], 2) : null,
        ];

        if($parameter["setting"]["show_change"] == 1)
            {
                $paymentInfoArray= array_merge($paymentInfoArray, [
                    "Change" => $parameter["balance"] > 0 ?  $parameter["currency_symbol"] .number_format($parameter["balance"], 2) : null,
                ]);
            }

        foreach ($paymentInfoArray as $key => $value) {
            if (!empty($value)) {
                $this->printSupport->makeColumns($key, $value, $this->printer, 42);
            }
        }

        /**
         * Footer
         */
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("\n");

        if (!empty($parameter['setting']['footer_note'])) {
            $printer->text($parameter['setting']['footer_note'] . "\n");
        } else {
            $printer->text("Thank you for your purchase!\n");
        }
        $printer->text("Visit again!\n\n");
    }

}
