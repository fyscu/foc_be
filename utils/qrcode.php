<?php
require_once 'phpqrcode/phpqrcode.php';

function generateQrCodeBase64($content) {

    $tempFile = tempnam(sys_get_temp_dir(), 'qrcode');

    \QRcode::png($content, $tempFile, QR_ECLEVEL_L, 10);

    $image = imagecreatefrompng($tempFile);

    $width = imagesx($image);
    $height = imagesy($image);

    $transparentImage = imagecreatetruecolor($width, $height);

    $transparentColor = imagecolorallocatealpha($transparentImage, 0, 0, 0, 127);
    imagefill($transparentImage, 0, 0, $transparentColor);
    imagesavealpha($transparentImage, true);

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $colors = imagecolorsforindex($image, $rgb);

            if ($colors['red'] < 128 && $colors['green'] < 128 && $colors['blue'] < 128) {
                imagesetpixel($transparentImage, $x, $y, imagecolorallocate($transparentImage, 0, 0, 0));
            }
        }
    }

    ob_start();

    imagepng($transparentImage);

    $imageData = ob_get_contents();

    ob_end_clean();

    imagedestroy($image);
    imagedestroy($transparentImage);

    unlink($tempFile);

    return 'data:image/png;base64,' . base64_encode($imageData);
}