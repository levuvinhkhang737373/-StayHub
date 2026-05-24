<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminAuthResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * Đăng nhập admin 
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:6', 'max:255'],
            ]);

            $admin = Admin::query()
                ->where('username', $validated['username'])
                ->first();

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Tên đăng nhập hoặc mật khẩu không chính xác', 401, null, 401);
            }

            if (! $admin->status) {
                return ApiResponse::responseJson(false, 'Tài khoản admin đang bị vô hiệu hóa', 403, null, 403);
            }

            if (! Hash::check($validated['password'], $admin->password)) {
                return ApiResponse::responseJson(false, 'Tên đăng nhập hoặc mật khẩu không chính xác', 401, null, 401);
            }

            $this->loginAdminSession($request, $admin);
            $this->writeLoginLog($request, $admin, 'login_success');

            return ApiResponse::responseJson(true, 'Đăng nhập admin thành công', 200, [
                'admin' => new AdminAuthResource($admin->refresh()),
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::responseJson(false, 'Hiện tại tôi không thể xử lí yêu cầu của bạn', 500, null, 500);
        }
    }

    /**
     * Đăng nhập admin bằng FaceID
     */
    public function faceLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images'   => ['required', 'array', 'min:2', 'max:3'],
            'images.*' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $embedding = $this->extractEmbedding($validated['images']);
            $match     = $this->searchFaceEmbedding($embedding);

            if (! $match || ! isset($match['payload']['admin_id'])) {
                return ApiResponse::responseJson(false, 'Không nhận diện được khuôn mặt', 401, null, 401);
            }

            $admin = Admin::query()->find($match['payload']['admin_id']);

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tài khoản admin tương ứng', 404, null, 404);
            }

            if (! filled($admin->image_path_faceid)) {
                $this->deleteFaceEmbedding($admin);
                Storage::disk('s3')->delete("face-credentials/admin/admin-{$admin->id}.jpg");

                return ApiResponse::responseJson(false, 'FaceID chưa được đăng ký hoặc đã bị xóa', 401, null, 401);
            }

            if (! $admin->status) {
                return ApiResponse::responseJson(false, 'Tài khoản admin đang bị vô hiệu hóa', 403, null, 403);
            }

            $this->loginAdminSession($request, $admin);
            AdminActivityLogger::write(
                $admin,
                'face_login_success',
                Admin::class,
                $admin->id,
                null,
                [
                    'score'    => $match['score'] ?? null,
                    'login_at' => now(),
                ],
                $request
            );

            return ApiResponse::responseJson(true, 'Đăng nhập FaceID thành công', 200, [
                'admin' => new AdminAuthResource($admin->refresh()),
            ], 200);
        } catch (\Exception $e) {
            return $this->faceExceptionResponse($e, 'Không thể xử lý FaceID');
        }
    }

    /**
     * Lấy thông tin admin đang đăng nhập .
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            return ApiResponse::responseJson(true, 'Lấy thông tin admin hiện tại thành công', 200, new AdminAuthResource($admin), 200);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Đăng ký FaceID cho admin hiện tại.   
     */
    public function registerFaceId(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images'   => ['required', 'array', 'min:2', 'max:3'],
            'images.*' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            $oldData = $admin->only(['image_path_faceid', 'created_faceid_at', 'updated_faceid_at']);
            $directory = 'face-credentials/admin';
            $fileName = "admin-{$admin->id}.jpg";
            $path = "{$directory}/{$fileName}";
            $image = $validated['images'][0];
            $embedding = $this->extractEmbedding($validated['images']);

            $storedPath = Storage::disk('s3')->putFileAs(
                $directory,
                $image,
                $fileName,
                ['ContentType' => $image->getMimeType() ?: 'image/jpeg']
            );

            if ($storedPath !== $path) {
                throw new \RuntimeException('Không thể lưu ảnh FaceID vào MinIO', 500);
            }

            $this->upsertFaceEmbedding($admin, $embedding);

            $admin->forceFill([
                'image_path_faceid' => $path,
                'created_faceid_at' => $admin->created_faceid_at ?? now(),
                'updated_faceid_at' => now(),
            ])->save();

            AdminActivityLogger::write(
                $admin,
                'register_faceid',
                Admin::class,
                $admin->id,
                $oldData,
                $admin->fresh()->only(['image_path_faceid', 'created_faceid_at', 'updated_faceid_at']),
                $request
            );

            return ApiResponse::responseJson(true, 'Đăng ký FaceID thành công', 200, [
                'admin' => new AdminAuthResource($admin->fresh()),
            ], 200);
        } catch (\Exception $e) {
            return $this->faceExceptionResponse($e, 'Không thể đăng ký FaceID');
        }
    }

    /**
     * Xóa FaceID của admin hiện tại.
     */
    public function deleteFaceId(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => ['required', 'string', 'min:6', 'max:255'],
            ]);

            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            if (! Hash::check($validated['current_password'], $admin->password)) {
                return ApiResponse::responseJson(false, 'Mật khẩu hiện tại không chính xác', 422, null, 422);
            }

            $oldData = $admin->only(['image_path_faceid', 'created_faceid_at', 'updated_faceid_at']);

            if ($admin->image_path_faceid) {
                Storage::disk('s3')->delete($admin->image_path_faceid);
            }

            $this->deleteFaceEmbedding($admin);

            $admin->forceFill([
                'image_path_faceid' => null,
                'created_faceid_at' => null,
                'updated_faceid_at' => null,
            ])->save();

            AdminActivityLogger::write(
                $admin,
                'delete_faceid',
                Admin::class,
                $admin->id,
                $oldData,
                null,
                $request
            );

            return ApiResponse::responseJson(true, 'Xóa FaceID thành công', 200, [
                'admin' => new AdminAuthResource($admin->fresh()),
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::responseJson(false, $e->getMessage() ?: 'Không thể xóa FaceID', 500, null, 500);
        }
    }

    /**
     * Đăng xuất admin, hủy session hiện tại và ghi nhận lịch sử thao tác.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Bạn chưa đăng nhập', 401, null, 401);
            }

            AdminActivityLogger::write(
                $admin,
                'logout',
                Admin::class,
                $admin->id,
                null,
                [
                    'logout_at' => now(),
                ],
                $request
            );

            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::responseJson(true, 'Đăng xuất admin thành công', 200, null, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function loginAdminSession(Request $request, Admin $admin): void
    {
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $request->session()->put(Auth::guard('admin')->getName(), $admin->getAuthIdentifier());
        $request->session()->put('admin_id', $admin->getAuthIdentifier());
        $request->session()->save();
    }

    private function writeLoginLog(Request $request, Admin $admin, string $action): void
    {
        AdminActivityLogger::write($admin, $action, Admin::class, $admin->id, null, null, $request);
    }

    private function faceExceptionResponse(\Exception $e, string $message): JsonResponse
    {
        $errorCode = (int) ($e->getCode() ?: 500);

        return ApiResponse::responseJson(false, $e->getMessage() ?: $message, $errorCode, null, $errorCode);
    }

    private function extractEmbedding(array $images): array
    {
        $request = Http::timeout(90);
        $streams = [];

        foreach ($images as $index => $image) {
            $stream = fopen($image->getRealPath(), 'r');

            if (! is_resource($stream)) {
                throw new \RuntimeException('Không thể đọc ảnh FaceID', 422);
            }

            $streams[] = $stream;
            $request = $request->attach(
                'files',
                $stream,
                $image->getClientOriginalName() ?: "face-{$index}.jpg"
            );
        }

        $response = $request->post(config('services.ai_service.url') . '/api/v1/extract');

        foreach ($streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            $message = $response->json('detail') ?? 'AI service không thể trích xuất khuôn mặt';
            throw new \RuntimeException(is_string($message) ? $message : 'AI service không thể trích xuất khuôn mặt', 422);
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new \RuntimeException('AI service không trả về embedding hợp lệ', 422);
        }

        return array_map('floatval', $embedding);
    }

    private function upsertFaceEmbedding(Admin $admin, array $embedding): void
    {
        $response = Http::timeout(30)->put($this->qdrantUrl('/points?wait=true'), [
            'points' => [[
                'id'      => $admin->id,
                'vector'  => $embedding,
                'payload' => [
                    'admin_id' => $admin->id,
                ],
            ]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Không thể lưu vector FaceID vào Qdrant', 500);
        }
    }

    private function searchFaceEmbedding(array $embedding): ?array
    {
        $response = Http::timeout(30)->post($this->qdrantUrl('/points/search'), [
            'vector'          => $embedding,
            'limit'           => 1,
            'score_threshold' => (float) config('services.qdrant.face_threshold'),
            'with_payload'    => true,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Không thể tìm kiếm FaceID trong Qdrant', 500);
        }

        $result = $response->json('result');

        return is_array($result) && isset($result[0]) ? $result[0] : null;
    }

    private function deleteFaceEmbedding(Admin $admin): void
    {
        $response = Http::timeout(30)->post($this->qdrantUrl('/points/delete?wait=true'), [
            'points' => [$admin->id],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Không thể xóa vector FaceID khỏi Qdrant', 500);
        }
    }

    private function qdrantUrl(string $path): string
    {
        return config('services.qdrant.url') . '/collections/' . config('services.qdrant.face_collection') . $path;
    }
}
