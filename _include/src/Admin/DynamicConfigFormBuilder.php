<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Config\DbColumnFieldType;
use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Database\TypeTransformer;
use S2\AdminYard\Form\FormFactory;
use S2\AdminYard\Form\FormParams;
use S2\AdminYard\SettingStorage\SettingStorageInterface;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Validator\Length;
use S2\AdminYard\Validator\Regex;
use S2\Cms\Model\PermissionChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gathers information about S2 configuration parameters and transforms the list of configuration parameters,
 * read by the AdminYard library from the database, into a set of mini-forms for editing these parameters.
 */
class DynamicConfigFormBuilder
{
    /**
     * S2 parameters. Extensions may add their own parameters via DynamicConfigFormExtenderInterface instances.
     */
    private const PARAM_TYPES = [
        'Site config'        => 'title',
        'S2_SITE_NAME'       => 'string',
        'S2_WEBMASTER'       => 'string',
        'S2_WEBMASTER_EMAIL' => 'email',
        'S2_START_YEAR'      => 'int',
        'S2_LANGUAGE'        => 'language',
        'S2_STYLE'           => 'style',
        'S2_COMPRESS'        => 'boolean',

        'Comments config'     => 'title',
        'S2_SHOW_COMMENTS'    => 'boolean',
        'S2_ENABLED_COMMENTS' => 'boolean',
        'S2_PREMODERATION'    => 'boolean',
        'S2_AKISMET_KEY'      => 'string',

        'Navigation config' => 'title',
        'S2_USE_HIERARCHY'  => 'boolean',
        'S2_MAX_ITEMS'      => 'int',
        'S2_FAVORITE_URL'   => 'string',
        'S2_TAGS_URL'       => 'string',

        'Admin config'     => 'title',
        'S2_ADMIN_COLOR'   => 'color',
        'S2_ADMIN_NEW_POS' => 'boolean',
        'S2_ADMIN_CUT'     => 'boolean',
        'S2_LOGIN_TIMEOUT' => 'int',
        'S2_DB_REVISION'   => 'hidden',
    ];

    /**
     * @var DynamicConfigFormExtenderInterface[]
     */
    private array $dynamicConfigFormExtenders;

    public function __construct(
        private readonly PermissionChecker       $permissionChecker,
        private readonly TranslatorInterface     $translator,
        private readonly TypeTransformer         $typeTransformer,
        private readonly FormFactory             $formFactory,
        private readonly TemplateRenderer        $templateRenderer,
        private readonly ResourceProvider        $resourceProvider,
        private readonly RequestStack            $requestStack,
        private readonly SettingStorageInterface $settingStorage,
        DynamicConfigFormExtenderInterface       ...$dynamicConfigFormExtenders
    ) {
        $this->dynamicConfigFormExtenders = $dynamicConfigFormExtenders;
    }

    public function transformConfigTable(string $entityName, array &$header, array &$rows): void
    {
        $paramTypes = $this->getParamTypes();
        foreach ($paramTypes as $paramName => $paramType) {
            if ($paramType === 'title') {
                $rows[] = [
                    'cells' => [
                        'name'  => ['content' => $paramName, 'type' => 'config-title'],
                        'value' => ['content' => '', 'type' => 'string'],
                        'help'  => ['content' => '', 'type' => 'string'],
                    ]
                ];
            }
        }
        $orderArray = array_flip(array_keys($paramTypes));
        usort($rows, static fn($row1, $row2) => ($orderArray[$row1['cells']['name']['content']] ?? PHP_INT_MAX) <=> ($orderArray[$row2['cells']['name']['content']] ?? PHP_INT_MAX));

        $valFieldName = 'value';
        foreach ($rows as $rowIndex => &$row) {
            $paramName = $row['cells']['name']['content'];

            if (($paramTypes[$paramName] ?? null) === 'title') {
                $row['cells']['name']['content'] = '<b>' . $this->translator->trans($paramName) . '</b>';
                continue;
            }
            if (($paramTypes[$paramName] ?? null) === 'hidden') {
                unset($rows[$rowIndex]);
                continue;
            }

            $field = $this->createDynamicFieldConfig($paramName);
            if ($field->inlineEdit) {
                $form = $this->formFactory->createEntityForm(new FormParams(
                    $entityName,
                    [$valFieldName => $field],
                    $this->settingStorage,
                    'patch',
                    $row['primary_key'],
                ));
                $form->fillFromArray([
                    $valFieldName => $this->typeTransformer->normalizedFromDb($row['cells']['value']['content'], $field->type->dataType),
                ]);

                $row['cells']['value']['content'] = $this->templateRenderer->render($field->inlineFormTemplate, [
                    'value'      => $row['cells']['value']['content'],
                    'form'       => $form,
                    'entityName' => $entityName,
                    'fieldName'  => $valFieldName,
                    'primaryKey' => $row['primary_key'],
                ]);
            }
            $row['cells']['name']['content'] = $this->translator->trans($paramName);
            $row['cells']['help']            = [
                'content' => $this->translator->trans($paramName . '_help'),
                'type'    => FieldConfig::DATA_TYPE_STRING,
            ];
        }
        unset($row);

        $header['help'] = $this->translator->trans('Help');
    }

    public function getValueFieldConfig(): FieldConfig
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null && $request->query->get('action') === 'patch' && $request->query->get('field') === 'value') {
            // Polymorphic field config for form processing in AdminYard.
            // Datatype and control are selected based on the parameter name.
            return $this->createDynamicFieldConfig($request->query->get('name'));
        }

        // Fake field config for AdminYard on the list screen.
        // Real field configs will be generated in self::transformConfigTable().
        return new FieldConfig(name: 'value');
    }

    private function createDynamicFieldConfig(string $paramName): FieldConfig
    {
        $inlineEdit = $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_USERS);

        return match ($this->getParamTypes()[$paramName] ?? 'string') {
            'string' => new FieldConfig(
                'value',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING),
                control: 'input',
                inlineEdit: $inlineEdit
            ),
            'email' => new FieldConfig(
                'value',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING),
                control: 'input',
                validators: [
                    (static function () {
                        $validator          = new Regex('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/');
                        $validator->message = 'Invalid webmaster email';
                        return $validator;
                    })(),
                    new Length(max: 80),
                ],
                inlineEdit: $inlineEdit
            ),
            'int' => new FieldConfig(
                'value',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT),
                control: 'int_input',
                inlineEdit: $inlineEdit
            ),
            'boolean' => new FieldConfig(
                'value',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                inlineEdit: $inlineEdit
            ),
            'color' => new FieldConfig(
                'value',
                control: 'color_input',
                options: ['#eeeeee', '#f5e6e6', '#f5ece6', '#f5f0e6', '#edf5e6', '#e6f5ed', '#e6f3f5', '#e6edf5', '#e8e6f5', '#ede6f5'],
                inlineEdit: $inlineEdit
            ),
            'language' => new FieldConfig(
                'value',
                control: 'select',
                options: array_combine($languages = $this->resourceProvider->readLanguages(), $languages),
                inlineEdit: $inlineEdit
            ),
            'style' => new FieldConfig(
                'value',
                control: 'select',
                options: array_combine($styles = $this->resourceProvider->readStyles(), $styles),
                inlineEdit: $inlineEdit
            ),
        };
    }

    private function getParamTypes(): array
    {
        return $this->paramTypes ?? array_merge(
            self::PARAM_TYPES,
            ...array_map(
                static fn(DynamicConfigFormExtenderInterface $extender) => $extender->getExtraParamTypes(),
                $this->dynamicConfigFormExtenders
            )
        );
    }
}
