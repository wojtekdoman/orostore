# OroCommerce CE Multi-Warehouse Implementation Plan

## Project Overview
**Goal:** Implement simple multi-warehouse inventory display for different customer groups in OroCommerce Community Edition

**Date:** 2025-08-05
**Status:** Planning Phase

## Current System Analysis

### OroCommerce CE Limitations
- ❌ No native multi-warehouse support (Enterprise only)
- ❌ Single inventory source per product
- ✅ Custom attributes/fields support
- ✅ Customer Groups functionality
- ✅ Flexible configuration hierarchy
- ✅ Event system for customization

### Existing Inventory Structure
- **Entity:** `Oro\Bundle\InventoryBundle\Entity\InventoryLevel`
- **Repository:** `InventoryLevelRepository`
- **Key Fields:** product_id, product_unit_precision_id, organization_id, quantity
- **Unique Constraint:** product_id + product_unit_precision_id + organization_id

## Proposed Solution: Custom Attributes + Customer Groups

### Architecture Overview
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Customer      │    │    Product      │    │   Inventory     │
│   Groups        │    │   Attributes    │    │   Display       │
│                 │    │                 │    │   Logic         │
│ • Warehouse_1   │◄──►│ • warehouse_1   │◄──►│ • EventListener │
│ • Warehouse_2   │    │ • warehouse_2   │    │ • TwigExtension │
│ • Default       │    │ • primary_wh    │    │ • Frontend      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Implementation Phases

## Phase 1: Database Structure Enhancement

### 1.1 Add Product Attributes
**Location:** System → Entities → Product Entity → Create Field

**New Attributes:**
1. **warehouse_1_stock**
   - Type: Integer
   - Label: "Warehouse 1 Stock"
   - Default: 0
   - Required: No

2. **warehouse_2_stock**
   - Type: Integer
   - Label: "Warehouse 2 Stock" 
   - Default: 0
   - Required: No

3. **assigned_warehouse**
   - Type: Select
   - Options: ["warehouse_1", "warehouse_2", "both"]
   - Label: "Assigned Warehouse"
   - Default: "both"
   - Required: Yes

4. **warehouse_priority**
   - Type: Select
   - Options: ["warehouse_1", "warehouse_2"]
   - Label: "Primary Warehouse"
   - Default: "warehouse_1"
   - Required: No

### 1.2 Customer Groups Setup
**Location:** Customers → Customer Groups

**New Customer Groups:**
1. **Warehouse_1_Customers**
   - Name: "Warehouse 1 Customers"
   - Code: "warehouse_1"
   - Description: "Customers seeing Warehouse 1 inventory"

2. **Warehouse_2_Customers** 
   - Name: "Warehouse 2 Customers"
   - Code: "warehouse_2"
   - Description: "Customers seeing Warehouse 2 inventory"

3. **All_Warehouses_Customers**
   - Name: "All Warehouses Access"
   - Code: "all_warehouses"
   - Description: "Customers seeing combined inventory"

## Phase 2: Custom Bundle Development

### 2.1 Bundle Structure
```
src/Acme/Bundle/MultiWarehouseBundle/
├── AcmeMultiWarehouseBundle.php
├── DependencyInjection/
│   ├── AcmeMultiWarehouseBundleExtension.php
│   └── Configuration.php
├── EventListener/
│   ├── InventoryDisplayListener.php
│   ├── ProductListListener.php
│   └── ProductViewListener.php
├── Service/
│   ├── WarehouseInventoryManager.php
│   └── CustomerWarehouseResolver.php
├── Twig/
│   └── WarehouseExtension.php
└── Resources/
    ├── config/
    │   ├── services.yml
    │   └── oro/bundles.yml
    └── views/
        └── Product/
            └── inventory_display.html.twig
```

### 2.2 Core Services

#### WarehouseInventoryManager
```php
class WarehouseInventoryManager
{
    public function getInventoryForCustomer(Product $product, Customer $customer): int
    public function getWarehouseForCustomer(Customer $customer): string
    public function getAllWarehouseStock(Product $product): array
    public function updateWarehouseStock(Product $product, string $warehouse, int $quantity): void
}
```

#### CustomerWarehouseResolver
```php
class CustomerWarehouseResolver  
{
    public function resolveWarehouse(Customer $customer): string
    public function canAccessWarehouse(Customer $customer, string $warehouse): bool
    public function getCustomerWarehousePriority(Customer $customer): array
}
```

### 2.3 Event Listeners

