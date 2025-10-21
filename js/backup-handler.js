class BackupHandler {
    constructor() {
        this.CLIENT_ID = '555053785574-0g8ndvnnaq1cdevj3e3b4gonk92gavkn.apps.googleusercontent.com';
        this.API_KEY = 'AIzaSyBNsVYLL5SxrBsfdC574bU0dRIOLL3Tbpo';
        this.SCOPES = 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive.appdata https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email';
        
        this.tokenClient = null;
        this.selectedAccount = null;
        
        this.initializeElements();
        this.initializeEventListeners();
    }

    initializeElements() {
        this.progressDiv = document.getElementById('backupProgress');
        this.progressBar = this.progressDiv?.querySelector('.progress-bar');
        this.progressText = document.getElementById('progressText');
        this.backupForm = document.getElementById('backupForm');
        this.submitButton = this.backupForm?.querySelector('button[type="submit"]');
        this.googleAuthBtn = document.getElementById('googleAuthBtn');
        this.googleAuthContainer = document.getElementById('googleAuthContainer');
    }

    initializeEventListeners() {
        // Google Auth button handler
        this.googleAuthBtn?.addEventListener('click', () => this.handleGoogleAuth());

        // Storage option change handler
        document.querySelectorAll('input[name="storageOption"]').forEach(radio => {
            radio.addEventListener('change', (e) => this.handleStorageOptionChange(e));
        });

        // Form submit handler
        this.backupForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const storageType = document.querySelector('input[name="storageOption"]:checked').value;
            await this.startBackup(storageType);
        });
    }

    async refreshAccessToken() {
        return new Promise((resolve, reject) => {
            if (!this.tokenClient) {
                reject(new Error('Token client not initialized'));
                return;
            }

            this.tokenClient.requestAccessToken({
                prompt: '',
                callback: (tokenResponse) => {
                    if (tokenResponse.error) {
                        reject(new Error(tokenResponse.error));
                        return;
                    }
                    
                    const tokenData = {
                        access_token: tokenResponse.access_token,
                        expires_at: Date.now() + (3600 * 1000),
                        timestamp: Date.now()
                    };
                    
                    sessionStorage.setItem('google_token', JSON.stringify(tokenData));
                    this.updateGoogleAuthButton(true);
                    resolve(tokenResponse.access_token);
                }
            });
        });
    }

    async getValidToken() {
        try {
            const tokenData = JSON.parse(sessionStorage.getItem('google_token') || '{}');
            
            if (!tokenData.access_token || Date.now() >= (tokenData.expires_at - 300000)) {
                console.log('Token expired or expiring soon, refreshing...');
                return await this.refreshAccessToken();
            }
            
            return tokenData.access_token;
        } catch (error) {
            console.error('Error getting valid token:', error);
            throw error;
        }
    }

    async initializeGoogleAPI() {
        try {
            await new Promise((resolve, reject) => {
                gapi.load('client', {
                    callback: resolve,
                    onerror: reject
                });
            });

            await gapi.client.init({
                apiKey: this.API_KEY,
                discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/drive/v3/rest']
            });

            // Initialize token client
            this.tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: this.CLIENT_ID,
                scope: this.SCOPES,
                callback: (tokenResponse) => this.handleTokenResponse(tokenResponse)
            });

            // Check existing token
            const savedToken = sessionStorage.getItem('google_token');
            if (savedToken) {
                try {
                    await this.refreshAccessToken();
                    this.updateGoogleAuthButton(true);
                } catch (error) {
                    console.error('Token refresh failed:', error);
                    sessionStorage.removeItem('google_token');
                    this.updateGoogleAuthButton(false);
                }
            }
        } catch (error) {
            console.error('Error initializing Google API:', error);
            throw error;
        }
    }

    handleTokenResponse(tokenResponse) {
        if (tokenResponse.error !== undefined) {
            console.error('Token error:', tokenResponse.error);
            return;
        }
        
        const tokenData = {
            access_token: tokenResponse.access_token,
            expires_at: Date.now() + (3600 * 1000)
        };
        
        sessionStorage.setItem('google_token', JSON.stringify(tokenData));
        this.updateGoogleAuthButton(true);
    }

    updateGoogleAuthButton(isAuthenticated) {
        if (isAuthenticated) {
            this.googleAuthBtn.innerHTML = '<i class="fas fa-check me-2"></i>Connected to Google Drive';
            this.googleAuthBtn.classList.remove('btn-outline-primary');
            this.googleAuthBtn.classList.add('btn-success');
            this.submitButton.disabled = false;
        } else {
            this.googleAuthBtn.innerHTML = '<i class="fab fa-google me-2"></i>Connect Google Drive';
            this.googleAuthBtn.classList.add('btn-outline-primary');
            this.googleAuthBtn.classList.remove('btn-success');
            this.submitButton.disabled = true;
        }
    }

    handleGoogleAuth() {
        if (!this.tokenClient) {
            console.error('Token client not initialized');
            return;
        }

        this.tokenClient.requestAccessToken({ prompt: 'consent' });
    }

    handleStorageOptionChange(e) {
        if (e.target.value === 'cloud') {
            this.googleAuthContainer.classList.remove('d-none');
            const savedToken = sessionStorage.getItem('google_token');
            this.submitButton.disabled = !savedToken;
            if (savedToken) this.updateGoogleAuthButton(true);
        } else {
            this.googleAuthContainer.classList.add('d-none');
            this.submitButton.disabled = false;
        }
    }

    async startBackup(storageType) {
        try {
            this.submitButton.disabled = true;
            this.progressDiv.classList.remove('d-none');
            this.progressBar.classList.remove('bg-danger');
            
            if (storageType === 'cloud') {
                await this.handleCloudBackup();
            } else {
                await this.handleLocalBackup();
            }
        } catch (error) {
            console.error('Backup error:', error);
            this.progressBar.classList.add('bg-danger');
            this.progressBar.style.width = '100%';
            this.progressText.textContent = 'Error: ' + error.message;
        } finally {
            setTimeout(() => {
                this.submitButton.disabled = false;
            }, 2000);
        }
    }

    async handleCloudBackup() {
        this.progressBar.style.width = '20%';
        this.progressText.textContent = 'Preparing Google Drive backup...';

        const tokenData = JSON.parse(sessionStorage.getItem('google_token') || '{}');
        if (!tokenData.access_token) {
            throw new Error('Please authenticate with Google Drive first');
        }

        this.progressBar.style.width = '40%';
        const formData = new FormData();
        formData.append('storageOption', 'cloud');
        formData.append('accessToken', tokenData.access_token);

        const response = await fetch('process_backup.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Backup failed: Network error');
        }

        const result = await response.json();
        if (result.status === 'success') {
            this.progressBar.style.width = '100%';
            this.progressText.textContent = 'Backup completed successfully!';
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(result.message || 'Backup failed');
        }
    }

    async handleLocalBackup() {
        this.progressBar.style.width = '20%';
        this.progressText.textContent = 'Preparing local backup...';

        const formData = new FormData();
        formData.append('storageOption', 'local');
        formData.append('created_at', new Date().toISOString().slice(0, 19).replace('T', ' '));
        formData.append('created_by', 'ibacmi2025');
        formData.append('storage_type', 'local');
        formData.append('status', 'pending');

        this.progressBar.style.width = '40%';
        this.progressText.textContent = 'Creating backup...';

        const response = await fetch('process_backup.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Backup failed: Network error');
        }

        const result = await response.json();
        if (result.status === 'success') {
            this.progressBar.style.width = '100%';
            this.progressText.textContent = 'Backup completed successfully!';
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(result.message || 'Backup failed');
        }
    }
}

// Initialize when DOM is loaded
let backupHandler;
document.addEventListener('DOMContentLoaded', async () => {
    backupHandler = new BackupHandler();
    await backupHandler.initializeGoogleAPI();
});