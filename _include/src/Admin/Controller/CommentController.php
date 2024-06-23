<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin\Controller;

use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Controller\EntityController;
use S2\AdminYard\Controller\InvalidRequestException;
use S2\AdminYard\Database\SafeDataProviderException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class CommentController extends EntityController
{
    public function rejectAction(Request $request): Response
    {
        if ($request->getRealMethod() !== Request::METHOD_POST) {
            throw new InvalidRequestException('Reject action must be called via POST request.', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $primaryKey = $this->getEntityPrimaryKeyFromRequest($request);
        $csrfToken  = $request->request->get('csrf_token');

        // Borrow CSRF token from delete action
        if ($this->getDeleteCsrfToken($primaryKey->toArray(), $request) !== $csrfToken) {
            return new JsonResponse(['errors' => [
                $this->translator->trans('Unable to confirm security token. A likely cause for this is that some time passed between when you first entered the page and when you submitted the form. If that is the case and you would like to continue, submit the form again.')
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->dataProvider->updateEntity(
                $this->entityConfig->getTableName(),
                [
                    'sent' => FieldConfig::DATA_TYPE_BOOL,
                    ... $this->entityConfig->getFieldDataTypes('patch', includePrimaryKey: true)
                ],
                $primaryKey,
                ['sent' => true],
            );
        } catch (SafeDataProviderException $e) {
            return new JsonResponse(['errors' => [$this->translator->trans($e->getMessage())]], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => ['Unable to update entity']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true]);
    }
}
