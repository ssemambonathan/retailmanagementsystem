<?php declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
abstract class BaseApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = ['status'=>'success','message'=>$message];
        if ($data !== null) { $payload['data'] = $data; }
        return response()->json($payload, $status);
    }
    protected function created(mixed $data, string $message = 'Created successfully'): JsonResponse { return $this->success($data,$message,201); }
    protected function error(string $message, int $status = 400, string $code = 'ERROR'): JsonResponse
    {
        return response()->json(['status'=>'error','message'=>$message,'code'=>$code],$status);
    }
}
