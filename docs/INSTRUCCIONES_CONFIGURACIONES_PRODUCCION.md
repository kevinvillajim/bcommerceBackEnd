# 🚀 INSTRUCCIONES PARA CONFIGURACIONES DE PRODUCCIÓN

## ⚠️ CRÍTICO: Configuraciones Necesarias para Producción

Este documento contiene las instrucciones para ejecutar las configuraciones críticas que necesita el sistema para funcionar correctamente en producción.

## 📋 ¿Por qué son necesarias estas configuraciones?

El sistema BCommerce depende de múltiples configuraciones almacenadas en la base de datos para:

- **Cálculos de descuentos por volumen**: 3+=5%, 6+=10%, 12+=15%
- **Cálculos de envío**: $5.00 (gratis desde $50.00)
- **Impuestos (IVA Ecuador)**: 15%
- **Comisiones de plataforma**: 10%
- **Integración con Datafast**: Fase 1 habilitada
- **Sistema de recomendaciones**: ML habilitado

Sin estas configuraciones, el sistema puede fallar en:
- ❌ Checkout y cálculos de precios
- ❌ Procesamiento de pagos
- ❌ Generación de órdenes
- ❌ Funcionalidades del frontend

## 🎯 OPCIÓN 1: Ejecutar Seeder (RECOMENDADO)

```bash
# Desde el directorio del backend
cd BCommerceBackEnd

# Ejecutar el seeder con las configuraciones de producción
php artisan db:seed --class=ProductionConfigurationSeeder
```

### ✅ Lo que hace este comando:
- Verifica que las tablas `configurations` y `platform_configurations` existan
- Inserta SOLO las configuraciones que no existen (no duplica)
- Muestra un reporte detallado de qué se insertó
- Es SEGURO ejecutar múltiples veces

### 📊 Configuraciones que se insertarán:

#### Sistema
- `system.production_ready` = true
- `system.environment` = production
- `debug.enable_extensive_logging` = true

#### Descuentos por Volumen (CRÍTICO)
- `volume_discounts.global_enabled` = true
- `volume_discounts.global_tiers` = JSON con tiers 3+, 6+, 12+

#### Envío (CRÍTICO)
- `shipping.enabled` = true
- `shipping.default_cost` = 5.00
- `shipping.free_threshold` = 50.00

#### Impuestos (CRÍTICO)
- `payment.taxRate` = 15
- `tax.iva_rate` = 15
- `tax.enabled` = true

#### Plataforma (CRÍTICO)
- `platform.commission_rate` = 10
- Configuraciones de distribución de envío

#### Datafast Ecuador
- `datafast.production_mode` = phase1
- `datafast.phase_1_enabled` = true
- `datafast.base_url` = https://ccapi-stg.datafast.com.ec

#### Recomendaciones
- `recommendations.enabled` = true
- `analytics.track_product_views` = true

## 🎯 OPCIÓN 2: Ejecutar Migración

```bash
# Si prefieres usar migración (menos recomendado)
php artisan migrate --path=database/migrations/2025_08_26_183503_create_default_configurations_for_production.php --force
```

## 🔍 VERIFICAR CONFIGURACIONES

Después de ejecutar, puedes verificar que se insertaron correctamente:

```sql
-- Ver configuraciones críticas
SELECT * FROM configurations WHERE `key` IN (
    'system.production_ready',
    'volume_discounts.global_enabled',
    'shipping.enabled',
    'payment.taxRate',
    'platform.commission_rate'
) ORDER BY `group`, `key`;

-- Ver configuraciones de plataforma
SELECT * FROM platform_configurations ORDER BY category, `key`;
```

## ⚠️ NOTAS IMPORTANTES

1. **SEGURIDAD**: El seeder verifica si cada configuración ya existe antes de insertarla
2. **IDEMPOTENTE**: Es seguro ejecutar múltiples veces
3. **SIN DUPLICADOS**: No creará registros duplicados
4. **RESPALDO**: Hace backup automático de configuraciones existentes

## 🚨 SI HAY PROBLEMAS DE CONEXIÓN

Si tienes problemas de acceso a la base de datos, las configuraciones también están disponibles como:

1. **Migración**: `database/migrations/2025_08_26_183503_create_default_configurations_for_production.php`
2. **Seeder**: `database/seeders/ProductionConfigurationSeeder.php`

Ambos archivos contienen los mismos datos y pueden ser ejecutados cuando tengas acceso a la DB.

## 📞 CONTACTO

Si tienes problemas con la ejecución, revisa:
1. Conexión a base de datos en `.env`
2. Que las tablas `configurations` y `platform_configurations` existan
3. Permisos de usuario de base de datos

---

**🎯 RESULTADO ESPERADO**: Después de ejecutar, el sistema tendrá todas las configuraciones necesarias para funcionar correctamente en producción, incluyendo cálculos de precios, envío, impuestos y pagos.