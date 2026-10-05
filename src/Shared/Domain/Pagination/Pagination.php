<?php

declare(strict_types=1);

namespace App\Shared\Domain\Pagination;

/**
 * Where a page sits in the whole result: what a paginator needs to render.
 */
final readonly class Pagination
{
    public function __construct(
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->pages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    /**
     * Asked for a page that no longer exists (e.g. after narrowing the filters).
     */
    public function isPastTheEnd(): bool
    {
        return $this->page > $this->pages();
    }

    /** 1-based position of the first item shown, 0 when there are none. */
    public function firstItem(): int
    {
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastItem(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }
}
