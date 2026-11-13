<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Image uploads. The type comes from the file contents (finfo), never from the
 * client's file name, and every stored file gets a random name.
 */
final class Upload
{
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const DIRS = ['posts', 'avatars', 'covers'];

    /**
     * @return array{path: string, width: int, height: int}|null null when no file was chosen
     * @throws ValidationError
     */
    public static function image(?array $file, string $dir): ?array
    {
        if (!in_array($dir, self::DIRS, true)) {
            throw new \InvalidArgumentException("Unknown upload folder: $dir");
        }
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE || !is_int($error)) {
            return null;
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new ValidationError('Images must be 5 MB or smaller.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationError('The upload did not finish. Try again.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            throw new ValidationError('Use a JPEG, PNG, WebP or GIF image.');
        }
        $size = @getimagesize($file['tmp_name']);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > 12000 || $size[1] > 12000) {
            throw new ValidationError('That file is not an image we can read.');
        }

        $folder = ROOT . '/public/uploads/' . $dir;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new \RuntimeException("Cannot create $folder");
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$folder/$name")) {
            throw new ValidationError('The image could not be saved. Try again.');
        }
        return ['path' => "$dir/$name", 'width' => $size[0], 'height' => $size[1]];
    }

    /** Deletes a stored upload. Paths that did not come from image() are ignored. */
    public static function delete(?string $path): void
    {
        if ($path !== null && preg_match('~^(posts|avatars|covers)/[a-f0-9]{32}\.(jpg|png|webp|gif)$~', $path)) {
            $file = ROOT . '/public/uploads/' . $path;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
