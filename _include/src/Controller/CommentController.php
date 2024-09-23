<?php
/**
 * @copyright 2007-2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use Psr\Log\LoggerInterface;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\AuthProvider;
use S2\Cms\Model\Comment\CommentStrategyInterface;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CommentController implements ControllerInterface
{
    public function __construct(
        private AuthProvider             $authProvider,
        private UserProvider             $userProvider,
        private CommentStrategyInterface $commentStrategy,
        private TranslatorInterface      $translator,
        private UrlBuilder               $urlBuilder,
        private HtmlTemplateProvider     $templateProvider,
        private Viewer                   $viewer,
        private LoggerInterface          $logger,
        private bool                     $commentsEnabled,
        private bool                     $premoderationEnabled,
    ) {
    }

    private const S2_MAX_COMMENT_BYTES = 65535;

    public static function commentHash(int $commentId, int $targetId, string $email, string $ip, string $strategyClass): string
    {
        return md5(serialize([$commentId, $targetId, $email, $ip, $strategyClass]));
    }

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $showEmail  = $request->request->get('show_email', false) !== false;
        $subscribed = $request->request->get('subscribed', false) !== false;
        $id         = $request->request->getString('id', '');
        if (!preg_match('#^[0-9a-f]{32}$#', $id)) {
            $id = '';
        }

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
            $errors[] = sprintf($this->translator->trans('long_text'), self::S2_MAX_COMMENT_BYTES);
        } elseif (self::linkCount($text) > 0) {
            $errors[] = $this->translator->trans('links_in_text');
        }

        $email = $request->request->get('email', '');
        $email = trim($email);
        if (!s2_is_valid_email($email)) {
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

        // What are we going to comment?
        $target = $this->commentStrategy->getTargetByRequest($request);
        $path   = $request->getPathInfo();

        if (empty($errors) && $target === null) {
            $errors[] = $this->translator->trans('no_item');
        }

        if (!empty($errors)) {
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

        $moderationRequired = $this->premoderationEnabled;

        // Save the comment
        $commentId = $this->commentStrategy->save($target->id, $name, $email, $showEmail, $subscribed, $text, (string)$request->getClientIp());

        $message = s2_bbcode_to_mail($text);

        // Sending the comment to moderators

        foreach ($this->userProvider->getModerators([], $moderationRequired && $isOnline ? [$email] : []) as $moderator) {
            s2_mail_moderator($moderator->login, $moderator->email, $message, $target->title, $link, $name, $email);
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

    private static function linkCount(string $text): int
    {
        return preg_match_all('#(https?://\S{2,}?)(?=[\s),\'><\]]|&lt;|&gt;|[.;:](?:\s|$)|$)#u', $text) ?: 0;
    }


    private static function checkCommentQuestion(string $key, string $answer): bool
    {
        if (\strlen($key) < 21) {
            return false;
        }

        return ((int)($key[10] . $key[12]) + (int)($key[20]) === (int)trim($answer));
    }
}
