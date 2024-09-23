<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\Comment\CommentStrategyInterface;
use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CommentUnsubscribeController implements ControllerInterface
{
    /**
     * @var CommentStrategyInterface[]
     */
    private array $commentStrategies;

    public function __construct(
        private TranslatorInterface $translator,
        private HtmlTemplateProvider $templateProvider,
        CommentStrategyInterface    ...$commentStrategies
    ) {
        $this->commentStrategies = $commentStrategies;
    }

    public function handle(Request $request): Response
    {
        $id   = $request->query->get('id');
        $mail = $request->query->get('mail');
        $code = $request->query->get('code');

        $template = $this->templateProvider->getTemplate('service.php');

        if (is_numeric($id) && \is_string($mail) && \is_string($code)) {
            foreach ($this->commentStrategies as $commentStrategy) {
                if ($commentStrategy->unsubscribe((int)$id, $mail, $code)) {
                    $template
                        ->putInPlaceholder('head_title', $this->translator->trans('Unsubscribed OK'))
                        ->putInPlaceholder('title', $this->translator->trans('Unsubscribed OK'))
                        ->putInPlaceholder('text', $this->translator->trans('Unsubscribed OK info'))
                    ;

                    return $template->toHttpResponse();
                }
            }
        }

        $template
            ->putInPlaceholder('head_title', $this->translator->trans('Unsubscribed failed'))
            ->putInPlaceholder('title', $this->translator->trans('Unsubscribed failed'))
            ->putInPlaceholder('text', $this->translator->trans('Unsubscribed failed info'))
        ;

        return $template->toHttpResponse();
    }
}
