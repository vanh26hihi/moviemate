<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiMovieContentService;
use Illuminate\Http\Request;

class AiContentController extends Controller
{
    public function __construct(
        protected AiMovieContentService $movieContentService
    ) {}

    public function movieContent()
    {
        return view('admin.ai.movie-content', [
            'input' => [],
            'result' => null,
            'meta' => null,
        ]);
    }

    public function movieContentStore(Request $request)
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'genres' => ['nullable', 'string', 'max:255'],
            'original_description' => ['nullable', 'string', 'max:3000'],
            'tone' => ['required', 'string', 'in:attractive,mysterious,professional,funny,emotional'],
        ], [
            'title.required' => 'Vui lòng nhập tên phim.',
            'tone.required' => 'Vui lòng chọn tone nội dung.',
        ]);

        $generated = $this->movieContentService->generate($input);

        return view('admin.ai.movie-content', [
            'input' => $input,
            'result' => $generated['content'],
            'meta' => $generated,
        ]);
    }
}
