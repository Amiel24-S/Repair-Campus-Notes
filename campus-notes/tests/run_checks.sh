#!/usr/bin/env bash
# Automated security checks against a throwaway SQLite copy of the lab database.
# Usage (from the project root):  bash tests/run_checks.sh
# Requires: php (pdo_sqlite), curl. Starts php -S on 127.0.0.1:8099 and stops it afterwards.
# Uses only the synthetic lab accounts. Prints no session IDs or tokens.
set -u
cd "$(dirname "$0")/.."
ROOT=$(pwd)
TMP="$ROOT/tests/tmp"; rm -rf "$TMP"; mkdir -p "$TMP"
DB="$TMP/lab.sqlite"
php -r '$p=new PDO("sqlite:".$argv[1]);$p->exec(file_get_contents($argv[2]));' "$DB" tests/schema.sqlite.sql

PORT=8099; BASE="http://127.0.0.1:$PORT"
LAB_DB_DSN="sqlite:$DB" LAB_ALLOWED_ORIGINS="$BASE" php -S 127.0.0.1:$PORT -t public >"$TMP/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1

PASS=0; FAIL=0
check() { # name, condition-result(0/1)
  if [ "$2" -eq 0 ]; then echo "PASS  $1"; PASS=$((PASS+1)); else echo "FAIL  $1"; FAIL=$((FAIL+1)); fi
}
c() { curl -s --noproxy '*' "$@"; }
token() { c -b "$1" -c "$1" "$BASE/" | sed -n 's/.*name="csrf-token" content="\([0-9a-f]*\)".*/\1/p'; }
sid() { awk '$6=="campus_notes_sid"{print $7}' "$1"; }
login() { # jar email password
  local t; t=$(token "$1")
  c -b "$1" -c "$1" -o /dev/null -w '%{http_code}' --data-urlencode "csrf_token=$t" --data-urlencode action=login \
    --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/"
}
sqlq() { php -r '$p=new PDO("sqlite:".$argv[1]);echo json_encode($p->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));' "$DB" "$1"; }

A="$TMP/alex.jar"; S="$TMP/sam.jar"; X="$TMP/anon.jar"

echo "== Login and sessions"
token "$A" >/dev/null; BEFORE=$(sid "$A")
code=$(login "$A" alex@example.test LabOnly-Alex-123); AFTER=$(sid "$A")
page=$(c -b "$A" "$BASE/")
check "T1 valid login redirects (303) and shows Alex's email" $([ "$code" = 303 ] && grep -q 'Signed in as alex@example.test' <<<"$page"; echo $?)
check "T2 session ID changes on login (fixation)" $([ -n "$BEFORE" ] && [ "$BEFORE" != "$AFTER" ]; echo $?)
check "T3 Alex's list shows only Alex's notes" $(grep -q 'Alex: course reminder' <<<"$page" && ! grep -q 'Sam:' <<<"$page"; echo $?)
login "$S" sam@example.test LabOnly-Sam-456 >/dev/null

login "$X" alex@example.test wrong-password-xx >/dev/null; p=$(c -b "$X" "$BASE/")
check "T4 wrong password fails with generic message" $(grep -q 'Invalid email or password' <<<"$p" && ! grep -q 'Signed in' <<<"$p"; echo $?)
login "$X" "' OR 1=1 -- " anything >/dev/null; p=$(c -b "$X" "$BASE/")
check "T5 SQL injection in email does not log in" $(! grep -q 'Signed in' <<<"$p"; echo $?)
login "$X" "alex@example.test' -- " anything >/dev/null; p=$(c -b "$X" "$BASE/")
check "T6 SQL comment injection does not log in" $(! grep -q 'Signed in' <<<"$p"; echo $?)
p=$(c -b "$X" "$BASE/?message=%3Cscript%3Ealert(1)%3C/script%3E")
check "T7 ?message= is not reflected" $(! grep -q '<script>alert' <<<"$p"; echo $?)

