/**
 * ========================================
 * CAMERA UTILITY
 * ========================================
 * Menangani akses kamera browser dan capture selfie
 */

class CameraHandler {
    constructor(videoElementId, canvasElementId = null) {
        this.videoElement = document.getElementById(videoElementId);
        this.canvasElement = canvasElementId ? document.getElementById(canvasElementId) : null;
        this.stream = null;
        this.isActive = false;
    }

    /**
     * Request camera access and start streaming
     * 
     * @return {Promise<boolean>} True if success
     */
    async start() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            this.videoElement.srcObject = this.stream;
            this.isActive = true;

            return true;
        } catch (error) {
            console.error('Camera error:', error);
            this.showError('Gagal mengakses kamera: ' + error.message);
            return false;
        }
    }

    /**
     * Stop camera stream
     * 
     * @return {void}
     */
    stop() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            this.isActive = false;
        }
    }

    /**
     * Capture photo from video stream
     * 
     * @return {string|null} Data URL of captured image or null if error
     */
    capture() {
        if (!this.videoElement || !this.stream) {
            this.showError('Kamera tidak aktif');
            return null;
        }

        // Create canvas if not exists
        let canvas = this.canvasElement;
        if (!canvas) {
            canvas = document.createElement('canvas');
        }

        const context = canvas.getContext('2d');
        canvas.width = this.videoElement.videoWidth;
        canvas.height = this.videoElement.videoHeight;

        // Draw video frame to canvas
        context.drawImage(this.videoElement, 0, 0);

        // Return image data URL
        return canvas.toDataURL('image/jpeg', 0.95);
    }

    /**
     * Convert data URL to blob
     * 
     * @param {string} dataUrl Data URL
     * @return {Blob} Image blob
     */
    dataUrlToBlob(dataUrl) {
        const parts = dataUrl.split(';base64,');
        const contentType = parts[0].split(':')[1];
        const raw = window.atob(parts[1]);
        const rawLength = raw.length;
        const uInt8Array = new Uint8Array(rawLength);

        for (let i = 0; i < rawLength; ++i) {
            uInt8Array[i] = raw.charCodeAt(i);
        }

        return new Blob([uInt8Array], { type: contentType });
    }

    /**
     * Download captured image
     * 
     * @param {string} dataUrl Data URL
     * @param {string} filename Filename
     * @return {void}
     */
    downloadImage(dataUrl, filename = 'selfie.jpg') {
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Show error message
     * 
     * @param {string} message Error message
     * @return {void}
     */
    showError(message) {
        alert(message);
        console.error(message);
    }

    /**
     * Check if camera is available
     * 
     * @return {boolean} True if camera is available
     */
    static isCameraAvailable() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    /**
     * Get camera device list
     * 
     * @return {Promise<Array>} Array of camera devices
     */
    static async getCameraDevices() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(device => device.kind === 'videoinput');
        } catch (error) {
            console.error('Error getting camera devices:', error);
            return [];
        }
    }
}
