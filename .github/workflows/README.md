# GitHub Actions Workflows

This directory contains automated CI/CD workflows for testing and deploying your Laravel application.

## Workflows

### test.yml - Testing Pipeline

Runs automatically on:
- Every push to `main` and `develop` branches
- Every pull request to `main` and `develop` branches

**What it does:**
- ✅ Sets up PHP 8.3 with SQLite support
- ✅ Sets up Node.js 20
- ✅ Installs Composer and npm dependencies
- ✅ Builds frontend assets with Vite
- ✅ Runs Pint code style checks
- ✅ Runs Pest test suite
- ✅ Generates and uploads code coverage reports

**Required:** No manual setup needed—this runs automatically.

### deploy.yml - Deployment Pipeline

Runs automatically on:
- Push to `main` branch (after tests pass)
- Manual trigger via GitHub Actions UI

**What it does:**
- ✅ Runs the test pipeline first (won't deploy if tests fail)
- ✅ Prepares the application for production
- ✅ Deploys via SSH to your server
- ✅ Runs database migrations
- ✅ Clears caches and optimizes

**Required Setup:**

Before deployment works, add these secrets to your GitHub repository:

1. Go to: **Settings** → **Secrets and variables** → **Actions**
2. Create these repository secrets:

   | Secret | Value |
   |--------|-------|
   | `DEPLOY_SERVER` | Your server IP address or domain (e.g., `192.168.1.100` or `example.com`) |
   | `DEPLOY_USER` | SSH user on your server (e.g., `deploy` or `root`) |
   | `DEPLOY_KEY` | Your SSH private key for authentication |
   | `DEPLOY_PATH` | Absolute path to your app on the server (e.g., `/var/www/html/app`) |

**Setting up SSH Key:**

1. Generate an SSH key pair (if you don't have one):
   ```bash
   ssh-keygen -t rsa -b 4096 -f deploy_key -N ""
   ```

2. Add the public key to your server:
   ```bash
   ssh-copy-id -i deploy_key.pub user@server
   # or manually add deploy_key.pub to ~/.ssh/authorized_keys
   ```

3. Copy the **private key** content and add it as `DEPLOY_KEY` secret in GitHub

**Example Secrets:**
```
DEPLOY_SERVER=185.120.100.50
DEPLOY_USER=deploy
DEPLOY_KEY=-----BEGIN RSA PRIVATE KEY-----
            MIIEpAIBAAKCAQEA...
            (paste full private key here)
            ...-----END RSA PRIVATE KEY-----
DEPLOY_PATH=/home/deploy/app
```

## Testing Locally

To test this locally before pushing:

```bash
# Run code style checks
vendor/bin/pint --test

# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/YourTest.php

# Run with coverage
php artisan test --coverage
```

## Customizations

### Modify branch triggers
Edit the `on` sections in both workflows to change which branches trigger them:
```yaml
on:
  push:
    branches: [main, develop, staging]  # Add more branches
```

### Change PHP version
Edit the `php-version` in both workflows:
```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: 8.4  # Change version here
```

### Change Node.js version
Edit the `node-version`:
```yaml
- uses: actions/setup-node@v4
  with:
    node-version: 22  # Change version here
```

### Customize deployment steps
Edit the `script` section in deploy.yml to add/remove steps as needed:
```yaml
script: |
  cd ${{ env.DEPLOY_PATH }}
  # Add your custom deployment steps here
```

## Monitoring & Debugging

1. **View workflow runs:** Go to **Actions** tab in your GitHub repository
2. **Check job logs:** Click on a workflow run to see detailed logs
3. **Troubleshoot:** Look at the red ❌ step to find what failed
4. **Re-run workflow:** Click "Re-run jobs" to retry a failed deployment

## Security

- Never commit SSH keys or sensitive data
- Always use GitHub Secrets for credentials
- Use a dedicated deployment user with limited permissions
- Rotate SSH keys regularly
- Review deployment logs for unauthorized access attempts

## Troubleshooting

**"Permission denied" on deploy:**
- Verify SSH key is added to server's `~/.ssh/authorized_keys`
- Check that `DEPLOY_USER` has permissions on `DEPLOY_PATH`

**"composer install" fails:**
- Check `composer.lock` is committed
- Verify all dependencies are compatible with PHP 8.3

**Tests fail in CI but pass locally:**
- Check `.env.example` has correct test configuration
- Verify all required extensions are installed

**Database migration fails:**
- Check migrations are properly committed
- Verify database user has migration permissions
- Check disk space on server
