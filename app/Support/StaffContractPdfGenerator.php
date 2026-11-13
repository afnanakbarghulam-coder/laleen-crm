<?php

namespace App\Support;

use App\Models\StaffContract;

class StaffContractPdfGenerator
{
    /**
     * Render an executive Laleen-branded PDF 1.4 contract with embedded Laleen logo,
     * clean 3-stat compensation summary, structured 01..0N clauses, and digital sign-off.
     */
    public function render(StaffContract $contract): string
    {
        $contract->loadMissing('staff');

        if (class_exists(\Dompdf\Dompdf::class)) {
            $html = view('contracts.pdf', ['contract' => $contract])->render();
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        }

        $staffName = (string) ($contract->staff?->name ?? 'Team Member');
        $currency = strtoupper((string) ($contract->currency ?: 'QAR'));
        $compensation = number_format($contract->monthlyCompensation(), 2);

        $startLabel = $contract->start_date ? $contract->start_date->format('d M Y') : 'Immediate';
        $expiryLabel = $contract->expiry_date ? $contract->expiry_date->format('d M Y') : 'Open-Ended';
        $signedAtLabel = $contract->signed_at
            ? $contract->signed_at->copy()->timezone('Asia/Qatar')->format('d M Y, h:i A') . ' (Doha)'
            : 'Pending Digital Signature';

        $logoImage = $this->prepareLogoJpeg();
        $hasLogo = $logoImage !== null;

        $sigImage = $this->prepareDrawnSignatureJpeg($contract);
        $hasSigImage = $sigImage !== null;

        $pages = [];
        $currentStream = $this->buildFirstPageHeader(
            $contract,
            $staffName,
            $startLabel,
            $expiryLabel,
            $currency,
            $compensation,
            $hasLogo
        );
        $cursorY = 582.0;

        $parsedClauses = $contract->parsedClauses();
        foreach ($parsedClauses as $clause) {
            if ($cursorY < 145.0) {
                $pages[] = $currentStream;
                $currentStream = $this->buildContinuationHeader($contract, count($pages) + 1, $hasLogo);
                $cursorY = 742.0;
            }

            // Subtle hairline rule above each clause
            $currentStream .= "0.90 0.82 0.79 RG 0.5 w\n";
            $currentStream .= sprintf("50 %.2f m 545 %.2f l S\n", $cursorY + 8.0, $cursorY + 8.0);

            // Rose-gold 2-digit index + bold clause title
            $currentStream .= $this->pdfText(50, $cursorY - 6.0, 'F2', 10.0, [0.78, 0.46, 0.40], $clause['index']);
            $headingLines = $this->wrapLine($clause['title'], 78);
            foreach ($headingLines as $hIdx => $hLine) {
                $currentStream .= $this->pdfText(74, $cursorY - 6.0 - ($hIdx * 13.0), 'F2', 10.0, [0.22, 0.13, 0.14], $hLine);
            }
            $cursorY -= (6.0 + (count($headingLines) * 13.5) + 3.0);

            $bodyParagraphs = preg_split("/\r\n|\n|\r/", $clause['body']) ?: [];
            foreach ($bodyParagraphs as $para) {
                $cleanPara = trim($para);
                if ($cleanPara === '') {
                    $cursorY -= 5.0;
                    continue;
                }
                foreach ($this->wrapLine($cleanPara, 86) as $bLine) {
                    if ($cursorY < 130.0) {
                        $pages[] = $currentStream;
                        $currentStream = $this->buildContinuationHeader($contract, count($pages) + 1, $hasLogo);
                        $cursorY = 742.0;
                    }
                    $currentStream .= $this->pdfText(74, $cursorY, 'F1', 9.5, [0.20, 0.15, 0.15], $bLine);
                    $cursorY -= 13.0;
                }
            }

            $cursorY -= 8.0;
        }

        if ($cursorY < 175.0) {
            $pages[] = $currentStream;
            $currentStream = $this->buildContinuationHeader($contract, count($pages) + 1, $hasLogo);
            $cursorY = 735.0;
        }

        $currentStream .= $this->buildSignatureBlock($contract, $staffName, $signedAtLabel, $cursorY - 8.0, $hasSigImage);
        $pages[] = $currentStream;

        return $this->compilePdfDocument($pages, $logoImage, $sigImage);
    }

