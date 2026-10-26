#!/bin/bash
cd "$(dirname "$0")"
chmod +x start.sh 2>/dev/null || true
exec ./start.sh
