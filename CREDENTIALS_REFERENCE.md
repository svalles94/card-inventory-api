# Quick Reference: Marketplace Integration Credentials

## Where to Get API Credentials

### 🛍️ Shopify (5 minutes)
**Portal**: https://admin.shopify.com → Settings → Apps → Develop apps

| Field | How to Get | Example |
|-------|-----------|---------|
| Shop URL | Your store domain | `https://cards-r-us.myshopify.com` |
| Access Token | Create app → Install → Copy token | `shpat_xxxxxxxxxxxxx` |

**Required Scopes**: `read_inventory`, `write_inventory`, `read_products`, `write_products`

---

### 🏪 eBay (30 minutes)
**Portal**: https://developer.ebay.com → My Account → Application Keys

| Field | How to Get | Example |
|-------|-----------|---------|
| Client ID (App ID) | Keyset → Production → App ID | `YourName-CardInve-PRD-axxxxx` |
| Client Secret (Cert ID) | Keyset → Production → Cert ID | `PRD-axxxxx-xxxxx` |
| OAuth Token | OAuth tool → Generate token | User token from OAuth flow |

**Sandbox Toggle**: Available for testing with fake data

**OAuth Flow**: https://developer.ebay.com/my/auth/?env=production

---

### 🃏 TCGPlayer (1-2 weeks approval)
**Portal**: https://seller.tcgplayer.com → API Management

| Field | How to Get | Example |
|-------|-----------|---------|
| API Key | Request API access → Wait for approval → Copy key | Long base64 string |
| Seller Key | Same portal → Private seller key | Unique seller identifier |

**Note**: Requires approved seller account. Cannot use without verification.

**Pricing Strategy Options**:
- `market` - Match TCGPlayer market price
- `below_market` - 5% below market
- `fixed` - Use your system's sell_price

---

### 📦 Amazon (1 day + AWS setup)
**Portal**: https://sellercentral.amazon.com → Apps & Services → Develop apps

| Field | How to Get | Example |
|-------|-----------|---------|
| Merchant ID | Seller Central → Account Info → Merchant Token | `A1234EXAMPLE` |
| Marketplace ID | Based on region | `ATVPDKIKX0DER` (US) |
| Access Key ID | AWS IAM → Create user for SP-API | `AKIA...` |
| Secret Access Key | AWS IAM → User → Security credentials | Secret key (copy once) |
| Role ARN | IAM → Create SP-API execution role | `arn:aws:iam::...` |

**Additional Requirement**: LWA (Login with Amazon) app registration

**Complex Setup!** Requires:
1. Amazon Professional Seller account ($39.99/mo)
2. SP-API registration
3. AWS IAM user creation
4. IAM role for SP-API
5. LWA credentials

**Marketplace IDs by Region**:
- US: `ATVPDKIKX0DER`
- CA: `A2EUQ1WTGCTBG2`
- UK: `A1F83G8C2ARO7P`
- DE: `A1PA6795UKMFR9`

---

## Testing Credentials

### In Admin Panel
1. Go to **Marketplace Integrations**
2. Find your integration
3. Click **"Test"** button
4. See instant success/failure notification

### In Terminal/Tinker
```php
// Test Shopify
$integration = MarketplaceIntegration::where('marketplace', 'shopify')->first();
$service = new \App\Services\Marketplace\ShopifyInventorySync($integration);
$success = $service->testConnection(); // true/false

// Test eBay
$service = new \App\Services\Marketplace\EbayInventorySync($integration);
$service->testConnection();

// Test TCGPlayer
$service = new \App\Services\Marketplace\TcgPlayerInventorySync($integration);
$service->testConnection();

// Test Amazon
$service = new \App\Services\Marketplace\AmazonInventorySync($integration);
$service->testConnection();
```

---

## Security Notes

### Encryption
All credentials are automatically encrypted using Laravel's encryption:
```php
'credentials' => 'encrypted:array'
```

### Storage Location
- **Database**: `marketplace_integrations.credentials` (JSON, encrypted)
- **Never in logs**: Credentials are masked in error messages
- **Admin only**: Only users with admin role can view

### Revoking Access
**To disable sync without deleting**:
- Toggle "Enable Sync" to OFF

**To remove completely**:
1. Admin panel → Delete integration
2. Go to marketplace portal → Revoke API access
3. Shopify: Delete the app
4. eBay: Revoke user token
5. TCGPlayer: Contact support
6. Amazon: Remove app permissions

---

## Credential Validation

### Valid Credential Examples

