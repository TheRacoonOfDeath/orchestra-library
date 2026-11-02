<?php

declare(strict_types=1);

namespace Orchestra\Library\Frontend;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Library\Capabilities;
use Orchestra\Library\Part\Instruments;
use Orchestra\Library\Part\InstrumentsInput;
use Orchestra\Library\Part\PartRepository;
use RuntimeException;

/**
 * Saves the instruments (admin-post.php?action=orchestra_library_save_instruments) and renames the
 * voices of renamed instruments.
 */
final class InstrumentsController
{
    public const ACTION = 'orchestra_library_save_instruments';

    public function __construct(private readonly Instruments $instruments, private readonly PartRepository $parts)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @internal Hooked to `admin_post_orchestra_library_save_instruments`.
     */
    public function handle(): never
    {
        \check_admin_referer(self::ACTION);
        if (!\current_user_can(Capabilities::PIECE_EDIT)) {
            \wp_die(\esc_html__('You are not allowed to change the instruments.', 'orchestra-library'), 403);
        }

        /** @var array<mixed> $post */
        $post = \wp_unslash($_POST);
        $input = InstrumentsInput::fromForm($post, $this->instruments->all(), $this->parts->countByInstrument());
        if ($input->errors !== []) {
            Notices::errors($input->errors);
            Redirect::back();
        }

        $this->instruments->save($input->instruments);
        $errors = [];
        foreach ($input->renamed as $instrument) {
            foreach ($this->parts->forInstrument($instrument->id) as $part) {
                $voice = $part->voice();
                try {
                    $this->parts->update($part->withVoice($voice, $voice->label($instrument), $part->position));
                } catch (RuntimeException) {
                    /* translators: %s: voice */
                    $message = \__(
                        'The voice "%s" could not be renamed, because the piece has a voice with the new name.',
                        'orchestra-library'
                    );
                    $errors[] = \sprintf($message, $part->name);
                }
            }
        }

        Notices::errors($errors);
        if ($errors === []) {
            Notices::success(\__('The instruments have been saved.', 'orchestra-library'));
        }
        Redirect::back();
    }
}
