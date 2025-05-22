<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SubtitleService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.opensubtitles.com/api/v1';
    protected $userAgent = 'SubtitleFinder v1.0';
    protected $token;

    public function __construct()
    {
        $this->apiKey = config('services.opensubtitles.api_key');
        $this->token = $this->getToken();
    }

    protected function getToken()
    {
        // Intentar obtener el token del cache
        $token = Cache::get('opensubtitles_token');

        if (!$token) {
            Log::info("Iniciando autenticación con OpenSubtitles");

            $loginData = [
                'username' => config('services.opensubtitles.username'),
                'password' => config('services.opensubtitles.password')
            ];

            $headers = [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => $this->userAgent
            ];

            Log::info("Datos de autenticación:", [
                'username' => $loginData['username'],
                'api_key' => $this->apiKey,
                'headers' => $headers
            ]);

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->post($this->baseUrl . '/login', $loginData);

                Log::info("Respuesta de autenticación:", [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->json()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if (!isset($responseData['token'])) {
                        Log::error("La respuesta no contiene el token", [
                            'response' => $responseData
                        ]);
                        throw new \Exception("La respuesta de OpenSubtitles no contiene el token");
                    }

                    $token = $responseData['token'];
                    // Guardar el token en cache por 24 horas
                    Cache::put('opensubtitles_token', $token, now()->addHours(24));

                    Log::info("Autenticación exitosa con OpenSubtitles", [
                        'token' => substr($token, 0, 10) . '...' // Solo mostramos parte del token por seguridad
                    ]);
                } else {
                    $errorDetails = [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'headers' => $response->headers(),
                        'request_data' => $loginData,
                        'request_headers' => $headers
                    ];

                    Log::error("Error en la autenticación con OpenSubtitles", $errorDetails);

                    // Mensaje de error más descriptivo
                    $errorMessage = "Error de autenticación con OpenSubtitles: ";
                    if ($response->status() === 401) {
                        $errorMessage .= "Credenciales inválidas";
                    } elseif ($response->status() === 429) {
                        $errorMessage .= "Límite de peticiones excedido";
                    } else {
                        $errorMessage .= "Código de estado: " . $response->status();
                    }

                    throw new \Exception($errorMessage);
                }
            } catch (\Exception $e) {
                Log::error("Excepción durante la autenticación con OpenSubtitles", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }

        return $token;
    }

    public function detectLanguage($filePath)
    {
        $message = "Detectando idioma para el archivo: {$filePath}";
        Log::info($message);

        try {
            // Ejecutar ffprobe para obtener información del audio con etiquetas de idioma
            $command = "ffprobe \"{$filePath}\" -show_entries stream=index:stream_tags=language -select_streams a -of compact=p=0:nk=1 2>&1";

            $message = "Ejecutando comando FFmpeg: {$command}";
            Log::info($message);

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Log del resultado completo de FFmpeg
            Log::info("Resultado de FFmpeg:", [
                'return_code' => $returnVar,
                'output' => $output,
                'command' => $command
            ]);

            // Procesar la salida para extraer el idioma
            $detectedLanguages = [];

            foreach ($output as $line) {
                if (preg_match('/^(\d+)\|([a-zA-Z]+)$/', $line, $matches)) {
                    $trackIndex = $matches[1];
                    $trackLanguage = strtolower($matches[2]);

                    // Ignorar si el idioma es "und" (undefined)
                    if ($trackLanguage === 'und') {
                        continue;
                    }

                    $detectedLanguages[] = [
                        'track' => $trackIndex,
                        'language' => $trackLanguage
                    ];
                }
            }

            $languageDetected = !empty($detectedLanguages);
            $language = $languageDetected ? $detectedLanguages[0]['language'] : null;

            // Normalizar el código de idioma si existe
            if ($language && strlen($language) > 2) {
                $language = substr($language, 0, 2);
            }

            $message = "Idioma detectado: " . ($language ?? 'No detectado');
            Log::info($message, [
                'detected_languages' => $detectedLanguages,
                'raw_output' => $output
            ]);

            return [
                'language' => $language,
                'detected' => $languageDetected,
                'ffmpeg_output' => $output,
                'ffmpeg_return_code' => $returnVar,
                'all_languages' => $detectedLanguages
            ];

        } catch (\Exception $e) {
            $errorDetails = [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ];

            $message = "Error al detectar el idioma";
            Log::error($message, $errorDetails);

            return [
                'language' => null,
                'detected' => false,
                'ffmpeg_output' => [],
                'ffmpeg_return_code' => -1,
                'error' => $e->getMessage()
            ];
        }
    }

    public function detectContentType($fileName)
    {
        $message = "Detectando tipo de contenido para: {$fileName}";
        Log::info($message);

        // Patrones comunes para series de TV
        $tvPatterns = [
            '/S(\d{1,2})E(\d{1,2})/i',           // S01E02
            '/(\d{1,2})x(\d{1,2})/i',            // 1x02
            '/Season\.(\d{1,2})\.Episode\.(\d{1,2})/i'  // Season.1.Episode.2
        ];

        foreach ($tvPatterns as $pattern) {
            if (preg_match($pattern, $fileName, $matches)) {
                $message = "Contenido detectado como serie de TV";
                Log::info($message);
                return [
                    'type' => 'tvshow',
                    'season' => (int)$matches[1],
                    'episode' => (int)$matches[2]
                ];
            }
        }

        $message = "Contenido detectado como película";
        Log::info($message);
        return [
            'type' => 'movie'
        ];
    }

    public function searchSubtitles(Video $video)
    {
        try {
            Log::info("Iniciando búsqueda de subtítulos para: {$video->file_name}");

            // Verificar si ya existe un subtítulo
            if ($this->checkExistingSubtitle($video)) {
                Log::info("Subtítulo existente encontrado para: {$video->file_name}");
                $video->status = 'completed';
                $video->save();
                return true;
            }

            $searchParams = [
                'query' => $video->file_name,
                'languages' => 'spa',
                'moviehash' => $video->hash,
                'moviebytesize' => $video->file_size
            ];

            if ($video->content_type === 'tv_show') {
                $searchParams['season'] = $video->season;
                $searchParams['episode'] = $video->episode;
            }

            $headers = [
                'Api-Key' => $this->apiKey,
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
                'User-Agent' => $this->userAgent
            ];

            Log::info("Parámetros de búsqueda:", $searchParams);
            Log::info("Headers de la petición:", $headers);

            $response = Http::withHeaders($headers)->get($this->baseUrl . '/subtitles', $searchParams);

            Log::info("Respuesta de la API - Status: " . $response->status());
            Log::info("Respuesta de la API - Headers:", $response->headers());
            Log::info("Respuesta de la API - Body:", $response->json());

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['data'])) {
                    Log::info("Subtítulo encontrado para: {$video->file_name}");
                    $subtitle = $data['data'][0];
                    return $this->downloadSubtitle($subtitle['attributes']['files'][0]['file_id'], $video);
                } else {
                    Log::warning("No se encontraron subtítulos para: {$video->file_name}");
                }
            } else {
                Log::error("Error en la respuesta de la API", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error al buscar subtítulos para {$video->file_name}: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function checkExistingSubtitle(Video $video)
    {
        $videoPath = pathinfo($video->file_path);
        $videoDir = $videoPath['dirname'];
        $videoName = $videoPath['filename'];

        // Patrones comunes de nombres de subtítulos
        $patterns = [
            $videoName . '.srt',
            $videoName . '.sub',
            $videoName . '.ssa',
            $videoName . '.ass',
            $videoName . '.vtt',
            // Patrones con idioma
            $videoName . '.es.srt',
            $videoName . '.spa.srt',
            $videoName . '.spanish.srt',
            // Patrones con calidad
            $videoName . '.720p.srt',
            $videoName . '.1080p.srt',
            $videoName . '.WEBRip.srt',
            $videoName . '.BluRay.srt',
            // Patrones combinados
            $videoName . '.720p.es.srt',
            $videoName . '.1080p.spa.srt',
            $videoName . '.WEBRip.spanish.srt'
        ];

        foreach ($patterns as $pattern) {
            $subtitlePath = $videoDir . DIRECTORY_SEPARATOR . $pattern;
            if (file_exists($subtitlePath)) {
                $video->subtitle_path = $subtitlePath;
                $video->language = 'es';
                return true;
            }
        }

        return false;
    }

    protected function downloadSubtitle($fileId, $video)
    {
        Log::info("Iniciando descarga de subtítulo (ID: {$fileId}) para: {$video->file_name}");

        $requestParams = [
            'file_id' => $fileId,
            'sub_format' => 'srt',
            'force_download' => 1
        ];

        $headers = [
            'Api-Key' => $this->apiKey,
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json'
        ];

        Log::info("Parámetros de descarga:", $requestParams);
        Log::info("Headers de descarga:", $headers);

        // Intentar la descarga hasta 3 veces con espera entre intentos
        $maxAttempts = 3;
        $attempt = 1;

        while ($attempt <= $maxAttempts) {
            Log::info("Intento de descarga {$attempt} de {$maxAttempts}");

            $response = Http::withHeaders($headers)->post($this->baseUrl . '/download', $requestParams);

            Log::info("Respuesta de descarga - Status: " . $response->status());
            Log::info("Respuesta de descarga - Headers:", $response->headers());
            Log::info("Respuesta de descarga - Body:", $response->json());

            if ($response->successful()) {
                $responseData = $response->json();

                if (!isset($responseData['link'])) {
                    Log::error("La respuesta no contiene el enlace de descarga del subtítulo", [
                        'response' => $responseData
                    ]);
                    return false;
                }

                // Realizar la descarga del archivo desde el enlace
                Log::info("Descargando archivo desde: " . $responseData['link']);

                $downloadResponse = Http::withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => '*/*'
                ])->get($responseData['link']);

                Log::info("Respuesta de descarga del archivo - Status: " . $downloadResponse->status());
                Log::info("Respuesta de descarga del archivo - Headers:", $downloadResponse->headers());

                if (!$downloadResponse->successful()) {
                    Log::error("Error al descargar el archivo de subtítulo", [
                        'status' => $downloadResponse->status(),
                        'headers' => $downloadResponse->headers()
                    ]);
                    return false;
                }

                $subtitleContent = $downloadResponse->body();
                Log::info("Contenido del subtítulo descargado - Tamaño: " . strlen($subtitleContent) . " bytes");

                // Obtener la ruta del directorio del video
                $videoDir = dirname($video->file_path);

                // Crear el nombre del archivo de subtítulo
                $subtitleFileName = pathinfo($video->file_name, PATHINFO_FILENAME) . '.srt';

                // Ruta completa del subtítulo
                $subtitlePath = $videoDir . DIRECTORY_SEPARATOR . $subtitleFileName;

                Log::info("Guardando subtítulo en: {$subtitlePath}");

                // Guardar el subtítulo en la misma carpeta que el video
                if (file_put_contents($subtitlePath, $subtitleContent)) {
                    Log::info("Subtítulo guardado exitosamente");
                    Log::info("Verificando archivo guardado - Existe: " . (file_exists($subtitlePath) ? 'Sí' : 'No'));
                    Log::info("Verificando archivo guardado - Tamaño: " . filesize($subtitlePath) . " bytes");

                    // Actualizar el modelo con la ruta relativa del subtítulo
                    $video->subtitle_path = $subtitlePath;
                    $video->status = 'completed';
                    $video->save();

                    Log::info("Estado del video actualizado a 'completed'");
                    return true;
                } else {
                    Log::error("Error al guardar el subtítulo", [
                        'path' => $subtitlePath,
                        'permissions' => decoct(fileperms($videoDir) & 0777),
                        'disk_free_space' => disk_free_space($videoDir),
                        'is_writable' => is_writable($videoDir)
                    ]);
                    return false;
                }
            } else {
                Log::error("Error en la respuesta de descarga", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                // Si es un error 406 o rate limit, esperar y reintentar
                if ($response->status() === 406 ||
                    (isset($response->headers()['Ratelimit-Remaining']) && $response->headers()['Ratelimit-Remaining'][0] == 0)) {

                    $waitTime = isset($response->headers()['Ratelimit-Reset']) ?
                        (int)$response->headers()['Ratelimit-Reset'][0] + 1 : 2;

                    Log::warning("Rate limit alcanzado o error 406. Esperando {$waitTime} segundos antes de reintentar...");
                    sleep($waitTime);
                    $attempt++;
                    continue;
                }
                return false;
            }
        }

        Log::error("Se agotaron los intentos de descarga");
        return false;
    }

    public function scanDirectory($path)
    {
        Log::info("Iniciando escaneo del directorio: {$path}");

        $videoExtensions = ['mp4', 'avi', 'mkv', 'mov', 'wmv'];
        $videosFound = 0;
        $videosAdded = 0;
        $videosWithSubtitles = 0;
        $videosSkippedSpanish = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $videoExtensions)) {
                $videosFound++;
                $fileName = $file->getFilename();
                $filePath = $file->getPathname();
                Log::info("Video encontrado: {$fileName}");

                $languageInfo = $this->detectLanguage($filePath);
                $contentInfo = $this->detectContentType($fileName);

                // Verificar si ya existe un subtítulo
                $subtitlePath = $this->findExistingSubtitle($filePath);
                $status = $subtitlePath ? 'completed' : 'pending';

                // Verificar si el video tiene español en alguna pista
                $hasSpanish = false;
                if (!empty($languageInfo['all_languages'])) {
                    foreach ($languageInfo['all_languages'] as $lang) {
                        if ($lang['language'] === 'spa' || $lang['language'] === 'es') {
                            $hasSpanish = true;
                            break;
                        }
                    }
                }

                if ($hasSpanish) {
                    Log::info("Video ignorado por tener audio en español: {$fileName}");
                    $videosSkippedSpanish++;
                    continue;
                }

                $videoData = [
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'language' => $languageInfo['language'],
                    'content_type' => $contentInfo['type'],
                    'season' => $contentInfo['season'] ?? null,
                    'episode' => $contentInfo['episode'] ?? null,
                    'status' => $status,
                    'subtitle_path' => $subtitlePath
                ];

                Log::info("Creando registro de video:", $videoData);

                Video::create($videoData);
                $videosAdded++;
                if ($subtitlePath) {
                    $videosWithSubtitles++;
                }
                Log::info("Video agregado a la base de datos: {$fileName} - Estado: {$status}");
            }
        }

        Log::info("Escaneo completado", [
            'videos_encontrados' => $videosFound,
            'videos_agregados' => $videosAdded,
            'videos_con_subtitulos' => $videosWithSubtitles,
            'videos_ignorados_por_espanol' => $videosSkippedSpanish
        ]);

        return [
            'found' => $videosFound,
            'added' => $videosAdded,
            'with_subtitles' => $videosWithSubtitles,
            'skipped_spanish' => $videosSkippedSpanish
        ];
    }

    private function findExistingSubtitle($videoPath)
    {
        $videoPathInfo = pathinfo($videoPath);
        $videoDir = $videoPathInfo['dirname'];
        $videoName = $videoPathInfo['filename'];

        // Patrones comunes de nombres de subtítulos
        $patterns = [
            $videoName . '.srt',
            $videoName . '.sub',
            $videoName . '.ssa',
            $videoName . '.ass',
            $videoName . '.vtt',
            // Patrones con idioma
            $videoName . '.es.srt',
            $videoName . '.spa.srt',
            $videoName . '.spanish.srt',
            // Patrones con calidad
            $videoName . '.720p.srt',
            $videoName . '.1080p.srt',
            $videoName . '.WEBRip.srt',
            $videoName . '.BluRay.srt',
            // Patrones combinados
            $videoName . '.720p.es.srt',
            $videoName . '.1080p.spa.srt',
            $videoName . '.WEBRip.spanish.srt'
        ];

        foreach ($patterns as $pattern) {
            $subtitlePath = $videoDir . DIRECTORY_SEPARATOR . $pattern;
            if (file_exists($subtitlePath)) {
                Log::info("Subtítulo existente encontrado: {$subtitlePath}");
                return $subtitlePath;
            }
        }

        return null;
    }
}
