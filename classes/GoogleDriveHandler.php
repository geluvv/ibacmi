<?php
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    throw new Exception('Vendor directory not found. Please run "composer install" first.');
}
require_once $vendorPath;

class GoogleDriveHandler {
    private $client;
    private $service;
    private $backupFolderId;
    private $config;

    public function __construct() {
        $this->config = require dirname(__DIR__) . '/config/google_drive_config.php';
        $this->initializeClient();
    }

    private function initializeClient() {
        try {
            // Load OAuth credentials from secure config file
            $oauthConfigPath = __DIR__ . '/GoogleDriveConfig.php';
            if (!file_exists($oauthConfigPath)) {
                throw new Exception('Google Drive OAuth configuration file not found. Please copy GoogleDriveConfig.example.php to GoogleDriveConfig.php and add your credentials.');
            }
            $oauthConfig = require $oauthConfigPath;
            
            $this->client = new Google_Client();
            $this->client->setApplicationName('IBACMI Backup System');
            $this->client->setClientId($oauthConfig['client_id']);
            $this->client->setClientSecret($oauthConfig['client_secret']);
            $this->client->setScopes(['https://www.googleapis.com/auth/drive.file']);
            $this->client->setAccessType('offline');

            $tokenPath = dirname(__DIR__) . '/config/google_drive_token.json';
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($accessToken);
            }

            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                } else {
                    throw new Exception('No valid access token available. Please authenticate first.');
                }
            }

            $this->service = new Google_Service_Drive($this->client);
            $this->ensureBackupFolder();
        } catch (Exception $e) {
            error_log("Google Drive initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    private function ensureBackupFolder() {
        try {
            $query = "name = '" . $this->config['backup_folder_name'] . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive'
            ]);

            if (empty($results->getFiles())) {
                $folderMetadata = new Google_Service_Drive_DriveFile([
                    'name' => $this->config['backup_folder_name'],
                    'mimeType' => 'application/vnd.google-apps.folder'
                ]);
                
                $folder = $this->service->files->create($folderMetadata, [
                    'fields' => 'id'
                ]);
                
                $this->backupFolderId = $folder->getId();
            } else {
                $this->backupFolderId = $results->getFiles()[0]->getId();
            }
        } catch (Exception $e) {
            error_log('Error ensuring backup folder: ' . $e->getMessage());
            throw new Exception('Failed to initialize backup folder in Google Drive');
        }
    }

    public function uploadBackup($filePath) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('Backup file not found: ' . $filePath);
            }

            if (!$this->backupFolderId) {
                $this->ensureBackupFolder();
            }

            $fileName = basename($filePath);
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $fileName,
                'parents' => [$this->backupFolderId]
            ]);

            $content = file_get_contents($filePath);
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/zip',
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return [
                'status' => 'success',
                'file_id' => $file->getId(),
                'message' => 'Backup uploaded successfully to Google Drive'
            ];
        } catch (Exception $e) {
            error_log('Error uploading backup to Google Drive: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to upload backup to Google Drive: ' . $e->getMessage()
            ];
        }
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function handleAuthCallback($authCode) {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            $this->client->setAccessToken($accessToken);

            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }

            $tokenPath = dirname(__DIR__) . '/config/google_drive_token.json';
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($accessToken));
            
            return true;
        } catch (Exception $e) {
            error_log('Error handling auth callback: ' . $e->getMessage());
            return false;
        }
    }
}