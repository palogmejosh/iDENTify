# 📱 Multi-Device Setup Guide for Fingerprint Scanner

## Step-by-Step Instructions for Running on Other Devices

This guide explains exactly what to change and configure when you want to use the fingerprint scanner on a different device than your web server.

---

## 🎯 Understanding the Setup

### Two Types of Devices

1. **Web Server Device** (Your XAMPP server)
   - Location: The computer running `C:\xampp\htdocs\iDENTify`
   - Hosts the web application and SDK files
   - Only needs 1 setup (one-time configuration)

2. **Client Device(s)** (Devices with fingerprint scanner)
   - Location: Any computer that will physically use the scanner
   - Can be the same as web server OR different computers on the network
   - Each client needs setup

---

## 📋 Configuration Scenarios

### Scenario A: Web Server = Client (Same Machine)

**Use Case:** You're testing on the same computer that runs XAMPP

**What to Change:** ❌ **NOTHING!** Already configured correctly

**Steps:**
1. Ensure DpHost service is running (see [Quick Fix](#quick-fix-start-dphost-service))
2. Connect scanner via USB
3. Access: `http://localhost/iDENTify/edit_patient.php?id=1`

---

### Scenario B: Client on Same Network (Different Machine)

**Use Case:** Web server at `192.168.1.100`, testing from device at `192.168.1.50`

**What to Change:**

#### ✅ On Web Server (192.168.1.100)
**Location:** No changes needed!

The SDK paths in `js/fingerprint-config.js` are already correct:
```javascript
sdkPaths: {
  websdk: 'node_modules/@digitalpersona/websdk/dist/websdk.client.min.js',
  core: 'node_modules/@digitalpersona/core/dist/es5.bundles/index.umd.min.js',
  devices: 'node_modules/@digitalpersona/devices/dist/es5.bundles/index.umd.min.js'
}
```

**Why?** These files are served FROM the web server to the browser. The paths are relative to the web application URL, not the client machine's file system.

#### ✅ On Client Device (192.168.1.50)

**IMPORTANT:** You do NOT copy any SDK files to the client machine. You only install the DigitalPersona service.

**Step-by-Step:**

1. **Install DigitalPersona Lite Client**
   - Download from: https://www.crossmatch.com/company/support/downloads/
   - Run installer **as Administrator**
   - Accept default installation path: `C:\Program Files\DigitalPersona\`
   - Restart computer if prompted

2. **Verify Service Installation**
   ```powershell
   Get-Service -Name "DpHost" | Select-Object Name, Status
   ```
   Expected output:
   ```
   Name   Status
   ----   ------
   DpHost Running
   ```

3. **Connect Fingerprint Scanner**
   - Plug U.are.U 4500 into USB port
   - Wait for Windows to install drivers
   - Check Device Manager → Biometric Devices

4. **Open Browser on Client Device**
   - Navigate to: `http://192.168.1.100/iDENTify/edit_patient.php?id=1`
   - Replace `192.168.1.100` with your web server's actual IP address

5. **Test Fingerprint Capture**
   - Click "Capture Fingerprint" button
   - Place finger on scanner
   - Image should capture successfully

---

### Scenario C: Multiple Client Devices

**Use Case:** Web server at one location, multiple workstations with scanners

**What to Change:**

#### ✅ On Web Server (One-Time Setup)
**Location:** `C:\xampp\htdocs\iDENTify\js\fingerprint-config.js`

**No changes needed** unless you want to customize settings:

```javascript
// Optional: Enable debug mode for troubleshooting
deploymentMode: {
  current: 'production',  // Change to 'development' for more logs
  debug: true             // Set to false to disable console logs
}
```

#### ✅ On Each Client Device (Repeat for Every Device)

Follow the **same steps as Scenario B** for EACH device:

1. Install DigitalPersona Lite Client
2. Start DpHost service
3. Connect scanner
4. Access web application via network

**Checklist per Device:**

| Step | Device 1 | Device 2 | Device 3 |
|------|----------|----------|----------|
| DigitalPersona installed | ☐ | ☐ | ☐ |
| DpHost service running | ☐ | ☐ | ☐ |
| Scanner connected | ☐ | ☐ | ☐ |
| Can access web app | ☐ | ☐ | ☐ |
| Fingerprint works | ☐ | ☐ | ☐ |

---

## 🔧 What NOT to Change

### ❌ Common Mistakes

1. **DO NOT copy SDK files to client machines**
   - The SDK files stay on the web server
   - Clients download them via HTTP when loading the page

2. **DO NOT modify `js/fingerprint-config.js` paths for multi-device**
   - The paths are web-server-relative, not client-relative
   - They work automatically for all clients

3. **DO NOT install node_modules on client devices**
   - node_modules only needs to exist on the web server
   - Clients access via the web application

4. **DO NOT change HTML file paths**
   - `edit_patient.php` script tags are already correct
   - They load from the server, not locally

---

## 📊 Configuration Matrix

| Component | Location | When to Change |
|-----------|----------|----------------|
| **SDK Files** | Web Server: `node_modules/@digitalpersona/` | Never (already correct) |
| **Configuration** | Web Server: `js/fingerprint-config.js` | Only for custom paths or debugging |
| **DigitalPersona Client** | Each Client: `C:\Program Files\DigitalPersona\` | Install on each device with scanner |
| **HTML/PHP Files** | Web Server: `edit_patient.php`, etc. | Never (already correct) |
| **DpHost Service** | Each Client: Windows Service | Must be running on each device |

---

## 🚀 Quick Start for New Device

Copy and paste this checklist for each new device:

### Device Setup Checklist

**Device Name:** ___________________  
**IP Address:** ___________________  
**Date:** ___________________

#### Installation Steps

- [ ] **Step 1:** Download DigitalPersona Lite Client
  - URL: https://www.crossmatch.com/company/support/downloads/
  - File saved to: ___________________

- [ ] **Step 2:** Install as Administrator
  - Right-click installer → "Run as administrator"
  - Installation path: `C:\Program Files\DigitalPersona\`
  - Restart required? ☐ Yes ☐ No

- [ ] **Step 3:** Verify Service
  ```powershell
  Get-Service -Name "DpHost"
  ```
  - Status: ☐ Running ☐ Stopped

- [ ] **Step 4:** If stopped, start service
  ```powershell
  Start-Service -Name "DpHost"
  ```
  - Service started successfully? ☐ Yes ☐ No

- [ ] **Step 5:** Connect Scanner
  - Scanner model: U.are.U 4500
  - USB port used: ___________________
  - LED lit? ☐ Yes ☐ No

- [ ] **Step 6:** Verify in Device Manager
  - Path: Device Manager → Biometric Devices
  - Device shown: ___________________
  - Status: ☐ Working ☐ Warning/Error

- [ ] **Step 7:** Test Network Connection
  - Web server IP: ___________________
  - Can ping server? ☐ Yes ☐ No
  ```powershell
  Test-Connection -ComputerName [SERVER_IP] -Count 2
  ```

- [ ] **Step 8:** Access Web Application
  - URL: `http://[SERVER_IP]/iDENTify/edit_patient.php?id=1`
  - Page loads? ☐ Yes ☐ No

- [ ] **Step 9:** Check Browser Console
  - Press F12 → Console tab
  - WebSDK loaded? ☐ Yes ☐ No
  - Any red errors? ☐ Yes ☐ No
  - If yes, note error: ___________________

- [ ] **Step 10:** Test Fingerprint Capture
  - Click "Capture Fingerprint" button
  - Place finger on scanner
  - Image captured? ☐ Yes ☐ No
  - Image quality: ☐ Good ☐ Fair ☐ Poor

#### Verification

- [ ] Scanner fully functional
- [ ] Can save patient record with fingerprint
- [ ] Fingerprint displays correctly in preview

**Setup completed by:** ___________________  
**Time taken:** ___________________  
**Notes:** ___________________

---

## 🔍 Troubleshooting by Device Type

### Web Server Issues

**Problem:** SDK files not found (404 errors in browser)

**Solution:**
```powershell
# Verify SDK files exist
Test-Path "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\websdk\dist\websdk.client.min.js"
Test-Path "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\core\dist\es5.bundles\index.umd.min.js"
Test-Path "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\devices\dist\es5.bundles\index.umd.min.js"

# If false, reinstall
cd C:\xampp\htdocs\iDENTify
npm install @digitalpersona/websdk @digitalpersona/core @digitalpersona/devices
```

### Client Device Issues

**Problem 1:** Service won't start

**Solution:**
```powershell
# Check if service exists
Get-Service -Name "DpHost" -ErrorAction SilentlyContinue

# If not found, reinstall DigitalPersona Lite Client

# If found but won't start, check permissions
# Run as Administrator:
Start-Service -Name "DpHost"
```

**Problem 2:** Scanner not detected

**Solution:**
1. Open Device Manager (`devmgmt.msc`)
2. Look for yellow warning icons
3. Right-click device → Update driver
4. Or uninstall and reconnect scanner

**Problem 3:** Can't access web server

**Solution:**
```powershell
# Test network connectivity
Test-Connection -ComputerName [SERVER_IP] -Count 4

# Check firewall on web server
# Windows Firewall → Allow Apache/HTTP
```

---

## 📝 Configuration File Reference

### When to Edit `js/fingerprint-config.js`

**File Location:** `C:\xampp\htdocs\iDENTify\js\fingerprint-config.js`

#### Scenario 1: Moving SDK to Custom Folder

If you want to organize SDK files differently:

```javascript
// Original (default - leave as-is)
sdkPaths: {
  websdk: 'node_modules/@digitalpersona/websdk/dist/websdk.client.min.js',
  core: 'node_modules/@digitalpersona/core/dist/es5.bundles/index.umd.min.js',
  devices: 'node_modules/@digitalpersona/devices/dist/es5.bundles/index.umd.min.js'
}

// Custom folder (ONLY if you move the files)
sdkPaths: {
  websdk: 'assets/digitalpersona/websdk.client.min.js',
  core: 'assets/digitalpersona/core.min.js',
  devices: 'assets/digitalpersona/devices.min.js'
}
```

**Steps to move SDK files:**

1. Create folder:
   ```powershell
   New-Item -ItemType Directory -Path "C:\xampp\htdocs\iDENTify\assets\digitalpersona" -Force
   ```

2. Copy files:
   ```powershell
   Copy-Item "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\websdk\dist\websdk.client.min.js" `
             "C:\xampp\htdocs\iDENTify\assets\digitalpersona\websdk.client.min.js"
   
   Copy-Item "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\core\dist\es5.bundles\index.umd.min.js" `
             "C:\xampp\htdocs\iDENTify\assets\digitalpersona\core.min.js"
   
   Copy-Item "C:\xampp\htdocs\iDENTify\node_modules\@digitalpersona\devices\dist\es5.bundles\index.umd.min.js" `
             "C:\xampp\htdocs\iDENTify\assets\digitalpersona\devices.min.js"
   ```

3. Update `js/fingerprint-config.js` with new paths (as shown above)

4. Test on all devices

#### Scenario 2: Enable/Disable Debug Mode

```javascript
deploymentMode: {
  current: 'production',  // Options: 'development', 'production', 'testing'
  debug: false            // Set to true for console logging
}
```

**When to enable debug:**
- Troubleshooting connection issues
- Testing new device setup
- Diagnosing scanner problems

**When to disable debug:**
- Production environment
- After confirming everything works

---

## 🎓 Summary: What Actually Changes Per Device

### On Web Server (One Time Only)
✅ **Already Done** - No changes needed!

Files are already configured:
- `js/fingerprint-config.js` ✅
- `edit_patient.php` ✅
- `test_fingerprint.html` ✅
- `node_modules/@digitalpersona/` ✅

### On Each Client Device (Per Device)
⚡ **Required for each device with a scanner:**

1. Install DigitalPersona Lite Client
2. Ensure DpHost service runs
3. Connect fingerprint scanner
4. Access web app via browser

**No file changes or configuration needed on client devices!**

---

## 💡 Key Concepts

### Path Types Explained

| Path Type | Example | Used For |
|-----------|---------|----------|
| **Web-relative** | `node_modules/@digitalpersona/websdk/dist/websdk.client.min.js` | Browser loading SDK from server |
| **File-system** | `C:\Program Files\DigitalPersona\` | DigitalPersona client installation |
| **Network** | `http://192.168.1.100/iDENTify/` | Accessing web app from client |

### Why SDK Paths Don't Change

When a browser on `192.168.1.50` loads `http://192.168.1.100/iDENTify/edit_patient.php`:

1. Browser requests the HTML from web server
2. HTML contains: `<script src="node_modules/@digitalpersona/websdk/dist/websdk.client.min.js"></script>`
3. Browser requests: `http://192.168.1.100/iDENTify/node_modules/@digitalpersona/websdk/dist/websdk.client.min.js`
4. Web server sends the file
5. Browser loads and executes it

**The path is relative to the WEB SERVER, not the client machine!**

---

## 📞 Quick Support Reference

### Test Commands

Run these on any client device to verify setup:

```powershell
# 1. Check DigitalPersona installation
Test-Path "C:\Program Files\DigitalPersona"

# 2. Check DpHost service
Get-Service -Name "DpHost" | Format-List Name, Status, StartType

# 3. Check scanner device
Get-PnpDevice -Class "Biometric" | Where-Object { $_.FriendlyName -like "*fingerprint*" }

# 4. Test network to web server
Test-Connection -ComputerName [YOUR_SERVER_IP] -Count 2

# 5. Check ports
Test-NetConnection -ComputerName "127.0.0.1" -Port 52181
Test-NetConnection -ComputerName "127.0.0.1" -Port 8080
```

### Expected Results

All should return positive results:
```
C:\Program Files\DigitalPersona     : True
DpHost Status                        : Running
Scanner Device                       : DigitalPersona Fingerprint Reader (OK)
Network Ping                         : Success
Port 52181                           : Open
```

---

## 🔄 Deployment Workflow

For IT administrators deploying to multiple devices:

### Phase 1: Web Server (Once)
1. ✅ Verify SDK files in `node_modules`
2. ✅ Verify `js/fingerprint-config.js` exists
3. ✅ Test locally on server machine
4. ✅ Document server IP address

### Phase 2: Client Devices (Per Device)
1. Install DigitalPersona Lite Client
2. Start DpHost service
3. Connect scanner
4. Test access to web server
5. Test fingerprint capture
6. Document device in inventory

### Phase 3: Verification (All Devices)
1. Test fingerprint capture from each device
2. Verify data saves correctly
3. Check performance/latency
4. Document any issues

---

**Questions?** See `FINGERPRINT_SETUP.md` for detailed troubleshooting or `QUICK_FIX.md` for common issues.

**Last Updated:** October 6, 2025
