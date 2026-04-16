#!/bin/bash

TOKEN="1|ZS9M3Y9cdVWLW74KuGDFUZZLpTJAXslT78Shmy1R9aa33415"
BASE_URL="http://127.0.0.1:8000/api/v1"

echo "=== Testing Health Endpoint (Public) ==="
curl -s -X GET "$BASE_URL/health" | json_pp || curl -s -X GET "$BASE_URL/health"
echo -e "\n"

echo "=== Testing Medicines Index (Authenticated) ==="
curl -s -X GET "$BASE_URL/medicines" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" | json_pp || curl -s -X GET "$BASE_URL/medicines" -H "Authorization: Bearer $TOKEN"
echo -e "\n"

echo "=== Testing Medicine Search (Authenticated) ==="
curl -s -X GET "$BASE_URL/medicines/search?q=Admenta" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" | json_pp || curl -s -X GET "$BASE_URL/medicines/search?q=Admenta" -H "Authorization: Bearer $TOKEN"
echo -e "\n"
