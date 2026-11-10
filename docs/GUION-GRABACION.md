# Guion de grabación (consola web + terminal)

Todo lo que se puede hacer con el mouse se hace en la **consola web de AWS**.
La **terminal normal** solo se usa para lo que de verdad la necesita (SSH e instalar/publicar).
Cada vez que algo cambia desde la terminal, se pasa al **navegador** para ver el resultado.

`🖱️ NAVEGADOR` = consola de AWS o la página · `⌨️ TERMINAL` = terminal normal (no la de Claude)
Las etiquetas aparecen como *English (Español)* porque la consola puede estar en cualquiera de los dos idiomas.

Duración estimada: 8–10 min. Narración sugerida: un integrante por escena.

---

## Antes de grabar (fuera de cámara)

- [ ] Sesión iniciada en https://console.aws.amazon.com con la región **N. Virginia (us-east-1)** arriba a la derecha.
- [ ] Que **no** exista ya una instancia `taller-ec2`, un key pair `taller-ec2-key` o un security group `taller-ec2-sg` (si quedaron de antes, bórralos).
- [ ] Borrar `~/Downloads/taller-ec2-key.pem` si quedó de un ensayo.
- [ ] Terminal normal abierta en `~/Downloads`, con letra grande (`Ctrl+Shift++`).
- [ ] Navegador con zoom al 110–125 %. Una pestaña con la consola de EC2 y otra vacía para la página.
- [ ] OBS: captura de pantalla completa **con el cursor visible**; micrófono probado.
- [ ] Este guion abierto en el celular o en otra pantalla (que no salga en el video).

---

## Escena 0 · Presentación — 🖱️ NAVEGADOR (≈20 s) · *Hernán*

En la consola de AWS: nombres del grupo, materia y objetivo ("desplegar una aplicación web PHP en EC2").
Señalar con el mouse la región **us-east-1**.

## Escena 1 · Parte 1: crear la instancia — 🖱️ NAVEGADOR (≈2 min) · *Camilo*

1. Buscador de arriba → escribir `EC2` → **EC2** → **Launch instance (Lanzar instancia)**.
2. **Name (Nombre):** `taller-ec2`.
3. **AMI:** dejar **Amazon Linux 2023** (*Free tier eligible*). Señalarlo.
4. **Instance type (Tipo de instancia):** `t3.micro` (*Free tier eligible*).
5. **Key pair (Par de claves)** → *Create new key pair (Crear un nuevo par de claves)* →
   nombre `taller-ec2-key`, tipo **RSA**, formato **.pem** → *Create*. El navegador descarga el `.pem` (mostrarlo).
6. **Network settings (Configuración de red)** → **Edit (Editar)**:
   - *Auto-assign public IP*: **Enable**.
   - *Create security group*, nombre `taller-ec2-sg`, descripción `SSH y HTTP`.
   - Regla 1: **ssh**, puerto **22**, Source type **My IP (Mi IP)**.
   - **Add security group rule** → Regla 2: **HTTP**, puerto **80**, Source type **Anywhere (0.0.0.0/0)**.
   - Pasar el mouse por las dos reglas mientras se explica para qué sirve cada puerto.
7. Panel derecho (*Summary*) → **Launch instance (Lanzar instancia)** → clic en el ID de la instancia.
8. Mostrar **Instance state = Running** (pulsar el botón ↻ si sigue en *Pending*).
   Esperar a que *Status check* llegue a **2/2 checks passed** (se puede cortar el video ahí).
9. Seleccionar la instancia → pestaña **Details**: señalar **Instance ID**, **Public IPv4 address**
   (copiarla con el ícono 📋), **Private IPv4**, **Availability Zone**.
10. Pestaña **Security**: señalar las reglas de entrada 22 y 80.

## Escena 2 · Conexión por SSH — 🖱️ → ⌨️ (≈1 min) · *Jeilyn*

1. 🖱️ Con la instancia seleccionada → **Connect (Conectar)** → pestaña **SSH client**.
   Señalar los pasos que da AWS y **copiar** el comando de ejemplo (ícono 📋).
