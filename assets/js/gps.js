/**
 * ========================================
 * GPS/GEOLOCATION UTILITY
 * ========================================
 * Menangani akses GPS dan geolocation
 */

class GPSHandler {
    constructor() {
        this.currentPosition = null;
        this.isTracking = false;
        this.watchId = null;
    }

    /**
     * Get current GPS position once
     * 
     * @return {Promise<object>} Position object {latitude, longitude, accuracy}
     */
    async getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!this.isGPSAvailable()) {
                reject(new Error('GPS tidak tersedia di device Anda'));
                return;
            }

            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const coords = position.coords;
                    this.currentPosition = {
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                        accuracy: coords.accuracy
                    };
                    resolve(this.currentPosition);
                },
                (error) => {
                    this.handleGPSError(error);
                    reject(new Error(this.getGPSErrorMessage(error)));
                },
                options
            );
        });
    }

    /**
     * Start tracking GPS position
     * 
     * @param {Function} callback Callback function saat position berubah
     * @return {void}
     */
    startTracking(callback) {
        if (this.isTracking) {
            console.warn('GPS tracking sudah aktif');
            return;
        }

        if (!this.isGPSAvailable()) {
            console.error('GPS tidak tersedia');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };

        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                const coords = position.coords;
                this.currentPosition = {
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy: coords.accuracy
                };
                callback(this.currentPosition);
            },
            (error) => {
                this.handleGPSError(error);
            },
            options
        );

        this.isTracking = true;
    }

    /**
     * Stop tracking GPS position
     * 
     * @return {void}
     */
    stopTracking() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            this.isTracking = false;
        }
    }

    /**
     * Get current cached position
     * 
     * @return {object|null} Current position or null
     */
    getPosition() {
        return this.currentPosition;
    }

    /**
     * Check if GPS is available
     * 
     * @return {boolean} True if GPS available
     */
    isGPSAvailable() {
        return !!navigator.geolocation;
    }

    /**
     * Handle GPS error
     * 
     * @param {GeolocationPositionError} error Error object
     * @return {void}
     */
    handleGPSError(error) {
        console.error('GPS Error:', error);
    }

    /**
     * Get GPS error message
     * 
     * @param {GeolocationPositionError} error Error object
     * @return {string} Error message
     */
    getGPSErrorMessage(error) {
        switch (error.code) {
            case error.PERMISSION_DENIED:
                return 'Akses GPS ditolak. Silakan berikan izin untuk mengakses lokasi.';
            case error.POSITION_UNAVAILABLE:
                return 'Informasi lokasi tidak tersedia.';
            case error.TIMEOUT:
                return 'Waktu tunggu GPS habis. Silakan coba lagi.';
            default:
                return 'Terjadi kesalahan saat mengakses GPS.';
        }
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     * 
     * @param {float} lat1 Latitude 1
     * @param {float} lon1 Longitude 1
     * @param {float} lat2 Latitude 2
     * @param {float} lon2 Longitude 2
     * @return {float} Distance in meters
     */
    static calculateDistance(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371000; // Earth radius in meters
        
        const dLat = this.toRadian(lat2 - lat1);
        const dLon = this.toRadian(lon2 - lon1);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                 Math.cos(this.toRadian(lat1)) * Math.cos(this.toRadian(lat2)) *
                 Math.sin(dLon / 2) * Math.sin(dLon / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const distance = earthRadius * c;
        
        return Math.round(distance * 100) / 100; // Round to 2 decimal places
    }

    /**
     * Convert degree to radian
     * 
     * @param {float} degree Degree value
     * @return {float} Radian value
     */
    static toRadian(degree) {
        return degree * (Math.PI / 180);
    }

    /**
     * Check if position is within radius
     * 
     * @param {float} currentLat Current latitude
     * @param {float} currentLon Current longitude
     * @param {float} centerLat Center latitude
     * @param {float} centerLon Center longitude
     * @param {float} radius Radius in meters
     * @return {boolean} True if within radius
     */
    static isWithinRadius(currentLat, currentLon, centerLat, centerLon, radius) {
        const distance = this.calculateDistance(currentLat, currentLon, centerLat, centerLon);
        return distance <= radius;
    }
}
