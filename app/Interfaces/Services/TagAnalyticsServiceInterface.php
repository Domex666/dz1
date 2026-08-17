<?php

declare(strict_types=1);

namespace App\Interfaces\Services;

use App\DTO\Response\TagCountDto;
use Throwable;

interface TagAnalyticsServiceInterface
{
    /**
     * @return list<TagCountDto>
     * @throws Throwable
     */
    public function getTopTags(int $limit): array;
}
