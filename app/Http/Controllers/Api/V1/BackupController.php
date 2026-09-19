<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TZ 2, 23: bulutga zaxira nusxa va tiklash */
class BackupController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly BackupService $backups) {}

    /** GET /backups — zaxiralar ro'yxati (yangisi birinchi) */
    public function index(Request $request): JsonResponse
    {
        $items = Backup::forUser($request->user())->orderByDesc('id')->get();

        return $this->success([
            'count' => $items->count(),
            'items' => BackupResource::collection($items)->resolve(),
        ]);
    }

    /** POST /backups — yangi zaxira nusxa yaratish */
    public function store(Request $request): JsonResponse
    {
        $backup = $this->backups->create($request->user());

        return $this->success(new BackupResource($backup), __('messages.backup.created'), 201);
    }

    /** GET /backups/{id} — tiklash uchun to'liq payload (mobil lokal bazani tiklaydi) */
    public function show(Request $request, int $id): JsonResponse
    {
        $backup = Backup::forUser($request->user())->findOrFail($id);

        return $this->success([
            'backup' => new BackupResource($backup),
            'payload' => $this->backups->payload($backup),
        ]);
    }

    /** DELETE /backups/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $backup = Backup::forUser($request->user())->findOrFail($id);
        $this->backups->delete($backup);

        return $this->success(message: __('messages.backup.deleted'));
    }
}
