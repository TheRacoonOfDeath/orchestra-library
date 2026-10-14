<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

/**
 * Validates entries of `$_FILES`. The file type is detected from the content, not only the extension.
 */
final class UploadValidator
{
    /**
     * Allowed extensions => MIME types, in the format WordPress uses for `upload_mimes`.
     * Extend with the `orchestra_library_allowed_file_types` filter.
     */
    public const DEFAULT_FILE_TYPES = [
        'pdf' => 'application/pdf',
        'jpg|jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * @param \Closure(string $path): bool $isUploadedFile Defaults to PHP's is_uploaded_file()
     */
    public function __construct(private readonly ?\Closure $isUploadedFile = null)
    {
    }

    /**
     * Extracts one upload from `$_FILES`, including nested fields like `name="files[12]"`.
     *
     * @param array<mixed> $files `$_FILES`
     * @return array<string, mixed>|null The upload in the shape of a single `$_FILES` entry, or null
     *         if the field does not exist or no file was chosen
     */
    public static function extract(array $files, string $field, int|string|null $key = null): ?array
    {
        $entry = $files[$field] ?? null;
        if (!\is_array($entry)) {
            return null;
        }

        $upload = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $property) {
            $value = $entry[$property] ?? null;
            if ($key !== null) {
                $value = \is_array($value) ? ($value[$key] ?? null) : null;
            }
            $upload[$property] = $value;
        }

        return $upload['error'] === UPLOAD_ERR_NO_FILE || $upload['name'] === null || $upload['name'] === ''
            ? null
            : $upload;
    }

    /**
     * Lists the keys of a nested upload field, e.g. the part IDs of `name="files[12]"`.
     *
     * @param array<mixed> $files `$_FILES`
     * @return list<int|string>
     */
    public static function keys(array $files, string $field): array
    {
        $names = \is_array($files[$field] ?? null) ? ($files[$field]['name'] ?? null) : null;

        return \is_array($names) ? \array_keys($names) : [];
    }

    /**
     * @param array<string, mixed> $upload A single `$_FILES` entry
     * @throws UploadRejected
     */
    public function validate(array $upload): UploadedFile
    {
        $name = \is_string($upload['name'] ?? null) ? $upload['name'] : '';
        $temporaryPath = \is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
        $error = \is_int($upload['error'] ?? null) ? $upload['error'] : UPLOAD_ERR_NO_FILE;
        $size = \is_int($upload['size'] ?? null) ? $upload['size'] : 0;

        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadRejected(self::errorMessage($name, $error));
        }
        $isUploadedFile = $this->isUploadedFile ?? \is_uploaded_file(...);
        if ($temporaryPath === '' || !$isUploadedFile($temporaryPath)) {
            $message = \__('"%s" was not uploaded correctly.', 'orchestra-library');
            throw new UploadRejected(self::message($message, $name));
        }
        if ($size <= 0) {
            throw new UploadRejected(self::message(\__('"%s" is empty.', 'orchestra-library'), $name));
        }

        $check = \wp_check_filetype_and_ext($temporaryPath, $name, self::allowedFileTypes());
        if (!\is_string($check['type']) || $check['type'] === '') {
            /* translators: 1: file name, 2: list of allowed file extensions */
            $message = \__('"%1$s" has a file type that is not allowed. Allowed types: %2$s.', 'orchestra-library');
            throw new UploadRejected(\sprintf($message, $name, self::allowedExtensionsLabel()));
        }

        $properName = \is_string($check['proper_filename']) && $check['proper_filename'] !== ''
            ? $check['proper_filename']
            : $name;

        return new UploadedFile($temporaryPath, \sanitize_file_name($properName), $check['type'], $size);
    }

    /**
     * @return array<string, string>
     */
    public static function allowedFileTypes(): array
    {
        $types = \apply_filters('orchestra_library_allowed_file_types', self::DEFAULT_FILE_TYPES);
        if (!\is_array($types)) {
            return self::DEFAULT_FILE_TYPES;
        }

        $allowed = [];
        foreach ($types as $extensions => $mimeType) {
            if (\is_string($extensions) && \is_string($mimeType)) {
                $allowed[$extensions] = $mimeType;
            }
        }

        return $allowed;
    }

    /**
     * The value for the `accept` attribute of file inputs.
     */
    public static function acceptAttribute(): string
    {
        $extensions = \explode('|', \implode('|', \array_keys(self::allowedFileTypes())));

        return \implode(',', \array_map(static fn (string $extension): string => '.' . $extension, $extensions));
    }

    private static function allowedExtensionsLabel(): string
    {
        return \str_replace('|', ', ', \implode(', ', \array_keys(self::allowedFileTypes())));
    }

    private static function errorMessage(string $name, int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::message(
                \__('"%s" is larger than the maximum upload size.', 'orchestra-library'),
                $name
            ),
            UPLOAD_ERR_PARTIAL => self::message(\__('"%s" was only partially uploaded.', 'orchestra-library'), $name),
            default => self::message(\__('"%s" could not be uploaded.', 'orchestra-library'), $name),
        };
    }

    private static function message(string $format, string $name): string
    {
        return \sprintf($format, $name);
    }
}
