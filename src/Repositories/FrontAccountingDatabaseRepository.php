<?php

declare(strict_types=1);

namespace AmazonInvoices\Repositories;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * FrontAccounting Database Repository Implementation
 * 
 * Wraps FrontAccounting database functions to implement the DatabaseRepositoryInterface
 * Provides consistent interface for database operations in FA environment
 * 
 * @package AmazonInvoices\Repositories
 * @author  Your Name
 * @since   1.0.0
 */
class FrontAccountingDatabaseRepository implements DatabaseRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = [])
    {
        // Simple parameter binding for FA (FA doesn't have prepared statements)
        if (!empty($params)) {
            $query = $this->bindParameters($query, $params);
        }

        $result = db_query($query, "Database query failed");
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($result): ?array
    {
        $row = db_fetch($result);
        return $row ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($result): array
    {
        $rows = [];
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function getAffectedRows(): int
    {
        return db_num_affected_rows();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastInsertId(): int
    {
        return (int) db_insert_id();
    }

    /**
     * {@inheritdoc}
     */
    public function getTablePrefix(): string
    {
        return TB_PREF;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        begin_transaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        commit_transaction();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        rollback_transaction();
    }

    /**
     * {@inheritdoc}
     */
    public function escape(string $value): string
    {
        return db_escape($value);
    }

    /**
     * {@inheritdoc}
     */
    public function tableExists(string $tableName): bool
    {
        $query = "SHOW TABLES LIKE " . db_escape($tableName);
        $result = db_query($query);
        return db_fetch($result) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): array
    {
        return [
            'code' => db_error_no(),
            'message' => db_error_msg()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionInfo(): array
    {
        return [
            'type' => 'mysql',
            'host' => $GLOBALS['host'] ?? 'localhost',
            'database' => $GLOBALS['dbname'] ?? '',
            'prefix' => TB_PREF
        ];
    }

    /**
     * Simple parameter binding for FrontAccounting
     * 
     * Since FA doesn't have prepared statements, we manually escape and substitute
     * 
     * @param string $query  SQL query with ? placeholders
     * @param array  $params Parameters to bind
     * @return string Query with parameters substituted
     */
    private function bindParameters(string $query, array $params): string
    {
        $paramIndex = 0;
        
        return preg_replace_callback('/\?/', function($matches) use ($params, &$paramIndex) {
            if (!isset($params[$paramIndex])) {
                throw new \Exception("Missing parameter at index {$paramIndex}");
            }
            
            $value = $params[$paramIndex++];
            
            if ($value === null) {
                return 'NULL';
            } elseif (is_string($value)) {
                return "'" . db_escape($value) . "'";
            } elseif (is_bool($value)) {
                return $value ? '1' : '0';
            } else {
                return (string) $value;
            }
        }, $query);
    }
}