echo "== Registration"
R="$TMP/reg.jar"; t=$(token "$R")
c -b "$R" -c "$R" -o /dev/null --data-urlencode "csrf_token=$t" -d action=register -d role=admin \
  --data-urlencode email=new.user@example.test --data-urlencode password=LabOnly-New-789 "$BASE/"
check "T8 role=admin in registration is ignored (stored as student)" $(sqlq "SELECT role FROM users WHERE email='new.user@example.test'" | grep -q '"student"'; echo $?)
check "T9 new password stored with password_hash (bcrypt), not MD5" $(sqlq "SELECT password_hash FROM users WHERE email='new.user@example.test'" | grep -q '\$2y\$'; echo $?)
t=$(token "$R"); c -b "$R" -c "$R" -o /dev/null --data-urlencode "csrf_token=$t" -d action=register \
  --data-urlencode email=short@example.test --data-urlencode password=short "$BASE/"
check "T10 too-short password rejected" $(sqlq "SELECT COUNT(*) n FROM users WHERE email='short@example.test'" | grep -q '"n":0'; echo $?)

echo "== Web authorization (Alex vs Sam's notes 3 and 4)"
p=$(c -b "$A" "$BASE/?edit=3")
check "T11 Alex cannot open Sam's note for editing" $(grep -q 'Note not found' <<<"$p" && ! grep -q 'Compare authentication' <<<"$p"; echo $?)
t=$(token "$A")
c -b "$A" -o /dev/null --data-urlencode "csrf_token=$t" -d action=update -d id=3 -d title=hacked -d body=hacked "$BASE/"
check "T12 Alex cannot update Sam's note (valid CSRF)" $(sqlq "SELECT title FROM notes WHERE id=3" | grep -q 'Sam: study plan'; echo $?)
c -b "$A" -o /dev/null --data-urlencode "csrf_token=$t" -d action=delete -d id=4 "$BASE/"
check "T13 Alex cannot delete Sam's note (valid CSRF)" $(sqlq "SELECT COUNT(*) n FROM notes WHERE id=4" | grep -q '"n":1'; echo $?)
c -b "$A" -o /dev/null --data-urlencode "csrf_token=$t" -d action=create -d user_id=2 -d title=OwnerTest -d body=x "$BASE/"
check "T14 client-supplied user_id ignored (note owned by Alex)" $(sqlq "SELECT user_id FROM notes WHERE title='OwnerTest'" | grep -q '"user_id":1'; echo $?)

echo "== CSRF and unsafe methods"
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -d action=create -d title=NoToken -d body=x "$BASE/")
check "T15 POST without CSRF token rejected (403)" $([ "$code" = 403 ] && sqlq "SELECT COUNT(*) n FROM notes WHERE title='NoToken'" | grep -q '"n":0'; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -d csrf_token=deadbeef -d action=create -d title=BadToken -d body=x "$BASE/")
check "T16 POST with wrong CSRF token rejected (403)" $([ "$code" = 403 ]; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "Origin: https://evil.example" --data-urlencode "csrf_token=$t" -d action=create -d title=EvilOrigin -d body=x "$BASE/")
check "T17 POST from untrusted Origin rejected even with token (403)" $([ "$code" = 403 ] && sqlq "SELECT COUNT(*) n FROM notes WHERE title='EvilOrigin'" | grep -q '"n":0'; echo $?)
c -b "$A" -o /dev/null "$BASE/?delete=1"
check "T18 GET ?delete=1 does not delete" $(sqlq "SELECT COUNT(*) n FROM notes WHERE id=1" | grep -q '"n":1'; echo $?)
c -b "$A" -o /dev/null "$BASE/?logout=1"; p=$(c -b "$A" "$BASE/")
check "T19 GET ?logout=1 is not a state change (still signed in)" $(grep -q 'Signed in' <<<"$p"; echo $?)

echo "== Output encoding (XSS)"
c -b "$A" -o /dev/null --data-urlencode "csrf_token=$t" -d action=create --data-urlencode 'title=<script>alert(1)</script>' \
  --data-urlencode 'body=<img src=x onerror=alert(2)> "quoted" '"'"'single'"'" "$BASE/"
