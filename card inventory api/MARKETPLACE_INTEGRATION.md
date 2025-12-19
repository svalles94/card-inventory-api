# Marketplace Integration Architecture

## Overview

This card inventory system serves as the **single source of truth** for singles inventory across multiple game stores. It automatically syncs inventory quantities to online marketplaces (Shopify, eBay, TCGPlayer, etc.) whenever changes occur.

## Architecture Principles

### 1. **Your System = Master Database**
- All inventory changes happen in your Laravel system first
- Staff use ManageInventory page or admin panel to adjust quantities
- System automatically pushes updates to all connected marketplaces

### 2. **Event-Driven Sync**
- `InventoryObserver` watches for quantity changes
- Automatically triggers sync to all enabled marketplaces
- Handles failures gracefully with retry logic and error tracking

### 3. **Multi-Marketplace Support**
- Easily extensible architecture
- Add new marketplaces by creating sync services
- Each store can enable/disable specific marketplaces

## Database Schema

### New Tables

#### `marketplace_integrations`
Stores API credentials and settings for each marketplace per store.

```sql
- store_id (FK to stores)
- marketplace (enum: 'shopify', 'ebay', 'tcgplayer', 'amazon')
- enabled (boolean)
- credentials (encrypted JSON: API keys, tokens)
- settings (JSON: marketplace-specific config)
- last_sync_at (timestamp)
```

### Enhanced Tables

#### `cards` - Added Shopify Integration Fields
```sql
- shopify_product_id (nullable)
- shopify_variant_id (nullable)
- shopify_inventory_item_id (nullable)
- sku (unique, nullable)
- sync_to_shopify (boolean, default true)
```

#### `inventory` - Added Sync Tracking Fields
```sql
- shopify_location_id (nullable)
- shopify_inventory_level_id (nullable)
- last_synced_at (timestamp)
- sync_status (enum: 'pending', 'synced', 'failed')
- sync_error (text, nullable)
```

## How It Works

### Flow Diagram

```
[Staff Updates Quantity in ManageInventory Page]
           ↓
[InventoryObserver Detects Change]
           ↓
[Loop Through Enabled Marketplaces]
           ↓
    ┌──────┴──────┐
    ↓             ↓
[Shopify Sync]  [eBay Sync]  [TCGPlayer Sync]
    ↓             ↓             ↓
[GraphQL API]  [REST API]   [REST API]
    ↓             ↓             ↓
[Update Status: synced/failed]
```

### Example: Quantity Update

1. **Staff Action**: Employee changes "Silvie" quantity from 5 → 3 in ManageInventory page
2. **Observer Triggers**: `InventoryObserver::updated()` fires
3. **Marketplace Loop**: System finds store has Shopify + eBay enabled
4. **Shopify Sync**:
   - `ShopifyInventorySync` builds GraphQL mutation
   - Calls `inventorySetQuantities` with quantity=3
   - Updates `sync_status='synced'`, `last_synced_at=now()`
5. **eBay Sync**: Similar process with eBay's API
6. **Result**: Both marketplaces now show 3 units available

## Shopify Integration Details

### Authentication
- Uses Shopify Admin API with private app credentials
- Stored encrypted in `marketplace_integrations.credentials`:
  ```json
  {
    "shop_url": "https://your-store.myshopify.com",
    "access_token": "shpat_xxxxx"
  }
  ```

### GraphQL Mutations Used

#### Setting Inventory Quantities
```graphql
mutation {
  inventorySetQuantities(input: {
    reason: "correction",
    referenceDocumentUri: "gid://card-inventory-system/InventorySync/{store_id}",
    quantities: [{
      inventoryItemId: "gid://shopify/InventoryItem/xxxxx",
      locationId: "gid://shopify/Location/xxxxx",
      quantity: 3
    }]
  }) {
    inventoryAdjustmentGroup { id }
    userErrors { message }
  }
}
```

#### Creating Products
```graphql
mutation {
  productCreate(input: {
    title: "Silvie",
    productType: "Trading Card",
    vendor: "Weebs of the Coast",
    tags: ["grand-archive", "Dawn of Ashes", "Champion"],
    variants: [{
      sku: "GA-DOA-001",
      price: "4.99",
      inventoryManagement: SHOPIFY
    }]
  }) {
    product {
      id
      variants { node { id inventoryItem { id } } }
    }
  }
}
```

### Sync Behavior

- **On quantity change**: Push new quantity to Shopify
- **On price change**: Update variant price (optional)
- **On new card**: Create product + variant in Shopify
- **Sync disabled**: Set `sync_to_shopify=false` on card to skip

## Extending to Other Marketplaces

### Adding eBay Integration

1. **Create Service Class**:
   ```php
   // app/Services/Marketplace/EbayInventorySync.php
   class EbayInventorySync {
       public function syncInventory(Inventory $inventory): bool {
           // Use eBay Trading API
           // Call ReviseInventoryStatus with new quantity
       }
   }
   ```

2. **Update Observer**:
   ```php
   protected function syncToEbay(Inventory $inventory, MarketplaceIntegration $integration): void {
       $service = new EbayInventorySync($integration);
       $service->syncInventory($inventory);
   }
   ```

