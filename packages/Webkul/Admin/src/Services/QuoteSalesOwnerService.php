<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class QuoteSalesOwnerService
{
    public const ALLOWED_ROLE_NAMES = [
        'administrator',
        'sales admin',
        'superadministrator',
        'sales user',
    ];

    public const SEARCH_LIMIT = 20;

    /**
     * Users eligible to become a Quote Sales Owner.
     *
     * On Edit, an existing legacy owner outside Sales roles is appended only
     * so an old Quote can be saved without silently changing its owner.
     * Any new owner selection still has to match an allowed active role.
     */
    public function options(?int $currentOwnerId = null): Collection
    {
        $users = $this->eligibleUsersQuery()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role_id',
            ])
            ->map(fn ($user) => (object) $this->formatOwner($user));

        if ($currentOwnerId && ! $users->contains('id', $currentOwnerId)) {
            $currentOwner = User::query()
                ->with('role')
                ->find($currentOwnerId);

            if ($currentOwner) {
                $users->prepend((object) $this->formatOwner($currentOwner, true));
            }
        }

        return $users->values();
    }

    /**
     * Search active users who may become a Quote Sales Owner.
     */
    public function search(string $searchTerm, int $limit = self::SEARCH_LIMIT): Collection
    {
        $searchTerm = trim($searchTerm);

        if (mb_strlen($searchTerm) < 2) {
            return collect();
        }

        $limit = min(max($limit, 1), self::SEARCH_LIMIT);

        return $this->eligibleUsersQuery()
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('email', 'like', '%'.$searchTerm.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'email',
                'role_id',
            ])
            ->map(fn ($user) => $this->formatOwner($user))
            ->values();
    }

    /**
     * Resolve the initial selection for Create/Edit without exposing every user.
     *
     * An existing owner outside the current eligibility rules may be displayed
     * only when it is the unchanged owner of the Quote being edited.
     */
    public function initialSelection(
        ?int $selectedOwnerId,
        ?int $existingOwnerId = null
    ): array {
        if (! $selectedOwnerId) {
            return [];
        }

        $owner = User::query()
            ->with('role')
            ->find($selectedOwnerId);

        if (! $owner) {
            return [];
        }

        $isEligible = $this->isEligible((int) $owner->id);
        $isUnchangedLegacyOwner = $existingOwnerId !== null
            && (int) $owner->id === $existingOwnerId;

        if (! $isEligible && ! $isUnchangedLegacyOwner) {
            return [];
        }

        return $this->formatOwner($owner, ! $isEligible);
    }

    public function isEligible(int $userId): bool
    {
        return $this->eligibleUsersQuery()
            ->whereKey($userId)
            ->exists();
    }

    public function roleSummary(): Collection
    {
        return $this->eligibleUsersQuery()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'role_id',
            ])
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => (string) ($user->role?->name ?? '-'),
            ]);
    }

    private function eligibleUsersQuery(): Builder
    {
        return User::query()
            ->with('role')
            ->where('status', true)
            ->whereHas('role', function (Builder $query) {
                $query->whereIn(
                    DB::raw('LOWER(TRIM(name))'),
                    self::ALLOWED_ROLE_NAMES
                );
            });
    }

    private function formatOwner(User $owner, bool $isLegacyCurrent = false): array
    {
        return [
            'id' => (int) $owner->id,
            'name' => (string) $owner->name,
            'email' => (string) ($owner->email ?? ''),
            'role_name' => (string) ($owner->role?->name ?? ''),
            'is_legacy_current' => $isLegacyCurrent,
        ];
    }
}
