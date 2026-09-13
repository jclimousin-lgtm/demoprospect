#!/usr/bin/env bash
# Redeploy public/ to demoprospect.serviceproi.fr (o2switch) via FTP.
#
# public/ is the docroot AND fully self-contained (no app/ or config/ file
# is required outside it — the Gemini API key is held client-side in the
# browser's localStorage, never on the server). So this script just mirrors
# public/ straight to the subdomain's webroot, no flatten/rewrite step.
#
# Requires ~/.config/o2switch/nare8592.env (FTP credentials) and `lftp`.
#
# Usage: tools/deploy/deploy-o2switch.sh [remote-dir]
#   remote-dir defaults to demoprospect.serviceproi.fr

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

REMOTE_DIR="${1:-demoprospect.serviceproi.fr}"
ENV_FILE="$HOME/.config/o2switch/nare8592.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Missing $ENV_FILE (FTP credentials)." >&2
    exit 1
fi
# shellcheck disable=SC1090
source "$ENV_FILE"

echo "Uploading public/ to ${O2SWITCH_FTP_USER}@${O2SWITCH_FTP_HOST}:${REMOTE_DIR} ..."
lftp -u "${O2SWITCH_FTP_USER},${O2SWITCH_FTP_PASS}" "${O2SWITCH_FTP_HOST}" <<LFTP
set ftp:ssl-allow no;
mirror -R --verbose --parallel=4 \
    --exclude-glob .well-known \
    --exclude-glob cgi-bin \
    public "${REMOTE_DIR}";
bye
LFTP

echo "Done."
