<?php

/**
 * Builds the favicon, app icons, square logo and social share image from the brand logo.
 *
 * Search engines need a square icon (Google: a multiple of 48px) and a square logo of at
 * least 112px for the Organization result. The source logo is 688x635 on a solid
 * background, so it is centred on a square of that same sampled colour. The small icons use
 * only the monogram, found automatically above the wordmark, because "SHAKY LTD" is
 * unreadable at 16-48px.
 *
 * Usage: php scripts/make-icons.php [source.png]   (writes into public/)
 */
$root = dirname(__DIR__);
$source = $argv[1] ?? $root.'/public/images/logo.png';
$public = $root.'/public';

$logo = imagecreatefromstring((string) file_get_contents($source)) ?: exit("Cannot read $source\n");
$w = imagesx($logo);
$h = imagesy($logo);
$bg = imagecolorsforindex($logo, imagecolorat($logo, 2, 2));

// Bounding box of everything that differs from the background, above a cut-off row.
$box = function (int $maxY) use ($logo, $w, $bg): array {
    [$x0, $y0, $x1, $y1] = [PHP_INT_MAX, PHP_INT_MAX, 0, 0];
    // Skip a 2px border: the source has a stray 1px grey line down its left edge.
    for ($y = 2; $y < $maxY; $y++) {
        for ($x = 2; $x < $w - 2; $x++) {
            $c = imagecolorsforindex($logo, imagecolorat($logo, $x, $y));
            if (abs($c['red'] - $bg['red']) + abs($c['green'] - $bg['green']) + abs($c['blue'] - $bg['blue']) > 60) {
                [$x0, $y0, $x1, $y1] = [min($x0, $x), min($y0, $y), max($x1, $x), max($y1, $y)];
            }
        }
    }

    return [$x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1];
};

// The wordmark starts where a full-width gap of background ends below the monogram.
$monogram = $box((int) round($h * 0.72));
$whole = $box($h - 2);

/** Draw a region centred on a square canvas of the background colour, with a margin. */
$square = function (int $size, array $region, float $margin) use ($logo, $bg) {
    [$sx, $sy, $sw, $sh] = $region;
    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, $bg['red'], $bg['green'], $bg['blue']));
    $scale = ($size * (1 - 2 * $margin)) / max($sw, $sh);
    [$dw, $dh] = [(int) round($sw * $scale), (int) round($sh * $scale)];
    imagecopyresampled($canvas, $logo, intdiv($size - $dw, 2), intdiv($size - $dh, 2), $sx, $sy, $dw, $dh, $sw, $sh);

    return $canvas;
};

$png = function ($image): string {
    ob_start();
    imagepng($image, null, 9);

    return (string) ob_get_clean();
};

$written = [];
foreach (['favicon-32x32.png' => 32, 'favicon-48x48.png' => 48, 'favicon-96x96.png' => 96, 'apple-touch-icon.png' => 180, 'icon-192.png' => 192, 'icon-512.png' => 512] as $file => $size) {
    file_put_contents("$public/$file", $png($square($size, $monogram, 0.12)));
    $written[] = $file;
}

// The Organization logo keeps the full wordmark: it is shown large enough to read.
file_put_contents("$public/images/logo-square.png", $png($square(512, $whole, 0.08)));
$written[] = 'images/logo-square.png';

// Social share image, 1200x630, full logo centred.
$og = imagecreatetruecolor(1200, 630);
imagefill($og, 0, 0, imagecolorallocate($og, $bg['red'], $bg['green'], $bg['blue']));
[$sx, $sy, $sw, $sh] = $whole;
$scale = 520 / $sh;
imagecopyresampled($og, $logo, (int) round((1200 - $sw * $scale) / 2), 55, $sx, $sy, (int) round($sw * $scale), 520, $sw, $sh);
file_put_contents("$public/images/og-default.png", $png($og));
$written[] = 'images/og-default.png';

// A real multi-size .ico (PNG-compressed entries, supported by every current browser).
$entries = array_map(fn ($size) => [$size, $png($square($size, $monogram, 0.08))], [16, 32, 48]);
$ico = pack('vvv', 0, 1, count($entries));
$offset = 6 + 16 * count($entries);
foreach ($entries as [$size, $data]) {
    $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($data), $offset);
    $offset += strlen($data);
}
foreach ($entries as [, $data]) {
    $ico .= $data;
}
file_put_contents("$public/favicon.ico", $ico);
$written[] = 'favicon.ico';

echo 'Wrote '.implode(', ', $written).PHP_EOL;
printf("Monogram box %s, whole logo box %s, background #%02x%02x%02x\n", json_encode($monogram), json_encode($whole), $bg['red'], $bg['green'], $bg['blue']);
