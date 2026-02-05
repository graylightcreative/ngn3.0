#!/bin/bash

# Verification script for logins and dashboards on beta.nextgennoise.com
BASE_URL="https://beta.nextgennoise.com"
LOGIN_API="$BASE_URL/api/auth/login.php"
COOKIE_JAR="/tmp/ngn_cookies.txt"

# Define colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

test_login() {
    local email=$1
    local password=$2
    local dashboard_path=$3
    local label=$4

    echo "--- Testing $label ($email) ---"
    
    # 1. Login
    echo -n "  Logging in... "
    response=$(curl -s -c "$COOKIE_JAR" -X POST "$LOGIN_API" -H "Content-Type: application/json" -d "{\"email\":\"$email\", \"password\":\"$password\"}")
    
    success=$(echo "$response" | grep -o '"success":true')
    
    if [ "$success" == '"success":true' ]; then
        echo -e "${GREEN}SUCCESS${NC}"
        
        # 2. Check Dashboard
        dashboard_url="$BASE_URL$dashboard_path"
        echo -n "  Checking Dashboard ($dashboard_url)... "
        status_code=$(curl -s -b "$COOKIE_JAR" -o /dev/null -w "%{http_code}" "$dashboard_url")
        
        if [ "$status_code" -eq 200 ]; then
            echo -e "${GREEN}PASS${NC} (Status: $status_code)"
        else
            echo -e "${RED}FAIL${NC} (Status: $status_code, Expected: 200)"
            # Let's see if we got redirected or what
            redirect_url=$(curl -s -b "$COOKIE_JAR" -o /dev/null -w "%{url_effective}" "$dashboard_url")
            echo "  Final URL reached: $redirect_url"
        fi
    else
        echo -e "${RED}FAILED${NC}"
        echo "  Response: $response"
    fi
    
    # Cleanup cookies for next user
    rm -f "$COOKIE_JAR"
    echo ""
}

echo "Starting Login & Dashboard Verification..."
echo ""

# 1. Admin
test_login "admin@ngn.local" "password123" "/admin/" "Administrator"

# 2. Artist
test_login "artist_test@ngn.local" "password123" "/dashboard/artist/" "Artist"

# 3. Label
test_login "label_test@ngn.local" "password123" "/dashboard/label/" "Label"

# 4. Station
test_login "station_test@ngn.local" "password123" "/dashboard/station/" "Station"

# 5. Venue
test_login "venue_test@ngn.local" "password123" "/dashboard/venue/" "Venue"

echo "Verification Complete."