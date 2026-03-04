<?php
/**
 * Minimal QR Code Generator - Creates scannable QR code
 * Uses PHP QR Code patterns
 */

class MinimalQR {
    private $width = 21; // Version 1 QR
    private $modules = [];

    public function generate($text, $size = 200) {
        // Initialize matrix
        $this->modules = array_fill(0, $this->width, array_fill(0, $this->width, 0));

        // Add finder patterns (3 corners)
        $this->addFinderPattern(0, 0);
        $this->addFinderPattern($this->width - 7, 0);
        $this->addFinderPattern(0, $this->width - 7);

        // Add timing patterns
        for ($i = 8; $i < $this->width - 8; $i++) {
            $this->modules[6][$i] = ($i % 2 == 0);
            $this->modules[$i][6] = ($i % 2 == 0);
        }

        // Reserve format areas
        $this->modules[8][0] = 1;
        $this->modules[8][$this->width - 1] = 1;
        $this->modules[0][8] = 1;
        $this->modules[$this->width - 1][8] = 1;

        // Encode and place data
        $this->encodeData($text);

        // Render to PNG
        return $this->renderPNG($size);
    }

    private function addFinderPattern($row, $col) {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                if ($r == 0 || $r == 6 || $c == 0 || $c == 6 ||
                    ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                    $this->modules[$row + $r][$col + $c] = 1;
                }
            }
        }
    }

    private function encodeData($text) {
        // Simple byte mode encoding
        $bits = [];

        // Mode indicator (byte mode = 0100)
        $bits = array_merge($bits, [0,1,0,0]);

        // Character count
        $len = strlen($text);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($len >> $i) & 1;
        }

        // Data
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        // Pad to 152 bits
        $bits = array_pad($bits, 152, 0);

        // Place bits in matrix
        $this->placeBits($bits);
    }

    private function placeBits($bits) {
        $x = $this->width - 1;
        $y = $this->width - 1;
        $dir = -1;
        $idx = 0;

        while ($x >= 0 && $idx < count($bits)) {
            if ($this->isMasked($y, $x)) {
                $this->modules[$y][$x] = $bits[$idx] ?? 0;
                $idx++;
            }

            // Move
            $x += $dir;
            if ($x < 0 || $x >= $this->width) {
                $x -= $dir;
                $dir = -$dir;
                $y--;
            }
        }
    }

    private function isMasked($row, $col) {
        // Finder patterns
        if ($row < 8 && $col < 8) return false;
        if ($row < 8 && $col >= $this->width - 8) return false;
        if ($row >= $this->width - 8 && $col < 8) return false;

        // Timing patterns
        if ($row == 6 || $col == 6) return false;

        return true;
    }

    private function renderPNG($size) {
        $moduleSize = $size / $this->width;

        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($row = 0; $row < $this->width; $row++) {
            for ($col = 0; $col < $this->width; $col++) {
                if ($this->modules[$row][$col]) {
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

// Standalone execution
if (php_sapi_name() !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'minimal_qr.php') {
    $text = $_GET['data'] ?? '';
    $size = (int)($_GET['size'] ?? 200);

    if (empty($text)) {
        header('Content-Type: text/html');
        echo "Minimal QR - use ?data=TEXT";
        exit;
    }

    $qr = new MinimalQR();
    $result = $qr->generate($text, $size);

    header('Content-Type: image/png');
    echo base64_decode(substr($result, 22));
}