    private function buildFirstPageHeader(
        StaffContract $contract,
        string $staffName,
        string $startLabel,
        string $expiryLabel,
        string $currency,
        string $compensation,
        bool $hasLogo
    ): string {
        $s = '';

        // Top Dark Espresso Luxury Banner
        $s .= "0.12 0.08 0.10 rg\n";
        $s .= "0 744 595 98 re f\n";
        // Rose-gold hairline accent rule
        $s .= "0.85 0.56 0.51 rg\n";
        $s .= "0 741 595 3 re f\n";

        $textStartX = 50.0;
        if ($hasLogo) {
            // Crisp white logo medallion + embedded Laleen logo XObject
            $s .= "1 1 1 rg\n";
            $s .= "50 759 66 66 re f\n";
            $s .= "q 62 0 0 62 52 761 cm /ImLogo Do Q\n";
            $textStartX = 128.0;
        }

        $s .= $this->pdfText($textStartX, 811, 'F2', 9.0, [0.90, 0.72, 0.66], 'LALEEN BEAUTY SALON  |  OFFICIAL AGREEMENT');
        $s .= $this->pdfText($textStartX, 791, 'F2', 16.0, [0.97, 0.94, 0.90], $staffName);
        $s .= $this->pdfText(
            $textStartX,
            772,
            'F1',
            9.5,
            [0.86, 0.78, 0.75],
            $contract->contract_number . '   |   ' . $contract->designationLabel() . '   |   ' . $contract->branchLabel()
        );

        $s .= $this->pdfText(445, 811, 'F2', 9.5, [0.97, 0.94, 0.90], 'Status: ' . $contract->statusLabel());
        $s .= $this->pdfText(445, 793, 'F1', 8.5, [0.90, 0.72, 0.66], 'Ref: ' . $contract->contract_number);

        // Clean 3-Stat Executive Strip (Monthly Compensation | Branch / Location | Effective Term)
        $s .= "0.99 0.97 0.96 rg\n";
        $s .= "0.88 0.76 0.72 RG 0.8 w\n";
        $s .= "50 658 495 64 re B\n";

        // Vertical column dividers inside 3-stat strip
        $s .= "0.90 0.82 0.79 RG 0.6 w\n";
        $s .= "215 668 m 215 712 l S\n";
        $s .= "375 668 m 375 712 l S\n";

        // Stat 1: Monthly Compensation
        $s .= $this->pdfText(64, 701, 'F2', 8.5, [0.55, 0.38, 0.35], 'MONTHLY COMPENSATION');
        $s .= $this->pdfText(64, 680, 'F2', 13.0, [0.13, 0.09, 0.11], "{$currency} {$compensation}");

        // Stat 2: Branch / Location
        $s .= $this->pdfText(229, 701, 'F2', 8.5, [0.55, 0.38, 0.35], 'BRANCH / LOCATION');
        $s .= $this->pdfText(229, 680, 'F2', 12.0, [0.13, 0.09, 0.11], $contract->branchLabel());

        // Stat 3: Effective Term
        $s .= $this->pdfText(389, 701, 'F2', 8.5, [0.55, 0.38, 0.35], 'EFFECTIVE TERM');
        $s .= $this->pdfText(389, 681, 'F2', 9.5, [0.13, 0.09, 0.11], "{$startLabel} -> {$expiryLabel}");

        // Section Heading
        $s .= $this->pdfText(50, 624, 'F2', 10.5, [0.42, 0.22, 0.20], 'TERMS & OPERATIONAL CLAUSES');

        return $s;
    }

    private function buildContinuationHeader(StaffContract $contract, int $pageNum, bool $hasLogo): string
    {
        $s = "0.12 0.08 0.10 rg\n";
        $s .= "0 786 595 56 re f\n";
        $s .= "0.85 0.56 0.51 rg\n";
        $s .= "0 784 595 2 re f\n";

        $x = 50.0;
        if ($hasLogo) {
            $s .= "1 1 1 rg\n";
            $s .= "50 795 36 36 re f\n";
            $s .= "q 34 0 0 34 51 796 cm /ImLogo Do Q\n";
            $x = 96.0;
        }

        $s .= $this->pdfText($x, 811, 'F2', 10.5, [0.97, 0.94, 0.90], 'LALEEN BEAUTY SALON — ' . $contract->contract_number . ' (Page ' . $pageNum . ')');

        return $s;
    }

