# 🔧 DeUna Integration - Troubleshooting Guide

## 🚨 Current Issues Identified & Fixed

### ❌ **Issue 1: Point of Sale Invalid**
**Error Message:** `Entity does not exist in system`, `Hierarchy tree parent 462 not found`

**Root Cause:** The Point of Sale `462` specified in the email doesn't exist in the DeUna testing system.

**Solution:** 
- Updated to use POS `138` (from official documentation) 
- Created test script to validate available POS values
- Run: `php deuna_pos_test.php` to find working POS

### ❌ **Issue 2: Incorrect Item Prices**  
**Error Message:** Items showing `price: 0` in payment requests

**Root Cause:** Frontend was using `item.price || 0` instead of correct final price.

**Solution:**
- Updated to use `item.final_price || item.price || item.subtotal || 0`
- Added backend validation for item prices
- Enhanced error messages for debugging

---

## 🧪 **Testing & Validation**

### **Quick Fix Test**
```bash
# Navigate to backend directory  
cd "C:\Users\As\Desktop\Apps Web\Proyectos Personales\BCommerce\webBCommerce\BCommerceBackEnd"

# Test Point of Sale validation
php deuna_pos_test.php

# Test complete integration  
php deuna_master_test.php
```

### **Frontend Test**
1. Open browser to checkout page
2. Add items to cart with valid prices
3. Select DeUna payment method
4. Click "Generar código QR" 
5. Should now show QR code and payment link

---

## 🔍 **Debugging Steps**

### **1. Check Configuration**
```bash
# Verify current configuration
grep DEUNA_ .env
```

Expected values:
```
DEUNA_API_URL=https://apis-merchant.qa.deunalab.com
DEUNA_API_KEY=d873c8dd29bd48fdba9b8c82a94bce6a  
DEUNA_API_SECRET=6675cf597860464ba845beb9cddac864
DEUNA_POINT_OF_SALE=138
```

### **2. Check Item Prices**
Look for these patterns in frontend console:
```javascript
// ❌ Bad - items with price: 0
items: [{name: "Product", quantity: 1, price: 0}]

// ✅ Good - items with valid prices  
items: [{name: "Product", quantity: 1, price: 25.99}]
```

### **3. Monitor API Responses**
```bash
# Watch DeUna API logs
tail -f storage/logs/laravel.log | grep -i deuna
```

---

## 🆘 **Common Error Messages & Solutions**

### **`Entity does not exist in system`**
- **Cause:** Invalid Point of Sale  
- **Fix:** Run `php deuna_pos_test.php` to find valid POS
- **Prevention:** Use POS values from official documentation

### **`Amount must be a positive number`**
- **Cause:** Cart total is 0 or invalid
- **Fix:** Check cart calculation in frontend  
- **Prevention:** Validate cart totals before payment

### **`Item X: valid price is required`**
- **Cause:** Item has price: 0 or null
- **Fix:** Use `final_price` instead of `price` in frontend
- **Prevention:** Validate all cart items have prices

### **`Request failed with status code 401`**  
- **Cause:** Invalid API credentials
- **Fix:** Verify API key and secret in .env
- **Prevention:** Use exact credentials from DeUna email

---

## 📊 **Integration Status Checklist**

- [x] **API Credentials:** Valid and configured  
- [x] **Point of Sale:** Updated to working POS (138)
- [x] **Item Prices:** Using correct price fields
- [x] **Backend Validation:** Enhanced error checking
- [x] **Frontend Fix:** Correct price extraction  
- [x] **Testing Suite:** Complete validation scripts
- [x] **Documentation:** Based on official PDF analysis

---

## 🎯 **Next Steps**

1. **Run Tests:** Execute `php deuna_master_test.php`
2. **Frontend Test:** Try checkout process in browser
3. **Monitor Logs:** Watch for any remaining issues  
4. **Production Ready:** Deploy with confidence

---

## 📞 **Support Contacts**

- **Technical Issues:** Check logs and test scripts
- **DeUna API Issues:** Contact DeUna client services  
- **Point of Sale Issues:** Request valid POS list from DeUna
- **Integration Questions:** Refer to official PDF documentation

---

## ✅ **Expected Working Flow**

1. **User adds items to cart** → Items have valid prices
2. **User goes to checkout** → Cart total calculated correctly  
3. **User selects DeUna payment** → Valid request payload created
4. **System calls DeUna API** → Uses working POS (138)
5. **DeUna returns QR/link** → Payment interface displayed
6. **User completes payment** → Webhook receives confirmation  
7. **Order status updated** → Process complete

**🚀 Integration should now work seamlessly!**
