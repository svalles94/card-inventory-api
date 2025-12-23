# Multi-Marketplace Integration Setup Guide

## Overview

Your card inventory system can now sync to **4 major marketplaces**:
- 🛍️ **Shopify** - Your e-commerce store
- 🏪 **eBay** - Auction/fixed-price listings
- 🃏 **TCGPlayer** - Trading card marketplace
- 📦 **Amazon** - Global marketplace

All use the **same database structure** - no additional migrations needed!

---

## Database Architecture

### ✅ Already Set Up

The database is **marketplace-agnostic** and ready for all platforms:

#### `cards` table
```sql
- sku (unique) - Used by ALL marketplaces
- shopify_product_id
- shopify_variant_id
- shopify_inventory_item_id
- sync_to_shopify (boolean)
```

#### `inventory` table
```sql
- quantity - Synced to all marketplaces
- shopify_location_id - Shopify-specific
- last_synced_at - Last sync to ANY marketplace
- sync_status - pending/synced/failed
- sync_error - Error message if sync fails
```

#### `marketplace_integrations` table
```sql
- store_id
- marketplace (shopify/ebay/tcgplayer/amazon)
- enabled (boolean)
- credentials (encrypted JSON) - Platform-specific
- settings (JSON) - Platform-specific config
```

### 🔧 Optional Enhancements

For advanced features, you may want to add:

```sql
-- Add to cards table for better marketplace mapping
ALTER TABLE cards ADD COLUMN ebay_listing_id VARCHAR(255) NULL;
ALTER TABLE cards ADD COLUMN tcgplayer_product_id INT NULL;
ALTER TABLE cards ADD COLUMN amazon_asin VARCHAR(20) NULL;
```

But this is **NOT required** - SKU is enough for all platforms!

---

## Platform-Specific Setup

### 1️⃣ eBay Integration

#### What You Need
- eBay Developer Account
- Production or Sandbox API credentials
- OAuth User Token

#### API Details
- **Endpoint**: `https://api.ebay.com/sell/inventory/v1`
- **Authentication**: OAuth 2.0
- **Rate Limit**: 5,000 calls/day
- **Inventory Method**: PUT `/inventory_item/{sku}`

#### Setup Steps

1. **Create eBay Developer Account**
   - Go to https://developer.ebay.com
   - Sign up or log in
   - Create a "Keyset" (Application)

2. **Get Credentials**
   - App ID (Client ID): `YourApp-YourApp-PRD-xxxxx`
   - Cert ID (Client Secret): `PRD-xxxxx`
   - OAuth Token: Generate via OAuth flow

3. **Generate OAuth Token**
   ```bash
   # Use eBay's OAuth tool
   # https://developer.ebay.com/my/auth/?env=production&index=0
   ```

4. **Add to System**
   - Admin panel → Marketplace Integrations → Create
   - Choose "eBay"
   - Enter credentials
   - Test connection

#### How Sync Works
```
Your System → eBay Inventory API
├─ Create inventory item (first time)
├─ Update quantity via PUT /inventory_item/{sku}
├─ Set price, condition, availability
└─ eBay listing updates instantly
```

#### Limitations
- Requires SKU on every card
- Must create inventory item before quantity updates
- Sandbox testing available
- Fixed-price listings only (no auctions via API)

---

### 2️⃣ TCGPlayer Integration

#### What You Need
- TCGPlayer Seller Account (Verified)
- TCGPlayer API Key
- Seller Key for private endpoints

#### API Details
- **Endpoint**: `https://api.tcgplayer.com`
- **Authentication**: Bearer token
- **Rate Limit**: 300 requests/min
- **Inventory Method**: PUT `/seller/inventory/products/{productId}`

#### Setup Steps

1. **Become TCGPlayer Seller**
   - Sign up at https://seller.tcgplayer.com
   - Verify your seller account
   - Get approved for API access

2. **Request API Access**
   - Go to TCGPlayer Seller Portal
   - API Management → Request Access
   - Wait for approval (can take 1-2 weeks)

3. **Get Credentials**
   - API Key: From seller portal
   - Seller Key: Private key for inventory management

4. **Add to System**
   - Admin panel → Marketplace Integrations
   - Choose "TCGPlayer"
   - Enter API Key and Seller Key
   - Test connection

#### How Sync Works
```
Your System → TCGPlayer Seller API
├─ Search for product by name/set
├─ Get TCGPlayer Product ID
├─ Update quantity + price via PUT
└─ TCGPlayer updates your seller inventory
```

#### Important Notes
- **Game Support**: Only works if TCGPlayer lists that game
  - ✅ Grand Archive: Not yet on TCGPlayer
  - ✅ Gundam: Limited support
  - ❌ Riftbound: Not on TCGPlayer
