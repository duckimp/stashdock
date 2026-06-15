#!/bin/bash

# ──────────────────────────────────────────────────────────────────────────────
# StashDock — Stop Script
# Gracefully shuts down the Laravel development server.
# ──────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="$SCRIPT_DIR/.stashdock.pid"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
RESET='\033[0m'

if [ ! -f "$PID_FILE" ]; then
    echo -e "${YELLOW}⚠  StashDock is not running (no PID file found).${RESET}"
    exit 0
fi

PID=$(cat "$PID_FILE")

if kill -0 "$PID" 2>/dev/null; then
    echo -e "${YELLOW}→  Stopping StashDock (PID $PID)…${RESET}"
    kill "$PID"
    sleep 1
    if kill -0 "$PID" 2>/dev/null; then
        kill -9 "$PID"
    fi
    rm -f "$PID_FILE"
    echo -e "${GREEN}✓ StashDock stopped.${RESET}"
else
    echo -e "${YELLOW}⚠  Process $PID is not running. Cleaning up stale PID file.${RESET}"
    rm -f "$PID_FILE"
fi
