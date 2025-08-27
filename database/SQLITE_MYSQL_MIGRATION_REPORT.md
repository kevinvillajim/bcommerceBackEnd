# REPORTE DE MIGRACIÓN SQLite → MySQL

**Fecha:** 2025-08-25 13:09:56
**Database MySQL:** comersia

## Tablas Migradas

- ❌ **full_log**:  filas - 
- ✅ **users**: 30 filas - Migración exitosa
- ✅ **categories**: 82 filas - Migración exitosa
- ✅ **sellers**: 6 filas - Migración exitosa
- ✅ **products**: 52 filas - Migración exitosa
- ✅ **orders**: 59 filas - Migración exitosa
- ✅ **order_items**: 62 filas - Migración exitosa
- ✅ **shopping_carts**: 5 filas - Migración exitosa
- ✅ **cart_items**: 0 filas - Tabla vacía
- ✅ **payments**: 0 filas - Tabla vacía
- ✅ **ratings**: 48 filas - Migración exitosa
- ✅ **chats**: 6 filas - Migración exitosa
- ✅ **messages**: 42 filas - Migración exitosa
- ✅ **volume_discounts**: 1 filas - Migración exitosa
- ✅ **password_reset_tokens**: 0 filas - Tabla vacía
- ✅ **sessions**: 0 filas - Tabla vacía
- ✅ **cache**: 52 filas - Migración exitosa
- ✅ **cache_locks**: 0 filas - Tabla vacía
- ✅ **jobs**: 6 filas - Migración exitosa
- ✅ **job_batches**: 0 filas - Tabla vacía
- ✅ **failed_jobs**: 0 filas - Tabla vacía
- ✅ **personal_access_tokens**: 0 filas - Tabla vacía
- ✅ **user_strikes**: 8 filas - Migración exitosa
- ✅ **shipping_history**: 96 filas - Migración exitosa
- ✅ **shipping_route_points**: 61 filas - Migración exitosa
- ✅ **carriers**: 0 filas - Tabla vacía
- ✅ **admins**: 3 filas - Migración exitosa
- ✅ **accounting_accounts**: 0 filas - Tabla vacía
- ✅ **accounting_transactions**: 0 filas - Tabla vacía
- ✅ **accounting_entries**: 0 filas - Tabla vacía
- ✅ **invoices**: 0 filas - Tabla vacía
- ✅ **sri_transactions**: 0 filas - Tabla vacía
- ✅ **invoice_items**: 0 filas - Tabla vacía
- ✅ **feedback**: 12 filas - Migración exitosa
- ✅ **discount_codes**: 11 filas - Migración exitosa
- ✅ **notifications**: 501 filas - Migración exitosa
- ✅ **favorites**: 2 filas - Migración exitosa
- ✅ **seller_orders**: 56 filas - Migración exitosa
- ✅ **configurations**: 108 filas - Migración exitosa
- ✅ **shippings**: 23 filas - Migración exitosa
- ✅ **email_verification_tokens**: 0 filas - Tabla vacía
- ✅ **user_interactions**: 148 filas - Migración exitosa
- ✅ **seller_applications**: 1 filas - Migración exitosa

## Resumen

- **Tablas procesadas:** 43
- **Tablas exitosas:** 42
- **Total filas migradas:** 1481
- **Tasa de éxito:** 97.67%

## Log de Migración

