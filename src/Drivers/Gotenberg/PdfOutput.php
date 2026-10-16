<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use InvalidArgumentException;

class PdfOutput implements Output
{
    /**
     * Configure PDF output with the selected paper and layout.
     *
     * @param  CaptureOptions<PdfOptions>  $options
     */
    public function __construct(
        protected CaptureOptions $options,
    ) {
        if ($options->element() !== null) {
            throw new InvalidArgumentException('Gotenberg does not support element selection for PDF captures.');
        }
    }

    /**
     * Get the Chromium conversion route.
     */
    public function route(): string
    {
        return 'convert';
    }

    /**
     * Translate the PDF layout into Gotenberg's form fields.
     */
    public function fields(): array
    {
        $options = $this->options->output();

        $fields = [
            'paperWidth' => $options->getPaperWidth().'mm',
            'paperHeight' => $options->getPaperHeight().'mm',
            'landscape' => $options->getLandscape(),
            'printBackground' => $options->getBackground(),
            'preferCssPageSize' => $options->getPreferCssPageSize(),
        ];

        foreach ($options->getMargins() as $side => $margin) {
            $fields['margin'.ucfirst($side)] = $margin.'mm';
        }

        return $fields;
    }

    /**
     * Validate the PDF and create a capture result.
     */
    public function result(string $contents): PdfResult
    {
        return new PdfResult(base64_encode($contents));
    }
}
