-- SQL table for storing FA Amazon Invoices module config variables
CREATE TABLE IF NOT EXISTS amazon_invoices_prefs (
    config_key VARCHAR(64) NOT NULL PRIMARY KEY,
    config_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
