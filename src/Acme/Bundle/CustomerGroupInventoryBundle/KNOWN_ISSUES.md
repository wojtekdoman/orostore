# Known Issues

## Shopping List Inventory Status

**Issue**: Shopping list (cart) shows default inventory status instead of customer group-specific status.

**Technical Details**:
- Shopping list uses OroDataGridBundle with grid name: `frontend-customer-user-shopping-list-edit-grid`
- Inventory status is rendered as `prod_inventory_status.in_stock` (enum value)
- Grid uses server-side rendering with complex event system

**Attempted Solutions**:
1. ❌ ORM ResultAfter listener - causes hanging or doesn't update values
2. ❌ BuildBefore configuration modification - removes status column entirely
3. ❌ Custom datagrid configuration override - no effect

**Root Cause**:
The datagrid pulls inventory status directly from `product.serialized_data` using JSON_EXTRACT in SQL query. This bypasses our PHP-level overrides.

**Potential Solutions** (not implemented):
1. Override the datagrid query builder to join with our inventory override table
2. Create a custom column provider for inventory status
3. Use JavaScript to update status after page load (not ideal)

**Workaround**:
Currently no workaround. Shopping list will show default product inventory status.

**Impact**: 
- Low to Medium - affects only shopping list view
- Other critical views (product page, listing, search) work correctly