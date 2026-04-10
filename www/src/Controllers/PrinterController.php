<?php

namespace Limahost\Eletron\Controllers;

use Exception;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Limahost\Eletron\Supports\ReceiptPrinter;
use Limahost\Eletron\Supports\DocketReceipt;


class PrinterController
{

private $docketSupport;
private $receiptSupport;

public function __construct()
{
    $this->receiptSupport = new ReceiptPrinter();
    $this->docketSupport = new DocketReceipt();
}

    public function handlePrinting(mixed $parameters)
    {
        /**
         * Receive the data from eletron
         */
        if (!$parameters) {
            throw new \Exception("There are no printing job sent. Please try again and if problem persist, contact support.");
            return;
        }

        $printers = $this->getPrinters($parameters);
        if (count($printers) > 0) {
            /**
             * Load printer profile
             */
            $profile = CapabilityProfile::load("simple");
          
            foreach ($printers as $printer) {
                //connect the printer
                switch (strtolower($printer->connection_type)) {
                    case "usb":
                        $connector = new WindowsPrintConnector($printer->printer);
                        break;
                    case "ethernet":
                        $port = $printer->printer_port ? $printer->printer_port : '9100';
                        $connector = new NetworkPrintConnector($printer->printer_ip, $port);
                        break;
                    default:
                        break;
                }
                
                $activePrinter = new Printer($connector, $profile);
                  $activePrinter->initialize();
                  $activePrinter->setFont(Printer::FONT_A);

                try {
                
                     if($printer->is_default == "1")
                        {
                            $this->receiptSupport->handle($parameters, $activePrinter, $printer->items);
                        }else{
                             $this->docketSupport->handle($parameters, $activePrinter, $printer->items);
                        }
                } catch (\Throwable $e) {
                    echo "Print failed: " . $e->getMessage();
                } finally {
                    $activePrinter->text("\n\n\n\n");
                    $activePrinter->cut();
                    /**
                     * 
                     * open cash drawer if setting allow
                     */
                    if (($parameters['setting']['auto_open_cash_drawer'] ?? "0") === "1") {
                        $activePrinter->pulse();
                    }
                    $activePrinter->close();
                }
            }
        }
    }


    /**
     * @ mixed $parameters
     * return type of array
     * 
     */
    private function getPrinters(mixed $parameters): ?array
    {
        /**
         * check if there is any printer
         */
        $availablePrinterArray = $parameters["printers"];
        if (!$parameters) {
            throw new \Exception("There are no printers associated with your account. Please add a new printer or contact support.");
        }


        /**
         * check printer and associated printing Items or categories, means sort the printer
         */

        $useablePrinter = [];
        $items = $parameters["items"];

        if (empty($items)) {
            throw new \Exception("No printable items found!.");
        }

        foreach ($availablePrinterArray as $key => $availablePrinter) {
            $printableCategory = json_decode($availablePrinter["categories"], true);
            if (empty($printableCategory)) continue;

            /**
             * 
             * proceed if printer category is not empty, meaning the printer can still be used
             */
            $toUsePrinter = (object)$availablePrinter;
            $toUsePrinter->items = [];


            foreach ($items as $i => $item) {
                /**
                 * Check if the printer accept the items, 
                 * then print out if it accept
                 * else if printer is default printer maybe for sales.
                 * print out all items with the printer
                 */
                if (!empty($item["category_id"]) && in_array($item["category_id"], $printableCategory)) {
                    $toUsePrinter->items[] = $item;
                } elseif ($availablePrinter["is_default"] == "1") {
                    $toUsePrinter->items[] = $item;
                }
            }

            /**
             *Now add items to printer if items are not empty
             *
             */
            if (!empty($toUsePrinter->items)) {
                $useablePrinter[] = $toUsePrinter;
            }
        }
        return $useablePrinter;
    }
}
