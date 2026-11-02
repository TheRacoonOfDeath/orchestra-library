<?php

declare(strict_types=1);

namespace Orchestra\Library\Admin;

use Orchestra\Core\Ui\Html;
use Orchestra\Library\Capabilities;
use Orchestra\Library\Frontend\InstrumentsView;
use Orchestra\Library\Piece\PiecePostType;

/**
 * Library → Instruments in wp-admin, with the same form as the library page.
 */
final class InstrumentsPage
{
    private const SLUG = 'orchestra-library-instruments';

    public function __construct(private readonly InstrumentsView $view)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addPage']);
    }

    /**
     * @internal Hooked to `admin_menu`.
     */
    public function addPage(): void
    {
        \add_submenu_page(
            'edit.php?post_type=' . PiecePostType::NAME,
            \__('Instruments', 'orchestra-library'),
            \__('Instruments', 'orchestra-library'),
            Capabilities::PIECE_EDIT,
            self::SLUG,
            [$this, 'render']
        );
    }

    /**
     * @internal Submenu page callback.
     */
    public function render(): void
    {
        echo Html::adminPage(\__('Instruments', 'orchestra-library'), $this->view->form()); // escaped by the view
    }
}