```
[13:09:55] ✅ Conexiones establecidas correctamente
[13:09:55] 🚀 INICIANDO MIGRACIÓN SQLite → MySQL
[13:09:55] ===================================================
[13:09:56] ✅ Conexión SQLite verificada
[13:09:56] ✅ Conexión MySQL verificada
[13:09:56] 📋 Encontradas 43 tablas en SQLite
[13:09:56] 🔄 Migrando tabla: users
[13:09:56]    📊 Migradas 30/30 filas
[13:09:56]    ✅ Tabla users migrada exitosamente (30 filas)
[13:09:56] 🔄 Migrando tabla: categories
[13:09:56]    📊 Migradas 82/82 filas
[13:09:56]    ✅ Tabla categories migrada exitosamente (82 filas)
[13:09:56] 🔄 Migrando tabla: sellers
[13:09:56]    📊 Migradas 6/6 filas
[13:09:56]    ✅ Tabla sellers migrada exitosamente (6 filas)
[13:09:56] 🔄 Migrando tabla: products
[13:09:56]    📊 Migradas 52/52 filas
[13:09:56]    ✅ Tabla products migrada exitosamente (52 filas)
[13:09:56] 🔄 Migrando tabla: orders
[13:09:56]    📊 Migradas 59/59 filas
[13:09:56]    ✅ Tabla orders migrada exitosamente (59 filas)
[13:09:56] 🔄 Migrando tabla: order_items
[13:09:56]    📊 Migradas 62/62 filas
[13:09:56]    ✅ Tabla order_items migrada exitosamente (62 filas)
[13:09:56] 🔄 Migrando tabla: shopping_carts
[13:09:56]    📊 Migradas 5/5 filas
[13:09:56]    ✅ Tabla shopping_carts migrada exitosamente (5 filas)
[13:09:56] 🔄 Migrando tabla: cart_items
[13:09:56]    ⚠️  Tabla cart_items está vacía
[13:09:56] 🔄 Migrando tabla: payments
[13:09:56]    ⚠️  Tabla payments está vacía
[13:09:56] 🔄 Migrando tabla: ratings
[13:09:56]    📊 Migradas 48/48 filas
[13:09:56]    ✅ Tabla ratings migrada exitosamente (48 filas)
[13:09:56] 🔄 Migrando tabla: chats
[13:09:56]    📊 Migradas 6/6 filas
[13:09:56]    ✅ Tabla chats migrada exitosamente (6 filas)
[13:09:56] 🔄 Migrando tabla: messages
[13:09:56]    📊 Migradas 42/42 filas
[13:09:56]    ✅ Tabla messages migrada exitosamente (42 filas)
[13:09:56] 🔄 Migrando tabla: volume_discounts
[13:09:56]    📊 Migradas 1/1 filas
[13:09:56]    ✅ Tabla volume_discounts migrada exitosamente (1 filas)
[13:09:56] 🔄 Migrando tabla: password_reset_tokens
[13:09:56]    ⚠️  Tabla password_reset_tokens está vacía
[13:09:56] 🔄 Migrando tabla: sessions
[13:09:56]    ⚠️  Tabla sessions está vacía
[13:09:56] 🔄 Migrando tabla: cache
[13:09:56]    📊 Migradas 52/52 filas
[13:09:56]    ✅ Tabla cache migrada exitosamente (52 filas)
[13:09:56] 🔄 Migrando tabla: cache_locks
[13:09:56]    ⚠️  Tabla cache_locks está vacía
[13:09:56] 🔄 Migrando tabla: jobs
[13:09:56]    📊 Migradas 6/6 filas
[13:09:56]    ✅ Tabla jobs migrada exitosamente (6 filas)
[13:09:56] 🔄 Migrando tabla: job_batches
[13:09:56]    ⚠️  Tabla job_batches está vacía
[13:09:56] 🔄 Migrando tabla: failed_jobs
[13:09:56]    ⚠️  Tabla failed_jobs está vacía
[13:09:56] 🔄 Migrando tabla: personal_access_tokens
[13:09:56]    ⚠️  Tabla personal_access_tokens está vacía
[13:09:56] 🔄 Migrando tabla: user_strikes
[13:09:56]    📊 Migradas 8/8 filas
[13:09:56]    ✅ Tabla user_strikes migrada exitosamente (8 filas)
[13:09:56] 🔄 Migrando tabla: shipping_history
[13:09:56]    📊 Migradas 96/96 filas
[13:09:56]    ✅ Tabla shipping_history migrada exitosamente (96 filas)
[13:09:56] 🔄 Migrando tabla: shipping_route_points
[13:09:56]    📊 Migradas 61/61 filas
[13:09:56]    ✅ Tabla shipping_route_points migrada exitosamente (61 filas)
[13:09:56] 🔄 Migrando tabla: carriers
[13:09:56]    ⚠️  Tabla carriers está vacía
[13:09:56] 🔄 Migrando tabla: admins
[13:09:56]    📊 Migradas 3/3 filas
[13:09:56]    ✅ Tabla admins migrada exitosamente (3 filas)
[13:09:56] 🔄 Migrando tabla: accounting_accounts
[13:09:56]    ⚠️  Tabla accounting_accounts está vacía
[13:09:56] 🔄 Migrando tabla: accounting_transactions
[13:09:56]    ⚠️  Tabla accounting_transactions está vacía
[13:09:56] 🔄 Migrando tabla: accounting_entries
[13:09:56]    ⚠️  Tabla accounting_entries está vacía
[13:09:56] 🔄 Migrando tabla: invoices
[13:09:56]    ⚠️  Tabla invoices está vacía
[13:09:56] 🔄 Migrando tabla: sri_transactions
[13:09:56]    ⚠️  Tabla sri_transactions está vacía
[13:09:56] 🔄 Migrando tabla: invoice_items
[13:09:56]    ⚠️  Tabla invoice_items está vacía
[13:09:56] 🔄 Migrando tabla: feedback
[13:09:56]    📊 Migradas 12/12 filas
[13:09:56]    ✅ Tabla feedback migrada exitosamente (12 filas)
[13:09:56] 🔄 Migrando tabla: discount_codes
[13:09:56]    📊 Migradas 11/11 filas
[13:09:56]    ✅ Tabla discount_codes migrada exitosamente (11 filas)
[13:09:56] 🔄 Migrando tabla: notifications
[13:09:56]    📊 Migradas 500/501 filas
[13:09:56]    📊 Migradas 501/501 filas
[13:09:56]    ✅ Tabla notifications migrada exitosamente (501 filas)
[13:09:56] 🔄 Migrando tabla: favorites
[13:09:56]    📊 Migradas 2/2 filas
[13:09:56]    ✅ Tabla favorites migrada exitosamente (2 filas)
[13:09:56] 🔄 Migrando tabla: seller_orders
[13:09:56]    📊 Migradas 56/56 filas
[13:09:56]    ✅ Tabla seller_orders migrada exitosamente (56 filas)
[13:09:56] 🔄 Migrando tabla: configurations
[13:09:56]    📊 Migradas 108/108 filas
[13:09:56]    ✅ Tabla configurations migrada exitosamente (108 filas)
[13:09:56] 🔄 Migrando tabla: shippings
[13:09:56]    📊 Migradas 23/23 filas
[13:09:56]    ✅ Tabla shippings migrada exitosamente (23 filas)
[13:09:56] 🔄 Migrando tabla: email_verification_tokens
[13:09:56]    ⚠️  Tabla email_verification_tokens está vacía
[13:09:56] 🔄 Migrando tabla: user_interactions
[13:09:56]    📊 Migradas 148/148 filas
[13:09:56]    ✅ Tabla user_interactions migrada exitosamente (148 filas)
[13:09:56] 🔄 Migrando tabla: seller_applications
[13:09:56]    📊 Migradas 1/1 filas
[13:09:56]    ✅ Tabla seller_applications migrada exitosamente (1 filas)
[13:09:56] ✅ MIGRACIÓN COMPLETADA EXITOSAMENTE
[13:09:56] 
📊 REPORTE DE MIGRACIÓN
[13:09:56] ===================================================
[13:09:56] ❌ full_log:  filas - 
[13:09:56] ✅ users: 30 filas - Migración exitosa
[13:09:56] ✅ categories: 82 filas - Migración exitosa
[13:09:56] ✅ sellers: 6 filas - Migración exitosa
[13:09:56] ✅ products: 52 filas - Migración exitosa
[13:09:56] ✅ orders: 59 filas - Migración exitosa
[13:09:56] ✅ order_items: 62 filas - Migración exitosa
[13:09:56] ✅ shopping_carts: 5 filas - Migración exitosa
[13:09:56] ✅ cart_items: 0 filas - Tabla vacía
[13:09:56] ✅ payments: 0 filas - Tabla vacía
[13:09:56] ✅ ratings: 48 filas - Migración exitosa
[13:09:56] ✅ chats: 6 filas - Migración exitosa
[13:09:56] ✅ messages: 42 filas - Migración exitosa
[13:09:56] ✅ volume_discounts: 1 filas - Migración exitosa
[13:09:56] ✅ password_reset_tokens: 0 filas - Tabla vacía
[13:09:56] ✅ sessions: 0 filas - Tabla vacía
[13:09:56] ✅ cache: 52 filas - Migración exitosa
[13:09:56] ✅ cache_locks: 0 filas - Tabla vacía
[13:09:56] ✅ jobs: 6 filas - Migración exitosa
[13:09:56] ✅ job_batches: 0 filas - Tabla vacía
[13:09:56] ✅ failed_jobs: 0 filas - Tabla vacía
[13:09:56] ✅ personal_access_tokens: 0 filas - Tabla vacía
[13:09:56] ✅ user_strikes: 8 filas - Migración exitosa
[13:09:56] ✅ shipping_history: 96 filas - Migración exitosa
[13:09:56] ✅ shipping_route_points: 61 filas - Migración exitosa
[13:09:56] ✅ carriers: 0 filas - Tabla vacía
[13:09:56] ✅ admins: 3 filas - Migración exitosa
[13:09:56] ✅ accounting_accounts: 0 filas - Tabla vacía
[13:09:56] ✅ accounting_transactions: 0 filas - Tabla vacía
[13:09:56] ✅ accounting_entries: 0 filas - Tabla vacía
[13:09:56] ✅ invoices: 0 filas - Tabla vacía
[13:09:56] ✅ sri_transactions: 0 filas - Tabla vacía
[13:09:56] ✅ invoice_items: 0 filas - Tabla vacía
[13:09:56] ✅ feedback: 12 filas - Migración exitosa
[13:09:56] ✅ discount_codes: 11 filas - Migración exitosa
[13:09:56] ✅ notifications: 501 filas - Migración exitosa
[13:09:56] ✅ favorites: 2 filas - Migración exitosa
[13:09:56] ✅ seller_orders: 56 filas - Migración exitosa
[13:09:56] ✅ configurations: 108 filas - Migración exitosa
[13:09:56] ✅ shippings: 23 filas - Migración exitosa
[13:09:56] ✅ email_verification_tokens: 0 filas - Tabla vacía
[13:09:56] ✅ user_interactions: 148 filas - Migración exitosa
[13:09:56] ✅ seller_applications: 1 filas - Migración exitosa
[13:09:56] 
📈 RESUMEN:
[13:09:56]    • Tablas procesadas: 43
[13:09:56]    • Tablas exitosas: 42
[13:09:56]    • Filas migradas: 1481
[13:09:56]    • Tasa de éxito: 97.67%
```
