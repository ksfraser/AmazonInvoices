<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\ItemMatchingService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\InvoiceItem;

/**
 * Unit tests for ItemMatchingService
 * 
 * @package AmazonInvoices\Tests\Unit\Services
 * @author  Your Name
 * @since   1.0.0
 */
class ItemMatchingServiceTest extends TestCase
{
    private ItemMatchingService $service;
    private MockObject $mockDatabase;

    protected function setUp(): void
    {
        $this->mockDatabase = $this->createMock(DatabaseRepositoryInterface::class);
        $this->mockDatabase->method('getTablePrefix')->willReturn('test_');
        
        $this->service = new ItemMatchingService($this->mockDatabase);
    }

    public function testFindMatchingStockItemByAsin(): void
    {
        // Mock query result for ASIN search
        $mockResult = $this->createMockQueryResult([
            ['stock_id' => 'TEST-001', 'description' => 'Test Product']
        ]);
        
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->willReturn($mockResult);
            
        $this->mockDatabase->expects($this->once())
            ->method('fetchAll')
            ->with($mockResult)
            ->willReturn([
                ['stock_id' => 'TEST-001', 'description' => 'Test Product']
            ]);

        $result = $this->service->findMatchingStockItem('B08XYZ123', null, 'Test Product');
        
        $this->assertEquals('TEST-001', $result);
    }

    public function testFindMatchingStockItemBySku(): void
    {
        // First call (ASIN search) returns empty
        $emptyResult = $this->createMockQueryResult([]);
        
        // Second call (SKU search) returns match
        $skuResult = $this->createMockQueryResult([
            ['stock_id' => 'SKU-001', 'description' => 'SKU Product']
        ]);
        
        $this->mockDatabase->expects($this->exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls($emptyResult, $skuResult);
            
        $this->mockDatabase->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls([], [
                ['stock_id' => 'SKU-001', 'description' => 'SKU Product']
            ]);

        $result = $this->service->findMatchingStockItem(null, 'TEST-SKU', 'Test Product');
        
        $this->assertEquals('SKU-001', $result);
    }

    public function testFindMatchingStockItemNotFound(): void
    {
        $emptyResult = $this->createMockQueryResult([]);
        
        $this->mockDatabase->method('query')->willReturn($emptyResult);
        $this->mockDatabase->method('fetchAll')->willReturn([]);

        $result = $this->service->findMatchingStockItem(null, null, 'Non-existent Product');
        
        $this->assertNull($result);
    }

    public function testAddMatchingRule(): void
    {
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO test_amazon_item_matching_rules'),
                ['asin_pattern', 'B08*', 'TEST-001', 80, 1, 'Added via API']
            );
            
        $this->mockDatabase->expects($this->once())
            ->method('getLastInsertId')
            ->willReturn(123);

        $ruleId = $this->service->addMatchingRule('asin_pattern', 'B08*', 'TEST-001', 1);
        
        $this->assertEquals(123, $ruleId);
    }

