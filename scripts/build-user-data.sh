#!/usr/bin/env bash
# Genera scripts/user-data.sh a partir de web/*.php (no editar user-data.sh a mano).
# Uso: bash scripts/build-user-data.sh
# Límite de EC2: 16384 bytes ANTES de base64. Si no cabe en base64 plano,
# se comprime cada archivo con gzip (base64 -d | gunzip).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB="$SCRIPT_DIR/../web"
OUT="$SCRIPT_DIR/user-data.sh"
LIMIT=16384
FILES=(index.php health.php)

emit() {  # $1 = modo (plain|gzip)
  local mode="$1" f
  cat <<'HEAD'
#!/bin/bash
# Generado por scripts/build-user-data.sh a partir de web/*.php. NO EDITAR A MANO.
# Amazon Linux 2023 - instala Apache + PHP y publica la aplicación en el primer arranque.
set -euo pipefail
exec > >(tee -a /var/log/workshop-user-data.log) 2>&1

WEBROOT=/var/www/html

echo "[1/5] Instalando Apache y PHP..."
dnf install -y httpd php php-fpm

echo "[2/5] Habilitando e iniciando servicios..."
systemctl enable --now php-fpm httpd

echo "[3/5] Escribiendo la aplicación en $WEBROOT ..."
install -d -m 0755 "$WEBROOT"
# --- BEGIN FILES ---
HEAD
  for f in "${FILES[@]}"; do
    if [ "$mode" = gzip ]; then
      echo "base64 -d <<'B64_EOF' | gunzip > \"\$WEBROOT/$f\""
      gzip -9nc "$WEB/$f" | base64 -w 76
    else
      echo "base64 -d <<'B64_EOF' > \"\$WEBROOT/$f\""
      base64 -w 76 "$WEB/$f"
    fi
    echo "B64_EOF"
  done
  cat <<'TAIL'
# --- END FILES ---

echo "[4/5] Permisos y reinicio de servicios..."
chown apache:apache "$WEBROOT/index.php" "$WEBROOT/health.php"
chmod 0644 "$WEBROOT/index.php" "$WEBROOT/health.php"
systemctl restart php-fpm httpd

echo "[5/5] Verificando..."
for _ in $(seq 1 15); do
  curl -fsS http://localhost/health.php && break || sleep 2
done
echo
echo "httpd: $(systemctl is-enabled httpd) / $(systemctl is-active httpd)"
echo "php-fpm: $(systemctl is-enabled php-fpm) / $(systemctl is-active php-fpm)"
echo "Listo."
TAIL
}

emit plain > "$OUT"
MODE="base64 plano"
SIZE=$(wc -c < "$OUT")
if [ "$SIZE" -gt "$LIMIT" ]; then
  echo "Con base64 plano: $SIZE bytes (> $LIMIT). Usando gzip + base64..."
  emit gzip > "$OUT"
  MODE="gzip + base64"
  SIZE=$(wc -c < "$OUT")
fi
chmod +x "$OUT"
echo "Generado $OUT ($MODE): $SIZE bytes (límite $LIMIT)"
if [ "$SIZE" -gt "$LIMIT" ]; then
  echo "ERROR: user-data excede el límite de EC2." >&2
  exit 1
fi
