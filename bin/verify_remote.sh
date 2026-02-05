#!/bin/bash

# Verification script for beta.nextgennoise.com
BASE_URL="https://beta.nextgennoise.com"

# Define colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

check_url() {
    local url=$1
    local expected_status=$2
    local label=$3

    echo -n "Checking $label ($url)... "
    status_code=$(curl -s -o /dev/null -w "%{http_code}" "$url")

    if [ "$status_code" -eq "$expected_status" ]; then
        echo -e "${GREEN}PASS${NC} (Status: $status_code)"
    else
        echo -e "${RED}FAIL${NC} (Status: $status_code, Expected: $expected_status)"
    fi
}

check_content() {
    local url=$1
    local pattern=$2
    local label=$3

    echo -n "Checking $label content for '$pattern'... "
    content=$(curl -s "$url")
    
    if echo "$content" | grep -q "$pattern"; then
        echo -e "${GREEN}PASS${NC}"
    else
        echo -e "${RED}FAIL${NC}"
    fi
}

echo "--- Starting Remote Verification for $BASE_URL ---"

# 1. Home Page
check_url "$BASE_URL/" 200 "Home Page"
check_content "$BASE_URL/" "NextGenNoise" "Home Page Title"

# 2. API Health
check_url "$BASE_URL/api/v1/health" 200 "API Health Endpoint"
check_content "$BASE_URL/api/v1/health" '"success":true' "API Health JSON"

# 3. Main Entity Lists
check_url "$BASE_URL/artists" 200 "Artists Page"
check_url "$BASE_URL/labels" 200 "Labels Page"
check_url "$BASE_URL/stations" 200 "Stations Page"
check_url "$BASE_URL/venues" 200 "Venues Page"

# 4. Charts
check_url "$BASE_URL/charts" 200 "NGN Charts Page"
check_url "$BASE_URL/smr-charts" 200 "SMR Charts Page"

# 5. Specific Profiles
check_url "$BASE_URL/artist/10-years" 200 "Artist Profile: 10 Years"
check_content "$BASE_URL/artist/10-years" "10 Years" "Artist Profile Content"

# 6. Specific Post
check_url "$BASE_URL/post/alabama-rockers-clozure-ink-deal-with-wake-up-music-rocks" 200 "Post: Alabama Rockers"

# 7. Pricing
check_url "$BASE_URL/pricing" 200 "Pricing Page"

echo "--- Verification Complete ---"
