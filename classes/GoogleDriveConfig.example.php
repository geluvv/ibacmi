<?php
/**
 * Google Drive OAuth Configuration Template
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy this file and rename it to: GoogleDriveConfig.php
 * 2. Replace the placeholder values with your actual Google OAuth credentials
 * 3. Never commit GoogleDriveConfig.php to version control
 * 
 * To get your credentials:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select an existing one
 * 3. Enable the Google Drive API
 * 4. Create OAuth 2.0 credentials
 * 5. Copy your Client ID and Client Secret here
 */

return [
    'client_id' => 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com',
    'client_secret' => 'YOUR_CLIENT_SECRET_HERE',
    'redirect_uri' => 'http://localhost/ibacmi/oauth2callback.php'
];
