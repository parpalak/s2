<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class RedirectDetector
{
    public function __construct(
        private array $redirectMap
    ) {
    }

    public function getRedirectResponse(Request $request): ?RedirectResponse
    {
        if (empty($this->redirectMap)) {
            return null;
        }

        $requestUri = $request->getPathInfo();
        $newUrl     = preg_replace(array_keys($this->redirectMap), array_values($this->redirectMap), $requestUri);
        if ($newUrl === $requestUri) {
            return null;
        }

        $url = (str_starts_with($newUrl, 'http://') || str_starts_with($newUrl, 'https://')) ? $newUrl : s2_link($newUrl);

        return new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
    }
}
