# IMPORTANT NOTE: buyers Table Migration

## Files Still Containing 'buyers' Table References

The following files still contain references to the old 'buyers' table but are NOT actively used in the main application:

1. **erp_database_setup.php** - Legacy setup script with buyers table definition
2. **create_complete_erp_schema.sql** - Complete schema definition (includes buyers for reference)
3. **drop_buyers_table.sql** - Helper script created to drop buyers table safely

## Action Required

To completely remove the buyers table from your database:

1. First, run the SQL commands in `drop_buyers_table.sql` to:
   - Drop foreign key constraints
   - Drop the buyers table

2. The main application now uses `buyer_profiles` table instead

3. The legacy setup files (erp_database_setup.php, create_complete_erp_schema.sql) are kept for reference but should not be used for new installations

## Migration Status: COMPLETE

✅ All active application files have been migrated to use buyer_profiles
✅ All SQL queries updated
✅ All foreign keys updated in active setup scripts
✅ Documentation updated

The only remaining references are in legacy/unused setup files.
