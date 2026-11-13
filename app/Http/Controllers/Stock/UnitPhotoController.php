<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\AddUnitPhotos;
use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Models\StockUnit;
use App\Models\StockUnitPhoto;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * Telefon rasmlari: kirimdan keyin har bir telefonni suratga olib qo'shish yoki o'chirish.
 * Web va mobil ilova bir xil amalni ishlatadi; xodim faqat o'z filialidagi telefonga tegadi.
 */
class UnitPhotoController extends Controller
{
    public function store(Request $request, StockUnit $unit, AddUnitPhotos $photos): RedirectResponse
    {
        $this->authorizeUnit($request, $unit);
        $photos->handle($unit, $this->files($request));
        Audit::log('stock_unit.photos_added', $unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rasm qo\'shildi.')]);

        return back();
    }

    public function destroy(Request $request, StockUnitPhoto $photo, AddUnitPhotos $photos): RedirectResponse
    {
        $this->authorizeUnit($request, $photo->unit);
        $photos->delete($photo);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Rasm o'chirildi.")]);

        return back();
    }

    public function storeViaApi(Request $request, StockUnit $unit, AddUnitPhotos $photos): JsonResponse
    {
        $this->authorizeUnit($request, $unit);
        $photos->handle($unit, $this->files($request));

        return response()->json($this->present($request, $unit), 201);
    }

    public function destroyViaApi(Request $request, StockUnitPhoto $photo, AddUnitPhotos $photos): JsonResponse
    {
        $unit = $photo->unit;
        $this->authorizeUnit($request, $unit);
        $photos->delete($photo);

        return response()->json($this->present($request, $unit));
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.AddUnitPhotos::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], attributes: ['photos' => 'Rasmlar', 'photos.*' => 'Rasm']);

        return array_values((array) $request->file('photos'));
    }

    private function authorizeUnit(Request $request, StockUnit $unit): void
    {
        abort_unless($this->user($request)->canAccessBranch($unit->branch), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Request $request, StockUnit $unit): array
    {
        $unit->load(['product:id,name,image_path', 'branch:id,name', 'photos']);

        return StockPresenter::unit($unit, $this->user($request)->isOwner());
    }
}