    public function testGetMatchingRulesActiveOnly(): void
    {
        $mockResult = $this->createMockQueryResult([
            [
                'id' => 1,
                'rule_type' => 'asin_pattern',
                'pattern' => 'B08*',
                'stock_id' => 'TEST-001',
                'active' => 1
            ]
        ]);
        
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->with($this->stringContains('WHERE r.active = 1'));
            
        $this->mockDatabase->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id' => 1,
                    'rule_type' => 'asin_pattern',
                    'pattern' => 'B08*',
                    'stock_id' => 'TEST-001',
                    'active' => 1
                ]
            ]);

        $rules = $this->service->getMatchingRules(true);
        
        $this->assertCount(1, $rules);
        $this->assertEquals('asin_pattern', $rules[0]['rule_type']);
    }

    public function testGetMatchingRulesAllRules(): void
    {
        $mockResult = $this->createMockQueryResult([]);
        
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->with($this->logicalNot($this->stringContains('WHERE r.active = 1')));
            
        $this->mockDatabase->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $rules = $this->service->getMatchingRules(false);
        
        $this->assertIsArray($rules);
    }

    public function testUpdateRuleStatus(): void
    {
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('UPDATE test_amazon_item_matching_rules SET active = ?'),
                [0, 123]
            );
            
        $this->mockDatabase->expects($this->once())
            ->method('getAffectedRows')
            ->willReturn(1);

        $result = $this->service->updateRuleStatus(123, false);
        
        $this->assertTrue($result);
    }

    public function testDeleteRule(): void
    {
        $this->mockDatabase->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('DELETE FROM test_amazon_item_matching_rules'),
                [123]
            );
            
        $this->mockDatabase->expects($this->once())
            ->method('getAffectedRows')
            ->willReturn(1);

        $result = $this->service->deleteRule(123);
        
        $this->assertTrue($result);
    }

    public function testAutoMatchInvoiceItems(): void
    {
        // Mock invoice items query
        $itemsResult = $this->createMockQueryResult([
            [
                'id' => 1,
                'line_number' => 1,
                'product_name' => 'Test Product',
                'asin' => 'B08XYZ123',
                'sku' => null,
                'quantity' => 1,
                'unit_price' => 25.00,
                'total_price' => 25.00
            ]
        ]);
        
        // Mock stock search result
        $stockResult = $this->createMockQueryResult([
            ['stock_id' => 'MATCHED-001']
        ]);
        
        $this->mockDatabase->expects($this->atLeast(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls($itemsResult, $stockResult);
            
        $this->mockDatabase->expects($this->atLeast(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'id' => 1,
                        'line_number' => 1,
                        'product_name' => 'Test Product',
                        'asin' => 'B08XYZ123',
                        'sku' => null,
                        'quantity' => 1,
                        'unit_price' => 25.00,
                        'total_price' => 25.00
                    ]
                ],
                [['stock_id' => 'MATCHED-001']]
            );

        $this->mockDatabase->expects($this->once())
            ->method('beginTransaction');
            
        $this->mockDatabase->expects($this->once())
            ->method('commit');

        $matchedCount = $this->service->autoMatchInvoiceItems(1);
        
        $this->assertEquals(1, $matchedCount);
    }

    public function testGetSuggestedStockItems(): void
    {
        $mockResult = $this->createMockQueryResult([
            [
                'stock_id' => 'SUGGESTION-001',
                'description' => 'Similar Product',
                'confidence' => 75,
                'match_type' => 'name'
            ]
        ]);
        
        $this->mockDatabase->method('query')->willReturn($mockResult);
        $this->mockDatabase->method('fetchAll')->willReturn([
            [
                'stock_id' => 'SUGGESTION-001',
                'description' => 'Similar Product',
                'confidence' => 75,
                'match_type' => 'name'
            ]
        ]);

        $suggestions = $this->service->getSuggestedStockItems('Test Product', 5);
        
        $this->assertCount(1, $suggestions);
        $this->assertEquals('SUGGESTION-001', $suggestions[0]['stock_id']);
        $this->assertEquals(75, $suggestions[0]['confidence']);
    }

    /**
     * Create a mock query result object
     * 
     * @param array $data Data to return
     * @return MockObject Mock query result
     */
    private function createMockQueryResult(array $data): MockObject
    {
        $mock = $this->createMock(\stdClass::class);
        return $mock;
    }

    public function testServiceConfiguration(): void
    {
        // Test with custom configuration
        $config = [
            'min_name_confidence' => 80,
            'default_category_id' => 5
        ];
        
        $service = new ItemMatchingService($this->mockDatabase, $config);
        
        // Service should be created without errors
        $this->assertInstanceOf(ItemMatchingService::class, $service);
    }

    public function testTransactionHandling(): void
    {
        // Test that transactions are properly handled during operations
        $this->mockDatabase->expects($this->once())
            ->method('beginTransaction');
            
        $this->mockDatabase->expects($this->once())
            ->method('commit');

        // Mock successful auto-match operation
        $itemsResult = $this->createMockQueryResult([]);
        $this->mockDatabase->method('query')->willReturn($itemsResult);
        $this->mockDatabase->method('fetchAll')->willReturn([]);

        $result = $this->service->autoMatchInvoiceItems(1);
        
        $this->assertEquals(0, $result);
    }
}
