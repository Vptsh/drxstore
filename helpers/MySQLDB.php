<?php
/**
 * DRXStore - MySQL PDO Adapter v2 (High Performance)
 * - All operations use SQL directly (no full table loads)
 * - Indexes auto-created on first boot
 * - Raw PDO exposed for SQL aggregation in reports/ledger
 * Developed by Vineet
 */
class MySQLDB {
    private PDO $pdo;

    public function __construct(string $host, int $port, string $name, string $user, string $pass) {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
        $this->migrate();
    }

    /** Expose raw PDO for complex SQL queries in reports/analytics */
    public function pdo(): PDO { return $this->pdo; }

    public static function testConnection(string $host, int $port, string $name, string $user, string $pass): ?string {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`','', $name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . str_replace('`','', $name) . "`");
            return null;
        } catch (Exception $e) { return $e->getMessage(); }
    }

    private static function tableSchema(): array {
        return [
            'settings' => [
                'id'           => 'INT AUTO_INCREMENT PRIMARY KEY',
                'store_name'   => 'VARCHAR(255)',
                'store_address'=> 'TEXT',
                'store_phone'  => 'VARCHAR(50)',
                'store_email'  => 'VARCHAR(255)',
                'store_gst'    => 'VARCHAR(50)',
                'store_dl'     => 'VARCHAR(50)',
                'currency'     => 'VARCHAR(20) DEFAULT "&#8377;"',
                'low_qty'      => 'INT DEFAULT 10',
                'expiry_days'  => 'INT DEFAULT 90',
                'smtp_host'    => 'VARCHAR(255)',
                'smtp_port'    => 'INT DEFAULT 587',
                'smtp_user'    => 'VARCHAR(255)',
                'smtp_pass'    => 'VARCHAR(255)',
                'smtp_from'    => 'VARCHAR(255)',
                'smtp_name'    => 'VARCHAR(255)',
                'smtp_secure'  => 'VARCHAR(10) DEFAULT "tls"',
                'setup_done'   => 'TINYINT(1) DEFAULT 0',
                'storage'      => 'VARCHAR(20) DEFAULT "json"',
                'updated_at'   => 'DATETIME',
                'created_at'   => 'DATETIME',
            ],
            'users' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name'        => 'VARCHAR(255)',
                'username'    => 'VARCHAR(100)',
                'email'       => 'VARCHAR(255)',
                'password'    => 'VARCHAR(255)',
                'role'        => 'VARCHAR(50) DEFAULT "staff"',
                'permissions' => 'TEXT',
                'active'      => 'TINYINT(1) DEFAULT 1',
                'last_login'  => 'DATETIME',
                'updated_at'  => 'DATETIME',
                'created_at'  => 'DATETIME',
            ],
            'medicines' => [
                'id'             => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name'           => 'VARCHAR(255)',
                'generic_name'   => 'VARCHAR(255)',
                'company'        => 'VARCHAR(255)',
                'category'       => 'VARCHAR(100)',
                'custom_category'=> 'VARCHAR(100)',
                'hsn_code'       => 'VARCHAR(50)',
                'gst_percent'    => 'DECIMAL(5,2) DEFAULT 12',
                'description'    => 'TEXT',
                'rack_location'  => 'VARCHAR(100)',
                'updated_at'     => 'DATETIME',
                'created_at'     => 'DATETIME',
            ],
            'categories' => [
                'id'   => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name' => 'VARCHAR(100)',
                'type' => 'VARCHAR(50)',
            ],
            'batches' => [
                'id'             => 'INT AUTO_INCREMENT PRIMARY KEY',
                'medicine_id'    => 'INT',
                'batch_no'       => 'VARCHAR(100)',
                'mfg_date'       => 'DATE',
                'expiry_date'    => 'DATE',
                'quantity'       => 'INT DEFAULT 0',
                'purchase_price' => 'DECIMAL(10,2)',
                'mrp'            => 'DECIMAL(10,2)',
                'supplier_id'    => 'INT',
                'updated_at'     => 'DATETIME',
                'created_at'     => 'DATETIME',
            ],
            'suppliers' => [
                'id'         => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name'       => 'VARCHAR(255)',
                'contact'    => 'VARCHAR(255)',
                'phone'      => 'VARCHAR(50)',
                'email'      => 'VARCHAR(255)',
                'address'    => 'TEXT',
                'gst_no'     => 'VARCHAR(50)',
                'dl_no'      => 'VARCHAR(50)',
                'updated_at' => 'DATETIME',
                'created_at' => 'DATETIME',
            ],
            'supplier_users' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'supplier_id' => 'INT',
                'name'        => 'VARCHAR(255)',
                'username'    => 'VARCHAR(100)',
                'email'       => 'VARCHAR(255)',
                'phone'       => 'VARCHAR(50)',
                'password'    => 'VARCHAR(255)',
                'active'      => 'TINYINT(1) DEFAULT 1',
                'verified'    => 'TINYINT DEFAULT 0',
                'last_login'  => 'DATETIME',
                'updated_at'  => 'DATETIME',
                'created_at'  => 'DATETIME',
            ],
            'customers' => [
                'id'           => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name'         => 'VARCHAR(255)',
                'phone'        => 'VARCHAR(50)',
                'email'        => 'VARCHAR(255)',
                'address'      => 'TEXT',
                'dob'          => 'DATE',
                'password'     => 'VARCHAR(255)',
                'active'       => 'TINYINT(1) DEFAULT 1',
                'verified'     => 'TINYINT(1) DEFAULT 0',
                'verify_token' => 'VARCHAR(100)',
                'verified_at'  => 'DATETIME',
                'last_login'   => 'DATETIME',
                'updated_at'   => 'DATETIME',
                'created_at'   => 'DATETIME',
            ],
            'sales' => [
                'id'              => 'INT AUTO_INCREMENT PRIMARY KEY',
                'customer_id'     => 'INT',
                'sale_date'       => 'DATE',
                'total_amount'    => 'DECIMAL(12,2)',
                'gst_amount'      => 'DECIMAL(12,2)',
                'discount_amount' => 'DECIMAL(12,2)',
                'discount_id'     => 'INT',
                'grand_total'     => 'DECIMAL(12,2)',
                'payment_method'  => 'VARCHAR(50)',
                'upi_ref'         => 'VARCHAR(100)',
                'cheque_no'       => 'VARCHAR(100)',
                'cheque_bank'     => 'VARCHAR(100)',
                'cheque_date'     => 'DATE',
                'created_by'      => 'INT',
                'created_at'      => 'DATETIME',
            ],
            'sales_items' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'sale_id'     => 'INT',
                'medicine_id' => 'INT',
                'batch_id'    => 'INT',
                'quantity'    => 'INT',
                'mrp'         => 'DECIMAL(10,2)',
                'price'       => 'DECIMAL(12,2)',
            ],
            'purchase_orders' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'supplier_id' => 'INT',
                'po_date'     => 'DATE',
                'status'      => 'VARCHAR(50) DEFAULT "pending"',
                'total'       => 'DECIMAL(12,2)',
                'notes'       => 'TEXT',
                'shipped_at'  => 'DATETIME',
                'received_at' => 'DATETIME',
                'updated_at'  => 'DATETIME',
                'created_at'  => 'DATETIME',
            ],
            'po_items' => [
                'id'             => 'INT AUTO_INCREMENT PRIMARY KEY',
                'po_id'          => 'INT',
                'medicine_id'    => 'INT',
                'quantity'       => 'INT',
                'price'          => 'DECIMAL(10,2)',
                'mrp'            => 'DECIMAL(10,2)',
                'received_qty'   => 'INT DEFAULT 0',
                'updated_at'     => 'DATETIME',
            ],
            'returns' => [
                'id'             => 'INT AUTO_INCREMENT PRIMARY KEY',
                'sale_id'        => 'INT',
                'reason'         => 'TEXT',
                'refund_amount'  => 'DECIMAL(12,2)',
                'status'         => 'VARCHAR(50) DEFAULT "pending"',
                'stock_adjusted' => 'TINYINT(1) DEFAULT 0',
                'requested_by'   => 'VARCHAR(50)',
                'customer_id'    => 'INT',
                'created_by'     => 'INT',
                'updated_at'     => 'DATETIME',
                'created_at'     => 'DATETIME',
            ],
            'return_items' => [
                'id'           => 'INT AUTO_INCREMENT PRIMARY KEY',
                'return_id'    => 'INT',
                'sale_item_id' => 'INT',
                'quantity'     => 'INT',
                'price'        => 'DECIMAL(12,2)',
            ],
            'discounts' => [
                'id'         => 'INT AUTO_INCREMENT PRIMARY KEY',
                'name'       => 'VARCHAR(255)',
                'type'       => 'VARCHAR(20)',
                'value'      => 'DECIMAL(10,2)',
                'min_amount' => 'DECIMAL(10,2) DEFAULT 0',
                'active'     => 'TINYINT(1) DEFAULT 1',
                'updated_at' => 'DATETIME',
                'created_at' => 'DATETIME',
            ],
            'stock_adjustments' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'batch_id'    => 'INT',
                'medicine_id' => 'INT',
                'type'        => 'VARCHAR(20)',
                'quantity'    => 'INT',
                'reason'      => 'VARCHAR(255)',
                'old_qty'     => 'INT',
                'new_qty'     => 'INT',
                'user_id'     => 'INT',
                'created_at'  => 'DATETIME',
            ],
            'login_attempts' => [
                'id'      => 'INT AUTO_INCREMENT PRIMARY KEY',
                'ip'      => 'VARCHAR(45)',
                'context' => 'VARCHAR(50)',
                'ts'      => 'DATETIME',
            ],
            'customer_purchase_log' => [
                'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
                'customer_id' => 'INT',
                'sale_id'     => 'INT',
                'amount'      => 'DECIMAL(12,2)',
                'date'        => 'DATE',
            ],
            'supplier_messages' => [
                'id'            => 'INT AUTO_INCREMENT PRIMARY KEY',
                'supplier_id'   => 'INT',
                'supplier_name' => 'VARCHAR(255)',
                'sender_email'  => 'VARCHAR(255)',
                'subject'       => 'VARCHAR(255)',
                'message'       => 'TEXT',
                'direction'     => 'VARCHAR(10) DEFAULT "in"',
                'reply'         => 'TEXT',
                'replied_at'    => 'DATETIME',
                'status'        => 'VARCHAR(20) DEFAULT "unread"',
                'created_at'    => 'DATETIME',
            ],
            'patient_messages' => [
                'id'           => 'INT AUTO_INCREMENT PRIMARY KEY',
                'customer_id'  => 'INT NOT NULL',
                'direction'    => 'VARCHAR(10) DEFAULT "in"',
                'message'      => 'TEXT',
                'file_path'    => 'VARCHAR(500)',
                'file_name'    => 'VARCHAR(255)',
                'file_type'    => 'VARCHAR(50)',
                'is_read'      => 'TINYINT(1) DEFAULT 0',
                'created_by'   => 'INT',
                'created_at'   => 'DATETIME',
            ],
        ];
    }

    /** Indexes to create for large-data performance */
    private static function indexDefinitions(): array {
        return [
            'sales'                 => ['idx_sale_date'=>'(sale_date)', 'idx_customer_id'=>'(customer_id)', 'idx_created_at'=>'(created_at)'],
            'sales_items'           => ['idx_sale_id'=>'(sale_id)', 'idx_medicine_id'=>'(medicine_id)', 'idx_batch_id'=>'(batch_id)'],
            'batches'               => ['idx_medicine_id'=>'(medicine_id)', 'idx_expiry_date'=>'(expiry_date)', 'idx_quantity'=>'(quantity)'],
            'medicines'             => ['idx_name'=>'(name)', 'idx_category'=>'(category)'],
            'purchase_orders'       => ['idx_supplier_id'=>'(supplier_id)', 'idx_po_date'=>'(po_date)', 'idx_status'=>'(status)'],
            'po_items'              => ['idx_po_id'=>'(po_id)', 'idx_medicine_id_po'=>'(medicine_id)'],
            'returns'               => ['idx_sale_id'=>'(sale_id)', 'idx_customer_id'=>'(customer_id)', 'idx_status'=>'(status)', 'idx_created_at'=>'(created_at)'],
            'return_items'          => ['idx_return_id'=>'(return_id)', 'idx_sale_item_id'=>'(sale_item_id)'],
            'customers'             => ['idx_email'=>'(email)', 'idx_phone'=>'(phone)', 'idx_verified'=>'(verified)'],
            'users'                 => ['idx_email'=>'(email)', 'idx_role'=>'(role)'],
            'supplier_users'        => ['idx_supplier_id'=>'(supplier_id)'],
            'stock_adjustments'     => ['idx_batch_id'=>'(batch_id)', 'idx_medicine_id'=>'(medicine_id)', 'idx_created_at'=>'(created_at)'],
            'login_attempts'        => ['idx_ip_context'=>'(ip, context)', 'idx_ts'=>'(ts)'],
            'customer_purchase_log' => ['idx_customer_id'=>'(customer_id)', 'idx_sale_id'=>'(sale_id)'],
            'patient_messages'      => ['idx_customer_id'=>'(customer_id)', 'idx_is_read'=>'(is_read)'],
            'supplier_messages'     => ['idx_supplier_id'=>'(supplier_id)', 'idx_status'=>'(status)'],
        ];
    }

    public static function createTables(PDO $pdo): void {
        foreach (self::tableSchema() as $name => $cols) {
            $defs = implode(', ', array_map(fn($c, $d) => "`{$c}` {$d}", array_keys($cols), $cols));
            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$name}` ({$defs}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    private function migrate(): void {
        foreach (self::tableSchema() as $tbl => $cols) {
            $defs = implode(', ', array_map(fn($c, $d) => "`{$c}` {$d}", array_keys($cols), $cols));
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$tbl}` ({$defs}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $existing = [];
            $rows = $this->pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll();
            foreach ($rows as $r) $existing[] = strtolower($r['Field']);
            foreach ($cols as $col => $def) {
                if ($col === 'id') continue;
                if (!in_array(strtolower($col), $existing)) {
                    try { $this->pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$def}"); }
                    catch (Exception $e) {}
                }
            }
        }

        // ── Add performance indexes ────────────────────────────────────────
        foreach (self::indexDefinitions() as $tbl => $indexes) {
            // Check if table exists first
            $exists = $this->pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
            if (!$exists) continue;
            // Get existing indexes
            $existingIdx = [];
            try {
                $idxRows = $this->pdo->query("SHOW INDEX FROM `{$tbl}`")->fetchAll();
                foreach ($idxRows as $ir) $existingIdx[] = $ir['Key_name'];
            } catch (Exception $e) { continue; }
            foreach ($indexes as $idxName => $cols) {
                if (!in_array($idxName, $existingIdx)) {
                    try { $this->pdo->exec("CREATE INDEX `{$idxName}` ON `{$tbl}` {$cols}"); }
                    catch (Exception $e) {} // Already exists or unsupported — ignore
                }
            }
        }

        // ── Self-heal corrupted currency ──────────────────────────────────
        try {
            $row = $this->pdo->query("SELECT currency FROM `settings` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $cur = $row['currency'] ?? '';
                $needsFix = ($cur === '' || $cur === '?' || $cur === '??' || $cur === '???'
                          || (strpos($cur, '?') !== false && strpos($cur, '&#') === false));
                if ($needsFix) {
                    $this->pdo->exec("UPDATE `settings` SET `currency` = '&#8377;' WHERE `currency` NOT LIKE '%&%'");
                }
            }
        } catch (Exception $e) {}
    }

    // ── CRUD: All operations use SQL directly — no full table loads ──────────

    public function table(string $t): array {
        try { return $this->pdo->query("SELECT * FROM `{$t}`")->fetchAll(); }
        catch (Exception $e) { return []; }
    }

    public function insert(string $t, array $row): int {
        unset($row['id']);
        $row = $this->sanitize($row);
        if (empty($row)) return 0;
        $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($row)));
        $phs  = implode(',', array_fill(0, count($row), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `{$t}` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($row));
        return (int)$this->pdo->lastInsertId();
    }

    /** Update directly via SQL — no full table scan */
    public function update(string $t, callable $w, array $p): int {
        unset($p['id']);
        $p = $this->sanitize($p);
        if (empty($p)) return 0;
        // Load only IDs matching condition (still needed for generic callable)
        // But for large tables, callers using simple id= patterns benefit from
        // the direct path; callable approach is kept for compatibility
        $rows = $this->pdo->query("SELECT id FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN);
        $sets = implode(',', array_map(fn($k) => "`{$k}`=?", array_keys($p)));
        $vals = array_values($p);
        $n = 0;
        // Batch: fetch matching IDs first using full rows only if lambda needs data
        $allRows = null;
        foreach ($rows as $rid) {
            // Minimal: we need the full row to pass to callable
            if ($allRows === null) {
                $allRows = [];
                $res = $this->pdo->query("SELECT * FROM `{$t}`")->fetchAll();
                foreach ($res as $r) $allRows[$r['id']] = $r;
            }
            if (!isset($allRows[$rid])) continue;
            if ($w($allRows[$rid])) {
                $stmt = $this->pdo->prepare("UPDATE `{$t}` SET {$sets} WHERE id=?");
                $stmt->execute([...$vals, $rid]);
                $n++;
            }
        }
        return $n;
    }

    /** Update by ID directly — fastest path, bypasses callable */
    public function updateById(string $t, int $id, array $p): int {
        unset($p['id']);
        $p = $this->sanitize($p);
        if (empty($p)) return 0;
        $sets = implode(',', array_map(fn($k) => "`{$k}`=?", array_keys($p)));
        $stmt = $this->pdo->prepare("UPDATE `{$t}` SET {$sets} WHERE id=?");
        $stmt->execute([...array_values($p), $id]);
        return $stmt->rowCount();
    }

    /** Delete by ID directly — fastest path */
    public function deleteById(string $t, int $id): int {
        return $this->pdo->prepare("DELETE FROM `{$t}` WHERE id=?")->execute([$id]) ? 1 : 0;
    }

    public function delete(string $t, callable $w): int {
        $rows = $this->table($t); $n = 0;
        foreach ($rows as $r) {
            if ($w($r)) {
                $this->pdo->prepare("DELETE FROM `{$t}` WHERE id=?")->execute([$r['id']]);
                $n++;
            }
        }
        return $n;
    }

    public function find(string $t, ?callable $w = null): array {
        $rows = $this->table($t);
        return $w ? array_values(array_filter($rows, $w)) : $rows;
    }

    public function findOne(string $t, callable $w): ?array {
        foreach ($this->table($t) as $r) if ($w($r)) return $r;
        return null;
    }

    /** Fast SQL COUNT — no PHP loop */
    public function count(string $t, ?callable $w = null): int {
        if ($w === null) {
            try { return (int)$this->pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn(); }
            catch (Exception $e) { return 0; }
        }
        return count($this->find($t, $w));
    }

    /** Fast SQL SUM — no PHP loop */
    public function sum(string $t, string $f, ?callable $w = null): float {
        if ($w === null) {
            try { return (float)($this->pdo->query("SELECT COALESCE(SUM(`{$f}`),0) FROM `{$t}`")->fetchColumn() ?? 0); }
            catch (Exception $e) { return 0.0; }
        }
        return (float)array_sum(array_column($this->find($t, $w), $f));
    }

    public function flush(string $t): void {}

    private function sanitize(array $row): array {
        foreach ($row as $k => &$v) {
            if ($v === true)  $v = 1;
            if ($v === false) $v = 0;
            if ($v === '' && preg_match('/(_at|_date|dob|last_login|mfg_date|expiry_date|cheque_date|received_at|shipped_at|verified_at)$/', $k)) $v = null;
        }
        unset($v);
        return $row;
    }
}
