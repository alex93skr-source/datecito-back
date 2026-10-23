<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\ReorderPhotosRequest;
use App\Http\Requests\Me\StorePhotoRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PhotoController extends Controller
{
    public function __construct(private PhotoService $photos) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PhotoResource::collection($request->user()->photos);
    }

    public function store(StorePhotoRequest $request): PhotoResource
    {
        return PhotoResource::make($this->photos->store($request->user(), $request->file('photo')));
    }

    public function destroy(Photo $photo): Response
    {
        Gate::authorize('delete', $photo);
        $this->photos->delete($photo);

        return response()->noContent();
    }

    public function reorder(ReorderPhotosRequest $request): AnonymousResourceCollection
    {
        $this->photos->reorder($request->user(), $request->input('ids'));

        return PhotoResource::collection($request->user()->photos()->get());
    }
}
