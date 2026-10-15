<?php

namespace DirectoryTree\Screenshot\Testing;

use DirectoryTree\Screenshot\Contracts\Factory;
use DirectoryTree\Screenshot\ImageResult;
use DirectoryTree\Screenshot\PdfResult;
use Illuminate\Support\Testing\Fakes\Fake;
use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @mixin ScreenshotFake
 */
class ScreenshotManagerFake implements Factory, Fake
{
    use ForwardsCalls;

    /**
     * Create a manager that records captures without resolving real drivers.
     */
    public function __construct(
        protected ScreenshotFake $fake,
    ) {}

    /**
     * Resolve every driver to the same configured recording fake.
     */
    public function driver($driver = null): ScreenshotFake
    {
        return $this->fake;
    }

    /**
     * Create a fresh recording manager using the same capture defaults.
     */
    public function fake(ImageResult|PdfResult|null $result = null): static
    {
        return new static(ScreenshotFake::from($this->fake, $result));
    }

    /**
     * Forward capture methods and assertions to the shared recorder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->fake, $method, $parameters);
    }
}
