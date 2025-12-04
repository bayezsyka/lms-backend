<?php

namespace App\Http\Controllers;

use App\Models\CourseInstance;
use App\Models\Material;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    /**
     * List semua material di sebuah section.
     */
    public function index(Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $section->load('materials');

        return response()->json([
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
                'course_instance' => [
                    'id' => $courseInstance->id,
                    'class_name' => $courseInstance->class_name,
                    'semester' => $courseInstance->semester,
                ],
            ],
            'materials' => $section->materials->map(function (Material $material) {
                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'type' => $material->type,
                    'file_path' => $material->file_path,
                    'url' => $material->url,
                    'subject' => $material->subject,
                    'created_at' => $material->created_at,
                    'updated_at' => $material->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Simpan material baru di sebuah section.
     * Mendukung type: file / link.
     */
    public function store(Request $request, Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:file,link'],
            'subject' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', 'max:51200'], // max 50 MB
        ]);

        $type = $data['type'];

        $filePath = null;
        $url = null;

        if ($type === 'file') {
            if (! $request->hasFile('file')) {
                return response()->json([
                    'message' => 'File harus diunggah untuk type=file.',
                    'errors' => [
                        'file' => ['File tidak ditemukan pada request.'],
                    ],
                ], 422);
            }

            $uploadedFile = $request->file('file');

            // Simpan ke storage: public/materials/{course}/{section}
            $filePath = $uploadedFile->store(
                'materials/' . $courseInstance->id . '/section-' . $section->id,
                'public'
            );
        } elseif ($type === 'link') {
            if (empty($data['url'])) {
                return response()->json([
                    'message' => 'URL wajib diisi untuk type=link.',
                    'errors' => [
                        'url' => ['URL tidak boleh kosong untuk type=link.'],
                    ],
                ], 422);
            }

            $url = $data['url'];
        }

        $material = Material::create([
            'section_id' => $section->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'file_path' => $filePath,
            'url' => $url,
            'subject' => $data['subject'] ?? null,
        ]);

        return response()->json([
            'message' => 'Material berhasil dibuat.',
            'data' => $material,
        ], 201);
    }

    /**
     * Detail satu material.
     */
    public function show(Material $material): JsonResponse
    {
        $section = $material->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        return response()->json([
            'material' => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'type' => $material->type,
                'file_path' => $material->file_path,
                'url' => $material->url,
                'subject' => $material->subject,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at,
            ],
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
            ],
            'course_instance' => [
                'id' => $courseInstance->id,
                'class_name' => $courseInstance->class_name,
                'semester' => $courseInstance->semester,
            ],
        ]);
    }

    /**
     * Update material.
     * Bisa update title, description, subject, type, url, dan file.
     */
    public function update(Request $request, Material $material): JsonResponse
    {
        $section = $material->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', 'in:file,link'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:100'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'file' => ['sometimes', 'file', 'max:51200'],
        ]);

        // Update field sederhana
        if (array_key_exists('title', $data)) {
            $material->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $material->description = $data['description'];
        }
        if (array_key_exists('subject', $data)) {
            $material->subject = $data['subject'];
        }

        // Tentukan type baru (jika diubah)
        $newType = $data['type'] ?? $material->type;

        // Handle perubahan berdasarkan type
        if ($newType === 'file') {
            // Kalau upload file baru
            if ($request->hasFile('file')) {
                // Hapus file lama kalau ada
                if ($material->file_path) {
                    Storage::disk('public')->delete($material->file_path);
                }

                $uploadedFile = $request->file('file');
                $filePath = $uploadedFile->store(
                    'materials/' . $courseInstance->id . '/section-' . $section->id,
                    'public'
                );

                $material->file_path = $filePath;
            }

            // Type file → URL tidak dipakai
            $material->url = null;
        } elseif ($newType === 'link') {
            // Wajib punya URL (bisa dari request, atau kalau nggak ada → error)
            $url = $data['url'] ?? $material->url;

            if (empty($url)) {
                return response()->json([
                    'message' => 'URL wajib diisi untuk type=link.',
                    'errors' => [
                        'url' => ['URL tidak boleh kosong untuk type=link.'],
                    ],
                ], 422);
            }

            // Kalau sebelumnya type file & ada file, boleh kita hapus file lama
            if ($material->type === 'file' && $material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }

            $material->url = $url;
            $material->file_path = null;
        }

        $material->type = $newType;
        $material->save();

        return response()->json([
            'message' => 'Material berhasil diperbarui.',
            'data' => $material,
        ]);
    }

    /**
     * Hapus material.
     * Jika type=file, file di storage juga dihapus.
     */
    public function destroy(Material $material): JsonResponse
    {
        $section = $material->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        // Hapus file fisik jika ada
        if ($material->type === 'file' && $material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json([
            'message' => 'Material berhasil dihapus.',
        ], 200);
    }

    /**
     * Endpoint untuk mendapatkan URL file yang bisa diakses frontend.
     */
    public function fileUrl(Material $material): JsonResponse
    {
        if ($material->type !== 'file' || ! $material->file_path) {
            return response()->json([
                'message' => 'Material ini bukan tipe file atau file_path kosong.',
            ], 422);
        }

        if (! Storage::disk('public')->exists($material->file_path)) {
            return response()->json([
                'message' => 'File tidak ditemukan di storage.',
            ], 404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        $url = $disk->url($material->file_path);

        return response()->json([
            'material_id' => $material->id,
            'download_url' => $url,
        ]);
    }

    /**
     * Cek apakah user boleh mengelola materi di kelas ini.
     * - superadmin: boleh semua kelas
     * - dosen: hanya kelas yang dia ampu
     */
    protected function authorizeCourseInstance(CourseInstance $courseInstance): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->role === 'superadmin') {
            return;
        }

        if ($user->role === 'dosen' && (int) $courseInstance->lecturer_id === (int) $user->id) {
            return;
        }

        abort(403, 'Anda tidak berhak mengelola material pada kelas ini.');
    }
}
