<?php

namespace Limahost\Eletron\Supports;

use Limahost\Eletron\Supports\PrintSupport;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

class DocketReceipt
{

    private $printSupport;

    private $printer;

    public function __construct()
    {
        $this->printSupport = new PrintSupport();
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
        if ($parameter["table_name"]) {
            $this->printer->setTextSize(3, 2);
            $this->printSupport->printWrappedText(ucfirst($parameter["table_name"]), $this->printer);
            $printer->text("\n\n");
        }
        // $printer->setEmphasis(true);  //enable bold
        $this->printer->setTextSize(1, 1);
        $this->printSupport->printWrappedText("DOCKET - #" . $parameter["order_number"], $this->printer);

        $printer->setEmphasis(false);  //disable bold
        $printer->text("\n");

        $headerArray = [
            //"Order Number :" => $parameter["order_number"],
            "Date :" => !empty($parameter['date']) && !empty($parameter['setting']['date_format'])
                ? date($parameter['setting']['date_format'], strtotime($parameter['date'])) : null,
            // "Table :" => $parameter["table_name"],
            "Customer :" => $parameter["full_name"],
            // "Payment Method :" => $parameter["setting"]["show_payment_method"] == "1" ? $parameter["payment_gateway_name"] : null,
            //  "Cashier :" => $parameter["setting"]["show_cashier"] == "1" && $parameter["emp_full_name"] ? $parameter["emp_full_name"] : null,
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
            $this->printSupport->printWrappedText($item["quantity"] . "X " . $item["description"], $this->printer);
        }

        /**
         * Print payment summary
         * 
         */
        $printer->text("----------------------------------------------\n\n\n");
    }
}
