# REPORTE DE MIGRACIÓN SQLite → MySQL

**Fecha:** 2025-08-19 22:09:29
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
[22:09:28] ✅ Conexiones establecidas correctamente
[22:09:28] 🚀 INICIANDO MIGRACIÓN SQLite → MySQL
[22:09:28] ===================================================
[22:09:28] ✅ Conexión SQLite verificada
[22:09:28] ✅ Conexión MySQL verificada
[22:09:28] 📋 Encontradas 43 tablas en SQLite
[22:09:28] 🔄 Migrando tabla: users
[22:09:29]    📊 Migradas 30/30 filas
[22:09:29]    ✅ Tabla users migrada exitosamente (30 filas)
[22:09:29] 🔄 Migrando tabla: categories
[22:09:29]    📊 Migradas 82/82 filas
[22:09:29]    ✅ Tabla categories migrada exitosamente (82 filas)
[22:09:29] 🔄 Migrando tabla: sellers
[22:09:29]    📊 Migradas 6/6 filas
[22:09:29]    ✅ Tabla sellers migrada exitosamente (6 filas)
[22:09:29] 🔄 Migrando tabla: products
[22:09:29]    📊 Migradas 52/52 filas
[22:09:29]    ✅ Tabla products migrada exitosamente (52 filas)
[22:09:29] 🔄 Migrando tabla: orders
[22:09:29]    📊 Migradas 59/59 filas
[22:09:29]    ✅ Tabla orders migrada exitosamente (59 filas)
[22:09:29] 🔄 Migrando tabla: order_items
[22:09:29]    📊 Migradas 62/62 filas
[22:09:29]    ✅ Tabla order_items migrada exitosamente (62 filas)
[22:09:29] 🔄 Migrando tabla: shopping_carts
[22:09:29]    📊 Migradas 5/5 filas
[22:09:29]    ✅ Tabla shopping_carts migrada exitosamente (5 filas)
[22:09:29] 🔄 Migrando tabla: cart_items
[22:09:29]    ⚠️  Tabla cart_items está vacía
[22:09:29] 🔄 Migrando tabla: payments
[22:09:29]    ⚠️  Tabla payments está vacía
[22:09:29] 🔄 Migrando tabla: ratings
[22:09:29]    📊 Migradas 48/48 filas
[22:09:29]    ✅ Tabla ratings migrada exitosamente (48 filas)
[22:09:29] 🔄 Migrando tabla: chats
[22:09:29]    📊 Migradas 6/6 filas
[22:09:29]    ✅ Tabla chats migrada exitosamente (6 filas)
[22:09:29] 🔄 Migrando tabla: messages
[22:09:29]    📊 Migradas 42/42 filas
[22:09:29]    ✅ Tabla messages migrada exitosamente (42 filas)
[22:09:29] 🔄 Migrando tabla: volume_discounts
[22:09:29]    📊 Migradas 1/1 filas
[22:09:29]    ✅ Tabla volume_discounts migrada exitosamente (1 filas)
[22:09:29] 🔄 Migrando tabla: password_reset_tokens
[22:09:29]    ⚠️  Tabla password_reset_tokens está vacía
[22:09:29] 🔄 Migrando tabla: sessions
[22:09:29]    ⚠️  Tabla sessions está vacía
[22:09:29] 🔄 Migrando tabla: cache
[22:09:29]    📊 Migradas 52/52 filas
[22:09:29]    ✅ Tabla cache migrada exitosamente (52 filas)
[22:09:29] 🔄 Migrando tabla: cache_locks
[22:09:29]    ⚠️  Tabla cache_locks está vacía
[22:09:29] 🔄 Migrando tabla: jobs
[22:09:29]    📊 Migradas 6/6 filas
[22:09:29]    ✅ Tabla jobs migrada exitosamente (6 filas)
[22:09:29] 🔄 Migrando tabla: job_batches
[22:09:29]    ⚠️  Tabla job_batches está vacía
[22:09:29] 🔄 Migrando tabla: failed_jobs
[22:09:29]    ⚠️  Tabla failed_jobs está vacía
[22:09:29] 🔄 Migrando tabla: personal_access_tokens
[22:09:29]    ⚠️  Tabla personal_access_tokens está vacía
[22:09:29] 🔄 Migrando tabla: user_strikes
[22:09:29]    📊 Migradas 8/8 filas
[22:09:29]    ✅ Tabla user_strikes migrada exitosamente (8 filas)
[22:09:29] 🔄 Migrando tabla: shipping_history
[22:09:29]    📊 Migradas 96/96 filas
[22:09:29]    ✅ Tabla shipping_history migrada exitosamente (96 filas)
[22:09:29] 🔄 Migrando tabla: shipping_route_points
[22:09:29]    📊 Migradas 61/61 filas
[22:09:29]    ✅ Tabla shipping_route_points migrada exitosamente (61 filas)
[22:09:29] 🔄 Migrando tabla: carriers
[22:09:29]    ⚠️  Tabla carriers está vacía
[22:09:29] 🔄 Migrando tabla: admins
[22:09:29]    📊 Migradas 3/3 filas
[22:09:29]    ✅ Tabla admins migrada exitosamente (3 filas)
[22:09:29] 🔄 Migrando tabla: accounting_accounts
[22:09:29]    ⚠️  Tabla accounting_accounts está vacía
[22:09:29] 🔄 Migrando tabla: accounting_transactions
[22:09:29]    ⚠️  Tabla accounting_transactions está vacía
[22:09:29] 🔄 Migrando tabla: accounting_entries
[22:09:29]    ⚠️  Tabla accounting_entries está vacía
[22:09:29] 🔄 Migrando tabla: invoices
[22:09:29]    ⚠️  Tabla invoices está vacía
[22:09:29] 🔄 Migrando tabla: sri_transactions
[22:09:29]    ⚠️  Tabla sri_transactions está vacía
[22:09:29] 🔄 Migrando tabla: invoice_items
[22:09:29]    ⚠️  Tabla invoice_items está vacía
[22:09:29] 🔄 Migrando tabla: feedback
[22:09:29]    📊 Migradas 12/12 filas
[22:09:29]    ✅ Tabla feedback migrada exitosamente (12 filas)
[22:09:29] 🔄 Migrando tabla: discount_codes
[22:09:29]    📊 Migradas 11/11 filas
[22:09:29]    ✅ Tabla discount_codes migrada exitosamente (11 filas)
[22:09:29] 🔄 Migrando tabla: notifications
[22:09:29]    📊 Migradas 500/501 filas
[22:09:29]    📊 Migradas 501/501 filas
[22:09:29]    ✅ Tabla notifications migrada exitosamente (501 filas)
[22:09:29] 🔄 Migrando tabla: favorites
[22:09:29]    📊 Migradas 2/2 filas
[22:09:29]    ✅ Tabla favorites migrada exitosamente (2 filas)
[22:09:29] 🔄 Migrando tabla: seller_orders
[22:09:29]    📊 Migradas 56/56 filas
[22:09:29]    ✅ Tabla seller_orders migrada exitosamente (56 filas)
[22:09:29] 🔄 Migrando tabla: configurations
[22:09:29]    📊 Migradas 108/108 filas
[22:09:29]    ✅ Tabla configurations migrada exitosamente (108 filas)
[22:09:29] 🔄 Migrando tabla: shippings
[22:09:29]    📊 Migradas 23/23 filas
[22:09:29]    ✅ Tabla shippings migrada exitosamente (23 filas)
[22:09:29] 🔄 Migrando tabla: email_verification_tokens
[22:09:29]    ⚠️  Tabla email_verification_tokens está vacía
[22:09:29] 🔄 Migrando tabla: user_interactions
[22:09:29]    📊 Migradas 148/148 filas
[22:09:29]    ✅ Tabla user_interactions migrada exitosamente (148 filas)
[22:09:29] 🔄 Migrando tabla: seller_applications
[22:09:29]    📊 Migradas 1/1 filas
[22:09:29]    ✅ Tabla seller_applications migrada exitosamente (1 filas)
[22:09:29] ✅ MIGRACIÓN COMPLETADA EXITOSAMENTE
[22:09:29] 
📊 REPORTE DE MIGRACIÓN
[22:09:29] ===================================================
[22:09:29] ❌ full_log:  filas - 
[22:09:29] ✅ users: 30 filas - Migración exitosa
[22:09:29] ✅ categories: 82 filas - Migración exitosa
[22:09:29] ✅ sellers: 6 filas - Migración exitosa
[22:09:29] ✅ products: 52 filas - Migración exitosa
[22:09:29] ✅ orders: 59 filas - Migración exitosa
[22:09:29] ✅ order_items: 62 filas - Migración exitosa
[22:09:29] ✅ shopping_carts: 5 filas - Migración exitosa
[22:09:29] ✅ cart_items: 0 filas - Tabla vacía
[22:09:29] ✅ payments: 0 filas - Tabla vacía
[22:09:29] ✅ ratings: 48 filas - Migración exitosa
[22:09:29] ✅ chats: 6 filas - Migración exitosa
[22:09:29] ✅ messages: 42 filas - Migración exitosa
[22:09:29] ✅ volume_discounts: 1 filas - Migración exitosa
[22:09:29] ✅ password_reset_tokens: 0 filas - Tabla vacía
[22:09:29] ✅ sessions: 0 filas - Tabla vacía
[22:09:29] ✅ cache: 52 filas - Migración exitosa
[22:09:29] ✅ cache_locks: 0 filas - Tabla vacía
[22:09:29] ✅ jobs: 6 filas - Migración exitosa
[22:09:29] ✅ job_batches: 0 filas - Tabla vacía
[22:09:29] ✅ failed_jobs: 0 filas - Tabla vacía
[22:09:29] ✅ personal_access_tokens: 0 filas - Tabla vacía
[22:09:29] ✅ user_strikes: 8 filas - Migración exitosa
[22:09:29] ✅ shipping_history: 96 filas - Migración exitosa
[22:09:29] ✅ shipping_route_points: 61 filas - Migración exitosa
[22:09:29] ✅ carriers: 0 filas - Tabla vacía
[22:09:29] ✅ admins: 3 filas - Migración exitosa
[22:09:29] ✅ accounting_accounts: 0 filas - Tabla vacía
[22:09:29] ✅ accounting_transactions: 0 filas - Tabla vacía
[22:09:29] ✅ accounting_entries: 0 filas - Tabla vacía
[22:09:29] ✅ invoices: 0 filas - Tabla vacía
[22:09:29] ✅ sri_transactions: 0 filas - Tabla vacía
[22:09:29] ✅ invoice_items: 0 filas - Tabla vacía
[22:09:29] ✅ feedback: 12 filas - Migración exitosa
[22:09:29] ✅ discount_codes: 11 filas - Migración exitosa
[22:09:29] ✅ notifications: 501 filas - Migración exitosa
[22:09:29] ✅ favorites: 2 filas - Migración exitosa
[22:09:29] ✅ seller_orders: 56 filas - Migración exitosa
[22:09:29] ✅ configurations: 108 filas - Migración exitosa
[22:09:29] ✅ shippings: 23 filas - Migración exitosa
[22:09:29] ✅ email_verification_tokens: 0 filas - Tabla vacía
[22:09:29] ✅ user_interactions: 148 filas - Migración exitosa
[22:09:29] ✅ seller_applications: 1 filas - Migración exitosa
[22:09:29] 
📈 RESUMEN:
[22:09:29]    • Tablas procesadas: 43
[22:09:29]    • Tablas exitosas: 42
[22:09:29]    • Filas migradas: 1481
[22:09:29]    • Tasa de éxito: 97.67%
```