#### InventoryDisplayListener
- **Events:** 
  - `oro_product.inventory.level.calculate`
  - `oro_frontend.product.view`
  - `oro_frontend.product.list`

#### ProductListListener  
- **Purpose:** Filter product lists based on warehouse assignment
- **Event:** `oro_datagrid.orm_datagrid.build.after`

#### ProductViewListener
- **Purpose:** Modify product view inventory display
- **Event:** `oro_layout.block_type.initialize`

## Phase 3: Frontend Integration

### 3.1 Twig Extensions
```twig
{{ oro_warehouse_stock(product, 'warehouse_1') }}
{{ oro_customer_warehouse_stock(product) }}
{{ oro_warehouse_availability(product) }}
```

### 3.2 JavaScript Components
- Real-time stock updates
- Warehouse switching (for admin users)
- Stock availability indicators

### 3.3 Template Overrides
- Product list item templates
- Product view inventory section
- Shopping cart inventory validation

## Phase 4: Configuration & Administration

### 4.1 System Configuration
**Location:** System → Configuration → Commerce → Inventory

**New Settings:**
- Enable Multi-Warehouse Mode
- Default Warehouse Assignment
- Stock Display Mode (separate/combined)
- Low Stock Threshold per Warehouse

### 4.2 Import/Export Support
- CSV templates for warehouse stock updates
- Bulk assignment of products to warehouses
- Customer group warehouse assignments

## Phase 5: Testing Strategy

### 5.1 Unit Tests
- WarehouseInventoryManager tests
- CustomerWarehouseResolver tests
- Event listener tests

### 5.2 Functional Tests  
- Customer group inventory visibility
- Product assignment workflow
- Stock calculation accuracy

### 5.3 Integration Tests
- Full workflow testing
- Performance impact assessment
- Compatibility with existing features

## Implementation Timeline

### Week 1: Setup & Database
- [ ] Add product attributes
- [ ] Create customer groups
- [ ] Test attribute functionality

### Week 2: Core Bundle Development
- [ ] Create bundle structure
- [ ] Implement core services
- [ ] Basic event listeners

### Week 3: Frontend Integration
- [ ] Twig extensions
- [ ] Template modifications
- [ ] JavaScript components

### Week 4: Testing & Refinement
- [ ] Unit/functional testing
- [ ] Performance optimization
- [ ] Documentation

## Configuration Examples

### Product Configuration
```yaml
# Example product attribute values
warehouse_1_stock: 50
warehouse_2_stock: 25
assigned_warehouse: "both"
warehouse_priority: "warehouse_1"
```

### Customer Assignment
```yaml
# Customer group assignments
customer_groups:
  - name: "Warehouse 1 Customers"
    warehouse_access: ["warehouse_1"]
  - name: "Warehouse 2 Customers" 
    warehouse_access: ["warehouse_2"]
```

## Migration Strategy

### Data Migration
1. **Existing Products:** Default all to "both" warehouses
2. **Stock Distribution:** Split current stock 50/50 between warehouses
3. **Customer Assignment:** Default to combined view

### Rollback Plan
1. Disable bundle
2. Remove custom attributes (optional)
3. Revert template changes
4. Clear cache

## Potential Issues & Solutions

### Issue 1: Performance Impact
**Solution:** Implement caching for warehouse stock calculations

### Issue 2: Stock Synchronization
**Solution:** Event-driven stock updates with validation

### Issue 3: Order Processing
**Solution:** Modify order creation to respect warehouse assignments

### Issue 4: Reporting Accuracy
**Solution:** Extend reports to include warehouse breakdown

## Success Metrics

- ✅ Customers see only relevant warehouse inventory
- ✅ Stock calculations remain accurate
- ✅ No performance degradation
- ✅ Easy administration through back-office
- ✅ Compatible with future OroCommerce updates

## Future Enhancements

1. **Advanced Warehouse Logic**
   - Distance-based warehouse selection
   - Automatic stock transfers
   - Multi-warehouse order fulfillment

2. **Enhanced Reporting**
   - Warehouse-specific analytics
   - Stock movement tracking
   - Demand forecasting per warehouse

3. **API Integration**
   - External warehouse system sync
   - Real-time stock updates
   - Third-party logistics integration

## Notes

- This solution maintains OroCommerce CE compatibility
- Uses only standard extension points
- Preserves upgrade path to Enterprise Edition
- Minimal core modifications required

**Last Updated:** 2025-08-05  
**Next Review:** Before implementation start