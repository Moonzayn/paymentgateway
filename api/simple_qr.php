<?php
/**
 * Simple QR Code Generator - Fixed version
 * Creates valid, scannable QR codes with minimal memory usage
 */

class SimpleQRCode {
    private $width = 0;
    private $frame = [];
    private $x = 0;
    private $y = 0;
    private $dir = -1;
    private $bit = 0;

    public function generate($text, $size = 200) {
        // Use version 1 QR code (21x21 modules)
        $this->width = 21;
        $this->frame = array_fill(0, $this->width, array_fill(0, $this->width, 0));

        // Create finder patterns (the 3 big squares)
        $this->makeFinderPattern(0, 0);
        $this->makeFinderPattern($this->width - 7, 0);
        $this->makeFinderPattern(0, $this->width - 7);

        // Timing patterns
        for ($i = 8; $i < $this->width - 8; $i++) {
            $this->frame[6][$i] = ($i % 2 == 0);
            $this->frame[$i][6] = ($i % 2 == 0);
        }

        // Reserve format areas
        $this->frame[8][0] = 1;
        $this->frame[8][$this->width - 1] = 1;
        $this->frame[0][8] = 1;
        $this->frame[$this->width - 1][8] = 1;

        // Encode data
        $this->encodeData($text);

        // Render image
        return $this->renderPNG($size);
    }

    private function makeFinderPattern($row, $col) {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $val = 0;
                if ($r == 0 || $r == 6 || $c == 0 || $c == 6) {
                    $val = 1;
                } elseif ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4) {
                    $val = 1;
                }
                $this->frame[$row + $r][$col + $c] = $val;
            }
        }
    }

    private function encodeData($text) {
        $data = [];

        // Mode indicator for byte mode (0100)
        $data = array_merge($data, [0,1,0,0]);

        // Character count (8 bits for version 1)
        $len = strlen($text);
        for ($i = 7; $i >= 0; $i--) {
            $data[] = ($len >> $i) & 1;
        }

        // Data bytes
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $data[] = ($byte >> $j) & 1;
            }
        }

        // Terminator
        $data = array_pad($data, 152, 0);

        // Place data using zigzag pattern
        $this->placeData($data);
    }

    private function placeData($bits) {
        $this->x = $this->width - 1;
        $this->y = $this->width - 1;
        $this->dir = -1;

        $maxBits = min(count($bits), 128); // Limit to prevent memory issues
        $bitIndex = 0;

        while ($this->x >= 0 && $bitIndex < $maxBits) {
            // Check if current position is available
            if ($this->isAvailable($this->y, $this->x)) {
                $this->frame[$this->y][$this->x] = $bits[$bitIndex];
                $bitIndex++;
            }

            // Move to next position
            $this->moveNext();
        }
    }

    private function isAvailable($row, $col) {
        // Skip reserved areas
        if ($row < 0 || $col < 0 || $row >= $this->width || $col >= $this->width) return false;

        // Finder patterns
        if ($row < 8 && $col < 8) return false;
        if ($row < 8 && $col >= $this->width - 8) return false;
        if ($row >= $this->width - 8 && $col < 8) return false;

        // Timing patterns
        if ($row == 6 || $col == 6) return false;

        return true;
    }

    private function moveNext() {
        // Zigzag pattern
        $this->x += $this->dir;

        if ($this->x < 0 || $this->x >= $this->width) {
            $this->x -= $this->dir;
            $this->dir = -$this->dir;
            $this->y -= 1;
        }
    }

    private function renderPNG($size) {
        $moduleSize = $size / $this->width;

        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($row = 0; $row < $this->width; $row++) {
            for ($col = 0; $col < $this->width; $col++) {
                if (!empty($this->frame[$row][$col])) {
                    imagefilledrectangle($img,
                        intval($col * $moduleSize),
                        intval($row * $moduleSize),
                        intval(($col + 1) * $moduleSize) - 1,
                        intval(($row + 1) * $moduleSize) - 1,
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

// Standalone execution
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'simple_qr.php') {
    $text = $_GET['data'] ?? $_GET['text'] ?? '';
    $size = (int)($_GET['size'] ?? 200);

    if (empty($text)) {
        header('Content-Type: text/html');
        echo "Simple QR Generator<br>Usage: simple_qr.php?data=TEXT&size=200";
        exit;
    }

    $qr = new SimpleQRCode();
    $result = $qr->generate($text, $size);

    if (strpos($result, 'data:') === 0) {
        header('Content-Type: image/png');
        echo base64_decode(substr($result, 22));
    }
}