- Requires product mapping (name → TCGPlayer Product ID)
- Pricing strategy configurable (market/below market/fixed)
- Condition defaults to "Near Mint"

#### Product Mapping
TCGPlayer requires you to link your cards to their product IDs:

**Option A: Manual Search**
```php
// Service includes findProduct() method
$tcgService = new TcgPlayerInventorySync($integration);
$productId = $tcgService->findProduct($card);
```

**Option B: Bulk Import**
- Export your inventory as CSV
- Use TCGPlayer's bulk upload tool
- Match products by name
- They'll provide Product IDs

---

### 3️⃣ Amazon Integration

#### What You Need
- Amazon Seller Central account
- SP-API (Selling Partner API) access
- AWS IAM credentials
- LWA (Login with Amazon) credentials

#### API Details
- **Endpoint**: `https://sellingpartnerapi-na.amazon.com`
- **Authentication**: AWS Signature V4 + OAuth 2.0
- **Rate Limit**: Varies by endpoint (typically 10-20 req/sec)
- **Inventory Method**: PATCH `/listings/2021-08-01/items/{sellerId}/{sku}`

#### Setup Steps (Most Complex!)

1. **Register as Amazon Seller**
   - Go to https://sellercentral.amazon.com
   - Sign up for Professional seller account ($39.99/month)
   - Complete verification

2. **Register for SP-API**
   - Seller Central → Apps & Services → Develop apps
   - Click "Add new app client"
   - App name: "Card Inventory System"
   - OAuth Redirect URI: `https://yourapp.com/amazon/callback`

3. **Get Credentials**
   - **LWA Client ID**: From app registration
   - **LWA Client Secret**: From app registration
   - **Refresh Token**: Generate via OAuth flow
   - **AWS Access Key ID**: Create in IAM
   - **AWS Secret Access Key**: From IAM
   - **Role ARN**: SP-API execution role

4. **Create IAM Role**
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Principal": {
         "AWS": "arn:aws:iam::437568002678:user/SellingPartnerAPI"
       },
       "Action": "sts:AssumeRole"
     }]
   }
   ```

5. **Add to System**
   - Admin panel → Marketplace Integrations
   - Choose "Amazon"
   - Enter ALL credentials (5 fields!)
   - Test connection

#### How Sync Works
```
Your System → Amazon SP-API
├─ Get OAuth token via LWA
├─ Sign request with AWS Signature V4
├─ PATCH listings endpoint
├─ Update fulfillment_availability quantity
└─ Amazon updates listing (may take 15-30 min)
```

#### Limitations
- **Most complex setup** (5 credential fields)
- Requires AWS knowledge
- Sync not instant (15-30 minute delay)
- Must create listing first (or link to ASIN)
- Trading cards require specific product type
- Professional seller account required

#### Amazon-Specific Challenges
1. **Product Type**: Must use "TRADING_CARDS" type
2. **ASIN Matching**: Need to find/create ASINs
3. **Category Approval**: May need approval to sell cards
4. **Condition Requirements**: Must specify condition type
5. **Throttling**: Amazon heavily rate-limits

---

## How It All Works Together

### When You Update Quantity

```
Staff: Changes "Silvie" from 5 → 3 in ManageInventory

InventoryObserver detects change
    ↓
Finds all enabled marketplaces for store
    ↓
┌─────────┬─────────┬─────────┬─────────┐
│ Shopify │  eBay   │ TCGPlayer│ Amazon │
│ ✅ Sync  │ ✅ Sync │ ⚠️ Skip  │ ✅ Sync │
└─────────┴─────────┴─────────┴─────────┘
         (TCGPlayer skipped because product not mapped)
    ↓
Results logged in inventory.sync_status
```

### Automatic Sync Logic

Each service checks:
1. **Is integration enabled?** → If no, skip
2. **Does card have required IDs?** → If no, log warning
3. **Send API request** → If fail, save error
4. **Update sync status** → synced/failed with timestamp

### Error Handling

All errors are gracefully handled:
- ❌ API down → Status: "failed", Error saved
- ⚠️ Missing SKU → Status: "pending", Warning logged
- ✅ Success → Status: "synced", Timestamp updated

---

## Comparison Table

| Feature | Shopify | eBay | TCGPlayer | Amazon |
|---------|---------|------|-----------|--------|
| **Setup Difficulty** | ⭐ Easy | ⭐⭐ Medium | ⭐⭐⭐ Hard | ⭐⭐⭐⭐ Very Hard |
| **Credential Count** | 2 fields | 3 fields | 2 fields | 5 fields |
| **Sync Speed** | Instant | Instant | ~1 min | 15-30 min |
| **Rate Limits** | High | Medium | Medium | Low |
| **Requires SKU** | No | Yes | No* | Yes |
| **Product Mapping** | Auto | Auto | Manual | Manual |
| **Best For** | Your store | General market | Card collectors | Mass market |
| **Monthly Cost** | $29+ | Free | Free (listing fee) | $39.99 |

*TCGPlayer uses Product IDs, not SKUs

---

## Testing Strategy

### 1. Test Each Platform Individually

```php
// In tinker or admin panel
$integration = MarketplaceIntegration::find(1);

