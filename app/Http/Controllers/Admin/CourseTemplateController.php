<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseTemplate;
use Illuminate\Http\Request;

class CourseTemplateController extends Controller
{
    /**
     * List semua template mata kuliah.
     *
     * GET /api/admin/course-templates
     */
    public function index(Request $request)
    {
        // Versi sederhana dulu: ambil semua, tanpa filter.
        $templates = CourseTemplate::orderBy('code')->get();

        return response()->json($templates);
    }

    /**
     * Tambah template mata kuliah baru.
     *
     * POST /api/admin/course-templates
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:course_templates,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sks' => ['required', 'integer', 'min:1', 'max:9'],
            'semester_recommendation' => ['nullable', 'integer', 'min:1', 'max:14'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template = CourseTemplate::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sks' => $data['sks'],
            'semester_recommendation' => $data['semester_recommendation'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($template, 201);
    }

    /**
     * Detail template.
     *
     * GET /api/admin/course-templates/{id}
     */
    public function show(int $id)
    {
        $template = CourseTemplate::findOrFail($id);

        return response()->json($template);
    }

    /**
     * Update template.
     *
     * PUT /api/admin/course-templates/{id}
     */
    public function update(Request $request, int $id)
    {
        $template = CourseTemplate::findOrFail($id);

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:course_templates,code,' . $template->id],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sks' => ['sometimes', 'required', 'integer', 'min:1', 'max:9'],
            'semester_recommendation' => ['nullable', 'integer', 'min:1', 'max:14'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template->fill($data);
        $template->save();

        return response()->json($template);
    }

    /**
     * Hapus template.
     *
     * DELETE /api/admin/course-templates/{id}
     */
    public function destroy(int $id)
    {
        $template = CourseTemplate::findOrFail($id);

        $template->delete();

        return response()->json([
            'message' => 'Template berhasil dihapus.',
        ]);
    }
}
