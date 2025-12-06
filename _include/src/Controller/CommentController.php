<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use Psr\Log\LoggerInterface;
use S2\Cms\Comment\SpamDetectorComment;
use S2\Cms\Comment\SpamDecision;
use S2\Cms\Comment\SpamDecisionProviderInterface;
use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Helper\StringHelper;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\AuthProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CommentController implements ControllerInterface
{
    public function __construct(
        private AuthProvider                  $authProvider,
        private UserProvider                  $userProvider,
        private CommentStrategyInterface      $commentStrategy,
        private TranslatorInterface           $translator,
        private UrlBuilder                    $urlBuilder,
        private HtmlTemplateProvider          $templateProvider,
        private Viewer                        $viewer,
        private LoggerInterface               $logger,
        private CommentMailer                 $commentMailer,
        private SpamDecisionProviderInterface $spamDecisionProvider,
        private bool                          $commentsEnabled,
        private bool                          $premoderationEnabled,
    ) {
    }

    private const S2_MAX_COMMENT_BYTES = 65535;

    public static function commentHash(int $commentId, int $targetId, string $email, string $ip, string $strategyClass): string
    {
        return md5(serialize([$commentId, $targetId, $email, $ip, $strategyClass]));
    }

    /**
     * @throws DbLayerException
     * @throws BadRequestException
     */
    public function handle(Request $request): Response
    {
        $showEmail  = $request->request->get('show_email', false) !== false;
        $subscribed = $request->request->get('subscribed', false) !== false;
        $id         = $request->request->getString('id', '');
        if (!preg_match('#^[0-9a-f]{32}$#', $id)) {
            $id = '';
        }
        $path = $request->getPathInfo();

        /**
         * Input validation
         */
        $errors = [];

        if (!$this->commentsEnabled) {
            $errors[] = $this->translator->trans('disabled');
        }

        $text = $request->request->get('text', '');
        $text = trim($text);
        if ($text === '') {
            $errors[] = $this->translator->trans('missing_text');
        }
        if (\strlen($text) > self::S2_MAX_COMMENT_BYTES) {
            $errors[] = \sprintf($this->translator->trans('long_text'), self::S2_MAX_COMMENT_BYTES);
        }

        $email = $request->request->get('email', '');
        $email = trim($email);
        if (!StringHelper::isValidEmail($email)) {
            $errors[] = $this->translator->trans('email');
        }

        $name = $request->request->get('name', '');
        $name = trim($name);
        if ($name === '') {
            $errors[] = $this->translator->trans('missing_nick');
        } elseif (mb_strlen($name) > 50) {
            $errors[] = $this->translator->trans('long_nick');
        }

        if (\count($errors) === 0 && !self::checkCommentQuestion($request->request->get('key', ''), $request->request->get('question', ''))) {
            $errors[] = $this->translator->trans('question');
        }

        if ($request->request->get('preview') !== null) {
            // Handling "Preview" button
            $text_preview = '<p>' . $this->translator->trans('Comment preview info') . '</p>' . "\n" .
                $this->viewer->render('comment', [
                    'text'       => $text,
                    'nick'       => $name,
                    'time'       => time(),
                    'email'      => $email,
                    'show_email' => $showEmail,
                ]);

            $template = $this->templateProvider->getTemplate('service.php');

            $template
                ->putInPlaceholder('head_title', $this->translator->trans('Comment preview'))
                ->putInPlaceholder('title', $this->translator->trans('Comment preview'))
                ->putInPlaceholder('text', $text_preview)
                ->putInPlaceholder('id', $id)
                ->putInPlaceholder('commented', true)
                ->putInPlaceholder('comment_form', compact('name', 'email', 'showEmail', 'subscribed', 'text'))
            ;

            return $template->toHttpResponse();
        }

        $spamDecision = SpamDecision::empty();
        if (\count($errors) === 0) {
            $spamDecision = $this->spamDecisionProvider->getVerdict(
                new SpamDetectorComment(
                    $name,
                    $email,
                    $text,
                    $request->headers->get('User-Agent'),
                    $request->headers->get('Referer'),
                    $this->urlBuilder->absLink($path)
                ),
                (string)$request->getClientIp()
            );
            // Convert spam detection report to some validation errors
            if ($spamDecision->shouldRejectLinks()) {
                $errors[] = $this->translator->trans('links_in_text');
            } elseif ($spamDecision->shouldRejectAsSpam()) {
                $errors[] = $this->translator->trans('spam_message_rejected');
            }
        }

        // What are we going to comment?
        $target = $this->commentStrategy->getTargetByRequest($request);

        if ($target === null && \count($errors) === 0) {
            $errors[] = $this->translator->trans('no_item');
        }

        if (\count($errors) > 0) {
            $errorText = '<p>' . $this->translator->trans('Error message') . '</p><ul>';
            foreach ($errors as $error) {
                $errorText .= '<li>' . $error . '</li>';
            }
            $errorText .= '</ul>';

            $template = $this->templateProvider->getTemplate('service.php');

            $template
                ->putInPlaceholder('head_title', '❌ ' . $this->translator->trans('Error'))
                ->putInPlaceholder('title', '<span class="icon-error">✖</span>' . $this->translator->trans('Error'))
                ->putInPlaceholder('text', $errorText . ($target !== null ? '<p>' . $this->translator->trans('Fix error') . '</p>' : ''))
                ->putInPlaceholder('id', $id)
                ->putInPlaceholder('commented', $target !== null) // can be commented, i.e. render comment form
                ->putInPlaceholder('comment_form', compact('name', 'email', 'showEmail', 'subscribed', 'text'))
            ;

            $this->logger->notice('Comment was not saved due to errors.', [
                'errors' => $errors,
                'path'   => $path,
            ]);
            return $template->toHttpResponse();
        }

        $link = $this->urlBuilder->absLink($path);

        /**
         * Everything is ok, save and send the comment
         */

        // Detect if there is a user logged in
        $isOnline = $this->authProvider->isOnline($email);

        $moderationRequired = $spamDecision->shouldModerate($this->premoderationEnabled);

        // Save the comment
        $commentId = $this->commentStrategy->save($target->id, $name, $email, $showEmail, $subscribed, $text, (string)$request->getClientIp());

        $message = StringHelper::bbcodeToMail($text);

        /**
         * Sending the comment to moderators.
         * We DO NOT SEND the comment to a moderator if his email is used and he is online.
         * We'll do it later if required in CommentSentController.
         * It cannot be done right now due to a special cookie is available in CommentSentController only.
         *
         * @see CommentSentController
         * @see \S2\Cms\Model\AuthManager::createCommentCookie
         */
        foreach ($this->userProvider->getModerators([], $moderationRequired && $isOnline ? [$email] : []) as $moderator) {
            $this->commentMailer->mailToModerator(
                $moderator->login,
                $moderator->email,
                $message,
                $target->title,
                $link,
                $name,
                $email,
                !$moderationRequired,
                $spamDecision->getStatus()
            );
        }

        if (!$moderationRequired) {
            // Sending the comment to subscribers
            $this->commentStrategy->notifySubscribers($commentId);
            $this->commentStrategy->publishComment($commentId);
            $hash = $this->commentStrategy->getHashForPublishedComment($target->id);
            // Redirect to the last comment
            $redirectLink = $this->urlBuilder->link($path) . ($hash !== null ? '#' . $hash : '');
        } else {
            $redirectLink = $this->urlBuilder->rawLink('/comment_sent', [
                'go=' . urlencode($path),
                'sign=' . self::commentHash($commentId, $target->id, $email, (string)$request->getClientIp(), \get_class($this->commentStrategy)),
            ]);
        }

        $response = new RedirectResponse($redirectLink);

        // Command for client code to clear draft from localStorage
        $response->headers->setCookie(Cookie::create('comment_form_sent', $id, httpOnly: false));

        return $response;
    }

    private static function checkCommentQuestion(string $key, string $answer): bool
    {
        if (\strlen($key) < 21) {
            return false;
        }

        return ((int)($key[10] . $key[12]) + (int)($key[20]) === (int)trim($answer));
    }
}
