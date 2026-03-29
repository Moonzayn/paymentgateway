<?php
/**
 * Aggregator Manager
 * Factory untuk memilih dan mengelola aggregator
 */

require_once __DIR__ . '/AggregatorInterface.php';
require_once __DIR__ . '/DigiflazzAggregator.php';

class AggregatorManager {
    private static ?AggregatorManager $instance = null;
    private array $aggregators = [];
    private ?AggregatorInterface $activeAggregator = null;

    private function __construct() {
        $this->registerDefaultAggregators();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function registerDefaultAggregators() {
        // Load Digiflazz credentials
        $digiflazzUsername = getPengaturan('digiflazz_username') ?: 'pehaduD7V7ro';
        $digiflazzApiKey = getPengaturan('digiflazz_api_key') ?: '3c737104-00a2-5561-b992-341577aa87f5';

        error_log("AggregatorManager: Loading Digiflazz - username=$digiflazzUsername");

        if (!empty($digiflazzUsername) && !empty($digiflazzApiKey)) {
            $agg = new DigiflazzAggregator($digiflazzUsername, $digiflazzApiKey);
            $this->register('digiflazz', $agg);
            $this->setActive('digiflazz');
            error_log("AggregatorManager: Digiflazz registered");
        } else {
            error_log("AggregatorManager: WARNING - Missing credentials!");
        }
    }

    public function register(string $name, AggregatorInterface $aggregator): void {
        $this->aggregators[$name] = $aggregator;
    }

    public function get(string $name): ?AggregatorInterface {
        return $this->aggregators[$name] ?? null;
    }

    public function setActive(string $name): bool {
        if (isset($this->aggregators[$name])) {
            $this->activeAggregator = $this->aggregators[$name];
            return true;
        }
        return false;
    }

    public function getActive(): ?AggregatorInterface {
        return $this->activeAggregator;
    }

    public function getAvailable(): array {
        return array_keys($this->aggregators);
    }

    public function getForProduct(int $productId): ?AggregatorInterface {
        $conn = koneksi();
        $stmt = $conn->prepare("SELECT aggregator FROM produk WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $aggName = $row['aggregator'] ?: 'digiflazz';
            return $this->get($aggName);
        }

        return $this->activeAggregator;
    }

    public function purchase(string $sku, string $customerNo, string $refId, ?string $aggregatorName = null, bool $testing = false): array {
        $aggregator = $aggregatorName ? $this->get($aggregatorName) : $this->activeAggregator;

        if (!$aggregator) {
            return [
                'success' => false,
                'status' => 'Gagal',
                'rc' => '99',
                'message' => 'Aggregator tidak ditemukan'
            ];
        }

        return $aggregator->purchase($sku, $customerNo, $refId, $testing);
    }

    public function checkStatus(string $refId, ?string $aggregatorName = null): array {
        $aggregator = $aggregatorName ? $this->get($aggregatorName) : $this->activeAggregator;

        if (!$aggregator) {
            return [
                'success' => false,
                'status' => 'Gagal',
                'rc' => '99',
                'message' => 'Aggregator tidak ditemukan'
            ];
        }

        return $aggregator->checkStatus($refId);
    }
}

function getAggregatorManager(): AggregatorManager {
    return AggregatorManager::getInstance();
}

/**
 * Purchase pulsa - HIGH LEVEL FUNCTION
 */
function purchasePulsa(int $productId, string $customerNo, string $refId, bool $testing = false): array {
    error_log("purchasePulsa: productId=$productId, customerNo=$customerNo, refId=$refId, testing=$testing");

    $manager = getAggregatorManager();

    // Get product info
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT sku_code, kode_produk, harga_modal, harga_jual, aggregator FROM produk WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        error_log("purchasePulsa: Product not found!");
        return [
            'success' => false,
            'status' => 'Gagal',
            'rc' => '99',
            'message' => 'Produk tidak ditemukan'
        ];
    }

    $sku = $product['sku_code'] ?: $product['kode_produk'];

    if (empty($sku)) {
        error_log("purchasePulsa: SKU is empty for product ID $productId");
        return [
            'success' => false,
            'status' => 'Gagal',
            'rc' => '99',
            'message' => 'SKU produk belum disetting'
        ];
    }

    error_log("purchasePulsa: Using SKU=$sku, aggregator={$product['aggregator']}");

    // Get aggregator
    $aggregatorName = $product['aggregator'] ?: 'digiflazz';
    $aggregator = $manager->get($aggregatorName);

    if (!$aggregator) {
        error_log("purchasePulsa: Aggregator '$aggregatorName' not found!");
        return [
            'success' => false,
            'status' => 'Gagal',
            'rc' => '99',
            'message' => "Aggregator '$aggregatorName' tidak ditemukan"
        ];
    }

    // Execute purchase
    $result = $aggregator->purchase($sku, $customerNo, $refId, $testing);
    $result['product_price'] = floatval($product['harga_modal']);
    $result['selling_price'] = floatval($product['harga_jual']);

    error_log("purchasePulsa: Result - " . json_encode($result));

    return $result;
}
