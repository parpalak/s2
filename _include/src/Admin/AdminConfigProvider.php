<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\Config\DbColumnFieldType;
use S2\AdminYard\Config\EntityConfig;
use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Config\Filter;
use S2\AdminYard\Config\FilterLinkTo;
use S2\AdminYard\Config\LinkedByFieldType;
use S2\AdminYard\Config\LinkTo;
use S2\AdminYard\Config\LinkToEntityParams;
use S2\AdminYard\Config\VirtualFieldType;
use S2\AdminYard\Database\Key;
use S2\AdminYard\Database\PdoDataProvider;
use S2\AdminYard\Event\AfterLoadEvent;
use S2\AdminYard\Event\AfterSaveEvent;
use S2\AdminYard\Event\BeforeDeleteEvent;
use S2\AdminYard\Event\BeforeRenderEvent;
use S2\AdminYard\Event\BeforeSaveEvent;
use S2\AdminYard\Translator;
use S2\AdminYard\Validator\Length;
use S2\AdminYard\Validator\NotBlank;
use S2\AdminYard\Validator\Regex;
use S2\Cms\Admin\Controller\CommentController;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\CommentNotifier;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Template\HtmlTemplateProvider;

readonly class AdminConfigProvider
{
    /**
     * @var AdminConfigExtenderInterface[]
     */
    private array $adminConfigExtenders;

    public function __construct(
        private HtmlTemplateProvider     $templateProvider,
        private DynamicConfigFormBuilder $dynamicConfigFormBuilder,
        private DynamicConfigProvider    $dynamicConfigProvider,
        private Translator               $translator,
        private ArticleProvider          $articleProvider,
        private TagsProvider             $tagsProvider,
        private UrlBuilder               $urlBuilder,
        private CommentNotifier          $commentNotifier,
        private ExtensionCache           $extensionCache,
        private string                   $dbType,
        private string                   $dbPrefix,
        AdminConfigExtenderInterface     ...$adminConfigExtenders
    ) {
        if (!\in_array($dbType, ['mysql', 'pgsql', 'sqlite'])) {
            throw new \InvalidArgumentException('Unsupported database type: ' . $dbType);
        }
        $this->adminConfigExtenders = $adminConfigExtenders;
    }

    public function getAdminConfig(): AdminConfig
    {
        $adminConfig = new AdminConfig();

        $articleEntity = new EntityConfig('Article', $this->dbPrefix . 'articles');
        $articleEntity->setEditTemplate('templates/article/edit.php.inc');
        $commentEntity = new EntityConfig('Comment', $this->dbPrefix . 'art_comments');

        $commentEntity
            ->addField(new FieldConfig(
                name: 'id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                useOnActions: []
            ))
            ->addField($articleFieldId = new FieldConfig(
                name: 'article_id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT),
                control: 'autocomplete',
                linkToEntity: new LinkTo($articleEntity, 'title'),
                useOnActions: [FieldConfig::ACTION_LIST, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'nick',
                label: 'Author',
                control: 'input',
                validators: [new Length(max: 50)],
                viewTemplate: 'templates/comment/view-author.php',
            ))
            ->addField(new FieldConfig(
                name: 'email',
                control: 'input',
                validators: [new Length(max: 80)],
            ))
            ->addField(new FieldConfig(
                name: 'show_email',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
            ))
            ->addField(new FieldConfig(
                name: 'subscribed',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
            ))
            ->addField(new FieldConfig(
                name: 'time',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                control: 'datetime',
                sortable: true,
                useOnActions: [FieldConfig::ACTION_SHOW, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'text',
                control: 'textarea',
            ))
            ->addField(new FieldConfig(
                name: 'ip',
                sortable: true,
                useOnActions: [FieldConfig::ACTION_SHOW, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'shown',
                label: $this->translator->trans('Published'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                inlineEdit: true,
                useOnActions: [FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'sent',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                useOnActions: [FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'good',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                inlineEdit: true,
                useOnActions: [FieldConfig::ACTION_LIST],
            ))
            ->addFilter(new Filter(
                'search',
                'Search',
                'search_input',
                'text LIKE %1$s OR nick LIKE %1$s OR email LIKE %1$s OR ip LIKE %1$s',
                fn(string $value) => $value !== '' ? '%' . $value . '%' : null
            ))
            ->addFilter(new FilterLinkTo(
                $articleFieldId,
                'Article',
            ))
            ->addFilter(new Filter(
                'good',
                $this->translator->trans('Good'),
                'radio',
                'good = %1$s',
                options: ['' => 'All', 1 => 'Good', 0 => 'Usual']
            ))
            ->addFilter(new Filter(
                'published',
                $this->translator->trans('Published'),
                'radio',
                'shown = %1$s',
                options: ['' => 'All', 1 => 'Visible', 0 => 'Hidden']
            ))
            ->addFilter(new Filter(
                'status',
                $this->translator->trans('Status'),
                'radio',
                '(sent = 0 AND shown = 0) = (0 = %1$s)',
                options: ['' => 'All', 0 => 'Pending', 1 => 'Considered']
            ))
            ->setControllerClass(CommentController::class)
            ->setEnabledActions([FieldConfig::ACTION_LIST, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_DELETE])
            ->setListActionsTemplate('templates/comment/list-actions.php.inc')
            ->addListener(EntityConfig::EVENT_BEFORE_PATCH, function (BeforeSaveEvent $event) {
                if (isset($event->data['shown'])) {
                    $this->commentNotifier->notify($event->primaryKey->getIntId());
                }
            })
        ;

        $userEntity = new EntityConfig('User', $this->dbPrefix . 'users');
        $userEntity
            ->setEnabledActions([FieldConfig::ACTION_DELETE, FieldConfig::ACTION_LIST, FieldConfig::ACTION_NEW])
            ->addField(new FieldConfig(
                name: 'id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                useOnActions: []
            ))
            ->addField(new FieldConfig(
                name: 'login',
                control: 'input',
                validators: [new NotBlank(), new Length(max: 191)],
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'password',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_PASSWORD),
                control: 'password',
                validators: [new Length(min: 8)],
                inlineEdit: true,
                useOnActions: [FieldConfig::ACTION_LIST, FieldConfig::ACTION_NEW],
            ))
            ->addField(new FieldConfig(
                name: 'name',
                control: 'input',
                validators: [new Length(max: 80)],
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'email',
                control: 'input',
                validators: [new Length(max: 80)],
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'view',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'view_hidden',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'hide_comments',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'edit_comments',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'create_articles',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'edit_site',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addField(new FieldConfig(
                name: 'edit_users',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: true,
            ))
            ->addListener(
                [EntityConfig::EVENT_BEFORE_PATCH, EntityConfig::EVENT_BEFORE_CREATE],
                function (BeforeSaveEvent $event) {
                    if (isset($event->data['password'])) {
                        $event->data['password'] = md5($event->data['password'] . 'Life is not so easy :-)');
                    }
                }
            )
        ;

        $articleEntity
            ->addField(new FieldConfig(
                name: 'id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                useOnActions: []
            ))
            ->addField(new FieldConfig(
                name: 'title',
                control: 'input',
                validators: [new Length(max: 255)],
                actionOnClick: 'edit',
                viewTemplate: 'templates/article/view-title.php',
            ))
            ->addField(new FieldConfig(
                name: 'tags',
                type: new VirtualFieldType((function () {
                    $column     = match ($this->dbType) {
                        'pgsql' => "STRING_AGG(t.name, ', ' ORDER BY pt.id)",
                        'sqlite' => "GROUP_CONCAT(t.name, ', ')", // seems like SQLite does not support ORDER BY
                        default => 'GROUP_CONCAT(t.name ORDER BY pt.id SEPARATOR ", ")',
                    };
                    $tableName  = $this->dbPrefix . 'tags';
                    $tableName2 = $this->dbPrefix . 'article_tag';
                    $sql        = "SELECT $column FROM $tableName AS t JOIN $tableName2 AS pt ON t.tag_id = pt.tag_id WHERE pt.article_id = entity.id";
                    return $sql;
                })()),
                control: 'input',
                validators: [
                    (static function () {
                        $validator          = new Regex('#^[\p{L}\p{N}_\- ,\.!]*$#u');
                        $validator->message = 'Tags must contain only letters, numbers and spaces.';
                        return $validator;
                    })(),
                ],
                sortable: true,
            ))
            ->addField(new FieldConfig(
                name: 'meta_keys',
                control: 'input',
                validators: [new Length(max: 255)],
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'meta_desc',
                control: 'input',
                validators: [new Length(max: 255)],
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'excerpt',
                control: 'input',
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'pagetext',
                control: 'html_textarea',
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'create_time',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                control: 'datetime',
                sortable: true,
                viewTemplate: 'templates/date.php.inc',
            ))
            ->addField(new FieldConfig(
                name: 'modify_time',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                control: 'datetime',
                sortable: true,
                viewTemplate: 'templates/date.php.inc',
            ))
            ->addField(new FieldConfig(
                name: 'published',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
            ))
            ->addField(new FieldConfig(
                name: 'favorite',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                viewTemplate: 'templates/article/view-favorite.php',
            ))
            ->addField(new FieldConfig(
                name: 'commented',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
            ))
            ->addField(new FieldConfig(
                name: 'comments',
                type: new LinkedByFieldType($commentEntity, 'CASE WHEN COUNT(*) > 0 THEN COUNT(*) ELSE NULL END', 'article_id'),
                sortable: true,
                viewTemplate: 'templates/article/view-comments.php'
            ))
            ->addField(new FieldConfig(
                name: 'url',
                control: 'input',
                validators: [new Length(max: 255)],
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_SHOW],
            ))
            ->addField(new FieldConfig(
                name: 'template',
                control: 'input',
            ))
            ->addField(new FieldConfig(
                name: 'user_id',
                label: 'Author',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT),
                control: 'select',
                linkToEntity: new LinkTo($userEntity, 'CASE WHEN name IS NULL OR name = \'\' THEN login ELSE name END'),
            ))
            ->addField(new FieldConfig(
                name: 'revision',
                control: 'hidden_input',
                useOnActions: [FieldConfig::ACTION_EDIT],
            ))
            ->markAsDefault()
            ->setEnabledActions([FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST, FieldConfig::ACTION_DELETE])
            ->addListener([EntityConfig::EVENT_AFTER_EDIT_FETCH], function (AfterLoadEvent $event) {
                if (\is_array($event->data)) {
                    // Convert NULL to an empty string when the edit form is filled with current data
                    $event->data['virtual_tags'] = (string)$event->data['virtual_tags'];
                    if (trim($event->data['virtual_tags']) !== '') {
                        // Add an extra comma to simplify adding a new tag
                        $event->data['virtual_tags'] .= ', ';
                    }
                }
            })
            ->addListener(EntityConfig::EVENT_BEFORE_EDIT_RENDER, function (BeforeRenderEvent $event) {
                $event->data['templateContent'] = $this->templateProvider->getRawTemplateContent('site.php', null);
                $event->data['tagsList']        = $this->tagsProvider->getAllTags();

                $path                        = $this->articleProvider->pathFromId((int)$event->data['primaryKey']['id']);
                $event->data['previewUrl']   = $this->urlBuilder->link($path);
                $event->data['templateList'] = $this->articleProvider->getTemplateList();
                $event->data['statusData']   = $this->getArticleStatusData((int)$event->data['primaryKey']['id']);
            })
            ->addListener(EntityConfig::EVENT_BEFORE_UPDATE, function (BeforeSaveEvent $event) use ($articleEntity) {
                $oldData = $event->dataProvider->getEntity(
                    $this->dbPrefix . 'articles',
                    $articleEntity->getFieldDataTypes(FieldConfig::ACTION_EDIT, includePrimaryKey: true),
                    [],
                    $event->primaryKey
                );

                if ($oldData === null) {
                    $event->errorMessages[] = 'Article not found';
                    return;
                }
//                [
//                    'column_user_id' => $user_id,
//                ] = $oldData;
//
//                if (!$s2_user['edit_site']) {
//                    s2_test_user_rights($user_id == $s2_user['id']);
//                }
//                if ($s2_user['edit_site'])
//                    $query['SET'] .= ', user_id = '.intval($page['user_id']);

                $changed = false;
                foreach (['pagetext', 'title', 'url', 'meta_keys', 'meta_desc'] as $field) {
                    if ($event->data[$field] !== $oldData['column_' . $field]) {
                        $changed = true;
                    }
                }
                if ($changed) {
                    // If the page text has been modified, we check if this modification is done by current user
                    if ($event->data['revision'] !== $oldData['column_revision']) {
                        // No, it's somebody else
                        $event->errorMessages[] = $this->translator->trans('Outdated version');
                        return;
                    }

                    $event->data['revision'] = (string)($event->data['revision'] + 1);
                    $event->context['new_revision'] = $event->data['revision'];
                } else {
                    // Changes might be in unimportant fields only.
                    // So we ignore $event->data['revision'] and refresh it on client side to the current value.
                    $event->data['revision'] = $oldData['column_revision'];
                    $event->context['new_revision'] = $oldData['column_revision'];
                }

                $event->context['article_id']   = $event->primaryKey->getIntId();
            })
            ->addListener(EntityConfig::EVENT_AFTER_UPDATE, function (AfterSaveEvent $event) {
                $event->ajaxExtraResponse = [
                    ...$this->getArticleStatusData($event->context['article_id']),
                    'revision' => $event->context['new_revision'],
                ];
            })
            ->addListener([EntityConfig::EVENT_BEFORE_UPDATE, EntityConfig::EVENT_BEFORE_CREATE], function (BeforeSaveEvent $event) {
                $event->context['tags'] = $event->data['tags'];
                unset($event->data['tags']);
            })
            ->addListener([EntityConfig::EVENT_AFTER_UPDATE, EntityConfig::EVENT_AFTER_CREATE], function (AfterSaveEvent $event) {
                $tagStr = $event->context['tags'];
                $tags   = array_map(static fn(string $tag) => trim($tag), explode(',', $tagStr));
                $tags   = array_filter($tags, static fn(string $tag) => $tag !== '');

                $newTagIds = self::tagIdsFromTags($event->dataProvider, $tags, $this->dbPrefix);

                $tableName = $this->dbPrefix . 'article_tag';

                $existingLinks = $event->dataProvider->getEntityList($tableName, [
                    'article_id' => FieldConfig::DATA_TYPE_INT,
                    'tag_id'     => FieldConfig::DATA_TYPE_INT,
                ], filterData: ['article_id' => $event->primaryKey->getIntId()]);

                $existingTagIds = array_column($existingLinks, 'column_tag_id');
                if (implode(',', $existingTagIds) !== implode(',', $newTagIds)) {
                    $event->dataProvider->deleteEntity(
                        $tableName,
                        ['article_id' => FieldConfig::DATA_TYPE_INT],
                        new Key(['article_id' => $event->primaryKey->getIntId()])
                    );

                    foreach ($newTagIds as $tagId) {
                        $event->dataProvider->createEntity($tableName, [
                            'article_id' => FieldConfig::DATA_TYPE_INT,
                            'tag_id'     => FieldConfig::DATA_TYPE_INT,
                        ], ['article_id' => $event->primaryKey->getIntId(), 'tag_id' => $tagId]);
                    }
                }
            })
            ->addListener(EntityConfig::EVENT_BEFORE_DELETE, function (BeforeDeleteEvent $event) {
                $event->dataProvider->deleteEntity(
                    $this->dbPrefix . 'article_tag',
                    ['article_id' => FieldConfig::DATA_TYPE_INT],
                    new Key(['article_id' => $event->primaryKey->getIntId()])
                );
            })
            ->addFilter(
                new Filter(
                    'search',
                    'Fulltext Search',
                    'search_input',
                    'title LIKE %1$s OR pagetext LIKE %1$s OR meta_keys LIKE %1$s OR meta_desc LIKE %1$s OR excerpt LIKE %1$s',
                    fn(string $value) => $value !== '' ? '%' . $value . '%' : null
                )
            )
            ->addFilter(
                new Filter(
                    'tags',
                    'Tags',
                    'search_input',
                    'id IN (SELECT at.article_id FROM ' . $this->dbPrefix . 'article_tag AS at JOIN ' . $this->dbPrefix . 'tags AS t ON t.tag_id = at.tag_id WHERE t.name LIKE %1$s)',
                    fn(string $value) => $value !== '' ? '%' . $value . '%' : null
                )
            )
            ->addFilter(
                new Filter(
                    'is_active',
                    'Published',
                    'radio',
                    'published = %1$s',
                    options: ['' => 'All', 1 => 'Yes', 0 => 'No']
                )
            )
            ->addFilter(
                new Filter(
                    'created_from',
                    'Created after',
                    'date',
                    'create_time >= %1$s',
                    fn(?string $value) => $value !== null ? strtotime($value) : null
                )
            )
            ->addFilter(
                new Filter(
                    'created_to',
                    'Created before',
                    'date',
                    'create_time < %1$s',
                    fn(?string $value) => $value !== null ? strtotime($value) : null
                )
            )
        ;

        $adminConfig
            ->addEntity($articleEntity, 0)
            ->addEntity($commentEntity, 10)
            ->addEntity($userEntity, 50)
            ->addEntity((new EntityConfig('Tag', $this->dbPrefix . 'tags'))
                ->addField(new FieldConfig(
                    name: 'tag_id',
                    type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                    useOnActions: []
                ))
                ->addField(new FieldConfig(
                    name: 'name',
                    control: 'input',
                    validators: [
                        new NotBlank(),
                        new Length(min: 1, max: 255),
                        (static function () {
                            $r          = new Regex('#^[\p{L}\p{N}_\- !\.]*$#u');
                            $r->message = 'Tag name must contain only letters, numbers and spaces';
                            return $r;
                        })(),
                    ],
                    sortable: true,
                    actionOnClick: 'edit'
                ))
                ->addField(new FieldConfig(
                    name: 'used_in_articles',
                    type: new VirtualFieldType(
                        'SELECT CAST(COUNT(*) AS CHAR) FROM ' . $this->dbPrefix . 'article_tag AS pt WHERE pt.tag_id = entity.tag_id',
                        new LinkToEntityParams($articleEntity->getName(), ['tags'], ['name' /* tags.name */])
                    ),
                    sortable: true,
                    useOnActions: [FieldConfig::ACTION_LIST, FieldConfig::ACTION_SHOW]
                ))
                ->addField(new FieldConfig(
                    name: 'description',
                    control: 'textarea',
                    useOnActions: [FieldConfig::ACTION_SHOW, FieldConfig::ACTION_EDIT, FieldConfig::ACTION_NEW]
                ))
                ->addField(new FieldConfig(
                    name: 'modify_time',
                    type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                    control: 'datetime',
                    sortable: true
                ))
                ->addField(new FieldConfig(
                    name: 'url',
                    control: 'input',
                    validators: [new Length(max: 255)],
                    sortable: true
                ))
                ->addFilter(new Filter(
                    'search',
                    'Fulltext Search',
                    'input',
                    'name LIKE %1$s OR description LIKE %1$s OR url LIKE %1$s',
                    fn(string $value) => $value !== '' ? '%' . $value . '%' : null
                )),
                20
            )
            ->addEntity(
                (new EntityConfig('Config', $this->dbPrefix . 'config'))
                    ->addField(new FieldConfig(
                        name: 'name',
                        type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING, true),
                    ))
                    ->addField($this->dynamicConfigFormBuilder->getValueFieldConfig())
                    ->setEnabledActions([FieldConfig::ACTION_LIST])
                    ->addListener(EntityConfig::EVENT_BEFORE_LIST_RENDER, function (BeforeRenderEvent $event) {
                        $this->dynamicConfigFormBuilder->transformConfigTable('Config', $event->data['header'], $event->data['rows']);
                    })
                    ->addListener(EntityConfig::EVENT_AFTER_PATCH, function (AfterSaveEvent $event) {
                        $this->dynamicConfigProvider->regenerate();
                        switch ($event->primaryKey->toArray()['name']) {
                            case 'S2_FAVORITE_URL':
                            case 'S2_TAGS_URL':
                                $this->extensionCache->clearRoutesCache();
                        }
                    })
                    ->setListTemplate('templates/config/list.php.inc')
                , 40
            )
            ->addEntity(
                (new EntityConfig('Queue', $this->dbPrefix . 'queue'))
                    ->addField(new FieldConfig(
                        name: 'id',
                        type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING, true),
                    ))
                    ->addField(new FieldConfig(
                        name: 'code',
                        type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING, true),
                    ))
                    ->addField(new FieldConfig(
                        name: 'payload',
                    ))
                    ->setEnabledActions([FieldConfig::ACTION_LIST])
                , 70
            )
//            ->addEntity(
//                (new EntityConfig('Extension', $this->dbPrefix . 'extensions'))
//                    ->addField(new FieldConfig(
//                        name: 'id',
//                        type: new DbColumnFieldType(FieldConfig::DATA_TYPE_STRING, true),
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'title',
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'version',
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'description',
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'author',
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'uninstall_note',
//                    ))
//                    ->addField(new FieldConfig(
//                        name: 'disabled',
//                        type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
//                        control: 'checkbox',
//                        inlineEdit: true
//                    ))
//                ->setEnabledActions([FieldConfig::ACTION_LIST])
//            )
        ;

        foreach ($this->adminConfigExtenders as $adminConfigExtender) {
            $adminConfigExtender->extend($adminConfig);
        }

        $adminConfig->setLayoutTemplate('templates/layout.php.inc');

        return $adminConfig;
    }

    /**
     * @throws \S2\AdminYard\Database\DataProviderException
     * @throws \PDOException
     */
    public static function tagIdsFromTags(PdoDataProvider $dataProvider, array $tags, string $dbPrefix): array
    {
        $existingTags = $dataProvider->getEntityList(
            $dbPrefix . 'tags',
            [
                'name'   => FieldConfig::DATA_TYPE_STRING,
                'tag_id' => FieldConfig::DATA_TYPE_INT,
            ],
            filterData: ['LOWER(name)' => array_map(static fn(string $tag) => mb_strtolower($tag), $tags)],
        );

        $existingTagsMap = array_column($existingTags, 'column_name', 'column_tag_id');
        $existingTagsMap = array_map(static fn(string $tag) => mb_strtolower($tag), $existingTagsMap);
        $existingTagsMap = array_flip($existingTagsMap);

        $tagIds = [];
        foreach ($tags as $tag) {
            if (!isset($existingTagsMap[mb_strtolower($tag)])) {
                $dataProvider->createEntity($dbPrefix . 'tags', [
                    'name'        => FieldConfig::DATA_TYPE_STRING,
                    'description' => FieldConfig::DATA_TYPE_STRING,
                    'url'         => FieldConfig::DATA_TYPE_STRING,
                    'modify_time' => FieldConfig::DATA_TYPE_INT,
                ], [
                    'name'        => $tag,
                    'description' => '',
                    'url'         => $tag,
                    'modify_time' => 0,
                ]);
                $newTagId = $dataProvider->lastInsertId();
            } else {
                $newTagId = $existingTagsMap[mb_strtolower($tag)];
            }
            $tagIds[] = $newTagId;
        }

        return $tagIds;
    }

    private function getArticleStatusData(int $articleId): array
    {
        $urlStatus = $this->articleProvider->checkUrlStatus($articleId);

        return [
            'urlStatus' => $urlStatus,
            'urlTitle'  => match ($urlStatus) {
                'empty' => $this->translator->trans('URL empty'),
                'not_unique' => $this->translator->trans('URL not unique'),
                'ok' => '',
            },
        ];
    }
}
