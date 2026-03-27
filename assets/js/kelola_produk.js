/**
 * kelola_produk.js — form handling, mapping row management
 * Loaded as external script — bypasses CSP inline-script restriction
 */

(function() {
    // ─── Aggregator Options (set by PHP inline script before this loads) ──
    // window.AGGREGATOR_OPTIONS must be set in the page <head> before this script runs

    // ─── Format Rupiah ─────────────────────────────────────────────
    window.formatRupiah = function(num) {
        return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    };

    window.escapeHtml = function(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    // ─── Build aggregator options HTML ─────────────────────────────
    window.buildAggregatorOptions = function(selectedAgg) {
        var html = '<option value="">-- Pilih --</option>';
        var aggs = window.AGGREGATOR_OPTIONS;
        for (var i = 0; i < aggs.length; i++) {
            var agg = aggs[i];
            var sel = agg.name === selectedAgg ? ' selected' : '';
            html += '<option value="' + window.escapeHtml(agg.name) + '"' + sel + '>' +
                    window.escapeHtml(agg.label || agg.name) + '</option>';
        }
        return html;
    };

    // ─── Mapping Row Management ───────────────────────────────────
    window.addMappingRowCounter = window.addMappingRowCounter || 0;

    window.addMappingRow = function(bodyId, hargaJualInputId) {
        var body = document.getElementById(bodyId);
        if (!body) return;

        var rowIndex = Date.now();
        var prefix = bodyId === 'editMappingBody' ? 'edit' : 'add';
        window.addMappingRowCounter++;
        var sellerSuffix = window.addMappingRowCounter;

        var row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center mapping-row';
        row.id = prefix + '_mapping_row_' + rowIndex;
        row.dataset.index = rowIndex;

        row.innerHTML =
            '<div class="col-span-3">' +
                '<select name="mapping_aggregator_' + rowIndex + '"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' onchange="window.syncSourceAggregator(this); window.updateMappingProfitPreview(this)">' +
                    window.buildAggregatorOptions('') +
                '</select>' +
            '</div>' +
            '<div class="col-span-2">' +
                '<input type="text" name="mapping_seller_name_' + rowIndex + '"' +
                ' value="seller' + sellerSuffix + '" placeholder="seller1"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none">' +
            '</div>' +
            '<div class="col-span-3">' +
                '<input type="text" name="mapping_aggregator_sku_' + rowIndex + '"' +
                ' placeholder="kode di aggregator"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' oninput="window.updateMappingProfitPreview(this)">' +
            '</div>' +
            '<div class="col-span-2">' +
                '<input type="number" name="mapping_harga_modal_' + rowIndex + '"' +
                ' min="0" step="1" placeholder="0"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' oninput="window.updateAllMappingProfitPreviews(\'' + hargaJualInputId + '\')">' +
            '</div>' +
            '<div class="col-span-2">' +
                '<span class="text-xs font-medium profit-preview text-gray-400">Rp 0</span>' +
            '</div>' +
            '<div class="col-span-1 text-center">' +
                '<button type="button"' +
                ' onclick="window.removeMappingRow(\'' + prefix + '_mapping_row_' + rowIndex + '\')"' +
                ' class="text-red-500 hover:text-red-700 text-xs" title="Hapus">' +
                    '<i class="fas fa-trash"></i>' +
                '</button>' +
            '</div>';

        body.appendChild(row);
        window.updateAllMappingProfitPreviews(hargaJualInputId);
        window.syncSourceAggregatorFromBody(bodyId);
    };

    window.removeMappingRow = function(rowId) {
        var row = document.getElementById(rowId);
        var bodyId = row && row.parentElement ? row.parentElement.id : null;
        if (row) row.remove();
        if (bodyId) window.syncSourceAggregatorFromBody(bodyId);
    };

    // Sync source_aggregator hidden field from first mapping row
    window.syncSourceAggregatorFromBody = function(bodyId) {
        var body = document.getElementById(bodyId);
        if (!body) return;
        var firstRow = body.querySelector('.mapping-row');
        if (!firstRow) return;
        var aggSelect = firstRow.querySelector('select[name^="mapping_aggregator_"]');
        if (aggSelect && aggSelect.value) {
            var hiddenFieldId = bodyId === 'addMappingBody' ? 'add_source_aggregator' : 'edit_source_aggregator';
            var hiddenField = document.getElementById(hiddenFieldId);
            if (hiddenField) hiddenField.value = aggSelect.value;
        }
    };

    window.syncSourceAggregator = function(el) {
        var row = el.closest('.mapping-row');
        var body = row ? row.parentElement : null;
        if (body) window.syncSourceAggregatorFromBody(body.id);
    };

    window.updateMappingProfitPreview = function(el) {
        var row = el.closest('.mapping-row');
        if (!row) return;

        var hargaModalInput = row.querySelector('input[name^="mapping_harga_modal_"]');
        var hargaModal = parseFloat(hargaModalInput && hargaModalInput.value) || 0;

        var form = row.closest('form');
        var hargaJual = parseFloat(form && form.querySelector('input[name="harga_jual"]') && form.querySelector('input[name="harga_jual"]').value) || 0;

        var profit = hargaJual - hargaModal;
        var profitEl = row.querySelector('.profit-preview');
        if (!profitEl) return;

        profitEl.textContent = window.formatRupiah(profit);
        var colorClass = profit >= 0 ? 'text-green-600' : 'text-red-600';
        // Reset all color classes then add the right one
        profitEl.className = 'text-xs font-medium profit-preview ' + colorClass;
    };

    window.updateAllMappingProfitPreviews = function(hargaJualInputId) {
        var hargaJualInput = document.getElementById(hargaJualInputId);
        if (!hargaJualInput) return;
        var hargaJual = parseFloat(hargaJualInput.value) || 0;

        var form = hargaJualInput.closest('form');
        if (!form) return;

        var rows = form.querySelectorAll('.mapping-row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var hargaModalInput = row.querySelector('input[name^="mapping_harga_modal_"]');
            var hargaModal = parseFloat(hargaModalInput && hargaModalInput.value) || 0;
            var profit = hargaJual - hargaModal;
            var profitEl = row.querySelector('.profit-preview');
            if (!profitEl) continue;
            profitEl.textContent = window.formatRupiah(profit);
            var colorClass = profit >= 0 ? 'text-green-600' : 'text-red-600';
            profitEl.className = 'text-xs font-medium profit-preview ' + colorClass;
        }
    };

    window.countMappingRows = function(bodyId) {
        var body = document.getElementById(bodyId);
        return body ? body.querySelectorAll('.mapping-row').length : 0;
    };

    window.validateMappingForm = function(bodyId, errorId) {
        var body = document.getElementById(bodyId);
        var errorEl = document.getElementById(errorId);
        if (!body) return true;

        var rows = body.querySelectorAll('.mapping-row');
        var valid = false;
        var message = '';

        if (rows.length === 0) {
            message = 'Minimal 1 mapping aggregator wajib diisi!';
        } else {
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var skuInput = row.querySelector('input[name^="mapping_aggregator_sku_"]');
                var modalInput = row.querySelector('input[name^="mapping_harga_modal_"]');
                if (skuInput && skuInput.value.trim() && modalInput && modalInput.value > 0) {
                    valid = true;
                    break;
                }
            }
            if (!valid) {
                message = 'Minimal 1 mapping wajib memiliki Kode dan Harga Modal!';
            }
        }

        if (message) {
            if (errorEl) {
                errorEl.classList.remove('hidden');
                errorEl.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i><span>' + message + '</span>';
            }
            return false;
        } else {
            if (errorEl) {
                errorEl.classList.add('hidden');
                errorEl.innerHTML = '';
            }
            return true;
        }
    };

    // ─── Edit Modal: load existing mappings ───────────────────────
    window.editPricingCache = {};

    window.buildMappingRow = function(prefix, rowIndex, entry) {
        entry = entry || {};
        window.addMappingRowCounter++;
        var row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center mapping-row';
        row.id = prefix + '_mapping_row_' + rowIndex;
        row.dataset.index = rowIndex;

        row.innerHTML =
            '<div class="col-span-3">' +
                '<select name="mapping_aggregator_' + rowIndex + '"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' onchange="window.syncSourceAggregator(this); window.updateMappingProfitPreview(this)">' +
                    window.buildAggregatorOptions(entry.aggregator || '') +
                '</select>' +
            '</div>' +
            '<div class="col-span-2">' +
                '<input type="text" name="mapping_seller_name_' + rowIndex + '"' +
                ' value="' + window.escapeHtml(entry.seller_name || ('seller' + window.addMappingRowCounter)) + '" placeholder="seller1"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none">' +
            '</div>' +
            '<div class="col-span-3">' +
                '<input type="text" name="mapping_aggregator_sku_' + rowIndex + '"' +
                ' value="' + window.escapeHtml(entry.aggregator_sku || '') + '" placeholder="kode di aggregator"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' oninput="window.updateMappingProfitPreview(this)">' +
            '</div>' +
            '<div class="col-span-2">' +
                '<input type="number" name="mapping_harga_modal_' + rowIndex + '"' +
                ' min="0" step="1" value="' + (entry.harga_modal || '') + '" placeholder="0"' +
                ' class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:outline-none"' +
                ' oninput="window.updateAllMappingProfitPreviews(\'edit_harga_jual\')">' +
            '</div>' +
            '<div class="col-span-2">' +
                '<span class="text-xs font-medium profit-preview text-gray-400">Rp 0</span>' +
            '</div>' +
            '<div class="col-span-1 text-center">' +
                '<button type="button"' +
                ' onclick="window.removeMappingRow(\'' + prefix + '_mapping_row_' + rowIndex + '\')"' +
                ' class="text-red-500 hover:text-red-700 text-xs" title="Hapus">' +
                    '<i class="fas fa-trash"></i>' +
                '</button>' +
            '</div>';

        // Trigger initial profit calculation after DOM insertion
        setTimeout(function() {
            window.updateAllMappingProfitPreviews('edit_harga_jual');
        }, 0);

        return row;
    };

    window.loadEditMappings = function(produkId) {
        var body = document.getElementById('editMappingBody');
        if (!body) return;
        body.innerHTML = '';

        var kodeProduk = document.getElementById('edit_kode_produk') &&
                         document.getElementById('edit_kode_produk').value;

        var cached = window.editPricingCache[produkId];
        if (cached && cached.length > 0) {
            for (var i = 0; i < cached.length; i++) {
                var entry = cached[i];
                var rowIndex = Date.now() + i;
                body.appendChild(window.buildMappingRow('edit', rowIndex, entry));
            }
            window.updateAllMappingProfitPreviews('edit_harga_jual');
            window.syncSourceAggregatorFromBody('editMappingBody');
        } else {
            var url = 'api_aggregator.php?action=list_pricing&sku_code=' +
                      encodeURIComponent(kodeProduk || '');
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.entries) {
                        window.editPricingCache[produkId] = data.entries;
                        for (var j = 0; j < data.entries.length; j++) {
                            var entry = data.entries[j];
                            var rowIndex = Date.now() + j;
                            body.appendChild(window.buildMappingRow('edit', rowIndex, entry));
                        }
                        window.updateAllMappingProfitPreviews('edit_harga_jual');
                        window.syncSourceAggregatorFromBody('editMappingBody');
                    }
                })
                .catch(function() {});
        }
    };

    // ─── Edit Modal opener ─────────────────────────────────────────
    window.openEditModal = function(id, kode, nama, kategoriId, provider, nominalDisplay, hargaJual, status) {
        document.getElementById('edit_produk_id').value = id;
        document.getElementById('edit_kode_produk').value = kode;
        document.getElementById('edit_nama_produk').value = nama;
        document.getElementById('edit_kategori_id').value = kategoriId;
        document.getElementById('edit_provider').value = provider;
        document.getElementById('edit_nominal').value = nominalDisplay;
        document.getElementById('edit_harga_jual').value = hargaJual;
        document.getElementById('edit_status').value = status;

        if (typeof window.updateNominalHint === 'function') {
            window.updateNominalHint('edit', kategoriId);
        }

        window.loadEditMappings(id);
        window.openModal('editProductModal');
    };

    // ─── Nominal Hint (preserved from inline script) ──────────────
    window.updateNominalHint = function(modal, kategoriId) {
        var hintId = modal === 'add' ? 'add_nominal_hint' : 'edit_nominal_hint';
        var hintEl = document.getElementById(hintId);
        var placeholderId = modal === 'add' ? 'add_nominal' : 'edit_nominal';
        var inputEl = document.getElementById(placeholderId);
        if (!hintEl) return;

        var hints = {
            1: { hint: 'Pulsa: ketik jumlah (contoh: 5000)', placeholder: '5000' },
            2: { hint: 'Kuota: ketik kapasitas (contoh: 10 GB, 5 MB, Unlimited)', placeholder: '10 GB' },
            3: { hint: 'Token Listrik: ketik nominal token (contoh: 20000)', placeholder: '20000' },
            4: { hint: 'Transfer Tunai: ketik jumlah transfer (contoh: 100000)', placeholder: '100000' },
            5: { hint: 'Game: ketik jumlah/item (contoh: 86)', placeholder: '86' },
        };

        var info = hints[parseInt(kategoriId)] || hints[1];
        hintEl.textContent = info.hint;
        if (inputEl) inputEl.placeholder = info.placeholder;
    };

    // ─── Toast Notification ────────────────────────────────────────
    window.showToast = function(message, type) {
        type = type || 'info';
        var existing = document.getElementById('toastNotif');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.id = 'toastNotif';
        toast.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;min-width:280px;max-width:400px;padding:1rem 1.25rem;border-radius:0.5rem;display:flex;align-items:center;gap:0.75rem;animation:slideIn 0.3s ease;box-shadow:0 4px 12px rgba(0,0,0,0.15);';

        var colors = {
            success: { bg: '#10b981', icon: '<i class="fas fa-check-circle"></i>' },
            error:   { bg: '#ef4444', icon: '<i class="fas fa-exclamation-circle"></i>' },
            info:    { bg: '#3b82f6', icon: '<i class="fas fa-info-circle"></i>' },
        };
        var c = colors[type] || colors.info;
        toast.style.background = c.bg;
        toast.style.color = 'white';
        toast.innerHTML = c.icon + '<span>' + message + '</span>';

        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 4000);
    };

    // ─── Form Submit via AJAX ──────────────────────────────────────
    function submitProductForm(form, mappingBodyId, errorId) {
        console.log('[DEBUG] submitProductForm called', mappingBodyId, errorId);

        if (!window.validateMappingForm(mappingBodyId, errorId)) {
            console.log('[DEBUG] validateMappingForm FAILED');
            return;
        }
        console.log('[DEBUG] validateMappingForm PASSED');

        var formData = new FormData(form);
        formData.set('mapping_count', String(window.countMappingRows(mappingBodyId)));
        console.log('[DEBUG] mapping_count:', window.countMappingRows(mappingBodyId));

        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Menyimpan...';
        }

        fetch('kelola_produk.php', {
            method: 'POST',
            body: formData,
        })
        .then(function(r) {
            console.log('[DEBUG] fetch response ok, redirecting to:', r.url);
            window.location.href = r.url || 'kelola_produk.php';
        })
        .catch(function(err) {
            console.error('[DEBUG] fetch error:', err);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            window.showToast('Terjadi kesalahan: ' + err.message, 'error');
        });
    }

    // ─── Init ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        // Sync source_aggregator on load
        window.syncSourceAggregatorFromBody('addMappingBody');

        // Add form - use native submit for reliability
        var addForm = document.getElementById('addProductForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                // Validate mapping before submit
                if (!window.validateMappingForm('addMappingBody', 'addMappingError')) {
                    e.preventDefault();
                    return false;
                }
                console.log('[DEBUG] Add form submitting...');
                // Continue with native form submission
            });
        }

        // Edit form - use native submit
        var editForm = document.getElementById('editProductForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                if (!window.validateMappingForm('editMappingBody', 'editMappingError')) {
                    e.preventDefault();
                    return false;
                }
                console.log('[DEBUG] Edit form submitting...');
            });
        }

        // Add modal: kategori change → nominal hint
        var addKatSelect = document.querySelector('#addProductModal select[name="kategori_id"]');
        if (addKatSelect) {
            addKatSelect.addEventListener('change', function() {
                window.updateNominalHint('add', this.value);
            });
            window.updateNominalHint('add', addKatSelect.value);
        }

        // Edit modal: kategori change → nominal hint
        var editKatSelect = document.getElementById('edit_kategori_id');
        if (editKatSelect) {
            editKatSelect.addEventListener('change', function() {
                window.updateNominalHint('edit', this.value);
            });
        }

        // Add modal: harga_jual change → update all profit previews
        var addHargaJual = document.getElementById('add_harga_jual');
        if (addHargaJual) {
            addHargaJual.addEventListener('input', function() {
                window.updateAllMappingProfitPreviews('add_harga_jual');
            });
        }

        // Edit modal: harga_jual change → update all profit previews
        var editHargaJual = document.getElementById('edit_harga_jual');
        if (editHargaJual) {
            editHargaJual.addEventListener('input', function() {
                window.updateAllMappingProfitPreviews('edit_harga_jual');
            });
        }
    });

})();

// ─── Expose global functions for inline onclick handlers ──────────────
// Inline HTML onclick="funcName(...)" needs functions on the global scope,
// not just window properties inside an IIFE.
function addMappingRow(bodyId, hargaJualInputId) { window.addMappingRow(bodyId, hargaJualInputId); }
function removeMappingRow(rowId) { window.removeMappingRow(rowId); }
function updateMappingProfitPreview(el) { window.updateMappingProfitPreview(el); }
function updateAllMappingProfitPreviews(hargaJualInputId) { window.updateAllMappingProfitPreviews(hargaJualInputId); }
function loadEditMappings(produkId) { window.loadEditMappings(produkId); }
function openEditModal(id, kode, nama, kategoriId, provider, nominalDisplay, hargaJual, status) {
    window.openEditModal(id, kode, nama, kategoriId, provider, nominalDisplay, hargaJual, status);
}
function showToast(message, type) { window.showToast(message, type); }
function formatRupiah(num) { return window.formatRupiah(num); }
