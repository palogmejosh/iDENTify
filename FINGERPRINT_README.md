# 🔐 Fingerprint Scanner Setup

## Quick Links

- 🚨 **[Having Issues? Start Here](docs/QUICK_FIX.md)** - Scanner not responding? Service stopped?
- 📱 **[Setting Up New Device? Go Here](docs/MULTI_DEVICE_SETUP.md)** - Complete step-by-step guide
- 📚 **[All Documentation](docs/README.md)** - Full documentation index

---

## The One Thing You Need to Know

### ❌ What NOT to Change

**When deploying to other devices, you do NOT need to change:**
- ❌ SDK paths in `js/fingerprint-config.js`
- ❌ Any code in `edit_patient.php`
- ❌ Any configuration files
- ❌ Anything in `node_modules`

**Why?** The SDK files are served FROM the web server. Client devices download them via HTTP.

### ✅ What to Change (Per Device)

**On EACH device that will use a physical scanner:**
1. Install DigitalPersona Lite Client
2. Start DpHost service
3. Connect scanner via USB
4. Access web application via browser

**That's it!** No file copying, no path configuration, no code changes.

---

## Quick Start

### Current Machine (Running XAMPP)

```powershell
# 1. Check if service is running
Get-Service -Name "DpHost"

# 2. If stopped, start it (as Administrator)
Start-Service -Name "DpHost"

# 3. Test at: http://localhost/iDENTify/test_fingerprint.html
```

### New Device (Different Computer)

1. **Install DigitalPersona Lite Client** on the new device
   - Download: https://www.crossmatch.com/company/support/downloads/

2. **Start DpHost service** (as Administrator)
   ```powershell
   Start-Service -Name "DpHost"
   ```

3. **Connect scanner** via USB

4. **Open browser** and navigate to:
   ```
   http://[YOUR_SERVER_IP]/iDENTify/edit_patient.php?id=1
   ```

---

## 📚 Full Documentation

All documentation is in the **[docs](docs/)** folder:

| Document | When to Use |
|----------|-------------|
| **[QUICK_FIX.md](docs/QUICK_FIX.md)** | Scanner isn't working right now |
| **[MULTI_DEVICE_SETUP.md](docs/MULTI_DEVICE_SETUP.md)** | Setting up on new device |
| **[FINGERPRINT_SETUP.md](docs/FINGERPRINT_SETUP.md)** | Complete technical guide |
| **[README.md](docs/README.md)** | Documentation index |

---

## Configuration Files

| File | Purpose | Change for Multi-Device? |
|------|---------|-------------------------|
| `js/fingerprint-config.js` | SDK path configuration | ❌ No |
| `edit_patient.php` | Patient form with fingerprint | ❌ No |
| `test_fingerprint.html` | Test page | ❌ No |
| `node_modules/@digitalpersona/` | SDK files | ❌ No |

**All configuration is already done!**

---

## Architecture

```
Web Server (192.168.1.100)
├── XAMPP/Apache running
├── SDK files in node_modules/
└── Configuration already set

    ↓ HTTP (browser loads SDK files)

Client Device 1 (192.168.1.50)
├── DigitalPersona client installed
├── DpHost service running
├── Scanner connected via USB
└── Browser → http://192.168.1.100/iDENTify/

Client Device 2 (192.168.1.51)
├── DigitalPersona client installed
├── DpHost service running
├── Scanner connected via USB
└── Browser → http://192.168.1.100/iDENTify/
```

---

## Troubleshooting Tools

- **Test Page:** `http://localhost/iDENTify/test_fingerprint.html`
- **Diagnostic Script:** `Check-FingerprintSetup.ps1`
- **Service Starter:** `Start-FingerprintService.bat` (right-click → Run as Administrator)

---

## Need Help?

1. **Immediate issues?** → [docs/QUICK_FIX.md](docs/QUICK_FIX.md)
2. **Setting up new device?** → [docs/MULTI_DEVICE_SETUP.md](docs/MULTI_DEVICE_SETUP.md)
3. **Want full details?** → [docs/README.md](docs/README.md)

---

**Last Updated:** October 6, 2025
