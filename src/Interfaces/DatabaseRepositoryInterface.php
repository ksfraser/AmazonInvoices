<?php

declare(strict_types=1);

namespace Amaz    /**
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
     * Get the table prefix used by the framework
     * 
     * @return string Table prefix
     */
    public function getTablePrefix(): string;ices\Interfaces;

/**
 * Database repository interface for Amazon invoice operations
 * 
 * This interface abstracts database operations to support multiple frameworks
 * (FrontAccounting, WordPress, Laravel, etc.)
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
     * @param string $query The SQL query to execute
     * @param array  $params Query parameters for prepared statements
     * @return mixed Query result resource/object
     * @throws \Exception When query execution fails
     */
    public function query(string $query, array $params = []);

    /**
     * Fetch a single row from query result
     * 
     * @param mixed $result Query result resource/object
     * @return array|null Associative array of row data or null if no more rows
     */
    public function fetch($result): ?array;

    /**
     * Fetch all rows from query result
     * 
     * @param mixed $result Query result resource/object
     * @return array Array of associative arrays
     */
    public function fetchAll($result): array;

    /**
     * Get the number of rows affected by last query
     * 
     * @return int Number of affected rows
     */
    public function getAffectedRows(): int;

    /**
     * Get the last inserted ID
     * 
     * @return int Last insert ID
     */
    public function getLastInsertId(): int;

    /**
     * Begin a database transaction
     * 
     * @return bool True on success
     */
    public function beginTransaction(): bool;

    /**
     * Commit a database transaction
     * 
     * @return bool True on success
     */
    public function commit(): bool;

    /**
     * Rollback a database transaction
     * 
     * @return bool True on success
     */
    public function rollback(): bool;

    /**
     * Escape a string for database use
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string;

    /**
     * Get the table prefix for this database
     * 
     * @return string Table prefix
     */
    public function getTablePrefix(): string;
}
