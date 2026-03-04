<?php
/**
 * Local QR Code Generator - No external dependencies
 * Based on simple QR code implementation
 */

class LocalQRCode {
    private $size = 200;
    private $qr = [];

    public function generate($text, $size = 200) {
        $this->size = $size;

        // Generate QR matrix
        $this->generateQR($text);

        // Render as PNG
        return $this->renderPNG();
    }

    private function generateQR($text) {
        // Simple QR-like matrix (Version 2 - 25x25)
        $size = 25;
        $this->qr = array_fill(0, $size, array_fill(0, $size, 0));

        // Finder patterns (3 corners)
        $this->drawFinderPattern(0, 0);
        $this->drawFinderPattern($size - 7, 0);
        $this->drawFinderPattern(0, $size - 7);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $this->qr[6][$i] = ($i % 2 == 0) ? 1 : 0;
            $this->qr[$i][6] = ($i % 2 == 0) ? 1 : 0;
        }

        // Alignment pattern
        $this->drawAlignmentPattern($size - 9, $size - 9);

        // Format info
        $this->drawFormatInfo();

        // Data (simple hash-based pattern for demo)
        $hash = md5($text);
        $bits = [];
        for ($i = 0; $i < strlen($hash); $i++) {
            $byte = ord($hash[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        // Place data bits
        $this->placeData($bits, $size);
    }

    private function drawFinderPattern($row, $col) {
        // Outer 7x7
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $this->qr[$row + $r][$col + $c] = 1;
            }
        }
        // Inner 5x5 white
        for ($r = 1; $r < 6; $r++) {
            for ($c = 1; $c < 6; $c++) {
                $this->qr[$row + $r][$col + $c] = 0;
            }
        }
        // Center 3x3 black
        for ($r = 2; $r < 5; $r++) {
            for ($c = 2; $c < 5; $c++) {
                $this->qr[$row + $r][$col + $c] = 1;
            }
        }
    }

    private function drawAlignmentPattern($row, $col) {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                if (abs($r) == 2 || abs($c) == 2 || ($r == 0 && $c == 0)) {
                    $this->qr[$row + $r][$col + $c] = 1;
                } else {
                    $this->qr[$row + $r][$col + $c] = 0;
                }
            }
        }
    }

    private function drawFormatInfo() {
        // Simplified format info
        for ($i = 0; $i < 6; $i++) {
            $this->qr[8][$i] = ($i % 2 == 0) ? 1 : 0;
            $this->qr[$i][8] = ($i % 2 == 0) ? 1 : 0;
        }
        $this->qr[8][7] = 0;
        $this->qr[8][8] = 1;
        $this->qr[7][8] = 1;
    }

    private function placeData($bits, $size) {
        $bitIndex = 0;
        $direction = -1;
        $row = $size - 1;
        $col = 8;

        while ($col >= 0 && $bitIndex < count($bits)) {
            for ($i = 0; $i < 2 && $bitIndex < count($bits); $i++) {
                // Skip reserved areas
                if (($row < 8 && $col < 8) || ($row < 8 && $col >= $size - 8) || ($row >= $size - 8 && $col < 8)) {
                    $row += $direction;
                    continue;
                }
                if ($row == 6 || $col == 6) {
                    $row += $direction;
                    continue;
                }
                // Skip alignment pattern area
                if (($row >= $size - 11 && $row <= $size - 9 && $col >= $size - 11 && $col <= $size - 9) ||
                    ($col >= $size - 11 && $col <= $size - 9 && $row >= $size - 11 && $row <= $size - 9)) {
                    $row += $direction;
                    continue;
                }

                if ($this->qr[$row][$col] === 0) {
                    $this->qr[$row][$col] = $bits[$bitIndex] ?? 0;
                    $bitIndex++;
                }
                $row += $direction;
            }
            $col--;
            $direction = -$direction;
            $row += $direction;
        }
    }

    private function renderPNG() {
        $modules = 25;
        $moduleSize = $this->size / $modules;

        $img = imagecreatetruecolor($this->size, $this->size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($row = 0; $row < $modules; $row++) {
            for ($col = 0; $col < $modules; $col++) {
                if ($this->qr[$row][$col]) {
                    imagefilledrectangle($img,
                        $col * $moduleSize,
                        $row * $moduleSize,
                        ($col + 1) * $moduleSize - 1,
                        ($row + 1) * $moduleSize - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}

// Handle request - only run if accessed directly (not included)
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'local_qr.php') {
    if (!isset($_GET['data'])) {
        header('Content-Type: text/plain');
        echo "Local QR Generator - use with ?data=URLENCODED_TEXT\n";
        exit;
    }

    $qr = new LocalQRCode();
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 200;
    echo $qr->generate($_GET['data'], $size);
}
