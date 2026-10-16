<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\Support\ErrorHandler;
use GdImage;
use RuntimeException;

class ImageOutput implements Output
{
    /**
     * Configure image output with the existing viewport and limits.
     *
     * @param  CaptureOptions<ImageOptions>  $options
     */
    public function __construct(
        protected CaptureOptions $options,
    ) {}

    /**
     * Get the Chromium screenshot route.
     */
    public function route(): string
    {
        return 'screenshot';
    }

    /**
     * Get the screenshot form fields without changing the layout viewport.
     */
    public function fields(): array
    {
        $browser = $this->options->browser();
        $options = $this->options->output();

        $fields = [
            'width' => $browser->getWidth(),
            'height' => $browser->getHeight(),
            'clip' => ! $options->getFullPage(),
            'format' => $options->getFormat()->value,
        ];

        if ($this->options->element() !== null) {
            $fields['selector'] = $this->options->element();
        }

        if ($options->getFormat() === OutputFormat::Jpeg) {
            $fields['quality'] = $options->getQuality();
        }

        return $fields;
    }

    /**
     * Validate the image and crop oversized results without scaling.
     */
    public function result(string $contents): ImageResult
    {
        $options = $this->options->output();

        $result = new ImageResult(base64_encode($contents));

        if ($result->mimeType() !== 'image/'.$options->getFormat()->value) {
            throw new RuntimeException('Gotenberg returned an unexpected image format.');
        }

        return $this->crop($result);
    }

    /**
     * Crop oversized images to their maximum dimensions without scaling.
     */
    protected function crop(ImageResult $result): ImageResult
    {
        $options = $this->options->output();

        $width = min($result->width(), $options->getMaxWidth());
        $height = min($result->height(), $options->getMaxHeight());

        if ($width === $result->width() && $height === $result->height()) {
            return $result;
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to crop Gotenberg images to their maximum dimensions.');
        }

        $image = ErrorHandler::suppress(fn () => imagecreatefromstring($result->contents()));

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

        return new ImageResult(base64_encode($this->encode($cropped)));
    }

    /**
     * Encode a cropped image using the configured output format.
     */
    protected function encode(GdImage $image): string
    {
        $options = $this->options->output();

        ob_start();

        try {
            $written = $options->getFormat() === OutputFormat::Jpeg
                ? imagejpeg($image, null, $options->getQuality())
                : imagepng($image);

            if (! $written) {
                throw new RuntimeException('Unable to encode the cropped Gotenberg image.');
            }

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
