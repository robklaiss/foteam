# Guide to Upload FoTeam to Root Domain

This guide will help you upload your FoTeam application to the root of your domain (https://bebe.com.py) instead of using a subdirectory structure.

## Files to Upload

1. **Upload the entire contents of the `/public` directory to your root domain directory**
   - All PHP files in the public directory should go directly to the root
   - This includes index.php, cart.php, checkout.php, etc.

2. **Create these directories in your root domain:**
   - `/includes` - Upload all content from `/foteam_unified/includes/`
   - `/uploads` - Upload all content from `/foteam_unified/uploads/`
   - `/database` - Upload all content from `/foteam_unified/database/`
   - `/public/assets` - Upload all content from `/foteam_unified/public/assets/`
   - `/public/payment` - Upload all content from `/foteam_unified/public/payment/`

3. **Maintain the directory structure** for important subdirectories:
   - Make sure `/uploads/thumbnails` exists and has correct permissions (755)
   - Make sure the `/database` directory has write permissions (755)

## Special Files

1. **Upload the .htaccess file** to the root domain
2. **Upload the .env file** to the root domain with proper environment variables:
   ```
   BANCARD_PUBLIC_KEY=vBvkW7xkVoCxsogzVAgcIgXeW4DveMYH
   BANCARD_PRIVATE_KEY=ToQSzGYQKLiopicQKtZtKpQ.m4iPE8XElg6W706e
   BANCARD_ENVIRONMENT=staging
   BANCARD_API_URL_STAGING=https://vpos.infonet.com.py:8888
   BANCARD_API_URL_PRODUCTION=https://vpos.infonet.com.py
   BANCARD_RETURN_URL=https://bebe.com.py/payment/success.php
   BANCARD_CANCEL_URL=https://bebe.com.py/payment/cancel.php
   GOOGLE_CLOUD_API_KEY=a46ade53287b4bbf8be673e9e813a6b811d961ad
   ```

## Verify Uploads

After uploading, check these files to verify proper operation:
1. Visit https://bebe.com.py/verify_bancard_config.php
2. Visit https://bebe.com.py/bancard_debug.php
3. Run the migration to create the photographer_marathons table: https://bebe.com.py/run_migration.php

## Common Issues

If you encounter issues:

1. **404 Errors**: Make sure all files from `/public` are uploaded to the root directory
2. **Path Problems**: Make sure the new .htaccess file is properly uploaded
3. **Database Connection Errors**: Verify that database credentials are correct
4. **Permission Issues**: Ensure uploads and database directories are writable (chmod 755)

## PHP Requirements

Make sure your server has:
- PHP 7.4 or higher
- SQLite3 extension enabled
- GD library for image processing
- Curl extension for API communication
