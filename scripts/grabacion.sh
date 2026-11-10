#!/usr/bin/env bash
# Plan B de grabación (todo por terminal con AWS CLI). Guion principal: docs/GUION-GRABACION.md
# Uso:  bash scripts/grabacion.sh <paso>
#   1        Parte 1: Security Group (22+80), llave, instancia, estado Running, IP pública
#   2        Parte 2: SSH, instalar Apache+PHP, iniciar y habilitar al arranque
#   3        Parte 3: clonar el repo, publicar la página y mostrar los datos
#   4        Extra:   reiniciar la instancia y comprobar el arranque automático
#   limpiar  Terminar la instancia y borrar Security Group y llave (evita costos)
#   estado   Ver los datos guardados de la instancia actual
set -euo pipefail
export PATH="$HOME/.local/bin:$PATH" AWS_DEFAULT_REGION="${AWS_REGION:-us-east-1}" AWS_PAGER=""

NAME=taller-ec2; SG_NAME=$NAME-sg; KEY_NAME=$NAME-key
KEY="$HOME/.ssh/$KEY_NAME.pem"; KH="$HOME/.ssh/known_hosts_taller"
STATE="$HOME/.taller-ec2.env"
REPO=https://github.com/Camilopr09/taller-php.git
AMI="resolve:ssm:/aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-x86_64"
LOCAL_WEB="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/web"

c_b=$'\e[1;36m'; c_g=$'\e[1;32m'; c_y=$'\e[1;33m'; c_0=$'\e[0m'
titulo() { echo; echo "${c_b}== $* ==${c_0}"; }
run()    { echo "${c_y}\$ $*${c_0}" >&2; "$@"; }
ok()     { echo "${c_g}✔ $*${c_0}"; }
cargar() { [ -f "$STATE" ] || { echo "Primero ejecuta el paso 1." >&2; exit 1; }; . "$STATE"; }
SSH()    { ssh -i "$KEY" -o UserKnownHostsFile="$KH" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 "ec2-user@$IP" "$@"; }

paso1() {
  titulo "PARTE 1 · Crear la instancia EC2"
  aws sts get-caller-identity --query Arn --output text >/dev/null || { echo "Inicia sesión: aws login --region us-east-1" >&2; exit 1; }
  VPC=$(aws ec2 describe-vpcs --filters Name=is-default,Values=true --query 'Vpcs[0].VpcId' --output text)
  MYIP=$(curl -fsS -m 5 https://checkip.amazonaws.com | tr -d '[:space:]')
  echo "Región: $AWS_DEFAULT_REGION · VPC por defecto: $VPC · Mi IP: $MYIP"

  titulo "1.1 Security Group con SSH (22) y HTTP (80)"
  SG=$(run aws ec2 create-security-group --group-name "$SG_NAME" --description "Taller EC2: SSH y HTTP" \
        --vpc-id "$VPC" --query GroupId --output text); echo "$SG"
  run aws ec2 authorize-security-group-ingress --group-id "$SG" --ip-permissions \
    "IpProtocol=tcp,FromPort=22,ToPort=22,IpRanges=[{CidrIp=$MYIP/32,Description=SSH}]" \
    "IpProtocol=tcp,FromPort=80,ToPort=80,IpRanges=[{CidrIp=0.0.0.0/0,Description=HTTP}]" \
    --query 'SecurityGroupRules[].[IpProtocol,FromPort,CidrIpv4]' --output table

  titulo "1.2 Par de llaves para SSH"
  mkdir -p ~/.ssh && chmod 700 ~/.ssh; rm -f "$KEY" "$KH"
  run aws ec2 create-key-pair --key-name "$KEY_NAME" --key-type ed25519 --query KeyMaterial --output text > "$KEY"
  chmod 400 "$KEY"; ok "Llave guardada en $KEY"

  titulo "1.3 Lanzar la instancia (Amazon Linux 2023, t3.micro)"
  ID=$(run aws ec2 run-instances --image-id "$AMI" --instance-type t3.micro --key-name "$KEY_NAME" \
        --security-group-ids "$SG" --metadata-options HttpTokens=required,HttpEndpoint=enabled \
        --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=$NAME}]" \
        --query 'Instances[0].InstanceId' --output text); echo "$ID"

  titulo "1.4 Esperar estado Running y obtener la IP pública"
  run aws ec2 wait instance-running --instance-ids "$ID"
  run aws ec2 describe-instances --instance-ids "$ID" --output table --query \
    'Reservations[0].Instances[0].{ID:InstanceId,Estado:State.Name,Tipo:InstanceType,Zona:Placement.AvailabilityZone,IP_publica:PublicIpAddress,IP_privada:PrivateIpAddress}'
  IP=$(aws ec2 describe-instances --instance-ids "$ID" --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)
  printf 'ID=%s\nSG=%s\nIP=%s\n' "$ID" "$SG" "$IP" > "$STATE"
  ok "Instancia $ID en estado running · IP pública: $IP"
}

