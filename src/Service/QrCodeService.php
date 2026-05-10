<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Generiert QR-Codes als PNG-Datei oder Data-URL
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
        $qrCode = $this->buildQrCode($content);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        $path = $this->outputDir . $filename . '.png';
        $result->saveToFile($path);

        return '/assets/img/qrcodes/' . $filename . '.png';
    }

    /** QR-Code als Base64 Data-URL für direkte HTML-Einbettung */
    public function generateDataUrl(string $content): string
    {
        $qrCode = $this->buildQrCode($content);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return $result->getDataUri();
    }

    private function buildQrCode(string $content): QrCode
    {
        return QrCode::create($content)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));
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
