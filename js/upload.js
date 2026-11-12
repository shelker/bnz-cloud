// Upload functionality with animations
class PhotoUploader {
    constructor() {
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        this.cameraInput = document.getElementById('cameraInput');
        this.cameraBtn = document.getElementById('cameraBtn');
        this.galleryBtn = document.getElementById('galleryBtn');
        this.uploadProgress = document.getElementById('uploadProgress');
        this.progressBar = document.getElementById('progressBar');
        this.progressText = document.getElementById('progressText');
        this.successOverlay = document.getElementById('successOverlay');
        this.uploadContent = document.getElementById('uploadContent');
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDragAndDrop();
    }

    setupEventListeners() {
        // Upload area click
        this.uploadArea.addEventListener('click', () => {
            this.fileInput.click();
        });

        // Camera button
        this.cameraBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.cameraInput.click();
        });

        // Gallery button
        this.galleryBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.fileInput.click();
        });

        // File input change
        this.fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });

        // Camera input change
        this.cameraInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
    }

    setupDragAndDrop() {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.uploadArea.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });

        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.uploadArea.addEventListener(eventName, () => {
                this.uploadArea.classList.add('border-narrenzunft', 'bg-red-50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.uploadArea.addEventListener(eventName, () => {
                this.uploadArea.classList.remove('border-narrenzunft', 'bg-red-50');
            }, false);
        });

        // Handle dropped files
        this.uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFiles(files);
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    handleFiles(files) {
        if (files.length === 0) return;

        // Validate files - be generous, only check size
        const validFiles = Array.from(files).filter(file => {
            // Only check file size - be generous with file types
            if (file.size === 0) {
                this.showError('Die Datei "' + file.name + '" ist leer.');
                return false;
            }
            
            if (file.size > 200 * 1024 * 1024) {
                this.showError('Dateien dürfen maximal 200MB groß sein!');
                return false;
            }
            
            // Allow all files - server will do final validation if needed
            return true;
        });

        if (validFiles.length === 0) return;

        this.uploadFiles(validFiles);
    }

    async uploadFiles(files) {
        this.showProgress();
        this.hideSuccess();

        const formData = new FormData();
        files.forEach(file => {
            formData.append('photos[]', file);
        });

        try {
            const response = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server antwortete nicht mit JSON');
            }

            const result = await response.json();

            if (result.success) {
                const fileType = files.length === 1 && files[0].type.startsWith('video/') ? 'Video' : 
                               files.length === 1 ? 'Foto' : 'Dateien';
                this.showSuccess(`Erfolgreich ${files.length} ${fileType} hochgeladen!`);
                this.resetForm();
            } else {
                this.showError(result.message || 'Upload fehlgeschlagen!');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showError(`Upload-Fehler: ${error.message}`);
        } finally {
            this.hideProgress();
        }
    }

    showProgress() {
        this.uploadProgress.classList.remove('hidden');
        this.uploadArea.classList.add('pointer-events-none', 'opacity-50');
        
        // Animate progress bar
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            this.progressBar.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 200);
    }

    hideProgress() {
        this.progressBar.style.width = '100%';
        setTimeout(() => {
            this.uploadProgress.classList.add('hidden');
            this.uploadArea.classList.remove('pointer-events-none', 'opacity-50');
            this.progressBar.style.width = '0%';
        }, 500);
    }

    showSuccess(message) {
        this.successOverlay.classList.remove('hidden');
        
        // Animate success overlay with bounce effect
        this.successOverlay.style.transform = 'scale(0)';
        this.successOverlay.style.transition = 'transform 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        setTimeout(() => {
            this.successOverlay.style.transform = 'scale(1)';
        }, 100);
        
        // Create confetti effect
        this.createConfetti();
        
        // Add success sound effect (if supported)
        this.playSuccessSound();
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            this.hideSuccess();
        }, 3000);
    }

    hideSuccess() {
        this.successOverlay.style.transform = 'scale(0)';
        setTimeout(() => {
            this.successOverlay.classList.add('hidden');
            this.successOverlay.style.transform = 'scale(1)';
        }, 300);
    }

    showError(message) {
        // Create temporary error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(errorDiv);
        
        // Animate in
        errorDiv.style.transform = 'translateX(100%)';
        errorDiv.style.transition = 'transform 0.3s ease';
        setTimeout(() => {
            errorDiv.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            errorDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(errorDiv);
            }, 300);
        }, 5000);
    }

    resetForm() {
        this.fileInput.value = '';
        this.cameraInput.value = '';
    }

    createConfetti() {
        const colors = ['#c4000c', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3'];
        const confettiCount = 80;
        
        for (let i = 0; i < confettiCount; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                
                // Random position across the screen
                confetti.style.left = Math.random() * window.innerWidth + 'px';
                confetti.style.top = '-20px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                
                // Random size
                const size = Math.random() * 8 + 4; // 4-12px
                confetti.style.width = size + 'px';
                confetti.style.height = size + 'px';
                
                // Random rotation
                const rotation = Math.random() * 360;
                confetti.style.transform = `rotate(${rotation}deg)`;
                
                // Animation with easing
                confetti.style.transition = 'all 3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                
                document.body.appendChild(confetti);
                
                // Trigger animation
                setTimeout(() => {
                    confetti.style.top = window.innerHeight + 50 + 'px';
                    confetti.style.transform = `rotate(${rotation + 720}deg)`;
                    confetti.style.opacity = '0';
                }, 10);
                
                // Remove confetti after animation
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 3000);
            }, i * 15);
        }
    }

    playSuccessSound() {
        // Create a simple success sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
            oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1); // E5
            oscillator.frequency.setValueAtTime(783.99, audioContext.currentTime + 0.2); // G5
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {
            // Fallback: no sound if Web Audio API is not supported
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new PhotoUploader();
    
    // Update year in footer
    const yearElement = document.getElementById('currentYear');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }
});