paso2() {
  cargar; titulo "PARTE 2 · Instalar Apache y PHP"
  echo "Esperando a que SSH esté disponible..."
  for _ in $(seq 1 40); do SSH true 2>/dev/null && break; sleep 4; done
  echo "${c_y}\$ ssh -i $KEY ec2-user@$IP${c_0}"
  SSH 'set -e; run(){ echo -e "\e[1;33m[ec2-user@$(hostname -s)]\$ $*\e[0m"; eval "$@"; }
    run sudo dnf install -y httpd php git
    run sudo systemctl enable --now httpd
    run systemctl is-active httpd
    run systemctl is-enabled httpd
    run "php -v | head -1"
    run "httpd -v | head -1"'
  ok "Apache activo y habilitado al arranque. Abre http://$IP/ (página de prueba de Apache)"
  xdg-open "http://$IP/" >/dev/null 2>&1 || true
}

paso3() {
  cargar; titulo "PARTE 3 · Publicar la página PHP"
  SSH 'set -e; run(){ echo -e "\e[1;33m[ec2-user@$(hostname -s)]\$ $*\e[0m"; eval "$@"; }
    rm -rf taller-php
    run git clone '"$REPO"'
    run sudo cp taller-php/web/*.php /var/www/html/
    run ls -l /var/www/html/'
  if SSH 'grep -q "Tu nombre aquí" /var/www/html/index.php'; then
    echo "AVISO: el repo en GitHub aún no tiene los nombres; copiando la versión local..."
    scp -q -i "$KEY" -o UserKnownHostsFile="$KH" "$LOCAL_WEB"/*.php "ec2-user@$IP:/tmp/" && SSH 'sudo cp /tmp/*.php /var/www/html/'
  fi
  titulo "Datos que muestra la página"
  curl -fsS "http://$IP/?format=json" | python3 -c '
import sys, json; d = json.load(sys.stdin); c, s, i = d["client"], d["server"], d["instance"]
for k, v in [("Integrantes", d["student"]), ("Cliente · IP", c["ip"]), ("Cliente · User Agent", c["user_agent"]),
             ("Servidor · Hostname", s["hostname"]), ("Servidor · IP", s["ip"]), ("Servidor · Fecha/hora", s["datetime"] + " " + s["timezone"]),
             ("EC2 · Instance ID", i["instance_id"]), ("EC2 · Zona", i["availability_zone"]), ("EC2 · Región", i["region"]),
             ("EC2 · IP privada", i["private_ip"]), ("EC2 · Metadatos", i["imds_version"])]:
    print(f"  {k:<24} {v}")'
  ok "Página en línea: http://$IP/   (extras: ?format=json · ?lang=en · /health.php)"
  xdg-open "http://$IP/" >/dev/null 2>&1 || true
}

paso4() {
  cargar; titulo "EXTRA · Reiniciar y comprobar el arranque automático"
  run aws ec2 reboot-instances --instance-ids "$ID"
  echo -n "Apagándose..."; for _ in $(seq 1 30); do curl -fsS -m 2 -o /dev/null "http://$IP/health.php" 2>/dev/null || break; echo -n "."; sleep 2; done
  echo " caída ✔"; echo -n "Encendiendo..."
  for _ in $(seq 1 60); do curl -fsS -m 3 -o /dev/null "http://$IP/health.php" 2>/dev/null && break; echo -n "."; sleep 3; done; echo
  run curl -s "http://$IP/health.php"; echo
  for _ in $(seq 1 20); do SSH true 2>/dev/null && break; sleep 3; done
  SSH 'echo "uptime: $(uptime -p) · httpd: $(systemctl is-active httpd) · habilitado: $(systemctl is-enabled httpd)"'
  ok "Apache arrancó solo después del reinicio. Recarga http://$IP/"
}

limpiar() {
  cargar; titulo "LIMPIEZA · Evitar costos"
  run aws ec2 terminate-instances --instance-ids "$ID" --query 'TerminatingInstances[0].CurrentState.Name' --output text
  run aws ec2 wait instance-terminated --instance-ids "$ID"
  run aws ec2 delete-security-group --group-id "$SG" >/dev/null
  run aws ec2 delete-key-pair --key-name "$KEY_NAME" >/dev/null
  rm -f "$KEY" "$KH" "$STATE"; ok "Todo eliminado: instancia terminada, Security Group y llave borrados"
}

case "${1:-}" in
  1) paso1 ;; 2) paso2 ;; 3) paso3 ;; 4) paso4 ;; limpiar) limpiar ;;
  estado) cargar; cat "$STATE" ;;
  *) sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