    private function buildSignatureBlock(
        StaffContract $contract,
        string $staffName,
        string $signedAtLabel,
        float $topY,
        bool $hasSigImage
    ): string {
        $boxBottom = max(44.0, $topY - 104.0);
        $boxHeight = $topY - $boxBottom;

        $s = "0.99 0.97 0.96 rg\n";
        $s .= "0.85 0.56 0.51 RG 0.9 w\n";
        $s .= sprintf("50 %.2f 495 %.2f re B\n", $boxBottom, $boxHeight);

        $s .= $this->pdfText(64, $topY - 18, 'F2', 10.0, [0.42, 0.22, 0.20], 'DIGITAL SIGN-OFF & ACCEPTANCE');
        $signedBy = $contract->isLocked()
            ? ($contract->signed_name ?: $staffName)
            : 'Awaiting Employee Signature';

        $sigMode = $contract->isLocked()
            ? ($contract->signature_type === 'drawn' ? 'Drawn Digital Signature + PIN Verified' : 'Typed Digital Signature + PIN Verified')
            : 'Pending Employee Sign-Off';

        $s .= $this->pdfText(64, $topY - 36, 'F2', 9.5, [0.13, 0.09, 0.11], 'Signed By: ' . $signedBy);
        $s .= $this->pdfText(64, $topY - 52, 'F1', 9.0, [0.25, 0.20, 0.20], 'Method: ' . $sigMode);
        $s .= $this->pdfText(64, $topY - 68, 'F1', 9.0, [0.25, 0.20, 0.20], 'Timestamp: ' . $signedAtLabel);

        if ($hasSigImage) {
            $sigY = max($boxBottom + 10.0, $topY - 92.0);
            $s .= sprintf("q 130 0 0 38 355 %.2f cm /ImSig Do Q\n", $sigY);
        } else {
            $s .= $this->pdfText(355, $topY - 38, 'F2', 9.5, [0.13, 0.09, 0.11], 'For Laleen Beauty Salon');
            $s .= $this->pdfText(355, $topY - 54, 'F1', 8.5, [0.40, 0.34, 0.34], 'Authorized Official Agreement');
        }

        if ($contract->isLocked() && $contract->signed_ip) {
            $s .= $this->pdfText(355, $topY - 84, 'F1', 8.0, [0.45, 0.38, 0.38], 'Verified IP: ' . $contract->signed_ip);
        }

        return $s;
    }

    /**
     * Load public/images/laleen-contract-logo.png (via base64 data URI / file) and convert to RGB JPEG bytes for PDF XObject.
     *
     * @return array{width: int, height: int, data: string}|null
     */
    private function prepareLogoJpeg(): ?array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        $dataUri = StaffContract::logoDataUri();
        if ($dataUri === '' || !str_contains($dataUri, ',')) {
            return null;
        }

