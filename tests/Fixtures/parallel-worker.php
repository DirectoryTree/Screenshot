<?php

use DirectoryTree\Screenshot\CaptureOptions;
use DirectoryTree\Screenshot\Drivers\ChromeDriver;
use DirectoryTree\Screenshot\Screenshot;
use DirectoryTree\Screenshot\Support\BrowserSocket;
use HeadlessChromium\Browser;

require __DIR__.'/../../vendor/autoload.php';

[$script, $directory, $id, $width] = $argv;

$driver = new class($directory, $id) extends ChromeDriver
{
    public function __construct(protected string $directory, protected string $id)
    {
        parent::__construct(getenv('CHROME_PATH') ?: null, ['noSandbox' => getenv('SCREENSHOT_NO_SANDBOX') === '1'], $directory);
    }

    protected function withPage(Browser $browser, string $source, string $type, CaptureOptions $options, bool $shared, callable $render): mixed
    {
        file_put_contents($this->directory.'/'.$this->id.'.ready', 'ready');
        $deadline = microtime(true) + 15;

        while (! is_file($this->directory.'/release')) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out waiting for both parallel workers.');
            }

            usleep(10000);
        }

        return parent::withPage($browser, $source, $type, $options, $shared, $render);
    }
};

$pending = (new Screenshot($driver))
    ->html('<style>html,body{margin:0}div{height:100px}</style><div id="content"></div><script>document.getElementById("content").style.height="600px"</script>')
    ->socket('parallel')
    ->size((int) $width, 240)
    ->javascript($id === 'second');

$isPdf = ($argv[4] ?? 'png') === 'pdf';
$image = ($isPdf ? $pending->pdf() : $pending)->capture();

file_put_contents($directory.'/'.$id.'.json', json_encode([
    'width' => $isPdf ? null : $image->width(),
    'height' => $isPdf ? null : $image->height(),
    'mime' => $image->mimeType(),
    'socket' => file_get_contents(BrowserSocket::named('parallel', $directory)->path()),
]));
