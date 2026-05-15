<?php

declare(strict_types=1);

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Generiert QR-Codes als PNG-Datei oder Data-URL.
 * Kompatibel mit endroid/qr-code ^6.x (QrCode ist final readonly, nur Konstruktor).
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
        $result = $this->write($content);
        $path   = $this->outputDir . $filename . '.png';
        $result->saveToFile($path);
        return '/assets/img/qrcodes/' . $filename . '.png';
    }

    /** QR-Code als Base64 Data-URL für direkte HTML-Einbettung */
    public function generateDataUrl(string $content): string
    {
        return $this->write($content)->getDataUri();
    }

    private function write(string $content): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        $qrCode = new QrCode(
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new PngWriter())->write($qrCode);
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
