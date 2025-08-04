<?php

declare(strict_types=1);

namespace AmazonInvoices\Interfaces;

/**
 * Database Repository Interface
 * 
 * Defines the contract for database operations, enabling framework-agnostic
 * database access. Implementations can target FrontAccounting, WordPress,
 * Laravel, or any other database abstraction layer.
 * 
 * @package AmazonInvoices\Interfaces
 * @author  Your Name
 * @since   1.0.0
 */
interface DatabaseRepositoryInterface
{
    /**
     * Execute a database query
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for the query
     * @return mixed Query result resource
     * @throws \Exception If query execution fails
     */
    public function query(string $query, array $params = []);

    /**
     * Fetch a single row from a result set
     * 
     * @param mixed $result Query result resource
     * @return array|null Associative array of row data or null if no more rows
     */
    public function fetch($result): ?array;

    /**
     * Fetch all rows from a result set
     * 
     * @param mixed $result Query result resource
     * @return array Array of associative arrays
     */
    public function fetchAll($result): array;

    /**
     * Get number of rows in a result set
     * 
     * @param mixed $result Query result resource
     * @return int Number of rows
     */
    public function numRows($result): int;

    /**
     * Get the last inserted record ID
     * 
     * @return int Last insert ID
     */
    public function getLastInsertId(): int;

    /**
     * Get number of affected rows from last query
     * 
     * @return int Number of affected rows
     */
    public function getAffectedRows(): int;

    /**
     * Get the table prefix used by this database
     * 
     * @return string Table prefix
     */
    public function getTablePrefix(): string;

    /**
     * Begin a database transaction
     * 
     * @throws \Exception If transaction cannot be started
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     * 
     * @throws \Exception If commit fails
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     * 
     * @throws \Exception If rollback fails
     */
    public function rollback(): void;

    /**
     * Escape a string value for safe inclusion in SQL
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string;

    /**
     * Check if a table exists in the database
     * 
     * @param string $tableName Name of table to check (without prefix)
     * @return bool True if table exists
     */
    public function tableExists(string $tableName): bool;

    /**
     * Get information about table columns
     * 
     * @param string $tableName Name of table (without prefix)
     * @return array Array of column information
     */
    public function getTableColumns(string $tableName): array;

    /**
     * Execute a raw SQL query without parameter binding
     * Use with caution - prefer the query() method with parameters
     * 
     * @param string $sql Raw SQL query
     * @return mixed Query result
     * @throws \Exception If query execution fails
     */
    public function rawQuery(string $sql);

    /**
     * Get the database connection handle
     * 
     * @return mixed Database connection resource/object
     */
    public function getConnection();
}
