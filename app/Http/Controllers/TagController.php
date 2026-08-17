<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TopTagsRequest;
use App\Http\Resources\TagCountResource;
use App\Interfaces\Services\TagAnalyticsServiceInterface;
use App\Support\Http\Request;
use App\Support\Http\Response;
use Throwable;

final readonly class TagController extends Controller
{
    public function __construct(
        private TagAnalyticsServiceInterface $tagAnalyticsService,
        private TopTagsRequest $topTagsRequest,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function top(Request $request): Response
    {
        $tags = $this->tagAnalyticsService->getTopTags($this->topTagsRequest->toLimit($request));

        return $this->successResponse(['items' => TagCountResource::collection($tags)]);
    }
}
