#!/bin/bash

# ===============================================
# 🚀 BCommerce Environment Deployment Script
# ===============================================
# Uso: ./deploy.sh [local|production|status|help]
# 
# Este script permite cambiar rápidamente entre
# configuraciones de entorno para desarrollo y producción
# ===============================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Emojis
SUCCESS="✅"
ERROR="❌"
WARNING="⚠️"
INFO="ℹ️"
ROCKET="🚀"
GEAR="🔧"
LOCK="🔐"

show_header() {
    echo ""
    echo -e "${BLUE}===============================================${NC}"
    echo -e "${PURPLE}${ROCKET} BCommerce Environment Manager${NC}"
    echo -e "${BLUE}===============================================${NC}"
    echo ""
}

show_current_env() {
    if [ -f .env ]; then
        local current_env=$(grep "^APP_ENV=" .env 2>/dev/null | cut -d'=' -f2 || echo "unknown")
        local app_url=$(grep "^APP_URL=" .env 2>/dev/null | cut -d'=' -f2 || echo "unknown")
        local frontend_url=$(grep "^FRONTEND_URL=" .env 2>/dev/null | cut -d'=' -f2 || echo "unknown")
        local db_database=$(grep "^DB_DATABASE=" .env 2>/dev/null | cut -d'=' -f2 || echo "unknown")
        
        echo -e "${INFO} ${CYAN}Configuración actual:${NC}"
        echo -e "   Entorno: ${YELLOW}${current_env}${NC}"
        echo -e "   API URL: ${YELLOW}${app_url}${NC}"
        echo -e "   Frontend URL: ${YELLOW}${frontend_url}${NC}"
        echo -e "   Base de datos: ${YELLOW}${db_database}${NC}"
        echo ""
    else
        echo -e "${ERROR} ${RED}No se encontró archivo .env${NC}"
        echo ""
    fi
}

backup_current_env() {
    if [ -f .env ]; then
        local timestamp=$(date +"%Y%m%d_%H%M%S")
        cp .env ".env.backup_${timestamp}"
        echo -e "${INFO} ${CYAN}Backup creado: .env.backup_${timestamp}${NC}"
    fi
}

deploy_local() {
    echo -e "${GEAR} ${YELLOW}Configurando entorno LOCAL...${NC}"
    echo ""
    
    if [ ! -f .env.local ]; then
        echo -e "${ERROR} ${RED}Archivo .env.local no encontrado${NC}"
        exit 1
    fi
    
    backup_current_env
    cp .env.local .env
    
    # Clear caches for development
    echo -e "${INFO} ${CYAN}Limpiando cachés...${NC}"
    php artisan config:clear >/dev/null 2>&1 || true
    php artisan route:clear >/dev/null 2>&1 || true
    php artisan cache:clear >/dev/null 2>&1 || true
    
    echo ""
    echo -e "${SUCCESS} ${GREEN}¡Configuración LOCAL activada correctamente!${NC}"
    echo ""
    echo -e "${INFO} ${CYAN}Comandos sugeridos para desarrollo:${NC}"
    echo -e "   ${YELLOW}php artisan serve${NC} - Iniciar servidor de desarrollo"
    echo -e "   ${YELLOW}npm run dev${NC} - Iniciar frontend (en directorio del frontend)"
    echo -e "   ${YELLOW}php artisan queue:work${NC} - Procesar colas en segundo plano"
}

