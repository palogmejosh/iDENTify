# HID Authentication Device Client Installation Guide

## Overview

This guide provides step-by-step instructions for installing the **HID Authentication Device Client** (formerly known as **DigitalPersona Lite Client**) required for fingerprint scanner functionality in the iDENTify system.

## What is the HID Authentication Device Client?

The HID Authentication Device Client is the essential software driver and service that enables communication between:
- Your fingerprint scanner hardware (DigitalPersona/Crossmatch devices)
- The iDENTify web application
- The fingerprint capture and authentication features

**Without this client installed, fingerprint scanning will not work.**

---

## System Requirements

### Operating System
- **Windows 64-bit** (Required)
- Supported versions:
  - Windows 11 (64-bit)
  - Windows 10 (64-bit)
  - Windows Server 2019/2022

### Hardware Requirements
- Compatible DigitalPersona/Crossmatch/HID fingerprint scanner
- USB port for scanner connection
- Minimum 2GB RAM
- 500MB available disk space

### Administrator Access
- **Administrator privileges required** for installation

---

## Installation Steps

### Step 1: Access the Download Page

1. Open your web browser
2. Navigate to: **https://crossmatch.hid.gl/lite-client/**
3. You should see the page titled: "Product Download - HID Authentication Device Client"

### Step 2: Download the Installer

The download page offers two installer formats. Choose the one that best fits your needs:

#### Option A: EXE Installer (Recommended for most users)
- Click on **"Installer (.exe)"** link
- File name: `HID_Authentication_Device_Client_x.x.x.exe`
- This is easier to use and includes a graphical setup wizard

#### Option B: MSI Installer (For IT administrators)
- Click on **"Installer (.msi)"** link
- File name: `HID_Authentication_Device_Client_x.x.x.msi`
- Suitable for silent installations and deployment via Group Policy

**Note:** The download should start automatically. If it doesn't start within a few seconds, click the appropriate direct download link.

### Step 3: Locate the Downloaded File

1. Check your browser's download folder (typically `C:\Users\[YourName]\Downloads`)
2. Look for the file you just downloaded
3. Verify the file size and name to ensure download completed successfully

### Step 4: Run the Installer

#### For EXE Installer:
1. **Right-click** on the downloaded `.exe` file
2. Select **"Run as administrator"**
3. Click **"Yes"** when prompted by User Account Control (UAC)

#### For MSI Installer:
1. **Right-click** on the downloaded `.msi` file
2. Select **"Run as administrator"** or just double-click
3. Click **"Yes"** when prompted by User Account Control (UAC)

### Step 5: Follow the Installation Wizard

1. **Welcome Screen**
   - Read the welcome message
   - Click **"Next"** to continue

2. **License Agreement**
   - Read the End User License Agreement (EULA)
   - Select **"I accept the terms in the License Agreement"**
   - Click **"Next"**

3. **Installation Location**
   - Default location: `C:\Program Files\DigitalPersona\`
   - You can change this if needed, but **default is recommended**
   - Click **"Next"**

4. **Installation Type**
   - Select **"Complete"** (recommended) to install all components
   - Click **"Install"** to begin installation

5. **Installation Progress**
   - Wait while files are being installed (typically 1-3 minutes)
   - Do not close the installer during this process

6. **Completion**
   - Click **"Finish"** to complete the installation
   - You may be prompted to **restart your computer** - do so if requested

### Step 6: Verify Installation

After installation (and restart if required):

1. **Check Windows Services**
   - Press `Win + R` to open Run dialog
   - Type `services.msc` and press Enter
   - Look for **"DpHost"** or **"DigitalPersona Authentication Service"**
   - Status should be **"Running"**

2. **Check Installed Programs**
   - Press `Win + R`
   - Type `appwiz.cpl` and press Enter
   - Look for **"HID Authentication Device Client"** in the programs list

3. **Check Installation Directory**
   - Navigate to `C:\Program Files\DigitalPersona\`
   - You should see folders like:
     - `Bin`
     - `Config`
     - `Drivers`

### Step 7: Connect Your Fingerprint Scanner

1. **Plug in your fingerprint scanner** to a USB port
2. Windows will detect the device and install drivers automatically
3. Wait for the "Device ready" notification
4. The scanner LED should light up (if equipped)

### Step 8: Test the Installation

1. **Open your web browser**
2. Navigate to your iDENTify installation
3. Go to the fingerprint test page: `http://localhost/iDENTify/test_fingerprint.html`
4. Click **"Test Connection"** button
5. You should see: **"Connected to DigitalPersona service! Scanner is ready."**
6. Try capturing a fingerprint using the **"Capture Fingerprint"** button

---

## Troubleshooting

### Issue 1: Download Doesn't Start
**Solution:**
- Ensure you have a stable internet connection
- Try a different browser (Chrome, Firefox, Edge)
- Disable browser extensions temporarily
- Use the direct download link if automatic download fails

### Issue 2: "Windows Protected Your PC" Warning
**Solution:**
- Click **"More info"**
- Click **"Run anyway"**
- This is normal for some installers; the software is safe

### Issue 3: Installation Fails
**Solution:**
- Ensure you ran the installer as administrator
- Temporarily disable antivirus software
- Check that you have enough disk space (500MB minimum)
- Close all other applications before installing

