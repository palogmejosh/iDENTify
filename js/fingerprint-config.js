/**
 * DigitalPersona Fingerprint Scanner Configuration
 * 
 * This file contains the configuration for the DigitalPersona SDK paths.
 * Update these paths based on where the SDK is installed on your system.
 * 
 * IMPORTANT: These paths must point to where the DigitalPersona WebSDK
 * files are located on the web server serving this application.
 */

const FingerprintConfig = {
  /**
   * SDK Installation Paths
   * 
   * Default paths assume the SDK files are in the node_modules directory
   * within your web application. If you have installed the SDK elsewhere,
   * update these paths accordingly.
   */
  sdkPaths: {
    // WebSDK client library (handles communication with DigitalPersona service)
    websdk: 'node_modules/@digitalpersona/websdk/dist/websdk.client.min.js',
    
    // Core library (contains fingerprint data structures and utilities)
    core: 'node_modules/@digitalpersona/core/dist/es5.bundles/index.umd.min.js',
    
    // Devices library (handles fingerprint reader devices)
    devices: 'node_modules/@digitalpersona/devices/dist/es5.bundles/index.umd.min.js'
  },

  /**
   * Service Configuration
   * 
   * DigitalPersona service connection settings
   */
  service: {
    // The service name running on the client machine
    serviceName: 'DpHost',
    
    // Default service port (usually WebSocket on port 8080 or 8443)
    // The WebSDK will auto-detect the correct port
    servicePort: null,
    
    // Connection retry attempts
    retryAttempts: 3,
    
    // Connection timeout in milliseconds
    timeout: 10000
  },

  /**
   * Device Configuration
   */
  device: {
    // Preferred sample format (PngImage is most compatible)
    sampleFormat: 'PngImage',
    
    // Image quality (0-100, higher is better but larger)
    imageQuality: 80,
    
    // Capture timeout in milliseconds
    captureTimeout: 15000
  },

  /**
   * Client Requirements
   * 
   * These are informational and shown in error messages
   */
  requirements: {
    // DigitalPersona software installation path on CLIENT machine
    // This is where users should install the DigitalPersona Lite Client
    clientInstallPath: 'C:\\Program Files\\DigitalPersona',
    
    // Alternative installation path for 32-bit systems
    clientInstallPathAlt: 'C:\\Program Files (x86)\\DigitalPersona',
    
    // Minimum version required
    minVersion: '4.0',
    
    // Supported devices
    supportedDevices: [
      'U.are.U 4500',
      'U.are.U 4500 Fingerprint Reader',
      'DigitalPersona U.are.U 4500'
    ]
  },

  /**
   * Deployment Modes
   * 
   * Different modes for different deployment scenarios
   */
  deploymentMode: {
    // Current mode: 'development', 'production', 'testing'
    current: 'production',
    
    // Debug logging enabled
    debug: true
  }
};

/**
 * Helper function to get the full SDK path
 * @param {string} sdkName - Name of the SDK (websdk, core, devices)
 * @returns {string} Full path to the SDK file
 */
FingerprintConfig.getSDKPath = function(sdkName) {
  const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
  const sdkPath = this.sdkPaths[sdkName];
  
  if (!sdkPath) {
    console.error(`Unknown SDK: ${sdkName}`);
    return null;
  }
  
  // If path is absolute, return as-is
  if (sdkPath.startsWith('http://') || sdkPath.startsWith('https://') || sdkPath.startsWith('/')) {
    return sdkPath;
  }
  
  // Otherwise, make it relative to the current page
  return sdkPath;
};

/**
 * Helper function to validate if SDK is loaded
 * @returns {Object} Validation result with status and missing libraries
 */
FingerprintConfig.validateSDK = function() {
  const result = {
    isValid: true,
    missing: [],
    message: ''
  };
  
  // Check WebSDK
  if (typeof WebSdk === 'undefined') {
    result.isValid = false;
    result.missing.push('WebSDK');
  }
  
  // Check dp (DigitalPersona) namespace
  if (typeof dp === 'undefined') {
    result.isValid = false;
    result.missing.push('DigitalPersona Core/Devices');
  } else {
    // Check devices
    if (typeof dp.devices === 'undefined') {
      result.isValid = false;
      result.missing.push('DigitalPersona Devices');
    }
  }
  
  if (!result.isValid) {
    result.message = `Missing required libraries: ${result.missing.join(', ')}. Please check SDK paths in fingerprint-config.js`;
  } else {
    result.message = 'All SDK libraries loaded successfully';
  }
  
  return result;
};

/**
 * Helper function to log configuration information
 */
FingerprintConfig.logInfo = function() {
  if (!this.deploymentMode.debug) return;
  
  console.group('🔐 DigitalPersona Fingerprint Configuration');
  console.log('Deployment Mode:', this.deploymentMode.current);
  console.log('SDK Paths:', this.sdkPaths);
  console.log('Service Config:', this.service);
  console.log('Device Config:', this.device);
  console.log('Client Install Path:', this.requirements.clientInstallPath);
  
  const validation = this.validateSDK();
  if (validation.isValid) {
    console.log('✅ SDK Status:', validation.message);
  } else {
    console.error('❌ SDK Status:', validation.message);
  }
  
  console.groupEnd();
};

// Auto-log on load if in debug mode
if (FingerprintConfig.deploymentMode.debug) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      FingerprintConfig.logInfo();
    });
  } else {
    FingerprintConfig.logInfo();
  }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = FingerprintConfig;
}
