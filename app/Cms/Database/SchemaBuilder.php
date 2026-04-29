<?php

declare(strict_types=1);

namespace App\Cms\Database;

/**
 * SchemaBuilder — Converts MLC table definitions to SQL statements.
 *
 * Reads `.mlc` migration files and generates CREATE TABLE, INDEX,
 * FOREIGN KEY, and INSERT (seed) SQL.
 */
final class SchemaBuilder
{
    /**
     * Parse an MLC migration file and generate SQL statements
     *
     * @return array{tables: string[], seeds: string[], rollbacks: string[]}
     */
    public function parse(string $mlcContent): array
    {
        $tables = [];
        $seeds = [];
        $rollbacks = [];

        // Parse table blocks (supports nested { } for column definitions)
        preg_match_all('/^table\s+"([^"]+)"\s*\{((?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*)\}/ms', $mlcContent, $tableMatches, PREG_SET_ORDER);

        foreach ($tableMatches as $match) {
            $tableName = $match[1];
            $body = $match[2];
            $tables[] = $this->buildCreateTable($tableName, $body);
        }

        // Parse seed blocks
        preg_match_all('/seed\s+"([^"]+)"\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $mlcContent, $seedMatches, PREG_SET_ORDER);

        foreach ($seedMatches as $match) {
            $tableName = $match[1];
            $body = $match[2];
            $seeds = array_merge($seeds, $this->buildSeedStatements($tableName, $body));
        }

        // Parse rollback blocks
        preg_match_all('/rollback\s+"([^"]+)"\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $mlcContent, $rollbackMatches, PREG_SET_ORDER);

        foreach ($rollbackMatches as $match) {
            $body = $match[2];
            if (preg_match('/drop_table\s*=\s*"([^"]+)"/', $body, $m)) {
                $rollbacks[] = "DROP TABLE IF EXISTS `{$m[1]}`;";
            }
        }

        return ['tables' => $tables, 'seeds' => $seeds, 'rollbacks' => $rollbacks];
    }

    /**
     * Build a complete CREATE TABLE statement from an MLC block body
     */
    private function buildCreateTable(string $tableName, string $body): string
    {
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        $primaryKey = null;
        $compositePrimary = null;

        // Check for composite primary key
        if (preg_match('/primary_key\s*=\s*\[(.*?)\]/', $body, $pkMatch)) {
            preg_match_all('/"([^"]+)"/', $pkMatch[1], $pkCols);
            $compositePrimary = $pkCols[1];
        }

        // Parse column definitions: name = { ... }
        preg_match_all('/^\s*(\w+)\s*=\s*\{([^}]+)\}/m', $body, $colMatches, PREG_SET_ORDER);

        foreach ($colMatches as $col) {
            $colName = $col[1];
            $colBody = $col[2];
            $def = $this->parseColumnDef($colBody);

            $sql = "`{$colName}` " . $this->columnTypeToSql($def);

            // Unsigned
            if (!empty($def['unsigned']) || $def['type'] === 'bigint') {
                if ($def['type'] !== 'bigint' || !empty($def['unsigned'])) {
                    $sql .= ' UNSIGNED';
                }
            }

            // Nullable
            if (isset($def['nullable']) && $def['nullable'] === 'true') {
                $sql .= ' NULL';
            } else {
                $sql .= ' NOT NULL';
            }

            // Auto increment
            if (!empty($def['auto_increment']) && $def['auto_increment'] === 'true') {
                $sql .= ' AUTO_INCREMENT';
            }

            // Default
            if (isset($def['default'])) {
                $sql .= ' DEFAULT ' . $this->formatDefault($def['default'], $def['type'] ?? 'string');
            }

            // On update timestamp
            if (!empty($def['on_update']) && $def['on_update'] === 'true') {
                $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
            }

            $columns[] = $sql;

            // Primary key
            if (!empty($def['primary']) && $def['primary'] === 'true') {
                $primaryKey = $colName;
            }

            // Unique (single column)
            if (!empty($def['unique']) && $def['unique'] === 'true') {
                $indexes[] = "UNIQUE KEY `idx_{$tableName}_{$colName}` (`{$colName}`)";
            }

            // Foreign key
            if (!empty($def['references'])) {
                $parts = explode('.', $def['references']);
                $refTable = $parts[0];
                $refCol = $parts[1] ?? 'id';
                $onDelete = strtoupper($def['on_delete'] ?? 'RESTRICT');
                $foreignKeys[] = "CONSTRAINT `fk_{$tableName}_{$colName}` FOREIGN KEY (`{$colName}`) REFERENCES `{$refTable}` (`{$refCol}`) ON DELETE {$onDelete}";
            }
        }

        // Primary key
        if ($compositePrimary) {
            $pkCols = implode('`, `', $compositePrimary);
            $columns[] = "PRIMARY KEY (`{$pkCols}`)";
        } elseif ($primaryKey) {
            $columns[] = "PRIMARY KEY (`{$primaryKey}`)";
        }

        // Parse named indexes
        preg_match_all('/^\s*index\s+"([^"]+)"\s*=\s*\[(.*?)\]/m', $body, $idxMatches, PREG_SET_ORDER);
        foreach ($idxMatches as $idx) {
            $idxName = $idx[1];
            preg_match_all('/"([^"]+)"/', $idx[2], $idxCols);
            $idxColStr = implode('`, `', $idxCols[1]);
            $indexes[] = "KEY `{$idxName}` (`{$idxColStr}`)";
        }

        // Parse unique indexes
        preg_match_all('/^\s*unique\s+"([^"]+)"\s*=\s*\[(.*?)\]/m', $body, $uniqMatches, PREG_SET_ORDER);
        foreach ($uniqMatches as $idx) {
            $idxName = $idx[1];
            preg_match_all('/"([^"]+)"/', $idx[2], $idxCols);
            $idxColStr = implode('`, `', $idxCols[1]);
            $indexes[] = "UNIQUE KEY `{$idxName}` (`{$idxColStr}`)";
        }

        // Parse fulltext indexes
        preg_match_all('/^\s*fulltext\s+"([^"]+)"\s*=\s*\[(.*?)\]/m', $body, $ftMatches, PREG_SET_ORDER);
        foreach ($ftMatches as $idx) {
            $idxName = $idx[1];
            preg_match_all('/"([^"]+)"/', $idx[2], $idxCols);
            $idxColStr = implode('`, `', $idxCols[1]);
            $indexes[] = "FULLTEXT KEY `{$idxName}` (`{$idxColStr}`)";
        }

        $allParts = array_merge($columns, $indexes, $foreignKeys);
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n    "
            . implode(",\n    ", $allParts)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $sql;
    }

    /**
     * Build INSERT statements from seed blocks
     */
    private function buildSeedStatements(string $tableName, string $body): array
    {
        $stmts = [];

        // Match each row: { key = value, key = value, ... }
        preg_match_all('/\{\s*((?:[^{}])+)\}/', $body, $rows);

        foreach ($rows[1] as $row) {
            $fields = [];
            // Parse key = value pairs (both quoted and unquoted values)
            preg_match_all('/(\w+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|\S+)/', $row, $pairs, PREG_SET_ORDER);

            foreach ($pairs as $pair) {
                $key = $pair[1];
                $val = $pair[2];
                // Strip outer quotes
                if ((str_starts_with($val, '"') && str_ends_with($val, '"'))
                    || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $fields[$key] = $val;
            }

            if ($fields) {
                $cols = implode('`, `', array_keys($fields));
                $vals = implode(', ', array_map(fn($v) => $this->quoteValue($v), array_values($fields)));
                $stmts[] = "INSERT INTO `{$tableName}` (`{$cols}`) VALUES ({$vals});";
            }
        }

        return $stmts;
    }

    // ── Type Mapping ────────────────────────────────────────────────────

    private function columnTypeToSql(array $def): string
    {
        $type = $def['type'] ?? 'string';
        $length = $def['length'] ?? null;

        return match ($type) {
            'bigint'    => 'BIGINT UNSIGNED',
            'integer', 'int' => !empty($def['unsigned']) ? 'INT UNSIGNED' : 'INT',
            'string'    => 'VARCHAR(' . ($length ?: 255) . ')',
            'text'      => 'TEXT',
            'longtext'  => 'LONGTEXT',
            'boolean', 'bool' => 'TINYINT(1)',
            'float'     => 'FLOAT',
            'double'    => 'DOUBLE',
            'decimal'   => 'DECIMAL(' . ($def['precision'] ?? 10) . ',' . ($def['scale'] ?? 2) . ')',
            'json'      => 'JSON',
            'timestamp', 'datetime' => 'DATETIME',
            'date'      => 'DATE',
            'time'      => 'TIME',
            'binary'    => 'BLOB',
            'enum'      => $this->buildEnum($def),
            default     => 'VARCHAR(255)',
        };
    }

    private function buildEnum(array $def): string
    {
        $values = [];
        if (!empty($def['values'])) {
            // values is already parsed as an array string like: "completed", "failed", "rolled_back"
            if (is_string($def['values'])) {
                preg_match_all('/"([^"]+)"/', $def['values'], $m);
                $values = $m[1] ?? [];
            }
        }

        $quoted = implode("','", $values);
        return "ENUM('{$quoted}')";
    }

    private function formatDefault(string $value, string $type): string
    {
        if ($value === 'CURRENT_TIMESTAMP') return 'CURRENT_TIMESTAMP';
        if ($value === 'true') return '1';
        if ($value === 'false') return '0';
        if (in_array($type, ['integer', 'int', 'bigint', 'float', 'double', 'decimal', 'boolean', 'bool'])) {
            return $value;
        }
        // JSON defaults need special handling
        if ($type === 'json') {
            return "('" . addslashes($value) . "')";
        }
        return "'" . addslashes($value) . "'";
    }

    private function quoteValue(string $value): string
    {
        if ($value === 'true') return '1';
        if ($value === 'false') return '0';
        if ($value === 'null') return 'NULL';
        if (is_numeric($value)) return $value;
        // JSON-like values
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            return "'" . addslashes($value) . "'";
        }
        return "'" . addslashes($value) . "'";
    }

    /**
     * Parse a column definition body: key = value pairs inside { }
     */
    private function parseColumnDef(string $body): array
    {
        $def = [];

        // Handle values array for enum: values = ["a", "b"]
        if (preg_match('/values\s*=\s*\[(.*?)\]/', $body, $arrMatch)) {
            $def['values'] = $arrMatch[1];
        }

        // Tokenize: split on commas first, then parse each token
        $tokens = preg_split('/,\s*/', $body);

        foreach ($tokens as $token) {
            $token = trim($token);
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $token, $m)) {
                $key = $m[1];
                $val = trim(trim($m[2]), '"');
                if ($key !== 'values') {
                    $def[$key] = $val;
                }
            }
        }

        return $def;
    }
}
