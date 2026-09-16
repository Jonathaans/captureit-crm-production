<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Admin\Models\GoogleCalendarEvent;

class CalendarActivityBridgeService
{
    public function sync(
        GoogleCalendarEvent $event
    ): ?int {
        try {
            if (
                ! Schema::hasTable(
                    'activities'
                )
            ) {
                $event->update([
                    'activity_sync_error' =>
                        'Table activities tidak ditemukan.',
                ]);

                return null;
            }

            $columns =
                Schema::getColumnListing(
                    'activities'
                );

            $payload = [];

            $this->putIfColumn(
                $payload,
                $columns,
                'title',
                $event->title
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'type',
                'meeting'
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'location',
                $event->location
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'comment',
                $event->notes
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'description',
                $event->notes
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'schedule_from',
                $event->start_at
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'schedule_to',
                $event->end_at
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'start_at',
                $event->start_at
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'end_at',
                $event->end_at
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'is_done',
                0
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'user_id',
                $event->sales_owner_id
            );

            $this->putIfColumn(
                $payload,
                $columns,
                'created_by_id',
                auth()->id()
                    ?: $event->sales_owner_id
            );

            if (
                in_array(
                    'lead_id',
                    $columns,
                    true
                )
            ) {
                $payload['lead_id'] =
                    $event->lead_id;
            }

            $now = now();

            $this->putIfColumn(
                $payload,
                $columns,
                'updated_at',
                $now
            );

            if ($event->activity_id) {
                DB::table(
                    'activities'
                )
                    ->where(
                        'id',
                        $event->activity_id
                    )
                    ->update(
                        $payload
                    );

                $event->update([
                    'activity_sync_error' =>
                        null,
                ]);

                return (int) $event
                    ->activity_id;
            }

            $this->putIfColumn(
                $payload,
                $columns,
                'created_at',
                $now
            );

            $unsupported =
                $this->requiredColumnsNotFilled(
                    $payload
                );

            if ($unsupported) {
                $message =
                    'Activity bridge skip. Required columns belum dikenali: '
                    .implode(
                        ', ',
                        $unsupported
                    );

                $event->update([
                    'activity_sync_error' =>
                        $message,
                ]);

                return null;
            }

            $activityId =
                (int) DB::table(
                    'activities'
                )
                    ->insertGetId(
                        $payload
                    );

            $linked =
                in_array(
                    'lead_id',
                    $columns,
                    true
                )
                || $this->linkPivot(
                    $activityId,
                    $event->lead_id
                );

            $event->update([
                'activity_id' =>
                    $activityId,

                'activity_sync_error' =>
                    $linked
                        ? null
                        : 'Activity dibuat, tetapi pivot Lead-Activity tidak ditemukan.',
            ]);

            return $activityId;
        } catch (Throwable $exception) {
            $event->update([
                'activity_sync_error' =>
                    $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function putIfColumn(
        array &$payload,
        array $columns,
        string $column,
        mixed $value
    ): void {
        if (
            in_array(
                $column,
                $columns,
                true
            )
        ) {
            $payload[$column] =
                $value;
        }
    }

    private function requiredColumnsNotFilled(
        array $payload
    ): array {
        $database =
            DB::getDatabaseName();

        $rows =
            DB::table(
                'information_schema.COLUMNS'
            )
                ->select([
                    'COLUMN_NAME',
                    'IS_NULLABLE',
                    'COLUMN_DEFAULT',
                    'EXTRA',
                ])
                ->where(
                    'TABLE_SCHEMA',
                    $database
                )
                ->where(
                    'TABLE_NAME',
                    'activities'
                )
                ->get();

        $missing = [];

        foreach ($rows as $row) {
            $column =
                $row->COLUMN_NAME;

            if (
                $column === 'id'
                || str_contains(
                    strtolower(
                        (string) $row->EXTRA
                    ),
                    'auto_increment'
                )
            ) {
                continue;
            }

            if (
                $row->IS_NULLABLE === 'NO'
                && $row->COLUMN_DEFAULT === null
                && ! array_key_exists(
                    $column,
                    $payload
                )
            ) {
                $missing[] =
                    $column;
            }
        }

        return $missing;
    }

    private function linkPivot(
        int $activityId,
        int $leadId
    ): bool {
        $database =
            DB::getDatabaseName();

        $tables =
            DB::table(
                'information_schema.COLUMNS'
            )
                ->select(
                    'TABLE_NAME'
                )
                ->where(
                    'TABLE_SCHEMA',
                    $database
                )
                ->whereIn(
                    'COLUMN_NAME',
                    [
                        'activity_id',
                        'lead_id',
                    ]
                )
                ->whereNotIn(
                    'TABLE_NAME',
                    [
                        // CALENDAR_ACTIVITY_BRIDGE_EXCLUDE_SELF_TABLE_V1
                        'google_calendar_events',
                    ]
                )
                ->groupBy(
                    'TABLE_NAME'
                )
                ->havingRaw(
                    'COUNT(DISTINCT COLUMN_NAME) = 2'
                )
                ->pluck(
                    'TABLE_NAME'
                );

        foreach ($tables as $table) {
            if ($table === 'activities') {
                continue;
            }

            $columns =
                Schema::getColumnListing(
                    $table
                );

            $payload = [
                'activity_id' =>
                    $activityId,

                'lead_id' =>
                    $leadId,
            ];

            if (
                in_array(
                    'created_at',
                    $columns,
                    true
                )
            ) {
                $payload['created_at'] =
                    now();
            }

            if (
                in_array(
                    'updated_at',
                    $columns,
                    true
                )
            ) {
                $payload['updated_at'] =
                    now();
            }

            DB::table(
                $table
            )->updateOrInsert(
                [
                    'activity_id' =>
                        $activityId,

                    'lead_id' =>
                        $leadId,
                ],
                $payload
            );

            return true;
        }

        return false;
    }
}
