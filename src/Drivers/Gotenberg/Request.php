<?php

namespace DirectoryTree\Screenshot\Drivers\Gotenberg;

class Request
{
    /**
     * Create a mutable Guzzle request configuration.
     */
    public function __construct(
        public string $url,
        public array $options,
    ) {}

    /**
     * Set a multipart field while preserving other request fields and files.
     */
    public function set(string $name, string|int|float|bool $value): static
    {
        $this->options['multipart'] = array_values(array_filter(
            $this->options['multipart'],
            fn (array $part) => $part['name'] !== $name,
        ));

        $this->options['multipart'][] = [
            'name' => $name,
            'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
        ];

        return $this;
    }
}
