<?php
$sourceImage = __DIR__ . '/assets/uploads/1.jpg';
$sizes = [72, 96, 128, 192, 512];
$outputDir = __DIR__ . '/assets/icons';

if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$source = imagecreatefromjpeg($sourceImage);
if (!$source) {
    die("Erreur: impossible de charger l'image source\n");
}

$blue = imagecolorallocate($source, 30, 58, 138);

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    
    $bgBlue = imagecolorallocate($img, 30, 58, 138);
    imagefill($img, 0, 0, $bgBlue);
    
    $sourceResized = imagescale($source, $size, $size, IMG_BILINEAR_FIXED);
    
    $circleSize = min($size * 0.85, $size - 20);
    $srcX = ($size - $circleSize) / 2;
    $srcY = ($size - $circleSize) / 2;
    
    imagecopyresampled($img, $sourceResized, $srcX, $srcY, 0, 0, $circleSize, $circleSize, imagesx($sourceResized), imagesy($sourceResized));
    
    $outputPath = $outputDir . '/icon-' . $size . 'x' . $size . '.png';
    imagepng($img, $outputPath, 90);
    
    echo "Créé: icon-{$size}x{$size}.png\n";
    
    imagedestroy($sourceResized);
    imagedestroy($img);
}

imagedestroy($source);
echo "Terminé!\n";