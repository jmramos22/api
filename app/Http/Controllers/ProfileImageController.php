<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use function class_exists;

class ProfileImageController extends Controller
{
    public function store(Request $request)
    {
        if (! class_exists(\MongoDB\Driver\Manager::class)) {
            return response()->json([
                'message' => 'La extensión MongoDB no está habilitada en este entorno.',
            ], 500);
        }

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $image = $validated['image'];
        // getKey() extrae automáticamente tu 'id_usuario' de PostgreSQL
        $userId = (string) $request->user()->getKey();

        $write = new BulkWrite;
        $write->update(
            ['user_id' => $userId],
            ['$set' => [
                'user_id' => $userId,
                // Guarda la imagen como código binario nativo en la base de datos
                'image' => new Binary($image->getContent(), Binary::TYPE_GENERIC),
                'mime_type' => $image->getMimeType(),
                'updated_at' => new UTCDateTime,
            ]],
            ['upsert' => true],
        );

        $this->manager()->executeBulkWrite(
            $this->collection(),
            $write,
            ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)],
        );

        return response()->json([
            'message' => 'Fotografía actualizada correctamente.',
            'image_url' => $this->urlFor($request, $userId),
        ]);
    }

    public function destroy(Request $request)
    {
        if (! class_exists(\MongoDB\Driver\Manager::class)) {
            return response()->json([
                'message' => 'La extensión MongoDB no está habilitada en este entorno.',
            ], 500);
        }

        $delete = new BulkWrite;
        $delete->delete(['user_id' => (string) $request->user()->getKey()], ['limit' => 1]);

        $this->manager()->executeBulkWrite(
            $this->collection(),
            $delete,
            ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)],
        );

        return response()->json([
            'message' => 'Fotografía eliminada correctamente.',
        ]);
    }

    public function show(string $userId)
    {
        if (! class_exists(\MongoDB\Driver\Manager::class)) {
            abort(500, 'La extensión MongoDB no está habilitada en este entorno.');
        }

        $query = new Query(['user_id' => $userId], ['limit' => 1]);
        $cursor = $this->manager()->executeQuery($this->collection(), $query);

        foreach ($cursor as $image) {
            if (! isset($image->image, $image->mime_type) || ! $image->image instanceof Binary) {
                abort(404);
            }

            return response($image->image->getData(), 200, [
                'Content-Type' => $image->mime_type,
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404);
    }

    public function urlFor(Request $request, int|string $userId): ?string
    {
        if (! class_exists(\MongoDB\Driver\Manager::class)) {
            return null;
        }

        $query = new Query(['user_id' => (string) $userId], ['limit' => 1]);
        
        try {
            $cursor = $this->manager()->executeQuery($this->collection(), $query);

            foreach ($cursor as $image) {
                if (isset($image->image, $image->mime_type)) {
                    $path = URL::temporarySignedRoute(
                        'api.user.image.show',
                        now()->addDays(7),
                        ['userId' => (string) $userId],
                        false,
                    );

                    return $request->getSchemeAndHttpHost().$path;
                }
            }
        } catch (\Exception $e) {
            // Si MongoDB falla temporalmente, devolvemos null en lugar de colapsar la página web
            return null;
        }

        return null;
    }

    private function manager(): Manager
    {
        // Forzamos a que lea la conexión desde tu archivo .env
        $uri = env('MONGODB_URI', 'mongodb://127.0.0.1:27017');
        return new Manager($uri);
    }

    private function collection(): string
    {
        // Forzamos a que lea el nombre de la BD desde tu archivo .env
        $db = env('MONGODB_DATABASE', 'tutorias_uas_docs');
        return $db . '.profile_images';
    }
}