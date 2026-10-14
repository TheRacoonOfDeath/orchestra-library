<?php

declare(strict_types=1);

namespace Orchestra\Library\Admin;

/**
 * Error messages that survive the redirect after saving a form.
 */
final class Notices
{
    private const TRANSIENT_PREFIX = 'orchestra_library_notices_';

    public function register(): void
    {
        \add_action('admin_notices', [$this, 'render']);
    }

    public function error(string $message): void
    {
        $messages = $this->stored();
        $messages[] = $message;
        \set_transient($this->transientName(), \array_values(\array_unique($messages)), 5 * MINUTE_IN_SECONDS);
    }

    /**
     * @param list<string> $messages
     */
    public function errors(array $messages): void
    {
        foreach ($messages as $message) {
            $this->error($message);
        }
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function render(): void
    {
        $messages = $this->stored();
        if ($messages === []) {
            return;
        }
        \delete_transient($this->transientName());

        echo '<div class="notice notice-error is-dismissible">';
        foreach ($messages as $message) {
            echo '<p>' . \esc_html($message) . '</p>';
        }
        echo '</div>';
    }

    /**
     * @return list<string>
     */
    private function stored(): array
    {
        $stored = \get_transient($this->transientName());

        return \is_array($stored) ? \array_values(\array_filter($stored, \is_string(...))) : [];
    }

    private function transientName(): string
    {
        return self::TRANSIENT_PREFIX . \get_current_user_id();
    }
}
