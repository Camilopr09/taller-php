#!/usr/bin/env bash
# Instala Apache + PHP en la instancia y publica la página (Parte 2 y 3 del taller).
# Ejecutar EN LA INSTANCIA (por SSH):   bash scripts/install.sh
# Es idempotente: se puede ejecutar varias veces sin problema.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_SRC="$(cd "$SCRIPT_DIR/../web" && pwd)"
DOCROOT="/var/www/html"

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  SUDO="sudo"
fi

# --- Detectar la distribución -------------------------------------------------
. /etc/os-release
FAMILY="unknown"
case " ${ID:-} ${ID_LIKE:-} " in
  *" ubuntu "*|*" debian "*)                       FAMILY="debian" ;;
  *" amzn "*|*" fedora "*|*" rhel "*|*" centos "*) FAMILY="rhel" ;;
esac
if [ "$FAMILY" = "unknown" ]; then
  echo "Distribución no soportada: ${PRETTY_NAME:-desconocida}" >&2
  exit 1
fi

for f in index.php health.php; do
  [ -f "$WEB_SRC/$f" ] || { echo "No se encontró $WEB_SRC/$f" >&2; exit 1; }
done

echo "=== Taller EC2: instalación en ${PRETTY_NAME:-$ID} ==="

# --- Paso 1: instalar Apache y PHP ----------------------------------------------
echo
echo "[1/6] Instalando Apache HTTP Server y PHP..."
if [ "$FAMILY" = "debian" ]; then
  WEB_SERVICE="apache2"
  WEB_USER="www-data"
  export DEBIAN_FRONTEND=noninteractive
  $SUDO apt-get update -y
  $SUDO apt-get install -y apache2 php libapache2-mod-php curl
else
  WEB_SERVICE="httpd"
  WEB_USER="apache"
  $SUDO dnf install -y httpd php php-fpm
fi

# --- Paso 2: iniciar y habilitar los servicios -------------------------------------
echo
echo "[2/6] Iniciando y habilitando ${WEB_SERVICE} para que arranque con el sistema..."
$SUDO systemctl enable --now "$WEB_SERVICE"
if [ "$FAMILY" = "rhel" ]; then
  echo "      Iniciando y habilitando php-fpm..."
  $SUDO systemctl enable --now php-fpm
fi

# --- Paso 3: copiar la aplicación -----------------------------------------------------
echo
echo "[3/6] Copiando index.php y health.php a ${DOCROOT}/ ..."
$SUDO install -d -m 0755 "$DOCROOT"
$SUDO cp "$WEB_SRC/index.php" "$WEB_SRC/health.php" "$DOCROOT/"

# --- Paso 4: quitar la página por defecto (Ubuntu/Debian) ------------------------------
echo
echo "[4/6] Ajustando la página por defecto..."
if [ "$FAMILY" = "debian" ] && [ -f "$DOCROOT/index.html" ]; then
  $SUDO rm -f "$DOCROOT/index.html"
  echo "      index.html por defecto eliminado para que se sirva index.php."
else
  echo "      Nada que eliminar."
fi

# --- Paso 5: permisos --------------------------------------------------------------------
echo
echo "[5/6] Ajustando propietario y permisos..."
$SUDO chown "$WEB_USER:$WEB_USER" "$DOCROOT/index.php" "$DOCROOT/health.php"
$SUDO chmod 0644 "$DOCROOT/index.php" "$DOCROOT/health.php"
# Reiniciar para que tomen efecto la configuración de PHP y los archivos nuevos
$SUDO systemctl restart "$WEB_SERVICE"
if [ "$FAMILY" = "rhel" ]; then
  $SUDO systemctl restart php-fpm
fi

# --- Paso 6: verificación -----------------------------------------------------------------
echo
echo "[6/6] Verificando el servicio..."
OK=0
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS http://localhost/health.php; then
    OK=1
    break
  fi
  sleep 2
done
echo
if [ "$OK" -ne 1 ]; then
  echo "ERROR: http://localhost/health.php no respondió." >&2
  exit 1
fi
echo "${WEB_SERVICE} habilitado al arranque: $(systemctl is-enabled "$WEB_SERVICE" || true)"
echo "${WEB_SERVICE} activo:                 $(systemctl is-active "$WEB_SERVICE" || true)"
if [ "$FAMILY" = "rhel" ]; then
  echo "php-fpm habilitado al arranque:  $(systemctl is-enabled php-fpm || true)"
  echo "php-fpm activo:                  $(systemctl is-active php-fpm || true)"
fi
echo
echo "Listo. Abre en el navegador: http://<IP-PUBLICA-DE-LA-INSTANCIA>/"
