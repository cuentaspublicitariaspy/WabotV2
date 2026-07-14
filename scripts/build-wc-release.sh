#!/usr/bin/env bash
set -euo pipefail

# Genera el paquete WC para instalar por File Manager o SFTP.
# El paquete no contiene WS, Git, datos de clientes, archivos de entorno ni documentación interna.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${1:-$ROOT_DIR/dist}"
OUTPUT_FILE="$OUTPUT_DIR/Wabot-WC-Instalador.zip"

mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_FILE"

(
  cd "$ROOT_DIR"
  zip -qr "$OUTPUT_FILE" . \
    -x '.git/*' \
       '.github/*' \
       '.env' \
       'wabot-cdn/*' \
       'NO SUBIR/*' \
       'scripts/*' \
       'dist/*' \
       'CORRECCIONES.md' \
       'node_modules/*' \
       '*.log' \
       'uploads/*'
  zip -q "$OUTPUT_FILE" uploads/.gitkeep
)

echo "Paquete WC creado: $OUTPUT_FILE"
