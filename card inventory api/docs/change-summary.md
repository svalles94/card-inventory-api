# Change Summary (feature/marketplace-integrations)

Date: 2025-12-27

## Highlights
- Added `edition_id` support to inventory (new migration) and updated unique key to include edition + foil + location.
- Queue Update modal now shows card art, edition-specific market price, foil shimmer, and foil-aware pricing; edition selection/prefill wiring updated.
- Queue storage now persists edition/foil, images, and recent prices; queue apply logic keys by card+edition+foil+location.
- Queue review page now displays card art, edition label, foil status, and market price; hydrates missing data from DB to avoid re-entry.

## Key Files Touched
- `database/migrations/2025_12_27_120000_add_edition_to_inventory_table.php`
- `app/Models/Inventory.php` (edition relation/fillable)
- `app/Support/InventoryUpdateQueue.php`
- `app/Filament/Store/Resources/StoreCardResource.php`
- `app/Filament/Store/Pages/InventoryUpdateQueuePage.php`

## Notes / Actions
- Run migrations before use: `php artisan migrate` (or Sail equivalent).
- Test the flow: Cards → Queue Update modal (choose edition, foil, set delta/sell price) → Inventory Update Queue page → Apply.
- Foil pricing uses edition price records and falls back to card-level prices if needed.