        [, $b64] = explode(',', $dataUri, 2);
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $targetSize = 240;

        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetSize, $targetSize, $srcW, $srcH);

        ob_start();
        imagejpeg($canvas, null, 92);
        $jpegBytes = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($canvas);

        if ($jpegBytes === '') {
            return null;
        }

        return [
            'width' => $targetSize,
            'height' => $targetSize,
            'data' => $jpegBytes,
        ];
    }

    /**
     * Render drawn employee signature PNG onto warm cream background for PDF signature box.
     *
     * @return array{width: int, height: int, data: string}|null
     */
    private function prepareDrawnSignatureJpeg(StaffContract $contract): ?array
    {
        if (
            !$contract->isLocked()
            || $contract->signature_type !== 'drawn'
            || empty($contract->signature_data)
            || !str_starts_with((string) $contract->signature_data, 'data:image/')
            || !function_exists('imagecreatefromstring')
        ) {
            return null;
        }

        $parts = explode(',', (string) $contract->signature_data, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $raw = base64_decode($parts[1], true);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 4 || $srcH < 4) {
            imagedestroy($src);
            return null;
        }

        $outW = 260;
        $outH = 76;
        $canvas = imagecreatetruecolor($outW, $outH);
        $bg = imagecolorallocate($canvas, 252, 247, 245);
        $ink = imagecolorallocate($canvas, 34, 20, 22);
        imagefill($canvas, 0, 0, $bg);

        // Convert light strokes (drawn on dark canvas) into crisp dark espresso ink on light PDF paper
        $scaled = imagecreatetruecolor($outW, $outH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefill($scaled, 0, 0, $transparent);
        imagecopyresampled($scaled, $src, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);

        for ($y = 0; $y < $outH; $y++) {
            for ($x = 0; $x < $outW; $x++) {
                $rgba = imagecolorat($scaled, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 110) {
                    imagesetpixel($canvas, $x, $y, $ink);
                }
            }
        }

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpegBytes = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($scaled);
        imagedestroy($canvas);

        return $jpegBytes !== '' ? ['width' => $outW, 'height' => $outH, 'data' => $jpegBytes] : null;
    }

    /**
     * @param  list<float>  $rgb
     */
    private function pdfText(float $x, float $y, string $font, float $size, array $rgb, string $text): string
    {
        $clean = $this->sanitizePdfString($text);

        return sprintf(
            "BT /%s %.2f Tf %.2f %.2f %.2f rg %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $clean
        );
    }

    private function sanitizePdfString(string $text): string
    {
        $replacements = [
            '—' => '-',
            '–' => '-',
            '•' => '|',
            '·' => '|',
            '’' => "'",
            '‘' => "'",
            '“' => '"',
            '”' => '"',
            '≤' => '<=',
            '≥' => '>=',
            '→' => '->',
        ];
        $ascii = strtr($text, $replacements);
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /**
     * @return list<string>
     */
    private function wrapLine(string $text, int $maxChars): array
    {
        $wrapped = wordwrap($text, $maxChars, "\n", true);

        return explode("\n", $wrapped);
    }

    /**
     * @param  list<string>  $pageStreams
     * @param  array{width: int, height: int, data: string}|null  $logoImage
     * @param  array{width: int, height: int, data: string}|null  $sigImage
     */
    private function compilePdfDocument(array $pageStreams, ?array $logoImage = null, ?array $sigImage = null): string
    {
        $objects = [];

        // 1: Catalog, 2: Pages, 3: Helvetica, 4: Helvetica-Bold
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $nextId = 5;
        $xObjectRefs = [];

        if ($logoImage !== null) {
            $logoObjId = $nextId++;
            $logoLen = strlen($logoImage['data']);
            $objects[$logoObjId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $logoImage['width'],
                $logoImage['height'],
                $logoLen,
                $logoImage['data']
            );
            $xObjectRefs[] = "/ImLogo {$logoObjId} 0 R";
        }

        if ($sigImage !== null) {
            $sigObjId = $nextId++;
            $sigLen = strlen($sigImage['data']);
            $objects[$sigObjId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $sigImage['width'],
                $sigImage['height'],
                $sigLen,
                $sigImage['data']
            );
            $xObjectRefs[] = "/ImSig {$sigObjId} 0 R";
        }

        $xObjectDict = !empty($xObjectRefs)
            ? ' /XObject << ' . implode(' ', $xObjectRefs) . ' >>'
            : '';

        $pageObjectIds = [];

        foreach ($pageStreams as $streamContent) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageObjectIds[] = $pageId;

            $len = strlen($streamContent);
            $objects[$contentId] = "<< /Length {$len} >>\nstream\n{$streamContent}endstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>{$xObjectDict} >> /Contents {$contentId} 0 R >>";
        }

        $kidsRefs = implode(' ', array_map(fn (int $id) => "{$id} 0 R", $pageObjectIds));
        $pageCount = count($pageObjectIds);
        $objects[2] = "<< /Type /Pages /Kids [ {$kidsRefs} ] /Count {$pageCount} >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;

        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