// Test connection
$service = new ShopifyInventorySync($integration);
$success = $service->testConnection(); // true/false

// Test single card sync
$inventory = Inventory::first();
$service->syncInventory($inventory);

// Check results
$inventory->fresh()->sync_status; // 'synced' or 'failed'
$inventory->sync_error; // null or error message
```

### 2. Test Auto-Sync

```php
// Update quantity
$inventory = Inventory::find(1);
$inventory->update(['quantity' => 99]);

// Observer fires automatically
// Check logs: storage/logs/laravel.log
// Look for "Synced inventory to {marketplace}"
```

### 3. Monitor Sync Status

Admin panel shows:
- ✅ Green checkmark = Synced successfully
- ⏳ Yellow dot = Pending sync
- ❌ Red X = Sync failed (click to see error)

---

## Common Issues & Solutions

### All Platforms

**Issue**: "Card missing SKU"
- **Fix**: Add SKU to card record
- **Why**: Most marketplaces require unique SKU

**Issue**: "Sync status stuck on pending"
- **Fix**: Check `storage/logs/laravel.log`
- **Why**: Sync may have failed silently

### eBay-Specific

**Issue**: "Inventory item not found"
- **Fix**: Service auto-creates, but may fail on first sync
- **Solution**: Manually trigger sync twice

**Issue**: "Sandbox mode not working"
- **Fix**: Toggle `sandbox_mode` in settings
- **Endpoint changes**: `api.sandbox.ebay.com`

### TCGPlayer-Specific

**Issue**: "Product ID not found"
- **Fix**: Use `findProduct()` to search
- **Manual**: Look up on TCGPlayer website

**Issue**: "Game not supported"
- **Solution**: Only sync games TCGPlayer lists
- **Check**: https://www.tcgplayer.com/search/product

### Amazon-Specific

**Issue**: "Access denied / Unauthorized"
- **Fix**: Regenerate OAuth token
- **Token expires**: Every 60 minutes

**Issue**: "Invalid signature"
- **Fix**: Check AWS credentials
- **Why**: AWS Signature V4 is complex

**Issue**: "Listing not found"
- **Fix**: Create listing first via `createListing()`
- **Or**: Match to existing ASIN

---

## Performance Considerations

### Sync Speeds
- **Shopify**: <1 second
- **eBay**: <2 seconds
- **TCGPlayer**: ~5 seconds (search required)
- **Amazon**: 15-30 minutes (not instant!)

### Rate Limits
Handle with queue if high volume:

```php
// In InventoryObserver
dispatch(new SyncToMarketplace($inventory, $integration))
    ->onQueue('marketplace-sync');
```

### Batch Operations
Disable observer for bulk imports:

```php
Inventory::withoutEvents(function() {
    // Bulk update
    Inventory::query()->update(['quantity' => 0]);
});

// Then trigger batch sync
dispatch(new BulkSyncAllMarketplaces($storeId));
```

---

## Next Steps

1. **Start with Shopify** (easiest, most important)
2. **Add eBay** if you want broader reach
3. **Add TCGPlayer** if your games are supported
4. **Add Amazon** only if you have dev resources (complex!)

Each integration is **independent** - one failing doesn't affect others!

---

## Support & Documentation

### API Documentation
- **Shopify**: https://shopify.dev/docs/api/admin-graphql
- **eBay**: https://developer.ebay.com/api-docs/sell/inventory/overview.html
- **TCGPlayer**: https://docs.tcgplayer.com/docs/seller-api
- **Amazon**: https://developer-docs.amazon.com/sp-api/

### Code Files
- `app/Services/Marketplace/ShopifyInventorySync.php`
- `app/Services/Marketplace/EbayInventorySync.php`
- `app/Services/Marketplace/TcgPlayerInventorySync.php`
- `app/Services/Marketplace/AmazonInventorySync.php`
- `app/Observers/InventoryObserver.php`

### Testing
All services include `testConnection()` method:
- Call from admin panel "Test" button
- Or test in tinker/terminal
- Returns true/false + logs details

---

## Summary

✅ **Database**: Ready for all marketplaces (no changes needed)
✅ **Services**: All 4 marketplace sync classes created
✅ **Observer**: Auto-syncs to all enabled marketplaces
✅ **Admin UI**: Test/manage all integrations
✅ **Error Handling**: Graceful failures with logging

**You're ready to onboard clients to any marketplace!** 🚀
