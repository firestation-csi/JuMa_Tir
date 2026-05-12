<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Generiert QR-Codes als PNG-Datei oder Data-URL (endroid/qr-code v5/v6 API)
 */
class QrCodeService
{
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = dirname(__DIR__, 2) . '/public/assets/img/qrcodes/';
    }

    /** QR-Code als PNG speichern, Pfad zurückgeben */
    public function generateFile(string $content, string $filename): string
    {
        $result = $this->build($content);
        $path   = $this->outputDir . $filename . '.png';
        $result->saveToFile($path);
        return '/assets/img/qrcodes/' . $filename . '.png';
    }

    /** QR-Code als Base64 Data-URL für direkte HTML-Einbettung */
    public function generateDataUrl(string $content): string
    {
        return $this->build($content)->getDataUri();
    }

    private function build(string $content): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($content)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->build();
    }

    /** Judge-QR-Token-URL generieren */
    public function getJudgeUrl(string $token): string
    {
        return APP_URL . '/judge?token=' . urlencode($token);
    }

    /** Gruppen-QR-Token-URL generieren */
    public function getGroupUrl(string $token): string
    {
        return APP_URL . '/judge/group?token=' . urlencode($token);
    }
}
