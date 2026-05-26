<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Adds endpoints implemented as plain Symfony controllers (with #[Route])
 * to the auto-generated API Platform OpenAPI document.
 *
 * API Platform only documents #[ApiResource] operations natively; this
 * decorator fills in everything else so Swagger UI is complete.
 */
final class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        foreach ($this->routes() as $route) {
            $pathItem = $paths->getPath($route['path']) ?? new PathItem();
            $method = strtolower($route['method']);
            $with = 'with'.ucfirst($method);
            $pathItem = $pathItem->{$with}($this->buildOperation($route));
            $paths->addPath($route['path'], $pathItem);
        }

        return $openApi->withPaths($paths);
    }

    private function buildOperation(array $route): Operation
    {
        $public = $route['public'] ?? false;

        $responses = [];
        foreach ($route['responses'] as $status => $resp) {
            $content = null;
            if (isset($resp['schema'])) {
                $content = new \ArrayObject([
                    'application/json' => ['schema' => $resp['schema']],
                ]);
            } elseif (isset($resp['contentType'])) {
                $content = new \ArrayObject([
                    $resp['contentType'] => ['schema' => ['type' => 'string', 'format' => 'binary']],
                ]);
            }
            $responses[(string) $status] = new Response($resp['description'] ?? '', $content);
        }

        $parameters = [];
        foreach ($route['parameters'] ?? [] as $param) {
            $parameters[] = new Parameter(
                name: $param['name'],
                in: $param['in'],
                description: $param['description'] ?? '',
                required: $param['required'] ?? ($param['in'] === 'path'),
                schema: $param['schema'],
            );
        }

        $requestBody = null;
        if (isset($route['requestBody'])) {
            $rb = $route['requestBody'];
            $requestBody = new RequestBody(
                description: $rb['description'] ?? '',
                content: new \ArrayObject($rb['content']),
                required: $rb['required'] ?? true,
            );
        }

        return new Operation(
            operationId: $route['operationId'],
            tags: [$route['tag']],
            responses: $responses,
            summary: $route['summary'],
            description: $route['description'] ?? null,
            parameters: $parameters,
            requestBody: $requestBody,
            security: $public ? [] : null,
        );
    }

    private function routes(): array
    {
        $jsonObject = fn (array $schema) => ['application/json' => ['schema' => $schema]];

        $multipartFile = ['multipart/form-data' => ['schema' => [
            'type' => 'object',
            'required' => ['file'],
            'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
        ]]];

        $userShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                'isPrivate' => ['type' => 'boolean'],
                'avatarUrl' => ['type' => 'string', 'nullable' => true],
                'bio' => ['type' => 'string', 'nullable' => true],
                'font' => ['type' => 'string', 'nullable' => true],
                'theme' => ['type' => 'string', 'nullable' => true],
            ],
        ];

        $bookShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'author' => ['type' => 'string', 'nullable' => true],
                'format' => ['type' => 'string', 'enum' => ['pdf', 'epub']],
                'totalPages' => ['type' => 'integer'],
                'totalWords' => ['type' => 'integer'],
                'originalFilename' => ['type' => 'string'],
                'uploadedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $progressShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'nullable' => true],
                'pageNumber' => ['type' => 'integer'],
                'wordIndex' => ['type' => 'integer'],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
        ];

        $followShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'isPrivate' => ['type' => 'boolean'],
                'avatarUrl' => ['type' => 'string', 'nullable' => true],
                'followedSince' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $followStatusShape = [
            'type' => 'object',
            'properties' => [
                'following' => ['type' => 'string', 'description' => 'none | pending | accepted'],
                'followId' => ['type' => 'integer', 'nullable' => true],
                'followedBy' => ['type' => 'boolean'],
            ],
        ];

        $purchaseShape = [
            'type' => 'object',
            'properties' => [
                'itemType' => ['type' => 'string'],
                'itemId' => ['type' => 'string'],
                'purchasedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $storeFontShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'displayName' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'originalFilename' => ['type' => 'string'],
                'format' => ['type' => 'string'],
                'uploadedAt' => ['type' => 'string', 'format' => 'date-time'],
                'purchased' => ['type' => 'boolean'],
                'isActive' => ['type' => 'boolean'],
            ],
        ];

        $badgeShape = [
            'type' => 'object',
            'properties' => [
                'badgeId' => ['type' => 'string'],
                'earnedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $notificationShape = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'type' => ['type' => 'string'],
                'actor' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ]],
                'referenceId' => ['type' => 'integer', 'nullable' => true],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $feedItemShape = [
            'type' => 'object',
            'properties' => [
                'user' => ['type' => 'object'],
                'book' => ['type' => 'object'],
                'progress' => ['type' => 'object'],
            ],
        ];

        $idPathParam = fn (string $name = 'id') => [
            'name' => $name, 'in' => 'path', 'required' => true,
            'schema' => ['type' => 'integer'],
        ];

        return [
            // ---- Auth ----
            [
                'path' => '/api/login', 'method' => 'POST', 'tag' => 'Auth',
                'operationId' => 'login', 'public' => true,
                'summary' => 'Authenticate and receive a JWT token',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'required' => ['username', 'password'],
                    'properties' => [
                        'username' => ['type' => 'string'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                    ],
                ])],
                'responses' => [
                    200 => ['description' => 'Token issued', 'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'user' => $userShape,
                            'token' => ['type' => 'string'],
                        ],
                    ]],
                    401 => ['description' => 'Invalid credentials'],
                ],
            ],
            [
                'path' => '/api/register', 'method' => 'POST', 'tag' => 'Auth',
                'operationId' => 'register', 'public' => true,
                'summary' => 'Register a new user',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'required' => ['name', 'password'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                    ],
                ])],
                'responses' => [
                    201 => ['description' => 'User created', 'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'user' => $userShape,
                            'token' => ['type' => 'string'],
                        ],
                    ]],
                    409 => ['description' => 'Name already taken'],
                ],
            ],

            // ---- Me ----
            [
                'path' => '/api/me/stats', 'method' => 'GET', 'tag' => 'Me',
                'operationId' => 'meStats',
                'summary' => 'Get aggregate stats for the authenticated user',
                'responses' => [200 => ['description' => 'Stats', 'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'followersCount' => ['type' => 'integer'],
                        'followingCount' => ['type' => 'integer'],
                        'booksCount' => ['type' => 'integer'],
                        'completedCount' => ['type' => 'integer'],
                        'readToday' => ['type' => 'boolean'],
                    ],
                ]]],
            ],
            [
                'path' => '/api/me/preferences', 'method' => 'PATCH', 'tag' => 'Me',
                'operationId' => 'meUpdatePreferences',
                'summary' => 'Update font/theme preferences',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'properties' => [
                        'font' => ['type' => 'string', 'nullable' => true],
                        'theme' => ['type' => 'string', 'nullable' => true],
                    ],
                ])],
                'responses' => [200 => ['description' => 'Updated user', 'schema' => $userShape]],
            ],
            [
                'path' => '/api/me/bio', 'method' => 'PATCH', 'tag' => 'Me',
                'operationId' => 'meUpdateBio',
                'summary' => 'Update the authenticated user\'s bio',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'properties' => ['bio' => ['type' => 'string', 'nullable' => true]],
                ])],
                'responses' => [200 => ['description' => 'Updated user', 'schema' => $userShape]],
            ],
            [
                'path' => '/api/me/privacy', 'method' => 'PATCH', 'tag' => 'Me',
                'operationId' => 'meUpdatePrivacy',
                'summary' => 'Toggle the private-profile flag',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'required' => ['isPrivate'],
                    'properties' => ['isPrivate' => ['type' => 'boolean']],
                ])],
                'responses' => [
                    200 => ['description' => 'Updated user', 'schema' => $userShape],
                    400 => ['description' => 'isPrivate field required'],
                ],
            ],
            [
                'path' => '/api/me/avatar', 'method' => 'POST', 'tag' => 'Me',
                'operationId' => 'meUploadAvatar',
                'summary' => 'Upload an avatar image (jpg, png, gif, webp)',
                'requestBody' => ['content' => $multipartFile],
                'responses' => [
                    200 => ['description' => 'Updated user', 'schema' => $userShape],
                    400 => ['description' => 'Invalid file'],
                ],
            ],
            [
                'path' => '/api/me/avatar', 'method' => 'DELETE', 'tag' => 'Me',
                'operationId' => 'meDeleteAvatar',
                'summary' => 'Remove the current avatar',
                'responses' => [200 => ['description' => 'Updated user', 'schema' => $userShape]],
            ],

            // ---- Users ----
            [
                'path' => '/api/users', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'usersSearch',
                'summary' => 'Search users by name (max 20 results)',
                'parameters' => [[
                    'name' => 'name', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => 'Case-insensitive substring match',
                ]],
                'responses' => [200 => ['description' => 'Matching users', 'schema' => [
                    'type' => 'array', 'items' => $userShape,
                ]]],
            ],
            [
                'path' => '/api/users/{id}', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'userShow',
                'summary' => 'Get a user\'s public profile',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'User profile', 'schema' => array_merge_recursive($userShape, [
                        'properties' => ['followStatus' => $followStatusShape],
                    ])],
                    404 => ['description' => 'User not found'],
                ],
            ],
            [
                'path' => '/api/users/{id}/books', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'userBooks',
                'summary' => 'List books owned by a user (visibility-gated)',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'Books', 'schema' => ['type' => 'array', 'items' => $bookShape]],
                    403 => ['description' => 'Not authorized to view this user\'s books'],
                ],
            ],
            [
                'path' => '/api/users/{id}/progress/{bookId}', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'userProgress',
                'summary' => 'Get another user\'s progress on a book',
                'parameters' => [$idPathParam(), $idPathParam('bookId')],
                'responses' => [200 => ['description' => 'Progress', 'schema' => $progressShape]],
            ],
            [
                'path' => '/api/users/{id}/badges', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'userBadges',
                'summary' => 'List badges earned by a user (visibility-gated)',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'Badges', 'schema' => ['type' => 'array', 'items' => $badgeShape]],
                    403 => ['description' => 'Not authorized'],
                ],
            ],
            [
                'path' => '/api/users/{id}/avatar', 'method' => 'GET', 'tag' => 'Users',
                'operationId' => 'userAvatar', 'public' => true,
                'summary' => 'Download a user\'s avatar image',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'Image bytes', 'contentType' => 'image/*'],
                    404 => ['description' => 'No avatar set'],
                ],
            ],

            // ---- Follows ----
            [
                'path' => '/api/follow/{userId}', 'method' => 'POST', 'tag' => 'Follows',
                'operationId' => 'follow',
                'summary' => 'Follow a user (or send a request if private)',
                'parameters' => [$idPathParam('userId')],
                'responses' => [200 => ['description' => 'Follow created or requested', 'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'accepted | pending'],
                        'followId' => ['type' => 'integer'],
                    ],
                ]]],
            ],
            [
                'path' => '/api/follow/{userId}', 'method' => 'DELETE', 'tag' => 'Follows',
                'operationId' => 'unfollow',
                'summary' => 'Unfollow a user or cancel a pending request',
                'parameters' => [$idPathParam('userId')],
                'responses' => [200 => ['description' => 'Removed', 'schema' => [
                    'type' => 'object', 'properties' => ['status' => ['type' => 'string']],
                ]]],
            ],
            [
                'path' => '/api/follows/{id}/accept', 'method' => 'POST', 'tag' => 'Follows',
                'operationId' => 'acceptFollow',
                'summary' => 'Accept a pending follow request',
                'parameters' => [$idPathParam()],
                'responses' => [200 => ['description' => 'Accepted', 'schema' => [
                    'type' => 'object', 'properties' => ['status' => ['type' => 'string']],
                ]]],
            ],
            [
                'path' => '/api/follows/{id}/deny', 'method' => 'POST', 'tag' => 'Follows',
                'operationId' => 'denyFollow',
                'summary' => 'Deny a pending follow request',
                'parameters' => [$idPathParam()],
                'responses' => [200 => ['description' => 'Denied', 'schema' => [
                    'type' => 'object', 'properties' => ['status' => ['type' => 'string']],
                ]]],
            ],
            [
                'path' => '/api/follow/{userId}/status', 'method' => 'GET', 'tag' => 'Follows',
                'operationId' => 'followStatus',
                'summary' => 'Get follow status with another user',
                'parameters' => [$idPathParam('userId')],
                'responses' => [200 => ['description' => 'Status', 'schema' => $followStatusShape]],
            ],
            [
                'path' => '/api/me/followers', 'method' => 'GET', 'tag' => 'Follows',
                'operationId' => 'myFollowers',
                'summary' => 'Paginated list of followers (20 per page)',
                'parameters' => [[
                    'name' => 'page', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                ]],
                'responses' => [200 => ['description' => 'Followers', 'schema' => [
                    'type' => 'array', 'items' => $followShape,
                ]]],
            ],
            [
                'path' => '/api/me/following', 'method' => 'GET', 'tag' => 'Follows',
                'operationId' => 'myFollowing',
                'summary' => 'Paginated list of users I follow (20 per page)',
                'parameters' => [[
                    'name' => 'page', 'in' => 'query', 'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                ]],
                'responses' => [200 => ['description' => 'Following', 'schema' => [
                    'type' => 'array', 'items' => $followShape,
                ]]],
            ],
            [
                'path' => '/api/friends', 'method' => 'GET', 'tag' => 'Follows',
                'operationId' => 'friends',
                'summary' => 'First 50 mutual or one-way follows',
                'responses' => [200 => ['description' => 'Friends', 'schema' => [
                    'type' => 'array', 'items' => $followShape,
                ]]],
            ],

            // ---- Feed ----
            [
                'path' => '/api/feed', 'method' => 'GET', 'tag' => 'Feed',
                'operationId' => 'feed',
                'summary' => 'Reading activity from mutual-follow users (one recent book each)',
                'responses' => [200 => ['description' => 'Feed', 'schema' => [
                    'type' => 'array', 'items' => $feedItemShape,
                ]]],
            ],

            // ---- Badges ----
            [
                'path' => '/api/badges', 'method' => 'POST', 'tag' => 'Badges',
                'operationId' => 'awardBadge',
                'summary' => 'Award a badge to the authenticated user (idempotent)',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'required' => ['badgeId'],
                    'properties' => [
                        'badgeId' => ['type' => 'string'],
                        'earnedAt' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ])],
                'responses' => [200 => ['description' => 'Badge', 'schema' => $badgeShape]],
            ],

            // ---- Notifications ----
            [
                'path' => '/api/notifications', 'method' => 'GET', 'tag' => 'Notifications',
                'operationId' => 'listNotifications',
                'summary' => 'List unread notifications (max 50)',
                'responses' => [200 => ['description' => 'Notifications', 'schema' => [
                    'type' => 'array', 'items' => $notificationShape,
                ]]],
            ],
            [
                'path' => '/api/notifications/{id}', 'method' => 'DELETE', 'tag' => 'Notifications',
                'operationId' => 'dismissNotification',
                'summary' => 'Dismiss a notification',
                'parameters' => [$idPathParam()],
                'responses' => [200 => ['description' => 'Dismissed', 'schema' => [
                    'type' => 'object', 'properties' => ['status' => ['type' => 'string']],
                ]]],
            ],

            // ---- Global Books ----
            [
                'path' => '/api/global-books', 'method' => 'GET', 'tag' => 'Global Books',
                'operationId' => 'listGlobalBooks',
                'summary' => 'List all books in the global library',
                'responses' => [200 => ['description' => 'Global books', 'schema' => [
                    'type' => 'array', 'items' => $bookShape,
                ]]],
            ],
            [
                'path' => '/api/global-books/{id}/claim', 'method' => 'POST', 'tag' => 'Global Books',
                'operationId' => 'claimGlobalBook',
                'summary' => 'Add a global book to the user\'s personal library',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'Claimed book', 'schema' => $bookShape],
                    404 => ['description' => 'Global book not found'],
                ],
            ],
            [
                'path' => '/api/admin/global-books', 'method' => 'POST', 'tag' => 'Global Books (Admin)',
                'operationId' => 'adminUploadGlobalBook',
                'summary' => 'Upload a PDF or EPUB to the global library',
                'description' => 'Requires ROLE_ADMIN',
                'requestBody' => ['content' => $multipartFile],
                'responses' => [201 => ['description' => 'Uploaded', 'schema' => $bookShape]],
            ],
            [
                'path' => '/api/admin/push-book', 'method' => 'POST', 'tag' => 'Global Books (Admin)',
                'operationId' => 'adminPushBook',
                'summary' => 'Push a book to every user who doesn\'t already have it',
                'description' => 'Requires ROLE_ADMIN',
                'requestBody' => ['content' => $multipartFile],
                'responses' => [200 => ['description' => 'Pushed', 'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'author' => ['type' => 'string', 'nullable' => true],
                        'pushedToUsers' => ['type' => 'integer'],
                    ],
                ]]],
            ],
            [
                'path' => '/api/admin/global-books/{id}', 'method' => 'DELETE', 'tag' => 'Global Books (Admin)',
                'operationId' => 'adminDeleteGlobalBook',
                'summary' => 'Delete a book from the global library',
                'description' => 'Requires ROLE_ADMIN',
                'parameters' => [$idPathParam()],
                'responses' => [204 => ['description' => 'Deleted']],
            ],

            // ---- Store ----
            [
                'path' => '/api/store/fonts', 'method' => 'GET', 'tag' => 'Store',
                'operationId' => 'storeListFonts',
                'summary' => 'List fonts available in the store',
                'responses' => [200 => ['description' => 'Fonts', 'schema' => [
                    'type' => 'array', 'items' => $storeFontShape,
                ]]],
            ],
            [
                'path' => '/api/store/theme-configs', 'method' => 'GET', 'tag' => 'Store',
                'operationId' => 'storeListThemeConfigs', 'public' => true,
                'summary' => 'Active/inactive status of theme configs',
                'responses' => [200 => ['description' => 'Theme statuses', 'schema' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'boolean'],
                ]]],
            ],
            [
                'path' => '/api/store/purchases', 'method' => 'GET', 'tag' => 'Store',
                'operationId' => 'storeListPurchases',
                'summary' => 'List my purchases',
                'responses' => [200 => ['description' => 'Purchases', 'schema' => [
                    'type' => 'array', 'items' => $purchaseShape,
                ]]],
            ],
            [
                'path' => '/api/store/purchase', 'method' => 'POST', 'tag' => 'Store',
                'operationId' => 'storePurchase',
                'summary' => 'Purchase a theme or font',
                'requestBody' => ['content' => $jsonObject([
                    'type' => 'object',
                    'required' => ['itemType', 'itemId'],
                    'properties' => [
                        'itemType' => ['type' => 'string', 'enum' => ['theme', 'font']],
                        'itemId' => ['type' => 'string'],
                    ],
                ])],
                'responses' => [
                    201 => ['description' => 'Purchase recorded', 'schema' => $purchaseShape],
                    400 => ['description' => 'Invalid item'],
                ],
            ],
            [
                'path' => '/api/store/fonts/{id}/file', 'method' => 'GET', 'tag' => 'Store',
                'operationId' => 'storeFontFile',
                'summary' => 'Download a store font file',
                'parameters' => [$idPathParam()],
                'responses' => [
                    200 => ['description' => 'Font bytes', 'contentType' => 'application/octet-stream'],
                    403 => ['description' => 'Not purchased'],
                    404 => ['description' => 'Font not found'],
                ],
            ],
            [
                'path' => '/api/admin/store/themes/{themeId}/toggle', 'method' => 'POST', 'tag' => 'Store (Admin)',
                'operationId' => 'adminToggleTheme',
                'summary' => 'Toggle a theme\'s active status',
                'description' => 'Requires ROLE_ADMIN',
                'parameters' => [[
                    'name' => 'themeId', 'in' => 'path', 'required' => true,
                    'schema' => ['type' => 'string'],
                ]],
                'responses' => [200 => ['description' => 'Toggled', 'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'themeId' => ['type' => 'string'],
                        'isActive' => ['type' => 'boolean'],
                    ],
                ]]],
            ],
            [
                'path' => '/api/admin/store/fonts', 'method' => 'POST', 'tag' => 'Store (Admin)',
                'operationId' => 'adminUploadStoreFont',
                'summary' => 'Upload a font to the store',
                'description' => 'Requires ROLE_ADMIN',
                'requestBody' => ['content' => ['multipart/form-data' => ['schema' => [
                    'type' => 'object',
                    'required' => ['file', 'displayName', 'category'],
                    'properties' => [
                        'file' => ['type' => 'string', 'format' => 'binary'],
                        'displayName' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'enum' => ['cartoon', 'techno', 'classic']],
                    ],
                ]]]],
                'responses' => [201 => ['description' => 'Uploaded', 'schema' => $storeFontShape]],
            ],
            [
                'path' => '/api/admin/store/fonts/{id}/hide', 'method' => 'POST', 'tag' => 'Store (Admin)',
                'operationId' => 'adminHideStoreFont',
                'summary' => 'Hide a store font from public listings',
                'description' => 'Requires ROLE_ADMIN',
                'parameters' => [$idPathParam()],
                'responses' => [200 => ['description' => 'Hidden', 'schema' => $storeFontShape]],
            ],
            [
                'path' => '/api/admin/store/fonts/{id}/restore', 'method' => 'POST', 'tag' => 'Store (Admin)',
                'operationId' => 'adminRestoreStoreFont',
                'summary' => 'Restore a hidden store font',
                'description' => 'Requires ROLE_ADMIN',
                'parameters' => [$idPathParam()],
                'responses' => [200 => ['description' => 'Restored', 'schema' => $storeFontShape]],
            ],
        ];
    }
}
