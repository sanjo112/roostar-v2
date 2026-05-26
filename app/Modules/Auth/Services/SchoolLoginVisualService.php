<?php

declare(strict_types=1);

namespace Roostar\Modules\Auth\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SchoolLoginVisualService
{
    public const COOKIE_NAME = 'school_login_visual';

    private const UPLOAD_DIR = '/uploads/login-visuals';
    private const LOGO_UPLOAD_DIR = '/uploads/school-logos';
    private const MAX_BYTES = 5242880;

    public function __construct(
        private readonly PDO $db,
        private readonly string $appKey,
    ) {
    }

    public function currentPathForSchool(string $schoolId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT login_visual_path
            FROM school_settings
            WHERE school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute(['school_id' => $schoolId]);
        $path = $stmt->fetchColumn();

        if (!is_string($path) || $path === '') {
            return null;
        }

        return $this->publicFileExists($path) ? $path : null;
    }

    public function storeUpload(string $schoolId, array $file): string
    {
        [$tmpName, $extension] = $this->validatedImageUpload($file);
        $this->ensurePublicDirectory(self::UPLOAD_DIR);
        $path = self::UPLOAD_DIR . '/school-' . $schoolId . '-login.' . $extension;
        $oldPath = $this->currentPathForSchool($schoolId);

        if (!move_uploaded_file($tmpName, $this->publicPath($path))) {
            throw new RuntimeException('De afbeelding kon niet worden opgeslagen.');
        }

        if ($oldPath !== null && $oldPath !== $path) {
            $this->deletePublicFile($oldPath);
        }

        $this->upsertPath($schoolId, $path);
        $this->setCookieForSchool($schoolId);

        return $path;
    }

    public function currentLogoPathForSchool(string $schoolId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT school_logo_path
            FROM school_settings
            WHERE school_id = :school_id
            LIMIT 1
        ");
        $stmt->execute(['school_id' => $schoolId]);
        $path = $stmt->fetchColumn();

        if (!is_string($path) || $path === '') {
            return null;
        }

        return $this->publicFileExists($path) ? $path : null;
    }

    public function storeLogoUpload(string $schoolId, array $file): string
    {
        [$tmpName, $extension] = $this->validatedImageUpload($file);
        $this->ensurePublicDirectory(self::LOGO_UPLOAD_DIR);
        $path = self::LOGO_UPLOAD_DIR . '/school-' . $schoolId . '-logo.' . $extension;
        $oldPath = $this->currentLogoPathForSchool($schoolId);

        if (!move_uploaded_file($tmpName, $this->publicPath($path))) {
            throw new RuntimeException('Het logo kon niet worden opgeslagen.');
        }

        if ($oldPath !== null && $oldPath !== $path) {
            $this->deletePublicFile($oldPath);
        }

        $stmt = $this->db->prepare("
            INSERT INTO school_settings (school_id, school_logo_path, school_logo_updated_at, created_at, updated_at)
            VALUES (:school_id, :school_logo_path, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                school_logo_path = VALUES(school_logo_path),
                school_logo_updated_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'school_logo_path' => $path,
        ]);

        return $path;
    }

    public function resetLogoForSchool(string $schoolId): void
    {
        $path = $this->currentLogoPathForSchool($schoolId);

        if ($path !== null) {
            $this->deletePublicFile($path);
        }

        $stmt = $this->db->prepare("
            INSERT INTO school_settings (school_id, school_logo_path, school_logo_updated_at, created_at, updated_at)
            VALUES (:school_id, NULL, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                school_logo_path = NULL,
                school_logo_updated_at = NULL,
                updated_at = NOW()
        ");
        $stmt->execute(['school_id' => $schoolId]);
    }

    public function resetForSchool(string $schoolId): void
    {
        $path = $this->currentPathForSchool($schoolId);

        if ($path !== null) {
            $this->deletePublicFile($path);
        }

        $stmt = $this->db->prepare("
            INSERT INTO school_settings (school_id, login_visual_path, login_visual_updated_at, created_at, updated_at)
            VALUES (:school_id, NULL, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                login_visual_path = NULL,
                login_visual_updated_at = NULL,
                updated_at = NOW()
        ");
        $stmt->execute(['school_id' => $schoolId]);

        $this->clearCookie();
    }

    public function setCookieForSchool(?string $schoolId): void
    {
        if ($schoolId === null || $schoolId === '') {
            $this->clearCookie();
            return;
        }

        $path = $this->currentPathForSchool($schoolId);
        if ($path === null) {
            $this->clearCookie();
            return;
        }

        $payload = [
            'school_id' => $schoolId,
            'path' => $path,
        ];
        $payload['signature'] = $this->signature($schoolId, $path);

        setcookie(self::COOKIE_NAME, $this->encode($payload), [
            'expires' => time() + 60 * 60 * 24 * 90,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function pathFromCookie(?string $cookieValue): ?string
    {
        if ($cookieValue === null || $cookieValue === '') {
            return null;
        }

        $payload = $this->decode($cookieValue);
        if (!is_array($payload)) {
            return null;
        }

        $schoolId = (string) ($payload['school_id'] ?? '');
        $path = (string) ($payload['path'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');

        if ($schoolId === '' || $path === '' || $signature === '') {
            return null;
        }

        if (!hash_equals($this->signature($schoolId, $path), $signature)) {
            return null;
        }

        if (!str_starts_with($path, self::UPLOAD_DIR . '/') || !$this->publicFileExists($path)) {
            return null;
        }

        return $path;
    }

    private function upsertPath(string $schoolId, string $path): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO school_settings (school_id, login_visual_path, login_visual_updated_at, created_at, updated_at)
            VALUES (:school_id, :login_visual_path, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                login_visual_path = VALUES(login_visual_path),
                login_visual_updated_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([
            'school_id' => $schoolId,
            'login_visual_path' => $path,
        ]);
    }

    private function validatedImageUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload een geldige afbeelding.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Upload een geldige afbeelding.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Gebruik een afbeelding van maximaal 5 MB.');
        }

        $mime = $this->mimeType($tmpName);
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime]) || @getimagesize($tmpName) === false) {
            throw new InvalidArgumentException('Gebruik een PNG, JPG of WebP afbeelding.');
        }

        return [$tmpName, $extensions[$mime]];
    }

    private function ensurePublicDirectory(string $path): void
    {
        $directory = $this->publicPath($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('De uploadmap kon niet worden aangemaakt.');
        }
    }

    private function mimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = finfo_file($finfo, $path);

        return is_string($mime) ? $mime : '';
    }

    private function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decode(string $value): ?array
    {
        $base64 = strtr($value, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $json = base64_decode($base64, true);
        if (!is_string($json)) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    private function signature(string $schoolId, string $path): string
    {
        return hash_hmac('sha256', $schoolId . '|' . $path, $this->appKey);
    }

    private function publicFileExists(string $path): bool
    {
        return is_file($this->publicPath($path));
    }

    private function deletePublicFile(string $path): void
    {
        $fullPath = $this->publicPath($path);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function publicPath(string $path): string
    {
        return dirname(__DIR__, 4) . '/public' . $path;
    }

    private function isSecureRequest(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
