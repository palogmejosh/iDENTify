/**
 * DigitalPersona U.are.U 4500 Fingerprint Scanner Integration
 * 
 * This library provides integration with DigitalPersona fingerprint devices
 * using the WebSDK for capturing and processing fingerprints.
 * 
 * Requirements:
 * - DigitalPersona Web SDK installed on client machine
 * - U.are.U 4500 or compatible device connected
 * - HTTPS connection (required for WebUSB/WebHID APIs)
 */

class DigitalPersonaFingerprint {
    constructor(options = {}) {
        this.options = {
            imageFormat: 'PNG',
            imageQuality: 80,
            captureTimeout: 15000, // 15 seconds
            ...options
        };
        
        this.device = null;
        this.reader = null;
        this.currentSample = null;
        this.isCapturing = false;
        
        // Event callbacks
        this.onDeviceConnected = options.onDeviceConnected || (() => {});
        this.onDeviceDisconnected = options.onDeviceDisconnected || (() => {});
        this.onCaptureComplete = options.onCaptureComplete || (() => {});
        this.onCaptureError = options.onCaptureError || (() => {});
        this.onQualityFeedback = options.onQualityFeedback || (() => {});
    }
    
    /**
     * Initialize the fingerprint device
     */
    async initialize() {
        try {
            // Check if DigitalPersona WebSDK is available
            if (typeof Fingerprint === 'undefined') {
                throw new Error('DigitalPersona WebSDK not found. Please install the WebSDK on this machine.');
            }
            
            // Create reader instance
            this.reader = new Fingerprint.WebApi();
            
            // Check device availability
            await this.checkDeviceStatus();
            
            return {
                success: true,
                message: 'Fingerprint device initialized successfully'
            };
        } catch (error) {
            console.error('Failed to initialize fingerprint device:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }
    
    /**
     * Check if fingerprint device is connected
     */
    async checkDeviceStatus() {
        try {
            const devices = await this.reader.enumerateDevices();
            
            if (devices && devices.length > 0) {
                this.device = devices[0];
                this.onDeviceConnected(this.device);
                return {
                    success: true,
                    device: this.device,
                    deviceName: this.device
                };
            } else {
                throw new Error('No fingerprint device detected');
            }
        } catch (error) {
            this.onDeviceDisconnected();
            throw error;
        }
    }
    
    /**
     * Start fingerprint capture
     */
    async startCapture() {
        if (this.isCapturing) {
            return {
                success: false,
                message: 'Capture already in progress'
            };
        }
        
        this.isCapturing = true;
        
        try {
            // Check device first
            await this.checkDeviceStatus();
            
            // Start acquisition
            this.onQualityFeedback('Place your finger on the scanner...');
            
            const sample = await this.reader.startAcquisition(
                Fingerprint.SampleFormat.PngImage,
                this.device
            );
            
            if (sample && sample.samples && sample.samples.length > 0) {
                this.currentSample = sample.samples[0];
                
                // Convert to base64 image
                const imageData = this._arrayBufferToBase64(this.currentSample);
                const imageQuality = this.currentSample.quality || 0;
                
                this.isCapturing = false;
                
                const result = {
                    success: true,
                    image: `data:image/png;base64,${imageData}`,
                    quality: imageQuality,
                    timestamp: new Date().toISOString()
                };
                
                this.onCaptureComplete(result);
                return result;
            } else {
                throw new Error('Failed to capture fingerprint sample');
            }
        } catch (error) {
            this.isCapturing = false;
            this.onCaptureError(error);
            return {
                success: false,
                message: error.message
            };
        }
    }
    
    /**
     * Stop ongoing capture
     */
    async stopCapture() {
        try {
            if (this.reader && this.isCapturing) {
                await this.reader.stopAcquisition();
            }
            this.isCapturing = false;
            return { success: true };
        } catch (error) {
            console.error('Error stopping capture:', error);
            return { success: false, message: error.message };
        }
    }
    
    /**
     * Get current fingerprint image as base64
     */
    getCurrentImage() {
        if (this.currentSample) {
            const imageData = this._arrayBufferToBase64(this.currentSample);
            return `data:image/png;base64,${imageData}`;
        }
        return null;
    }
    
    /**
     * Clear current sample
     */
    clearSample() {
        this.currentSample = null;
    }
    
    /**
     * Convert ArrayBuffer to Base64
     */
    _arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;
        
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        
        return window.btoa(binary);
    }
    
    /**
     * Cleanup and release resources
     */
    async dispose() {
        try {
            await this.stopCapture();
            this.device = null;
            this.reader = null;
            this.currentSample = null;
        } catch (error) {
            console.error('Error disposing fingerprint device:', error);
        }
    }
}

// Alternative implementation using Web HID API (if DigitalPersona SDK not available)
class FingerprintDeviceWebHID {
    constructor(options = {}) {
        this.options = options;
        this.device = null;
        this.onCaptureComplete = options.onCaptureComplete || (() => {});
        this.onCaptureError = options.onCaptureError || (() => {});
    }
    
    async initialize() {
        try {
            if (!('hid' in navigator)) {
                throw new Error('WebHID not supported in this browser');
            }
            
            return {
                success: true,
                message: 'WebHID initialized'
            };
        } catch (error) {
            return {
                success: false,
                message: error.message
            };
        }
    }
    
    async requestDevice() {
        try {
            const devices = await navigator.hid.requestDevice({
                filters: [{
                    vendorId: 0x05ba // DigitalPersona vendor ID
                }]
            });
            
            if (devices.length > 0) {
                this.device = devices[0];
                await this.device.open();
                return { success: true, device: this.device };
            } else {
                throw new Error('No device selected');
            }
        } catch (error) {
            return {
                success: false,
                message: error.message
            };
        }
    }
    
    async dispose() {
        if (this.device) {
            await this.device.close();
            this.device = null;
        }
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { DigitalPersonaFingerprint, FingerprintDeviceWebHID };
}
