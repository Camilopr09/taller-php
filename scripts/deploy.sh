#!/usr/bin/env bash
# Despliegue automático con AWS CLI v2 (funciona en AWS CloudShell).
# Variables opcionales: AWS_REGION, INSTANCE_TYPE, NAME, KEY_NAME
# Uso:  bash scripts/deploy.sh
set -euo pipefail

AWS_REGION="${AWS_REGION:-us-east-1}"
INSTANCE_TYPE="${INSTANCE_TYPE:-t3.micro}"
NAME="${NAME:-workshop-ec2-php}"
KEY_NAME="${KEY_NAME:-workshop-ec2-key}"
SG_NAME="${NAME}-sg"
PROJECT_TAG="workshop-ec2-php"
AMI_PARAM="/aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-x86_64"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USER_DATA="$SCRIPT_DIR/user-data.sh"
export AWS_DEFAULT_REGION="$AWS_REGION"
export AWS_PAGER=""

[ -f "$USER_DATA" ] || { echo "No existe $USER_DATA (ejecuta scripts/build-user-data.sh)" >&2; exit 1; }

echo "[1/9] Verificando credenciales de AWS (región $AWS_REGION)..."
aws sts get-caller-identity --query '[Account,Arn]' --output text

echo "[2/9] Buscando la VPC por defecto..."
VPC_ID=$(aws ec2 describe-vpcs --filters Name=isDefault,Values=true --query 'Vpcs[0].VpcId' --output text)
if [ -z "$VPC_ID" ] || [ "$VPC_ID" = "None" ]; then
  echo "No hay VPC por defecto en $AWS_REGION. Créala o usa otra región." >&2
  exit 1
fi
echo "      VPC: $VPC_ID"

echo "[3/9] Key pair '$KEY_NAME'..."
PEM="$PWD/$KEY_NAME.pem"
if aws ec2 describe-key-pairs --key-names "$KEY_NAME" >/dev/null 2>&1; then
  echo "      Ya existe en AWS; no se crea de nuevo."
  [ -f "$PEM" ] || echo "      AVISO: no se encontró $PEM localmente (no podrás conectarte por SSH con esa llave)."
elif [ -f "$PEM" ]; then
  echo "      Existe $PEM pero no el key pair en AWS. Bórralo o cambia KEY_NAME." >&2
  exit 1
else
  aws ec2 create-key-pair --key-name "$KEY_NAME" --key-type rsa \
    --tag-specifications "ResourceType=key-pair,Tags=[{Key=Project,Value=$PROJECT_TAG},{Key=Name,Value=$NAME}]" \
    --query 'KeyMaterial' --output text > "$PEM"
  chmod 400 "$PEM"
  echo "      Llave guardada en $PEM"
fi

echo "[4/9] Security Group '$SG_NAME' (SSH 22 + HTTP 80)..."
SG_ID=$(aws ec2 describe-security-groups \
  --filters Name=group-name,Values="$SG_NAME" Name=vpc-id,Values="$VPC_ID" \
  --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || true)
if [ -z "$SG_ID" ] || [ "$SG_ID" = "None" ]; then
  SG_ID=$(aws ec2 create-security-group --group-name "$SG_NAME" \
    --description "Workshop EC2 PHP: SSH y HTTP" --vpc-id "$VPC_ID" \
    --tag-specifications "ResourceType=security-group,Tags=[{Key=Project,Value=$PROJECT_TAG},{Key=Name,Value=$SG_NAME}]" \
    --query 'GroupId' --output text)
  echo "      Creado: $SG_ID"
else
  echo "      Ya existe: $SG_ID"
fi
MY_IP=$(curl -fsS --max-time 5 https://checkip.amazonaws.com 2>/dev/null | tr -d '[:space:]' || true)
if [[ "$MY_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  SSH_CIDR="$MY_IP/32"
else
  SSH_CIDR="0.0.0.0/0"
  echo "      AVISO: no se pudo obtener tu IP pública; SSH quedará abierto a 0.0.0.0/0."
fi
aws ec2 authorize-security-group-ingress --group-id "$SG_ID" --protocol tcp --port 22 --cidr "$SSH_CIDR" >/dev/null 2>&1 \
  && echo "      Regla SSH (22) permitida desde $SSH_CIDR" || echo "      Regla SSH ya existente (se omite)."
aws ec2 authorize-security-group-ingress --group-id "$SG_ID" --protocol tcp --port 80 --cidr 0.0.0.0/0 >/dev/null 2>&1 \
  && echo "      Regla HTTP (80) permitida desde 0.0.0.0/0" || echo "      Regla HTTP ya existente (se omite)."

echo "[5/9] Buscando la última AMI de Amazon Linux 2023..."
AMI_ID=$(aws ssm get-parameter --name "$AMI_PARAM" --query 'Parameter.Value' --output text)
echo "      AMI: $AMI_ID"

echo "[6/9] Lanzando la instancia ($INSTANCE_TYPE)..."
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id "$AMI_ID" \
  --instance-type "$INSTANCE_TYPE" \
  --key-name "$KEY_NAME" \
  --security-group-ids "$SG_ID" \
  --associate-public-ip-address \
  --user-data "file://$USER_DATA" \
  --metadata-options HttpTokens=required,HttpEndpoint=enabled \
  --tag-specifications \
    "ResourceType=instance,Tags=[{Key=Name,Value=$NAME},{Key=Project,Value=$PROJECT_TAG}]" \
    "ResourceType=volume,Tags=[{Key=Name,Value=$NAME},{Key=Project,Value=$PROJECT_TAG}]" \
  --query 'Instances[0].InstanceId' --output text)
echo "      Instance ID: $INSTANCE_ID"

echo "[7/9] Esperando el estado 'running'..."
aws ec2 wait instance-running --instance-ids "$INSTANCE_ID"

echo "[8/9] Datos de la instancia..."
read -r STATE PUBLIC_IP < <(aws ec2 describe-instances --instance-ids "$INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].[State.Name,PublicIpAddress]' --output text)
echo "      Estado:      $STATE"
echo "      Instance ID: $INSTANCE_ID"
echo "      IP pública:  $PUBLIC_IP"

echo "[9/9] Esperando a que la aplicación responda (máx. ~3 min)..."
READY=0
for _ in $(seq 1 36); do
  if curl -fsS --max-time 4 "http://$PUBLIC_IP/health.php" >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 5
done

echo
if [ "$READY" -eq 1 ]; then
  echo "La aplicación está en línea."
else
  echo "AVISO: aún no responde. Puede tardar un poco más; revisa el Security Group (puerto 80) y vuelve a probar."
fi
echo "URL:  http://$PUBLIC_IP/"
echo "SSH:  ssh -i $KEY_NAME.pem ec2-user@$PUBLIC_IP"
echo
echo "Cuando termines ejecuta scripts/cleanup.sh para evitar costos."
