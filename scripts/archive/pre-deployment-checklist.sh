#!/bin/bash
# Pre-Deployment Checklist for NGN 2.0
# Run this before deploying to production or beta environments

set -e

echo "================================"
echo "NGN Pre-Deployment Checklist"
echo "================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

fail_count=0
pass_count=0

check_pass() {
    echo -e "${GREEN}✅ $1${NC}"
    ((pass_count++))
}

check_fail() {
    echo -e "${RED}❌ $1${NC}"
    ((fail_count++))
}

# Check 1: .env file exists
echo "Checking .env file..."
if [ ! -f ".env" ]; then
    check_fail ".env file not found"
else
    check_pass ".env file exists"
fi
echo ""

# Check 2: Required environment variables
echo "Checking required environment variables..."
required_vars=("DB_HOST" "DB_NAME" "DB_USER" "DB_PASSWORD" "NGN_VERSION" "APP_ENV")
for var in "${required_vars[@]}"; do
    if grep -q "^$var=" .env 2>/dev/null; then
        check_pass "$var is set"
    else
        check_fail "$var is missing from .env"
    fi
done
echo ""

# Check 3: Storage directories exist and are writable
echo "Checking storage directories..."
for dir in "storage/logs" "storage/cache" "storage/uploads" "storage/certificates"; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            check_pass "$dir exists and is writable"
        else
            check_fail "$dir exists but is not writable"
        fi
    else
        check_fail "$dir does not exist"
    fi
done
echo ""

# Check 4: Composer vendor directory exists
echo "Checking Composer dependencies..."
if [ -d "vendor" ]; then
    check_pass "Composer vendor directory exists"
else
    check_fail "Composer vendor directory missing - run 'composer install'"
fi
echo ""

# Check 5: PHP version >= 8.0
echo "Checking PHP version..."
php_version=$(php -v | head -n 1 | awk '{print $2}')
php_major=$(echo $php_version | cut -d. -f1)
if [ "$php_major" -ge 8 ]; then
    check_pass "PHP version is $php_version (>= 8.0)"
else
    check_fail "PHP version is $php_version (requires >= 8.0)"
fi
echo ""

# Check 6: Database connection
echo "Checking database connection..."
if php -r "
require_once 'lib/bootstrap.php';
try {
    \$config = new \NGN\Lib\Config();
    \$pdo = \NGN\Lib\DB\ConnectionFactory::read(\$config);
    \$stmt = \$pdo->query('SELECT 1');
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; then
    check_pass "Database connection successful"
else
    check_fail "Database connection failed"
fi
echo ""

# Check 7: Critical tables exist
echo "Checking critical database tables..."
tables=("artists" "users" "entity_scores" "content_ledger")
for table in "${tables[@]}"; do
    if php -r "
require_once 'lib/bootstrap.php';
\$config = new \NGN\Lib\Config();
\$pdo = \NGN\Lib\DB\ConnectionFactory::read(\$config);
try {
    \$stmt = \$pdo->query('SELECT 1 FROM \`' . \$table . '\` LIMIT 1');
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; then
        check_pass "Table $table exists"
    else
        check_fail "Table $table missing or inaccessible"
    fi
done
echo ""

# Check 8: VersionBanner class exists
echo "Checking version banner component..."
if [ -f "lib/UI/VersionBanner.php" ]; then
    check_pass "VersionBanner class exists"
else
    check_fail "VersionBanner.php not found at lib/UI/VersionBanner.php"
fi
echo ""

# Check 9: SubdomainRouter class exists
echo "Checking subdomain routing component..."
if [ -f "lib/HTTP/SubdomainRouter.php" ]; then
    check_pass "SubdomainRouter class exists"
else
    check_fail "SubdomainRouter.php not found at lib/HTTP/SubdomainRouter.php"
fi
echo ""

# Check 10: .htaccess subdomain rules
echo "Checking .htaccess subdomain rules..."
if grep -q "api\.nextgennoise\.com" public/.htaccess; then
    check_pass "Subdomain routing rules found in .htaccess"
else
    check_fail "Subdomain routing rules not found in .htaccess"
fi
echo ""

# Check 11: health.php endpoint exists
echo "Checking health check endpoint..."
if [ -f "public/health.php" ]; then
    check_pass "Health check endpoint exists"
else
    check_fail "public/health.php not found"
fi
echo ""

# Summary
echo "================================"
echo "Pre-Deployment Checklist Summary"
echo "================================"
echo -e "Passed: ${GREEN}$pass_count${NC}"
echo -e "Failed: ${RED}$fail_count${NC}"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}All checks passed! ✅${NC}"
    echo "Ready for deployment."
    exit 0
else
    echo -e "${YELLOW}Fix the $fail_count failing check(s) before deploying.${NC}"
    exit 1
fi
