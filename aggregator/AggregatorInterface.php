<?php
/**
 * Aggregator Interface
 * Interface untuk semua aggregator (Digiflazz, Tripay, dll)
 */

interface AggregatorInterface {
    /**
     * Get nama aggregator
     */
    public function getName(): string;

    /**
     * Cek saldo di aggregator
     */
    public function checkBalance(): float;

    /**
     * Beli produk (pulsa/paket data)
     */
    public function purchase(string $sku, string $customerNo, string $refId): array;

    /**
     * Cek status transaksi
     */
    public function checkStatus(string $refId): array;

    /**
     * Get list produk dari aggregator
     */
    public function getPriceList(string $type = 'prepaid'): array;

    /**
     * Generate sign/signature untuk request
     */
    public function generateSign(string $data): string;
}

/**
 * Transaction Result Helper
 */
class TransactionResult {
    public bool $success;
    public string $status; // pending, sukses, gagal
    public string $rc;
    public string $message;
    public ?string $sn; // Serial Number
    public float $price;
    public float $buyerLastSaldo;
    public array $raw;

    public function __construct(array $data) {
        $this->success = ($data['status'] ?? '') === 'Sukses';
        $this->status = $data['status'] ?? 'Gagal';
        $this->rc = $data['rc'] ?? '99';
        $this->message = $data['message'] ?? '';
        $this->sn = $data['sn'] ?? null;
        $this->price = floatval($data['price'] ?? 0);
        $this->buyerLastSaldo = floatval($data['buyer_last_saldo'] ?? 0);
        $this->raw = $data;
    }

    public function isPending(): bool {
        return $this->status === 'Pending';
    }

    public function isSuccess(): bool {
        return $this->status === 'Sukses';
    }

    public function isFailed(): bool {
        return $this->status === 'Gagal';
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'rc' => $this->rc,
            'message' => $this->message,
            'sn' => $this->sn,
            'price' => $this->price,
            'buyer_last_saldo' => $this->buyerLastSaldo,
        ];
    }
}
