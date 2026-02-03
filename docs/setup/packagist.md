# Packagist Setup Guide

## 1. Create Packagist Account

1. Go to [packagist.org](https://packagist.org)
2. Click "Sign in with GitHub"
3. Authorize Packagist to access your GitHub account
4. Complete profile setup if prompted

## 2. Submit Package (First Time Only)

1. Go to [Submit Package](https://packagist.org/packages/submit)
2. Enter repository URL: `https://github.com/birdcar/laravel-label-graph`
3. Click "Check" to validate
4. Click "Submit" to add package

**Note**: The package will be named `birdcar/laravel-label-graph` based on composer.json.

## 3. Generate API Token

1. Go to [Profile Settings](https://packagist.org/profile/)
2. Scroll to "API Tokens" section
3. Click "Generate new token"
4. Name it: `github-actions-release`
5. Copy the token immediately (it won't be shown again)

## 4. Add GitHub Secret

1. Go to repository Settings > Secrets and variables > Actions
2. Click "New repository secret"
3. Name: `PACKAGIST_API_TOKEN`
4. Value: Paste the API token from step 3
5. Click "Add secret"

## 5. Verify Setup

After creating your first release:
1. Check [packagist.org/packages/birdcar/laravel-label-graph](https://packagist.org/packages/birdcar/laravel-label-graph)
2. Verify the new version appears
3. Test: `composer require birdcar/laravel-label-graph:^0.1`

## Troubleshooting

### "Package not found"
- Ensure the package is submitted to Packagist first
- Wait a few minutes for indexing

### "401 Unauthorized"
- Verify the API token is correct
- Regenerate token if needed
- Check secret name matches exactly

### "Package not updated"
- Packagist webhook may be delayed
- Manual trigger: Go to package page > "Update" button
