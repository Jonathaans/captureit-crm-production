<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Models\WorkflowNotification;

class WorkflowNotificationService
{
    public function notifyUser(
        int $userId,
        string $type,
        string $title,
        ?string $message,
        ?string $actionUrl,
        string $dedupeKey,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $meta = []
    ): ?WorkflowNotification {
        if ($userId < 1) {
            return null;
        }

        return WorkflowNotification::query()
            ->firstOrCreate(
                [
                    'user_id' =>
                        $userId,

                    'dedupe_key' =>
                        $dedupeKey,
                ],
                [
                    'type' =>
                        $type,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'action_url' =>
                        $actionUrl,

                    'source_type' =>
                        $sourceType,

                    'source_id' =>
                        $sourceId,

                    'meta' =>
                        $meta ?: null,
                ]
            );
    }

    public function notifyUsers(
        iterable $userIds,
        string $type,
        string $title,
        ?string $message,
        ?string $actionUrl,
        string $dedupeBase,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $meta = []
    ): int {
        $count = 0;

        foreach ($userIds as $userId) {
            $notification =
                $this->notifyUser(
                    (int) $userId,
                    $type,
                    $title,
                    $message,
                    $actionUrl,
                    $dedupeBase,
                    $sourceType,
                    $sourceId,
                    $meta
                );

            if ($notification) {
                $count++;
            }
        }

        return $count;
    }

    public function usersByRoleNames(
        array $roleNames
    ): Collection {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('roles')
        ) {
            return collect();
        }

        $normalized =
            collect($roleNames)
                ->map(
                    fn ($name) =>
                        strtolower(
                            trim(
                                (string) $name
                            )
                        )
                )
                ->filter()
                ->values();

        if ($normalized->isEmpty()) {
            return collect();
        }

        $query =
            DB::table('users')
                ->join(
                    'roles',
                    'roles.id',
                    '=',
                    'users.role_id'
                )
                ->whereIn(
                    DB::raw(
                        'LOWER(TRIM(roles.name))'
                    ),
                    $normalized->all()
                )
                ->select(
                    'users.id'
                );

        if (
            Schema::hasColumn(
                'users',
                'status'
            )
        ) {
            $query->where(
                'users.status',
                1
            );
        }

        return $query
            ->pluck('users.id')
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();
    }
}
