<?php

declare(strict_types=1);

namespace App\Shared\Domain\Pagination;

/**
 * Which slice of a list to read (offset pagination), kept within bounds:
 * out-of-range numbers are clamped rather than rejected, it's presentation.
 */
final readonly class PageRequest
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 100;

    private function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    public static function of(int $page, int $perPage = self::DEFAULT_PER_PAGE): self
    {
        return new self(max(1, $page), min(max(1, $perPage), self::MAX_PER_PAGE));
    }

    public function onPage(int $page): self
    {
        return self::of($page, $this->perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function paginate(int $total): Pagination
    {
        return new Pagination($total, $this->page, $this->perPage);
    }
}
