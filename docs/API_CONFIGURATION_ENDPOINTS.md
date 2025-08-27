# 📚 DOCUMENTACIÓN DE ENDPOINTS DE CONFIGURACIÓN

**🎯 JORDAN FASE 1**: Documentación completa de endpoints de configuración pública

## 📋 RESUMEN

Los endpoints de configuración permiten al frontend obtener configuraciones dinámicas del sistema sin requerir permisos de administrador. Estos endpoints son accesibles por sellers y usuarios normales.

## 🔗 ENDPOINTS PÚBLICOS

### 1. **Configuración de Comisiones**
```
GET /api/configurations/platform-commission-public
```

**Autenticación:** No requerida  
**Controlador:** `PlatformCommissionController::getConfiguration`

**Estructura de Respuesta:**
```json
{
  "status": "success",
  "data": {
    "platform_commission_rate": 10.0,
    "seller_earnings_rate": 90.0,
    "last_updated": "2025-08-25T05:15:28.487775Z",
    "enabled": true
  }
}
```

**Valores:**
- `platform_commission_rate`: Comisión de la plataforma como porcentaje (10.0 = 10%)
- `seller_earnings_rate`: Porcentaje que conserva el vendedor (90.0 = 90%)
- `enabled`: Si el sistema de comisiones está activo
- `last_updated`: Timestamp de última actualización

---

### 2. **Configuración de Envío**
```
GET /api/configurations/shipping-public
```

**Autenticación:** No requerida  
**Controlador:** `ConfigurationController::getShippingConfigs`

**Estructura de Respuesta:**
```json
{
  "status": "success",
  "data": {
    "enabled": true,
    "default_cost": 5.0,
    "free_threshold": 50.0
  }
}
```

**Valores:**
- `enabled`: Si el cobro de envío está activo
- `default_cost`: Costo de envío por defecto (USD)
- `free_threshold`: Umbral para envío gratis (USD)

**⚠️ NOTA:** Este endpoint usa **snake_case** (diferente a otros que pueden usar camelCase)

---

### 3. **Configuración de Distribución de Envío**
```
GET /api/configurations/shipping-distribution-public
```

**Autenticación:** No requerida  
**Controlador:** `ShippingDistributionController::getConfiguration`

**Estructura de Respuesta:**
```json
{
  "status": "success",
  "data": {
    "single_seller_max_percentage": 80.0,
    "multiple_sellers_percentage_each": 40.0,
    "enabled": true,
    "last_updated": "2025-08-25T05:15:28.487775Z"
  }
}
```

**Valores:**
- `single_seller_max_percentage`: Porcentaje máximo del envío para un solo seller (80.0 = 80%)
- `multiple_sellers_percentage_each`: Porcentaje de envío por seller cuando hay múltiples (40.0 = 40%)
- `enabled`: Si la distribución de envío está activa
- `last_updated`: Timestamp de última actualización

---

### 4. **Configuración de Descuentos por Volumen**
```
GET /api/configurations/volume-discounts-public
```

**Autenticación:** No requerida  
**Controlador:** `ConfigurationController::getVolumeDiscountConfigs`

**Estructura de Respuesta:**
```json
{
  "status": "success", 
  "data": {
    "enabled": true,
    "stackable": true,
    "default_tiers": [
      {"quantity": 3, "discount": 5, "label": "Descuento 3+"},
      {"quantity": 5, "discount": 8, "label": "Descuento 5+"},
      {"quantity": 6, "discount": 10, "label": "Descuento 6+"},
      {"quantity": 10, "discount": 15, "label": "Descuento 10+"}
    ],
    "show_savings_message": true
  }
}
```

**Valores:**
- `enabled`: Si los descuentos por volumen están activos
- `stackable`: Si los descuentos se pueden combinar
- `default_tiers`: Array de tiers de descuento
  - `quantity`: Cantidad mínima requerida
  - `discount`: Descuento como porcentaje (10 = 10%)
  - `label`: Etiqueta descriptiva
- `show_savings_message`: Si mostrar mensaje de ahorros

---

### 5. **Configuración de Impuestos** ⭐ **NUEVO**
```
GET /api/configurations/tax-public
```

**Autenticación:** No requerida  
**Controlador:** `TaxConfigurationController::getConfiguration`

