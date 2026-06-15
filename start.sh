#!/bin/bash

# ──────────────────────────────────────────────────────────────────────────────
# StashDock — Startup Script
# Starts the Laravel development server in the background and opens the browser.
# ──────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/stashdock"
HOST="127.0.0.1"
PORT="8000"
URL="http://${HOST}:${PORT}"
PID_FILE="$SCRIPT_DIR/.stashdock.pid"

# ── Colors ────────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
RESET='\033[0m'

echo ""
echo -e "${CYAN}  ┌─────────────────────────────────────┐${RESET}"
echo -e "${CYAN}  │         StashDock  🚀                │${RESET}"
echo -e "${CYAN}  │   Local Git Control & Dashboard     │${RESET}"
echo -e "${CYAN}  └─────────────────────────────────────┘${RESET}"
echo ""

# ── Check app directory ───────────────────────────────────────────────────────
if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}✗ Laravel project not found at: $APP_DIR${RESET}"
    exit 1
fi

# ── Check if already running ──────────────────────────────────────────────────
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE")
    if kill -0 "$OLD_PID" 2>/dev/null; then
        echo -e "${YELLOW}⚠  StashDock is already running (PID $OLD_PID)${RESET}"
        echo -e "${GREEN}→  Opening browser at $URL${RESET}"
        echo ""
        # Open browser and exit
        if command -v xdg-open &>/dev/null; then
            xdg-open "$URL" &
        elif command -v open &>/dev/null; then
            open "$URL"
        fi
        exit 0
    else
        # Stale PID file — remove it
        rm -f "$PID_FILE"
    fi
fi

# ── Check PHP ─────────────────────────────────────────────────────────────────
if ! command -v php &>/dev/null; then
    echo -e "${RED}✗ PHP is not installed or not in PATH.${RESET}"
    exit 1
fi

# ── Run pending migrations (safe — only applies new ones) ────────────────────
echo -e "${YELLOW}→  Running database migrations…${RESET}"
cd "$APP_DIR" && php artisan migrate --force --no-interaction 2>&1 | \
    grep -v "^$" | sed 's/^/   /'

# ── Start Laravel server in the background ───────────────────────────────────
echo ""
echo -e "${YELLOW}→  Starting Laravel server on $URL …${RESET}"

nohup php artisan serve --host="$HOST" --port="$PORT" \
    > "$SCRIPT_DIR/.stashdock.log" 2>&1 &

SERVER_PID=$!
echo "$SERVER_PID" > "$PID_FILE"

# ── Wait for server to be ready (up to 10s) ───────────────────────────────────
echo -n "   Waiting for server"
for i in $(seq 1 20); do
    if curl -s --head "$URL" > /dev/null 2>&1; then
        echo ""
        break
    fi
    echo -n "."
    sleep 0.5
done

if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo ""
    echo -e "${RED}✗ Server failed to start. Check .stashdock.log for details.${RESET}"
    rm -f "$PID_FILE"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ StashDock is running! (PID $SERVER_PID)${RESET}"
echo -e "${GREEN}→  URL: $URL${RESET}"
echo -e "   Log: $SCRIPT_DIR/.stashdock.log"
echo -e "   To stop: bash $SCRIPT_DIR/stop.sh  (or kill $SERVER_PID)"
echo ""

# ── Open browser ─────────────────────────────────────────────────────────────
if command -v xdg-open &>/dev/null; then
    xdg-open "$URL" &
elif command -v open &>/dev/null; then
    open "$URL" &
elif command -v wslview &>/dev/null; then
    wslview "$URL" &
else
    echo -e "${YELLOW}⚠  Could not detect a browser opener. Please open $URL manually.${RESET}"
fi

echo -e "${CYAN}  Happy coding! 🎉${RESET}"
echo ""
