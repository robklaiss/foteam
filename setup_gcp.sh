#!/bin/bash

# Install Google Cloud SDK
brew install --cask google-cloud-sdk

# Initialize GCP configuration
gcloud init
gcloud services enable vision.googleapis.com

# Create service account
gcloud iam service-accounts create runner-detection-sa \
    --description="Service account for runner number detection" \
    --display-name="Runner Detection SA"

# Assign permissions
gcloud projects add-iam-policy-binding $(gcloud config get-value project) \
    --member="serviceAccount:runner-detection-sa@$(gcloud config get-value project).iam.gserviceaccount.com" \
    --role="roles/vision.admin"

# Generate key file
gcloud iam service-accounts keys create service-account-key.json \
    --iam-account=runner-detection-sa@$(gcloud config get-value project).iam.gserviceaccount.com

# Create GCS bucket
GCS_BUCKET_NAME="runner-detection-$(gcloud config get-value project)"
gsutil mb -p $(gcloud config get-value project) -l us-central1 gs://$GCS_BUCKET_NAME
gsutil uniformbucketlevelaccess set on gs://$GCS_BUCKET_NAME
gsutil iam ch serviceAccount:runner-detection-sa@$(gcloud config get-value project).iam.gserviceaccount.com:legacyBucketWriter gs://$GCS_BUCKET_NAME

# Update environment file
echo "GOOGLE_APPLICATION_CREDENTIALS=\"$(pwd)/service-account-key.json\"" >> .env
echo "GCS_BUCKET_NAME=\"$GCS_BUCKET_NAME\"" >> .env
