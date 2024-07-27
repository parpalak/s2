<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

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
use S2\AdminYard\Database\LogicalExpression;
use S2\AdminYard\Event\AfterLoadEvent;
use S2\AdminYard\Event\AfterSaveEvent;
use S2\AdminYard\Event\BeforeDeleteEvent;
use S2\AdminYard\Event\BeforeRenderEvent;
use S2\AdminYard\Event\BeforeSaveEvent;
use S2\AdminYard\Translator;
use S2\AdminYard\Validator\Length;
use S2\AdminYard\Validator\Regex;
use S2\Cms\Admin\AdminConfigExtenderInterface;
use S2\Cms\Admin\AdminConfigProvider;
use S2\Cms\Admin\Controller\CommentController;
use S2\Cms\Admin\Event\VisibleEntityChangedEvent;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Template\HtmlTemplateProvider;
use s2_extensions\s2_blog\BlogUrlBuilder;
use s2_extensions\s2_blog\Model\BlogCommentNotifier;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class AdminConfigExtender implements AdminConfigExtenderInterface
{
    public function __construct(
        private PermissionChecker        $permissionChecker,
        private HtmlTemplateProvider     $templateProvider,
        private Translator               $translator,
        private TagsProvider             $tagsProvider,
        private PostProvider             $postProvider,
        private BlogUrlBuilder           $blogUrlBuilder,
        private BlogCommentNotifier      $blogCommentNotifier,
        private EventDispatcherInterface $eventDispatcher,
        private string                   $dbType,
        private string                   $dbPrefix
    ) {
    }

    public function extend(AdminConfig $adminConfig): void
    {
        $postEntity    = new EntityConfig('BlogPost', $this->dbPrefix . 's2_blog_posts');
        $commentEntity = new EntityConfig('BlogComment', $this->dbPrefix . 's2_blog_comments');

        $commentEntity
            ->addField(new FieldConfig(
                name: 'id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                useOnActions: []
            ))
            ->addField($postIdField = new FieldConfig(
                name: 'post_id',
                label: $this->translator->trans('Post'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT),
                control: 'autocomplete',
                linkToEntity: new LinkTo($postEntity, 'title'),
                useOnActions: [FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'nick',
                label: $this->translator->trans('Author'),
                control: 'input',
                validators: [new Length(max: 50)],
                viewTemplate: '_admin/templates/comment/view-author.php',
            ))
            ->addField(new FieldConfig(
                name: 'email',
                control: 'input',
                validators: [new Length(max: 80)],
                useOnActions: $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN) ? [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST] : [],
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
                label: $this->translator->trans('Date'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                control: 'datetime',
                sortable: true,
                useOnActions: [FieldConfig::ACTION_SHOW, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'text',
                label: $this->translator->trans('Comment'),
                control: 'textarea',
            ))
            ->addField(new FieldConfig(
                name: 'ip',
                label: $this->translator->trans('IP address'),
                sortable: true,
                useOnActions: $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN) ? [FieldConfig::ACTION_LIST] : [],
            ))
            ->addField(new FieldConfig(
                name: 'shown',
                label: $this->translator->trans('Published'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                inlineEdit: $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_HIDE_COMMENTS),
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
                label: $this->translator->trans('Good comment'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                inlineEdit: $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_COMMENTS),
                useOnActions: [FieldConfig::ACTION_LIST],
            ))
            ->addFilter(new Filter(
                'search',
                $this->translator->trans('Search'),
                'search_input',
                'text LIKE %1$s OR nick LIKE %1$s OR email LIKE %1$s OR ip LIKE %1$s',
                fn(string $value) => $value !== '' ? '%' . $value . '%' : null
            ))
            ->addFilter(new FilterLinkTo(
                $postIdField,
                $this->translator->trans('Post'),
            ))
            ->addFilter(new Filter(
                'good',
                $this->translator->trans('Mark'),
                'radio',
                'good = %1$s',
                options: [
                    '' => $this->translator->trans('All'),
                    1  => $this->translator->trans('Good'),
                    0  => $this->translator->trans('Usual'),
                ],
            ))
            ->addFilter(new Filter(
                'published',
                $this->translator->trans('Published'),
                'radio',
                'shown = %1$s',
                options: [
                    '' => $this->translator->trans('All'),
                    1  => $this->translator->trans('Visible'),
                    0  => $this->translator->trans('Hidden'),
                ]
            ))
            ->addFilter(new Filter(
                'status',
                $this->translator->trans('Status'),
                'radio',
                '(sent = 0 AND shown = 0) = (0 = %1$s)',
                options: ['' => 'All', 0 => 'Pending', 1 => 'Considered']
            ))
            ->setControllerClass(CommentController::class)
            ->setEnabledActions([
                FieldConfig::ACTION_LIST,
                ...$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_COMMENTS) ? [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_DELETE] : [],
            ])
            ->setListActionsTemplate('_admin/templates/comment/list-actions.php.inc')
            ->addListener(EntityConfig::EVENT_BEFORE_PATCH, function (BeforeSaveEvent $event) {
                if (isset($event->data['shown'])) {
                    $this->blogCommentNotifier->notify($event->primaryKey->getIntId());
                }
            })
        ;

        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN)) {
            $commentEntity->setReadAccessControl(new LogicalExpression('shown', 1));
        }

        $postEntity
            ->addField(new FieldConfig(
                name: 'id',
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, true),
                useOnActions: []
            ))
            ->addField(new FieldConfig(
                name: 'title',
                label: $this->translator->trans('Title'),
                control: 'input',
                validators: [new Length(max: 255)],
                sortable: true,
                actionOnClick: 'edit',
                viewTemplate: '_admin/templates/article/view-title.php',
            ))
            ->addField(new FieldConfig(
                name: 'tags',
                label: $this->translator->trans('Tags'),
                hint: $this->translator->trans('Tags help'),
                type: new VirtualFieldType((function () {
                    $column     = match ($this->dbType) {
                        'pgsql' => "STRING_AGG(t.name, ', ' ORDER BY pt.id)",
                        'sqlite' => "GROUP_CONCAT(t.name, ', ')", // seems like SQLite does not support ORDER BY
                        default => 'GROUP_CONCAT(t.name ORDER BY pt.id SEPARATOR ", ")',
                    };
                    $tableName  = $this->dbPrefix . 'tags';
                    $tableName2 = $this->dbPrefix . 's2_blog_post_tag';
                    $sql        = "SELECT $column FROM $tableName AS t JOIN $tableName2 AS pt ON t.tag_id = pt.tag_id WHERE pt.post_id = entity.id";
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
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'create_time',
                label: $this->translator->trans('Create time'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME, defaultValue: new \DateTimeImmutable()),
                control: 'datetime',
                sortable: true,
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
                viewTemplate: '_admin/templates/date.php.inc',
            ))
            ->addField(new FieldConfig(
                name: 'modify_time',
                label: $this->translator->trans('Modify time'),
                hint: $this->translator->trans('Modify time help'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_UNIXTIME),
                control: 'datetime',
                sortable: true,
                useOnActions: [FieldConfig::ACTION_EDIT],
                viewTemplate: '_admin/templates/date.php.inc',
            ))
            ->addField(new FieldConfig(
                name: 'text',
                control: 'html_textarea',
                useOnActions: [FieldConfig::ACTION_NEW, FieldConfig::ACTION_EDIT],
            ))
            ->addField(new FieldConfig(
                name: 'published',
                label: $this->translator->trans('Published'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'favorite',
                label: $this->translator->trans('Favorite'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                useOnActions: [
                    FieldConfig::ACTION_LIST,
                    ...$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) ? [FieldConfig::ACTION_EDIT] : [],
                ],
                viewTemplate: '_admin/templates/article/view-favorite.php',
            ))
            ->addField(new FieldConfig(
                name: 'commented',
                label: $this->translator->trans('Commented'),
                hint: $this->translator->trans('Commented info'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'comments',
                type: new LinkedByFieldType($commentEntity, 'CASE WHEN COUNT(*) > 0 THEN COUNT(*) ELSE NULL END', 'post_id'),
                sortable: true,
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
                viewTemplate: '_admin/templates/article/view-comments.php'
            ))
            ->addField(new FieldConfig(
                name: 'label',
                label: $this->translator->trans('Label'),
                hint: $this->translator->trans('Label help'),
                control: 'input',
                useOnActions: [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_LIST],
            ))
            ->addField(new FieldConfig(
                name: 'url',
                label: $this->translator->trans('URL part'),
                control: 'input',
                validators: [new Length(max: 255)],
                useOnActions: [FieldConfig::ACTION_EDIT],
            ))
            ->addField($userIdField = new FieldConfig(
                name: 'user_id',
                label: $this->translator->trans('Author'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_INT, defaultValue: $this->permissionChecker->getUserId()),
                control: 'select',
                linkToEntity: new LinkTo($adminConfig->findEntityByName('User'), 'CASE WHEN name IS NULL OR name = \'\' THEN login ELSE name END',  new LogicalExpression('create_articles', 1)),
                useOnActions: [
                    FieldConfig::ACTION_LIST,
                    ...$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) ? [FieldConfig::ACTION_EDIT] : [],
                ],
            ))
            ->addField(new FieldConfig(
                name: 'revision',
                control: 'hidden_input',
                useOnActions: [FieldConfig::ACTION_EDIT],
            ))
            ->setEnabledActions([
                FieldConfig::ACTION_LIST,
                ...$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_CREATE_ARTICLES) ? [FieldConfig::ACTION_EDIT, FieldConfig::ACTION_DELETE, FieldConfig::ACTION_NEW] : [],
            ])
            ->setReadAccessControl(
                $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN)
                    ? null
                    : new LogicalExpression('read_access_control_user_id', $this->permissionChecker->getUserId(), 'published = 1 OR user_id = %s')
            )
            ->setWriteAccessControl(
                $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE)
                    ? null
                    : new LogicalExpression('user_id', $this->permissionChecker->getUserId())
            )
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
                $event->data['labelList']       = $this->postProvider->getAllLabels();

                $formData   = $event->data['form']->getData();
                $createTime = $formData['create_time']?->getTimeStamp() ?? 0;
                $id         = (int)$event->data['primaryKey']['id'];

                $event->data['commentsNum'] = $this->postProvider->getCommentNum($id, $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN));
                $event->data['previewUrl']  = $this->blogUrlBuilder->postFromTimestamp($createTime, $formData['url']);
                $event->data['statusData']  = $this->getPostStatusData($createTime, $formData['url']);
            })
            ->addListener(EntityConfig::EVENT_BEFORE_UPDATE, function (BeforeSaveEvent $event) use ($postEntity) {
                $oldData = $event->dataProvider->getEntity(
                    $this->dbPrefix . 's2_blog_posts',
                    $postEntity->getFieldDataTypes(FieldConfig::ACTION_EDIT, includePrimaryKey: true),
                    [],
                    $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) ? [] : [
                        new LogicalExpression('user_id', $this->permissionChecker->getUserId()),
                    ],
                    $event->primaryKey
                );
                if ($oldData === null) {
                    $event->errorMessages[] = 'Post not found';
                    return;
                }

                $changed = false;
                foreach (['text', 'title', 'url'] as $field) {
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

                    $event->data['revision']        = (string)($event->data['revision'] + 1);
                    $event->context['new_revision'] = $event->data['revision'];
                } else {
                    // Changes might be in unimportant fields only.
                    // So we ignore $event->data['revision'] and refresh it on client side to the current value.
                    $event->data['revision']        = $oldData['column_revision'];
                    $event->context['new_revision'] = $oldData['column_revision'];
                }

                $newPublished = $event->data['published'];
                $oldPublished = $oldData['column_published'];

                if (
                    ($newPublished && (!$oldPublished || $changed)) // Publish a new article or update an existing one
                    || (!$newPublished && $oldPublished) // Withdraw a published article
                ) {
                    $event->context['visible_changed_event'] = new VisibleEntityChangedEvent(
                        $postEntity->getName(),
                        $event->primaryKey->getIntId()
                    );
                }

                $event->context['create_time'] = $event->data['create_time']->getTimestamp();
                $event->context['url']         = $event->data['url'];
            })
            ->addListener(EntityConfig::EVENT_AFTER_UPDATE, function (AfterSaveEvent $event) {
                if (isset($event->context['visible_changed_event'])) {
                    $this->eventDispatcher->dispatch($event->context['visible_changed_event']);
                }
                $event->ajaxExtraResponse = [
                    ...$this->getPostStatusData($event->context['create_time'], $event->context['url']),
                    'revision' => $event->context['new_revision'],
                ];
            })
            ->addListener([EntityConfig::EVENT_BEFORE_UPDATE], function (BeforeSaveEvent $event) {
                $event->context['tags'] = $event->data['tags'];
                unset($event->data['tags']);
            })
            ->addListener([EntityConfig::EVENT_AFTER_UPDATE], function (AfterSaveEvent $event) {
                $tagStr = $event->context['tags'];
                $tags   = array_map(static fn(string $tag) => trim($tag), explode(',', $tagStr));
                $tags   = array_filter($tags, static fn(string $tag) => $tag !== '');

                $newTagIds = AdminConfigProvider::tagIdsFromTags($event->dataProvider, $tags, $this->dbPrefix);

                $tableName = $this->dbPrefix . 's2_blog_post_tag';
                $fieldName = 'post_id';

                $existingLinks = $event->dataProvider->getEntityList($tableName, [
                    $fieldName => FieldConfig::DATA_TYPE_INT,
                    'tag_id'   => FieldConfig::DATA_TYPE_INT,
                ], conditions: [new LogicalExpression($fieldName, $event->primaryKey->getIntId())]);

                $existingTagIds = array_column($existingLinks, 'column_tag_id');
                if (implode(',', $existingTagIds) !== implode(',', $newTagIds)) {
                    $event->dataProvider->deleteEntity(
                        $tableName,
                        [$fieldName => FieldConfig::DATA_TYPE_INT],
                        new Key([$fieldName => $event->primaryKey->getIntId()]),
                        [],
                    );
                    foreach ($newTagIds as $tagId) {
                        $event->dataProvider->createEntity($tableName, [
                            $fieldName => FieldConfig::DATA_TYPE_INT,
                            'tag_id'   => FieldConfig::DATA_TYPE_INT,
                        ], [$fieldName => $event->primaryKey->getIntId(), 'tag_id' => $tagId]);
                    }
                }
            })
            ->addListener(EntityConfig::EVENT_BEFORE_DELETE, function (BeforeDeleteEvent $event) {
                $event->dataProvider->deleteEntity(
                    $this->dbPrefix . 's2_blog_post_tag',
                    ['post_id' => FieldConfig::DATA_TYPE_INT],
                    new Key(['post_id' => $event->primaryKey->getIntId()]),
                    [],
                );
                $event->dataProvider->deleteEntity(
                    $this->dbPrefix . 's2_blog_comments',
                    ['post_id' => FieldConfig::DATA_TYPE_INT],
                    new Key(['post_id' => $event->primaryKey->getIntId()]),
                    [],
                );
            })
            ->addFilter(
                new Filter(
                    'search',
                    $this->translator->trans('Fulltext Search'),
                    'search_input',
                    'title LIKE %1$s OR text LIKE %1$s',
                    fn(string $value) => $value !== '' ? '%' . $value . '%' : null
                )
            )
            ->addFilter(
                new Filter(
                    'tags',
                    $this->translator->trans('Tags'),
                    'search_input',
                    'id IN (SELECT pt.post_id FROM ' . $this->dbPrefix . 's2_blog_post_tag AS pt JOIN ' . $this->dbPrefix . 'tags AS t ON t.tag_id = pt.tag_id WHERE t.name LIKE %1$s)',
                    fn(string $value) => $value !== '' ? '%' . $value . '%' : null
                )
            )
            ->addFilter(
                new Filter(
                    'is_active',
                    $this->translator->trans('Published'),
                    'radio',
                    'published = %1$s',
                    options: ['' => 'All', 1 => 'Yes', 0 => 'No']
                )
            )
            ->addFilter(
                new Filter(
                    'created_from',
                    $this->translator->trans('Created after'),
                    'date',
                    'create_time >= %1$s',
                    fn(?string $value) => $value !== null ? strtotime($value) : null
                )
            )
            ->addFilter(
                new Filter(
                    'created_to',
                    $this->translator->trans('Created before'),
                    'date',
                    'create_time < %1$s',
                    fn(?string $value) => $value !== null ? strtotime($value) : null
                )
            )
            ->addFilter(new FilterLinkTo($userIdField, null))
            ->setEditTemplate(__DIR__ . '/../views/admin/post/edit.php.inc')
        ;

        $tagEntity = $adminConfig->findEntityByName('Tag');
        $tagEntity
            ->addField(new FieldConfig(
                name: 'used_in_posts',
                label: $this->translator->trans('Used in posts'),
                hint: $this->translator->trans('Used in posts info'),
                type: new VirtualFieldType(
                    'SELECT COUNT(*) FROM s2_blog_post_tag AS pt WHERE pt.tag_id = entity.tag_id',
                    new LinkToEntityParams($postEntity->getName(), ['tags'], ['name' /* tags.name */])
                ),
                sortable: true,
                useOnActions: [FieldConfig::ACTION_LIST, FieldConfig::ACTION_SHOW]
            ), 'used_in_articles')
            ->addField(new FieldConfig(
                name: 's2_blog_important',
                label: $this->translator->trans('Important tag'),
                hint: $this->translator->trans('Important tag info'),
                type: new DbColumnFieldType(FieldConfig::DATA_TYPE_BOOL),
                control: 'checkbox',
                sortable: true,
                inlineEdit: $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE),
                useOnActions: [
                    FieldConfig::ACTION_LIST,
                    FieldConfig::ACTION_SHOW,
                    ...$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_EDIT_SITE) ? [FieldConfig::ACTION_EDIT] : [],
                ]
            ))
            ->addListener(EntityConfig::EVENT_BEFORE_DELETE, function (BeforeDeleteEvent $event) {
                $event->dataProvider->deleteEntity(
                    $this->dbPrefix . 's2_blog_post_tag',
                    ['tag_id' => FieldConfig::DATA_TYPE_INT],
                    new Key(['tag_id' => $event->primaryKey->getIntId('tag_id')]),
                    [],
                );
            })
        ;

        $adminConfig
            ->addEntity($postEntity, 11)
            ->addEntity($commentEntity, 12)
        ;
    }

    private function getPostStatusData(int $createTime, string $url): array
    {
        $urlStatus = $this->postProvider->checkUrlStatus($createTime, $url);

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
