<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

use RuntimeException;

/**
 * An upload failed validation. The message is translated and can be shown to the user.
 */
final class UploadRejected extends RuntimeException
{
}
