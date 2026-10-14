<?php

declare(strict_types=1);

namespace Orchestra\Library\Download;

final class ContentDisposition
{
    /**
     * Builds an `attachment` header value that works for non-ASCII file names (RFC 6266).
     */
    public static function attachment(string $fileName): string
    {
        $fileName = \str_replace(["\r", "\n", '/', '\\'], ['', '', '-', '-'], $fileName);

        $fallback = (string) \preg_replace('/[^\x20-\x7E]/', '_', $fileName);
        $fallback = \str_replace(['"', '%'], '_', $fallback);

        return \sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $fallback, \rawurlencode($fileName));
    }
}