3. **Add Credentials**: Store eBay OAuth tokens in `marketplace_integrations`

### Adding TCGPlayer Integration

Similar process using TCGPlayer's Seller API:
- Endpoint: `PUT /inventory/sku/{sku}`
- Update quantity for specific SKU
- Requires TCGPlayer seller account + API key

## Configuration

### Setup Shopify Integration

1. **Create Private App** in Shopify admin:
   - Go to Apps → Develop apps → Create app
   - Grant `read_inventory` and `write_inventory` scopes
   - Copy admin API access token

2. **Create Integration Record**:
   ```php
   MarketplaceIntegration::create([
       'store_id' => 1,
       'marketplace' => 'shopify',
       'enabled' => true,
       'credentials' => [
           'shop_url' => 'https://your-store.myshopify.com',
           'access_token' => 'shpat_xxxxx',
       ],
       'settings' => [
           'default_location_id' => 'gid://shopify/Location/xxxxx',
       ],
   ]);
   ```

3. **Link Cards to Shopify Products**:
   - Option A: Bulk import existing products via GraphQL queries
   - Option B: Create products on-demand when inventory is first added
   - Option C: Manual mapping in admin panel

### Environment Variables

Add to `.env`:
```env
# Shopify
SHOPIFY_API_VERSION=2024-01

# eBay (future)
EBAY_CLIENT_ID=
EBAY_CLIENT_SECRET=

# TCGPlayer (future)
TCGPLAYER_API_KEY=
```

## Error Handling

### Sync Failures
- Stored in `inventory.sync_error` field
- Visible in admin panel with retry button
- Logged to `storage/logs/laravel.log`

### Retry Logic
- Failed syncs remain `sync_status='failed'`
- Staff can manually trigger retry from admin
- Or implement queue-based retry with exponential backoff

### Common Issues

| Error | Cause | Solution |
|-------|-------|----------|
| `CHANGE_FROM_QUANTITY_STALE` | Quantity changed in Shopify directly | Pull latest from Shopify first |
| `Invalid inventoryItemId` | Card not linked to Shopify product | Create product in Shopify first |
| `Unauthorized` | Invalid access token | Regenerate token in Shopify |

## Admin UI Features

### Marketplace Settings Page (Future)
- Enable/disable marketplaces per store
- Test connection to each marketplace
- View sync status for all inventory items
- Bulk retry failed syncs
- View sync logs/history

### Inventory Table Enhancements
- Badge showing sync status (✓ synced, ⏳ pending, ✗ failed)
- Last synced timestamp
- Quick action to toggle `sync_to_shopify`
- Bulk operations (sync all, disable sync for set)

## Performance Considerations

### Async Queue Processing
For high-volume stores, consider queueing sync jobs:

```php
// In InventoryObserver
dispatch(new SyncInventoryToMarketplace($inventory, $integration));
```

### Batch Updates
When importing large datasets, disable observer temporarily:
```php
Inventory::withoutEvents(function () {
    // Bulk insert/update
});

// Then trigger batch sync manually
dispatch(new BulkSyncToShopify($inventoryIds));
```

### Rate Limiting
Shopify allows:
- REST API: 2 requests/second
- GraphQL API: 1000 points/second (inventory mutations = 10 points each)

Implement throttling if needed:
```php
RateLimiter::attempt('shopify-sync', 2, function() {
    // Sync logic
});
```

## Testing

### Unit Tests
```php
// Test sync service
$inventory = Inventory::factory()->create(['quantity' => 5]);
$service = new ShopifyInventorySync($integration);
$result = $service->syncInventory($inventory);
$this->assertTrue($result);
```

### Integration Tests
```php
// Test observer fires
$inventory->update(['quantity' => 10]);
$this->assertEquals('synced', $inventory->fresh()->sync_status);
```

### Mock Shopify API
Use HTTP fake for testing without real API calls:
```php
Http::fake([
    '*/admin/api/*/graphql.json' => Http::response(['data' => [...]])
]);
```

## Future Enhancements

- [ ] Webhook receivers (Shopify → Your System for order fulfillment)
- [ ] Bi-directional sync (pull changes from marketplaces)
- [ ] Price sync (auto-update marketplace prices)
- [ ] Image sync (push card images to marketplaces)
- [ ] Analytics dashboard (sales by marketplace)
- [ ] Multi-currency support
- [ ] Automated repricing based on market conditions
- [ ] Low stock alerts per marketplace
- [ ] Sync scheduling (daily full sync vs. real-time)

## Support

### Shopify API Documentation
- [Inventory Management Apps](https://shopify.dev/docs/apps/build/orders-fulfillment/inventory-management-apps)
- [GraphQL Admin API](https://shopify.dev/docs/api/admin-graphql)
- [inventorySetQuantities](https://shopify.dev/docs/api/admin-graphql/latest/mutations/inventorySetQuantities)

### eBay API Documentation
- [Trading API - ReviseInventoryStatus](https://developer.ebay.com/devzone/xml/docs/reference/ebay/ReviseInventoryStatus.html)

### TCGPlayer API Documentation
- [Seller API - Inventory Management](https://docs.tcgplayer.com/docs/seller-api-inventory)