**Estructura de Respuesta:**
```json
{
  "status": "success",
  "data": {
    "tax_rate": 15.0,
    "tax_name": "IVA", 
    "enabled": true,
    "last_updated": "2025-08-25T05:15:28.487775Z"
  }
}
```

**Valores:**
- `tax_rate`: Tasa de impuesto como porcentaje (15.0 = 15%)
- `tax_name`: Nombre del impuesto (ej: "IVA", "Tax")
- `enabled`: Si el cálculo de impuestos está activo
- `last_updated`: Timestamp de última actualización

---

## 🔧 USO EN FRONTEND

### **ConfigurationManager**
El `ConfigurationManager` consume todos estos endpoints en paralelo usando `Promise.allSettled()` para obtener una configuración unificada.

```typescript
import ConfigurationManager from '@/core/services/ConfigurationManager';

const configManager = ConfigurationManager.getInstance();
const result = await configManager.getUnifiedConfig();

console.log('Tax rate:', result.config.tax_rate);
console.log('Platform commission:', result.config.platform_commission_rate);
console.log('Shipping cost:', result.config.shipping.default_cost);
console.log('Volume tiers:', result.config.volume_discounts);
```

### **Hooks Especializados**
```typescript
import { useFinancialConfig, useShippingConfig, useVolumeDiscounts } from '@/hooks/useUnifiedConfig';

// Hook de configuración financiera
const { taxRate, platformCommissionRate } = useFinancialConfig();

// Hook de configuración de envío
const { calculateShipping, isFreeShipping } = useShippingConfig();

// Hook de descuentos por volumen
const { tiers, getDiscountForQuantity } = useVolumeDiscounts();
```

---

## 🚨 MANEJO DE ERRORES

### **Respuestas de Error**
Todos los endpoints devuelven errores en este formato:
```json
{
  "status": "error",
  "message": "Error description",
  "errors": ["Detailed error 1", "Detailed error 2"]
}
```

### **Estrategia de Fallback**
Si un endpoint falla, el `ConfigurationManager` usa valores por defecto:
- `tax_rate`: 0.15 (15%)
- `platform_commission_rate`: 0.10 (10%)
- `shipping.default_cost`: 5.00 (USD)
- `shipping.free_threshold`: 50.00 (USD)
- `volume_discounts`: Tiers predefinidos

### **Monitoreo**
El sistema trackea:
- Cache hits/misses
- Endpoints fallidos
- Tiempos de respuesta
- Configuración utilizada (API vs fallback)

---

## 🔄 NAMING CONVENTIONS

### **⚠️ INCONSISTENCIAS CONOCIDAS**

1. **ConfigurationController::getShippingConfigs** usa **snake_case**:
   - ✅ `default_cost`
   - ✅ `free_threshold`

2. **Otros controllers usan snake_case**:
   - ✅ `platform_commission_rate`
   - ✅ `single_seller_max_percentage`
   - ✅ `default_tiers`

**RECOMENDACIÓN**: Todos los nuevos endpoints deben usar **snake_case** para consistencia.

---

## 📊 MÉTRICAS Y DEBUGGING

### **Estadísticas Disponibles**
```typescript
const stats = configManager.getStats();
console.log('Cache hit rate:', stats.cache_hit_rate);
console.log('API calls:', stats.api_calls);
console.log('Last cache hit:', stats.last_cache_hit);
```

### **Debugging en Desarrollo**
```typescript
import { useConfigurationDebug } from '@/hooks/useUnifiedConfig';

const { getDebugInfo, warnings } = useConfigurationDebug();
console.log('Debug info:', getDebugInfo());
console.log('Warnings:', warnings);
```

---

## 🛠️ TESTING

Todos los endpoints están cubiertos por tests unitarios:
- **Backend**: Feature tests en `tests/Feature/`
- **Frontend**: Tests unitarios en `ConfigurationManager.test.ts`

Para ejecutar tests:
```bash
# Backend
php artisan test --filter=Configuration

# Frontend  
npm run test ConfigurationManager.test.ts
```

---

## 📅 HISTORIAL DE CAMBIOS

**v1.0.0 - JORDAN Fase 1**
- ✅ Endpoints públicos implementados
- ✅ ConfigurationManager centralizado
- ✅ Hooks unificados
- ✅ Monitoreo de cache
- ✅ Documentación completa
- ✅ Tests unitarios
- ✅ Tax rate dinámico agregado
- ✅ Naming conventions normalizadas