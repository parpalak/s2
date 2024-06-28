<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

use S2\AdminYard\TemplateRenderer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomTemplateRenderer extends TemplateRenderer
{
    private ?array $extraAssets = null;

    public function __construct(
        TranslatorInterface                       $translator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string                   $basePath
    ) {
        parent::__construct($translator);
    }

    private const FILE_SIZE_UNITS = ['B', 'КB', 'MB', 'GB', 'ТB', 'PB', 'EB', 'ZB', 'YB'];

    public function render(string $_template_path, array $data): string
    {
        $trans            = $this->translator->trans(...);
        $friendlyFilesize = $this->friendlyFilesize(...);
        $numberFormat     = $this->numberFormat(...);
        $basePath         = $this->basePath;
        [$extraStyles, $extraScripts] = $this->getExtraAssets();

        extract($data);
        ob_start();
        require $_template_path;
        return ob_get_clean();
    }

    private function friendlyFilesize(int $size): string
    {
        $unitIndex = 0;
        $unitsNum  = \count(self::FILE_SIZE_UNITS);
        while (($size / 1024) > 1 && $unitIndex < $unitsNum - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return $this->translator->trans('Filesize format', [
            '{{ number }}' => $this->numberFormat($size),
            '{{ unit }}'   => $this->translator->trans('Filesize ' . self::FILE_SIZE_UNITS[$unitIndex]),
        ]);
    }

    private function numberFormat(int|float $number, bool $keepTrailingZero = false, ?int $decimalCount = null): string
    {
        $result = number_format(
            $number,
            $decimalCount ?? (int)$this->translator->trans('Decimal count'),
            $this->translator->trans('Decimal point'),
            $this->translator->trans('Thousands separator')
        );

        if (!$keepTrailingZero) {
            $result = preg_replace('#' . preg_quote($this->translator->trans('Decimal point'), '#') . '?0*$#', '', $result);
        }

        return $result;
    }

    private function getExtraAssets(): array
    {
        if ($this->extraAssets !== null) {
            return $this->extraAssets;
        }
        $event = new CustomTemplateRendererEvent($this->basePath);
        $this->eventDispatcher->dispatch($event);

        return $this->extraAssets = [$event->extraStyles, $event->extraScripts];
    }
}
