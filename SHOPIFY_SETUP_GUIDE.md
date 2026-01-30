# 🚀 Quick Start Guide: Connect Your Store to Shopify

## For Store Administrators

This guide will walk you through connecting your card inventory system to your Shopify store in **less than 5 minutes**.

---

## What You'll Need

- Access to your Shopify Admin panel
- Admin access to this card inventory system
- 5 minutes of time

---

## Step-by-Step Setup

### 1️⃣ Access Marketplace Setup

In your card inventory admin panel:
1. Click **"Administration"** in the left sidebar
2. Click **"Marketplace Setup"** or **"Marketplace Integrations"**
3. You'll see the setup wizard

---

### 2️⃣ Create a Shopify Private App

Open a new browser tab and go to your Shopify Admin:

1. **Navigate to Settings**
   - Click your store name (bottom left)
   - Click "Settings"

2. **Go to Apps Section**
   - Click "Apps and sales channels" in the left menu

3. **Enable App Development** (First time only)
   - Click "Develop apps"
   - If you see a message about enabling, click "Allow custom app development"
   - Click "Allow custom app development" again to confirm

4. **Create New App**
   - Click the **"Create an app"** button
   - App name: `Card Inventory Sync`
   - App developer: (Your name)
   - Click **"Create app"**

5. **Configure API Scopes**
   - Click **"Configure Admin API scopes"**
   - Scroll down and check these 4 boxes:
     - ✅ `read_inventory` - Read inventory
     - ✅ `write_inventory` - Edit inventory
     - ✅ `read_products` - Read products
     - ✅ `write_products` - Edit products
   - Click **"Save"** (top right)

6. **Install the App**
   - Click **"Install app"** button
   - Click **"Install"** to confirm
   - ⚠️ **IMPORTANT**: Copy the **Admin API access token** that appears
   - It looks like: `shpat_xxxxxxxxxxxxxxxxxxxxx`
   - You can only see this ONCE, so copy it now!

---

### 3️⃣ Enter Credentials in Inventory System

Go back to your card inventory system's Marketplace Setup page:

1. **Choose Store**: Select your store from the dropdown

2. **Choose Marketplace**: Select "Shopify"

3. **Enter Credentials**:
   - **Shop URL**: Enter your full Shopify URL
     - Format: `https://your-store-name.myshopify.com`
     - Example: `https://cards-unlimited.myshopify.com`
   - **Admin API Access Token**: Paste the token you copied
     - Starts with `shpat_`
   - **Enable Automatic Sync**: Leave this ON (recommended)

4. **Click "Save & Test Connection"**

✅ If successful, you'll see: **"Integration Saved & Tested - Successfully connected to Shopify!"**

❌ If it fails:
- Double-check your Shop URL (must include https://)
- Verify you copied the entire access token
- Make sure you enabled all 4 API scopes

---

## What Happens Next?

### Automatic Inventory Sync

From now on, whenever you or your staff:
- ✏️ Update a card's quantity in the inventory system
- ➕ Add new inventory
- ➖ Remove inventory

The system will **automatically push the change to Shopify** within seconds!

### First-Time Setup

For cards that don't exist in Shopify yet:
1. The system will create them automatically
2. It uses the card name, set, type, and pricing you've entered
3. SKUs are generated automatically

For cards that already exist in Shopify:
1. You'll need to map them (we'll add a bulk import tool soon)
2. Or they'll be created as new products

---

## Testing Your Integration

### Quick Test

1. Go to **"Manage Inventory"** in your admin panel
2. Find any card and change its quantity
3. Wait 5 seconds
4. Check your Shopify Admin → Products → Inventory
5. The quantity should match!

### View Sync Status

In **"Inventory"** resource:
- Look for the sync status badges
- ✅ Green = Synced successfully
- ⏳ Yellow = Pending sync
- ❌ Red = Sync failed (click to see error)

---

## Managing Multiple Locations

If you have multiple store locations:

1. Each location can have its own Shopify location ID
2. Set this in the integration settings
3. Or leave blank to sync to Shopify's default location

To find your Shopify Location IDs:
- We'll add a tool to fetch these automatically soon!

---

## Adding More Marketplaces

Want to sync to eBay, TCGPlayer, or Amazon too?

1. Go back to **"Marketplace Setup"**
2. Fill out the wizard again
3. Choose a different marketplace
4. Enter those credentials
5. Save!

Each marketplace syncs independently, so you can:
- Disable Shopify temporarily without affecting eBay
- Test one marketplace at a time
- Enable/disable any marketplace anytime

---

## Troubleshooting

### ❌ "Connection Failed"

**Check these:**
- Is your Shop URL correct? (must include `https://` and `.myshopify.com`)
- Did you copy the full access token?
- Did you enable all 4 API scopes? (read/write inventory, read/write products)
- Is your Shopify app installed?

**How to fix:**
1. Go back to Shopify Admin → Apps → Develop apps
2. Click your "Card Inventory Sync" app
3. Click "API credentials" tab
4. Verify scopes are enabled (should see 4 checkmarks)
5. If needed, click "Reveal token once" to get a new token

### ❌ "Sync Failed" on Specific Cards

**Common causes:**
- Card doesn't have a SKU assigned
- Shopify product was deleted
- Card name has special characters Shopify doesn't allow

**How to fix:**
1. Click the card in Inventory table
2. Check sync error message
3. Fix the issue (add SKU, fix name, etc.)
4. Click "Sync Now" button

### ⚠️ Quantities Don't Match

**This happens when:**
- Someone updated Shopify directly (not through your system)
- There was a temporary connection issue
- Multiple people edited at the same time

**How to fix:**
1. Go to **"Marketplace Integrations"**
2. Find your Shopify integration
3. Click **"Sync Now"** to force a full resync
4. All quantities will be pushed from your system to Shopify

---

## Security & Privacy

### Is My Data Safe?

✅ **YES!** Your credentials are:
- Encrypted in the database
- Never exposed in logs
- Only accessible to admin users
- Stored securely using Laravel's encryption

### Can I Revoke Access?

Yes! At any time:
1. In your inventory system: Click "Delete" on the integration
2. In Shopify: Go to Apps → Develop apps → Your app → "Delete app"

Both methods immediately stop all syncing.

---

## Getting Help

### Need Support?

**Check the logs:**
- Go to your server
- Look in `storage/logs/laravel.log`
- Search for "Shopify" to see detailed sync activity

**Contact your developer:**
- They can check the database
- Review sync status for all cards
- Trigger manual syncs if needed

### Feature Requests

Want to see something added?
- Let us know what marketplace you need
- Suggest improvements to the sync logic
- Request bulk operations (import all Shopify products, etc.)

---

## Advanced Features (Coming Soon)

- 📥 Import existing Shopify products
- 🔄 Bi-directional sync (Shopify → Your system)
- 📊 Sync analytics dashboard
- 🎨 Custom product templates
- 📷 Automatic image uploads
- 💰 Price sync rules (auto-adjust based on market)

---

## Congratulations! 🎉

You've successfully connected your card inventory to Shopify!

Your inventory will now sync automatically, saving you hours of manual updates every week.

**Next Steps:**
- Test with a few cards
- Train your staff on the system
- Set up additional marketplaces (eBay, TCGPlayer)
- Customize sync settings as needed

Happy selling! 🃏💰
