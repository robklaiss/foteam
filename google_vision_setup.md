# Setting Up Google Cloud Vision API for Runner Number Detection

This guide will help you set up the Google Cloud Vision API integration for automatically detecting runner numbers in marathon photos.

## Prerequisites

1. A Google Cloud Platform account
2. A project with billing enabled

## Setup Steps

### 1. Create a Google Cloud Project

If you don't already have a project:

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Click on "Select a project" at the top of the page
3. Click "New Project"
4. Enter a name for your project and click "Create"

### 2. Enable the Vision API

1. Go to the [API Library](https://console.cloud.google.com/apis/library)
2. Search for "Vision API"
3. Click on "Cloud Vision API"
4. Click "Enable"

### 3. Create a Service Account and Download Credentials

1. Go to [IAM & Admin > Service Accounts](https://console.cloud.google.com/iam-admin/serviceaccounts)
2. Click "Create Service Account"
3. Enter a name for your service account and click "Create and Continue"
4. For the role, select "Project > Owner" (or a more restricted role if preferred)
5. Click "Continue" and then "Done"
6. Find your new service account in the list and click on it
7. Go to the "Keys" tab
8. Click "Add Key" > "Create new key"
9. Select "JSON" and click "Create"
10. Save the downloaded JSON key file

### 4. Configure the Application

1. Place the downloaded JSON key file in the `/credentials` directory of your application
2. Rename it to `google-cloud-key.json` or update the path in `includes/google_vision_config.php`
3. Open `includes/google_vision_config.php` and update the `GOOGLE_CLOUD_PROJECT_ID` with your project ID

## Testing

After setup, upload a photo with visible runner numbers to test the detection. The detected numbers will be stored in the database and can be viewed in the image details.

## Troubleshooting

- If you encounter authentication errors, verify that the service account key file is correctly placed and has the proper permissions
- Check the error logs for specific API error messages
- Ensure that billing is enabled for your Google Cloud project
