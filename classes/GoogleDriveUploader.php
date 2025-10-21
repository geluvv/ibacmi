<?php
require_once 'vendor/autoload.php';

class GoogleDriveUploader {
    private $client;
    private $service;
    private $config;

    public function __construct($accessToken) {
        // Load configuration from secure config file
        $configPath = __DIR__ . '/GoogleDriveConfig.php';
        if (!file_exists($configPath)) {
            throw new Exception('Google Drive configuration file not found. Please copy GoogleDriveConfig.example.php to GoogleDriveConfig.php and add your credentials.');
        }
        $this->config = require $configPath;
        
        $this->client = new Google_Client();
        $this->client->setClientId($this->config['client_id']);
        $this->client->setClientSecret($this->config['client_secret']);
        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            throw new Exception('Access token has expired');
        }

        $this->service = new Google_Service_Drive($this->client);
    }

    public function uploadFile($filePath, $fileName) {
        try {
            // Create folder for backup
            $folderMetadata = new Google_Service_Drive_DriveFile([
                'name' => 'IBACMI_Backup_' . date('Y-m-d_H-i-s'),
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);
            
            $folder = $this->service->files->create($folderMetadata, [
                'fields' => 'id'
            ]);

            // Upload file to the folder
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $fileName,
                'parents' => [$folder->getId()]
            ]);

            $content = file_get_contents($filePath);
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/zip',
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink'
            ]);

            return [
                'success' => true,
                'fileId' => $file->getId(),
                'folderId' => $folder->getId(),
                'webViewLink' => $file->getWebViewLink()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}