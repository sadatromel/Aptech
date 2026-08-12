<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;

abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    private array $whereConditions = [];
    private string $orderBy = '';
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct()
    {
        $this->db = new Database(Container::get(PDO::class));
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function where(string $column, string $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->whereConditions[] = [$column, $operator, $value];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column " . strtoupper($direction);
        return $this;
    }

    public function take(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function skip(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($this->whereConditions)) {
            $whereClauses = [];
            foreach ($this->whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT ?";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET ?";
        }

        $stmt = $this->db->prepare($sql);
        
        $paramIndex = 1;
        
        // Bind WHERE params
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }

        // Bind LIMIT
        if ($this->limit !== null) {
            $stmt->bindValue($paramIndex++, $this->limit, PDO::PARAM_INT);
        }

        // Bind OFFSET
        if ($this->offset !== null) {
            $stmt->bindValue($paramIndex++, $this->offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fix #1: Always reset query state after execution to prevent state leaks
        // across reused model instances (e.g. via DI container singletons).
        $this->resetQuery();

        return $results;
    }

    public function first(): ?array
    {
        $this->take(1);
        $results = $this->get(); // get() resets state internally
        return $results[0] ?? null;
    }

    public function create(array $data): int
    {
        // Fix #8: Guard against silent failure when $fillable is not defined
        if (empty($this->fillable)) {
            throw new \LogicException(
                static::class . ' must define a non-empty $fillable array before calling create().'
            );
        }

        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        if (empty($filteredData)) {
            throw new \InvalidArgumentException(
                'No fillable fields found in the provided data for ' . static::class . '. ' .
                'Ensure the data keys match the $fillable property.'
            );
        }
        
        $columns = implode(', ', array_keys($filteredData));
        $placeholders = implode(', ', array_fill(0, count($filteredData), '?'));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($filteredData));
        
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        // Fix #8: Guard against silent failure when $fillable is not defined
        if (empty($this->fillable)) {
            throw new \LogicException(
                static::class . ' must define a non-empty $fillable array before calling update().'
            );
        }

        $filteredData = array_intersect_key($data, array_flip($this->fillable));

        if (empty($filteredData)) {
            throw new \InvalidArgumentException(
                'No fillable fields found in the provided data for ' . static::class . '. ' .
                'Ensure the data keys match the $fillable property.'
            );
        }
        
        $setClause = [];
        foreach (array_keys($filteredData) as $column) {
            $setClause[] = "$column = ?";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$this->table} SET $setClause WHERE {$this->primaryKey} = ?";
        $values = array_values($filteredData);
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Count total records with where conditions
        $countSql = "SELECT COUNT(*) FROM {$this->table}";
        $countParams = [];
        
        if (!empty($this->whereConditions)) {
            $whereClauses = [];
            foreach ($this->whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $countParams[] = $condition[2];
            }
            $countSql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
        
        // Get paginated results
        $this->limit = $perPage;
        $this->offset = $offset;
        $items = $this->get();
        
        // Reset query state
        $this->whereConditions = [];
        $this->orderBy = '';
        $this->limit = null;
        $this->offset = null;
        
        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];

        if (!empty($this->whereConditions)) {
            $whereClauses = [];
            foreach ($this->whereConditions as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = (int) $stmt->fetchColumn();

        // Fix #1: Reset query state after execution to prevent leaks
        $this->resetQuery();

        return $result;
    }

    public function exists(): bool
    {
        // count() resets state internally, so no extra reset needed here
        return $this->count() > 0;
    }

    protected function resetQuery(): void
    {
        $this->whereConditions = [];
        $this->orderBy = '';
        $this->limit = null;
        $this->offset = null;
    }
}
