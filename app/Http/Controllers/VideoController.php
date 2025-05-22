<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\SubtitleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class VideoController extends Controller
{
    protected $subtitleService;

    public function __construct(SubtitleService $subtitleService)
    {
        $this->subtitleService = $subtitleService;
    }

    public function index()
    {
        $videos = Video::latest()->paginate(50);
        return view('videos.index', compact('videos'));
    }

    public function scan(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $result = $this->subtitleService->scanDirectory($request->path);

            return response()->json([
                'success' => true,
                'message' => "Escaneo completado. Se encontraron {$result['found']} videos, de los cuales {$result['added']} necesitan subtítulos.",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error al escanear el directorio: " . $e->getMessage()
            ], 500);
        }
    }

    public function process(Video $video)
    {
        Log::info("Iniciando procesamiento del video: {$video->file_name}");

        try {
            $video->status = 'processing';
            $video->save();

            $result = $this->subtitleService->searchSubtitles($video);

            if ($result) {
                Log::info("Procesamiento completado exitosamente para: {$video->file_name}");
                return response()->json([
                    'success' => true,
                    'message' => "Subtítulo descargado exitosamente para {$video->file_name}",
                    'video' => $video->fresh()
                ]);
            } else {
                $video->status = 'failed';
                $video->save();
                Log::error("Error al procesar el video: {$video->file_name}");
                return response()->json([
                    'success' => false,
                    'message' => "No se pudo encontrar un subtítulo para {$video->file_name}",
                    'video' => $video->fresh()
                ]);
            }
        } catch (\Exception $e) {
            $video->status = 'failed';
            $video->save();
            Log::error("Error al procesar el video {$video->file_name}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Error al procesar {$video->file_name}: " . $e->getMessage(),
                'video' => $video->fresh()
            ], 500);
        }
    }

    public function getVideos(Request $request)
    {
        $sortColumn = $request->input('sort', 'file_name');
        $sortDirection = $request->input('direction', 'asc');

        // Validar la columna de ordenamiento
        $allowedColumns = ['file_name', 'language', 'status', 'created_at'];
        if (!in_array($sortColumn, $allowedColumns)) {
            $sortColumn = 'file_name';
        }

        // Validar la dirección de ordenamiento
        $sortDirection = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';

        $videos = Video::orderBy($sortColumn, $sortDirection)->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $videos
        ]);
    }
}
