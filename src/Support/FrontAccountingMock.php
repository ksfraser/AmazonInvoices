<?php

/**
 * FrontAccounting Mock Functions
 * 
 * This file provides mock implementations of FrontAccounting functions and constants
 * for testing and development outside of the FA environment.
 * These definitions are only used when not running within FrontAccounting.
 * 
 * @package AmazonInvoices
 * @author  Your Name
 * @since   1.0.0
 */

// Only define these if we're not in a FrontAccounting environment
if (!defined('TB_PREF')) {

    // Define FrontAccounting constants
    define('TB_PREF', 'fa_');
    
    // Mock database connection resource
    $mock_db_connection = null;
    $mock_last_insert_id = 0;
    $mock_affected_rows = 0;
    $mock_transaction_active = false;

    /**
     * Mock FrontAccounting database query function
     * 
     * @param string $sql SQL query
     * @param string $err_msg Error message for logging
     * @return MockQueryResult Mock query result
     */
    function db_query($sql, $err_msg = "Database error")
    {
        global $mock_last_insert_id, $mock_affected_rows;
        
        // Simulate different query types for testing
        if (stripos($sql, 'INSERT') === 0) {
            $mock_last_insert_id++;
            $mock_affected_rows = 1;
            return new MockQueryResult('insert', $mock_last_insert_id);
        } elseif (stripos($sql, 'UPDATE') === 0 || stripos($sql, 'DELETE') === 0) {
            $mock_affected_rows = 1;
            return new MockQueryResult('modify');
        } elseif (stripos($sql, 'SELECT') === 0) {
            return new MockQueryResult('select');
        } else {
            return new MockQueryResult('other');
        }
    }

    /**
     * Mock fetch function
     * 
     * @param MockQueryResult $result Query result
     * @return array|false Mock row data or false if no more rows
     */
    function db_fetch($result)
    {
        if (!($result instanceof MockQueryResult)) {
            return false;
        }
        
        return $result->fetch();
    }

    /**
     * Mock get last insert ID
     * 
     * @return int Last insert ID
     */
    function db_insert_id()
    {
        global $mock_last_insert_id;
        return $mock_last_insert_id;
    }

    /**
     * Mock get affected rows
     * 
     * @return int Number of affected rows
     */
    function db_num_affected_rows()
    {
        global $mock_affected_rows;
        return $mock_affected_rows;
    }

    /**
     * Mock escape function
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    function db_escape($value)
    {
        return addslashes($value);
    }

    /**
     * Mock begin transaction
     * 
     * @return void
     */
    function begin_transaction()
    {
        global $mock_transaction_active;
        $mock_transaction_active = true;
    }

    /**
     * Mock commit transaction
     * 
     * @return void
     */
    function commit_transaction()
    {
        global $mock_transaction_active;
        $mock_transaction_active = false;
    }

    /**
     * Mock rollback transaction
     * 
     * @return void
     */
    function rollback_transaction()
    {
        global $mock_transaction_active;
        $mock_transaction_active = false;
    }

    /**
     * Mock error number function
     * 
     * @return int Error number (0 for no error)
     */
    function db_error_no()
    {
        return 0;
    }

    /**
     * Mock error message function
     * 
     * @return string Error message
     */
    function db_error_msg()
    {
        return '';
    }

    /**
     * Mock translation function
     * 
     * @param string $text Text to translate
     * @return string Translated text (just returns original for mock)
     */
    function _($text)
    {
        return $text;
    }

    /**
     * Mock display error function
     * 
     * @param string $msg Error message
     * @return void
     */
    function display_error($msg)
    {
        error_log("FA Mock Error: " . $msg);
    }

    /**
     * Mock display notification function
     * 
     * @param string $msg Notification message
     * @return void
     */
    function display_notification($msg)
    {
        error_log("FA Mock Notification: " . $msg);
    }

    /**
     * Mock check security function
     * 
     * @param string $security_area Security area constant
     * @return bool Always returns true for mock
     */
    function check_security($security_area)
    {
        return true;
    }

    // Define mock security area constants
    define('SA_AMAZON_INVOICES', 'SA_AMAZON_INVOICES');
    define('SA_AMAZON_DOWNLOAD', 'SA_AMAZON_DOWNLOAD');
    define('SA_AMAZON_PROCESS', 'SA_AMAZON_PROCESS');
    define('SA_AMAZON_MATCH', 'SA_AMAZON_MATCH');
    define('SA_AMAZON_PAYMENTS', 'SA_AMAZON_PAYMENTS');

    // Mock global path variable
    if (!isset($path_to_root)) {
        $path_to_root = dirname(__DIR__);
    }
}

/**
 * Mock Query Result Class
 * 
 * Simulates FrontAccounting query results for testing
 */
class MockQueryResult
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $currentRow;

    /**
     * @var int
     */
    private $insertId;

    public function __construct(string $type, int $insertId = 0)
    {
        $this->type = $type;
        $this->currentRow = 0;
        $this->insertId = $insertId;
        
        // Generate mock data based on query type
        $this->data = $this->generateMockData();
    }

    /**
     * Fetch next row from mock result
     * 
     * @return array|false Mock row data or false if no more rows
     */
    public function fetch()
    {
        if ($this->currentRow >= count($this->data)) {
            return false;
        }
        
        return $this->data[$this->currentRow++];
    }

    /**
     * Get all rows from mock result
     * 
     * @return array All mock rows
     */
    public function fetchAll(): array
    {
        return $this->data;
    }

    /**
     * Generate mock data based on query type
     * 
     * @return array Mock data
     */
    private function generateMockData(): array
    {
        switch ($this->type) {
            case 'select':
                return [
                    [
                        'id' => 1,
                        'stock_id' => 'TEST001',
                        'description' => 'Test Product',
                        'long_description' => 'Test Product Long Description',
                        'units' => 'each',
                        'material_cost' => 10.00,
                        'inactive' => 0
                    ],
                    [
                        'id' => 2,
                        'stock_id' => 'TEST002',
                        'description' => 'Another Test Product',
                        'long_description' => 'Another Test Product Description',
                        'units' => 'pcs',
                        'material_cost' => 25.50,
                        'inactive' => 0
                    ]
                ];
            
            case 'insert':
                return [['insert_id' => $this->insertId]];
            
            case 'modify':
                return [['affected_rows' => 1]];
            
            default:
                return [];
        }
    }
}
