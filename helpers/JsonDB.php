<?php
/**
 * DRXStore - JsonDB Engine
 * Developed by Vineet | psvineet@zohomail.in
 */
class JsonDB {
    private string $dir;
    private array $cache = [];
    public function __construct(string $dir) {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir)) mkdir($this->dir, 0755, true);
    }
    private function path(string $t): string { return "{$this->dir}/{$t}.json"; }
    public function table(string $t): array {
        if (isset($this->cache[$t])) return $this->cache[$t];
        $p = $this->path($t);
        if (!file_exists($p)) return $this->cache[$t] = [];
        $d = json_decode(file_get_contents($p), true);
        return $this->cache[$t] = (is_array($d) ? $d : []);
    }
    private function save(string $t): void {
        $tmp = $this->path($t) . '.tmp';
        file_put_contents($tmp, json_encode(array_values($this->cache[$t]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, $this->path($t));
    }
    public function insert(string $t, array $row): int {
        $rows = $this->table($t); $max = 0;
        foreach ($rows as $r) if (($r['id'] ?? 0) > $max) $max = $r['id'];
        $row['id'] = $max + 1; $rows[] = $row;
        $this->cache[$t] = $rows; $this->save($t); return $row['id'];
    }
    public function update(string $t, callable $w, array $p): int {
        $rows = $this->table($t); $n = 0;
        foreach ($rows as &$r) { if ($w($r)) { $r = array_merge($r, $p); $n++; } }
        unset($r); $this->cache[$t] = $rows; if ($n) $this->save($t); return $n;
    }
    public function delete(string $t, callable $w): int {
        $rows = $this->table($t); $b = count($rows);
        $rows = array_values(array_filter($rows, fn($r) => !$w($r)));
        $this->cache[$t] = $rows; $d = $b - count($rows); if ($d) $this->save($t); return $d;
    }
    public function find(string $t, ?callable $w = null): array {
        $rows = $this->table($t);
        return $w ? array_values(array_filter($rows, $w)) : $rows;
    }
    public function findOne(string $t, callable $w): ?array {
        foreach ($this->table($t) as $r) if ($w($r)) return $r; return null;
    }
    public function sum(string $t, string $f, ?callable $w = null): float {
        return (float) array_sum(array_column($this->find($t, $w), $f));
    }
    public function count(string $t, ?callable $w = null): int { return count($this->find($t, $w)); }
    public function updateById(string $t, int $id, array $p): int {
        return $this->update($t, fn($r) => (int)($r['id']??0) === $id, $p);
    }
    public function deleteById(string $t, int $id): int {
        return $this->delete($t, fn($r) => (int)($r['id']??0) === $id);
    }
    public function flush(string $t): void { unset($this->cache[$t]); }
}
