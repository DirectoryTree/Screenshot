<?php

namespace DirectoryTree\Screenshot\Drivers;

use DirectoryTree\Screenshot\CaptureContext;
use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Contracts\Driver;
use DirectoryTree\Screenshot\Drivers\Gotenberg\ImageOutput;
use DirectoryTree\Screenshot\Drivers\Gotenberg\Output;
use DirectoryTree\Screenshot\Drivers\Gotenberg\PdfOutput;
use DirectoryTree\Screenshot\Drivers\Gotenberg\Request;
use DirectoryTree\Screenshot\Enums\OutputFormat;
use DirectoryTree\Screenshot\Enums\SourceType;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class GotenbergDriver implements Driver
{
    /**
     * Connect to Gotenberg with the service's JavaScript setting.
     */
    public function __construct(
        protected string $url = 'http://localhost:3000',
        protected ClientInterface $http = new Client,
        protected bool $javascript = true,
    ) {
        $this->validateUrl($url);
    }

    /**
     * Capture HTML or a URL through Gotenberg's Chromium routes.
     */
    public function capture(string $source, SourceType $type, CaptureOptions $options): ImageResult|PdfResult
    {
        $this->validateOptions($options);

        $output = $this->newOutput($options);

        $contents = $this->capturePage($source, $type, $options, $output);

        return $output->result($contents);
    }

    /**
     * Create an output handler for the selected format.
     */
    protected function newOutput(CaptureOptions $options): Output
    {
        return match ($options->format()) {
            OutputFormat::Png, OutputFormat::Jpeg => new ImageOutput($options),
            OutputFormat::Pdf => new PdfOutput($options),
        };
    }

    /**
     * Send a capture request and return its output bytes.
     */
    protected function capturePage(string $source, SourceType $type, CaptureOptions $options, Output $output): string
    {
        $route = $output->route();

        $request = new Request(rtrim($this->url, '/')."/forms/chromium/{$route}/{$type->value}", [
            'multipart' => $this->getMultipart($source, $type, $output->fields()),
            'timeout' => $options->browser()->getBrowserTimeout() / 1000,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);

        $options->prepare(new CaptureContext($this, $request));

        $response = $this->http->request('POST', $request->url, $request->options);

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
     * Require an HTTP or HTTPS base URL without a query or fragment.
     */
    protected function validateUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || ! Str::isUrl($url, ['http', 'https']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The Gotenberg URL must be an HTTP or HTTPS base URL without a query or fragment.');
        }
    }

    /**
     * Validate capture settings and their compatibility with the remote browser.
     */
    protected function validateOptions(CaptureOptions $options): void
    {
        $options->validate();

        if ($options->browser()->getSocket() !== null) {
            throw new InvalidArgumentException('Gotenberg manages its own browsers and does not support shared socket keys.');
        }

        if ($options->browser()->getJavascript() !== $this->javascript) {
            throw new InvalidArgumentException('The capture JavaScript setting must match the Gotenberg service. Configure screenshot.gotenberg.javascript and use javascript() or screenshot.capture.javascript accordingly.');
        }
    }
}
