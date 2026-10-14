<?php

/**
 * Minimal fakes of the WordPress functions used by the unit-tested classes.
 */

declare(strict_types=1);

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
{
    return $value;
}

function sanitize_file_name(string $fileName): string
{
    return (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName);
}

function wp_generate_uuid4(): string
{
    $bytes = bin2hex(random_bytes(16));

    return sprintf(
        '%s-%s-4%s-a%s-%s',
        substr($bytes, 0, 8),
        substr($bytes, 8, 4),
        substr($bytes, 13, 3),
        substr($bytes, 17, 3),
        substr($bytes, 20, 12)
    );
}

function wp_mkdir_p(string $target): bool
{
    return is_dir($target) || mkdir($target, 0777, true);
}

/**
 * Like WordPress: the extension must be allowed and match the detected content type.
 *
 * @param array<string, string> $mimes
 * @return array{ext: string|false, type: string|false, proper_filename: string|false}
 */
function wp_check_filetype_and_ext(string $file, string $filename, ?array $mimes = null): array
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    foreach ($mimes ?? [] as $extensions => $mimeType) {
        if (in_array($extension, explode('|', $extensions), true)) {
            $detected = (new finfo(FILEINFO_MIME_TYPE))->file($file);

            return $detected === $mimeType
                ? ['ext' => $extension, 'type' => $mimeType, 'proper_filename' => false]
                : ['ext' => false, 'type' => false, 'proper_filename' => false];
        }
    }

    return ['ext' => false, 'type' => false, 'proper_filename' => false];
}
