# ⚠️ ADVERTENCIA CRÍTICA: RefreshDatabase

## ❌ NUNCA MÁS USAR RefreshDatabase EN TESTS

El uso de `RefreshDatabase` en los tests causa que se borren TODOS los datos de la base de datos.

### ❌ Problemas causados:
- Borra completamente la base de datos de desarrollo
- Elimina usuarios, productos, categorías, pedidos
- Pérdida total de datos sin respaldo automático

### ✅ Alternativas seguras:
1. **Tests unitarios**: No requieren base de datos
2. **Datos específicos**: Crear/eliminar solo los datos necesarios para el test
3. **Mocks**: Usar mocks en lugar de datos reales
4. **Base de datos de test separada**: Configurar entorno completamente aislado

### 🔧 Tests ya corregidos:
- `tests/Feature/ShippingConfigurationTest.php` - RefreshDatabase comentado

### 📝 Regla de oro:
**ANTES DE EJECUTAR CUALQUIER TEST, VERIFICAR QUE NO USE RefreshDatabase**

### 🚨 Si necesitas ejecutar tests:
1. Hacer backup de la base de datos PRIMERO
2. Verificar que el test no use RefreshDatabase
3. Usar entorno de test completamente separado

---
**Fecha**: 2025-08-19
**Creado después de**: Pérdida accidental de datos por RefreshDatabase en tests