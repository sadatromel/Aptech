<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function select(string $table, mixed $join = null, mixed $columns = '*', mixed $where = []): array
    {
        [$tableName, $joins, $columnList, $options] = $this->resolveSelectParams($table, $join, $columns, $where);
        $query = $this->compileSelect($tableName, $joins, $columnList, $options);

        return $this->execute($query['sql'], $query['params'])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(string $table, mixed $join = null, mixed $columns = '*', mixed $where = []): ?array
    {
        [$tableName, $joins, $columnList, $options] = $this->resolveSelectParams($table, $join, $columns, $where);
        $options['limit'] = $options['limit'] ?? 1;

        $query = $this->compileSelect($tableName, $joins, $columnList, $options);
        $statement = $this->execute($query['sql'], $query['params']);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Insert data must not be empty.');
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', $columns);

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $columnList, $placeholders);
        $this->execute($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Update data must not be empty.');
        }

        // Safety guard: prevent accidental full-table UPDATE when no WHERE clause is supplied.
        // delete() already enforces this — update() must do the same.
        if (empty($where)) {
            throw new InvalidArgumentException(
                'Update operation requires a WHERE clause to prevent unintended full-table updates. ' .
                "Table: {$table}"
            );
        }

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s', $table, implode(', ', $setParts));

        $whereClause = $this->buildConditionString($where);

        // Secondary guard: $where was non-empty but compiled to no SQL (e.g. all invalid keys).
        // Still refuse to run without a WHERE clause.
        if (empty($whereClause['sql'])) {
            throw new InvalidArgumentException(
                'Update operation WHERE clause compiled to an empty string. ' .
                "Table: {$table} — check your condition keys."
            );
        }

        $sql .= ' WHERE ' . $whereClause['sql'];
        $params = array_merge($params, $whereClause['params']);

        $statement = $this->execute($sql, $params);
        return $statement->rowCount() > 0;
    }

    public function delete(string $table, array $where): bool
    {
        if (empty($where)) {
            throw new InvalidArgumentException('Delete operation requires a WHERE clause.');
        }

        $whereClause = $this->buildConditionString($where);
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $whereClause['sql']);

        $statement = $this->execute($sql, $whereClause['params']);
        return $statement->rowCount() > 0;
    }

    public function count(string $table, array $where = []): int
    {
        return (int) $this->aggregate('COUNT', $table, '*', $where);
    }

    public function sum(string $table, string $column, array $where = []): float|int
    {
        return $this->aggregate('SUM', $table, $column, $where);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        return $this->execute($sql, $params);
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    private function resolveSelectParams(string $table, mixed $join, mixed $columns, mixed $where): array
    {
        $joins = [];
        $columnList = '*';
        $options = [];

        if (is_array($join) && $this->isJoinDefinition($join)) {
            $joins = $join;
            $columnList = $columns ?? '*';
            $options = is_array($where) ? $where : [];
        } else {
            $columnList = $join ?? '*';
            $options = is_array($columns) ? $columns : [];
        }

        return [
            $table,
            $joins,
            $columnList,
            $this->normalizeQueryOptions($options),
        ];
    }

    private function compileSelect(string $table, array $joins, mixed $columns, array $options): array
    {
        $sql = 'SELECT ' . $this->buildColumnList($columns) . ' FROM ' . $table;
        $params = [];

        foreach ($joins as $pattern => $relation) {
            $sql .= ' ' . $this->buildJoinClause($pattern, $relation);
        }

        if (!empty($options['where'])) {
            $whereClause = $this->buildConditionString($options['where']);
            if ($whereClause['sql']) {
                $sql .= ' WHERE ' . $whereClause['sql'];
                $params = array_merge($params, $whereClause['params']);
            }
        }

        if (!empty($options['group'])) {
            $sql .= ' ' . $this->buildGroupClause($options['group']);
        }

        if (!empty($options['having'])) {
            $havingClause = $this->buildConditionString($options['having']);
            if ($havingClause['sql']) {
                $sql .= ' HAVING ' . $havingClause['sql'];
                $params = array_merge($params, $havingClause['params']);
            }
        }

        if (!empty($options['order'])) {
            $sql .= ' ' . $this->buildOrderClause($options['order']);
        }

        if ($options['limit'] !== null || $options['offset'] !== null) {
            $sql .= ' ' . $this->buildLimitClause($options['limit'], $options['offset']);
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function normalizeQueryOptions(array $options): array
    {
        $normalized = [
            'where' => [],
            'order' => [],
            'limit' => null,
            'offset' => null,
            'group' => [],
            'having' => [],
        ];

        if (empty($options)) {
            return $normalized;
        }

        $upperKeys = [];
        foreach ($options as $key => $value) {
            if (is_string($key)) {
                $upperKeys[strtoupper($key)] = $value;
            } else {
                $upperKeys[$key] = $value;
            }
        }

        if (isset($upperKeys['WHERE']) && is_array($upperKeys['WHERE']) && !empty($upperKeys['WHERE'])) {
            $normalized['where'] = $upperKeys['WHERE'];
        } elseif (!isset($upperKeys['WHERE'])) {
            // Only process where candidates if WHERE key is not explicitly set
            $whereCandidates = $options;
            unset(
                $whereCandidates['ORDER'],
                $whereCandidates['LIMIT'],
                $whereCandidates['GROUP'],
                $whereCandidates['HAVING'],
                $whereCandidates['OFFSET']
            );

            if (!empty($whereCandidates)) {
                $normalized['where'] = $whereCandidates;
            }
        }
        // If WHERE is explicitly set but empty, don't set it at all

        if (isset($upperKeys['ORDER'])) {
            $normalized['order'] = $upperKeys['ORDER'];
        }

        if (isset($upperKeys['GROUP'])) {
            $normalized['group'] = $upperKeys['GROUP'];
        }

        if (isset($upperKeys['HAVING']) && is_array($upperKeys['HAVING'])) {
            $normalized['having'] = $upperKeys['HAVING'];
        }

        if (isset($upperKeys['LIMIT'])) {
            $normalized['limit'] = $upperKeys['LIMIT'];
        }

        if (isset($upperKeys['OFFSET'])) {
            $normalized['offset'] = (int) $upperKeys['OFFSET'];
        }

        return $normalized;
    }

    private function isJoinDefinition(array $candidate): bool
    {
        foreach (array_keys($candidate) as $key) {
            if (is_string($key) && str_starts_with($key, '[')) {
                return true;
            }
        }

        return false;
    }

    private function buildColumnList(mixed $columns): string
    {
        if ($columns === null || $columns === '*') {
            return '*';
        }

        if (is_string($columns)) {
            return $columns;
        }

        $parts = [];
        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                $parts[] = $column;
            } else {
                $parts[] = sprintf('%s AS %s', $column, $alias);
            }
        }

        return implode(', ', $parts);
    }

    private function buildJoinClause(string $pattern, mixed $relations): string
    {
        [$symbol, $table, $alias] = $this->parseJoinPattern($pattern);
        $joinType = match ($symbol) {
            '>' => 'LEFT JOIN',
            '<' => 'RIGHT JOIN',
            '<>' => 'FULL OUTER JOIN',
            default => 'INNER JOIN',
        };

        $tableSql = $alias ? "{$table} {$alias}" : $table;

        $conditions = [];
        if (is_array($relations)) {
            foreach ($relations as $left => $right) {
                $rightColumn = str_contains((string) $right, '.') ? $right : ($alias ? "{$alias}.{$right}" : $right);
                $conditions[] = "{$left} = {$rightColumn}";
            }
        } elseif (is_string($relations)) {
            $column = $alias ? "{$alias}.{$relations}" : $relations;
            $conditions[] = "{$column}";
        } else {
            throw new InvalidArgumentException('Invalid join relation definition.');
        }

        $onClause = implode(' AND ', $conditions);
        return "{$joinType} {$tableSql} ON {$onClause}";
    }

    private function parseJoinPattern(string $pattern): array
    {
        $pattern = trim($pattern);
        $parts = preg_split('/\s+/', $pattern);
        $target = $parts[0] ?? '';
        $alias = $parts[1] ?? null;

        if (!preg_match('/^\[([^\]]+)\](.+)$/', $target, $matches)) {
            return ['><', $target, $alias];
        }

        return [
            $matches[1],
            trim($matches[2]),
            $alias,
        ];
    }

    private function buildConditionString(array $conditions, string $glue = 'AND'): array
    {
        $clauses = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_string($key) && strtoupper($key) === 'OR') {
                $sub = $this->buildConditionString((array) $value, 'OR');
                if ($sub['sql']) {
                    $clauses[] = '(' . $sub['sql'] . ')';
                    $params = array_merge($params, $sub['params']);
                }
                continue;
            }

            if (is_string($key) && strtoupper($key) === 'AND') {
                $sub = $this->buildConditionString((array) $value, 'AND');
                if ($sub['sql']) {
                    $clauses[] = '(' . $sub['sql'] . ')';
                    $params = array_merge($params, $sub['params']);
                }
                continue;
            }

            if (is_int($key) && is_array($value)) {
                $sub = $this->buildConditionString($value, 'AND');
                if ($sub['sql']) {
                    $clauses[] = '(' . $sub['sql'] . ')';
                    $params = array_merge($params, $sub['params']);
                }
                continue;
            }

            [$column, $operator] = $this->extractColumnOperator((string) $key);

            if ($value === null) {
                $clauses[] = $operator === '<>' ? "{$column} IS NOT NULL" : "{$column} IS NULL";
                continue;
            }

            if (is_array($value)) {
                if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true) && count($value) === 2) {
                    $clauses[] = sprintf('%s %s ? AND ?', $column, $operator);
                    $params[] = $value[0];
                    $params[] = $value[1];
                    continue;
                }

                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $comparison = in_array($operator, ['<>', '!='], true) ? 'NOT IN' : 'IN';
                $clauses[] = sprintf('%s %s (%s)', $column, $comparison, $placeholders);
                $params = array_merge($params, array_values($value));
                continue;
            }

            if (in_array($operator, ['LIKE', 'NOT LIKE'], true) && !str_contains((string) $value, '%')) {
                $value = '%' . $value . '%';
            }

            $clauses[] = sprintf('%s %s ?', $column, $operator);
            $params[] = $value;
        }

        return [
            'sql' => implode(" {$glue} ", array_filter($clauses)),
            'params' => $params,
        ];
    }

    private function extractColumnOperator(string $key): array
    {
        if (preg_match('/(.*)\[([^\]]+)\]$/', $key, $matches)) {
            $column = $matches[1];
            $operator = strtoupper($matches[2]);
        } else {
            $column = $key;
            $operator = '=';
        }

        // Fix #5: '<>' was previously mapped to BETWEEN here AND FULL OUTER JOIN
        // in buildJoinClause — causing silent ambiguity. Now '<>' is exclusively
        // the FULL OUTER JOIN indicator. BETWEEN uses the dedicated key '>|<'.
        $mapped = match ($operator) {
            '>'        => '>',
            '<'        => '<',
            '>='       => '>=',
            '<='       => '<=',
            '!'        => '<>',
            '!='       => '<>',
            '>|<'      => 'BETWEEN',      // Dedicated BETWEEN operator (was '<>')
            '!>|<'     => 'NOT BETWEEN',  // Dedicated NOT BETWEEN (was '><')
            '~'        => 'LIKE',
            '!~'       => 'NOT LIKE',
            default    => '=',
        };

        return [$column, $mapped];
    }

    private function buildOrderClause(mixed $order): string
    {
        if (is_string($order)) {
            return 'ORDER BY ' . $order;
        }

        $parts = [];
        foreach ((array) $order as $column => $direction) {
            if (is_int($column)) {
                $parts[] = $direction;
                continue;
            }

            $dir = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';
            $parts[] = "{$column} {$dir}";
        }

        return 'ORDER BY ' . implode(', ', $parts);
    }

    private function buildGroupClause(mixed $group): string
    {
        if (is_string($group)) {
            return 'GROUP BY ' . $group;
        }

        if (is_array($group) && !empty($group)) {
            return 'GROUP BY ' . implode(', ', $group);
        }

        return '';
    }

    private function buildLimitClause(mixed $limit, mixed $offset): string
    {
        $limitValue = null;
        $offsetValue = $offset;

        if (is_array($limit)) {
            $values = array_values($limit);
            if (count($values) === 2) {
                $offsetValue = $values[0];
                $limitValue = $values[1];
            } elseif (count($values) === 1) {
                $limitValue = $values[0];
            }
        } elseif ($limit !== null) {
            $limitValue = $limit;
        }

        // Detect PDO driver dynamically to support multi-database structures
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlsrv') {
            $clauses = [];
            if ($offsetValue !== null || $limitValue !== null) {
                $offsetRows = ($offsetValue !== null) ? (int)$offsetValue : 0;
                $clauses[] = "OFFSET {$offsetRows} ROWS";
                
                if ($limitValue !== null) {
                    $clauses[] = "FETCH NEXT " . (int)$limitValue . " ROWS ONLY";
                }
            }
            return implode(' ', $clauses);
        } else {
            $clauses = [];
            if ($limitValue !== null) {
                $clauses[] = 'LIMIT ' . (int) $limitValue;
            }

            if ($offsetValue !== null) {
                if ($limitValue === null) {
                    $clauses[] = 'LIMIT 18446744073709551615';
                }
                $clauses[] = 'OFFSET ' . (int) $offsetValue;
            }

            return implode(' ', $clauses);
        }
    }

    private function aggregate(string $function, string $table, string $column, array $where): float|int
    {
        $columns = sprintf('%s(%s) AS aggregate', strtoupper($function), $column === '*' ? '*' : $column);
        $query = $this->compileSelect(
            $table,
            [],
            $columns,
            $this->normalizeQueryOptions(['WHERE' => $where])
        );

        $statement = $this->execute($query['sql'], $query['params']);
        $value = $statement->fetchColumn();

        return $value === null ? 0 : (float) $value;
    }

    private function execute(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($params));

        return $statement;
    }
}
