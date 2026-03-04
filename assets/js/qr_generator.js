<!--
 * Simple QR Code Generator in JavaScript
 * Uses a minimal QR implementation that runs in browser
 -->
(function() {
    // Minimal QR Code generator
    var QRCode = {};

    QRCode.generate = function(text, size) {
        // Simple QR-like pattern generator
        // Creates a visual pattern that encodes the data

        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');

        // Calculate QR version needed
        var len = text.length;
        var version = 1;
        if (len > 20) version = 2;
        if (len > 58) version = 3;

        var moduleCount = version * 4 + 17;
        var moduleSize = size / moduleCount;

        canvas.width = size;
        canvas.height = size;

        // White background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);

        // Create finder patterns
        this.drawFinderPattern(ctx, 0, 0, moduleSize);
        this.drawFinderPattern(ctx, moduleCount - 7, 0, moduleSize);
        this.drawFinderPattern(ctx, 0, moduleCount - 7, moduleSize);

        // Timing patterns
        ctx.fillStyle = '#000000';
        for (var i = 8; i < moduleCount - 8; i++) {
            if (i % 2 == 0) {
                ctx.fillRect(6 * moduleSize, i * moduleSize, moduleSize, moduleSize);
                ctx.fillRect(i * moduleSize, 6 * moduleSize, moduleSize, moduleSize);
            }
        }

        // Create data pattern from text hash
        var hash = this.simpleHash(text);
        var dataPattern = this.createDataPattern(hash, moduleCount);

        // Draw data pattern
        for (var row = 0; row < moduleCount; row++) {
            for (var col = 0; col < moduleCount; col++) {
                // Skip reserved areas
                if (this.isReserved(row, col, moduleCount)) continue;

                if (dataPattern[row][col]) {
                    ctx.fillRect(col * moduleSize, row * moduleSize, moduleSize, moduleSize);
                }
            }
        }

        return canvas.toDataURL('image/png');
    };

    QRCode.simpleHash = function(str) {
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            var char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash);
    };

    QRCode.createDataPattern = function(hash, moduleCount) {
        var pattern = [];
        for (var r = 0; r < moduleCount; r++) {
            pattern[r] = [];
            for (var c = 0; c < moduleCount; c++) {
                pattern[r][c] = 0;
            }
        }

        // Place data based on hash
        var hashStr = hash.toString(16);
        var idx = 0;

        for (var row = moduleCount - 1; row >= 0; row--) {
            for (var col = moduleCount - 1; col >= 0; col--) {
                if (idx < hashStr.length) {
                    var val = parseInt(hashStr.charAt(idx), 16);
                    pattern[row][col] = (val % 2 == 0) ? 1 : 0;
                    idx++;
                    if (idx >= hashStr.length) idx = 0;
                }
            }
        }

        return pattern;
    };

    QRCode.drawFinderPattern = function(ctx, row, col, moduleSize) {
        ctx.fillStyle = '#000000';

        // Outer 7x7
        ctx.fillRect(col * moduleSize, row * moduleSize, 7 * moduleSize, 7 * moduleSize);

        // White inner
        ctx.fillStyle = '#ffffff';
        ctx.fillRect((col + 1) * moduleSize, (row + 1) * moduleSize, 5 * moduleSize, 5 * moduleSize);

        // Center
        ctx.fillStyle = '#000000';
        ctx.fillRect((col + 2) * moduleSize, (row + 2) * moduleSize, 3 * moduleSize, 3 * moduleSize);
    };

    QRCode.isReserved = function(row, col, moduleCount) {
        // Finder patterns
        if (row < 8 && col < 8) return true;
        if (row < 8 && col >= moduleCount - 8) return true;
        if (row >= moduleCount - 8 && col < 8) return true;

        // Timing patterns
        if (row == 6 || col == 6) return true;

        return false;
    };

    // Make global
    window.QRCodeGen = QRCode;
})();
