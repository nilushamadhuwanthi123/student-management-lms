<?php
/**
 * BaseModel — Abstract base for all models
 * Provides generic CRUD, query, and pagination using PDO prepared statements.
 */

abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ─── Raw query helper ────────────────────────────────────────────

    /**
     * Execute an arbitrary SQL statement with bound parameters.
     *
     * @param  string $sql    SQL with named or positional placeholders
     * @param  array  $params Values to bind
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // ─── Read ────────────────────────────────────────────────────────

    /**
     * Find a single record by primary key.
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->query($sql, [':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find the first record where $column = $value.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $column = $this->sanitizeColumn($column);
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = :val LIMIT 1";
        $stmt = $this->query($sql, [':val' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Return every row, optionally ordered.
     */
    public function all(string $orderBy = 'id ASC'): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy);
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        return $this->query($sql)->fetchAll();
    }

    // ─── Write ───────────────────────────────────────────────────────

    /**
     * Insert a new record. Only $fillable columns are written.
     *
     * @return int  The last-insert ID.
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            throw new InvalidArgumentException('No fillable data provided for insert.');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $params = [];
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }
        $this->query($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a record by primary key. Only $fillable columns are written.
     *
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            return false;
        }

        $setParts = [];
        $params = [':pk_id' => $id];
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts)
             . " WHERE {$this->primaryKey} = :pk_id";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->query($sql, [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ─── Conditional queries ─────────────────────────────────────────

    /**
     * Select rows matching associative conditions (AND).
     *
     * @param  array  $conditions  ['column' => value, ...]
     * @param  string $orderBy     e.g. "created_at DESC"
     * @return array
     */
    public function where(array $conditions, string $orderBy = 'id ASC'): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy);
        [$clause, $params] = $this->buildWhereClause($conditions);

        $sql = "SELECT * FROM {$this->table} WHERE {$clause} ORDER BY {$orderBy}";
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Count rows matching conditions.
     */
    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}";
            return (int) $this->query($sql)->fetch()['cnt'];
        }
        [$clause, $params] = $this->buildWhereClause($conditions);
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$clause}";
        return (int) $this->query($sql, $params)->fetch()['cnt'];
    }

    /**
     * Check whether at least one matching row exists.
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    // ─── Pagination ──────────────────────────────────────────────────

    /**
     * Paginated select.
     *
     * @return array{data: array, total: int, pages: int, current_page: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 15,
        array $conditions = [],
        string $orderBy = 'id ASC'
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;
        $orderBy = $this->sanitizeOrderBy($orderBy);

        if (!empty($conditions)) {
            [$clause, $params] = $this->buildWhereClause($conditions);
            $where = "WHERE {$clause}";
        } else {
            $where = '';
            $params = [];
        }

        $countSql = "SELECT COUNT(*) AS cnt FROM {$this->table} {$where}";
        $total = (int) $this->query($countSql, $params)->fetch()['cnt'];

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;
        $dataSql = "SELECT * FROM {$this->table} {$where} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
        $data = $this->query($dataSql, $params)->fetchAll();

        return [
            'data'         => $data,
            'total'        => $total,
            'pages'        => (int) ceil($total / $perPage),
            'current_page' => $page,
        ];
    }

    // ─── Internal helpers ────────────────────────────────────────────

    /**
     * Build a WHERE clause from an associative array (AND logic).
     *
     * @return array{0: string, 1: array}  [clause, params]
     */
    protected function buildWhereClause(array $conditions): array
    {
        $parts  = [];
        $params = [];
        $i = 0;
        foreach ($conditions as $col => $val) {
            $col = $this->sanitizeColumn($col);
            $placeholder = ":w{$i}";
            if ($val === null) {
                $parts[] = "{$col} IS NULL";
            } else {
                $parts[] = "{$col} = {$placeholder}";
                $params[$placeholder] = $val;
            }
            $i++;
        }
        return [implode(' AND ', $parts), $params];
    }

    /**
     * Keep only columns listed in $fillable.
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Basic column-name whitelist to prevent SQL injection in dynamic column references.
     */
    protected function sanitizeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }
        return $column;
    }

    /**
     * Sanitize an ORDER BY expression (column + direction only).
     */
    protected function sanitizeOrderBy(string $orderBy): string
    {
        $parts = array_map('trim', explode(',', $orderBy));
        $safe = [];
        foreach ($parts as $part) {
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_.]*)\s*(ASC|DESC)?$/i', $part, $m)) {
                $safe[] = $m[1] . ' ' . strtoupper($m[2] ?? 'ASC');
            } else {
                throw new InvalidArgumentException("Invalid ORDER BY clause: {$part}");
            }
        }
        return implode(', ', $safe);
    }
}