p=$(c -b "$A" "$BASE/")
check "T20 HTML in title/body rendered as text" $(grep -q '&lt;script&gt;alert(1)&lt;/script&gt;' <<<"$p" && ! grep -q '<script>alert' <<<"$p" && ! grep -q '<img src=x' <<<"$p"; echo $?)
XID=$(sqlq "SELECT id FROM notes WHERE title LIKE '<script>%'" | grep -o '[0-9]*' | head -1)
p=$(c -b "$A" "$BASE/?edit=$XID")
check "T21 HTML in edit form attribute/textarea is escaped" $(grep -q 'value="&lt;script&gt;' <<<"$p" && ! grep -q '</textarea><img' <<<"$p"; echo $?)
h=$(c -b "$A" -D - -o /dev/null "$BASE/")
check "T22 CSP, nosniff and frame headers present" $(grep -qi "content-security-policy: default-src 'none'" <<<"$h" && grep -qi 'x-content-type-options: nosniff' <<<"$h" && grep -qi 'x-frame-options: deny' <<<"$h"; echo $?)
check "T23 session cookie is HttpOnly + SameSite=Strict" $(grep -i 'set-cookie' <(c -c /dev/null -D - -o /dev/null "$BASE/") | grep -qi 'httponly; samesite=strict'; echo $?)

