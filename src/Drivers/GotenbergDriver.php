<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\Gotenberg\ImageCapture;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageOptions;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfOptions;
use DirectoryTree\Screenshot\PdfResult;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use RuntimeException;

class GotenbergDriver implements Driver
{
    /**
     * The HTTP client used to communicate with Gotenberg.
     */
    protected ClientInterface $http;

    /**
     * Connect to Gotenberg with the service's JavaScript setting.
     */
    public function __construct(
        protected string $url = 'http://localhost:3000',
        protected array $httpOptions = [],
        protected bool $javascript = true,
        ?ClientInterface $http = null,
    ) {
        $parts = parse_url($this->url);

        if (! $parts || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The Gotenberg URL must be an HTTP or HTTPS base URL without a query or fragment.');
        }

        $this->http = $http ?? new Client($this->httpOptions);
    }

    /**
     * Capture HTML or a URL through Gotenberg's Chromium routes.
     */
    public function capture(string $source, SourceType $type, CaptureOptions $options): ImageResult|PdfResult
    {
        $options->validate();
        $this->validateOptions($options);

        $output = $options->output();
        $image = $output instanceof ImageOptions
            ? new ImageCapture(
                $options->browser(),
                $output,
                $options->element(),
            )
            : null;
        $fields = $image ? $image->fields() : $this->getPdfFields($output);
        $contents = $this->capturePage($source, $type, $options, $fields);

        if ($image) {
            return $image->result($contents);
        }

        return new PdfResult(base64_encode($contents));
    }

    /**
     * Send a capture request and return its output bytes.
     */
    protected function capturePage(string $source, SourceType $type, CaptureOptions $options, array $fields): string
    {
        $route = $options->output() instanceof ImageOptions ? 'screenshot' : 'convert';

        $response = $this->http->request('POST', rtrim($this->url, '/')."/forms/chromium/{$route}/{$type->value}", [
            'multipart' => $this->getMultipart($source, $type, $fields),
            'timeout' => $options->browser()->getBrowserTimeout() / 1000,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf(
                'Gotenberg capture failed (HTTP %d, trace: %s): %s',
                $response->getStatusCode(),
                $response->getHeaderLine('Gotenberg-Trace'),
                (string) $response->getBody(),
            ));
        }

        return (string) $response->getBody();
    }

    /**
     * Prepare the source and capture fields for a multipart request.
     */
    protected function getMultipart(string $source, SourceType $type, array $fields): array
    {
        $multipart = [];

        if ($type === SourceType::Html) {
            $multipart[] = [
                'name' => 'files',
                'contents' => $source,
                'filename' => 'index.html',
                'headers' => ['Content-Type' => 'text/html'],
            ];
        } else {
            $multipart[] = ['name' => 'url', 'contents' => $source];
        }

        foreach ($fields as $name => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $multipart[] = [
                'name' => $name,
                'contents' => (string) $value,
            ];
        }

        return $multipart;
    }

    /**
     * Reject settings that cannot be applied to the remote browser.
     */
    protected function validateOptions(CaptureOptions $options): void
    {
        if ($options->browser()->getSocket() !== null) {
            throw new InvalidArgumentException('Gotenberg manages its own browsers and does not support shared socket keys.');
        }

        if ($options->browser()->getJavascript() !== $this->javascript) {
            throw new InvalidArgumentException('The capture JavaScript setting must match the Gotenberg service. Configure screenshot.gotenberg.javascript and use javascript() or screenshot.capture.javascript accordingly.');
        }

        if ($options->output() instanceof PdfOptions && $options->element() !== null) {
            throw new InvalidArgumentException('Gotenberg does not support element selection for PDF captures.');
        }
    }

    /**
     * Translate the existing PDF layout into Gotenberg's form fields.
     */
    protected function getPdfFields(PdfOptions $options): array
    {
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
}
