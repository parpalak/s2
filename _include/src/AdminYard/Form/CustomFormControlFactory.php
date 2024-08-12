<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard\Form;

use S2\AdminYard\Form\FormControlFactory;
use S2\AdminYard\Form\FormControlInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomFormControlFactory extends FormControlFactory
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function create(string $control, string $fieldName): FormControlInterface
    {
        if ($control === 'datetime') {
            return new CustomDateTime($fieldName, $this->translator);
        }
        if ($control === 'html_textarea') {
            return new HtmlTextarea($fieldName);
        }

        return parent::create($control, $fieldName);
    }
}
