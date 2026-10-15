<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

use DirectoryTree\Screenshot\BrowserOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\Support\ErrorHandler;
use RuntimeException;

class ImageCapture
{
    /**
     * Prepare a remote image capture with the existing viewport and limits.
     */
    public function __construct(
        protected BrowserOptions $browser,
        protected ImageOptions $options,
        protected ?string $element = null,
    ) {}

    /**
     * Get the screenshot form fields without changing the layout viewport.
     */
    public function fields(): array
    {
        $fields = [
            'width' => $this->browser->getWidth(),
            'height' => $this->browser->getHeight(),
            'clip' => ! $this->options->getFullPage(),
            'format' => $this->options->getFormat()->value,
        ];

        if ($this->element !== null) {
            $fields['selector'] = $this->element;
        }

        if ($this->options->getFormat() === OutputFormat::Jpeg) {
            $fields['quality'] = $this->options->getQuality();
        }

        return $fields;
    }

    /**
     * Validate the image and crop oversized results without scaling.
     */
    public function result(string $contents): ImageResult
    {
        $result = new ImageResult(base64_encode($contents));

        if ($result->mimeType() !== 'image/'.$this->options->getFormat()->value) {
            throw new RuntimeException('Gotenberg returned an unexpected image format.');
        }

        $width = min($result->width(), $this->options->getMaxWidth());
        $height = min($result->height(), $this->options->getMaxHeight());

        if ($width === $result->width() && $height === $result->height()) {
            return $result;
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to crop Gotenberg images to their maximum dimensions.');
        }

        $image = ErrorHandler::suppress(fn () => imagecreatefromstring($contents));

        if ($image === false) {
            throw new RuntimeException('Unable to decode the Gotenberg image for cropping.');
        }

        $cropped = imagecrop($image, [
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
        ]);

        if ($cropped === false) {
            throw new RuntimeException('Unable to crop the Gotenberg image.');
        }

        imagesavealpha($cropped, true);

        ob_start();

        try {
            $written = $this->options->getFormat() === OutputFormat::Jpeg
                ? imagejpeg($cropped, null, $this->options->getQuality())
                : imagepng($cropped);

            if (! $written) {
                throw new RuntimeException('Unable to encode the cropped Gotenberg image.');
            }

            return new ImageResult(
                base64_encode(ob_get_contents())
            );
        } finally {
            ob_end_clean();
        }
    }
}
