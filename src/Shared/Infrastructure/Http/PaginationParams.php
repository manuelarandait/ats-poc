<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Pagination\PageRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Paging as it travels in the query string (`page`, `perPage`): only the page
 * sizes the UI offers are accepted, anything else falls back to the default.
 */
final readonly class PaginationParams
{
    /**
     * @param list<int> $perPageOptions
     */
    private function __construct(
        public int $page,
        public int $perPage,
        public array $perPageOptions,
        private int $defaultPerPage,
    ) {
    }

    /**
     * @param list<int> $perPageOptions
     */
    public static function fromRequest(Request $request, array $perPageOptions, int $defaultPerPage = PageRequest::DEFAULT_PER_PAGE): self
    {
        $perPage = $request->query->getInt('perPage', $defaultPerPage);

        return new self(
            $request->query->getInt('page', 1),
            \in_array($perPage, $perPageOptions, true) ? $perPage : $defaultPerPage,
            $perPageOptions,
            $defaultPerPage,
        );
    }

    /**
     * What every page link must keep; the page itself is set by each link,
     * and the default page size is left out of the URL.
     *
     * @return array{perPage?: int}
     */
    public function linkParams(): array
    {
        return $this->defaultPerPage === $this->perPage ? [] : ['perPage' => $this->perPage];
    }
}