**Shopify Shop URL**:
- ✅ `https://store.myshopify.com`
- ✅ `https://my-store-123.myshopify.com`
- ❌ `store.myshopify.com` (missing https://)
- ❌ `myshopify.com` (not a store URL)

**Shopify Access Token**:
- ✅ `shpat_1234567890abcdef`
- ✅ `shpat_...` (starts with shpat_)
- ❌ `1234567890` (not a valid token)

**eBay Client ID**:
- ✅ `YourName-AppName-PRD-a12345678`
- ✅ `YourName-AppName-SBX-a12345678` (sandbox)
- Pattern: `{name}-{name}-{env}-{id}`

**eBay OAuth Token**:
- Format: Long string, starts with `v^1.1#...`
- Expires: Check with eBay's token introspection

**Amazon Role ARN**:
- ✅ `arn:aws:iam::123456789012:role/SPAPIRole`
- Pattern: `arn:aws:iam::{account}:role/{name}`

---

## Troubleshooting Credentials

### "Invalid credentials" errors

**Check**:
1. **Copied completely?** Tokens are long, easy to cut off
2. **Spaces?** Trim whitespace before/after
3. **Expired?** Some tokens expire (Amazon, eBay OAuth)
4. **Correct environment?** Production vs. Sandbox

### "Access denied" errors

**Check**:
1. **Scopes enabled?** Shopify requires 4 specific scopes
2. **App installed?** Must click "Install app" in Shopify
3. **Account approved?** TCGPlayer requires seller verification
4. **Permissions?** Amazon requires IAM role trust relationship

### "Connection timeout" errors

**Check**:
1. **Firewall?** Server must allow outbound HTTPS
2. **Correct URL?** Sandbox vs. production endpoints
3. **API down?** Check marketplace status pages

---

## Credential Rotation

### When to Rotate

**Shopify**: Never expires, but rotate if:
- Employee leaves with access
- Token accidentally exposed
- Suspicious API activity

**eBay**: OAuth token expires, refresh via:
```php
// Implement token refresh logic
$newToken = refreshEbayToken($oldToken);
```

**TCGPlayer**: Keys don't expire but rotate annually as best practice

**Amazon**: Access keys should rotate every 90 days (AWS best practice)

### How to Rotate

1. Generate new credentials in marketplace portal
2. Update in admin panel **before** deleting old
3. Test connection with new credentials
4. Delete old credentials from marketplace portal
5. Monitor logs for any failed syncs

---

## Multi-Store Setup

### Same Marketplace, Different Stores

Each store gets its own credentials:

```
Store A (Location: Downtown)
├─ Shopify: downtown-cards.myshopify.com
├─ eBay: downtown_seller account
└─ TCGPlayer: Downtown Cards seller

Store B (Location: Mall)
├─ Shopify: mall-cards.myshopify.com
├─ eBay: mall_location account
└─ TCGPlayer: Mall Cards seller
```

### Same Store, Multiple Marketplaces

One store can sync to all platforms:

```
Cards Unlimited
├─ Shopify ✅ (Main website)
├─ eBay ✅ (Auction listings)
├─ TCGPlayer ✅ (Card-specific)
└─ Amazon ❌ (Disabled - not worth complexity)
```

---

## Quick Start Checklist

### For Each New Client

- [ ] Determine which marketplaces they need
- [ ] Send them credential instructions (per platform)
- [ ] They create API apps and copy credentials
- [ ] Enter credentials in admin panel
- [ ] Click "Test Connection" - must see ✅
- [ ] Update one test card quantity
- [ ] Verify it synced to marketplace (check their admin)
- [ ] Enable auto-sync
- [ ] Done! 🎉

**Estimated Time**:
- Shopify only: 5 minutes
- + eBay: +30 minutes
- + TCGPlayer: +5 minutes (if already approved)
- + Amazon: +2 hours (complex setup)

---

## Support Contacts

### If Credentials Don't Work

**Shopify**:
- Help: https://help.shopify.com
- Forum: https://community.shopify.com/c/shopify-apis-and-sdks/bd-p/shopify-apis-and-technology

**eBay**:
- Developer support: https://developer.ebay.com/support
- Forum: https://community.ebay.com/t5/Developer-s-Forum/ct-p/developer-forum

**TCGPlayer**:
- Seller support: https://help.tcgplayer.com/hc/en-us/categories/115000099346-Selling
- Email: sellers@tcgplayer.com

**Amazon**:
- SP-API docs: https://developer-docs.amazon.com/sp-api/
- Case log: Seller Central → Help → Contact Us
- GitHub: https://github.com/amzn/selling-partner-api-models

---

## API Credential Storage Schema

```json
// Shopify
{
  "shop_url": "https://store.myshopify.com",
  "access_token": "shpat_xxxxx"
}

// eBay
{
  "client_id": "YourApp-PRD-xxxxx",
  "client_secret": "PRD-xxxxx",
  "oauth_token": "v^1.1#xxxxx"
}

// TCGPlayer
{
  "api_key": "base64_encoded_key",
  "seller_key": "seller_unique_id"
}

// Amazon
{
  "merchant_id": "A1234EXAMPLE",
  "marketplace_id": "ATVPDKIKX0DER",
  "access_key_id": "AKIA...",
  "secret_access_key": "secret...",
  "role_arn": "arn:aws:iam::...",
  "refresh_token": "Atzr|..." // For LWA
}
```

All stored encrypted in `marketplace_integrations.credentials` column.