2. ⌨️ En la terminal:
   ```bash
   ls -l taller-ec2-key.pem
   chmod 400 taller-ec2-key.pem
   ssh -i "taller-ec2-key.pem" ec2-user@ec2-X-X-X-X.compute-1.amazonaws.com   # ← pegado desde AWS
   ```
   A la pregunta `Are you sure you want to continue connecting` responder `yes`.
   Aparece el logo de Amazon Linux: estamos dentro.

## Escena 3 · Parte 2: Apache y PHP — ⌨️ → 🖱️ (≈2 min) · *Jeilyn*

1. ⌨️ En la instancia:
   ```bash
   sudo dnf install -y httpd php git
   sudo systemctl enable --now httpd
   systemctl status httpd          # "active (running)" en verde · salir con q
   systemctl is-enabled httpd      # enabled → arranca solo al reiniciar
   php -v
   ```
2. 🖱️ Nueva pestaña → `http://IP-PUBLICA/` (escribir **http://**, no https).
   Aparece la **página de prueba de Apache**. ✔ Parte 2 verificada desde el navegador.

## Escena 4 · Parte 3: la página PHP — ⌨️ → 🖱️ (≈2–3 min) · *Isac*

1. ⌨️ En la instancia:
   ```bash
   git clone https://github.com/Camilopr09/taller-php.git
   sudo cp taller-php/web/*.php /var/www/html/
   ls -l /var/www/html/
   ```
2. 🖱️ En la pestaña de la página → **recargar (F5)**. Recorrer los bloques con el mouse:
   - **Cliente:** tu IP pública y tu User Agent (navegador y sistema operativo).
   - **Servidor:** hostname, IP y fecha/hora (el reloj avanza en vivo).
   - **Instancia EC2:** Instance ID, zona de disponibilidad, región, IP privada.
3. 🖱️ Cambiar a la pestaña de la consola → **Details** y comparar con el mouse que el **Instance ID**,
   la **zona** y la **IP privada** son iguales a los de la página. (Momento clave del video.)
4. 🖱️ Extras en la página: botón **Copiar** junto a un dato, enlace **English** (y volver con **Español**),
   enlace **Ver JSON** (`?format=json`), y abrir `http://IP-PUBLICA/health.php`.
   Bajar hasta memoria, disco y **Tiempo encendido (uptime)**. (El modo oscuro sigue el tema del sistema.)

## Escena 5 · Prueba de arranque automático — 🖱️ (≈1 min) · *José*

1. 🖱️ Consola → seleccionar la instancia → **Instance state (Estado de la instancia)** →
   **Reboot instance (Reiniciar instancia)** → confirmar.
2. ⌨️ Mostrar un instante la terminal: la sesión SSH se cierra sola (`Connection closed`). No se toca nada más.
3. 🖱️ Esperar ~1 min y **recargar** la página: sigue funcionando y el **uptime** volvió a 0 min.
   Apache arrancó solo porque está habilitado con `systemctl enable`.

## Escena 6 · Cierre y limpieza — 🖱️ (≈1 min) · *José*

1. 🖱️ Resumen en voz: Partes 1, 2 y 3 cumplidas + extras.
2. 🖱️ **Instance state → Terminate (delete) instance (Terminar instancia)** → confirmar → mostrar *Shutting-down*.
3. (Opcional, puede ser fuera de cámara cuando la instancia ya esté *Terminated*):
   **Network & Security → Security Groups** → borrar `taller-ec2-sg`;
   **Key Pairs** → borrar `taller-ec2-key`.

---

## Si algo falla durante la grabación

| Síntoma | Solución |
|---|---|
| `ssh` se queda esperando / *timed out* | Cambió tu IP: consola → Security → regla SSH → **Edit inbound rules** → Source **My IP** → Save. |
| `Permission denied (publickey)` | Faltó `chmod 400 taller-ec2-key.pem`, o el usuario no es `ec2-user`. |
| `UNPROTECTED PRIVATE KEY FILE` | Ejecutar `chmod 400 taller-ec2-key.pem`. |
| El navegador no carga la IP | Usar `http://` (no https) y revisar la regla HTTP 80 → Anywhere. |
| Se ve el código PHP en vez de la página | `sudo systemctl restart httpd php-fpm` y recargar. |
| Los datos de EC2 salen "No disponible" | `sudo systemctl restart php-fpm` y recargar. |

**Plan B** (sin consola, todo por terminal): `bash scripts/grabacion.sh 1|2|3|4|limpiar`.
