<?php
namespace Limahost\Eletron\Supports;



class PrintSupport
{


    
    public function makeColumns($left, $right, $printer, $width = 32)
    {
        $rightWidth = strlen($right);
        $firstLineLeftWidth = $width - $rightWidth;
        $otherLineWidth = $width;

        $lines = [];
        $words = preg_split('/\s+/', trim($left));
        $currentLine = '';
        $isFirstLine = true;

        foreach ($words as $word) {
            $availableWidth = $isFirstLine ? $firstLineLeftWidth : $otherLineWidth;

            // If single word is longer than available width, split it
            while (strlen($word) > $availableWidth) {
                if ($currentLine !== '') {
                    $lines[] = [
                        'left' => $currentLine,
                        'right' => $isFirstLine ? $right : ''
                    ];
                    $currentLine = '';
                    $isFirstLine = false;
                    $availableWidth = $otherLineWidth;
                }

                $chunk = substr($word, 0, $availableWidth);
                $word = substr($word, $availableWidth);

                $lines[] = [
                    'left' => $chunk,
                    'right' => $isFirstLine ? $right : ''
                ];

                $isFirstLine = false;
                $availableWidth = $otherLineWidth;
            }

            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (strlen($testLine) <= $availableWidth) {
                $currentLine = $testLine;
            } else {
                $lines[] = [
                    'left' => $currentLine,
                    'right' => $isFirstLine ? $right : ''
                ];
                $currentLine = $word;
                $isFirstLine = false;
            }
        }

        if ($currentLine !== '') {
            $lines[] = [
                'left' => $currentLine,
                'right' => $isFirstLine ? $right : ''
            ];
        }

        foreach ($lines as $line) {
            $lineWidth = $line['right'] !== '' ? $firstLineLeftWidth : $otherLineWidth;
            $printer->text(
                str_pad($line['left'], $lineWidth) . $line['right'] . "\n"
            );
        }
    }


    public function printWrappedText($text, $printer, $width = 32)
    {
        $lines = wordwrap($text, $width, "\n", true);
        $printer->text($lines . "\n");
    }
}