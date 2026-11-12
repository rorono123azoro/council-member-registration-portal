#!/usr/bin/env bash
# Council Member Registration Portal — Curl Smoke Test
# Usage: BASE_URL=http://127.0.0.1:8000 bash tests/smoke-test.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

echo "Running smoke tests against $BASE_URL ..."

# 1. Public form loads (200) and contains CSRF token + 4 steps
STATUS=$(curl -s -o /tmp/reg_form.html -w "%{http_code}" -c "$COOKIE_JAR" "$BASE_URL/index.php")
[ "$STATUS" = "200" ]
grep -q '_csrf_token' /tmp/reg_form.html
grep -q 'data-step="4"' /tmp/reg_form.html
echo "[PASS] Public registration form loads with CSRF token and 4 steps"

# 2. Sensitive paths blocked (403/404)
for p in "/app/" "/storage/" "/.env" "/database/schema.sql"; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$p")
    [ "$CODE" = "403" ] || [ "$CODE" = "404" ]
done
echo "[PASS] Sensitive paths blocked"

# 3. Unauthenticated admin access redirects to login (302)
ADMIN_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/admin/dashboard.php")
[ "$ADMIN_CODE" = "302" ]
echo "[PASS] Unauthenticated admin dashboard redirects"

# 4. API register without CSRF rejected (403)
API_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/api/register.php")
[ "$API_CODE" = "403" ]
echo "[PASS] POST /api/register.php without CSRF returns 403"

# 5. Live unique check API returns JSON
CU=$(curl -s "$BASE_URL/api/check-unique.php?email=nonexistent@example.com")
echo "$CU" | grep -q '"available":true'
echo "[PASS] check-unique.php returns JSON availability"

rm -f /tmp/reg_form.html
echo "All smoke tests passed."