### Issue 4: Service Not Running
**Solution:**
1. Open Services (`services.msc`)
2. Find **"DpHost"** service
3. Right-click and select **"Start"**
4. Right-click again, select **"Properties"**
5. Set "Startup type" to **"Automatic"**
6. Click **"Apply"** and **"OK"**

### Issue 5: Scanner Not Detected
**Solution:**
- Try a different USB port
- Unplug and replug the scanner
- Restart the DpHost service
- Check Device Manager for driver issues:
  - Press `Win + X` and select "Device Manager"
  - Look under "Biometric devices"
  - Update driver if needed

### Issue 6: Connection Fails in Test Page
**Solution:**
1. Verify DpHost service is running
2. Clear browser cache and reload
3. Check Windows Firewall isn't blocking the service
4. Restart your computer
5. Reinstall the HID Authentication Device Client

### Issue 7: "Service Not Available" Error
**Solution:**
- Ensure WebSDK files are present in `node_modules/@digitalpersona/`
- Check that the service is running on default port
- Verify no other software is using the same port

---

## Uninstalling

If you need to uninstall or reinstall:

1. Press `Win + R`
2. Type `appwiz.cpl` and press Enter
3. Find **"HID Authentication Device Client"**
4. Right-click and select **"Uninstall"**
5. Follow the uninstallation wizard
6. Restart your computer when complete

---

## Additional Information

### Service Details
- **Service Name:** DpHost
- **Display Name:** DigitalPersona Authentication Service
- **Startup Type:** Automatic
- **Default Port:** Various (managed by service)

### File Locations
- **Installation Directory:** `C:\Program Files\DigitalPersona\`
- **Service Executable:** `C:\Program Files\DigitalPersona\Bin\DPHost.exe`
- **Configuration:** `C:\Program Files\DigitalPersona\Config\`

### Compatible Devices
The HID Authentication Device Client supports:
- DigitalPersona U.are.U 4500 Fingerprint Reader
- DigitalPersona U.are.U 5100 Fingerprint Reader
- DigitalPersona U.are.U 5160 Fingerprint Reader
- Crossmatch fingerprint scanners
- HID biometric readers
- Other compatible devices

### Version Information
- Always download the **latest version** from the official website
- Check for updates periodically for bug fixes and improvements
- Older versions may not be compatible with newer Windows updates

---

## Support and Resources

### Official Resources
- **Download Page:** https://crossmatch.hid.gl/lite-client/
- **Manufacturer:** HID Global Corporation (part of ASSA ABLOY)
- **Copyright:** © 2024 HID Global Corporation

### iDENTify System Resources
- **Test Page:** `http://localhost/iDENTify/test_fingerprint.html`
- **WebSDK Location:** `node_modules/@digitalpersona/websdk/`
- **System Documentation:** Check other files in the `docs/` folder

### Getting Help
If you encounter issues not covered in this guide:
1. Check Windows Event Viewer for error details
2. Review DpHost service logs
3. Contact your system administrator
4. Consult HID Global support resources

---

## Security Notes

### Important Security Considerations:
- Only download the client from the official HID Global website
- Verify the digital signature of the installer before running
- Keep the client updated to the latest version
- Use only compatible and authorized fingerprint scanners
- Follow your organization's security policies for biometric data

### Firewall Configuration:
If your firewall is blocking the service:
1. Open Windows Firewall settings
2. Allow `DPHost.exe` through the firewall
3. Add inbound rules for the DigitalPersona service if needed

---

## Quick Reference

### Installation Checklist
- [ ] Windows 64-bit system
- [ ] Administrator access
- [ ] Downloaded HID Authentication Device Client
- [ ] Installed as administrator
- [ ] Restarted computer (if required)
- [ ] DpHost service running
- [ ] Fingerprint scanner connected
- [ ] Test page shows successful connection
- [ ] Fingerprint capture working

### Essential Troubleshooting Commands

```powershell
# Check if service is running
Get-Service -Name "DpHost"

# Start the service
Start-Service -Name "DpHost"

# Restart the service
Restart-Service -Name "DpHost"

# Check service status in detail
Get-Service -Name "DpHost" | Format-List *
```

### Quick Test
To quickly verify everything is working:
1. Ensure scanner is connected
2. Open `test_fingerprint.html` in browser
3. Click "Test Connection"
4. Status should show "Connected"
5. Click "Capture Fingerprint"
6. Place finger on scanner
7. Fingerprint image should appear

---

## Frequently Asked Questions

### Q: Do I need to install this on every computer?
**A:** Yes, the HID Authentication Device Client must be installed on every workstation that will use fingerprint scanning.

### Q: Can I use the fingerprint scanner without this client?
**A:** No, the client is required for the scanner to communicate with the iDENTify system.

### Q: Is this software free?
**A:** The client software is provided by HID Global. Licensing may depend on your hardware purchase.

### Q: Will this work on 32-bit Windows?
**A:** No, this client only supports Windows 64-bit systems.

### Q: Can I install this on Mac or Linux?
**A:** No, this client is only compatible with Microsoft Windows 64-bit.

### Q: How often should I update the client?
**A:** Check for updates quarterly or when you experience issues. Always test updates in a non-production environment first.

### Q: Can multiple users use one scanner?
**A:** Yes, multiple users can use the same scanner. The authentication is based on the enrolled fingerprint data, not the scanner hardware.

---

**Last Updated:** October 2025  
**Document Version:** 1.0  
**For:** iDENTify Patient Identification System
