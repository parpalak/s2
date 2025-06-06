<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Helper\StringHelper;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\AuthProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Outputs "comment saved" message (used if the pre-moderation mode is enabled)
 */
readonly class CommentSentController implements ControllerInterface
{
    /**
     * @var CommentStrategyInterface[]
     */
    private array $commentStrategies;

    public function __construct(
        private AuthProvider          $authProvider,
        private UserProvider          $userProvider,
        private TranslatorInterface   $translator,
        private UrlBuilder            $urlBuilder,
        private HtmlTemplateProvider  $templateProvider,
        private CommentMailer         $commentMailer,
        CommentStrategyInterface      ...$strategies
    ) {
        $this->commentStrategies = $strategies;
    }

    /**
     * @throws DbLayerException
     * @throws BadRequestException
     */
    public function handle(Request $request): Response
    {
        $targetPath     = $request->get('go', '');
        $commentHash    = $request->get('sign', '');
        $moderatorEmail = $this->authProvider->getAuthenticatedModeratorEmail($request);
        $authorIp       = (string)$request->getClientIp();

        foreach ($this->commentStrategies as $commentStrategy) {
            $comment = $commentStrategy->getRecentComment($commentHash, $authorIp);
            if ($comment === null) {
                continue;
            }

            if ($moderatorEmail === $comment->email) {
                // We have confirmed that the moderator is the one who has really sent the comment
                return $this->publishAndNotifyAndGetRedirectResponse($commentStrategy, $comment, $targetPath);
            }

            $moderators = $this->userProvider->getModerators([$comment->email]);
            if (\count($moderators) > 0) {
                /**
                 * The comment was sent with a moderator email but the moderator is not logged in.
                 * We assume that this comment has been written by somebody else.
                 * So we have to notify this moderator.
                 */
                $link    = $this->urlBuilder->absLink($targetPath);
                $message = StringHelper::bbcodeToMail($comment->text);
                $target  = $commentStrategy->getTargetById($comment->targetId);
                foreach ($moderators as $moderator) {
                    $this->commentMailer->mailToModerator(
                        $moderator->login,
                        $moderator->email,
                        $message,
                            $target->title ?? 'unknown item',
                        $link,
                        $comment->name,
                        $comment->email,
                        false,
                        'unknown'
                    );
                }
            }

            break;
        }

        $template = $this->templateProvider->getTemplate('service.php');

        $template
            ->putInPlaceholder('head_title', '✅ ' . $this->translator->trans('Comment sent'))
            ->putInPlaceholder('title', '<span class="icon-success">✔</span>' . $this->translator->trans('Comment sent'))
            ->putInPlaceholder('text', \sprintf($this->translator->trans('Comment sent info'), s2_htmlencode($this->urlBuilder->link($targetPath)), $this->urlBuilder->link('/')))
        ;

        return $template->toHttpResponse();
    }

    private function publishAndNotifyAndGetRedirectResponse(
        CommentStrategyInterface $commentStrategy,
        Comment\CommentDto       $comment,
        mixed                    $targetPath
    ): RedirectResponse {
        $commentStrategy->notifySubscribers($comment->id);
        $commentStrategy->publishComment($comment->id);
        $hash = $commentStrategy->getHashForPublishedComment($comment->targetId);

        // Redirect to the last comment
        $redirectLink = $this->urlBuilder->link($targetPath) . ($hash !== null ? '#' . $hash : '');

        return new RedirectResponse($redirectLink);
    }
}
