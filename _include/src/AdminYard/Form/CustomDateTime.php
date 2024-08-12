<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard\Form;

use S2\AdminYard\Form\Datetime;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomDateTime extends Datetime
{
    public function __construct(string $fieldName, private TranslatorInterface $translator)
    {
        parent::__construct($fieldName);
    }

    public function getHtml(?string $id = null): string
    {
        $id = $id ?? uniqid('datetime-', true);

        /**
         * Hack to set the current server time in JS. We pass the current time
         * in the server's timezone to $serverTime with a formally assigned UTC timezone.
         *
         * This way, timeDifference will contain the client's time offset in seconds relative to UTC,
         * adjusted for the client's clock inaccuracy.
         *
         * Additionally, on the client side, toISOString() returns the date and time converted in the UTC timezone
         * regardless of the client's timezone. Because of this, the time offset relative to UTC disappears,
         * and the client's clock inaccuracy cancels out.
         */
        $serverTime = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

        $trans = $this->translator->trans('Now');

        $script = <<<HTML
    <a
        href="#"
        class="now-control"
        data-diff=""
        id="$id-now-control"
        onclick="document.getElementById('$id').value = new Date(new Date().getTime() + parseInt(this.getAttribute('data-diff'))).toISOString().substring(0, 16); return false;">$trans</a>
    <script>
        (function () {
            const serverTime = new Date('$serverTime');
            const clientTime = new Date();
            const timeDifference = serverTime - clientTime; // Difference in milliseconds
            document.getElementById('$id-now-control').dataset.diff = timeDifference;
        })();
    </script>
    HTML;

        return parent::getHtml($id) . $script;
    }
}