deploy_production() {
    echo -e "${LOCK} ${YELLOW}Configurando entorno PRODUCCIÓN...${NC}"
    echo ""
    
    if [ ! -f .env.production ]; then
        echo -e "${ERROR} ${RED}Archivo .env.production no encontrado${NC}"
        exit 1
    fi
    
    backup_current_env
    cp .env.production .env
    
    # Optimize for production
    echo -e "${INFO} ${CYAN}Optimizando para producción...${NC}"
    
    # Clear caches first to avoid conflicts
    php artisan config:clear >/dev/null 2>&1 || true
    php artisan route:clear >/dev/null 2>&1 || true
    php artisan view:clear >/dev/null 2>&1 || true
    
    # Try to cache configs, but don't fail if there are closure issues
    echo -e "${INFO} ${CYAN}Intentando cachear configuraciones...${NC}"
    if ! php artisan config:cache >/dev/null 2>&1; then
        echo -e "${WARNING} ${YELLOW}No se pudo cachear config (closures detectados) - continuando sin cache${NC}"
    fi
    
    php artisan route:cache >/dev/null 2>&1 || echo -e "${WARNING} ${YELLOW}Route cache falló - continuando${NC}"
    php artisan view:cache >/dev/null 2>&1 || echo -e "${WARNING} ${YELLOW}View cache falló - continuando${NC}"
    
    # Check if APP_KEY is set
    if ! grep -q "APP_KEY=base64:" .env; then
        echo -e "${WARNING} ${YELLOW}Generando APP_KEY...${NC}"
        php artisan key:generate --force
    fi
    
    echo ""
    echo -e "${SUCCESS} ${GREEN}¡Configuración PRODUCCIÓN activada correctamente!${NC}"
    echo ""
    echo -e "${WARNING} ${YELLOW}IMPORTANTE para producción:${NC}"
    echo -e "   ${RED}• Verificar DEUNA_WEBHOOK_SECRET en .env.production${NC}"
    echo -e "   ${RED}• Configurar credenciales reales de Datafast si es necesario${NC}"
    echo -e "   ${RED}• Verificar permisos de archivos y directorios${NC}"
    echo -e "   ${RED}• Ejecutar migraciones si es necesario: php artisan migrate${NC}"
}

show_status() {
    show_current_env
    
    echo -e "${INFO} ${CYAN}Archivos de configuración disponibles:${NC}"
    
    if [ -f .env.local ]; then
        echo -e "   ${SUCCESS} .env.local (desarrollo)"
    else
        echo -e "   ${ERROR} .env.local (no encontrado)"
    fi
    
    if [ -f .env.production ]; then
        echo -e "   ${SUCCESS} .env.production (producción)"
    else
        echo -e "   ${ERROR} .env.production (no encontrado)"
    fi
    
    if [ -f .env ]; then
        echo -e "   ${SUCCESS} .env (activo)"
    else
        echo -e "   ${ERROR} .env (no encontrado)"
    fi
    
    echo ""
    
    # Show recent backups
    local backups=$(ls .env.backup_* 2>/dev/null | tail -3 || true)
    if [ -n "$backups" ]; then
        echo -e "${INFO} ${CYAN}Backups recientes:${NC}"
        for backup in $backups; do
            echo -e "   ${YELLOW}$backup${NC}"
        done
        echo ""
    fi
}

show_help() {
    echo -e "${INFO} ${CYAN}Uso:${NC} ./deploy.sh [comando]"
    echo ""
    echo -e "${CYAN}Comandos disponibles:${NC}"
    echo -e "   ${YELLOW}local${NC}      - Configurar entorno de desarrollo"
    echo -e "   ${YELLOW}production${NC} - Configurar entorno de producción"
    echo -e "   ${YELLOW}status${NC}     - Mostrar estado actual del entorno"
    echo -e "   ${YELLOW}help${NC}       - Mostrar esta ayuda"
    echo ""
    echo -e "${CYAN}Ejemplos:${NC}"
    echo -e "   ${YELLOW}./deploy.sh local${NC}      - Cambiar a desarrollo"
    echo -e "   ${YELLOW}./deploy.sh production${NC} - Cambiar a producción"
    echo -e "   ${YELLOW}./deploy.sh status${NC}     - Ver configuración actual"
    echo ""
    echo -e "${INFO} ${CYAN}Los archivos .env anteriores se respaldan automáticamente${NC}"
}

# Main script logic
show_header

case "${1:-}" in
    "local")
        deploy_local
        show_current_env
        ;;
    "production")
        deploy_production
        show_current_env
        ;;
    "status")
        show_status
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    "")
        show_current_env
        show_help
        ;;
    *)
        echo -e "${ERROR} ${RED}Comando no reconocido: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac

echo -e "${BLUE}===============================================${NC}"
echo ""