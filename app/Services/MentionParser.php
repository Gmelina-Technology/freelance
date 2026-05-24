<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MentionParser
{
    /**
     * Extract mentioned user IDs from HTML content.
     *
     * @return array<int, int>
     */
    public function extractIds(string $html): array
    {
        preg_match_all(
            '/<span[^>]+data-type="mention"[^>]+data-id="(\d+)"[^>]*>/i',
            $html,
            $matches,
        );

        return array_map('intval', array_unique($matches[1]));
    }

    /**
     * Resolve mentioned users from HTML content.
     *
     * @return Collection<int, User>
     */
    public function extract(string $html): Collection
    {
        $ids = $this->extractIds($html);

        if (empty($ids)) {
            return collect();
        }

        return User::whereIn('id', $ids)->get();
    }
}