echo "== API"
J='Content-Type: application/json'
code=$(c -o /dev/null -w '%{http_code}' "$BASE/api.php?user_id=2")
check "T24 API GET with no session -> 401" $([ "$code" = 401 ]; echo $?)
code=$(c -o /dev/null -w '%{http_code}' -H "$J" -d '{"id":3,"title":"x","body":"y"}' "$BASE/api.php")
check "T25 API POST with no session -> 401, note unchanged" $([ "$code" = 401 ] && sqlq "SELECT title FROM notes WHERE id=3" | grep -q 'Sam: study plan'; echo $?)
r=$(c -b "$A" "$BASE/api.php")
check "T26 API GET returns only caller's notes" $(grep -q 'Alex: course' <<<"$r" && ! grep -q 'Sam:' <<<"$r"; echo $?)
code=$(c -b "$A" -o "$TMP/r" -w '%{http_code}' "$BASE/api.php?user_id=2")
check "T27 API GET ?user_id=2 as Alex -> 403, no Sam data" $([ "$code" = 403 ] && ! grep -q 'Sam:' "$TMP/r"; echo $?)
At=$(token "$A")
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" -d '{"id":3,"title":"hacked","body":"x"}' "$BASE/api.php")
check "T28 API update of Sam's note as Alex -> 404, unchanged" $([ "$code" = 404 ] && sqlq "SELECT title FROM notes WHERE id=3" | grep -q 'Sam: study plan'; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -d '{"id":1,"title":"NoCsrf","body":"x"}' "$BASE/api.php")
check "T29 API POST without X-CSRF-Token -> 403" $([ "$code" = 403 ]; echo $?)
code=$(c -b "$A" -o "$TMP/r" -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" -d '{bad json' "$BASE/api.php")
check "T30 API malformed JSON -> 400 JSON error, no PHP warning" $([ "$code" = 400 ] && grep -q '"Malformed JSON"' "$TMP/r" && ! grep -qi 'warning\|stack' "$TMP/r"; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" -d '[1,2,3]' "$BASE/api.php")
check "T31 API JSON array instead of object -> 400" $([ "$code" = 400 ]; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" -d '{"id":"1 OR 1=1","title":"x","body":"y"}' "$BASE/api.php")
check "T32 API non-integer id -> 422" $([ "$code" = 422 ]; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" -d '{"id":1,"title":"x"}' "$BASE/api.php")
check "T33 API missing field -> 422" $([ "$code" = 422 ]; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H 'Content-Type: text/plain' -H "X-CSRF-Token: $At" -d '{"id":1,"title":"x","body":"y"}' "$BASE/api.php")
check "T34 API text/plain body (simple CORS request) -> 415" $([ "$code" = 415 ]; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H "$J" -H "X-CSRF-Token: $At" --data-binary @<(php -r 'echo json_encode(["id"=>1,"title"=>"t","body"=>str_repeat("a",70000)]);') "$BASE/api.php")
check "T35 API oversized body -> 413" $([ "$code" = 413 ]; echo $?)
h=$(c -b "$A" -D - -o "$TMP/r" -H 'Origin: https://evil.example' "$BASE/api.php")
check "T36 API untrusted Origin -> 403 and no Access-Control-Allow-* headers" $(grep -q ' 403' <<<"$h" && ! grep -qi 'access-control-allow' <<<"$h" && ! grep -q 'Alex:' "$TMP/r"; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -H 'Origin: https://evil.example' -H "$J" -H "X-CSRF-Token: $At" -d '{"id":1,"title":"Evil","body":"x"}' "$BASE/api.php")
check "T37 API POST from untrusted Origin (even with token) -> 403" $([ "$code" = 403 ] && sqlq "SELECT COUNT(*) n FROM notes WHERE title='Evil'" | grep -q '"n":0'; echo $?)
code=$(c -b "$A" -o "$TMP/r" -w '%{http_code}' -H "Origin: $BASE" -H "$J" -H "X-CSRF-Token: $At" -d '{"id":1,"title":"Updated via API","body":"ok"}' "$BASE/api.php")
check "T38 API valid update of own note -> 200" $([ "$code" = 200 ] && sqlq "SELECT title FROM notes WHERE id=1" | grep -q 'Updated via API'; echo $?)
code=$(c -b "$A" -o /dev/null -w '%{http_code}' -X DELETE "$BASE/api.php")
check "T39 API unsupported method -> 405" $([ "$code" = 405 ]; echo $?)

echo "== Logout"
cp "$A" "$TMP/alex-old.jar"
t=$(token "$A"); c -b "$A" -c "$A" -o /dev/null --data-urlencode "csrf_token=$t" -d action=logout "$BASE/"
p=$(c -b "$A" "$BASE/")
check "T40 after logout the page shows the sign-in form" $(grep -q 'Register or sign in' <<<"$p"; echo $?)
p=$(c -b "$TMP/alex-old.jar" "$BASE/"); code=$(c -b "$TMP/alex-old.jar" -o /dev/null -w '%{http_code}' "$BASE/api.php")
check "T41 replaying the old session cookie after logout is rejected (web + API 401)" $(! grep -q 'Signed in' <<<"$p" && [ "$code" = 401 ]; echo $?)
p=$(c -b "$S" "$BASE/")
check "T42 Sam's separate session unaffected by Alex's logout" $(grep -q 'Signed in as sam@example.test' <<<"$p"; echo $?)

echo "== Error handling"
kill $SERVER; sleep 0.5
env -u LAB_DB_USER -u LAB_DB_PASSWORD LAB_DB_DSN='mysql:host=127.0.0.1;port=1;dbname=x' php -S 127.0.0.1:$PORT -t public >"$TMP/server2.log" 2>&1 &
SERVER=$!; sleep 1
code=$(c -o "$TMP/r" -w '%{http_code}' "$BASE/"); code2=$(c -o "$TMP/r2" -w '%{http_code}' "$BASE/api.php")
check "T43 DB misconfiguration -> generic 500, no stack trace/paths/DSN (web)" $([ "$code" = 500 ] && grep -q 'Something went wrong' "$TMP/r" && ! grep -qiE 'stack|exception|\.php|mysql|LAB_DB' "$TMP/r"; echo $?)
check "T44 DB misconfiguration -> generic JSON 500 (API)" $([ "$code2" = 500 ] && grep -q '"Internal server error"' "$TMP/r2" && ! grep -qiE 'stack|\.php|mysql' "$TMP/r2"; echo $?)
check "T45 no root fallback: missing credentials are logged, not used" $(grep -q 'RuntimeException' "$TMP/server2.log" && ! grep -q 'LAB_DB_PASSWORD=' "$TMP/server2.log"; echo $?)

echo
echo "Result: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
