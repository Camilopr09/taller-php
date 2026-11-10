#!/usr/bin/env bash
# Elimina los recursos creados por deploy.sh (instancias, security group y key pair).
# Uso:  bash scripts/cleanup.sh [--yes]
set -euo pipefail

AWS_REGION="${AWS_REGION:-us-east-1}"
NAME="${NAME:-workshop-ec2-php}"
KEY_NAME="${KEY_NAME:-workshop-ec2-key}"
SG_NAME="${NAME}-sg"
PROJECT_TAG="workshop-ec2-php"
export AWS_DEFAULT_REGION="$AWS_REGION"
export AWS_PAGER=""

YES=0
[ "${1:-}" = "--yes" ] && YES=1

echo "Se eliminarán en $AWS_REGION:"
echo "  - Instancias con la etiqueta Project=$PROJECT_TAG"
echo "  - Security Group '$SG_NAME'"
echo "  - Key pair '$KEY_NAME'"
if [ "$YES" -ne 1 ]; then
  read -r -p "¿Continuar? [y/N] " ans
  case "$ans" in
    y|Y|yes|YES) ;;
    *) echo "Cancelado."; exit 0 ;;
  esac
fi

echo "[1/4] Buscando instancias..."
IDS=$(aws ec2 describe-instances \
  --filters "Name=tag:Project,Values=$PROJECT_TAG" \
            "Name=instance-state-name,Values=pending,running,stopping,stopped" \
  --query 'Reservations[].Instances[].InstanceId' --output text)

if [ -n "$IDS" ]; then
  echo "[2/4] Terminando: $IDS"
  # shellcheck disable=SC2086
  aws ec2 terminate-instances --instance-ids $IDS >/dev/null
  echo "      Esperando a que terminen..."
  # shellcheck disable=SC2086
  aws ec2 wait instance-terminated --instance-ids $IDS
else
  echo "[2/4] No hay instancias activas con esa etiqueta."
fi

echo "[3/4] Eliminando Security Group '$SG_NAME'..."
SG_ID=$(aws ec2 describe-security-groups --filters Name=group-name,Values="$SG_NAME" \
  --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || true)
if [ -n "$SG_ID" ] && [ "$SG_ID" != "None" ]; then
  aws ec2 delete-security-group --group-id "$SG_ID" && echo "      Eliminado $SG_ID" \
    || echo "      No se pudo eliminar $SG_ID (¿aún en uso?). Reintenta en un minuto."
else
  echo "      No existe."
fi

echo "[4/4] Eliminando key pair '$KEY_NAME'..."
if aws ec2 describe-key-pairs --key-names "$KEY_NAME" >/dev/null 2>&1; then
  aws ec2 delete-key-pair --key-name "$KEY_NAME" && echo "      Eliminado."
else
  echo "      No existe."
fi

echo
echo "Listo. Borra también la llave local si existe:  rm -f $KEY_NAME.pem"
