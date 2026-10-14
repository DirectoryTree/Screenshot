<?php

namespace DirectoryTree\Screenshot\Support;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class ChromeArchive
{
    /**
     * Extract an official archive, preserving executable bits and safe symlinks.
     */
    public function extract(string $path, string $directory): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('The Chrome download is not a valid ZIP archive.');
        }

        $files = new Filesystem;
        $links = [];

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name) || ! $this->safePath($name)) {
                    throw new RuntimeException('The Chrome archive contains an unsafe path.');
                }

                $archive->getExternalAttributesIndex($index, $system, $attributes);
                $mode = $system === ZipArchive::OPSYS_UNIX ? ($attributes >> 16) & 0xFFFF : 0;
                $destination = $directory.'/'.$name;

                if (($mode & 0170000) === 0120000) {
                    $target = $archive->getFromIndex($index);

                    if (! is_string($target) || ! $this->safePath($target)) {
                        throw new RuntimeException('The Chrome archive contains an unsafe symbolic link.');
                    }

                    $links[$destination] = $target;

                    continue;
                }

                if (str_ends_with($name, '/')) {
                    $files->mkdir($destination);

                    continue;
                }

                $stream = $archive->getStream($name);

                if ($stream === false) {
                    throw new RuntimeException('Unable to read a file from the Chrome archive.');
                }

                try {
                    $files->dumpFile($destination, $stream);
                } finally {
                    fclose($stream);
                }

                if (PHP_OS_FAMILY !== 'Windows') {
                    $files->chmod($destination, ($mode & 0111) ? 0755 : 0644);
                }
            }

            // Create links last so archive entries cannot write through them.
            foreach ($links as $link => $target) {
                if (file_exists($link) || is_link($link)) {
                    throw new RuntimeException('The Chrome archive contains a conflicting symbolic link.');
                }

                $files->mkdir(dirname($link));
                $files->symlink($target, $link);
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * Reject absolute paths, parent traversal, and platform-specific separators.
     */
    protected function safePath(string $path): bool
    {
        return $path !== '' && ! str_starts_with($path, '/')
            && ! preg_match('/[\\\\:\x00]/', $path)
            && ! in_array('..', explode('/', $path), true);
    }
}
