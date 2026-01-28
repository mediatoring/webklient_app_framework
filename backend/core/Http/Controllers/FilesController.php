<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Storage\LocalStorage;
use WebklientApp\Core\ConfigLoader;

class FilesController extends BaseController
{
    public function upload(Request $request): JsonResponse
    {
        if (empty($_FILES['file'])) {
            throw new ValidationException('No file uploaded.');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException('Upload error: ' . $file['error']);
        }

        $config = ConfigLoader::getInstance();
        $maxSize = (int) $config->env('MAX_UPLOAD_SIZE', 10485760);
        if ($file['size'] > $maxSize) {
            throw new ValidationException("File too large. Maximum size: " . ($maxSize / 1024 / 1024) . "MB");
        }

        // Validate MIME type from actual file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = $config->get('storage.allowed_types', []);
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            throw new ValidationException("File type not allowed: {$mimeType}");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadPath = date('Y/m');

        $storage = new LocalStorage(dirname(__DIR__, 3) . '/storage/uploads');
        $storage->store($file['tmp_name'], "{$uploadPath}/{$storedName}");

        $userId = $request->getAttribute('user_id');
        $isPublic = (int) ($request->input()['is_public'] ?? 0);

        $id = $this->query->table('files')->insert([
            'user_id' => $userId,
            'original_filename' => $file['name'],
            'stored_filename' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => $file['size'],
            'upload_path' => $uploadPath,
            'is_public' => $isPublic,
        ]);

        $record = $this->query->table('files')->where('id', (int) $id)->first();

        return JsonResponse::created($record, "/api/files/{$id}");
    }

    public function download(Request $request): void
    {
        $id = (int) $request->param('id');
        $file = $this->query->table('files')->where('id', $id)->first();

        if (!$file) {
            throw new NotFoundException('File not found.');
        }

        // Check permission: owner or public
        $userId = $request->getAttribute('user_id');
        if (!$file['is_public'] && (int) $file['user_id'] !== $userId) {
            throw new \WebklientApp\Core\Exceptions\AuthorizationException('Access denied to this file.');
        }

        $storage = new LocalStorage(dirname(__DIR__, 3) . '/storage/uploads');
        $fullPath = $storage->getFullPath("{$file['upload_path']}/{$file['stored_filename']}");

        if (!file_exists($fullPath)) {
            throw new NotFoundException('File not found on disk.');
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
        header('Content-Length: ' . $file['size_bytes']);
        readfile($fullPath);
        exit;
    }
}
